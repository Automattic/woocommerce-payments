<?php
/**
 * Class WC_Payments_Session.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Platform_Checkout;

use Automattic\Jetpack\Connection\Rest_Authentication;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;
use WCPay\Logger;
use WC_Payments;
use WC_Payments_Features;
use WP_REST_Request;

/**
 * Class responsible for handling platform checkout sessions.
 * This class should be loaded as soon as possible so the correct session is loaded.
 * So don't load it in the WC_Payments::init() function.
 */
class Platform_Checkout_Session {

	const STORE_API_NAMESPACE = 'wc/store/v1';

	/**
	 * Init the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'determine_current_user', [ __CLASS__, 'determine_current_user_for_platform_checkout' ], 20 );
		add_filter( 'rest_request_before_callbacks', [ __CLASS__, 'add_platform_checkout_store_api_session_handler' ], 10, 3 );
	}

	/**
	 * This filter is used to add a custom session handler before processing Store API request callbacks.
	 * This is only necessary because the Store API SessionHandler currently doesn't provide an `init_session_cookie` method.
	 *
	 * @param mixed           $response The response object.
	 * @param mixed           $handler The handler used for the response.
	 * @param WP_REST_Request $request The request used to generate the response.
	 *
	 * @return mixed
	 */
	public static function add_platform_checkout_store_api_session_handler( $response, $handler, WP_REST_Request $request ) {
		$cart_token = $request->get_header( 'Cart-Token' );

		if ( $cart_token && 0 === strpos( $request->get_route(), self::STORE_API_NAMESPACE ) && JsonWebToken::validate( $cart_token, '@' . wp_salt() ) ) {
			add_filter(
				'woocommerce_session_handler',
				function ( $session_handler ) {
					return \WCPay\Platform_Checkout\SessionHandler::class;
				},
				20
			);
		}

		return $response;
	}

	/**
	 * Sets the current user as the user sent via the api from WooPay if present.
	 *
	 * @param \WP_User|null|int $user user to be used during the request.
	 *
	 * @return \WP_User|null|int
	 */
	public static function determine_current_user_for_platform_checkout( $user ) {
		if ( ! self::is_store_api_request() || ! self::is_request_from_woopay() ) {
			return $user;
		}

		if ( ! self::is_woopay_enabled() ) {
			return $user;
		}

		// Validate that the request is signed properly.
		if ( ! self::has_valid_request_signature() ) {
			Logger::log( __( 'WooPay request is not signed correctly.', 'woocommerce-payments' ) );
			wp_die( esc_html__( 'WooPay request is not signed correctly.', 'woocommerce-payments' ), 401 );
		}

		$cart_token_user_id = self::get_user_id_from_cart_token();
		if ( null === $cart_token_user_id ) {
			return $user;
		}

		return $cart_token_user_id;
	}

	/**
	 * Returns the user ID from the cart token.
	 *
	 * @return int|null The User ID or null if there's no cart token in the request.
	 */
	private static function get_user_id_from_cart_token() {
		if ( ! isset( $_SERVER['HTTP_CART_TOKEN'] ) ) {
			return null;
		}

		$cart_token = wc_clean( wp_unslash( $_SERVER['HTTP_CART_TOKEN'] ) );

		if ( $cart_token && JsonWebToken::validate( $cart_token, '@' . wp_salt() ) ) {
			$payload = JsonWebToken::get_parts( $cart_token )->payload;

			if ( empty( $payload ) ) {
				return null;
			}

			if ( self::STORE_API_NAMESPACE !== $payload->iss ) {
				return null;
			}

			$session_handler = new \WCPay\Platform_Checkout\SessionHandler();
			$session_data    = $session_handler->get_session( $payload->user_id );
			$customer        = maybe_unserialize( $session_data['customer'] );

			return is_numeric( $customer['id'] ) ? intval( $customer['id'] ) : null;
		}

		return null;
	}

	/**
	 * Returns true if the request that's currently being processed is a Store API request, false
	 * otherwise.
	 *
	 * @return bool True if request is a Store API request, false otherwise.
	 */
	private static function is_store_api_request(): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}

		/**
		 * The request URI. This comment is here to satisfy Psalm.
		 *
		 * @var string
		 */
		$request_uri = urldecode( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ); // phpcs:ignore

		// Check if the request URI contains a URL traversal.
		if ( strpos( $request_uri . '/', '../' ) !== false ) {
			return false;
		}

		$request_uri   = home_url( esc_url( $request_uri ) );
		$store_api_url = home_url( '/wp-json/wc/store/' );

		return 0 === strpos( $request_uri, $store_api_url );
	}

	/**
	 * Returns true if the request that's currently being processed is from WooPay, false
	 * otherwise.
	 *
	 * @return bool True if request is from WooPay.
	 */
	private static function is_request_from_woopay(): bool {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) && 'WooPay' === $_SERVER['HTTP_USER_AGENT'];
	}

	/**
	 * Returns true if the request that's currently being processed is signed with the blog token.
	 *
	 * @return bool True if the request signature is valid.
	 */
	private static function has_valid_request_signature() {
		return apply_filters( 'wcpay_woopay_is_signed_with_blog_token', Rest_Authentication::is_signed_with_blog_token() );
	}

	/**
	 * Returns true if WooPay is enabled, false otherwise.
	 *
	 * @return bool True if WooPay is enabled, false otherwise.
	 */
	private static function is_woopay_enabled(): bool {
		return WC_Payments_Features::is_platform_checkout_eligible() && 'yes' === WC_Payments::get_gateway()->get_option( 'platform_checkout', 'no' );
	}
}

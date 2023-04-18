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

/**
 * Class responsible for handling platform checkout sessions.
 * This class should be loaded as soon as possible so the correct session is loaded.
 * So don't load it in the WC_Payments::init() function.
 */
class Platform_Checkout_Session {

	/**
	 * Init the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'determine_current_user', [ __CLASS__, 'determine_current_user_for_platform_checkout' ], 20 );
	}

	/**
	 * Sets the current user as the user sent via the api from WooPay if present.
	 *
	 * @param \WP_User|null|int $user user to be used during the request.
	 *
	 * @return \WP_User|null|int
	 */
	public static function determine_current_user_for_platform_checkout( $user ) {
		if ( $user ) {
			return $user;
		}

		if ( ! self::is_store_api_request() || ! self::is_request_from_woopay() ) {
			return $user;
		}

		// Validate that the request is signed properly.
		self::validate_request_signature();

		$cart_token_user_id = self::get_user_id_from_cart_token();
		if ( null === $cart_token_user_id ) {
			return $user;
		}

		return $cart_token_user_id;
	}

	/**
	 * Returns the user ID from the cart token.
	 *
	 * @return int User ID.
	 */
	private static function get_user_id_from_cart_token() {
		if ( ! isset( $_SERVER['HTTP_CART_TOKEN'] ) ) {
			return null;
		}

		$cart_token = wc_clean( wp_unslash( $_SERVER['HTTP_CART_TOKEN'] ) );

		if ( $cart_token ) {
			$payload = JsonWebToken::get_parts( $cart_token )->payload;

			if ( empty( $payload ) ) {
				return null;
			}

			$session_handler = new \Automattic\WooCommerce\StoreApi\SessionHandler();
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
	 * @return bool  True if request is a Store API request, false otherwise.
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
		$request_uri = wc_clean( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

		// Check if the request URI contains a URL traversal.
		if ( strpos( $request_uri, '../' ) !== false ) {
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
	 * @return bool  True if request is from WooPay.
	 */
	private static function is_request_from_woopay(): bool {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) && 'WooPay' === $_SERVER['HTTP_USER_AGENT'];
	}

	/**
	 * Validates the request signature.
	 */
	private static function validate_request_signature() {
		if ( ! apply_filters( 'wcpay_woopay_is_signed_with_blog_token', Rest_Authentication::is_signed_with_blog_token() ) ) {
			Logger::log( __( 'WooPay request is not signed correctly.', 'woocommerce-payments' ) );
			wp_die( esc_html__( 'WooPay request is not signed correctly.', 'woocommerce-payments' ), 401 );
		}
	}
}

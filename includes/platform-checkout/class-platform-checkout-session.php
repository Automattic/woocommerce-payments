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

		if ( ! self::is_store_api_request() || ! self::validate_request_from_woopay() ) {
			return $user;
		}

		return self::get_user_id_from_cart_token();
	}

	/**
	 * Returns the user ID from the cart token.
	 *
	 * @return int User ID.
	 */
	private static function get_user_id_from_cart_token() {
		if ( ! isset( $_SERVER['HTTP_CART_TOKEN'] ) ) {
			return 0;
		}

		$cart_token = wc_clean( wp_unslash( $_SERVER['HTTP_CART_TOKEN'] ) );

		if ( $cart_token ) {
			$payload = JsonWebToken::get_parts( $cart_token )->payload;

			if ( empty( $payload ) ) {
				return 0;
			}

			$session_handler = new \Automattic\WooCommerce\StoreApi\SessionHandler();
			$session_data    = $session_handler->get_session( $payload->user_id );
			$customer        = maybe_unserialize( $session_data['customer'] );

			return (int) $customer['id'];
		}

		return 0;
	}

	/**
	 * Returns true if the request that's currently being processed is a Store API request, false
	 * otherwise.
	 *
	 * @return bool  True if request is a Store API request, false otherwise.
	 */
	private static function is_store_api_request(): bool {
		/**
		 * The request URI. This comment is here to satisfy Psalm.
		 *
		 * @var string
		 */
		$request_uri   = wc_clean( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		$request_uri   = home_url( esc_url( $request_uri ) );
		$store_api_url = home_url( '/wp-json/wc/store/' );

		// Restrict filter to only run on Store API requests.
		return 0 === strpos( $request_uri, $store_api_url );
	}

	/**
	 * Validates that the request is from WooPay and that it's signed with the blog token.
	 *
	 * @return bool  True if request is from WooPay.
	 * @throws \Exception If the request is not signed with the blog token.
	 */
	private static function validate_request_from_woopay(): bool {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) || 'WooPay' !== $_SERVER['HTTP_USER_AGENT'] ) {
			return false;
		}

		if ( ! apply_filters( 'wcpay_woopay_is_signed_with_blog_token', Rest_Authentication::is_signed_with_blog_token() ) ) {
			Logger::log( 'WooPay request is not signed correctly.' );
			throw new \Exception( 'Request is not signed correctly.' );
		}

		return true;
	}
}

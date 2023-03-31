<?php
/**
 * Class WC_Payments_Session.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Platform_Checkout;

use Automattic\Jetpack\Connection\Rest_Authentication;

/**
 * Class responsible for handling platform checkout sessions.
 * This class should be loaded as soon as possible so the correct session is loaded.
 * So don't load it in the WC_Payments::init() function.
 */
class Platform_Checkout_Session {

	const PLATFORM_CHECKOUT_SESSION_COOKIE_NAME = 'platform_checkout_session';

	/**
	 * Init the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'determine_current_user', [ __CLASS__, 'determine_current_user_for_platform_checkout' ], 20 );
		add_filter( 'woocommerce_cookie', [ __CLASS__, 'determine_session_cookie_for_platform_checkout' ], 20 );
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

		if ( ! self::is_store_api_request() || ! apply_filters( 'wcpay_woopay_is_signed_with_blog_token', Rest_Authentication::is_signed_with_blog_token() ) ) {
			return $user;
		}

		if ( ! isset( $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] ) || ! is_numeric( $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] ) ) {
			return null;
		}

		return (int) $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'];
	}

	/**
	 * Tells WC to use platform checkout session cookie if the header is present.
	 *
	 * @param string $cookie_hash Default cookie hash.
	 *
	 * @return string
	 */
	public static function determine_session_cookie_for_platform_checkout( $cookie_hash ) {
		if ( isset( $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] ) && 0 === (int) $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] ) {
			return self::PLATFORM_CHECKOUT_SESSION_COOKIE_NAME;
		}

		return $cookie_hash;
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
}

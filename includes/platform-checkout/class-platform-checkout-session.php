<?php
/**
 * Class WC_Payments_Session.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Platform_Checkout;

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
		add_filter( 'determine_current_user', [ __CLASS__, 'determine_current_user_for_platform_checkout' ] );
		add_filter( 'woocommerce_cookie', [ __CLASS__, 'determine_session_cookie_for_platform_checkout' ] );
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
}

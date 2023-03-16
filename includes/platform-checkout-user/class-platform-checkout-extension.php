<?php
/**
 * Class PlatformCheckoutExtension
 *
 * @package WooCommerce\Payments\PlatformCheckout
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class that registers a blocks extension callback for platform checkout new user.
 */
class Platform_Checkout_Extension {
	const EXTENSION_NAMESPACE           = 'platform-checkout';
	const PLATFORM_CHECKOUT_SESSION_KEY = 'platform-checkout-user-data';

	/**
	 * Registers callback.
	 *
	 * @return void
	 */
	public function register_extend_rest_api_update_callback() {
		if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			woocommerce_store_api_register_update_callback(
				[
					'namespace' => self::EXTENSION_NAMESPACE,
					'callback'  => [ $this, 'store_user_consent_for_platform_checkout' ],
				]
			);
		}
	}

	/**
	 * Checks and stores the value of 'Remember your details' checkbox and phone number for platform checkout
	 *
	 * @param array $data Items to update in session data array.
	 *
	 * @return void
	 */
	public function store_user_consent_for_platform_checkout( array $data ) {
		// Sets the WC customer session if one is not set.
		if ( ! ( isset( WC()->session ) && WC()->session->has_session() ) ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->session->set( self::PLATFORM_CHECKOUT_SESSION_KEY, $data );
	}
}

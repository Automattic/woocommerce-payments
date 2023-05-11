<?php
/**
 * Class WooPayExtension
 *
 * @package WooCommerce\Payments\WooPay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class that registers a blocks extension callback for woopay new user.
 */
class WooPay_Extension {
	const EXTENSION_NAMESPACE = 'woopay';
	const WOOPAY_SESSION_KEY  = 'woopay-user-data';

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
					'callback'  => [ $this, 'store_user_consent_for_woopay' ],
				]
			);
		}
	}

	/**
	 * Checks and stores the value of 'Remember your details' checkbox and phone number for woopay
	 *
	 * @param array $data Items to update in session data array.
	 *
	 * @return void
	 */
	public function store_user_consent_for_woopay( array $data ) {
		// Sets the WC customer session if one is not set.
		if ( ! ( isset( WC()->session ) && WC()->session->has_session() ) ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->session->set( self::WOOPAY_SESSION_KEY, $data );
	}
}

<?php
/**
 * Class WooPaySaveUser
 *
 * @package WooCommerce\Payments\WooPay
 */

defined( 'ABSPATH' ) || exit;

use WCPay\WooPay\WooPay_Utilities;

/**
 * Class that adds a section to save new user for platform checkout on the frontend.
 */
class WooPay_Save_User {

	/**
	 * WooPay utilities.
	 *
	 * @var WooPay_Utilities
	 */
	protected $platform_checkout_util;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->platform_checkout_util = new WooPay_Utilities();

		add_action( 'wp_enqueue_scripts', [ $this, 'register_checkout_page_scripts' ] );
		add_filter( 'wcpay_metadata_from_order', [ $this, 'maybe_add_userdata_to_metadata' ], 10, 2 );
		add_action( 'woocommerce_payment_complete', [ $this, 'maybe_clear_session_key' ], 10, 2 );
	}

	/**
	 * Load scripts and styles for checkout page.
	 */
	public function register_checkout_page_scripts() {
		// Don't enqueue checkout page scripts when WCPay isn't available.
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( ! isset( $gateways['woocommerce_payments'] ) ) {
			return;
		}

		if ( ! $this->platform_checkout_util->is_country_available( $gateways['woocommerce_payments'] ) ) {
			return;
		}

		$style_url = plugins_url( 'dist/platform-checkout.css', WCPAY_PLUGIN_FILE );

		wp_register_style(
			'WCPAY_WOOPAY',
			$style_url,
			[],
			\WC_Payments::get_file_version( 'dist/platform-checkout.css' )
		);
		WC_Payments::register_script_with_dependencies( 'WCPAY_WOOPAY', 'dist/platform-checkout' );

		wp_enqueue_style( 'WCPAY_WOOPAY' );
		wp_enqueue_script( 'WCPAY_WOOPAY' );
	}

	/**
	 * Checks if the user chose to save their data for platform checkout and adds appropriate metadata.
	 *
	 * @param array     $metadata Metadata to be saved.
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public function maybe_add_userdata_to_metadata( $metadata, $order ) {
		$should_save_platform_customer = $this->platform_checkout_util->should_save_platform_customer();
		$platform_checkout_phone       = $this->platform_checkout_util->get_platform_checkout_phone();

		if ( $should_save_platform_customer && $platform_checkout_phone ) {
			$platform_checkout_source_url = $this->platform_checkout_util->get_platform_checkout_source_url();
			$platform_checkout_is_blocks  = $this->platform_checkout_util->get_platform_checkout_is_blocks();
			$platform_checkout_viewport   = $this->platform_checkout_util->get_platform_checkout_viewport();

			// Add the metadata.
			$metadata['platform_checkout_primary_first_name']   = wc_clean( $order->get_billing_first_name() );
			$metadata['platform_checkout_primary_last_name']    = wc_clean( $order->get_billing_last_name() );
			$metadata['platform_checkout_primary_phone']        = wc_clean( $order->get_billing_phone() );
			$metadata['platform_checkout_primary_company']      = wc_clean( $order->get_billing_company() );
			$metadata['platform_checkout_secondary_first_name'] = wc_clean( $order->get_shipping_first_name() );
			$metadata['platform_checkout_secondary_last_name']  = wc_clean( $order->get_shipping_last_name() );
			$metadata['platform_checkout_secondary_phone']      = wc_clean( $order->get_shipping_phone() );
			$metadata['platform_checkout_secondary_company']    = wc_clean( $order->get_shipping_company() );
			$metadata['platform_checkout_phone']                = $platform_checkout_phone;
			$metadata['platform_checkout_source_url']           = $platform_checkout_source_url;
			$metadata['platform_checkout_is_blocks']            = $platform_checkout_is_blocks;
			$metadata['platform_checkout_viewport']             = $platform_checkout_viewport;
		}

		return $metadata;
	}

	/**
	 * Clears if platform checkout user data is set.
	 *
	 * @return void
	 */
	public function maybe_clear_session_key() {
		$session_data = WC()->session->get( WooPay_Extension::WOOPAY_SESSION_KEY );

		if ( ! empty( $session_data ) ) {
			WC()->session->__unset( WooPay_Extension::WOOPAY_SESSION_KEY );
		}
	}
}

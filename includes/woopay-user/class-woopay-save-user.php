<?php
/**
 * Class WooPaySaveUser
 *
 * @package WooCommerce\Payments\WooPay
 */

defined( 'ABSPATH' ) || exit;

use WCPay\WooPay\WooPay_Session;
use WCPay\WooPay\WooPay_Utilities;

/**
 * Class that adds a section to save new user for woopay on the frontend.
 */
class WooPay_Save_User {

	/**
	 * WooPay utilities.
	 *
	 * @var WooPay_Utilities
	 */
	protected $woopay_util;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->woopay_util = new WooPay_Utilities();

		add_action( 'wp_enqueue_scripts', [ $this, 'register_checkout_page_scripts' ] );
		add_filter( 'wcpay_metadata_from_order', [ $this, 'maybe_add_userdata_to_metadata' ], 10, 2 );
		add_action( 'woocommerce_payment_complete', [ $this, 'maybe_clear_session_key' ], 10, 2 );
	}

	/**
	 * Load scripts and styles for checkout page.
	 */
	public function register_checkout_page_scripts() {
		if ( ! is_checkout() && ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		// Don't enqueue checkout page scripts when WCPay isn't available.
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( ! isset( $gateways['woocommerce_payments'] ) ) {
			return;
		}

		if ( ! $this->woopay_util->is_country_available() ) {
			return;
		}

		$style_url = plugins_url( 'dist/woopay.css', WCPAY_PLUGIN_FILE );

		WC_Payments_Utils::enqueue_style(
			'WCPAY_WOOPAY',
			$style_url,
			[],
			\WC_Payments::get_file_version( 'dist/woopay.css' ),
			'all'
		);
		WC_Payments::register_script_with_dependencies( 'WCPAY_WOOPAY', 'dist/woopay' );

		$account_data = WC_Payments::get_account_service()->get_cached_account_data();

		wp_localize_script(
			'WCPAY_WOOPAY',
			'woopayCheckout',
			[
				'PRE_CHECK_SAVE_MY_INFO' => isset( $account_data['pre_check_save_my_info'] ) ? $account_data['pre_check_save_my_info'] : false,
			]
		);

		wp_enqueue_script( 'WCPAY_WOOPAY' );
	}

	/**
	 * Checks if the user chose to save their data for woopay and adds appropriate metadata.
	 *
	 * @param array     $metadata Metadata to be saved.
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public function maybe_add_userdata_to_metadata( $metadata, $order ) {
		$should_save_platform_customer = $this->woopay_util->should_save_platform_customer();
		$woopay_phone                  = $this->woopay_util->get_woopay_phone();

		if ( $should_save_platform_customer && $woopay_phone ) {
			$woopay_source_url = $this->woopay_util->get_woopay_source_url();
			$woopay_is_blocks  = $this->woopay_util->get_woopay_is_blocks();
			$woopay_viewport   = $this->woopay_util->get_woopay_viewport();

			// Add the metadata.
			$metadata['platform_checkout_primary_first_name']   = wc_clean( $order->get_billing_first_name() );
			$metadata['platform_checkout_primary_last_name']    = wc_clean( $order->get_billing_last_name() );
			$metadata['platform_checkout_primary_phone']        = wc_clean( $order->get_billing_phone() );
			$metadata['platform_checkout_primary_company']      = wc_clean( $order->get_billing_company() );
			$metadata['platform_checkout_secondary_first_name'] = wc_clean( $order->get_shipping_first_name() );
			$metadata['platform_checkout_secondary_last_name']  = wc_clean( $order->get_shipping_last_name() );
			$metadata['platform_checkout_secondary_phone']      = wc_clean( $order->get_shipping_phone() );
			$metadata['platform_checkout_secondary_company']    = wc_clean( $order->get_shipping_company() );
			$metadata['platform_checkout_phone']                = $woopay_phone;
			$metadata['platform_checkout_source_url']           = $woopay_source_url;
			$metadata['platform_checkout_is_blocks']            = $woopay_is_blocks;
			$metadata['platform_checkout_viewport']             = $woopay_viewport;
		}

		return $metadata;
	}

	/**
	 * Clears if woopay user data is set.
	 *
	 * @return void
	 */
	public function maybe_clear_session_key() {
		$session_data = WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY );

		if ( ! empty( $session_data ) ) {
			WC()->session->__unset( WooPay_Session::WOOPAY_SESSION_KEY );
		}
	}
}

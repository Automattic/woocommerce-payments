<?php
/**
 * Overwrites the default payment settings sections in WooCommerce
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Admin Class.
 */
class WC_Payments_Admin_Sections_Overwrite {
	/**
	 * WC_Payments_Admin_Sections_Overwrite constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_settings_page_init', [ $this, 'page_init' ] );
		add_filter( 'woocommerce_get_sections_checkout', [ $this, 'add_checkout_sections' ] );
	}

	/**
	 * Checks the current page in wp-admin to ensure we're looking at the payment gateways settings page.
	 *
	 * @return bool
	 */
	private function is_default_payments_settings_section_request() {
		global $current_tab, $plugin_page;

		if ( 'wc-settings' !== $plugin_page ) {
			return false;
		}

		if ( 'checkout' !== $current_tab ) {
			return false;
		}

		// $_REQUEST['section'] can be not set at all or an empty string
		// in the case of empty string, the current section requested would be the one with the list of all the gateways
		if ( isset( $_REQUEST['section'] ) ) { // phpcs:ignore
			return false;
		}

		return true;
	}

	/**
	 * Overwrites the current section on init so our plugins page is loaded by default.
	 */
	public function page_init() {
		global $current_section;

		// no need to make any changes to the global variables if we're not in the correct WC page.
		if ( ! $this->is_default_payments_settings_section_request() ) {
			return;
		}

		$current_section = 'woocommerce_payments';
	}

	/**
	 * Adds an "all payment methods" and a "woocommerce payments" section to the gateways settings page
	 *
	 * @param array $sections the sections for the payment gateways tab.
	 *
	 * @return array
	 */
	public function add_checkout_sections( array $sections ): array {
		$sections['woocommerce_payments'] = __( 'WooCommerce Payments', 'woocommerce-payments' );

		// unsetting and setting again, so it appears last in the array.
		unset( $sections[''] );
		$sections[''] = __( 'All payment methods', 'woocommerce-payments' );

		return $sections;
	}
}

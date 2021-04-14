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
	private $has_page_been_overridden = false;

	public function __construct() {
		add_action( 'woocommerce_settings_page_init', [ $this, 'page_init' ] );
		add_filter( 'woocommerce_get_sections_checkout', [ $this, 'add_checkout_sections' ] );
		add_action( 'woocommerce_sections_checkout', [ $this, 'first' ], 5 );
		add_action( 'woocommerce_sections_checkout', [ $this, 'second' ], 15 );
	}

	private function is_current_page_payment_methods() {
		global $current_tab, $plugin_page;
		if ( 'wc-settings' !== $plugin_page ) {
			return false;
		}

		if ( 'checkout' !== $current_tab ) {
			return false;
		}

		return true;
	}

	/**
	 * Overwrites the current section on init so our plugins page is loaded by default.
	 */
	public function page_init() {
		global $current_section;

		// no need to make any changes to the global variables if we're not in the correct WC page
		if ( ! $this->is_current_page_payment_methods() ) {
			return;
		}

//		var_dump($current_section);

		// overwrites the global variables so that the hooks are going to render the WC Pay settings by default
		if ( '' === $current_section && ! isset($_POST['section']) ) {
			$current_section = 'woocommerce_payments';
		} elseif ( $current_section === 'all-gateways' ) {
			$current_section = '';
		}
	}

	/**
	 * Adds an "all payment methods" and a "woocommerce payments" section to the gateways settings page
	 * @param array $sections
	 *
	 * @return array
	 */
	public function add_checkout_sections( array $sections ): array {
		unset( $sections[''] );

		$sections['woocommerce_payments'] = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$sections['all-gateways'] = __( 'All payment methods', 'woocommerce-payments' );

		return $sections;
	}

	/**
	 * Called before outputting the section list and the section's output - useful to set the active section styles
	 */
	public function first() {
		global $current_section;
		if ( ! $this->is_current_page_payment_methods() ) {
			return;
		}

		if ( $current_section === '' ) {
			$this->has_page_been_overridden = true;

			$current_section = 'all-gateways';
		}
	}

	/**
	 * Called after outputting the section's output - resetting the values possibly set by the previous overwrite method
	 */
	public function second() {
		global $current_section;
		if ( ! $this->has_page_been_overridden ) {
			return;
		}

		// ensures that on POST (save on the "all gateways" page), the page rendered on redirect is accurate
		?><input type="hidden" name="section" value="" /><?php

		$current_section = '';
	}
}

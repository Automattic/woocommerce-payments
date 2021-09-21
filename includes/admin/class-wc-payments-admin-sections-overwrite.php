<?php
/**
 * Overwrites the default payment settings sections in WooCommerce
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Admin_Sections_Overwrite Class.
 */
class WC_Payments_Admin_Sections_Overwrite {
	/**
	 * Used for restoring the original value of `$current_section` global variable.
	 *
	 * @var string
	 */
	private $previous_current_section;

	/**
	 * WC_Payments_Account instance to get information about the account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payments_Admin_Sections_Overwrite constructor.
	 *
	 * @param WC_Payments_Account $account WC_Payments_Account instance.
	 */
	public function __construct( WC_Payments_Account $account ) {
		$this->account = $account;

		add_filter( 'woocommerce_get_sections_checkout', [ $this, 'add_checkout_sections' ] );

		add_action( 'woocommerce_sections_checkout', [ $this, 'overwrite_current_section_global' ], 5 );
		add_action( 'woocommerce_sections_checkout', [ $this, 'restore_current_section_global' ], 15 );

		// Before rendering the "Settings" page.
		add_action( 'woocommerce_settings_start', [ $this, 'add_overwrite_payments_tab_url_filter' ] );

		// After outputting tabs on the "Settings" page.
		add_action( 'woocommerce_settings_tabs', [ $this, 'remove_overwrite_payments_tab_url_filter' ] );
	}

	/**
	 * Determines whether the account is connected or not.
	 *
	 * @return bool
	 */
	public function is_account_disconnected() {
		return empty( $this->account->get_cached_account_data() );
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

	/**
	 * Overwrites `$current_section`.
	 *
	 * Called before rendering the section list to render the "WooCommerce
	 * Payments" section as active on payment method settings pages.
	 */
	public function overwrite_current_section_global() {
		if ( $this->is_account_disconnected() ) {
			return;
		}

		global $current_section;

		$this->previous_current_section = $current_section;
		if ( WC_Payments_Utils::is_payments_settings_page() ) {
			$current_section = 'woocommerce_payments';
		}
	}

	/**
	 * Resets `$current_section` to its original value.
	 */
	public function restore_current_section_global() {
		if ( $this->is_account_disconnected() ) {
			return;
		}

		global $current_section;

		$current_section = $this->previous_current_section;
	}

	/**
	 * Add the callback to overwrite the Payments tab URL to the `admin_url` filter.
	 */
	public function add_overwrite_payments_tab_url_filter() {
		if ( $this->is_account_disconnected() ) {
			return;
		}

		add_filter( 'admin_url', [ $this, 'overwrite_payments_tab_url' ], 100, 2 );
	}

	/**
	 * Remove the callback to overwrite the Payments tab URL from the `admin_url` filter.
	 */
	public function remove_overwrite_payments_tab_url_filter() {
		if ( $this->is_account_disconnected() ) {
			return;
		}

		remove_filter( 'admin_url', [ $this, 'overwrite_payments_tab_url' ], 100 );
	}

	/**
	 * Overwrite the Payments tab URL.
	 *
	 * @param string $url The URL to overwrite.
	 * @param string $path Path relative to the admin area URL.
	 *
	 * @return string
	 */
	public function overwrite_payments_tab_url( $url, $path ): string {
		if ( 'admin.php?page=wc-settings&tab=checkout' === $path ) {
			return add_query_arg( [ 'section' => 'woocommerce_payments' ], $url );
		}

		return $url;
	}
}

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
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'woocommerce_get_sections_checkout', [ $this, 'add_checkout_sections' ] );
	}

	/**
	 * Adds an "all payment methods" and a "woopayments" section to the gateways settings page
	 *
	 * @param array $default_sections the sections for the payment gateways tab.
	 *
	 * @return array
	 */
	public function add_checkout_sections( array $default_sections ): array {
		$sections_to_render['woocommerce_payments'] = 'WooPayments';
		$sections_to_render['']                     = __( 'All payment methods', 'woocommerce-payments' );

		return $sections_to_render;
	}
}

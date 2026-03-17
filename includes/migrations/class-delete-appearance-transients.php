<?php
/**
 * Class Delete_Appearance_Transients
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Delete_Appearance_Transients
 *
 * Removes stale UPE appearance transients that were previously used to cache
 * Stripe Elements appearance data. The server-side transient caching mechanism
 * has been removed to prevent cache poisoning attacks.
 *
 * @since 10.6.0
 */
class Delete_Appearance_Transients {
	/**
	 * Checks whether it's worth doing the migration.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( '10.6.0', $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Does the actual migration — deletes all appearance transients.
	 */
	private function migrate() {
		$transient_names = [
			'wcpay_upe_appearance',
			'wcpay_upe_add_payment_method_appearance',
			'wcpay_wc_blocks_upe_appearance',
			'wcpay_upe_bnpl_product_page_appearance',
			'wcpay_upe_bnpl_classic_cart_appearance',
			'wcpay_upe_bnpl_cart_block_appearance',
			'wcpay_upe_appearance_theme',
			'wcpay_upe_add_payment_method_appearance_theme',
			'wcpay_wc_blocks_upe_appearance_theme',
			'wcpay_upe_bnpl_product_page_appearance_theme',
			'wcpay_upe_bnpl_classic_cart_appearance_theme',
			'wcpay_upe_bnpl_cart_block_appearance_theme',
		];

		foreach ( $transient_names as $transient ) {
			delete_transient( $transient );
		}
	}
}

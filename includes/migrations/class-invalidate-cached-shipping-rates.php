<?php
/**
 * Class Invalidate_Cached_Shipping_Rates
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Refreshes WooCommerce's shipping cache version, which is part of every package hash, so rates cached before
 * store-currency packages were left unmarked are rebuilt rather than reused.
 *
 * @since 11.3.0
 */
class Invalidate_Cached_Shipping_Rates {

	/**
	 * Invalidates the cached rates when upgrading from a version before the store-currency packages were left unmarked.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version', '' );
		if ( version_compare( '11.3.0', $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Refreshes WooCommerce's shipping cache version. Running it again only costs one more rate rebuild per cart.
	 */
	public function migrate(): void {
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}

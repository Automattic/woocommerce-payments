<?php
/**
 * Class WooCommerceUPS
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce UPS Plugin.
 */
class WooCommerceUPS extends BaseCompatibility {

	/**
	 * Init the class.
	 *
	 * @return  void
	 */
	public function init() {
		// Add needed actions and filters if UPS is active.
		if ( class_exists( 'WC_Shipping_UPS_Init' ) ) {
			add_filter( MultiCurrency::FILTER_PREFIX . 'should_return_store_currency', [ $this, 'should_return_store_currency' ] );
		}
	}

	/**
	 * Determine whether to return the store currency or not.
	 *
	 * @param bool $should_return Whether to return the store currency or not.
	 *
	 * @return bool
	 */
	public function should_return_store_currency( bool $should_return ): bool {
		// If it's already true, return it.
		if ( $should_return ) {
			return $should_return;
		}

		if ( $this->utils->is_in_shipping_method_rates( 'WC_Shipping_UPS' ) ) {
			return true;
		}

		return $should_return;
	}
}

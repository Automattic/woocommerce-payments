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
	protected function init() {
		// Add needed actions and filters if UPS is active.
		if ( class_exists( 'WC_Shipping_UPS_Init' ) ) {
			add_filter( MultiCurrency::FILTER_PREFIX . 'should_return_store_currency', [ $this, 'should_return_store_currency' ] );
		}
	}

	/**
	 * Determine whether to return the store currency or not.
	 *
	 * @param bool $return Whether to return the store currency or not.
	 *
	 * @return bool
	 */
	public function should_return_store_currency( bool $return ): bool {
		// If it's already true, return it.
		if ( $return ) {
			return $return;
		}

		$calls = [
			'WC_Shipping_UPS->per_item_shipping',
			'WC_Shipping_UPS->box_shipping',
			'WC_Shipping_UPS->calculate_shipping',
		];
		if ( $this->utils->is_call_in_backtrace( $calls ) ) {
			return true;
		}

		return $return;
	}
}

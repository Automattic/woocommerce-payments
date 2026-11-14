<?php
/**
 * Class WooCommerceFedEx
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\MultiCurrency;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce FedEx Plugin.
 */
class WooCommerceFedEx extends BaseCompatibility {

	/**
	 * Init the class.
	 *
	 * @return void
	 */
	public function init() {
		// Add needed actions and filters if FedEx is active.
		if ( class_exists( 'WC_Shipping_Fedex_Init' ) ) {
			add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_product_price', [ $this, 'should_convert_product_price' ] );
			add_filter( MultiCurrency::FILTER_PREFIX . 'should_return_store_currency', [ $this, 'should_return_store_currency' ] );
		}
	}

	/**
	 * Checks to see if the product's price should be converted.
	 *
	 * @param bool $should_convert Whether to convert the product's price or not. Default is true.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( bool $should_convert ): bool {
		// If it's already false, return it.
		if ( ! $should_convert ) {
			return $should_convert;
		}

		if ( $this->utils->is_in_shipping_method_rates( 'WC_Shipping_Fedex' ) ) {
			return false;
		}

		return $should_convert;
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

		if ( $this->utils->is_in_shipping_method_rates( 'WC_Shipping_Fedex' ) ) {
			return true;
		}

		return $should_return;
	}
}

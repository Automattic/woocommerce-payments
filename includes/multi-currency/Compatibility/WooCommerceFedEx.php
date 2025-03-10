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
	 * Calls to look for in the backtrace when determining whether
	 * to return store currency or skip converting product prices.
	 */
	private const WC_SHIPPING_FEDEX_CALLS = [
		'WC_Shipping_Fedex->set_settings',
		'WC_Shipping_Fedex->per_item_shipping',
		'WC_Shipping_Fedex->box_shipping',
		'WC_Shipping_Fedex->get_fedex_api_request',
		'WC_Shipping_Fedex->get_fedex_requests',
		'WC_Shipping_Fedex->process_result',
	];

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
	 * @param bool $return Whether to convert the product's price or not. Default is true.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( bool $return ): bool {
		// If it's already false, return it.
		if ( ! $return ) {
			return $return;
		}

		if ( $this->utils->is_call_in_backtrace( self::WC_SHIPPING_FEDEX_CALLS ) ) {
			return false;
		}

		return $return;
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

		if ( $this->utils->is_call_in_backtrace( self::WC_SHIPPING_FEDEX_CALLS ) ) {
			return true;
		}

		return $return;
	}
}

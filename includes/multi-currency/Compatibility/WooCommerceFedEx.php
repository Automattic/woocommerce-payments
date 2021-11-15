<?php
/**
 * Class WooCommerceFedEx
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce FedEx Plugin.
 */
class WooCommerceFedEx extends BaseCompatibility {

	/**
	 * Init the class.
	 *
	 * @return void
	 */
	protected function init() {
		// Add needed actions and filters if FedEx is active.
		if ( class_exists( 'WC_Shipping_Fedex_Init' ) ) {
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
			'WC_Shipping_Fedex->set_settings',
			'WC_Shipping_Fedex->per_item_shipping',
			'WC_Shipping_Fedex->box_shipping',
			'WC_Shipping_Fedex->get_fedex_api_request',
			'WC_Shipping_Fedex->get_fedex_requests',
			'WC_Shipping_Fedex->process_result',
		];
		if ( $this->utils->is_call_in_backtrace( $calls ) ) {
			return true;
		}

		return $return;
	}
}

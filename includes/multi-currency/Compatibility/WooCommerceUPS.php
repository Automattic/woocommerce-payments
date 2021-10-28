<?php
/**
 * Class WooCommerceUPS
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WC_Product;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce UPS Plugin.
 */
class WooCommerceUPS {

	const FILTER_PREFIX = 'wcpay_multi_currency_';

	/**
	 * MultiCurrency class.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * Utils class.
	 *
	 * @var Utils
	 */
	private $utils;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency MultiCurrency class.
	 * @param Utils         $utils Utils class.
	 */
	public function __construct( MultiCurrency $multi_currency, Utils $utils ) {
		$this->multi_currency = $multi_currency;
		$this->utils          = $utils;
		$this->initialize_hooks();
	}

	/**
	 * Adds compatibility filters if the plugin exists and loaded
	 *
	 * @return  void
	 */
	protected function initialize_hooks() {
		if ( class_exists( 'WC_Shipping_UPS_Init' ) ) {
			add_filter( self::FILTER_PREFIX . 'should_return_store_currency', [ $this, 'should_return_store_currency' ] );
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

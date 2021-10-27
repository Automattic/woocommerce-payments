<?php
/**
 * Class WooCommerceBookings
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WC_Product;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Bookings Plugin.
 */
class WooCommerceBookings {

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
		if ( class_exists( 'WC_Bookings' ) ) {
			add_filter( 'woocommerce_product_get_block_cost', [ $this, 'get_price' ], 50, 1 );
			add_filter( 'woocommerce_product_get_cost', [ $this, 'get_price' ], 50, 1 );
			add_filter( 'woocommerce_product_get_display_cost', [ $this, 'get_price' ], 50, 1 );
			add_filter( 'woocommerce_product_booking_person_type_get_block_cost', [ $this, 'get_price' ], 50, 1 );
			add_filter( 'woocommerce_product_booking_person_type_get_cost', [ $this, 'get_price' ], 50, 1 );
			add_filter( 'woocommerce_product_get_resource_base_costs', [ $this, 'get_resource_prices' ], 50, 1 );
			add_filter( 'woocommerce_product_get_resource_block_costs', [ $this, 'get_resource_prices' ], 50, 1 );
			add_filter( self::FILTER_PREFIX . 'should_convert_product_price', [ $this, 'should_convert_product_price' ] );
		}
	}

	/**
	 * Returns the price for an item.
	 *
	 * @param mixed $price The item's price.
	 *
	 * @return mixed The converted item's price.
	 */
	public function get_price( $price ) {
		if ( ! $price ) {
			return $price;
		}
		return $this->multi_currency->get_price( $price, 'product' );
	}

	/**
	 * Returns the prices for a resource.
	 *
	 * @param mixed $prices The resource's prices in array format.
	 *
	 * @return mixed The converted resource's prices.
	 */
	public function get_resource_prices( $prices ) {
		if ( is_array( $prices ) ) {
			foreach ( $prices as $key => $price ) {
				$prices[ $key ] = $this->get_price( $price );
			}
		}
		return $prices;
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

		if ( $this->utils->is_call_in_backtrace( [ 'WC_Product_Booking->get_price' ] ) ) {
			$calls = [
				'WC_Cart_Totals->calculate_item_totals',
				'WC_Cart->get_product_price',
				'WC_Cart->get_product_subtotal',
			];
			if ( $this->utils->is_call_in_backtrace( $calls ) ) {
				return false;
			}
		}

		return $return;
	}
}

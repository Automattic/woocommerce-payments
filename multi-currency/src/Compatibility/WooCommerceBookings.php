<?php
/**
 * Class WooCommerceBookings
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\FrontendCurrencies;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Bookings Plugin.
 */
class WooCommerceBookings extends BaseCompatibility {
	/**
	 * Front-end currencies.
	 *
	 * @var FrontendCurrencies
	 */
	private $frontend_currencies;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency      $multi_currency      MultiCurrency class.
	 * @param Utils              $utils               Utils class.
	 * @param FrontendCurrencies $frontend_currencies FrontendCurrencies class.
	 */
	public function __construct( MultiCurrency $multi_currency, Utils $utils, FrontendCurrencies $frontend_currencies ) {
		parent::__construct( $multi_currency, $utils );
		$this->frontend_currencies = $frontend_currencies;
	}

	/**
	 * Init the class.
	 *
	 * @return void
	 */
	protected function init() {
		// Add needed actions and filters if Bookings is active.
		if ( class_exists( 'WC_Bookings' ) ) {
			if ( ! is_admin() || wp_doing_ajax() ) {
				add_filter( 'woocommerce_product_get_block_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_get_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_get_display_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_booking_person_type_get_block_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_booking_person_type_get_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_get_resource_base_costs', [ $this, 'get_resource_prices' ], 50, 1 );
				add_filter( 'woocommerce_product_get_resource_block_costs', [ $this, 'get_resource_prices' ], 50, 1 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_product_price', [ $this, 'should_convert_product_price' ] );
				add_action( 'wp_ajax_wc_bookings_calculate_costs', [ $this, 'add_wc_price_args_filter_for_ajax' ], 9 );
				add_action( 'wp_ajax_nopriv_wc_bookings_calculate_costs', [ $this, 'add_wc_price_args_filter_for_ajax' ], 9 );
			}
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

		// This prevents a double conversion of the price in the cart.
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

		// Fixes price display on product page and in shop.
		if ( $this->utils->is_call_in_backtrace( [ 'WC_Product_Booking->get_price_html' ] ) ) {
			return false;
		}

		return $return;
	}

	/**
	 * Adds a filter for when there is an ajax call to calculate the booking cost.
	 *
	 * @return void
	 */
	public function add_wc_price_args_filter_for_ajax() {
		add_filter( 'wc_price_args', [ $this, 'filter_wc_price_args' ], 100 );
	}

	/**
	 * Returns the formatting arguments to use when a booking price is calculated on the product.
	 *
	 * @param array $args Original args from wc_price().
	 *
	 * @return array New arguments matching the selected currency.
	 */
	public function filter_wc_price_args( $args ): array {
		return wp_parse_args(
			[
				'currency'           => $this->multi_currency->get_selected_currency()->get_code(),
				'decimal_separator'  => $this->frontend_currencies->get_price_decimal_separator( $args['decimal_separator'] ),
				'thousand_separator' => $this->frontend_currencies->get_price_thousand_separator( $args['thousand_separator'] ),
				'decimals'           => $this->frontend_currencies->get_price_decimals( $args['decimals'] ),
				'price_format'       => $this->frontend_currencies->get_woocommerce_price_format( $args['price_format'] ),
			],
			$args
		);
	}
}

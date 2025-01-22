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
use WC_Product;

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
	public function init() {
		// Add needed actions and filters if Bookings is active.
		if ( class_exists( 'WC_Bookings' ) ) {
			if ( ! is_admin() || wp_doing_ajax() ) {
				add_filter( 'woocommerce_bookings_calculated_booking_cost', [ $this, 'adjust_amount_for_calculated_booking_cost' ], 50, 1 );
				add_filter( 'woocommerce_product_get_block_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_get_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_get_display_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_booking_person_type_get_block_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_booking_person_type_get_cost', [ $this, 'get_price' ], 50, 1 );
				add_filter( 'woocommerce_product_get_resource_base_costs', [ $this, 'get_resource_prices' ], 50, 1 );
				add_filter( 'woocommerce_product_get_resource_block_costs', [ $this, 'get_resource_prices' ], 50, 1 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_product_price', [ $this, 'should_convert_product_price' ], 50, 2 );
				add_action( 'wp_ajax_wc_bookings_calculate_costs', [ $this, 'add_wc_price_args_filter_for_ajax' ], 9 );
				add_action( 'wp_ajax_nopriv_wc_bookings_calculate_costs', [ $this, 'add_wc_price_args_filter_for_ajax' ], 9 );
			}
		}
	}

	/**
	 * Adjusts the calculated booking cost for the selected currency, applying rounding and charm pricing as necessary.
	 *
	 * @param mixed $costs The original calculated booking costs.
	 * @return mixed The booking cost adjusted for the selected currency.
	 */
	public function adjust_amount_for_calculated_booking_cost( $costs ) {
		/**
		 * Prevents adjustment of the calculated booking cost during cart addition.
		 *
		 * When a booking is added to the cart, the Booking plugin calculates the booking cost and
		 * overrides the cart item price with this calculated amount. To avoid interfering with this process,
		 * this function skips any additional adjustments at this stage.
		 */
		if ( $this->utils->is_call_in_backtrace( [ 'WC_Cart->add_to_cart' ] ) ) {
			return $costs;
		}

		return $this->multi_currency->adjust_amount_for_selected_currency( $costs );
	}

	/**
	 * Retrieves the price for an item, converting it based on the selected currency and context.
	 *
	 * @param mixed $price The item's price.
	 *
	 * @return mixed The converted item's price.
	 */
	public function get_price( $price ) {
		if ( ! $price ) {
			return $price;
		}

		// Skip conversion during specific booking cost calculations to avoid double conversion.
		if ( $this->utils->is_call_in_backtrace( [ 'WC_Cart->add_to_cart' ] ) && $this->utils->is_call_in_backtrace( [ 'WC_Bookings_Cost_Calculation::calculate_booking_cost' ] ) ) {
			return $price;
		}

		/**
		 * When showing the price in HTML, the function applies currency conversion, charm pricing,
		 * and rounding. For internal calculations, it uses the raw exchange rate, with charm pricing
		 * and rounding adjustments applied only to the final calculated amount (handled in
		 * adjust_amount_for_calculated_booking_cost).
		 */
		return $this->multi_currency->get_price( $price, $this->utils->is_call_in_backtrace( [ 'WC_Product_Booking->get_price_html' ] ) ? 'product' : 'exchange_rate' );
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
	 * @param bool       $return Whether to convert the product's price or not. Default is true.
	 * @param WC_Product $product The product instance being checked.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( bool $return, WC_Product $product ): bool {
		// If it's already false, or the product is not a booking, ignore it.
		if ( ! $return || $product->get_type() !== 'booking' ) {
			return $return;
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

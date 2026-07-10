<?php
/**
 * Class WC_Payments_Explicit_Price_Formatter
 *
 * @package WooCommerce\Payments
 */

use WCPay\MultiCurrency\MultiCurrency;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for displaying the explicit prices on total amounts.
 *
 * Also used for consistent rendering of price values with currency (get_explicit_price_with_currency()).
 */
class WC_Payments_Explicit_Price_Formatter {

	/**
	 * The Multi-Currency instance for checking the number of enabled currencies
	 *
	 * @var MultiCurrency
	 */
	private static $multi_currency_instance = null;

	/**
	 * Inits the formatter, registering the necessary hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_cart_total', [ __CLASS__, 'get_explicit_price' ], 100 );
		add_filter( 'woocommerce_get_formatted_order_total', [ __CLASS__, 'get_explicit_price' ], 100, 2 );
		add_action( 'woocommerce_admin_order_totals_after_tax', [ __CLASS__, 'register_formatted_woocommerce_price_filter' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ __CLASS__, 'unregister_formatted_woocommerce_price_filter' ] );
	}

	/**
	 * Overrides the MultiCurrency instance that the class uses.
	 * Mostly for testing purposes.
	 *
	 * @param   MultiCurrency $multi_currency  MultCurrency class.
	 *
	 * @return  void
	 */
	public static function set_multi_currency_instance( MultiCurrency $multi_currency ) {
		self::$multi_currency_instance = $multi_currency;
	}

	/**
	 * Checks if the method should output explicit price
	 *
	 * @return  bool  Whether if it should return explicit price or not
	 */
	public static function should_output_explicit_price() {
		$should_output_explicit_price = self::is_explicit_price_required();

		/**
		 * Filters whether the explicit price (currency code suffix) should be
		 * displayed alongside the currency symbol on total amounts.
		 *
		 * By default, when Multi-Currency is enabled with additional currencies,
		 * totals are rendered with an explicit currency code (e.g. "765 Kč CZK")
		 * to make the charged currency unambiguous. Merchants who only use
		 * currencies with distinct symbols can return `false` to display the
		 * symbol only (e.g. "765 Kč"). Returning `true` forces the explicit
		 * price on regardless of the default checks.
		 *
		 * @since 10.9.0
		 *
		 * @param bool $should_output_explicit_price Whether the explicit currency code suffix should be output.
		 */
		return (bool) apply_filters( 'wcpay_multi_currency_should_output_explicit_price', $should_output_explicit_price );
	}

	/**
	 * Registers the get_explicit_price filter for the order details screen.
	 *
	 * There are no hooks that enable us to filter the output on the order details screen.
	 * So, we need to add a filter to formatted_woocommerce_price. We use specific actions
	 * to register and unregister the filter, so that only the appropriate prices are affected.
	 */
	public static function register_formatted_woocommerce_price_filter() {
		add_filter( 'wc_price_args', [ __CLASS__, 'get_explicit_price_args' ], 100 );
	}

	/**
	 * Unregisters the get_explicit_price filter for the order details screen.
	 *
	 * There are no hooks that enable us to filter the output on the order details screen.
	 * So, we need to add a filter to formatted_woocommerce_price. We use specific actions
	 * to register and unregister the filter, so that only the appropriate prices are affected.
	 */
	public static function unregister_formatted_woocommerce_price_filter() {
		remove_filter( 'wc_price_args', [ __CLASS__, 'get_explicit_price_args' ], 100 );
	}

	/**
	 * Returns the price suffixed with the appropriate currency code, if not already.
	 *
	 * @param string                 $price The price.
	 * @param WC_Abstract_Order|null $order The order.
	 *
	 * @return string
	 */
	public static function get_explicit_price( string $price, ?WC_Abstract_Order $order = null ) {
		if ( null === $order ) {
			$currency_code = get_woocommerce_currency();
		} else {
			$currency_code = $order->get_currency();
		}

		return static::get_explicit_price_with_currency( $price, $currency_code );
	}

	/**
	 * Returns a formatted price string suffixed with the appropriate currency code (if necessary).
	 *
	 * In multi-currency stores, order and price values are rendered with currency suffix.
	 * This method only renders the currency suffix if appropriate (see `should_output_explicit_price`).
	 *
	 * @param string  $price A price value (as a string).
	 * @param ?string $currency_code Currency of the price.
	 *
	 * @return string Price value with currency code suffix if necessary.
	 */
	public static function get_explicit_price_with_currency( string $price, ?string $currency_code ) {
		if ( false === static::should_output_explicit_price() ) {
			return $price;
		}
		if ( empty( $currency_code ) ) {
			return $price;
		}

		$price_to_check = html_entity_decode( wp_strip_all_tags( $price ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 );

		if ( false === strpos( $price_to_check, trim( $currency_code ) ) ) {
			return $price . ' ' . $currency_code;
		}

		return $price;
	}

	/**
	 * Alters the price formatting arguments to include explicit format
	 *
	 * @param   array $args  Price formatting args passed through `wc_price_args` filter.
	 *
	 * @return  array        The modified arguments
	 */
	public static function get_explicit_price_args( $args ) {
		if ( false === static::should_output_explicit_price() ) {
			return $args;
		}
		if ( false === strpos( $args['price_format'], $args['currency'] ) ) {
			$args['price_format'] = sprintf( '%s&nbsp;%s', $args['price_format'], $args['currency'] );
		}
		return $args;
	}

	/**
	 * Determines, based on the Multi-Currency configuration, whether the explicit
	 * price should be output by default (before the filter is applied).
	 *
	 * @return  bool  Whether the explicit price is required by the current configuration.
	 */
	private static function is_explicit_price_required() {
		// If customer Multi-Currency is disabled, don't use explicit currencies.
		// Because it'll have only the store currency active, same as count == 1.
		if ( ! WC_Payments_Features::is_customer_multi_currency_enabled() ) {
			return false;
		}

		// If the MultiCurrency instance hasn't been defined yet, fetch the instance.
		if ( null === self::$multi_currency_instance ) {
			self::$multi_currency_instance = WC_Payments_Multi_Currency();
		}

		// If the instance isn't initialized yet, skip the checks.
		if ( ! self::$multi_currency_instance->is_initialized() ) {
			return false;
		}

		// If no additional currencies are enabled, skip it.
		if ( ! self::$multi_currency_instance->has_additional_currencies_enabled() ) {
			return false;
		}

		return true;
	}
}

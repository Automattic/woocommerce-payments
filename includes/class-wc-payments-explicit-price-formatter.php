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
	 * Checks if the method should output explicit price on frontend
	 *
	 * @return  bool  Whether if it should return explicit price or not
	 */
	private static function should_output_explicit_price() {
		// As is_admin() returns false for REST requests, we need to skip those checks for REST requests for backend too.
		$is_backend_request = (
			// Current URL is an admın URL.
			( 0 === stripos( wp_get_referer(), admin_url() ) )
			// The current request is a REST request.
			&& WC()->is_rest_api_request()
		);

		$is_customer_email = doing_action( 'woocommerce_email_order_details' );

		// Only apply this for frontend.
		if ( ( ! is_admin() && ! defined( 'DOING_CRON' ) && ! $is_backend_request ) || $is_customer_email ) {
			// If customer Multi-Currency is disabled, don't use explicit currencies on frontend.
			// Because it'll have only the store currency active, same as count == 1.
			if ( ! WC_Payments_Features::is_customer_multi_currency_enabled() ) {
				return false;
			}

			// If the MultiCurrency instance hasn't been defined yet, fetch the instance.
			if ( null === self::$multi_currency_instance ) {
				self::$multi_currency_instance = WC_Payments_Multi_Currency();
			}

			// If the instance isn't initalized yet, skip the checks.
			if ( ! self::$multi_currency_instance->is_initialized() ) {
				return false;
			}

			$enabled_currencies = self::$multi_currency_instance->get_enabled_currencies();

			// If there isn't any enabled currency, skip it.
			if ( empty( $enabled_currencies ) ) {
				return false;
			}

			// Don't attach explicit price filters on frontend with a single currency setup.
			if ( is_array( $enabled_currencies ) && 1 === count( $enabled_currencies ) ) {
				return false;
			}
		}

		return true;
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
	public static function get_explicit_price( string $price, WC_Abstract_Order $order = null ) {
		if ( false === static::should_output_explicit_price() ) {
			return $price;
		}
		if ( null === $order ) {
			$currency_code = get_woocommerce_currency();
		} else {
			$currency_code = $order->get_currency();
		}

		if ( ! empty( $currency_code ) ) {
			$price_to_check = html_entity_decode( wp_strip_all_tags( $price ) );

			if ( false === strpos( $price_to_check, trim( $currency_code ) ) ) {
				return $price . ' ' . $currency_code;
			}
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
}

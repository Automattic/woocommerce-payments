<?php
/**
 * Class WC_Payments_Explicit_Price_Formatter
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for displaying the explicit prices on total amounts.
 */
class WC_Payments_Explicit_Price_Formatter {
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
	 * Registers the get_explicit_price filter for the order details screen.
	 *
	 * There are no hooks that enable us to filter the output on the order details screen.
	 * So, we need to add a filter to formatted_woocommerce_price. We use specific actions
	 * to register and unregister the filter, so that only the appropriate prices are affected.
	 */
	public function register_formatted_woocommerce_price_filter() {
		add_filter( 'formatted_woocommerce_price', [ __CLASS__, 'get_explicit_price' ] );
	}

	/**
	 * Unregisters the get_explicit_price filter for the order details screen.
	 *
	 * There are no hooks that enable us to filter the output on the order details screen.
	 * So, we need to add a filter to formatted_woocommerce_price. We use specific actions
	 * to register and unregister the filter, so that only the appropriate prices are affected.
	 */
	public function unregister_formatted_woocommerce_price_filter() {
		remove_filter( 'formatted_woocommerce_price', [ __CLASS__, 'get_explicit_price' ] );
	}

	/**
	 * Returns the price suffixed with the appropriate currency code, if not already.
	 *
	 * @param string        $price The price.
	 * @param WC_Order|null $order The order.
	 *
	 * @return string
	 */
	public static function get_explicit_price( string $price, WC_Order $order = null ) {
		if ( null === $order ) {
			$currency_code = get_woocommerce_currency();
		} else {
			$currency_code = $order->get_currency();
		}

		if ( substr( $price, - strlen( $currency_code ) ) === $currency_code ) {
			return $price;
		}

		return $price . ' ' . $currency_code;
	}
}

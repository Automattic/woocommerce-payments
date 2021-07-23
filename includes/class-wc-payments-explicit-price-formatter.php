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

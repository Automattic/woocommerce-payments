<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency Compatibility.
 */
class Compatibility {

	/**
	 * Utils class.
	 *
	 * @var Utils
	 */
	private $utils;

	/**
	 * Constructor.
	 *
	 * @param Utils $utils Utils class.
	 */
	public function __construct( Utils $utils ) {
		$this->utils = $utils;
	}

	/**
	 * Checks to see if the if the selected currency needs to be overridden.
	 *
	 * @return mixed Three letter currency code or false if not.
	 */
	public function override_selected_currency() {
		$subscription_renewal = $this->cart_contains_renewal();
		if ( $subscription_renewal ) {
			return get_post_meta( $subscription_renewal['subscription_renewal']['renewal_order_id'], '_order_currency', true );
		}

		return false;
	}

	/**
	 * Checks to see if the widgets should be hidden.
	 *
	 * @return bool False if it shouldn't be hidden, true if it should.
	 */
	public function should_hide_widgets(): bool {
		return $this->cart_contains_renewal() ? true : false;
	}

	/**
	 * Checks to see if the product's price should be converted.
	 *
	 * @param object $product Product object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( $product = null ): bool {
		// If we have a product, and it's a subscription renewal.
		if ( $product && $this->is_product_subscription_renewal( $product ) ) {
			$calls = [
				'WC_Cart_Totals->calculate_item_totals',
				'WC_Cart->get_product_subtotal',
				'wc_get_price_excluding_tax',
				'wc_get_price_including_tax',
			];
			if ( $this->utils->is_call_in_backtrace( $calls ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks the cart to see if it contains a subscription product renewal.
	 *
	 * @return mixed The cart item containing the renewal as an array, else false.
	 */
	private function cart_contains_renewal() {
		if ( ! function_exists( 'wcs_cart_contains_renewal' ) ) {
			return false;
		}
		return wcs_cart_contains_renewal();
	}

	/**
	 * Checks to see if the product passed is in the cart as a subscription renewal.
	 *
	 * @param object $product Product to test.
	 *
	 * @return bool True if it's a subscription renewal in the cart, false if not.
	 */
	private function is_product_subscription_renewal( $product ): bool {
		$subscription_renewal = $this->cart_contains_renewal();
		if ( $subscription_renewal && $product ) {
			if ( ( isset( $subscription_renewal['variation_id'] ) && $subscription_renewal['variation_id'] === $product->get_id() )
				|| $subscription_renewal['product_id'] === $product->get_id() ) {
				return true;
			}
		}
		return false;
	}
}

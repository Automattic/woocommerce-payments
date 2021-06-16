<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\Multi_Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency Compatibility.
 */
class Compatibility {
	/**
	 * Multi-Currency instance.
	 *
	 * @var Multi_Currency
	 */
	protected $multi_currency;

	/**
	 * Constructor.
	 *
	 * @param Multi_Currency $multi_currency The Multi_Currency instance.
	 */
	public function __construct( Multi_Currency $multi_currency ) {
		$this->multi_currency = $multi_currency;
	}

	/**
	 * Checks the cart to see if it contains a subscription product renewal.
	 *
	 * @return mixed The cart item containing the renewal as an array, else false.
	 */
	public function cart_contains_renewal() {
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
	public function is_product_subscription_renewal( $product ): bool {
		$subscription_renewal = $this->cart_contains_renewal();
		if ( $subscription_renewal && $product ) {
			if ( ( isset( $subscription_renewal['variation_id'] ) && $subscription_renewal['variation_id'] === $product->get_id() )
				|| $subscription_renewal['product_id'] === $product->get_id() ) {
				return true;
			}
		}
		return false;
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
	 * @return mixed False if it shouldn't be hidden.
	 */
	public function should_hide_widgets() {
		return $this->cart_contains_renewal();
	}

	/**
	 * Checks to see if the product's price should be converted.
	 *
	 * @param object       $product Product object to test.
	 * @param float|string $price Price being converted.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( $product = null, $price = 0 ): bool {

		// If we have a product, and it's a subscription renewal.
		if ( $product && $this->is_product_subscription_renewal( $product ) ) {
			$calls = [
				'WC_Cart_Totals->calculate_item_totals',
				'WC_Cart->get_product_subtotal',
				'wc_get_price_excluding_tax',
				'wc_get_price_including_tax',
			];
			if ( $this->is_call_in_backtrace( $calls ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks backtrace calls to see if a certain call has been made.
	 *
	 * @param array $calls Array of the calls to check for.
	 *
	 * @return bool True if found, false if not.
	 */
	public function is_call_in_backtrace( array $calls ): bool {
		$backtrace = wp_debug_backtrace_summary( null, 0, false ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		foreach ( $calls as $call ) {
			if ( in_array( $call, $backtrace, true ) ) {
				return true;
			}
		}
		return false;
	}
}

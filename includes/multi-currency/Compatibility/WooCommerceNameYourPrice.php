<?php
/**
 * Class WooCommerceNameYourPrice
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\MultiCurrency;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Name Your Price Plugin.
 */
class WooCommerceNameYourPrice extends BaseCompatibility {

	const NYP_CURRENCY = '_wcpay_multi_currency_nyp_currency';

	/**
	 * Init the class.
	 *
	 * @return  void
	 */
	protected function init() {
		// Add needed actions and filters if Name Your Price is active.
		if ( class_exists( 'WC_Name_Your_Price' ) ) {
			// Convert meta prices.
			add_filter( 'wc_nyp_raw_minimum_price', [ $this, 'get_nyp_prices' ] );
			add_filter( 'wc_nyp_raw_maximum_price', [ $this, 'get_nyp_prices' ] );
			add_filter( 'wc_nyp_raw_suggested_price', [ $this, 'get_nyp_prices' ] );

			// Maybe translate cart prices.
			add_action( 'woocommerce_add_cart_item_data', [ $this, 'add_initial_currency' ], 20, 3 );
			add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'convert_cart_currency' ], 20, 2 );
			add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_product_price', [ $this, 'should_convert_product_price' ], 50, 2 );
		}
	}

	/**
	 * Converts the min/max/suggested prices of Name Your Price extension.
	 *
	 * @param mixed $price   The price to be filtered.
	 * @return mixed         The price as a string or float.
	 */
	public function get_nyp_prices( $price ) {
		return ! $price ? $price : $this->multi_currency->get_price( $price, 'product' );
	}

	/**
	 * Store the inintial currency when item is added.
	 *
	 * @param array $cart_item Extra cart item data being passed to the cart item.
	 * @param int   $product_id The id of the product being added to the cart.
	 * @param int   $variation_id The id of the variation being added to the cart.
	 *
	 * @return array
	 */
	public function add_initial_currency( $cart_item, $product_id, $variation_id ) {

		$nyp_id = $variation_id ? $variation_id : $product_id;

		if ( \WC_Name_Your_Price_Helpers::is_nyp( $nyp_id ) && isset( $cart_item['nyp'] ) ) {
			$currency                  = $this->multi_currency->get_selected_currency();
			$cart_item['nyp_currency'] = $currency->get_code();
			$cart_item['nyp_original'] = $cart_item['nyp'];
		}

		return $cart_item;
	}

	/**
	 * Switch the cart price when currency changes.
	 * Do not convert price if in the same currency as when added to the cart.
	 * This prevevents USD > EUR > USD style conversions and potential rounding problems.
	 *
	 * @param array $cart_item Cart item array.
	 * @param array $values Cart item values e.g. quantity and product_id.
	 *
	 * @return array
	 */
	public function convert_cart_currency( $cart_item, $values ) {

		if ( isset( $cart_item['nyp_original'] ) && isset( $cart_item['nyp_currency'] ) ) {

			// Store the original currency in $product meta.
			$cart_item['data']->update_meta_data( self::NYP_CURRENCY, $cart_item['nyp_currency'] );

			$selected_currency = $this->multi_currency->get_selected_currency();

			// If the currency is currently the same as at time price entered, restore NYP to original value.
			if ( $cart_item['nyp_currency'] === $selected_currency->get_code() ) {
				$cart_item['nyp'] = $cart_item['nyp_original'];
			} else {

				$nyp_currency = $this->multi_currency->get_enabled_currencies()[ $cart_item['nyp_currency'] ] ?? null;

				// Convert entered price back to default currency.
				$converted_price = ( (float) $cart_item['nyp_original'] ) / $nyp_currency->get_rate();

				if ( ! $selected_currency->get_is_default() ) {
					$converted_price = $this->multi_currency->get_price( $converted_price, 'product' );
				}

				$cart_item['nyp'] = $converted_price;
			}

			$cart_item = WC_Name_Your_Price()->cart->set_cart_item( $cart_item );

		}

		return $cart_item;

	}

	/**
	 * Checks to see if the product's price should be converted.
	 *
	 * @param bool   $return  Whether to convert the product's price or not. Default is true.
	 * @param object $product Product object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( bool $return, $product ): bool {
		// If it's already false, return it.
		if ( ! $return ) {
			return $return;
		}

		$currency = $this->multi_currency->get_selected_currency();

		// Check for cart items to see if they are in the original currency.
		if ( $currency->get_code() === $product->get_meta( self::NYP_CURRENCY ) ) {
			$return = false;
		}

		// Check to see if the product is a NYP product.
		if ( \WC_Name_Your_Price_Helpers::is_nyp( $product ) ) {
			return false;
		}

		return $return;
	}
}

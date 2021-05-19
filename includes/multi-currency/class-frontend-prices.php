<?php
/**
 * WooCommerce Payments Multi Currency Frontend Prices
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Multi_Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that applies Multi Currency prices on the frontend.
 */
class Frontend_Prices {
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

		// Currency hooks.
		add_filter( 'woocommerce_currency', [ $this, 'get_current_currency_code' ] );

		// Simple product price hooks.
		add_filter( 'woocommerce_product_get_price', [ $this, 'get_product_price' ] );
		add_filter( 'woocommerce_product_get_regular_price', [ $this, 'get_product_price' ] );
		add_filter( 'woocommerce_product_get_sale_price', [ $this, 'get_product_price' ] );

		// Variation price hooks.
		add_filter( 'woocommerce_product_variation_get_price', [ $this, 'get_product_price' ] );
		add_filter( 'woocommerce_product_variation_get_regular_price', [ $this, 'get_product_price' ] );
		add_filter( 'woocommerce_product_variation_get_sale_price', [ $this, 'get_product_price' ] );

		// Variation price range hooks.
		add_filter( 'woocommerce_variation_prices', [ $this, 'get_variation_price_range' ] );
		add_filter( 'woocommerce_get_variation_prices_hash', [ $this, 'add_exchange_rate_to_variation_prices_hash' ] );
	}

	/**
	 * Returns the currency code to be used by WooCommerce.
	 *
	 * @return string The code of the currency to be used.
	 */
	public function get_current_currency_code() {
		return $this->multi_currency->get_selected_currency()->get_code();
	}

	/**
	 * Returns the price for a product.
	 *
	 * @param mixed $price The product's price.
	 *
	 * @return float The converted product's price.
	 */
	public function get_product_price( $price ) {
		return $this->multi_currency->get_price( $price );
	}

	/**
	 * Returns the price range for a variation.
	 *
	 * @param array $variation_prices The variation's prices.
	 *
	 * @return array The converted variation's prices.
	 */
	public function get_variation_price_range( $variation_prices ) {
		foreach ( $variation_prices as $price_type => $prices ) {
			foreach ( $prices as $variation_id => $price ) {
				$variation_prices[ $price_type ][ $variation_id ] = $this->multi_currency->get_price( $price );
			}
		}

		return $variation_prices;
	}

	/**
	 * Add the exchange rate into account for the variation prices hash.
	 * This is used to recalculate the variation price range when the exchange
	 * rate changes, otherwise the old prices will be cached.
	 *
	 * @param array $prices_hash The variation prices hash.
	 *
	 * @return array The variation prices hash with the current exchange rate.
	 */
	public function add_exchange_rate_to_variation_prices_hash( $prices_hash ) {
		$prices_hash[] = $this->get_product_price( 1 );
		return $prices_hash;
	}
}

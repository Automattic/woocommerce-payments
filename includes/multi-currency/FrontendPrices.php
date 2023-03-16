<?php
/**
 * WooCommerce Payments Multi-Currency Frontend Prices
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Class that applies Multi-Currency prices on the frontend.
 */
class FrontendPrices {
	/**
	 * Compatibility instance.
	 *
	 * @var Compatibility
	 */
	protected $compatibility;

	/**
	 * Multi-Currency instance.
	 *
	 * @var MultiCurrency
	 */
	protected $multi_currency;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency The MultiCurrency instance.
	 * @param Compatibility $compatibility The Compatibility instance.
	 */
	public function __construct( MultiCurrency $multi_currency, Compatibility $compatibility ) {
		$this->multi_currency = $multi_currency;
		$this->compatibility  = $compatibility;

		if ( ! is_admin() && ! defined( 'DOING_CRON' ) && ! Utils::is_admin_api_request() ) {
			// Simple product price hooks.
			add_filter( 'woocommerce_product_get_price', [ $this, 'get_product_price_string' ], 900, 2 );
			add_filter( 'woocommerce_product_get_regular_price', [ $this, 'get_product_price_string' ], 900, 2 );
			add_filter( 'woocommerce_product_get_sale_price', [ $this, 'get_product_price_string' ], 900, 2 );

			// Variation price hooks.
			add_filter( 'woocommerce_product_variation_get_price', [ $this, 'get_product_price_string' ], 900, 2 );
			add_filter( 'woocommerce_product_variation_get_regular_price', [ $this, 'get_product_price_string' ], 900, 2 );
			add_filter( 'woocommerce_product_variation_get_sale_price', [ $this, 'get_product_price_string' ], 900, 2 );

			// Variation price range hooks.
			add_filter( 'woocommerce_variation_prices', [ $this, 'get_variation_price_range' ], 900 );
			add_filter( 'woocommerce_get_variation_prices_hash', [ $this, 'add_exchange_rate_to_variation_prices_hash' ], 900 );

			// Shipping methods hooks.
			add_action( 'init', [ $this, 'register_free_shipping_filters' ], 900 );
			add_filter( 'woocommerce_shipping_method_add_rate_args', [ $this, 'convert_shipping_method_rate_cost' ], 900 );

			// Coupon hooks.
			add_filter( 'woocommerce_coupon_get_amount', [ $this, 'get_coupon_amount' ], 900, 2 );
			add_filter( 'woocommerce_coupon_get_minimum_amount', [ $this, 'get_coupon_min_max_amount' ], 900 );
			add_filter( 'woocommerce_coupon_get_maximum_amount', [ $this, 'get_coupon_min_max_amount' ], 900 );

			// Order hooks.
			add_filter( 'woocommerce_new_order', [ $this, 'add_order_meta' ], 900, 2 );
		}
	}

	/**
	 * Returns the price for a product.
	 *
	 * @param mixed $price The product's price.
	 * @param mixed $product WC_Product or null.
	 *
	 * @return mixed The converted product's price.
	 */
	public function get_product_price( $price, $product = null ) {
		if ( ! $price || ! $this->compatibility->should_convert_product_price( $product ) ) {
			return $price;
		}

		return $this->multi_currency->get_price( $price, 'product' );
	}

	/**
	 * Returns the stringified price for a product.
	 *
	 * @param mixed $price The product's price.
	 * @param mixed $product WC_Product or null.
	 *
	 * @return string The converted product's price.
	 */
	public function get_product_price_string( $price, $product = null ): string {
		return (string) $this->get_product_price( $price, $product );
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
				$variation_prices[ $price_type ][ $variation_id ] = $this->get_product_price_string( $price );
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

	/**
	 * Returns the shipping add rate args with cost converted.
	 *
	 * @param array $args Shipping rate args.
	 *
	 * @return array Shipping rate args with converted cost.
	 */
	public function convert_shipping_method_rate_cost( $args ) {
		$cost = is_array( $args['cost'] ) ? array_sum( $args['cost'] ) : $args['cost'];
		$args = wp_parse_args(
			[
				'cost' => $this->multi_currency->get_price( $cost, 'shipping' ),
			],
			$args
		);
		return $args;
	}

	/**
	 * Returns the amount for a coupon.
	 *
	 * @param mixed  $amount The coupon's amount.
	 * @param object $coupon The coupon object.
	 *
	 * @return mixed The converted coupon's amount.
	 */
	public function get_coupon_amount( $amount, $coupon ) {
		$percent_coupon_types = [ 'percent' ];

		if ( ! $amount
			|| $coupon->is_type( $percent_coupon_types )
			|| ! $this->compatibility->should_convert_coupon_amount( $coupon ) ) {
			return $amount;
		}

		return $this->multi_currency->get_price( $amount, 'coupon' );
	}

	/**
	 * Returns the min or max amount for a coupon.
	 *
	 * @param mixed $amount The coupon's min or max amount.
	 *
	 * @return mixed The converted coupon's min or max amount.
	 */
	public function get_coupon_min_max_amount( $amount ) {
		if ( ! $amount ) {
			return $amount;
		}

		// Coupon mix/max prices are treated as products to avoid inconsistencies with charm pricing
		// making a coupon invalid when the coupon min/max amount is the same as the product's price.
		return $this->multi_currency->get_price( $amount, 'product' );
	}

	/**
	 * Returns the free shipping zone settings with converted min_amount.
	 *
	 * @param array $data The shipping zone settings.
	 *
	 * @return array The shipping zone settings with converted min_amount.
	 */
	public function get_free_shipping_min_amount( $data ) {
		if ( empty( $data['min_amount'] ) ) {
			return $data;
		}

		// Free shipping min amount is treated as products to avoid inconsistencies with charm pricing
		// making a method invalid when its min amount is the same as the product's price.
		$data['min_amount'] = $this->multi_currency->get_price( $data['min_amount'], 'product' );
		return $data;
	}

	/**
	 * Register the hooks to set the min amount for free shipping methods.
	 */
	public function register_free_shipping_filters() {
		$shipping_zones = \WC_Shipping_Zones::get_zones();

		$default_zone = \WC_Shipping_Zones::get_zone( 0 );
		if ( $default_zone ) {
			$shipping_zones[] = [ 'shipping_methods' => $default_zone->get_shipping_methods() ];
		}

		foreach ( $shipping_zones as $shipping_zone ) {
			foreach ( $shipping_zone['shipping_methods'] as $shipping_method ) {
				if ( 'free_shipping' === $shipping_method->id ) {
					$option_name = 'option_woocommerce_' . trim( $shipping_method->id ) . '_' . (int) $shipping_method->instance_id . '_settings';
					add_filter( $option_name, [ $this, 'get_free_shipping_min_amount' ], 900 );
				}
			}
		}
	}

	/**
	 * Adds the exchange rate and default currency to the order's meta if prices have been converted.
	 *
	 * @param int      $order_id The order ID.
	 * @param WC_Order $order    The order object.
	 */
	public function add_order_meta( $order_id, $order ) {
		$default_currency = $this->multi_currency->get_default_currency();

		// Do not add exchange rate if order was made in the store's default currency.
		if ( $default_currency->get_code() === $order->get_currency() ) {
			return;
		}

		$exchange_rate = $this->multi_currency->get_price( 1, 'exchange_rate' );

		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', $exchange_rate );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', $default_currency->get_code() );
		$order->save_meta_data();
	}
}

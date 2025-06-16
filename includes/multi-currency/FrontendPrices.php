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
	}

	/**
	 * Initializes this class' WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! is_admin() && ! defined( 'DOING_CRON' ) && ! Utils::is_admin_api_request() ) {
			// Simple product price hooks.
			add_filter( 'woocommerce_product_get_price', [ $this, 'get_product_price_string' ], 99, 2 );
			add_filter( 'woocommerce_product_get_regular_price', [ $this, 'get_product_price_string' ], 99, 2 );
			add_filter( 'woocommerce_product_get_sale_price', [ $this, 'get_product_price_string' ], 99, 2 );

			// Variation price hooks.
			add_filter( 'woocommerce_product_variation_get_price', [ $this, 'get_product_price_string' ], 99, 2 );
			add_filter( 'woocommerce_product_variation_get_regular_price', [ $this, 'get_product_price_string' ], 99, 2 );
			add_filter( 'woocommerce_product_variation_get_sale_price', [ $this, 'get_product_price_string' ], 99, 2 );

			// Variation price range hooks.
			add_filter( 'woocommerce_variation_prices', [ $this, 'get_variation_price_range' ], 99 );
			add_filter( 'woocommerce_get_variation_prices_hash', [ $this, 'add_exchange_rate_to_variation_prices_hash' ], 99 );

			// Shipping methods hooks.
			add_action( 'init', [ $this, 'register_free_shipping_filters' ], 99 );
			add_filter( 'woocommerce_shipping_method_add_rate_args', [ $this, 'convert_shipping_method_rate_cost' ], 99 );

			// Coupon hooks.
			add_filter( 'woocommerce_coupon_get_amount', [ $this, 'get_coupon_amount' ], 99, 2 );
			add_filter( 'woocommerce_coupon_get_minimum_amount', [ $this, 'get_coupon_min_max_amount' ], 99 );
			add_filter( 'woocommerce_coupon_get_maximum_amount', [ $this, 'get_coupon_min_max_amount' ], 99 );

			// Order hooks.
			add_filter( 'woocommerce_new_order', [ $this, 'add_order_meta' ], 99, 2 );

			// Price Filter Hooks.
			add_filter( 'rest_post_dispatch', [ $this, 'maybe_modify_price_ranges_rest_response' ], 10, 3 );
			add_filter( 'query_loop_block_query_vars', [ $this, 'maybe_modify_price_ranges_query_var' ], 10, 3 );
		}
	}

	/**
	 * Modifies the price range query parameters when the selected currency is not the same as the store currency.
	 *
	 * This method converts the '_price' parameters based on the selected currency.
	 *
	 * @param array     $query The current query variables.
	 * @param \WP_Block $block The current block instance.
	 * @param int       $page  The current page number.
	 *
	 * @return array The modified query variables.
	 */
	public function maybe_modify_price_ranges_query_var( $query, $block, $page ) {
		if ( 'product' !== $query['post_type'] ) {
			return $query;
		}

		if ( empty( $query['meta_query'] ) || ! is_array( $query['meta_query'] ) ) {
			return $query;
		}

		$store_currency    = $this->multi_currency->get_default_currency()->get_code();
		$selected_currency = $this->multi_currency->get_selected_currency()->get_code();

		// If currencies are the same, no need to convert prices in the query.
		if ( $store_currency === $selected_currency ) {
			return $query;
		}

		// Traverse and modify the meta_query array.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$query['meta_query'] = $this->convert_meta_query_price_filters( $query['meta_query'], $store_currency, $selected_currency );

		return $query;
	}

	/**
	 * Recursively traverses and modifies the meta_query array to adjust '_price' values
	 * from the 'from_currency' to the 'target_currency'.
	 *
	 * @param array  $meta_query       The meta_query array to traverse.
	 * @param string $from_currency   The from currency code.
	 * @param string $target_currency The target currency code.
	 * @param int    $depth           The current depth of the recursion.
	 *
	 * @return array The modified meta_query array.
	 */
	private function convert_meta_query_price_filters( $meta_query, $from_currency, $target_currency, $depth = 0 ) {
		// Prevent infinite recursion in a malformed meta_query.
		if ( $depth > 4 ) {
			return $meta_query;
		}

		foreach ( $meta_query as &$mq ) {
			// If the current element is a nested meta_query with a relation.
			if ( isset( $mq['relation'] ) && is_array( $mq ) ) {
				// Recursively modify the nested meta_query.
				if ( isset( $mq['relation'] ) ) {
					// Extract the relation and the nested queries.
					$relation = $mq['relation'];

					$modified_nested = $this->convert_meta_query_price_filters( $mq, $from_currency, $target_currency, $depth + 1 );

					// Reconstruct the meta_query with the modified nested queries.
					$mq = array_merge( [ 'relation' => $relation ], $modified_nested );
				}
			} elseif ( isset( $mq['key'] ) && '_price' === $mq['key'] && isset( $mq['value'] ) && is_numeric( $mq['value'] ) ) {
				$converted_price = $this->multi_currency->get_raw_conversion( $mq['value'], $from_currency, $target_currency );

				if ( is_numeric( $converted_price ) ) {
					// Apply floor or ceil based on the 'compare' operator.
					if ( isset( $mq['compare'] ) ) {
						if ( '<=' === $mq['compare'] ) {
							$mq['value'] = (string) ceil( $converted_price ); // max_price.
						} elseif ( '>=' === $mq['compare'] ) {
							$mq['value'] = (string) floor( $converted_price ); // min_price.
						}
					}
				}
			}
		}
		unset( $mq );

		return $meta_query;
	}

	/**
	 * Modify the products/collection-data REST API response to include converted price ranges.
	 *
	 * @param \WP_REST_Response $response The original REST response.
	 * @param \WP_REST_Server   $server   The REST server instance.
	 * @param \WP_REST_Request  $request  The REST request instance.
	 *
	 * @return \WP_REST_Response The modified REST response.
	 */
	public function maybe_modify_price_ranges_rest_response( $response, $server, $request ) {
		if ( '/wc/store/v1/products/collection-data' !== $request->get_route() ) {
			return $response;
		}

		$data = $response->get_data();

		if ( empty( $data['price_range'] ) || ! is_object( $data['price_range'] ) ) {
			return $response;
		}

		$store_currency    = $this->multi_currency->get_default_currency()->get_code();
		$selected_currency = $this->multi_currency->get_selected_currency()->get_code();

		if ( $store_currency === $selected_currency ) {
			return $response;
		}

		$price_fields = [ 'min_price', 'max_price' ];

		foreach ( $price_fields as $field ) {
			if ( property_exists( $data['price_range'], $field ) && is_numeric( $data['price_range']->$field ) ) {
				$converted_price = $this->multi_currency->get_price( $data['price_range']->$field, 'product' );

				if ( is_numeric( $converted_price ) ) {
					$data['price_range']->$field = (string) $converted_price;
				}
			}
		}

		$response->set_data( $data );

		return $response;
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
		if ( isset( $args['cost'] ) ) {
			/**
			 * We need to keep the `cost` structure intact when applying
			 * multi-currency conversions, because downstream it is important
			 * for WooCommerce to keep the taxes flow consistent.
			 */
			if ( is_array( $args['cost'] ) ) {
				$args['cost'] = array_map(
					function ( $cost ) {
						return $this->multi_currency->get_price( $cost, 'shipping' );
					},
					$args['cost']
				);
			} else {
				$args['cost'] = $this->multi_currency->get_price( $args['cost'], 'shipping' );
			}
		}

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
					add_filter( $option_name, [ $this, 'get_free_shipping_min_amount' ], 99 );
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

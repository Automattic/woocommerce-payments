<?php
/**
 * WooCommerce Payments Multi-Currency Frontend Prices
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WC_Order;
use WC_Shipping_Method;

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
	 * The shipping method whose last cost formula we converted to the store currency, so that only its
	 * fees round to the store currency's decimals rather than the selected one's. Null when the last
	 * formula through our filter was left in the selected currency.
	 *
	 * @var WC_Shipping_Method|null
	 */
	private $shipping_method_with_converted_cost = null;

	/**
	 * The shipping method whose `[fee]` shortcode is rendering, or null outside one. The store-currency
	 * decimals and decimal separator apply only inside it, where WC_Shipping_Flat_Rate::fee() rounds and
	 * parses the fee.
	 *
	 * @var WC_Shipping_Method|null
	 */
	private $shipping_method_rendering_fee = null;

	/**
	 * The coupon WooCommerce is re-applying to an existing order, if any.
	 *
	 * @var \WC_Coupon|false|null
	 */
	protected $order_coupon;

	/**
	 * The currency of the order that coupon is being re-applied to.
	 *
	 * @var Currency|null
	 */
	protected $order_coupon_currency;

	/**
	 * Whether the coupon being re-applied holds a store-currency amount that still needs converting.
	 *
	 * @var bool
	 */
	protected $order_coupon_needs_conversion = false;

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
		if ( defined( 'DOING_CRON' ) || is_admin() || Utils::is_admin_api_request() ) {
			return;
		}

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
		add_filter( 'woocommerce_shipping_zone_shipping_methods', [ $this, 'convert_free_shipping_method_min_amount' ], 99 );
		add_filter( 'woocommerce_evaluate_shipping_cost_args', [ $this, 'convert_shipping_cost_args_to_store_currency' ], 99, 3 );
		add_filter( 'wc_get_price_decimals', [ $this, 'use_store_currency_decimals_during_shipping_fee' ], 990 );
		add_filter( 'wc_get_price_decimal_separator', [ $this, 'use_store_decimal_separator_during_shipping_fee' ], 990 );
		add_filter( 'shortcode_atts_fee', [ $this, 'start_shipping_fee_rendering' ] );
		add_filter( 'do_shortcode_tag', [ $this, 'end_shipping_fee_rendering' ], PHP_INT_MIN, 2 );
		// PHP_INT_MIN so it runs before plugins that set item prices on the same action.
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'reset_shipping_fee_rendering' ], PHP_INT_MIN );
		add_filter( 'woocommerce_shipping_method_add_rate_args', [ $this, 'convert_shipping_method_rate_cost' ], 99, 2 );
		// PHP_INT_MAX so the marker is stamped last and survives a plugin that rebuilds the packages array on this filter.
		add_filter( 'woocommerce_cart_shipping_packages', [ $this, 'add_currency_to_shipping_packages' ], PHP_INT_MAX );

		// Coupon hooks.
		add_filter( 'woocommerce_coupon_get_amount', [ $this, 'get_coupon_amount' ], 99, 2 );
		add_filter( 'woocommerce_coupon_get_minimum_amount', [ $this, 'get_coupon_min_max_amount' ], 99, 2 );
		add_filter( 'woocommerce_coupon_get_maximum_amount', [ $this, 'get_coupon_min_max_amount' ], 99, 2 );
		add_filter( 'woocommerce_order_recalculate_coupons_coupon_object', [ $this, 'set_order_coupon' ], 99, 4 );

		// Order hooks.
		add_filter( 'woocommerce_new_order', [ $this, 'add_order_meta' ], 99, 2 );

		// Price Filter Hooks.
		add_filter( 'rest_post_dispatch', [ $this, 'maybe_modify_price_ranges_rest_response' ], 10, 3 );
		add_filter( 'query_loop_block_query_vars', [ $this, 'maybe_modify_price_ranges_query_var' ], 10, 3 );
	}

	/**
	 * Modifies the price range query parameters when the selected currency is not the same as the store currency.
	 *
	 * This method converts the '_price' parameters based on the selected currency.
	 *
	 * @param array     $query The current query variables.
	 * @param \WP_Block $_unused_block The current block instance.
	 * @param int       $_unused_page  The current page number.
	 *
	 * @return array The modified query variables.
	 */
	public function maybe_modify_price_ranges_query_var( $query, $_unused_block, $_unused_page ) {
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
	 * Converts a flat-rate formula's `cost` back to the store currency before evaluation, so the
	 * cart-derived part (already in the selected currency) is not converted twice.
	 *
	 * @param array                   $args        The `qty`/`cost` the formula is evaluated with.
	 * @param string                  $_unused_sum The formula.
	 * @param WC_Shipping_Method|null $method      The shipping method evaluating the formula.
	 *
	 * @return array
	 */
	public function convert_shipping_cost_args_to_store_currency( $args, $_unused_sum, $method = null ) {
		$this->shipping_method_with_converted_cost = null;

		if ( empty( $args['cost'] ) ) {
			return $args;
		}

		if ( ! $this->compatibility->should_convert_shipping_amount( $method ) ) {
			return $args;
		}

		$selected_currency = $this->multi_currency->get_selected_currency();

		if ( $this->multi_currency->get_default_currency()->get_code() === $selected_currency->get_code() ) {
			return $args;
		}

		$selected_rate = $selected_currency->get_rate();

		if ( 0 >= $selected_rate ) {
			return $args;
		}

		// Reverses get_price(), which later converts the evaluated rate by multiplying it by the selected rate.
		$args['cost'] = (float) $args['cost'] / $selected_rate;

		// The formula now runs in the store currency, so its fees must round to the store currency's decimals.
		$this->shipping_method_with_converted_cost = $method;

		return $args;
	}

	/**
	 * Rounds a converted formula's fees to the store currency's decimals. WC_Shipping_Flat_Rate::fee()
	 * rounds to wc_get_price_decimals(), which we otherwise filter to the selected currency and would
	 * truncate the store-currency fee.
	 *
	 * @param int $decimals The decimals WooCommerce would use.
	 *
	 * @return int
	 */
	public function use_store_currency_decimals_during_shipping_fee( $decimals ): int {
		if ( null === $this->shipping_method_rendering_fee ) {
			return $decimals;
		}

		// A method that renders [fee] without our cost filter, such as one overriding evaluate_cost(), kept its cost in the selected currency.
		if ( $this->shipping_method_rendering_fee !== $this->shipping_method_with_converted_cost ) {
			return $decimals;
		}

		return absint( get_option( 'woocommerce_price_num_decimals', 2 ) );
	}

	/**
	 * Reads a `[fee]` shortcode's `percent`, `min_fee` and `max_fee` with the store's decimal separator.
	 * The merchant types them in the store's number format, and WC_Shipping_Flat_Rate::fee() (WC 11.3+)
	 * parses them with wc_get_price_decimal_separator(), which we otherwise switch to the selected currency.
	 *
	 * @param string $separator The decimal separator WooCommerce would use.
	 *
	 * @return string
	 */
	public function use_store_decimal_separator_during_shipping_fee( $separator ) {
		if ( null === $this->shipping_method_rendering_fee ) {
			return $separator;
		}

		if ( $this->multi_currency->get_default_currency()->get_code() === $this->multi_currency->get_selected_currency()->get_code() ) {
			return $separator;
		}

		// wc_get_price_decimal_separator() falls back to a dot when the option is empty.
		return (string) get_option( 'woocommerce_price_decimal_sep' );
	}

	/**
	 * Marks the start of a shipping method's `[fee]` shortcode. WC_Shipping_Flat_Rate::fee() reads its
	 * attributes with shortcode_atts() first, so this runs only once the fee is actually being worked out,
	 * never for a shortcode that another callback short-circuits.
	 *
	 * A fee() override that does not call shortcode_atts( ..., 'fee' ) keeps the selected currency's
	 * format. One that throws keeps the store's format on until another `[fee]` shortcode finishes or the
	 * next cart calculation starts (see reset_shipping_fee_rendering()).
	 *
	 * @param array $atts The `[fee]` attributes, returned unchanged.
	 *
	 * @return array
	 */
	public function start_shipping_fee_rendering( $atts ) {
		global $shortcode_tags;

		$callback = $shortcode_tags['fee'] ?? null;

		if ( is_array( $callback ) && ( $callback[0] ?? null ) instanceof WC_Shipping_Method ) {
			$this->shipping_method_rendering_fee = $callback[0];
		}

		return $atts;
	}

	/**
	 * Marks the end of a shipping method's `[fee]` shortcode.
	 *
	 * @param string $output The shortcode output, returned unchanged.
	 * @param string $tag    The shortcode tag.
	 *
	 * @return string
	 */
	public function end_shipping_fee_rendering( $output, $tag ) {
		if ( 'fee' === $tag ) {
			$this->shipping_method_rendering_fee = null;
		}

		return $output;
	}

	/**
	 * Clears the `[fee]` state before a cart calculation. A fee() override that throws skips the end of its
	 * shortcode, and if its caller catches the exception, the store currency's decimals would otherwise stay
	 * on while the item totals are taken.
	 *
	 * @return void
	 */
	public function reset_shipping_fee_rendering() {
		$this->shipping_method_rendering_fee       = null;
		$this->shipping_method_with_converted_cost = null;
	}

	/**
	 * Returns the shipping add rate args with cost converted.
	 *
	 * @param array              $args   Shipping rate args.
	 * @param WC_Shipping_Method $method The shipping method adding the rate.
	 *
	 * @return array Shipping rate args with converted cost.
	 */
	public function convert_shipping_method_rate_cost( $args, $method ) {
		if ( isset( $args['cost'] ) && $this->compatibility->should_convert_shipping_amount( $method ) ) {
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

		// A coupon re-applied to an existing order is converted to the order's currency,
		// no matter which currency the shopper has selected since the order was placed.
		if ( $coupon === $this->order_coupon ) {
			// set_order_coupon() works out whether the amount is still in the store currency.
			return $this->order_coupon_needs_conversion
				? $this->multi_currency->get_price( $amount, 'coupon', $this->order_coupon_currency )
				: $amount;
		}

		return $this->multi_currency->get_price( $amount, 'coupon' );
	}

	/**
	 * Remembers the coupon WC_Abstract_Order::recalculate_coupons() is about to re-apply to an order,
	 * so that its amount is converted to the currency of the order instead of the selected currency.
	 *
	 * @param \WC_Coupon|false      $coupon       The coupon about to be re-applied.
	 * @param string                $_unused_code The coupon code.
	 * @param \WC_Order_Item_Coupon $coupon_item  The order's coupon line item.
	 * @param WC_Order              $order        The order the coupon is re-applied to.
	 *
	 * @return \WC_Coupon|false The unmodified coupon.
	 */
	public function set_order_coupon( $coupon, $_unused_code, $coupon_item, $order ) {
		// Runs on any frontend request except standard REST order writes, which already force the store currency:
		// the Store API pay-for-order route, and frontend calls such as `$order->apply_coupon()`.
		if ( WC()->is_rest_api_request() && ! Utils::is_store_api_request() && ! Utils::is_store_batch_request() ) {
			$this->order_coupon = null;
			return $coupon;
		}

		$this->order_coupon = $coupon;
		// The enabled currency carries the merchant's manual rate, as at checkout. The available one, which
		// only has the automatic rate, covers a currency the merchant disabled since checkout.
		$order_currency              = $order->get_currency();
		$this->order_coupon_currency = $this->multi_currency->get_enabled_currencies()[ $order_currency ]
			?? $this->multi_currency->get_available_currencies()[ $order_currency ]
			?? $this->multi_currency->get_default_currency();

		// Decide whether the amount read back for this coupon is in the store currency and so needs
		// converting. A live coupon's amount is the store-currency value. A deleted coupon is rebuilt by
		// recalculate_coupons() from the order line: `coupon_info` (WC >= 8.7) holds the amount already
		// converted at checkout, while legacy `coupon_data` holds the raw store-currency amount, which must
		// still be converted. A coupon rebuilt with no stored amount takes its value from the order line,
		// already in the order's currency, so it is left as is.
		// Known gap: WooCommerce's coupon data conversion tool (WC >= 8.7) rebuilt `coupon_info` from
		// `coupon_data` without Multi-Currency, so on those orders a deleted coupon's amount is still in the
		// store currency but is treated as converted.
		// Cast like WC_Abstract_Order::get_temporary_coupon(), which also accepts object-valued meta.
		$legacy_data                         = (array) $coupon_item->get_meta( 'coupon_data', true );
		$legacy_amount                       = (float) ( $legacy_data['amount'] ?? 0 );
		$has_coupon_info                     = (bool) $coupon_item->get_meta( 'coupon_info', true );
		$this->order_coupon_needs_conversion = $coupon instanceof \WC_Coupon && (
			! $coupon->get_virtual()
			|| ( ! $has_coupon_info && $legacy_amount > 0 )
		);

		return $coupon;
	}

	/**
	 * Returns the min or max amount for a coupon.
	 *
	 * @param mixed  $amount The coupon's min or max amount.
	 * @param object $coupon The coupon object.
	 *
	 * @return mixed The converted coupon's min or max amount.
	 */
	public function get_coupon_min_max_amount( $amount, $coupon = null ) {
		if ( ! $amount || ! $this->compatibility->should_convert_coupon_min_max_amount( $coupon ) ) {
			return $amount;
		}

		// Coupon mix/max prices are treated as products to avoid inconsistencies with charm pricing
		// making a coupon invalid when the coupon min/max amount is the same as the product's price.
		return $this->multi_currency->get_price( $amount, 'product' );
	}

	/**
	 * Adds the selected currency to each shipping package so WooCommerce's package hash changes on a
	 * currency switch and cached shipping rates are rebuilt. WC's hash omits the currency, and the rate
	 * is converted once at build time, so without this the stale rate is reused.
	 *
	 * @param mixed $packages The shipping packages (may be a non-array if a prior filter misbehaved).
	 *
	 * @return mixed The packages, with the selected currency marker added to each non-empty array package unless the
	 *               store currency is selected.
	 */
	public function add_currency_to_shipping_packages( $packages ) {
		if ( ! is_array( $packages ) ) {
			return $packages;
		}

		$currency = $this->multi_currency->get_selected_currency();

		// Store-currency packages stay unmarked so they hash the same as where this filter doesn't run, and the two don't
		// invalidate each other's cached rates.
		if ( $currency->get_code() === $this->multi_currency->get_default_currency()->get_code() ) {
			return $packages;
		}

		$marker = implode(
			':',
			[
				$currency->get_code(),
				$currency->get_rate(),
				$currency->get_rounding(),
				$currency->get_charm(),
				$this->multi_currency->get_apply_charm_only_to_products() ? '1' : '0',
			]
		);

		foreach ( array_keys( $packages ) as $key ) {
			if ( is_array( $packages[ $key ] ) && ! empty( $packages[ $key ] ) ) {
				$packages[ $key ]['wcpay_multi_currency'] = $marker;
			}
		}

		return $packages;
	}

	/**
	 * Converts the min_amount of free shipping methods.
	 *
	 * @param array $methods The shipping methods.
	 */
	public function convert_free_shipping_method_min_amount( $methods ) {
		foreach ( $methods as $method ) {
			// Free shipping min amount is treated as products to avoid inconsistencies with charm pricing
			// making a method invalid when its min amount is the same as the product's price.
			if ( 'free_shipping' === $method->id && ! empty( $method->min_amount ) ) {
				$method->min_amount = $this->multi_currency->get_price( $method->min_amount, 'product' );
			}
		}

		return $methods;
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

		// A third-party currency switcher sets the order currency through the `woocommerce_currency`
		// filter, without the shopper selecting it in Multi-Currency. Multi-Currency did not convert
		// that order, and a rate recorded for it would make Analytics convert the order on top of the
		// switcher's own conversion. Orders given an explicit currency, such as through the REST API,
		// are left as they were.
		$order_currency = $order->get_currency();
		if (
			$this->multi_currency->get_selected_currency()->get_code() !== $order_currency
			&& get_woocommerce_currency() === $order_currency
		) {
			return;
		}

		$exchange_rate = $this->multi_currency->get_price( 1, 'exchange_rate' );

		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', $exchange_rate );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', $default_currency->get_code() );
		$order->save_meta_data();
	}
}

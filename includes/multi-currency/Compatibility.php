<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

use WC_Order;
use WC_Order_Refund;
use WC_Product;
use WCPay\MultiCurrency\Compatibility\WooCommerceFedEx;
use WCPay\MultiCurrency\Compatibility\WooCommerceUPS;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi-Currency Compatibility.
 */
class Compatibility {

	const ADDONS_CONVERTED_META_KEY = '_wcpay_multi_currency_addons_converted';
	const FILTER_PREFIX             = 'wcpay_multi_currency_';

	/**
	 * Subscription switch cart item.
	 *
	 * @var string
	 */
	public $switch_cart_item = '';

	/**
	 * MultiCurrency class.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * Utils class.
	 *
	 * @var Utils
	 */
	private $utils;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency MultiCurrency class.
	 * @param Utils         $utils Utils class.
	 */
	public function __construct( MultiCurrency $multi_currency, Utils $utils ) {
		$this->multi_currency = $multi_currency;
		$this->utils          = $utils;
		$this->init_filters();

		$compatibility_classes[] = new WooCommerceFedEx( $multi_currency, $utils );
		$compatibility_classes[] = new WooCommerceUPS( $multi_currency, $utils );
	}

	/**
	 * Converts subscription prices, if needed.
	 *
	 * @param mixed  $price   The price to be filtered.
	 * @param object $product The product that will have a filtered price.
	 *
	 * @return mixed The price as a string or float.
	 */
	public function get_subscription_product_price( $price, $product ) {
		if ( ! $price || ! $this->should_convert_product_price( $product ) ) {
			return $price;
		}

		return $this->multi_currency->get_price( $price, 'product' );
	}

	/**
	 * Converts subscription sign up prices, if needed.
	 *
	 * @param mixed  $price   The price to be filtered.
	 * @param object $product The product that will have a filtered price.
	 *
	 * @return mixed The price as a string or float.
	 */
	public function get_subscription_product_signup_fee( $price, $product ) {
		if ( ! $price ) {
			return $price;
		}

		$switch_cart_items = $this->get_subscription_switch_cart_items();
		if ( 0 < count( $switch_cart_items ) ) {

			// There should only ever be one item, so use that item.
			$item                   = array_shift( $switch_cart_items );
			$item_id                = isset( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
			$switch_cart_item       = $this->switch_cart_item;
			$this->switch_cart_item = $item['key'];

			if ( $product->get_id() === $item_id ) {

				/**
				 * These tests get mildly complex due to, when switching, the sign up fee is queried
				 * several times to determine prorated costs. This means we have to test to see when
				 * the fee actually needs be converted.
				 */

				if ( $this->utils->is_call_in_backtrace( [ 'WC_Subscriptions_Cart::set_subscription_prices_for_calculation' ] ) ) {
					return $price;
				}

				// Check to see if it's currently determining prorated prices.
				if ( $this->utils->is_call_in_backtrace( [ 'WC_Subscriptions_Product::get_sign_up_fee' ] )
					&& $this->utils->is_call_in_backtrace( [ 'WC_Cart->calculate_totals' ] )
					&& $item['key'] === $switch_cart_item
					&& ! $this->utils->is_call_in_backtrace( [ 'WCS_Switch_Totals_Calculator->apportion_sign_up_fees' ] ) ) {
						return $price;
				}

				// Check to see if the _subscription_sign_up_fee meta for the product has already been updated.
				if ( $item['key'] === $switch_cart_item ) {
					foreach ( $product->get_meta_data() as $meta ) {
						if ( '_subscription_sign_up_fee' === $meta->get_data()['key'] && 0 < count( $meta->get_changes() ) ) {
							return $price;
						}
					}
				}
			}
		}

		return $this->multi_currency->get_price( $price, 'product' );
	}

	/**
	 * Disables the mixed cart if needed.
	 *
	 * @param string|bool $value Option from the database, or false.
	 *
	 * @return mixed False, yes, or no.
	 */
	public function maybe_disable_mixed_cart( $value ) {
		// If there's a subscription switch in the cart, disable multiple items in the cart.
		// This is so that subscriptions with different currencies cannot be added to the cart.
		if ( 0 < count( $this->get_subscription_switch_cart_items() ) ) {
			return 'no';
		}

		return $value;
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

		$switch_id = $this->get_subscription_switch_id_from_superglobal();
		if ( $switch_id ) {
			return get_post_meta( $switch_id, '_order_currency', true );
		}

		$switch_cart_items = $this->get_subscription_switch_cart_items();
		if ( 0 < count( $switch_cart_items ) ) {
			$switch_cart_item = array_shift( $switch_cart_items );
			return get_post_meta( $switch_cart_item['subscription_switch']['subscription_id'], '_order_currency', true );
		}

		$subscription_resubscribe = $this->cart_contains_resubscribe();
		if ( $subscription_resubscribe ) {
			return get_post_meta( $subscription_resubscribe['subscription_resubscribe']['subscription_id'], '_order_currency', true );
		}

		return false;
	}

	/**
	 * Checks to see if the widgets should be hidden.
	 *
	 * @return bool False if it shouldn't be hidden, true if it should.
	 */
	public function should_hide_widgets(): bool {
		if ( $this->cart_contains_renewal()
			|| $this->get_subscription_switch_id_from_superglobal()
			|| 0 < count( $this->get_subscription_switch_cart_items() )
			|| $this->cart_contains_resubscribe() ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks to see if the coupon's amount should be converted.
	 *
	 * @param object $coupon Coupon object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_coupon_amount( $coupon = null ): bool {
		if ( ! $coupon ) {
			return true;
		}

		// We do not need to convert percentage coupons.
		if ( $this->is_coupon_type( $coupon, 'subscription_percent' ) ) {
			return false;
		}

		// If there's not a renewal in the cart, we can convert.
		$subscription_renewal = $this->cart_contains_renewal();
		if ( ! $subscription_renewal ) {
			return true;
		}

		/**
		 * We need to allow the early renewal to convert the cost, as it pulls the original value of the coupon.
		 * Subsequent queries for the amount use the first converted amount.
		 * This also works for normal manual renewals.
		 */
		if ( ! $this->utils->is_call_in_backtrace( [ 'WCS_Cart_Early_Renewal->setup_cart' ] )
			&& $this->utils->is_call_in_backtrace( [ 'WC_Discounts->apply_coupon' ] )
			&& $this->is_coupon_type( $coupon, 'subscription_recurring' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks to see if the product's price should be converted.
	 *
	 * @param object $product Product object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( $product = null ): bool {
		if ( ! $product ) {
			return true;
		}

		// Check for cart items to see if they have already been converted.
		if ( 1 === $product->get_meta( self::ADDONS_CONVERTED_META_KEY ) ) {
			return false;
		}

		// Check for subscription renewal or resubscribe.
		if ( $this->is_product_subscription_type_in_cart( $product, 'renewal' )
			|| $this->is_product_subscription_type_in_cart( $product, 'resubscribe' ) ) {
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

		// WCPay Subs does a check against the product price and the total, we need to return the actual product price for this check.
		if ( $this->utils->is_call_in_backtrace( [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] )
			&& $this->utils->is_call_in_backtrace( [ 'WC_Product->get_price' ] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determines if the store currency should be returned or not.
	 *
	 * @return bool
	 */
	public function should_return_store_currency(): bool {
		return apply_filters( self::FILTER_PREFIX . 'should_return_store_currency', false );
	}

	/**
	 * This filter is called when the best sales day logic is called. We use it to add another filter which will
	 * convert the order prices used in this inbox notification.
	 *
	 * @param bool $arg Whether or not the best sales day logic should execute. We will just return this as is to
	 * respect the existing behaviour.
	 *
	 * @return bool
	 */
	public function attach_order_modifier( $arg ) {
		// Attach our filter to modify the order prices.
		add_filter( 'woocommerce_order_query', [ $this, 'convert_order_prices' ] );

		// This will be a bool value indication whether the best day logic should be run. Let's just return it as is.
		return $arg;
	}

	/**
	 * When a request is made by the "Best Sales Day" Inbox notification, we want to hook into this and convert
	 * the order totals to the store default currency.
	 *
	 * @param WC_Order[]|WC_Order_Refund[] $results The results returned by the orders query.
	 *
	 * @return array
	 */
	public function convert_order_prices( $results ): array {
		$backtrace_calls = [
			'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::sum_sales_for_date',
			'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::possibly_add_note',
		];

		// If the call we're expecting isn't in the backtrace, then just do nothing and return the results.
		if ( ! $this->utils->is_call_in_backtrace( $backtrace_calls ) ) {
			return $results;
		}

		$default_currency = $this->multi_currency->get_default_currency();
		if ( ! $default_currency ) {
			return $results;
		}

		foreach ( $results as $order ) {
			if ( ! $order ||
				$order->get_currency() === $default_currency->get_code() ||
				! $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true ) ||
				$order->get_meta( '_wcpay_multi_currency_order_default_currency', true ) !== $default_currency->get_code()
			) {
				continue;
			}

			$exchange_rate = $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true );
			$order->set_total( number_format( $order->get_total() * ( 1 / $exchange_rate ), wc_get_price_decimals() ) );
		}

		remove_filter( 'woocommerce_order_query', [ $this, 'convert_order_prices' ] );

		return $results;
	}

	/**
	 * Converts the price of an addon from WooCommerce Products Add-on extension.
	 *
	 * @param mixed $price   The price to be filtered.
	 * @param array $type    The type of the addon.

	 * @return mixed         The price as a string or float.
	 */
	public function get_addons_price( $price, $type ) {
		if ( 'percentage_based' === $type['price_type'] ) {
			// If the addon is a percentage_based type $price is actually a percentage
			// and doesn't need any conversion.
			return $price;
		}

		return $this->multi_currency->get_price( $price, 'product' );
	}

	/**
	 * Gets the product price during ajax requests from the product page.
	 *
	 * @param float       $price    Price to get converted.
	 * @param int         $quantity Quantity of the product selected.
	 * @param \WC_Product $product  WC_Product related to the price.
	 *
	 * @return float Adjusted price.
	 */
	public function get_product_calculation_price( float $price, int $quantity, \WC_Product $product ): float {
		return $this->multi_currency->get_price( $price / $quantity, 'product' ) * $quantity;
	}

	/**
	 * Filters the meta data for order line items so that we can properly set values in the names.
	 *
	 * @param array                  $meta_data A key/value for the meta data to be inserted for the line item.
	 * @param array                  $addon     The addon being processed.
	 * @param \WC_Order_Item_Product $item      Order item data.
	 * @param array                  $values    Order item values.
	 *
	 * @return array A key/value for the meta data to be inserted for the line item.
	 */
	public function order_line_item_meta( array $meta_data, array $addon, \WC_Order_Item_Product $item, array $values ): array {

		// If there is an add-on price, add the price of the add-on to the label name.
		if ( $addon['price'] && apply_filters( 'woocommerce_addons_add_price_to_name', true ) ) {
			$product = $item->get_product();

			if ( 'percentage_based' === $addon['price_type'] && 0.0 !== (float) $product->get_price() ) {
				// Calculate the percentage price.
				$addon_price = $product->get_price() * ( $addon['price'] / 100 );
			} elseif ( 'custom_price' === $addon['field_type'] ) {
				// Custom prices do not get converted.
				$addon_price = $addon['price'];
			} elseif ( 'input_multiplier' === $addon['field_type'] ) {
				// Quantity/multiplier add on needs to be split, calculated, then multiplied by input value.
				$addon_price = $this->multi_currency->get_price( $addon['price'] / $addon['value'], 'product' ) * $addon['value'];
			} else {
				// Convert all others.
				$addon_price = $this->multi_currency->get_price( $addon['price'], 'product' );
			}

			/**
			 * Get the display price.
			 *
			 * The WC_Product_Addons_Helper is defined in Product Add Ons.
			 * This method in WCPay is only called if Product Add Ons is installed and active.
			 *
			 * @psalm-suppress UndefinedClass
			 */
			$price          = html_entity_decode(
				wp_strip_all_tags( wc_price( \WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon_price, $values['data'] ) ) ),
				ENT_QUOTES,
				get_bloginfo( 'charset' )
			);
			$addon['name'] .= ' (' . $price . ')';
		}

		if ( 'custom_price' === $addon['field_type'] ) {
			$addon['value'] = $addon['price'];
		}

		return [
			'key'   => $addon['name'],
			'value' => $addon['value'],
		];
	}

	/**
	 * Updates the product price according to converted add on values.
	 *
	 * @param array $updated_prices Prices updated by Product Add-Ons (unused).
	 * @param array $cart_item      Cart item meta data.
	 * @param array $prices         Original prices passed to Product Add-Ons for calculations.
	 *
	 * @return array
	 */
	public function update_product_price( $updated_prices, $cart_item, $prices ): array {
		$price         = $this->multi_currency->get_price( $prices['price'], 'product' );
		$regular_price = $this->multi_currency->get_price( $prices['regular_price'], 'product' );
		$sale_price    = $this->multi_currency->get_price( $prices['sale_price'], 'product' );
		$quantity      = $cart_item['quantity'];

		// TODO: Check compat with Smart Coupons.
		// Compatibility with Smart Coupons self declared gift amount purchase.
		$credit_called = ! empty( $_POST['credit_called'] ) ? $_POST['credit_called'] : null;  // phpcs:ignore
		if ( empty( $price ) && ! empty( $credit_called ) ) {
			// Variable $_POST['credit_called'] is an array.
			if ( isset( $credit_called[ $cart_item['data']->get_id() ] ) ) {
				$price         = (float) $credit_called[ $cart_item['data']->get_id() ];
				$regular_price = $price;
				$sale_price    = $price;
			}
		}

		if ( empty( $price ) && ! empty( $cart_item['credit_amount'] ) ) {
			$price         = (float) $cart_item['credit_amount'];
			$regular_price = $price;
			$sale_price    = $price;
		}

		foreach ( $cart_item['addons'] as $addon ) {
			// Percentage based and custom defined addon prices do not get converted, all others do.
			if ( 'percentage_based' === $addon['price_type'] || 'custom_price' === $addon['field_type'] ) {
				$addon_price = $addon['price'];
			} elseif ( 'input_multiplier' === $addon['field_type'] ) {
				// Quantity/multiplier add on needs to be split, calculated, then multiplied by input value.
				$addon_price = $this->multi_currency->get_price( $addon['price'] / $addon['value'], 'product' ) * $addon['value'];
			} else {
				$addon_price = $this->multi_currency->get_price( $addon['price'], 'product' );
			}

			switch ( $addon['price_type'] ) {
				case 'percentage_based':
					$price         += (float) ( $cart_item['data']->get_price( 'view' ) * ( $addon_price / 100 ) );
					$regular_price += (float) ( $regular_price * ( $addon_price / 100 ) );
					$sale_price    += (float) ( $sale_price * ( $addon_price / 100 ) );
					break;
				case 'flat_fee':
					$price         += (float) ( $addon_price / $quantity );
					$regular_price += (float) ( $addon_price / $quantity );
					$sale_price    += (float) ( $addon_price / $quantity );
					break;
				default:
					$price         += (float) $addon_price;
					$regular_price += (float) $addon_price;
					$sale_price    += (float) $addon_price;
					break;
			}
		}

		// Let ourselves know this item has had add ons converted.
		$cart_item['data']->update_meta_data( self::ADDONS_CONVERTED_META_KEY, 1 );

		return [
			'price'         => $price,
			'regular_price' => $regular_price,
			'sale_price'    => $sale_price,
		];
	}

	/**
	 * Filters the cart item data meta so we can provide the proper name with converted add on price.
	 *
	 * @param array $addon_data The addon data we are filtering/replacing.
	 * @param array $addon      The addon being processed.
	 * @param array $cart_item  The cart item being processed.
	 *
	 * @return array
	 */
	public function get_item_data( $addon_data, $addon, $cart_item ): array {
		$price = isset( $cart_item['addons_price_before_calc'] ) ? $cart_item['addons_price_before_calc'] : $addon['price'];
		$name  = $addon['name'];

		if ( 0.0 === $addon['price'] ) {
			$name .= '';
		} elseif ( 'percentage_based' === $addon['price_type'] && 0.0 === $price ) {
			$name .= '';
		} elseif ( 'custom_price' === $addon['field_type'] ) {
			$name .= ' (' . wc_price( $addon['price'] ) . ')';
		} elseif ( 'percentage_based' !== $addon['price_type'] && $addon['price'] && apply_filters( 'woocommerce_addons_add_price_to_name', '__return_true' ) ) {
			// Get our converted and tax adjusted price to put in the add on name.
			$price = $this->multi_currency->get_price( $addon['price'], 'product' );
			if ( 'input_multiplier' === $addon['field_type'] ) {
				// Quantity/multiplier add on needs to be split, calculated, then multiplied by input value.
				$price = $this->multi_currency->get_price( $addon['price'] / $addon['value'], 'product' ) * $addon['value'];
			}
			/**
			 * The WC_Product_Addons_Helper is defined in Product Add Ons.
			 * This method in WCPay is only called if Product Add Ons is installed and active.
			 *
			 * @psalm-suppress UndefinedClass
			 */
			$price = \WC_Product_Addons_Helper::get_product_addon_price_for_display( $price, $cart_item['data'] );
			$name .= ' (' . wc_price( $price ) . ')';
		} else {
			// Get the percentage cost in the currency in use, and set the meta data on the product that the value was converted.
			$_product = wc_get_product( $cart_item['product_id'] );
			$price    = $this->multi_currency->get_price( $price, 'product' );
			$_product->set_price( $price * ( $addon['price'] / 100 ) );
			$_product->update_meta_data( self::ADDONS_CONVERTED_META_KEY, 1 );
			$name .= ' (' . WC()->cart->get_product_price( $_product ) . ')';
		}

		return [
			'name'    => $name,
			'value'   => $addon['value'],
			'display' => isset( $addon['display'] ) ? $addon['display'] : '',
		];
	}

	/**
	 * Fixes currency formatting issues in Product Add-Ons. PAO gets these values directly from the db options,
	 * so those values aren't filtered. Luckily, there's a filter.
	 *
	 * @param array $params Product Add-Ons global parameters.
	 *
	 * @return array Adjust parameters.
	 */
	public function product_addons_params( array $params ): array {
		$params['currency_format_num_decimals'] = wc_get_price_decimals();
		$params['currency_format_decimal_sep']  = wc_get_price_decimal_separator();
		$params['currency_format_thousand_sep'] = wc_get_price_thousand_separator();

		return $params;
	}

	/**
	 * Initializes our filters for compatibility.
	 *
	 * @return void
	 */
	private function init_filters() {
		if ( ! is_admin() && ! defined( 'DOING_CRON' ) ) {
			// Subscriptions filters.
			add_filter( 'option_woocommerce_subscriptions_multiple_purchase', [ $this, 'maybe_disable_mixed_cart' ], 50 );
			add_filter( 'woocommerce_subscriptions_product_price', [ $this, 'get_subscription_product_price' ], 50, 2 );
			add_filter( 'woocommerce_product_get__subscription_sign_up_fee', [ $this, 'get_subscription_product_signup_fee' ], 50, 2 );
			add_filter( 'woocommerce_product_variation_get__subscription_sign_up_fee', [ $this, 'get_subscription_product_signup_fee' ], 50, 2 );

			// Product Add-Ons filters.
			add_filter( 'woocommerce_product_addons_option_price_raw', [ $this, 'get_addons_price' ], 50, 2 );
			add_filter( 'woocommerce_product_addons_price_raw', [ $this, 'get_addons_price' ], 50, 2 );
			add_filter( 'woocommerce_product_addons_params', [ $this, 'product_addons_params' ], 50, 1 );
			add_filter( 'woocommerce_product_addons_get_item_data', [ $this, 'get_item_data' ], 50, 3 );
			add_filter( 'woocommerce_product_addons_update_product_price', [ $this, 'update_product_price' ], 50, 4 );
			add_filter( 'woocommerce_product_addons_order_line_item_meta', [ $this, 'order_line_item_meta' ], 50, 4 );
		}

		if ( wp_doing_ajax() ) {
			// Product Add-Ons filters.
			add_filter( 'woocommerce_product_addons_ajax_get_product_price_including_tax', [ $this, 'get_product_calculation_price' ], 50, 3 );
			add_filter( 'woocommerce_product_addons_ajax_get_product_price_excluding_tax', [ $this, 'get_product_calculation_price' ], 50, 3 );
		}

		if ( defined( 'DOING_CRON' ) ) {
			add_filter( 'woocommerce_admin_sales_record_milestone_enabled', [ $this, 'attach_order_modifier' ] );
		}
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
	 * Gets the subscription switch items out of the cart.
	 *
	 * @return array Empty array or the cart items in an array..
	 */
	private function get_subscription_switch_cart_items(): array {
		if ( ! function_exists( 'wcs_get_order_type_cart_items' ) ) {
			return [];
		}
		return wcs_get_order_type_cart_items( 'switch' );
	}

	/**
	 * Checks $_GET superglobal for a switch id and returns it if found.
	 *
	 * @return mixed Id of the sub being switched, or false.
	 */
	private function get_subscription_switch_id_from_superglobal() {
		if ( isset( $_GET['_wcsnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wcsnonce'] ), 'wcs_switch_request' ) ) {
			if ( isset( $_GET['switch-subscription'] ) ) {
				return (int) $_GET['switch-subscription'];
			}
		}
		return false;
	}

	/**
	 * Checks the cart to see if it contains a resubscription.
	 *
	 * @return mixed The cart item containing the resubscription as an array, else false.
	 */
	private function cart_contains_resubscribe() {
		if ( ! function_exists( 'wcs_cart_contains_resubscribe' ) ) {
			return false;
		}
		return wcs_cart_contains_resubscribe();
	}

	/**
	 * Checks to see if the product passed is in the cart as a subscription type.
	 *
	 * @param object $product Product to test.
	 * @param string $type    Type of subscription.
	 *
	 * @return bool True if found in the cart, false if not.
	 */
	private function is_product_subscription_type_in_cart( $product, $type ): bool {
		$subscription = false;

		switch ( $type ) {
			case 'renewal':
				$subscription = $this->cart_contains_renewal();
				break;

			case 'resubscribe':
				$subscription = $this->cart_contains_resubscribe();
				break;
		}

		if ( $subscription && $product ) {
			if ( ( isset( $subscription['variation_id'] ) && $subscription['variation_id'] === $product->get_id() )
				|| $subscription['product_id'] === $product->get_id() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks to see if the coupon passed is of a specified type.
	 *
	 * @param \WC_Coupon $coupon Coupon to test.
	 * @param string     $type   Type of coupon to test for.
	 *
	 * @return bool True on match.
	 */
	private function is_coupon_type( $coupon, string $type ) {

		switch ( $type ) {
			case 'subscription_percent':
				$types = [ 'recurring_percent', 'sign_up_fee_percent', 'renewal_percent' ];
				break;

			case 'subscription_recurring':
				$types = [ 'recurring_fee', 'recurring_percent', 'renewal_fee', 'renewal_percent', 'renewal_cart' ];
				break;
		}

		if ( in_array( $coupon->get_discount_type(), $types, true ) ) {
			return true;
		}
		return false;
	}
}

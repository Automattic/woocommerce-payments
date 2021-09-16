<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency Compatibility.
 */
class Compatibility {
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

		if ( ! is_admin() && ! defined( 'DOING_CRON' ) ) {
			// Subscriptions filters.
			add_filter( 'option_woocommerce_subscriptions_multiple_purchase', [ $this, 'maybe_disable_mixed_cart' ], 50 );
			add_filter( 'woocommerce_subscriptions_product_price', [ $this, 'get_subscription_product_price' ], 50, 2 );
			add_filter( 'woocommerce_product_get__subscription_sign_up_fee', [ $this, 'get_subscription_product_signup_fee' ], 50, 2 );
			add_filter( 'woocommerce_product_variation_get__subscription_sign_up_fee', [ $this, 'get_subscription_product_signup_fee' ], 50, 2 );

			// Product Add-Ons filters.
			add_filter( 'woocommerce_product_addons_adjust_price', '__return_false' );
			add_filter( 'woocommerce_add_cart_item', [ $this, 'add_cart_item' ], 50 );
			add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'get_cart_item_from_session' ], 50, 2 );

			add_filter( 'woocommerce_product_addons_option_price_raw', [ $this, 'get_addons_price' ], 50, 2 );
			add_filter( 'woocommerce_product_addons_price_raw', [ $this, 'get_addons_price' ], 50, 2 );
			add_filter( 'woocommerce_get_price_including_tax', [ $this, 'get_display_price' ], 50, 3 );
			add_filter( 'woocommerce_get_price_excluding_tax', [ $this, 'get_display_price' ], 50, 3 );
			add_filter( 'raw_woocommerce_price', [ $this, 'get_mini_cart_price' ], 50, 2 );
			add_filter( 'woocommerce_product_addons_params', [ $this, 'product_addons_params' ], 50, 1 );

			add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'order_line_item' ], 50, 3 );
		}

		if ( defined( 'DOING_CRON' ) ) {
			add_filter( 'woocommerce_admin_sales_record_milestone_enabled', [ $this, 'attach_order_modifier' ] );
		}
	}

	public function order_line_item( $item, $cart_item_key, $values ) {
		$trigger = 'here';
	}

	// public function get_unconverted_price( $product, $type = '' ) {
	// 	switch ( $type ) {
	// 		case 'regular':
	// 			$price = $product->get_regular_price();
	// 			break;
	// 		case 'sale':
	// 			$price = $product->get_sale_price();
	// 			break;
	// 		default:
	// 			$price = $product->get_price();
	// 			break;
	// 	}
	// 	return $price;
	// }

	public function add_cart_item( $cart_item ) {
		if ( ! empty( $cart_item['addons'] ) ) {
			$price         = (float) $cart_item['data']->get_price( 'view' ); //$prices['price'];
			$regular_price = (float) $cart_item['data']->get_regular_price( 'view' ); //$prices['regular_price'];
			$sale_price    = (float) $cart_item['data']->get_sale_price( 'view' ); //$prices['sale_price'];
			// $price         = (float) $this->get_unconverted_price( $cart_item['data'], 'default' );
			// $regular_price = (float) $this->get_unconverted_price( $cart_item['data'], 'regular' );
			// $sale_price    = (float) $this->get_unconverted_price( $cart_item['data'], 'sale' );
			$quantity      = $cart_item['quantity'];

			// Compatibility with Smart Coupons self declared gift amount purchase.
			if ( empty( $price ) && ! empty( $_POST['credit_called'] ) ) {
				// Variable $_POST['credit_called'] is an array.
				if ( isset( $_POST['credit_called'][ $cart_item['data']->get_id() ] ) ) {
					$price         = (float) $_POST['credit_called'][ $cart_item['data']->get_id() ];
					$regular_price = $price;
					$sale_price    = $price;
				}
			}

			if ( empty( $price ) && ! empty( $cart_item['credit_amount'] ) ) {
				$price         = (float) $cart_item['credit_amount'];
				$regular_price = $price;
				$sale_price    = $price;
			}

			// Save the price before price type calculations to be used later.
			// $cart_item['addons_price_before_calc']         = (float) $this->get_unconverted_price( $cart_item['data'], 'default' );
			// $cart_item['addons_regular_price_before_calc'] = (float) $this->get_unconverted_price( $cart_item['data'], 'regular' );
			// $cart_item['addons_sale_price_before_calc']    = (float) $this->get_unconverted_price( $cart_item['data'], 'sale' );
			$cart_item['addons_price_before_calc']         = (float) $price;
			$cart_item['addons_regular_price_before_calc'] = (float) $regular_price;
			$cart_item['addons_sale_price_before_calc']    = (float) $sale_price;

			foreach ( $cart_item['addons'] as $addon ) {
				$price_type  = $addon['price_type'];
				$addon_price = 'percentage_based' === $price_type ? $addon['price'] : $this->multi_currency->get_price( $addon['price'], 'product' );

				switch ( $price_type ) {
					case 'percentage_based':
						$price         += (float) ( $cart_item['data']->get_price( 'view' ) * ( $addon_price / 100 ) );
						$regular_price += (float) ( $regular_price * ( $addon_price / 100 ) );
						$sale_price    += (float) ( $sale_price * ( $addon_price / 100 ) );

						$cart_item['data']->update_meta_data(
							'wcpay_mc_percentage_currency_amount',
							$cart_item['addons_price_before_calc'] * ( $addon_price / 100 )
						);
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

			$cart_item['data']->set_price( $price );
			$cart_item['data']->update_meta_data( 'wcpay_mc_converted', 1 );

			// Only update regular price if it was defined.
			$has_regular_price = is_numeric( $cart_item['data']->get_regular_price( 'edit' ) );
			if ( $has_regular_price ) {
				$cart_item['data']->set_regular_price( $regular_price );
			}

			// Only update sale price if it was defined.
			$has_sale_price = is_numeric( $cart_item['data']->get_sale_price( 'edit' ) );
			if ( $has_sale_price ) {
				$cart_item['data']->set_sale_price( $sale_price );
			}
		}

		return $cart_item;
	}

	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( ! empty( $values['addons'] ) ) {
			$cart_item['addons'] = $values['addons'];
			$cart_item           = $this->add_cart_item( $cart_item );
		}

		return $cart_item;
	}

	public function get_mini_cart_price( $price, $original_price ) {
		$cart = WC()->cart->get_cart_contents();

		foreach ( $cart as $item ) {
			/**
			 * This is a best guess scenario, and it only affects the display.
			 * For example, if a product has a flat fee add on, and a percentage add on, and for some reason
			 * those equal the same amount, then the flat fee add on's price will not be converted in the mini cart.
			 * 
			 * ..not presently working if taxes are included in prices.
			 */
			$percentage_price = $item['data']->get_meta( 'wcpay_mc_percentage_currency_amount' );
			if ( (string) $price === (string) $percentage_price ) {
				return $price;
			}
		}

		if ( $this->utils->is_call_in_backtrace( [ 'woocommerce_mini_cart' ] )
			&& $this->utils->is_call_in_backtrace( [ 'WC_Product_Addons_Cart->get_item_data' ] ) ) {
			$price = $this->multi_currency->get_price( $price, 'product' );
		}

		return $price;
	}

	public function get_display_price( $price, $qty, $product ) {
		$cart = WC()->cart->get_cart_contents();

		foreach ( $cart as $item ) {
			/**
			 * This is a best guess scenario, and it only affects the display.
			 * For example, if a product has a flat fee add on, and a percentage add on, and for some reason
			 * those equal the same amount, then the flat fee add on's price will not be converted in the display price.
			 * The display price is used in the cart and checkout page, along with the line item meta in orders.
			 * 
			 * ..not presently working if taxes are included in prices.
			 */
			// $item_price = $item['data']->get_meta( 'wcpay_mc_percentage_currency_amount' );
			// $item_qty = $item['quantity'];
			// $item_id = $item['data']->get_id();
			// $prod_id = $product->get_id();
			if ( (string) $price === (string) $item['data']->get_meta( 'wcpay_mc_percentage_currency_amount' )
				&& $item['data']->get_id() === $product->get_id() ) {
				return $price;
			}
		}

		if ( $this->utils->is_call_in_backtrace( [ 'WC_Product_Addons_Helper::get_product_addon_price_for_display' ] ) ) {
			$calls = [
				'WC_Product_Addons_Cart->order_line_item', // Fixes the values entered into order line item meta.
				'WC_Product_Addons_Cart->get_item_data', // Fixes the values in the cart and checkout pages.
			];

			if ( $this->utils->is_call_in_backtrace( $calls ) ) {
				$price = $this->multi_currency->get_price( $price, 'product' );
			}
		}

		return $price;
	}

	public function product_addons_params( $params ) {
		// Fixes currency formatting issues.
		$params['currency_format_num_decimals'] = wc_get_price_decimals();
		$params['currency_format_decimal_sep']  = wc_get_price_decimal_separator();
		$params['currency_format_thousand_sep'] = wc_get_price_thousand_separator();

		return $params;
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

		// if ( $this->utils->is_call_in_backtrace( [ 'WCPay\MultiCurrency\Compatibility->get_unconverted_price' ] ) ) {
		// 	return false;
		// }

		// Correct the percentage price in the addon name in the cart.
		if ( $this->utils->is_call_in_backtrace( [ 'WC_Product_Addons_Cart->get_item_data' ] )
			&& $this->utils->is_call_in_backtrace( [ 'WC_Cart->get_product_price' ] ) ) {
				return false;
		}

		// Check for cart items to see if they have already been converted.
		if ( 1 === $product->get_meta( 'wcpay_mc_converted' ) ) {
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

		return true;
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

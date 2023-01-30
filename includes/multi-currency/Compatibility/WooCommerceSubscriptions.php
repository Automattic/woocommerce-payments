<?php
/**
 * Class WooCommerceSubscriptions
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WC_Payments_Features;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Subscriptions Plugin and WCPay Subscriptions.
 */
class WooCommerceSubscriptions extends BaseCompatibility {

	/**
	 * Subscription switch cart item.
	 *
	 * @var string
	 */
	public $switch_cart_item = '';

	/**
	 * If we are running through our filters.
	 *
	 * @var bool
	 */
	private $running_override_selected_currency_filters = false;

	/**
	 * Init the class.
	 *
	 * @return  void
	 */
	protected function init() {
		// Add needed actions and filters if WC Subscriptions or WCPay Subscriptions are active.
		if ( class_exists( 'WC_Subscriptions' ) || WC_Payments_Features::is_wcpay_subscriptions_enabled() ) {
			if ( ! is_admin() && ! defined( 'DOING_CRON' ) ) {
				add_filter( 'woocommerce_subscriptions_product_price', [ $this, 'get_subscription_product_price' ], 50, 2 );
				add_filter( 'woocommerce_product_get__subscription_sign_up_fee', [ $this, 'get_subscription_product_signup_fee' ], 50, 2 );
				add_filter( 'woocommerce_product_variation_get__subscription_sign_up_fee', [ $this, 'get_subscription_product_signup_fee' ], 50, 2 );
				add_filter( 'option_woocommerce_subscriptions_multiple_purchase', [ $this, 'maybe_disable_mixed_cart' ], 50 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'override_selected_currency', [ $this, 'override_selected_currency' ], 50 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_product_price', [ $this, 'should_convert_product_price' ], 50, 2 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_coupon_amount', [ $this, 'should_convert_coupon_amount' ], 50, 2 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_hide_widgets', [ $this, 'should_hide_widgets' ], 50 );
			}
		}
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
		if ( ! $price || ! $this->should_convert_product_price( true, $product ) ) {
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
	 * @param mixed $return Default is false, but could be three letter currency code.
	 *
	 * @return mixed Three letter currency code or false if not.
	 */
	public function override_selected_currency( $return ) {
		// If it's not false, return it.
		if ( $return || $this->running_override_selected_currency_filters ) {
			return $return;
		}

		$subscription_renewal = $this->cart_contains_renewal();
		if ( $subscription_renewal ) {
			$order = wc_get_order( $subscription_renewal['subscription_renewal']['renewal_order_id'] );
			return $order ? $order->get_currency() : $return;
		}

		// The running_override_selected_currency_filters property has been added here due to if it isn't, it will create an infinite loop of calls.
		if ( isset( WC()->session ) && WC()->session->get( 'order_awaiting_payment' ) ) {
			$this->running_override_selected_currency_filters = true;
			$order = wc_get_order( WC()->session->get( 'order_awaiting_payment' ) );
			$this->running_override_selected_currency_filters = false;
			if ( $order && $this->order_contains_renewal( $order ) ) {
				return $order->get_currency();
			}
		}

		// The running_override_selected_currency_filters property is used to avoid an infinite loop
		// that can occur on the product page when `get_subscription()` is used.
		$switch_id = $this->get_subscription_switch_id_from_superglobal();
		if ( $switch_id ) {
			$this->running_override_selected_currency_filters = true;
			$switch_subscription                              = $this->get_subscription( $switch_id );
			$this->running_override_selected_currency_filters = false;
			return $switch_subscription ? $switch_subscription->get_currency() : $return;
		}

		$switch_cart_items = $this->get_subscription_switch_cart_items();
		if ( 0 < count( $switch_cart_items ) ) {
			$switch_cart_item    = array_shift( $switch_cart_items );
			$switch_subscription = $this->get_subscription( $switch_cart_item['subscription_switch']['subscription_id'] );
			return $switch_subscription ? $switch_subscription->get_currency() : $return;
		}

		$subscription_resubscribe = $this->cart_contains_resubscribe();
		if ( $subscription_resubscribe ) {
			$subscription = $this->get_subscription( $subscription_resubscribe['subscription_resubscribe']['subscription_id'] );
			return $subscription ? $subscription->get_currency() : $return;
		}

		return $return;
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

		return $return;
	}

	/**
	 * Checks to see if the coupon's amount should be converted.
	 *
	 * @param bool   $return Whether to convert the coupon's price or not. Default is true.
	 * @param object $coupon Coupon object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_coupon_amount( bool $return, $coupon ): bool {
		// If it's already false, return it.
		if ( ! $return ) {
			return $return;
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

		return $return;
	}

	/**
	 * Checks to see if the widgets should be hidden.
	 *
	 * @param bool $return Whether widgets should be hidden or not. Default is false.
	 *
	 * @return bool
	 */
	public function should_hide_widgets( bool $return ): bool {
		// If it's already true, return it.
		if ( $return ) {
			return $return;
		}

		if ( $this->cart_contains_renewal()
			|| $this->get_subscription_switch_id_from_superglobal()
			|| 0 < count( $this->get_subscription_switch_cart_items() )
			|| $this->cart_contains_resubscribe() ) {
			return true;
		}

		return $return;
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
	 * Checks an order  to see if it contains a subscription product renewal.
	 *
	 * @param object $order Order object.
	 *
	 * @return bool The cart item containing the renewal as an array, else false.
	 */
	private function order_contains_renewal( $order ): bool {
		if ( ! function_exists( 'wcs_order_contains_renewal' ) ) {
			return false;
		}
		return wcs_order_contains_renewal( $order );
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
	 * Getter for subscription objects.
	 *
	 * @param  mixed $the_subscription Post object or post ID of the order.
	 * @return mixed The subscription object, or false if it cannot be found.
	 *               Note: this is WC_Subscription|bool in normal use, but in tests
	 *               we use WC_Order to simulate a subscription (hence `mixed`).
	 */
	private function get_subscription( $the_subscription ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return false;
		}
		return wcs_get_subscription( $the_subscription );
	}

	/**
	 * Checks $_GET superglobal for a switch ID from the `switch-subscription` param if it exists.
	 * This `switch-subscription` param is added to the URL when a customer
	 * has initiated a switch from the My Account → Subscription page.
	 *
	 * @return int|bool The ID of the subscription being switched, or false if it cannot be found.
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

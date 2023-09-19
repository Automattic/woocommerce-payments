<?php
/**
 * Class WooCommerceSubscriptions
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WC_Payments_Features;
use WCPay\Logger;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Subscriptions Plugin and WCPay Subscriptions.
 */
class WooCommerceSubscriptions extends BaseCompatibility {

	/**
	 * Our allowed subscription types.
	 */
	const SUBSCRIPTION_TYPES = [ 'renewal', 'resubscribe', 'switch' ];

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
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_disable_currency_switching', [ $this, 'should_disable_currency_switching' ], 50 );
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

		$item = $this->get_subscription_type_from_cart( 'switch' );
		if ( $item ) {
			$item_id                = ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
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
						if ( '_subscription_sign_up_fee' === $meta->get_data()['key'] && ! empty( $meta->get_changes() ) ) {
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
		if ( $this->get_subscription_type_from_cart( 'switch' ) ) {
			return 'no';
		}

		return $value;
	}

	/**
	 * Checks to see if the if the selected currency needs to be overridden.
	 *
	 * The running_override_selected_currency_filters property is used here to avoid infinite loops.
	 *
	 * @param mixed $return Default is false, but could be three letter currency code.
	 *
	 * @return mixed Three letter currency code or false if not.
	 */
	public function override_selected_currency( $return ) {
		// If it's not false, or we are already running filters, exit.
		if ( $return || $this->running_override_selected_currency_filters ) {
			return $return;
		}

		// Loop through subscription types and check for cart items.
		foreach ( self::SUBSCRIPTION_TYPES as $type ) {
			$cart_item = $this->get_subscription_type_from_cart( $type );
			if ( $cart_item ) {
				$this->running_override_selected_currency_filters = true;

				// If we have a cart item, then we can get the order or subscription to pull the currency from.
				$subscription_type = 'subscription_' . $type;
				$subscription      = $this->get_subscription( $cart_item[ $subscription_type ]['subscription_id'] );

				$this->running_override_selected_currency_filters = false;
				return $subscription ? $subscription->get_currency() : $return;
			}
		}

		// This instance is for when the customer lands on the product page to choose a new subscription tier.
		$switch_subscription = $this->get_subscription_from_superglobal_switch_id();
		return $switch_subscription ? $switch_subscription->get_currency() : $return;
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
		if ( $this->get_subscription_type_from_cart( 'renewal' )
			|| $this->get_subscription_type_from_cart( 'resubscribe' ) ) {
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
		$subscription_renewal = $this->get_subscription_type_from_cart( 'renewal' );
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
	 * Checks to see if currency switching should be disabled.
	 *
	 * @param bool $return Whether widgets should be hidden or not. Default is false.
	 *
	 * @return bool
	 */
	public function should_disable_currency_switching( bool $return ): bool {
		// If it's already true, return it.
		if ( $return ) {
			return $return;
		}

		if ( $this->get_subscription_type_from_cart( 'renewal' )
			|| $this->get_subscription_type_from_cart( 'resubscribe' )
			|| $this->get_subscription_type_from_cart( 'switch' )
			|| $this->get_subscription_from_superglobal_switch_id() ) {
			return true;
		}

		return $return;
	}

	/**
	 * Checks the cart values to see if there are subscriptions with specific types present.
	 *
	 * This checks both the cart itself and the session. This is due to there are times when an item may be present in
	 * one place and not the other. We need to make sure that if an item is in either we are not creating double conversions.
	 *
	 * @param string $type The type of subscription to look for in the cart.
	 *
	 * @return mixed False if none found, or the subscription cart item as an array.
	 */
	private function get_subscription_type_from_cart( $type ) {
		// Make sure we're looking for allowed types.
		if ( ! in_array( $type, self::SUBSCRIPTION_TYPES, true ) ) {
			return false;
		}

		// Set the sub type cart key.
		$subscription_type = 'subscription_' . $type;

		// Go through each cart item and if it matches the type, return that item.
		if ( isset( WC()->cart ) && is_array( WC()->cart->cart_contents ) && ! empty( WC()->cart->cart_contents ) ) {
			foreach ( WC()->cart->cart_contents as $cart_item ) {
				if ( isset( $cart_item[ $subscription_type ] ) ) {
					return $cart_item;
				}
			}
		}

		// Go through each session cart item and if it matches the type, return that item.
		if ( isset( WC()->session ) && is_array( WC()->session->get( 'cart' ) ) && ! empty( WC()->session->get( 'cart' ) ) ) {
			foreach ( WC()->session->get( 'cart' ) as $cart_item ) {
				if ( isset( $cart_item[ $subscription_type ] ) ) {
					return $cart_item;
				}
			}
		}

		return false;
	}

	/**
	 * Getter for subscription objects.
	 *
	 * @param  mixed $the_subscription Post object or post ID of the order.
	 *
	 * @return mixed The subscription object, or false if it cannot be found.
	 *               Note: This should be WC_Subscription|bool, but Psalm throws errors like:
	 *                     Docblock-defined class, interface or enum named WC_Subscription does not exist (see https://psalm.dev/200)
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
	 * @return mixed The subscription object, or false if it cannot be found.
	 *               Note: This should be WC_Subscription|bool, but Psalm throws errors like:
	 *                     Docblock-defined class, interface or enum named WC_Subscription does not exist (see https://psalm.dev/200)
	 */
	private function get_subscription_from_superglobal_switch_id() {
		// Return false if there's no nonce, or if it fails.
		if ( ! isset( $_GET['_wcsnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wcsnonce'] ), 'wcs_switch_request' ) ) {
			return false;
		}

		// Return false if the param isn't set, or if it isn't numeric.
		if ( ! isset( $_GET['switch-subscription'] ) || ! is_numeric( $_GET['switch-subscription'] ) ) {
			return false;
		}

		// Get the switch ID from the param.
		$switch_id = (int) sanitize_key( $_GET['switch-subscription'] );

		// Get the sub, preventing an infinite loop with running_override_selected_currency_filters.
		$this->running_override_selected_currency_filters = true;
		$switch_subscription                              = $this->get_subscription( $switch_id );
		$this->running_override_selected_currency_filters = false;

		// Confirm the sub user matches current user, and return the sub.
		if ( $switch_subscription && $switch_subscription->get_customer_id() === get_current_user_id() ) {
			return $switch_subscription;
		} else {
			Logger::notice( 'User (' . get_current_user_id() . ') attempted to switch a subscription (' . $switch_subscription->get_id() . ') not assigned to them.' );
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

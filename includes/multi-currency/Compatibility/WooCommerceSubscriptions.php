<?php
/**
 * Class WooCommerceSubscriptions
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WC_Order;
use WC_Subscription;
use WCPay\MultiCurrency\Logger;
use WCPay\MultiCurrency\FrontendCurrencies;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Subscriptions Plugin and WCPay Subscriptions.
 */
class WooCommerceSubscriptions extends BaseCompatibility {

	/**
	 * Subscription cart item types whose currency comes from the subscription in the cart item's `subscription_id`.
	 */
	const SUBSCRIPTION_TYPES = [ 'renewal', 'resubscribe', 'switch' ];

	/**
	 * Cart item types that pay an existing order again. Their cart items hold an `order_id` instead of a
	 * `subscription_id`, so they are kept out of SUBSCRIPTION_TYPES.
	 */
	private const ORDER_PAYMENT_TYPES = [ 'initial_payment' ];

	/**
	 * Session key identifying the coupon copies in `wcs_renewal_coupons` that this class stored unconverted.
	 *
	 * Holds STORING_SESSION_COUPONS while a cart is being set up, then a hash of the copies stored.
	 */
	private const UNCONVERTED_SESSION_COUPONS_KEY = 'wcpay_multi_currency_unconverted_session_coupons';

	/**
	 * Value of UNCONVERTED_SESSION_COUPONS_KEY while a cart is being set up and its coupon copies are stored.
	 */
	private const STORING_SESSION_COUPONS = 'storing';

	/**
	 * Subscription switch cart item.
	 *
	 * @var string
	 */
	public $switch_cart_item = '';

	/**
	 * The current subscription being iterated through on the My Account > Subscriptions page.
	 *
	 * @var WC_Subscription|null
	 */
	public $current_my_account_subscription = null;

	/**
	 * The FrontendCurrencies object.
	 *
	 * @var FrontendCurrencies
	 */
	public $frontend_currencies;

	/**
	 * If we are running through our filters.
	 *
	 * @var bool
	 */
	private $running_override_selected_currency_filters = false;

	/**
	 * Currencies of signup orders paid through the cart, keyed by order ID.
	 *
	 * @var array
	 */
	private $initial_payment_order_currencies = [];

	/**
	 * Init the class.
	 *
	 * @return  void
	 */
	public function init() {
		// Add needed actions and filters if WC Subscriptions or WCPay Subscriptions are active.
		if ( class_exists( 'WC_Subscriptions' ) || class_exists( 'WC_Payments_Subscriptions' ) ) {
			if ( ! is_admin() && ! defined( 'DOING_CRON' ) ) {
				$this->frontend_currencies = $this->multi_currency->get_frontend_currencies();

				add_filter( 'woocommerce_subscriptions_product_price', [ $this, 'get_subscription_product_price' ], 50, 2 );
				add_filter( 'woocommerce_product_get__subscription_sign_up_fee', [ $this, 'get_subscription_product_signup_fee' ], 50, 2 );
				add_filter( 'woocommerce_product_variation_get__subscription_sign_up_fee', [ $this, 'get_subscription_product_signup_fee' ], 50, 2 );
				add_filter( 'option_woocommerce_subscriptions_multiple_purchase', [ $this, 'maybe_disable_mixed_cart' ], 50 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'override_selected_currency', [ $this, 'override_selected_currency' ], 50 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_product_price', [ $this, 'should_convert_product_price' ], 50, 2 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_coupon_amount', [ $this, 'should_convert_coupon_amount' ], 50, 2 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_coupon_min_max_amount', [ $this, 'should_convert_coupon_min_max_amount' ], 50, 2 );
				add_action( 'woocommerce_setup_cart_for_subscription_renewal', [ $this, 'start_storing_unconverted_session_coupons' ], 0 );
				add_action( 'woocommerce_setup_cart_for_subscription_initial_payment', [ $this, 'start_storing_unconverted_session_coupons' ], 0 );
				add_action( 'woocommerce_setup_cart_for_subscription_renewal', [ $this, 'finish_storing_unconverted_session_coupons' ], PHP_INT_MAX );
				add_action( 'woocommerce_setup_cart_for_subscription_initial_payment', [ $this, 'finish_storing_unconverted_session_coupons' ], PHP_INT_MAX );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_disable_currency_switching', [ $this, 'should_disable_currency_switching' ], 50 );
				add_filter( 'woocommerce_subscription_price_string_details', [ $this, 'maybe_set_current_my_account_subscription' ], 50, 2 );
				add_filter( 'woocommerce_get_formatted_subscription_total', [ $this, 'maybe_clear_current_my_account_subscription' ], 50, 2 );
				add_filter( 'wc_price', [ $this, 'maybe_get_explicit_format_for_subscription_total' ], 50, 1 );
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
	 * @param mixed $selected_currency Default is false, but could be three letter currency code.
	 *
	 * @return mixed Three letter currency code or false if not.
	 */
	public function override_selected_currency( $selected_currency ) {
		// If it's not false, or we are already running filters, exit.
		if ( $selected_currency || $this->running_override_selected_currency_filters ) {
			return $selected_currency;
		}

		// If we have a subscription in $current_my_account_subscription, we want to use the currency from that subscription.
		if ( $this->is_current_my_account_subscription_set() ) {
			return $this->current_my_account_subscription->get_currency();
		}

		// Loop through subscription types and check for cart items.
		foreach ( self::SUBSCRIPTION_TYPES as $type ) {
			$cart_item = $this->get_subscription_type_from_cart( $type );
			if ( $cart_item ) {
				// If we have a cart item, then we can get the order or subscription to pull the currency from.
				$subscription_type = 'subscription_' . $type;
				$subscription      = $this->get_subscription_without_override( $cart_item[ $subscription_type ]['subscription_id'] );

				return $subscription ? $subscription->get_currency() : $selected_currency;
			}
		}

		// A failed or pending signup order is paid again through a cart rebuilt from it, in the order's currency.
		$initial_payment_currency = $this->get_initial_payment_order_currency();
		if ( $initial_payment_currency ) {
			return $initial_payment_currency;
		}

		// This instance is for when the customer lands on the product page to choose a new subscription tier.
		$switch_subscription = $this->get_subscription_from_superglobal_switch_id();
		return $switch_subscription ? $switch_subscription->get_currency() : $selected_currency;
	}

	/**
	 * Checks to see if the product's price should be converted.
	 *
	 * @param bool        $should_convert Whether to convert the product's price or not. Default is true.
	 * @param object|null $product        Product object to test. Without one, a renewal or resubscribe anywhere in the cart counts.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( bool $should_convert, $product ): bool {
		// If it's already false, return it.
		if ( ! $should_convert ) {
			return $should_convert;
		}

		$calls = [
			'WC_Cart_Totals->calculate_item_totals',
			'WC_Cart->get_product_subtotal',
			'wc_get_price_excluding_tax',
			'wc_get_price_including_tax',
		];

		// Check for renewal. Only the renewal's own product has its price set from the order; any other product in the cart still needs to be converted.
		if ( $this->get_subscription_type_from_cart( 'renewal', $product ) ) {
			// When WCPay Subs programmatically sets up the cart, we need to return the
			// converted price so the user lands at the checkout with the correct price.
			if ( $this->utils->is_call_in_backtrace( [ 'WCS_Cart_Renewal->setup_cart' ] ) ) {
				return $should_convert;
			}

			if ( $this->utils->is_call_in_backtrace( $calls ) ) {
				return false;
			}
		}

		// Check for resubscribe. As with renewals, only the resubscribe item's own product keeps its unconverted price.
		if ( $this->get_subscription_type_from_cart( 'resubscribe', $product )
			&& $this->utils->is_call_in_backtrace( $calls ) ) {
			return false;
		}

		// WCPay Subs does a check against the product price and the total, we need to return the actual product price for this check.
		if ( $this->utils->is_call_in_backtrace( [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] )
			&& $this->utils->is_call_in_backtrace( [ 'WC_Product->get_price' ] ) ) {
			return false;
		}

		return $should_convert;
	}

	/**
	 * Checks to see if the coupon's amount should be converted.
	 *
	 * @param bool   $should_convert Whether to convert the coupon's price or not. Default is true.
	 * @param object $coupon         Coupon object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_coupon_amount( bool $should_convert, $coupon ): bool {
		// If it's already false, return it.
		if ( ! $should_convert ) {
			return $should_convert;
		}

		// We do not need to convert percentage coupons.
		if ( $this->is_coupon_type( $coupon, 'subscription_percent' ) ) {
			return false;
		}

		// The amount of a pseudo coupon is a discount taken from the order or subscription, so it is already in the order's currency.
		if ( $this->is_coupon_type( $coupon, 'subscription_pseudo' ) ) {
			return false;
		}

		return ! $this->is_coupon_being_stored_in_session() && ! $this->is_coupon_from_converted_session_copy( $coupon );
	}

	/**
	 * Checks to see if the coupon's minimum and maximum amounts should be converted.
	 *
	 * @param mixed  $should_convert Whether to convert the coupon's minimum and maximum amounts or not. Default is true.
	 * @param object $coupon         Coupon object to test.
	 *
	 * @return bool True if they should be converted.
	 */
	public function should_convert_coupon_min_max_amount( $should_convert, $coupon = null ): bool {
		// If it's already false, return it.
		if ( ! $should_convert ) {
			return false;
		}

		return ! $this->is_coupon_being_stored_in_session() && ! $this->is_coupon_from_converted_session_copy( $coupon );
	}

	/**
	 * Marks the coupon copies Subscriptions is about to store in the session, while the cart is set up, as unconverted.
	 *
	 * Copies stored by earlier versions hold amounts that were converted when they were stored, so they must not be converted again.
	 *
	 * @return void
	 */
	public function start_storing_unconverted_session_coupons() {
		if ( ! isset( WC()->session ) ) {
			return;
		}

		WC()->session->set( self::UNCONVERTED_SESSION_COUPONS_KEY, self::STORING_SESSION_COUPONS );
	}

	/**
	 * Records which coupon copies were stored unconverted once the cart is set up.
	 *
	 * A hash of the copies, rather than a flag, so that copies rewritten later by a version without this
	 * code (e.g. after a rollback) aren't taken for unconverted ones.
	 *
	 * @return void
	 */
	public function finish_storing_unconverted_session_coupons() {
		if ( ! isset( WC()->session ) ) {
			return;
		}

		WC()->session->set( self::UNCONVERTED_SESSION_COUPONS_KEY, $this->get_session_coupons_hash( WC()->session->get( 'wcs_renewal_coupons' ) ) );
	}

	/**
	 * Checks to see if currency switching should be disabled.
	 *
	 * @param bool $should_disable Whether widgets should be hidden or not. Default is false.
	 *
	 * @return bool
	 */
	public function should_disable_currency_switching( bool $should_disable ): bool {
		// If it's already true, return it.
		if ( $should_disable ) {
			return $should_disable;
		}

		if ( $this->get_subscription_type_from_cart( 'renewal' )
			|| $this->get_subscription_type_from_cart( 'resubscribe' )
			|| $this->get_subscription_type_from_cart( 'switch' )
			|| $this->get_subscription_type_from_cart( 'initial_payment' )
			|| $this->get_subscription_from_superglobal_switch_id() ) {
			return true;
		}

		return $should_disable;
	}

	/**
	 * Maybe sets the current_my_account_subscription var and clears the FrontendCurrencies cache.
	 *
	 * The my-subscriptions.php template file calls $subscription->get_formatted_order_total(), which then calls $subscription->get_price_string_details().
	 * At that point in time, if we have certain calls in the backtrace, we need to add the subscription into $current_my_account_subscription so that we
	 * are able to use it later on in the maybe_get_explicit_format_for_subscription_total filter.
	 *
	 * @param array           $subscription_details The details related to the subscription.
	 * @param WC_Subscription $subscription         The subscription being acted on.
	 *
	 * @return array The unmodified subscription details.
	 */
	public function maybe_set_current_my_account_subscription( $subscription_details, $subscription ): array {
		$calls = [
			'WCS_Template_Loader::get_my_subscriptions ',
			'WC_Subscription->get_formatted_order_total',
		];
		if ( $this->utils->is_call_in_backtrace( $calls ) ) {
			// If we have our calls in the backtrace, we set our cached sub and clear the FrontendCurrencies cache.
			$this->current_my_account_subscription = $subscription;
			$this->frontend_currencies->selected_currency_changed();
		}

		return $subscription_details;
	}

	/**
	 * Maybe clears the current_my_account_subscription var and clears the FrontendCurrencies cache.
	 *
	 * During the $subscription->get_formatted_order_total() call we may set the current_my_account_subscription var, and we want to clear it as soon as
	 * we no longer need it. The woocommerce_get_formatted_subscription_total filter is at the end of that call, so we check to see if the var is set,
	 * and if it is, we clear it, and we also clear the FrontendCurrencies cache.
	 *
	 * @param string          $formatted    The subscription formatted total.
	 * @param WC_Subscription $_unused_subscription The subscription being acted on.
	 *
	 * @return string The unmodified subscription formatted total.
	 */
	public function maybe_clear_current_my_account_subscription( $formatted, $_unused_subscription ): string {
		if ( $this->is_current_my_account_subscription_set() ) {
			$this->current_my_account_subscription = null;
			$this->frontend_currencies->selected_currency_changed();
		}

		return $formatted;
	}

	/**
	 * If the current_my_account_subscription var is set, then we use that subscription's currency in order to set the explicit total.
	 *
	 * @param string $html_price Price HTML markup.
	 *
	 * @return string The wc_price with HTML wrapping, possibly with the currency code added for explicit formatting.
	 */
	public function maybe_get_explicit_format_for_subscription_total( $html_price ): string {
		if ( ! $this->is_current_my_account_subscription_set() ) {
			return $html_price;
		}

		if ( ! $this->multi_currency->has_additional_currencies_enabled() ) {
			return $html_price;
		}

		/**
		 * Get the currency code from the subscription, then return the explicit price.
		 */
		$currency_code = $this->current_my_account_subscription->get_currency() ?? get_woocommerce_currency();

		// This is sourced from WC_Payments_Explicit_Price_Formatter::get_explicit_price_with_currency.
		$price_to_check = \WC_Payments_Utils::decode_html_entities( wp_strip_all_tags( $html_price ) );

		if ( false === strpos( $price_to_check, trim( $currency_code ) ) ) {
			return $html_price . ' ' . $currency_code;
		}

		return $html_price;
	}

	/**
	 * Simple check to see if the current_my_account_subscription is a WC_Subscription object.
	 *
	 * @return bool
	 */
	private function is_current_my_account_subscription_set(): bool {
		return is_a( $this->current_my_account_subscription, 'WC_Subscription' );
	}

	/**
	 * Checks the cart values to see if there are subscriptions with specific types present.
	 *
	 * This checks both the cart itself and the session. This is due to there are times when an item may be present in
	 * one place and not the other. We need to make sure that if an item is in either we are not creating double conversions.
	 *
	 * If a product is passed, only the cart item holding that product object is a match. The objects are compared rather
	 * than the IDs, as the same product can also be in the cart as a regular item.
	 *
	 * @param string      $type    The type of subscription to look for in the cart.
	 * @param object|null $product Optional. The product object the cart item needs to hold.
	 *
	 * @return mixed False if none found, or the subscription cart item as an array.
	 */
	private function get_subscription_type_from_cart( $type, $product = null ) {
		// Make sure we're looking for allowed types.
		if ( ! in_array( $type, array_merge( self::SUBSCRIPTION_TYPES, self::ORDER_PAYMENT_TYPES ), true ) ) {
			return false;
		}

		// Set the sub type cart key.
		$subscription_type = 'subscription_' . $type;

		// Go through each cart item and if it matches the type, return that item.
		if ( isset( WC()->cart ) && is_array( WC()->cart->cart_contents ) && ! empty( WC()->cart->cart_contents ) ) {
			foreach ( WC()->cart->cart_contents as $cart_item ) {
				if ( isset( $cart_item[ $subscription_type ] ) && ( ! $product || ( $cart_item['data'] ?? null ) === $product ) ) {
					return $cart_item;
				}
			}
		}

		// The session cart items do not hold product objects, so there is nothing to match the product against.
		if ( $product ) {
			return false;
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
	 * Gets the currency of the signup order being paid through the cart, if there is one.
	 *
	 * The currency is kept per order, because this runs on every price read.
	 *
	 * @return string|false Three letter currency code, or false if the cart is not paying for a signup order.
	 */
	private function get_initial_payment_order_currency() {
		$cart_item = $this->get_subscription_type_from_cart( 'initial_payment' );
		$order_id  = absint( $cart_item['subscription_initial_payment']['order_id'] ?? 0 );
		if ( ! $order_id ) {
			return false;
		}

		if ( ! array_key_exists( $order_id, $this->initial_payment_order_currencies ) ) {
			$order = $this->load_without_override(
				function () use ( $order_id ) {
					return wc_get_order( $order_id );
				}
			);

			$this->initial_payment_order_currencies[ $order_id ] = $order instanceof WC_Order ? $order->get_currency() : false;
		}

		return $this->initial_payment_order_currencies[ $order_id ];
	}

	/**
	 * Getter for subscription objects.
	 *
	 * @param  mixed $the_subscription Post object or post ID of the order.
	 * @return WC_Subscription|bool The subscription object, or false if it cannot be found.
	 */
	private function get_subscription( $the_subscription ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return false;
		}
		return wcs_get_subscription( $the_subscription );
	}

	/**
	 * Gets a subscription while the currency override is turned off, as loading it reads the selected currency.
	 *
	 * @param  mixed $the_subscription Post object or post ID of the order.
	 * @return WC_Subscription|bool The subscription object, or false if it cannot be found.
	 */
	private function get_subscription_without_override( $the_subscription ) {
		return $this->load_without_override(
			function () use ( $the_subscription ) {
				return $this->get_subscription( $the_subscription );
			}
		);
	}

	/**
	 * Loads an order or subscription while the currency override is turned off, as loading it reads the selected currency.
	 *
	 * Those nested reads resolve without the override, so whatever FrontendCurrencies cached during the load
	 * is discarded. Otherwise decimals and formats would follow that currency instead of the loaded object's.
	 *
	 * @param callable $load Loads and returns the object.
	 *
	 * @return mixed Whatever $load returns.
	 */
	private function load_without_override( callable $load ) {
		$this->running_override_selected_currency_filters = true;
		try {
			return $load();
		} finally {
			// A caller may catch a failed load and carry on, e.g. the Store API for each request in a batch.
			$this->running_override_selected_currency_filters = false;
			$this->frontend_currencies->selected_currency_changed();
		}
	}

	/**
	 * Checks $_GET superglobal for a switch ID from the `switch-subscription` param if it exists.
	 * This `switch-subscription` param is added to the URL when a customer
	 * has initiated a switch from the My Account → Subscription page.
	 *
	 * @return WC_Subscription|bool The subscription object, or false if it cannot be found.
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
		$switch_subscription = $this->get_subscription_without_override( $switch_id );

		// Confirm the sub user matches current user, and return the sub.
		if ( $switch_subscription && $switch_subscription->get_customer_id() === get_current_user_id() ) {
			return $switch_subscription;
		} else {
			Logger::notice( 'User (' . get_current_user_id() . ') attempted to switch a subscription (' . $switch_subscription->get_id() . ') not assigned to them.' );
		}

		return false;
	}

	/**
	 * Checks to see if Subscriptions is copying a coupon into the session.
	 *
	 * When a cart is set up from an order or subscription (renewal, early renewal, initial payment), the coupons are
	 * copied into the session, and every later query for those coupons is built from that copy, whatever their type.
	 * The copy needs to keep the original amounts, so that they are converted only once, when they are used.
	 *
	 * @return bool True if the coupon is being copied.
	 */
	private function is_coupon_being_stored_in_session(): bool {
		// Coupons are only copied while one of these carts is set up (early renewal fires the renewal action),
		// so the backtrace is not inspected on every other coupon read.
		if ( ! doing_action( 'woocommerce_setup_cart_for_subscription_renewal' )
			&& ! doing_action( 'woocommerce_setup_cart_for_subscription_initial_payment' ) ) {
			return false;
		}

		// PHP names an inherited method after the class that declares it, so this also matches the initial payment and
		// early renewal carts, which inherit store_coupon() from WCS_Cart_Renewal.
		return $this->utils->is_call_in_backtrace( [ 'WCS_Cart_Renewal->store_coupon' ] );
	}

	/**
	 * Checks to see if the coupon is built from a session copy stored before the copies were marked as unconverted.
	 *
	 * Earlier versions converted the amounts when the copy was stored, so converting them again would apply the
	 * exchange rate twice. Such copies last until the cart is set up again, emptied, or the session expires.
	 *
	 * @param mixed $coupon Coupon to test.
	 *
	 * @return bool True if the coupon's amounts are already converted.
	 */
	private function is_coupon_from_converted_session_copy( $coupon ): bool {
		if ( ! $coupon instanceof \WC_Coupon || ! isset( WC()->session ) ) {
			return false;
		}

		$session_coupons = WC()->session->get( 'wcs_renewal_coupons' );
		if ( ! is_array( $session_coupons ) ) {
			return false;
		}

		$is_copied = false;
		foreach ( $session_coupons as $coupons ) {
			if ( is_array( $coupons ) && array_key_exists( $coupon->get_code(), $coupons ) ) {
				$is_copied = true;
				break;
			}
		}
		if ( ! $is_copied ) {
			return false;
		}

		$marker = WC()->session->get( self::UNCONVERTED_SESSION_COUPONS_KEY );

		return self::STORING_SESSION_COUPONS !== $marker && $this->get_session_coupons_hash( $session_coupons ) !== $marker;
	}

	/**
	 * Hashes the coupon copies stored in the session.
	 *
	 * @param mixed $session_coupons The value of `wcs_renewal_coupons`.
	 *
	 * @return string
	 */
	private function get_session_coupons_hash( $session_coupons ): string {
		return md5( (string) wp_json_encode( $session_coupons ) );
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

		$types = null;
		switch ( $type ) {
			case 'subscription_percent':
				$types = [ 'recurring_percent', 'sign_up_fee_percent', 'renewal_percent' ];
				break;

			case 'subscription_pseudo':
				$types = [ 'renewal_cart', 'initial_cart' ];
				break;
		}

		if ( in_array( $coupon->get_discount_type(), $types, true ) ) {
			return true;
		}
		return false;
	}
}

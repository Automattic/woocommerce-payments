<?php
/**
 * Class WC_Payments_Express_Checkout_Button_Helper
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Exceptions\Invalid_Price_Exception;
use WCPay\Logger;
use WCPay\PaymentMethods\Configs\Definitions\AmazonPayDefinition;

/**
 * Express Checkout Button Helper class.
 */
class WC_Payments_Express_Checkout_Button_Helper {
	/**
	 * Nonce action securing the tokenized cart Store API requests. Created
	 * client-side and verified on each tokenized-cart entry point, so it lives
	 * here as the single source of truth shared across those call sites.
	 */
	const TOKENIZED_CART_NONCE_ACTION = 'woopayments_tokenized_cart_nonce';

	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Whether the [product_page] shortcode context has been worked out for this request.
	 *
	 * Only ever set once the main query and its host post are available, so a caller that
	 * asks before WordPress has parsed the request cannot pin a "no" for the whole request.
	 *
	 * @var bool
	 */
	private $shortcode_context_resolved = false;

	/**
	 * Whether the host post embeds a [product_page] shortcode, however it resolves.
	 *
	 * @var bool
	 */
	private $has_product_page_shortcode = false;

	/**
	 * The product that shortcode embeds, or null when it names one that no longer exists.
	 *
	 * @var WC_Product|null
	 */
	private $shortcode_product = null;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway WCPay gateway.
	 * @param WC_Payments_Account      $account Account information.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway, WC_Payments_Account $account ) {
		$this->gateway = $gateway;
		$this->account = $account;
	}

	/**
	 * Gets the booking id from the cart.
	 * It's expected that the cart only contains one item which was added via ajax_add_to_cart.
	 * Used to remove the booking from WC Bookings in-cart status.
	 *
	 * @return int|false
	 */
	public function get_booking_id_from_cart() {
		$cart      = WC()->cart->get_cart();
		$cart_item = reset( $cart );

		if ( $cart_item && isset( $cart_item['booking']['_booking_id'] ) ) {
			return $cart_item['booking']['_booking_id'];
		}

		return false;
	}

	/**
	 * Builds the line items to pass to Express Checkout
	 *
	 * @param boolean $itemized_display_items Indicates whether to show subtotals or itemized views.
	 */
	public function build_display_items( $itemized_display_items = false ) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$items     = [];
		$discounts = 0;
		$currency  = get_woocommerce_currency();

		/**
		 * Filters whether to hide itemization and show only the subtotal in Express Checkout.
		 *
		 * @since 2.1.0
		 *
		 * @param bool $hide_itemization Whether to hide itemized display items.
		 */
		if ( ! apply_filters( 'wcpay_payment_request_hide_itemization', ! $itemized_display_items ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$amount         = $cart_item['line_subtotal'];
				$quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';

				$product_name = $cart_item['data']->get_name();

				$item_tax = $this->cart_prices_include_tax() ? ( $cart_item['line_subtotal_tax'] ?? 0 ) : 0;

				$item = [
					'label'  => $product_name . $quantity_label,
					'amount' => WC_Payments_Utils::prepare_amount( $amount + $item_tax, $currency ),
				];

				$items[] = $item;
			}
		}

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$discounts = wc_format_decimal( WC()->cart->get_cart_discount_total(), WC()->cart->dp );
		} else {
			$applied_coupons = array_values( WC()->cart->get_coupon_discount_totals() );

			foreach ( $applied_coupons as $amount ) {
				$discounts += (float) $amount;
			}
		}

		$discounts   = wc_format_decimal( $discounts, WC()->cart->dp );
		$tax         = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp );
		$shipping    = wc_format_decimal( WC()->cart->shipping_total, WC()->cart->dp );
		$items_total = wc_format_decimal( WC()->cart->cart_contents_total, WC()->cart->dp ) + $discounts;
		$order_total = version_compare( WC_VERSION, '3.2', '<' ) ? wc_format_decimal( $items_total + $tax + $shipping - $discounts, WC()->cart->dp ) : WC()->cart->get_total( '' );

		if ( ! $this->cart_prices_include_tax() ) {
			$items[] = [
				'label'  => esc_html( __( 'Tax', 'woocommerce-payments' ) ),
				'amount' => WC_Payments_Utils::prepare_amount( $tax, $currency ),
			];
		}

		if ( WC()->cart->needs_shipping() ) {
			$shipping_tax = $this->cart_prices_include_tax() ? WC()->cart->shipping_tax_total : 0;
			$items[]      = [
				'key'    => 'total_shipping',
				'label'  => esc_html( __( 'Shipping', 'woocommerce-payments' ) ),
				'amount' => WC_Payments_Utils::prepare_amount( $shipping + $shipping_tax, $currency ),
			];
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = [
				'key'    => 'total_discount',
				'label'  => esc_html( __( 'Discount', 'woocommerce-payments' ) ),
				'amount' => WC_Payments_Utils::prepare_amount( $discounts, $currency ),
			];
		}

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$cart_fees = WC()->cart->fees;
		} else {
			$cart_fees = WC()->cart->get_fees();
		}

		// Include fees and taxes as display items.
		foreach ( $cart_fees as $fee ) {
			$items[] = [
				'label'  => $fee->name,
				'amount' => WC_Payments_Utils::prepare_amount( $fee->amount, $currency ),
			];
		}

		return [
			'displayItems' => $items,
			'total'        => [
				'label'   => $this->get_total_label(),
				/**
				 * Filters the calculated total for the Express Checkout request.
				 *
				 * @since 2.1.0
				 *
				 * @param int      $prepared_total The prepared (Stripe-formatted) order total.
				 * @param string   $order_total    The raw cart total, as a numeric string.
				 * @param WC_Cart  $cart           The WooCommerce cart object.
				 */
				'amount'  => max( 0, apply_filters( 'wcpay_calculated_total', WC_Payments_Utils::prepare_amount( $order_total, $currency ), $order_total, WC()->cart ) ),
				'pending' => false,
			],
		];
	}

	/**
	 * Whether tax should be displayed on separate line in cart.
	 * returns true if tax is disabled or display of tax in checkout is set to inclusive.
	 *
	 * @return boolean
	 */
	public function cart_prices_include_tax() {
		return ! wc_tax_enabled() || 'incl' === get_option( 'woocommerce_tax_display_cart' );
	}

	/**
	 * Gets total label.
	 *
	 * @return string
	 */
	public function get_total_label() {
		// Get statement descriptor from API/cached account data.
		$statement_descriptor = $this->account->get_statement_descriptor();
		/**
		 * Filters the suffix appended to the Express Checkout total label.
		 *
		 * @since 2.1.0
		 *
		 * @param string $suffix The total label suffix.
		 */
		return str_replace( "'", '', $statement_descriptor ) . apply_filters( 'wcpay_payment_request_total_label_suffix', ' (via WooCommerce)' );
	}

	/**
	 * Gets quantity from request.
	 *
	 * @return float|int
	 */
	public function get_quantity() {
		// Express Checkout Element sends the quantity as qty. WooPay sends it as quantity.
		// wc_stock_amount() respects the store's decimal-quantity setting; wc_format_decimal()
		// normalizes localized separators ("0,25") before the cast so fractions survive.
		if ( isset( $_POST['quantity'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return max( 0, wc_stock_amount( (float) wc_format_decimal( wc_clean( wp_unslash( $_POST['quantity'] ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_POST['qty'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return max( 0, wc_stock_amount( (float) wc_format_decimal( wc_clean( wp_unslash( $_POST['qty'] ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} else {
			return 1;
		}
	}

	/**
	 * Checks if this is a product page or content contains a product_page shortcode.
	 *
	 * @return boolean
	 */
	public function is_product() {
		$this->resolve_shortcode_context();

		return is_product() || $this->has_product_page_shortcode;
	}

	/**
	 * Checks if this is the Pay for Order page.
	 *
	 * @return boolean
	 */
	public function is_pay_for_order_page() {
		return is_checkout() && isset( $_GET['pay_for_order'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Checks if this is the cart page or content contains a cart block.
	 *
	 * @return boolean
	 */
	public function is_cart() {
		return is_cart() || has_block( 'woocommerce/cart' );
	}

	/**
	 * Checks if this is the checkout page or content contains a cart block.
	 *
	 * @return boolean
	 */
	public function is_checkout() {
		return is_checkout() || has_block( 'woocommerce/checkout' );
	}

	/**
	 * Checks if a specific express checkout method is enabled at a given location.
	 *
	 * Uses the new location-centric settings (express_checkout_{location}_methods).
	 *
	 * @param string $location Location (product, cart, checkout).
	 * @param string $method_id Method identifier (payment_request, woopay, amazon_pay, link).
	 * @return boolean
	 */
	public function is_express_checkout_method_enabled_at( $location, $method_id ) {
		return in_array( $method_id, $this->get_methods_enabled_at( $location ), true );
	}

	/**
	 * Returns the methods the merchant enabled at the current page's location,
	 * straight from the location settings — without the currency or availability
	 * gating that `get_enabled_express_checkout_methods_for_context()` applies.
	 *
	 * The Store API cart response is currency-fresh but location-blind, so the
	 * client intersects it with this list to keep location gating intact when a
	 * method's currency availability changes after page load.
	 *
	 * @return string[] Method ids (e.g. ['payment_request', 'amazon_pay']).
	 */
	public function get_methods_enabled_at_current_location() {
		return $this->get_methods_enabled_at( $this->get_button_context() );
	}

	/**
	 * Checks whether cart contains a subscription product or this is a subscription product page.
	 *
	 * @return boolean
	 */
	public function has_subscription_product() {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) || ! class_exists( 'WC_Subscriptions_Cart' ) ) {
			return false;
		}

		if ( $this->is_product() ) {
			$product = $this->get_product();
			if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
				return true;
			}
		}

		if ( $this->is_checkout() || $this->is_cart() ) {
			if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
				return true;
			}
			if ( function_exists( 'wcs_cart_contains_renewal' ) && wcs_cart_contains_renewal() ) {
				return true;
			}
			if ( function_exists( 'wcs_cart_contains_resubscribe' ) && wcs_cart_contains_resubscribe() ) {
				return true;
			}
			if ( function_exists( 'wcs_cart_contains_switches' ) && wcs_cart_contains_switches() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if Amazon Pay can be used as an express checkout button.
	 *
	 * This validates:
	 * - Express checkout is not displayed in the payment methods list
	 * - Amazon Pay feature flag is enabled
	 * - Gateway exists and is available for express checkout
	 * - Tax settings are compatible (Amazon Pay doesn't support taxes based on billing address)
	 *
	 * @return boolean
	 */
	public function can_use_amazon_pay() {
		// When express checkout methods are displayed in the payment methods list,
		// Amazon Pay should not appear as a separate express button.
		if ( \WC_Payments::get_gateway()->is_express_checkout_in_payment_methods_enabled() ) {
			return false;
		}

		if ( ! WC_Payments_Features::is_amazon_pay_enabled() ) {
			return false;
		}

		$amazon_pay_gateway = WC_Payments::get_payment_gateway_by_id( AmazonPayDefinition::get_id() );
		if ( ! $amazon_pay_gateway ) {
			return false;
		}

		if ( ! $amazon_pay_gateway->is_available_for_express_checkout() ) {
			return false;
		}

		// Amazon Pay doesn't support taxes based on billing address.
		if ( wc_tax_enabled() && 'billing' === get_option( 'woocommerce_tax_based_on' ) && ! $this->is_pay_for_order_page() ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets the list of enabled express checkout methods for the current page context.
	 *
	 * This method checks:
	 * 1. The current page context (product, cart, checkout)
	 * 2. The location settings (express_checkout_{location}_methods)
	 * 3. The feature flags (is_payment_request_enabled, is_amazon_pay_enabled)
	 * 4. Currency availability (e.g., Amazon Pay checks currency restrictions)
	 *
	 * @return array Array of enabled method IDs (e.g., ['payment_request', 'amazon_pay']).
	 */
	public function get_enabled_express_checkout_methods_for_context() {
		$enabled_methods = [];
		$context         = $this->get_button_context();

		// If no valid context, return an empty array.
		if ( empty( $context ) ) {
			return $enabled_methods;
		}

		// Check Google Pay / Apple Pay (payment_request).
		if (
			$this->gateway->is_payment_request_enabled() &&
			$this->is_express_checkout_method_enabled_at( $context, 'payment_request' )
		) {
			$enabled_methods[] = 'payment_request';
		}

		// Check Amazon Pay.
		if (
			$this->can_use_amazon_pay() &&
			$this->is_express_checkout_method_enabled_at( $context, 'amazon_pay' )
		) {
			$enabled_methods[] = 'amazon_pay';
		}

		return $enabled_methods;
	}

	/**
	 * Gets settings that are shared between the Express Checkout button and the WooPay button.
	 *
	 * @return array
	 */
	public function get_common_button_settings() {
		$button_type = $this->gateway->get_option( 'payment_request_button_type' );

		return [
			'type'   => $button_type,
			'theme'  => $this->gateway->get_option( 'payment_request_button_theme' ),
			'height' => $this->get_button_height(),
			'radius' => $this->gateway->get_option( 'payment_request_button_border_radius' ),
		];
	}

	/**
	 * Gets the context for where the button is being displayed.
	 *
	 * @return string
	 */
	public function get_button_context() {
		if ( $this->is_product() ) {
			return 'product';
		}

		if ( $this->is_cart() ) {
			return 'cart';
		}

		if ( $this->is_pay_for_order_page() ) {
			return 'pay_for_order';
		}

		if ( $this->is_checkout() ) {
			return 'checkout';
		}

		return '';
	}

	/**
	 * Gets the button height.
	 *
	 * @return string
	 */
	public function get_button_height() {
		$height = $this->gateway->get_option( 'payment_request_button_size' );
		if ( 'medium' === $height ) {
			return '48';
		}

		if ( 'large' === $height ) {
			return '55';
		}

		// for the "default"/"small" and "catch-all" scenarios.
		return '40';
	}

	/**
	 * Get product from product page or product_page shortcode.
	 *
	 * @return WC_Product|false|null Product object.
	 */
	public function get_product() {
		global $post;

		// The button markup goes out on woocommerce_after_add_to_cart_form, which only fires
		// from inside a single-product loop - the product template's and the [product_page]
		// shortcode's alike. WooCommerce has already set up the global product by then, so
		// take its answer rather than deriving a second one. Narrow to that hook: elsewhere
		// the global can be left over from an archive, cross-sell or [products] loop.
		if ( doing_action( 'woocommerce_after_add_to_cart_form' ) && isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product ) {
			return $GLOBALS['product'];
		}

		if ( is_product() ) {
			return wc_get_product( $post->ID );
		}

		$this->resolve_shortcode_context();

		return $this->shortcode_product;
	}

	/**
	 * Used to get the order in admin edit page.
	 *
	 * @return WC_Order|WC_Order_Refund|bool
	 */
	public function get_current_order() {
		global $theorder;
		global $post;

		if ( is_object( $theorder ) ) {
			return $theorder;
		}

		if ( is_object( $post ) ) {
			return wc_get_order( $post->ID );
		}

		return false;
	}

	/**
	 * Returns true if the provided WC_Product is a subscription, false otherwise.
	 *
	 * @param WC_Product $product The product to check.
	 *
	 * @return bool  True if product is subscription, false otherwise.
	 */
	public function is_product_subscription( WC_Product $product ): bool {
		return 'subscription' === $product->get_type()
			|| 'subscription_variation' === $product->get_type()
			|| 'variable-subscription' === $product->get_type();
	}

	/**
	 * Whether the resolvable product can be added to the cart.
	 *
	 * Returns false when no product can be resolved (e.g. off a product page,
	 * where get_product() returns null), so callers must gate with
	 * `is_product() && ! is_product_purchasable()` to avoid affecting the cart
	 * or checkout contexts. On a product page, a product that is not purchasable
	 * or out of stock can't be added to the cart, so the express buttons should
	 * not show.
	 *
	 * Variable products report the parent's aggregate status: is_purchasable()
	 * and is_in_stock() are true when at least one variation is buyable, so this
	 * only filters out wholly-unavailable products. A specific out-of-stock or
	 * unavailable variation is still handled at click time, where the express
	 * button prompts the shopper to pick a valid combination.
	 *
	 * @return bool
	 */
	public function is_product_purchasable(): bool {
		$product = $this->get_product();

		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		return $product->is_purchasable() && $product->is_in_stock();
	}

	/**
	 * Checks whether Express Checkout Element Button should be available on this page.
	 *
	 * @return bool
	 */
	public function should_show_express_checkout_button() {
		// When express checkout methods are displayed in the payment methods list,
		// don't show them as separate express buttons.
		if ( \WC_Payments::get_gateway()->is_express_checkout_in_payment_methods_enabled() ) {
			return false;
		}

		// If account is not connected, then bail.
		if ( ! $this->account->is_stripe_connected( false ) ) {
			return false;
		}

		// If no SSL, bail.
		if ( ! WC_Payments::mode()->is_test() && ! is_ssl() ) {
			Logger::log( 'Stripe Express Checkout live mode requires SSL.' );

			return false;
		}

		// Page not supported.
		if ( ! $this->is_product() && ! $this->is_cart() && ! $this->is_checkout() ) {
			return false;
		}

		// No express checkout methods are actually enabled for the current page context
		// (checks both location settings and feature flags/availability).
		if ( empty( $this->get_enabled_express_checkout_methods_for_context() ) ) {
			return false;
		}

		// Product page, but has unsupported product type.
		if ( $this->is_product() && ! $this->is_product_supported() ) {
			Logger::log( 'Product page has unsupported product type ( Express Checkout Element button disabled )' );
			return false;
		}

		// Product page, but the product can't be added to the cart (not purchasable or out of stock).
		if ( $this->is_product() && ! $this->is_product_purchasable() ) {
			Logger::log( 'Product is not purchasable ( Express Checkout Element button disabled )' );
			return false;
		}

		// Cart has unsupported product type.
		if ( ( $this->is_checkout() || $this->is_cart() ) && ! $this->has_allowed_items_in_cart() ) {
			Logger::log( 'Items in the cart have unsupported product type ( Express Checkout Element button disabled )' );
			return false;
		}

		// Order total doesn't matter for Pay for Order page. Thus, this page should always display payment buttons.
		if ( $this->is_pay_for_order_page() ) {
			return $this->is_pay_for_order_supported();
		}

		// Non-shipping product and tax is calculated based on shopper billing address. Excludes Pay for Order page.
		if (
			// If the product doesn't needs shipping.
			(
				// on the product page.
				( $this->is_product() && ! $this->product_needs_shipping( $this->get_product() ) ) ||

				// on the cart or checkout page.
				( ( $this->is_cart() || $this->is_checkout() ) && ! WC()->cart->needs_shipping() )
			)

			// ...and tax is calculated based on billing address.
			&& wc_tax_enabled()
			&& 'billing' === get_option( 'woocommerce_tax_based_on' )
			&& 'yes' !== get_option( 'woocommerce_prices_include_tax' )
		) {
			return false;
		}

		// Cart total is 0 or is on product page and product price is 0.
		// Exclude pay-for-order pages from this check.
		if (
			( ! $this->is_product() && ! $this->is_pay_for_order_page() && 0.0 === (float) WC()->cart->get_total( 'edit' ) )
			|| ( $this->is_product() && 0.0 === (float) $this->get_product()->get_price() )
		) {
			Logger::log( 'Order price is 0 ( Express Checkout Element button disabled )' );
			return false;
		}

		return true;
	}

	/**
	 * Check if the passed product needs to be shipped.
	 *
	 * @param WC_Product $product The product to check.
	 *
	 * @return bool Returns true if the product requires shipping; otherwise, returns false.
	 */
	public function product_needs_shipping( WC_Product $product ) {
		if ( ! $product ) {
			return false;
		}

		return wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping();
	}

	/**
	 * Checks to make sure product type is supported.
	 *
	 * @return  array
	 */
	public function supported_product_types() {
		/**
		 * Filters the product types supported by Express Checkout.
		 *
		 * @since 2.1.0
		 *
		 * @param string[] $supported_types The list of supported product types.
		 */
		return apply_filters(
			'wcpay_payment_request_supported_types',
			[
				'simple',
				'variable',
				'variation',
				'subscription',
				'variable-subscription',
				'subscription_variation',
				'booking',
				'bundle',
				'composite',
				'mix-and-match',
			]
		);
	}

	/**
	 * Checks the cart to see if all items are allowed to be used.
	 *
	 * @return boolean
	 */
	public function has_allowed_items_in_cart() {
		/**
		 * Pre Orders compatbility where we don't support charge upon release.
		 */
		if ( class_exists( 'WC_Pre_Orders_Cart' ) && WC_Pre_Orders_Cart::cart_contains_pre_order() && class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( WC_Pre_Orders_Cart::get_pre_order_product() ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WooCommerce core hook, not defined by WooPayments.
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( ! in_array( $_product->get_type(), $this->supported_product_types(), true ) ) {
				return false;
			}

			/**
			 * Filter whether product supports Express Checkout Element Button on cart page.
			 *
			 * @since 6.9.0
			 *
			 * @param boolean $is_supported Whether product supports Express Checkout Element Button on cart page.
			 * @param object  $_product     Product object.
			 */
			if ( ! apply_filters( 'wcpay_payment_request_is_cart_supported', true, $_product ) ) {
				return false;
			}
		}

		// We don't support multiple packages with Express Checkout Element Buttons because we can't offer a good UX.
		$packages = WC()->cart->get_shipping_packages();
		if ( 1 < ( is_countable( $packages ) ? count( $packages ) : 0 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets the product data for the currently viewed page.
	 *
	 * @return mixed Returns false if not on a product page, the product information otherwise.
	 */
	public function get_product_data() {
		if ( ! $this->is_product() ) {
			return false;
		}

		/** @var WC_Product_Variable $product */ // phpcs:ignore
		$product  = $this->get_product();
		$currency = get_woocommerce_currency();

		if ( 'variable' === $product->get_type() || 'variable-subscription' === $product->get_type() ) {
			$variation_attributes = $product->get_variation_attributes();
			$attributes           = [];

			foreach ( $variation_attributes as $attribute_name => $attribute_values ) {
				$attribute_key = 'attribute_' . sanitize_title( $attribute_name );

				// Passed value via GET takes precedence. Otherwise get the default value for given attribute.
				$attributes[ $attribute_key ] = isset( $_GET[ $attribute_key ] ) // phpcs:ignore WordPress.Security.NonceVerification
					? wc_clean( wp_unslash( $_GET[ $attribute_key ] ) ) // phpcs:ignore WordPress.Security.NonceVerification
					: $product->get_variation_default_attribute( $attribute_name );
			}

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			if ( ! empty( $variation_id ) ) {
				$product = wc_get_product( $variation_id );
			}
		}

		try {
			$price = $this->get_product_price( $product );
		} catch ( Invalid_Price_Exception $e ) {
			Logger::log( $e->getMessage() );
			return false;
		}

		$data  = [];
		$items = [];

		$items[] = [
			'label'  => $product->get_name(),
			'amount' => WC_Payments_Utils::prepare_amount( $price, $currency ),
		];

		$total_tax = 0;
		foreach ( $this->get_taxes_like_cart( $product, $price ) as $tax ) {
			$total_tax += $tax;

			$items[] = [
				'label'   => __( 'Tax', 'woocommerce-payments' ),
				'amount'  => WC_Payments_Utils::prepare_amount( $tax, $currency ),
				'pending' => 0 === $tax,
			];
		}

		if ( wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping() ) {
			$items[] = [
				'label'   => __( 'Shipping', 'woocommerce-payments' ),
				'amount'  => 0,
				'pending' => true,
			];

			$data['shippingOptions'] = [
				'id'     => 'pending',
				'label'  => __( 'Pending', 'woocommerce-payments' ),
				'detail' => '',
				'amount' => 0,
			];
		}

		$data['displayItems'] = $items;
		$data['total']        = [
			/**
			 * Filters the label shown for the order total in the Express Checkout request.
			 *
			 * @since 2.1.0
			 *
			 * @param string $total_label The order total label.
			 */
			'label'   => apply_filters( 'wcpay_payment_request_total_label', $this->get_total_label() ),
			'amount'  => WC_Payments_Utils::prepare_amount( $price + $total_tax, $currency ),
			'pending' => true,
		];

		$data['needs_shipping'] = ( wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping() );
		$data['currency']       = strtolower( $currency );
		$data['country_code']   = substr( get_option( 'woocommerce_default_country' ), 0, 2 );
		$data['product_type']   = $product->get_type();

		/**
		 * Filters the product data sent to the Express Checkout request.
		 *
		 * @since 2.1.0
		 *
		 * @param array      $data    The product data for the Express Checkout request.
		 * @param WC_Product $product The product object.
		 */
		return apply_filters( 'wcpay_payment_request_product_data', $data, $product );
	}

	/**
	 * Works out, once per request, whether the current page embeds a [product_page]
	 * shortcode and which product it names.
	 *
	 * Reads the host post off the main query rather than the $post / $wp_query globals:
	 * the shortcode renders in its own loop, so by the time the button markup goes out
	 * those globals point at the embedded product instead of at the page.
	 *
	 * @return void
	 */
	private function resolve_shortcode_context() {
		if ( $this->shortcode_context_resolved ) {
			return;
		}

		// A caller can reach this before WordPress has parsed the request - is_product() is
		// public, so anything on init can - and the answer would be a meaningless "no".
		// Leave the context unresolved so the next caller works it out for real.
		$main_query = $GLOBALS['wp_the_query'] ?? null;
		if ( ! $main_query instanceof WP_Query || ! $main_query->is_singular() ) {
			return;
		}

		$host = $main_query->get_queried_object();
		if ( ! $host instanceof WP_Post ) {
			return;
		}

		$this->shortcode_context_resolved = true;

		// WordPress's own shortcode regex, narrowed to this one tag: it hands back the raw
		// attributes in the same pass and marks escaped [[product_page]] tags, which
		// do_shortcode() skips too.
		if ( ! preg_match_all( '/' . get_shortcode_regex( [ 'product_page' ] ) . '/', $host->post_content, $matches, PREG_SET_ORDER ) ) {
			return;
		}

		$atts = null;
		foreach ( $matches as $match ) {
			if ( '[' === $match[1] && ']' === $match[6] ) {
				continue;
			}

			$atts = shortcode_parse_atts( $match[3] );
			break;
		}

		if ( null === $atts ) {
			return;
		}

		// Presence is recorded whatever the attributes point at: is_product() has always
		// answered "does this page embed the shortcode", and a shortcode naming a product
		// that no longer exists must not turn that into a "no".
		$this->has_product_page_shortcode = true;

		$product_id = 0;
		if ( ! empty( $atts['id'] ) ) {
			$product_id = absint( $atts['id'] );
		} elseif ( ! empty( $atts['sku'] ) ) {
			$product_id = wc_get_product_id_by_sku( $atts['sku'] );
		}

		$product = $product_id ? wc_get_product( $product_id ) : null;

		$this->shortcode_product = $product instanceof WC_Product ? $product : null;
	}

	/**
	 * Determines whether the current Pay for Order page can be paid via the Express Checkout button.
	 *
	 * An order can be created without a billing email (e.g. by the merchant). The Store API requires
	 * one to process the payment, but the email is captured from the wallet (Apple Pay / Google Pay)
	 * and forwarded to the checkout request, so a pre-existing order email is not required to offer
	 * the button. See https://github.com/woocommerce/woocommerce/issues/48540
	 *
	 * @return bool
	 */
	private function is_pay_for_order_supported() {
		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( 0 === $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether product page has a supported product.
	 *
	 * @return boolean
	 */
	private function is_product_supported() {
		$product      = $this->get_product();
		$is_supported = true;

		if ( is_null( $product ) || ! is_object( $product ) ) {
			$is_supported = false;
		} elseif (
			! in_array( $product->get_type(), $this->supported_product_types(), true )
			|| ( class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) // Pre Orders charge upon release not supported.
			|| ( class_exists( 'WC_Composite_Products' ) && $product->is_type( 'composite' ) ) // Composite products are not supported on the product page.
			|| ( class_exists( 'WC_Mix_and_Match' ) && $product->is_type( 'mix-and-match' ) ) // Mix and match products are not supported on the product page.
			// Subscriptions with a free trial and no sign-up fee are not supported
			// because ECE and ConfirmationToken do not deal well with Setup Intent.
			// When a sign-up fee exists, the initial charge is non-zero, so ECE can display it correctly.
			|| ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) && WC_Subscriptions_Product::get_trial_length( $product ) > 0 && 0.0 >= (float) WC_Subscriptions_Product::get_sign_up_fee( $product ) )
		) {
			$is_supported = false;
		} elseif ( class_exists( 'WC_Product_Addons_Helper' ) ) {
			// File upload addon not supported.
			$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );
			foreach ( $product_addons as $addon ) {
				if ( 'file_upload' === $addon['type'] ) {
					$is_supported = false;
					break;
				}
			}
		}

		/**
		 * Filters whether the current product is supported for Express Checkout.
		 *
		 * @since 3.4.0
		 *
		 * @param bool            $is_supported Whether the product is supported.
		 * @param WC_Product|null $product      The product object, or null.
		 */
		return apply_filters( 'wcpay_payment_request_is_product_supported', $is_supported, $product );
	}

	/**
	 * Reads the raw location settings for a page location.
	 *
	 * @param string $location Location (product, cart, checkout, pay_for_order).
	 * @return string[] Method ids enabled at that location.
	 */
	private function get_methods_enabled_at( $location ) {
		// The "pay for order" page is a checkout page, but we want to use the "checkout" location for settings.
		if ( 'pay_for_order' === $location ) {
			$location = 'checkout';
		}

		$enabled_methods = $this->gateway->get_option( "express_checkout_{$location}_methods" );

		return is_array( $enabled_methods ) ? $enabled_methods : [];
	}

	/**
	 * Gets the product total price.
	 *
	 * @param object $product WC_Product_* object.
	 * @param bool   $is_deposit Whether customer is paying a deposit.
	 * @param int    $deposit_plan_id The ID of the deposit plan.
	 * @return mixed Total price.
	 *
	 * @throws Invalid_Price_Exception Whenever a product has no price.
	 */
	public function get_product_price( $product, ?bool $is_deposit = null, int $deposit_plan_id = 0 ) {
		// If prices should include tax, using tax inclusive price.
		if ( $this->cart_prices_include_tax() ) {
			$base_price = wc_get_price_including_tax( $product );
		} else {
			$base_price = wc_get_price_excluding_tax( $product );
		}

		// If WooCommerce Deposits is active, we need to get the correct price for the product.
		if ( class_exists( 'WC_Deposits_Product_Manager' ) && class_exists( 'WC_Deposits_Plans_Manager' ) && WC_Deposits_Product_Manager::deposits_enabled( $product->get_id() ) ) {
			// If is_deposit is null, we use the default deposit type for the product.
			if ( is_null( $is_deposit ) ) {
				$is_deposit = 'deposit' === WC_Deposits_Product_Manager::get_deposit_selected_type( $product->get_id() );
			}
			if ( $is_deposit ) {
				$deposit_type       = WC_Deposits_Product_Manager::get_deposit_type( $product->get_id() );
				$available_plan_ids = WC_Deposits_Plans_Manager::get_plan_ids_for_product( $product->get_id() );
				// Default to first (default) plan if no plan is specified.
				if ( 'plan' === $deposit_type && 0 === $deposit_plan_id && ! empty( $available_plan_ids ) ) {
					$deposit_plan_id = $available_plan_ids[0];
				}

				// Ensure the selected plan is available for the product.
				if ( 0 === $deposit_plan_id || in_array( $deposit_plan_id, $available_plan_ids, true ) ) {
					$base_price = WC_Deposits_Product_Manager::get_deposit_amount( $product, $deposit_plan_id, 'display', $base_price );
				}
			}
		}

		// Add subscription sign-up fees to product price.
		$sign_up_fee        = 0;
		$subscription_types = [
			'subscription',
			'subscription_variation',
		];
		if ( in_array( $product->get_type(), $subscription_types, true ) && class_exists( 'WC_Subscriptions_Product' ) ) {
			// When there is no sign-up fee, `get_sign_up_fee` falls back to an int 0.
			$sign_up_fee = WC_Subscriptions_Product::get_sign_up_fee( $product );
		}

		if ( ! is_numeric( $base_price ) || ! is_numeric( $sign_up_fee ) ) {
			$error_message = sprintf(
				// Translators: %d is the numeric ID of the product without a price.
				__( 'Express checkout does not support products without prices! Please add a price to product #%d', 'woocommerce-payments' ),
				(int) $product->get_id()
			);
			throw new Invalid_Price_Exception(
				esc_html( $error_message )
			);
		}

		return $base_price + $sign_up_fee;
	}

	/**
	 * Calculates taxes as displayed on cart, based on a product and a particular price.
	 *
	 * @param WC_Product $product The product, for retrieval of tax classes.
	 * @param float      $price   The price, which to calculate taxes for.
	 * @return array              An array of final taxes.
	 */
	public function get_taxes_like_cart( $product, $price ) {
		if ( ! wc_tax_enabled() || $this->cart_prices_include_tax() ) {
			// Only proceed when taxes are enabled, but not included.
			return [];
		}

		// Follows the way `WC_Cart_Totals::get_item_tax_rates()` works.
		$tax_class = $product->get_tax_class();
		$rates     = WC_Tax::get_rates( $tax_class );
		// No cart item, `woocommerce_cart_totals_get_item_tax_rates` can't be applied here.

		// Normally there should be a single tax, but `calc_tax` returns an array, let's use it.
		return WC_Tax::calc_tax( $price, $rates, false );
	}


	/**
	 * Sanitize string for comparison.
	 *
	 * @param string $value String to be sanitized.
	 *
	 * @return string The sanitized string.
	 */
	public function sanitize_string( $value ) {
		return trim( wc_strtolower( remove_accents( $value ) ) );
	}
}

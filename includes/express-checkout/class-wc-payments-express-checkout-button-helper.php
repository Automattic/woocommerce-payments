<?php
/**
 * Class WC_Payments_Express_Checkout_Button_Helper
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Constants\Country_Code;
use WCPay\Exceptions\Invalid_Price_Exception;
use WCPay\Logger;

/**
 * Express Checkout Button Helper class.
 */
class WC_Payments_Express_Checkout_Button_Helper {
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

		// Default show only subtotal instead of itemization.
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
		return str_replace( "'", '', $statement_descriptor ) . apply_filters( 'wcpay_payment_request_total_label_suffix', ' (via WooCommerce)' );
	}

	/**
	 * Gets quantity from request.
	 *
	 * @return int
	 */
	public function get_quantity() {
		// Express Checkout Element sends the quantity as qty. WooPay sends it as quantity.
		if ( isset( $_POST['quantity'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return absint( $_POST['quantity'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_POST['qty'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return absint( $_POST['qty'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		return is_product() || wc_post_content_has_shortcode( 'product_page' );
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
	 * Checks if button is available at a given location.
	 *
	 * @param string $location Location.
	 * @param string $option_name Option name.
	 * @return boolean
	 */
	public function is_available_at( $location, $option_name ) {
		$available_locations = $this->gateway->get_option( $option_name );
		if ( $available_locations && is_array( $available_locations ) ) {
			return in_array( $location, $available_locations, true );
		}

		return false;
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

		if ( is_product() ) {
			return wc_get_product( $post->ID );
		}

		if ( wc_post_content_has_shortcode( 'product_page' ) ) {
			// Get id from product_page shortcode.
			preg_match( '/\[product_page id="(?<id>\d+)"\]/', $post->post_content, $shortcode_match );
			if ( isset( $shortcode_match['id'] ) ) {
				return wc_get_product( $shortcode_match['id'] );
			}
		}

		return null;
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
	 * Checks whether Express Checkout Element Button should be available on this page.
	 *
	 * @return bool
	 */
	public function should_show_express_checkout_button() {
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

		// Product page, but not available in settings.
		if ( $this->is_product() && ! $this->is_available_at( 'product', WC_Payments_Express_Checkout_Button_Handler::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Checkout page, but not available in settings.
		if ( $this->is_checkout() && ! $this->is_available_at( 'checkout', WC_Payments_Express_Checkout_Button_Handler::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Cart page, but not available in settings.
		if ( $this->is_cart() && ! $this->is_available_at( 'cart', WC_Payments_Express_Checkout_Button_Handler::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Product page, but has unsupported product type.
		if ( $this->is_product() && ! $this->is_product_supported() ) {
			Logger::log( 'Product page has unsupported product type ( Express Checkout Element button disabled )' );
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
			( ! $this->is_product() && ! $this->is_pay_for_order_page() && 0.0 === (float) WC()->cart->get_total( 'edit' ) ) ||
			( $this->is_product() && 0.0 === (float) $this->get_product()->get_price() )

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
	 *
	 * @psalm-suppress UndefinedClass
	 */
	public function has_allowed_items_in_cart() {
		/**
		 * Pre Orders compatbility where we don't support charge upon release.
		 *
		 * @psalm-suppress UndefinedClass
		 */
		if ( class_exists( 'WC_Pre_Orders_Cart' ) && WC_Pre_Orders_Cart::cart_contains_pre_order() && class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( WC_Pre_Orders_Cart::get_pre_order_product() ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
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

			/**
			 * Trial subscriptions with shipping are not supported.
			 *
			 * @psalm-suppress UndefinedClass
			 */
			if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $_product ) && $_product->needs_shipping() && WC_Subscriptions_Product::get_trial_length( $_product ) > 0 ) {
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
			'label'   => apply_filters( 'wcpay_payment_request_total_label', $this->get_total_label() ),
			'amount'  => WC_Payments_Utils::prepare_amount( $price + $total_tax, $currency ),
			'pending' => true,
		];

		$data['needs_shipping'] = ( wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping() );
		$data['currency']       = strtolower( $currency );
		$data['country_code']   = substr( get_option( 'woocommerce_default_country' ), 0, 2 );
		$data['product_type']   = $product->get_type();

		return apply_filters( 'wcpay_payment_request_product_data', $data, $product );
	}

	/**
	 * The Store API doesn't allow checkout without the billing email address present on the order data.
	 * https://github.com/woocommerce/woocommerce/issues/48540
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

		// we don't need to check its validity or value, we just need to ensure a billing email is present.
		$billing_email = $order->get_billing_email();
		if ( ! empty( $billing_email ) ) {
			return true;
		}

		Logger::log( 'Billing email not present ( Express Checkout Element button disabled )' );

		return false;
	}

	/**
	 * Whether product page has a supported product.
	 *
	 * @return boolean
	 */
	private function is_product_supported() {
		$product      = $this->get_product();
		$is_supported = true;

		/**
		 * Ignore undefined classes from 3rd party plugins.
		 *
		 * @psalm-suppress UndefinedClass
		 */

		if ( is_null( $product ) || ! is_object( $product ) ) {
			$is_supported = false;
		} else {
			// Simple subscription that needs shipping with free trials is not supported.
			$is_free_trial_simple_subs = class_exists( 'WC_Subscriptions_Product' ) && $product->get_type() === 'subscription' && $product->needs_shipping() && WC_Subscriptions_Product::get_trial_length( $product ) > 0;

			if (
			! in_array( $product->get_type(), $this->supported_product_types(), true )
			|| $is_free_trial_simple_subs
			|| ( class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) // Pre Orders charge upon release not supported.
			|| ( class_exists( 'WC_Composite_Products' ) && $product->is_type( 'composite' ) ) // Composite products are not supported on the product page.
			|| ( class_exists( 'WC_Mix_and_Match' ) && $product->is_type( 'mix-and-match' ) ) // Mix and match products are not supported on the product page.
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
		}

		return apply_filters( 'wcpay_payment_request_is_product_supported', $is_supported, $product );
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
	 *
	 * @psalm-suppress UndefinedClass
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
	 * @param string $string String to be sanitized.
	 *
	 * @return string The sanitized string.
	 */
	public function sanitize_string( $string ) {
		return trim( wc_strtolower( remove_accents( $string ) ) );
	}

	/**
	 * Add express checkout payment method title to the order.
	 *
	 * @param integer $order_id The order ID.
	 *
	 * @return  void
	 */
	public function add_order_payment_method_title( $order_id ) {
		if ( empty( $_POST['express_payment_type'] ) || ! isset( $_POST['payment_method'] ) || 'woocommerce_payments' !== $_POST['payment_method'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$express_payment_type   = wc_clean( wp_unslash( $_POST['express_payment_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$express_payment_titles = [
			'apple_pay'  => 'Apple Pay',
			'google_pay' => 'Google Pay',
		];
		$payment_method_title   = $express_payment_titles[ $express_payment_type ] ?? false;

		if ( ! $payment_method_title ) {
			return;
		}

		$suffix = apply_filters( 'wcpay_payment_request_payment_method_title_suffix', 'WooPayments' );
		if ( ! empty( $suffix ) ) {
			$suffix = " ($suffix)";
		}

		$order = wc_get_order( $order_id );
		$order->set_payment_method_title( $payment_method_title . $suffix );
		$order->save();
	}
}

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
	 * Builds the line items to pass to Payment Request
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
		// Payment Request Button sends the quantity as qty. WooPay sends it as quantity.
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
	 * Gets settings that are shared between the Payment Request button and the WooPay button.
	 *
	 * @return array
	 */
	public function get_common_button_settings() {
		$button_type = $this->gateway->get_option( 'payment_request_button_type' );
		$settings    = [
			'type'   => $button_type,
			'theme'  => $this->gateway->get_option( 'payment_request_button_theme' ),
			'height' => $this->get_button_height(),
		];

		if ( WC_Payments_Features::is_stripe_ece_enabled() ) {
			$settings['radius'] = $this->gateway->get_option( 'payment_request_button_border_radius' );
		}

		return $settings;
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
	 * Checks whether Payment Request Button should be available on this page.
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
			Logger::log( 'Stripe Payment Request live mode requires SSL.' );

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
			Logger::log( 'Product page has unsupported product type ( Payment Request button disabled )' );
			return false;
		}

		// Cart has unsupported product type.
		if ( ( $this->is_checkout() || $this->is_cart() ) && ! $this->has_allowed_items_in_cart() ) {
			Logger::log( 'Items in the cart have unsupported product type ( Payment Request button disabled )' );
			return false;
		}

		// Order total doesn't matter for Pay for Order page. Thus, this page should always display payment buttons.
		if ( $this->is_pay_for_order_page() ) {
			return true;
		}

		// Non-shipping product and billing is calculated based on shopper billing addres. Excludes Pay for Order page.
		if (
			// If the product doesn't needs shipping.
			(
				// on the product page.
				( $this->is_product() && ! $this->product_needs_shipping( $this->get_product() ) ) ||

				// on the cart or checkout page.
				( ( $this->is_cart() || $this->is_checkout() ) && ! WC()->cart->needs_shipping() )
			)

			// ...and billing is calculated based on billing address.
			&& 'billing' === get_option( 'woocommerce_tax_based_on' )
		) {
			return false;
		}

		// Cart total is 0 or is on product page and product price is 0.
		// Exclude pay-for-order pages from this check.
		if (
			( ! $this->is_product() && ! $this->is_pay_for_order_page() && 0.0 === (float) WC()->cart->get_total( 'edit' ) ) ||
			( $this->is_product() && 0.0 === (float) $this->get_product()->get_price() )

		) {
			Logger::log( 'Order price is 0 ( Payment Request button disabled )' );
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
			 * Filter whether product supports Payment Request Button on cart page.
			 *
			 * @since 6.9.0
			 *
			 * @param boolean $is_supported Whether product supports Payment Request Button on cart page.
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

		// We don't support multiple packages with Payment Request Buttons because we can't offer a good UX.
		$packages = WC()->cart->get_shipping_packages();
		if ( 1 < ( is_countable( $packages ) ? count( $packages ) : 0 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets shipping options available for specified shipping address
	 *
	 * @param array   $shipping_address Shipping address.
	 * @param boolean $itemized_display_items Indicates whether to show subtotals or itemized views.
	 *
	 * @return array Shipping options data.
	 *
	 * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag
	 */
	public function get_shipping_options( $shipping_address, $itemized_display_items = false ) {
		try {
			// Set the shipping options.
			$data = [];

			// Remember current shipping method before resetting.
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', [] );
			$this->calculate_shipping( apply_filters( 'wcpay_payment_request_shipping_posted_values', $shipping_address ) );

			$packages = WC()->shipping->get_packages();

			if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
				foreach ( $packages as $package ) {
					if ( empty( $package['rates'] ) ) {
						throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-payments' ) );
					}

					foreach ( $package['rates'] as $rate ) {
						$data['shipping_options'][] = [
							'id'          => $rate->id,
							'displayName' => $rate->label,
							'amount'      => WC_Payments_Utils::prepare_amount( $rate->cost, get_woocommerce_currency() ),
						];
					}
				}
			} else {
				throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-payments' ) );
			}

			// The first shipping option is automatically applied on the client.
			// Keep chosen shipping method by sorting shipping options if the method still available for new address.
			// Fallback to the first available shipping method.
			if ( isset( $data['shipping_options'][0] ) ) {
				if ( isset( $chosen_shipping_methods[0] ) ) {
					$chosen_method_id         = $chosen_shipping_methods[0];
					$compare_shipping_options = function ( $a, $b ) use ( $chosen_method_id ) {
						if ( $a['id'] === $chosen_method_id ) {
							return -1;
						}

						if ( $b['id'] === $chosen_method_id ) {
							return 1;
						}

						return 0;
					};
					usort( $data['shipping_options'], $compare_shipping_options );
				}

				$first_shipping_method_id = $data['shipping_options'][0]['id'];
				$this->update_shipping_method( [ $first_shipping_method_id ] );
			}

			WC()->cart->calculate_totals();

			$this->maybe_restore_recurring_chosen_shipping_methods( $chosen_shipping_methods );

			$data          += $this->build_display_items( $itemized_display_items );
			$data['result'] = 'success';
		} catch ( Exception $e ) {
			$data          += $this->build_display_items( $itemized_display_items );
			$data['result'] = 'invalid_shipping_address';
		}

		return $data;
	}

	/**
	 * Restores the shipping methods previously chosen for each recurring cart after shipping was reset and recalculated
	 * during the Payment Request get_shipping_options flow.
	 *
	 * When the cart contains multiple subscriptions with different billing periods, customers are able to select different shipping
	 * methods for each subscription, however, this is not supported when purchasing with Apple Pay and Google Pay as it's
	 * only concerned about handling the initial purchase.
	 *
	 * In order to avoid Woo Subscriptions's `WC_Subscriptions_Cart::validate_recurring_shipping_methods` throwing an error, we need to restore
	 * the previously chosen shipping methods for each recurring cart.
	 *
	 * This function needs to be called after `WC()->cart->calculate_totals()` is run, otherwise `WC()->cart->recurring_carts` won't exist yet.
	 *
	 * @param array $previous_chosen_methods The previously chosen shipping methods.
	 */
	private function maybe_restore_recurring_chosen_shipping_methods( $previous_chosen_methods = [] ) {
		if ( empty( WC()->cart->recurring_carts ) || ! method_exists( 'WC_Subscriptions_Cart', 'get_recurring_shipping_package_key' ) ) {
			return;
		}

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', [] );

		foreach ( WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart ) {
			foreach ( $recurring_cart->get_shipping_packages() as $recurring_cart_package_index => $recurring_cart_package ) {
				// phpcs:ignore
				/**
				 * @psalm-suppress UndefinedClass
				 */
				$package_key = WC_Subscriptions_Cart::get_recurring_shipping_package_key( $recurring_cart_key, $recurring_cart_package_index );

				// If the recurring cart package key is found in the previous chosen methods, but not in the current chosen methods, restore it.
				if ( isset( $previous_chosen_methods[ $package_key ] ) && ! isset( $chosen_shipping_methods[ $package_key ] ) ) {
					$chosen_shipping_methods[ $package_key ] = $previous_chosen_methods[ $package_key ];
				}
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
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

		return apply_filters( 'wcpay_payment_request_product_data', $data, $product );
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
		if ( is_null( $product )
			|| ! is_object( $product )
			|| ! in_array( $product->get_type(), $this->supported_product_types(), true )
			|| ( class_exists( 'WC_Subscriptions_Product' ) && $product->needs_shipping() && WC_Subscriptions_Product::get_trial_length( $product ) > 0 ) // Trial subscriptions with shipping are not supported.
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
	 * Gets the normalized state/county field because in some
	 * cases, the state/county field is formatted differently from
	 * what WC is expecting and throws an error. An example
	 * for Ireland, the county dropdown in Chrome shows "Co. Clare" format.
	 *
	 * @param string $state Full state name or an already normalized abbreviation.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state abbreviation.
	 */
	public function get_normalized_state( $state, $country ) {
		// If it's empty or already normalized, skip.
		if ( ! $state || $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// Try to match state from the Payment Request API list of states.
		$state = $this->get_normalized_state_from_pr_states( $state, $country );

		// If it's normalized, return.
		if ( $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// If the above doesn't work, fallback to matching against the list of translated
		// states from WooCommerce.
		return $this->get_normalized_state_from_wc_states( $state, $country );
	}

	/**
	 * The Payment Request API provides its own validation for the address form.
	 * For some countries, it might not provide a state field, so we need to return a more descriptive
	 * error message, indicating that the Payment Request button is not supported for that country.
	 */
	public static function validate_state() {
		$wc_checkout     = WC_Checkout::instance();
		$posted_data     = $wc_checkout->get_posted_data();
		$checkout_fields = $wc_checkout->get_checkout_fields();
		$countries       = WC()->countries->get_countries();

		$is_supported = true;
		// Checks if billing state is missing and is required.
		if ( ! empty( $checkout_fields['billing']['billing_state']['required'] ) && '' === $posted_data['billing_state'] ) {
			$is_supported = false;
		}

		// Checks if shipping state is missing and is required.
		if ( WC()->cart->needs_shipping_address() && ! empty( $checkout_fields['shipping']['shipping_state']['required'] ) && '' === $posted_data['shipping_state'] ) {
			$is_supported = false;
		}

		if ( ! $is_supported ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: country. */
					__( 'The payment request button is not supported in %s because some required fields couldn\'t be verified. Please proceed to the checkout page and try again.', 'woocommerce-payments' ),
					$countries[ $posted_data['billing_country'] ] ?? $posted_data['billing_country']
				),
				'error'
			);
		}
	}

	/**
	 * Normalizes billing and shipping state fields.
	 */
	public function normalize_state() {
		check_ajax_referer( 'woocommerce-process_checkout', '_wpnonce' );

		$billing_country  = ! empty( $_POST['billing_country'] ) ? wc_clean( wp_unslash( $_POST['billing_country'] ) ) : '';
		$shipping_country = ! empty( $_POST['shipping_country'] ) ? wc_clean( wp_unslash( $_POST['shipping_country'] ) ) : '';
		$billing_state    = ! empty( $_POST['billing_state'] ) ? wc_clean( wp_unslash( $_POST['billing_state'] ) ) : '';
		$shipping_state   = ! empty( $_POST['shipping_state'] ) ? wc_clean( wp_unslash( $_POST['shipping_state'] ) ) : '';

		if ( $billing_state && $billing_country ) {
			$_POST['billing_state'] = $this->get_normalized_state( $billing_state, $billing_country );
		}

		if ( $shipping_state && $shipping_country ) {
			$_POST['shipping_state'] = $this->get_normalized_state( $shipping_state, $shipping_country );
		}
	}

	/**
	 * Checks if given state is normalized.
	 *
	 * @param string $state State.
	 * @param string $country Two-letter country code.
	 *
	 * @return bool Whether state is normalized or not.
	 */
	public function is_normalized_state( $state, $country ) {
		$wc_states = WC()->countries->get_states( $country );
		return is_array( $wc_states ) && array_key_exists( $state, $wc_states );
	}

	/**
	 * Get normalized state from Payment Request API dropdown list of states.
	 *
	 * @param string $state Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	public function get_normalized_state_from_pr_states( $state, $country ) {
		// Include Payment Request API State list for compatibility with WC countries/states.
		include_once WCPAY_ABSPATH . 'includes/constants/class-payment-request-button-states.php';
		$pr_states = \WCPay\Constants\Payment_Request_Button_States::STATES;

		if ( ! isset( $pr_states[ $country ] ) ) {
			return $state;
		}

		foreach ( $pr_states[ $country ] as $wc_state_abbr => $pr_state ) {
			$sanitized_state_string = $this->sanitize_string( $state );
			// Checks if input state matches with Payment Request state code (0), name (1) or localName (2).
			if (
				( ! empty( $pr_state[0] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[0] ) ) ||
				( ! empty( $pr_state[1] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[1] ) ) ||
				( ! empty( $pr_state[2] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[2] ) )
			) {
				return $wc_state_abbr;
			}
		}

		return $state;
	}

	/**
	 * Get normalized state from WooCommerce list of translated states.
	 *
	 * @param string $state Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	public function get_normalized_state_from_wc_states( $state, $country ) {
		$wc_states = WC()->countries->get_states( $country );

		if ( is_array( $wc_states ) ) {
			foreach ( $wc_states as $wc_state_abbr => $wc_state_value ) {
				if ( preg_match( '/' . preg_quote( $wc_state_value, '/' ) . '/i', $state ) ) {
					return $wc_state_abbr;
				}
			}
		}

		return $state;
	}

	/**
	 * Normalizes postal code in case of redacted data from Apple Pay.
	 *
	 * @param string $postcode Postal code.
	 * @param string $country Country.
	 */
	public function get_normalized_postal_code( $postcode, $country ) {
		/**
		 * Currently, Apple Pay truncates the UK and Canadian postal codes to the first 4 and 3 characters respectively
		 * when passing it back from the shippingcontactselected object. This causes WC to invalidate
		 * the postal code and not calculate shipping zones correctly.
		 */
		if ( Country_Code::UNITED_KINGDOM === $country ) {
			// Replaces a redacted string with something like N1C0000.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 7, '0' );
		}
		if ( Country_Code::CANADA === $country ) {
			// Replaces a redacted string with something like H3B000.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 6, '0' );
		}

		return $postcode;
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
	 * Updates shipping method in WC session
	 *
	 * @param array $shipping_methods Array of selected shipping methods ids.
	 */
	public function update_shipping_method( $shipping_methods ) {
		$chosen_shipping_methods = (array) WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shipping_methods ) ) {
			foreach ( $shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
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

	/**
	 * Calculate and set shipping method.
	 *
	 * @param array $address Shipping address.
	 */
	protected function calculate_shipping( $address = [] ) {
		$country   = $address['country'];
		$state     = $address['state'];
		$postcode  = $address['postcode'];
		$city      = $address['city'];
		$address_1 = $address['address_1'];
		$address_2 = $address['address_2'];

		// Normalizes state to calculate shipping zones.
		$state = $this->get_normalized_state( $state, $country );

		// Normalizes postal code in case of redacted data from Apple Pay.
		$postcode = $this->get_normalized_postal_code( $postcode, $country );

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		$packages = [];

		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;
		$packages[0]['destination']['address']   = $address_1;
		$packages[0]['destination']['address_2'] = $address_2;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $item['data']->needs_shipping() ) {
				if ( isset( $item['line_total'] ) ) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		WC()->shipping->calculate_shipping( $packages );
	}
}

<?php
/**
 * Class WC_Payments_Express_Checkout_Button_Handler
 * Adds support for Apple Pay, Google Pay and ECE API buttons.
 * Utilizes the Stripe Express Checkout Element to support checkout from the product detail and cart pages.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCPay\Exceptions\Invalid_Price_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Logger;

/**
 * WC_Payments_Express_Checkout_Button_Handler class.
 */
class WC_Payments_Express_Checkout_Button_Handler {
	const BUTTON_LOCATIONS = 'payment_request_button_locations';

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payments_Account                        $account Account information.
	 * @param WC_Payment_Gateway_WCPay                   $gateway WCPay gateway.
	 * @param WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper Express checkout helper.
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $gateway, WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper ) {
		$this->account                 = $account;
		$this->gateway                 = $gateway;
		$this->express_checkout_helper = $express_checkout_helper;
	}

	/**
	 * Initialize hooks.
	 *
	 * @return  void
	 */
	public function init() {
		// Checks if WCPay is enabled.
		if ( ! $this->gateway->is_enabled() ) {
			return;
		}

		if ( ! WC_Payments_Features::is_stripe_ece_enabled() ) {
			return;
		}

		// Checks if Payment Request is enabled.
		if ( 'yes' !== $this->gateway->get_option( 'payment_request' ) ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );
	}

	/**
	 * The settings for the `button` attribute - they depend on the "grouped settings" flag value.
	 *
	 * @return array
	 */
	public function get_button_settings() {
		$button_type                     = $this->gateway->get_option( 'payment_request_button_type' );
		$common_settings                 = $this->express_checkout_helper->get_common_button_settings();
		$payment_request_button_settings = [
			// Default format is en_US.
			'locale'       => apply_filters( 'wcpay_payment_request_button_locale', substr( get_locale(), 0, 2 ) ),
			'branded_type' => 'default' === $button_type ? 'short' : 'long',
		];

		return array_merge( $common_settings, $payment_request_button_settings );
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
		if ( ! $this->express_checkout_helper->is_product() && ! $this->express_checkout_helper->is_cart() && ! $this->express_checkout_helper->is_checkout() ) {
			return false;
		}

		// Product page, but not available in settings.
		if ( $this->express_checkout_helper->is_product() && ! $this->express_checkout_helper->is_available_at( 'product', self::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Checkout page, but not available in settings.
		if ( $this->express_checkout_helper->is_checkout() && ! $this->express_checkout_helper->is_available_at( 'checkout', self::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Cart page, but not available in settings.
		if ( $this->express_checkout_helper->is_cart() && ! $this->express_checkout_helper->is_available_at( 'cart', self::BUTTON_LOCATIONS ) ) {
			return false;
		}

		// Product page, but has unsupported product type.
		if ( $this->express_checkout_helper->is_product() && ! $this->is_product_supported() ) {
			Logger::log( 'Product page has unsupported product type ( Payment Request button disabled )' );
			return false;
		}

		// Cart has unsupported product type.
		if ( ( $this->express_checkout_helper->is_checkout() || $this->express_checkout_helper->is_cart() ) && ! $this->has_allowed_items_in_cart() ) {
			Logger::log( 'Items in the cart have unsupported product type ( Payment Request button disabled )' );
			return false;
		}

		// Order total doesn't matter for Pay for Order page. Thus, this page should always display payment buttons.
		if ( $this->express_checkout_helper->is_pay_for_order_page() ) {
			return true;
		}

		// Cart total is 0 or is on product page and product price is 0.
		// Exclude pay-for-order pages from this check.
		if (
			( ! $this->express_checkout_helper->is_product() && ! $this->express_checkout_helper->is_pay_for_order_page() && 0.0 === (float) WC()->cart->get_total( 'edit' ) ) ||
			( $this->express_checkout_helper->is_product() && 0.0 === (float) $this->express_checkout_helper->get_product()->get_price() )

		) {
			Logger::log( 'Order price is 0 ( Payment Request button disabled )' );
			return false;
		}

		return true;
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
	 * Load public scripts and styles.
	 */
	public function scripts() {
		// Don't load scripts if page is not supported.
		if ( ! $this->should_show_express_checkout_button() ) {
			return;
		}

		$payment_request_params = [
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'wc_ajax_url'        => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe'             => [
				'publishableKey' => $this->account->get_publishable_key( WC_Payments::mode()->is_test() ),
				'accountId'      => $this->account->get_stripe_account_id(),
				'locale'         => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
			],
			'nonce'              => [
				'get_cart_details'          => wp_create_nonce( 'wcpay-get-cart-details' ),
				'shipping'                  => wp_create_nonce( 'wcpay-payment-request-shipping' ),
				'update_shipping'           => wp_create_nonce( 'wcpay-update-shipping-method' ),
				'checkout'                  => wp_create_nonce( 'woocommerce-process_checkout' ),
				'add_to_cart'               => wp_create_nonce( 'wcpay-add-to-cart' ),
				'empty_cart'                => wp_create_nonce( 'wcpay-empty-cart' ),
				'get_selected_product_data' => wp_create_nonce( 'wcpay-get-selected-product-data' ),
				'platform_tracker'          => wp_create_nonce( 'platform_tracks_nonce' ),
				'pay_for_order'             => wp_create_nonce( 'pay_for_order' ),
			],
			'checkout'           => [
				'currency_code'     => strtolower( get_woocommerce_currency() ),
				'country_code'      => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'needs_shipping'    => WC()->cart->needs_shipping(),
				// Defaults to 'required' to match how core initializes this option.
				'needs_payer_phone' => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			],
			'button'             => $this->get_button_settings(),
			'login_confirmation' => '',
			'is_product_page'    => $this->express_checkout_helper->is_product(),
			'button_context'     => $this->express_checkout_helper->get_button_context(),
			'is_pay_for_order'   => $this->express_checkout_helper->is_pay_for_order_page(),
			'has_block'          => has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ),
			'product'            => $this->get_product_data(),
			'total_label'        => $this->express_checkout_helper->get_total_label(),
			'is_checkout_page'   => $this->express_checkout_helper->is_checkout(),
		];

		WC_Payments::register_script_with_dependencies( 'WCPAY_EXPRESS_CHECKOUT_ECE', 'dist/express-checkout', [ 'jquery', 'stripe' ] );

		WC_Payments_Utils::enqueue_style(
			'WCPAY_EXPRESS_CHECKOUT_ECE',
			plugins_url( 'dist/payment-request.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/payment-request.css' )
		);

		wp_localize_script( 'WCPAY_EXPRESS_CHECKOUT_ECE', 'wcpayExpressCheckoutParams', $payment_request_params );

		wp_set_script_translations( 'WCPAY_EXPRESS_CHECKOUT_ECE', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_EXPRESS_CHECKOUT_ECE' );

		Fraud_Prevention_Service::maybe_append_fraud_prevention_token();

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['woocommerce_payments'] ) ) {
			WC_Payments::get_wc_payments_checkout()->register_scripts();
		}
	}

	/**
	 * Display the payment request button.
	 */
	public function display_express_checkout_button_html() {
		if ( ! $this->should_show_express_checkout_button() ) {
			return;
		}
		?>
		<div id="wcpay-express-checkout-element"></div>
		<?php
	}

	/**
	 * Whether product page has a supported product.
	 *
	 * @return boolean
	 */
	private function is_product_supported() {
		$product      = $this->express_checkout_helper->get_product();
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
	 * Gets the product data for the currently viewed page.
	 *
	 * @return mixed Returns false if not on a product page, the product information otherwise.
	 */
	public function get_product_data() {
		if ( ! $this->express_checkout_helper->is_product() ) {
			return false;
		}

		/** @var WC_Product_Variable $product */ // phpcs:ignore
		$product  = $this->express_checkout_helper->get_product();
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
			'label'   => apply_filters( 'wcpay_payment_request_total_label', $this->express_checkout_helper->get_total_label() ),
			'amount'  => WC_Payments_Utils::prepare_amount( $price + $total_tax, $currency ),
			'pending' => true,
		];

		$data['needs_shipping'] = ( wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping() );
		$data['currency']       = strtolower( $currency );
		$data['country_code']   = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

		return apply_filters( 'wcpay_payment_request_product_data', $data, $product );
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
		if ( $this->express_checkout_helper->cart_prices_include_tax() ) {
			$base_price = wc_get_price_including_tax( $product );
		} else {
			$base_price = wc_get_price_excluding_tax( $product );
		}

		// If WooCommerce Deposits is active, we need to get the correct price for the product.
		if ( class_exists( 'WC_Deposits_Product_Manager' ) && WC_Deposits_Product_Manager::deposits_enabled( $product->get_id() ) ) {
			if ( is_null( $is_deposit ) ) {
				/**
				 * If is_deposit is null, we use the default deposit type for the product.
				 *
				 * @psalm-suppress UndefinedClass
				 */
				$is_deposit = 'deposit' === WC_Deposits_Product_Manager::get_deposit_selected_type( $product->get_id() );
			}
			if ( $is_deposit ) {
				/**
				 * Ignore undefined classes from 3rd party plugins.
				 *
				 * @psalm-suppress UndefinedClass
				 */
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
	private function get_taxes_like_cart( $product, $price ) {
		if ( ! wc_tax_enabled() || $this->express_checkout_helper->cart_prices_include_tax() ) {
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
}
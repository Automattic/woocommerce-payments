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
	 */
	public function has_allowed_items_in_cart() {
		// Pre Orders compatbility where we don't support charge upon release.
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

			// Trial subscriptions with shipping are not supported.
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
			'product'            => '',
			'total_label'        => $this->express_checkout_helper->get_total_label(),
			'is_checkout_page'   => $this->express_checkout_helper->is_checkout(),
		];

		WC_Payments::register_script_with_dependencies( 'WCPAY_PAYMENT_REQUEST', 'dist/express-checkout', [ 'jquery', 'stripe' ] );

		WC_Payments_Utils::enqueue_style(
			'WCPAY_PAYMENT_REQUEST',
			plugins_url( 'dist/payment-request.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/payment-request.css' )
		);

		wp_localize_script( 'WCPAY_PAYMENT_REQUEST', 'wcpayPaymentRequestParams', $payment_request_params );

		wp_set_script_translations( 'WCPAY_PAYMENT_REQUEST', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_PAYMENT_REQUEST' );

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

}
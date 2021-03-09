<?php
/**
 * Stripe Payment Request API
 * Adds support for Apple Pay and Chrome Payment Request API buttons.
 * Utilizes the Stripe Payment Request Button to support checkout from the product detail and cart pages.
 *
 * Adapted from WooCommerce Stripe Gateway extension.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WCPay\Logger;

/**
 * WC_Payments_Payment_Request_Button_Handler class.
 */
class WC_Payments_Payment_Request_Button_Handler {
	/**
	 * Gateway settings.
	 *
	 * @var array
	 */
	public $gateway_settings;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * This Instance.
	 *
	 * @var WC_Payments_Payment_Request_Button_Handler
	 */
	private static $instance;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payments_Account $account Account information.
	 */
	public function __construct( WC_Payments_Account $account ) {
		self::$instance = $this;

		// Add default settings in case the payment request options are missing.
		$this->gateway_settings = array_merge( self::get_default_settings(), get_option( 'woocommerce_woocommerce_payments_settings', [] ) );
		$this->account          = $account;
		$this->testmode         = ( ! empty( $this->gateway_settings['test_mode'] ) && 'yes' === $this->gateway_settings['test_mode'] ) ? true : false;

		// Checks if Stripe Gateway is enabled.
		if ( empty( $this->gateway_settings ) || ( isset( $this->gateway_settings['enabled'] ) && 'yes' !== $this->gateway_settings['enabled'] ) ) {
			return;
		}

		// Checks if Payment Request is enabled.
		if ( ! isset( $this->gateway_settings['payment_request'] ) || 'yes' !== $this->gateway_settings['payment_request'] ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		add_action( 'template_redirect', [ $this, 'set_session' ] );
		$this->init();
	}

	/**
	 * Payment request default settings.
	 *
	 * @return array Default settings.
	 */
	public static function get_default_settings() {
		return [
			'payment_request'                     => 'yes',
			'payment_request_button_type'         => 'buy',
			'payment_request_button_theme'        => 'dark',
			'payment_request_button_height'       => '44',
			'payment_request_button_label'        => __( 'Buy now', 'woocommerce-payments' ),
			'payment_request_button_branded_type' => 'long',
		];
	}

	/**
	 * Get this instance.
	 *
	 * @return class
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Get total label.
	 *
	 * @return string
	 */
	public function get_total_label() {
		// Get statement descriptor from API/cached account data.
		$statement_descriptor = $this->account->get_statement_descriptor();
		return str_replace( "'", '', $statement_descriptor ) . apply_filters( 'wcpay_payment_request_total_label_suffix', ' (via WooCommerce)' );
	}

	/**
	 * Sets the WC customer session if one is not set.
	 * This is needed so nonces can be verified by AJAX Request.
	 *
	 * @return void
	 */
	public function set_session() {
		if ( ! is_product() || ( isset( WC()->session ) && WC()->session->has_session() ) ) {
			return;
		}

		WC()->session->set_customer_session_cookie( true );
	}

	/**
	 * Initialize hooks.
	 *
	 * @return  void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_payment_request_button_html' ], 1 );
		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_payment_request_button_separator_html' ], 2 );

		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_payment_request_button_html' ], 1 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_payment_request_button_separator_html' ], 2 );

		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_payment_request_button_html' ], 1 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_payment_request_button_separator_html' ], 2 );

		add_action( 'wc_ajax_wcpay_get_cart_details', [ $this, 'ajax_get_cart_details' ] );
		add_action( 'wc_ajax_wcpay_get_shipping_options', [ $this, 'ajax_get_shipping_options' ] );
		add_action( 'wc_ajax_wcpay_update_shipping_method', [ $this, 'ajax_update_shipping_method' ] );
		add_action( 'wc_ajax_wcpay_create_order', [ $this, 'ajax_create_order' ] );
		add_action( 'wc_ajax_wcpay_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
		add_action( 'wc_ajax_wcpay_get_selected_product_data', [ $this, 'ajax_get_selected_product_data' ] );
		add_action( 'wc_ajax_wcpay_clear_cart', [ $this, 'ajax_clear_cart' ] );
		add_action( 'wc_ajax_wcpay_log_errors', [ $this, 'ajax_log_errors' ] );

		add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
		add_filter( 'woocommerce_validate_postcode', [ $this, 'postal_code_validation' ], 10, 3 );

		add_action( 'woocommerce_checkout_order_processed', [ $this, 'add_order_meta' ], 10, 2 );
	}

	/**
	 * Gets the button type.
	 *
	 * @return  string
	 */
	public function get_button_type() {
		return isset( $this->gateway_settings['payment_request_button_type'] ) ? $this->gateway_settings['payment_request_button_type'] : 'default';
	}

	/**
	 * Gets the button theme.
	 *
	 * @return  string
	 */
	public function get_button_theme() {
		return isset( $this->gateway_settings['payment_request_button_theme'] ) ? $this->gateway_settings['payment_request_button_theme'] : 'dark';
	}

	/**
	 * Gets the button height.
	 *
	 * @return  string
	 */
	public function get_button_height() {
		return isset( $this->gateway_settings['payment_request_button_height'] ) ? str_replace( 'px', '', $this->gateway_settings['payment_request_button_height'] ) : '64';
	}

	/**
	 * Checks if the button is branded.
	 *
	 * @return  boolean
	 */
	public function is_branded_button() {
		return 'branded' === $this->get_button_type();
	}

	/**
	 * Gets the branded button type.
	 *
	 * @return  string
	 */
	public function get_button_branded_type() {
		return isset( $this->gateway_settings['payment_request_button_branded_type'] ) ? $this->gateway_settings['payment_request_button_branded_type'] : 'default';
	}

	/**
	 * Checks if the button is custom.
	 *
	 * @return  boolean
	 */
	public function is_custom_button() {
		return 'custom' === $this->get_button_type();
	}

	/**
	 * Returns custom button css selector.
	 *
	 * @return  string
	 */
	public function custom_button_selector() {
		return $this->is_custom_button() ? '#wcpay-custom-button' : '';
	}

	/**
	 * Gets the custom button label.
	 *
	 * @return  string
	 */
	public function get_button_label() {
		return isset( $this->gateway_settings['payment_request_button_label'] ) ? $this->gateway_settings['payment_request_button_label'] : 'Buy now';
	}

	/**
	 * Gets the product data for the currently viewed page
	 *
	 * @return  mixed Returns false if not on a product page, the product information otherwise.
	 */
	public function get_product_data() {
		if ( ! is_product() ) {
			return false;
		}

		global $post;

		$product = wc_get_product( $post->ID );

		if ( 'variable' === $product->get_type() ) {
			$attributes = wc_clean( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			if ( ! empty( $variation_id ) ) {
				$product = wc_get_product( $variation_id );
			}
		}

		$data  = [];
		$items = [];

		$items[] = [
			'label'  => $product->get_name(),
			'amount' => WC_Payments_Utils::prepare_amount( $product->get_price() ),
		];

		if ( wc_tax_enabled() ) {
			$items[] = [
				'label'   => __( 'Tax', 'woocommerce-payments' ),
				'amount'  => 0,
				'pending' => true,
			];
		}

		if ( wc_shipping_enabled() && $product->needs_shipping() ) {
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
			'amount'  => WC_Payments_Utils::prepare_amount( $product->get_price() ),
			'pending' => true,
		];

		$data['requestShipping'] = ( wc_shipping_enabled() && $product->needs_shipping() );
		$data['currency']        = strtolower( get_woocommerce_currency() );
		$data['country_code']    = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

		return apply_filters( 'wcpay_payment_request_product_data', $data, $product );
	}

	/**
	 * Filters the gateway title to reflect Payment Request type
	 *
	 * @param string $title Gateway title.
	 * @param string $id    Gateway ID.
	 */
	public function filter_gateway_title( $title, $id ) {
		global $post;

		if ( ! is_object( $post ) ) {
			return $title;
		}

		$order        = wc_get_order( $post->ID );
		$method_title = is_object( $order ) ? $order->get_payment_method_title() : '';

		if ( 'woocommerce_payments' === $id && ! empty( $method_title ) && 'Apple Pay (WooCommerce Payments)' === $method_title ) {
			return $method_title;
		}

		if ( 'woocommerce_payments' === $id && ! empty( $method_title ) && 'Chrome Payment Request (WooCommerce Payments)' === $method_title ) {
			return $method_title;
		}

		return $title;
	}

	/**
	 * Removes postal code validation from WC.
	 *
	 * @param bool   $valid Whether postal code is valid.
	 * @param string $postcode Postal code.
	 * @param string $country Country.
	 * @return bool Whether postal code is valid.
	 */
	public function postal_code_validation( $valid, $postcode, $country ) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['woocommerce_payments'] ) ) {
			return $valid;
		}

		$payment_request_type = isset( $_POST['payment_request_type'] ) ? wc_clean( wp_unslash( $_POST['payment_request_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'apple_pay' !== $payment_request_type ) {
			return $valid;
		}

		/**
		 * Currently Apple Pay truncates postal codes from UK and Canada to first 3 characters
		 * when passing it back from the shippingcontactselected object. This causes WC to invalidate
		 * the order and not let it go through. The remedy for now is just to remove this validation.
		 * Note that this only works with shipping providers that don't validate full postal codes.
		 */
		if ( 'GB' === $country || 'CA' === $country ) {
			return true;
		}

		return $valid;
	}

	/**
	 * Add needed order meta
	 *
	 * @param integer $order_id    The order ID.
	 * @param array   $posted_data The posted data from checkout form.
	 *
	 * @return  void
	 */
	public function add_order_meta( $order_id, $posted_data ) {
		if ( empty( $_POST['payment_request_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$order = wc_get_order( $order_id );

		$payment_request_type = wc_clean( wp_unslash( $_POST['payment_request_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'apple_pay' === $payment_request_type ) {
			$order->set_payment_method_title( 'Apple Pay (WooCommerce Payments)' );
			$order->save();
		}

		if ( 'payment_request_api' === $payment_request_type ) {
			$order->set_payment_method_title( 'Chrome Payment Request (WooCommerce Payments)' );
			$order->save();
		}
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
	 * Checks the cart to see if all items are allowed to used.
	 *
	 * @return  boolean
	 */
	public function allowed_items_in_cart() {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( ! in_array( $_product->get_type(), $this->supported_product_types(), true ) ) {
				return false;
			}

			// Trial subscriptions with shipping are not supported.
			if ( class_exists( 'WC_Subscriptions_Order' ) && WC_Subscriptions_Cart::cart_contains_subscription() && $_product->needs_shipping() && WC_Subscriptions_Product::get_trial_length( $_product ) > 0 ) {
				return false;
			}

			// Pre Orders compatbility where we don't support charge upon release.
			if ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Cart::cart_contains_pre_order() && WC_Pre_Orders_Product::product_is_charged_upon_release( WC_Pre_Orders_Cart::get_pre_order_product() ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Load public scripts and styles.
	 */
	public function scripts() {
		// If account is not connected then bail.
		if ( ! $this->account->is_stripe_connected( false ) ) {
			return;
		}

		// If no SSL bail.
		if ( ! $this->testmode && ! is_ssl() ) {
			Logger::log( 'Stripe Payment Request live mode requires SSL.' );
			return;
		}

		if ( ! is_product() && ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( is_product() && ! $this->should_show_payment_button_on_product_page() ) {
			return;
		}

		$stripe_params = [
			'ajax_url'        => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe'          => [
				'publishableKey'     => $this->account->get_publishable_key( WC_Payments::get_gateway()->is_in_test_mode() ),
				'accountId'          => $this->account->get_stripe_account_id(),
				'allow_prepaid_card' => apply_filters( 'wcpay_allow_prepaid_card', true ) ? 'yes' : 'no',
			],
			'nonce'           => [
				'payment'                   => wp_create_nonce( 'wcpay-payment-request' ),
				'shipping'                  => wp_create_nonce( 'wcpay-payment-request-shipping' ),
				'update_shipping'           => wp_create_nonce( 'wcpay-update-shipping-method' ),
				'checkout'                  => wp_create_nonce( 'woocommerce-process_checkout' ),
				'add_to_cart'               => wp_create_nonce( 'wcpay-add-to-cart' ),
				'get_selected_product_data' => wp_create_nonce( 'wcpay-get-selected-product-data' ),
				'log_errors'                => wp_create_nonce( 'wcpay-log-errors' ),
				'clear_cart'                => wp_create_nonce( 'wcpay-clear-cart' ),
			],
			'i18n'            => [
				'no_prepaid_card'  => __( 'Sorry, we\'re not accepting prepaid cards at this time.', 'woocommerce-payments' ),
				/* translators: Do not translate the [option] placeholder */
				'unknown_shipping' => __( 'Unknown shipping option "[option]".', 'woocommerce-payments' ),
			],
			'checkout'        => [
				'url'               => wc_get_checkout_url(),
				'currency_code'     => strtolower( get_woocommerce_currency() ),
				'country_code'      => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'needs_shipping'    => WC()->cart->needs_shipping() ? 'yes' : 'no',
				// Defaults to 'required' to match how core initializes this option.
				'needs_payer_phone' => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			],
			'button'          => [
				'type'         => $this->get_button_type(),
				'theme'        => $this->get_button_theme(),
				'height'       => $this->get_button_height(),
				'locale'       => apply_filters( 'wcpay_payment_request_button_locale', substr( get_locale(), 0, 2 ) ), // Default format is en_US.
				'is_custom'    => $this->is_custom_button(),
				'is_branded'   => $this->is_branded_button(),
				'css_selector' => $this->custom_button_selector(),
				'branded_type' => $this->get_button_branded_type(),
			],
			'is_product_page' => is_product(),
			'product'         => $this->get_product_data(),
		];

		wp_register_style( 'payment_request_styles', plugins_url( 'dist/payment-request.css', WCPAY_PLUGIN_FILE ), [], WC_Payments::get_file_version( 'dist/payment-request.css' ) );
		wp_enqueue_style( 'payment_request_styles' );

		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', [], '3.0', true );
		wp_register_script( 'WCPAY_PAYMENT_REQUEST', plugins_url( 'dist/payment-request.js', WCPAY_PLUGIN_FILE ), [ 'jquery', 'stripe' ], WC_Payments::get_file_version( 'dist/payment-request.js' ), true );

		wp_localize_script( 'WCPAY_PAYMENT_REQUEST', 'wcpayPaymentRequestParams', $stripe_params );

		wp_enqueue_script( 'WCPAY_PAYMENT_REQUEST' );

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['woocommerce_payments'] ) ) {
			$gateways['woocommerce_payments']->register_scripts();
		}
	}

	/**
	 * Display the payment request button.
	 */
	public function display_payment_request_button_html() {
		global $post;
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['woocommerce_payments'] ) ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() && ! is_product() && ! isset( $_GET['pay_for_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( is_checkout() && ! apply_filters( 'wcpay_show_payment_request_on_checkout', false, $post ) ) {
			return;
		}

		if ( is_product() && ! $this->should_show_payment_button_on_product_page() ) {
			return;
		} elseif ( ! $this->should_show_payment_button_on_cart() ) {
			return;
		}
		?>
		<div id="wcpay-payment-request-wrapper" style="clear:both;padding-top:1.5em;display:none;">
			<div id="wcpay-payment-request-button">
				<?php
				if ( $this->is_custom_button() ) {
					$label      = esc_html( $this->get_button_label() );
					$class_name = esc_attr( 'button ' . $this->get_button_theme() );
					$style      = esc_attr( 'height:' . $this->get_button_height() . 'px;' );
					echo '<button id="wcpay-custom-button" class="' . esc_attr( $class_name ) . '" style="' . esc_attr( $style ) . '">' . esc_html( $label ) . '</button>';
				}
				?>
				<!-- A Stripe Element will be inserted here. -->
			</div>
		</div>
		<?php
	}

	/**
	 * Display payment request button separator.
	 */
	public function display_payment_request_button_separator_html() {
		global $post;

		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['woocommerce_payments'] ) ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() && ! is_product() && ! isset( $_GET['pay_for_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( is_checkout() && ! apply_filters( 'wcpay_show_payment_request_on_checkout', false, $post ) ) {
			return;
		}

		if ( is_product() && ! $this->should_show_payment_button_on_product_page() ) {
			return;
		} elseif ( ! $this->should_show_payment_button_on_cart() ) {
			return;
		}
		?>
		<p id="wcpay-payment-request-button-separator" style="margin-top:1.5em;text-align:center;display:none;">&mdash; <?php esc_html_e( 'OR', 'woocommerce-payments' ); ?> &mdash;</p>
		<?php
	}

	/**
	 * Whether payment button html should be rendered on the Cart
	 *
	 * @return boolean
	 */
	private function should_show_payment_button_on_cart() {
		if ( ! apply_filters( 'wcpay_show_payment_request_on_cart', true ) ) {
			return false;
		}
		if ( ! $this->allowed_items_in_cart() ) {
			Logger::log( 'Items in the cart has unsupported product type ( Payment Request button disabled )' );
			return false;
		}
		return true;
	}

	/**
	 * Whether payment button html should be rendered
	 *
	 * @return boolean
	 */
	private function should_show_payment_button_on_product_page() {
		global $post;

		$product = wc_get_product( $post->ID );

		if ( apply_filters( 'wcpay_hide_payment_request_on_product_page', false, $post ) ) {
			return false;
		}

		if ( ! is_object( $product ) || ! in_array( $product->get_type(), $this->supported_product_types(), true ) ) {
			return false;
		}

		// Trial subscriptions with shipping are not supported.
		if ( class_exists( 'WC_Subscriptions_Order' ) && $product->needs_shipping() && WC_Subscriptions_Product::get_trial_length( $product ) > 0 ) {
			return false;
		}

		// Pre Orders charge upon release not supported.
		if ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) {
			Logger::log( 'Pre Order charge upon release is not supported. ( Payment Request button disabled )' );
			return false;
		}

		// File upload addon not supported.
		if ( class_exists( 'WC_Product_Addons_Helper' ) ) {
			$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );
			foreach ( $product_addons as $addon ) {
				if ( 'file_upload' === $addon['type'] ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Log errors coming from Payment Request
	 */
	public function ajax_log_errors() {
		check_ajax_referer( 'wcpay-log-errors', 'security' );

		if ( empty( $_POST['errors'] ) ) {
			exit;
		}

		$errors = wc_clean( wp_unslash( $_POST['errors'] ) );

		Logger::log( $errors );

		exit;
	}

	/**
	 * Clears cart.
	 */
	public function ajax_clear_cart() {
		check_ajax_referer( 'wcpay-clear-cart', 'security' );

		WC()->cart->empty_cart();
		exit;
	}

	/**
	 * Get cart details.
	 */
	public function ajax_get_cart_details() {
		check_ajax_referer( 'wcpay-payment-request', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->cart->calculate_totals();

		$currency = get_woocommerce_currency();

		// Set mandatory payment details.
		$data = [
			'shipping_required' => WC()->cart->needs_shipping(),
			'order_data'        => [
				'currency'     => strtolower( $currency ),
				'country_code' => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			],
		];

		$data['order_data'] += $this->build_display_items();

		wp_send_json( $data );
	}

	/**
	 * Get shipping options.
	 *
	 * @see WC_Cart::get_shipping_packages().
	 * @see WC_Shipping::calculate_shipping().
	 * @see WC_Shipping::get_packages().
	 */
	public function ajax_get_shipping_options() {
		check_ajax_referer( 'wcpay-payment-request-shipping', 'security' );

		$shipping_address          = filter_input_array(
			INPUT_POST,
			[
				'country'   => FILTER_SANITIZE_STRING,
				'state'     => FILTER_SANITIZE_STRING,
				'postcode'  => FILTER_SANITIZE_STRING,
				'city'      => FILTER_SANITIZE_STRING,
				'address'   => FILTER_SANITIZE_STRING,
				'address_2' => FILTER_SANITIZE_STRING,
			]
		);
		$product_view_options      = filter_input_array( INPUT_POST, [ 'is_product_page' => FILTER_SANITIZE_STRING ] );
		$should_show_itemized_view = ! isset( $product_view_options['is_product_page'] ) ? true : filter_var( $product_view_options['is_product_page'], FILTER_VALIDATE_BOOLEAN );

		$data = $this->get_shipping_options( $shipping_address, $should_show_itemized_view );
		wp_send_json( $data );
	}

	/**
	 * Gets shipping options available for specified shipping address
	 *
	 * @param array   $shipping_address       Shipping address.
	 * @param boolean $itemized_display_items Indicates whether to show subtotals or itemized views.
	 *
	 * @return array Shipping options data.
	 * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag
	 */
	public function get_shipping_options( $shipping_address, $itemized_display_items = false ) {
		try {
			// Set the shipping options.
			$data = [];

			// Remember current shipping method before resetting.
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			$this->calculate_shipping( apply_filters( 'wcpay_payment_request_shipping_posted_values', $shipping_address ) );

			$packages = WC()->shipping->get_packages();

			if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
				foreach ( $packages as $package_key => $package ) {
					if ( empty( $package['rates'] ) ) {
						throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-payments' ) );
					}

					foreach ( $package['rates'] as $key => $rate ) {
						$data['shipping_options'][] = [
							'id'     => $rate->id,
							'label'  => $rate->label,
							'detail' => '',
							'amount' => WC_Payments_Utils::prepare_amount( $rate->cost ),
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

			$data          += $this->build_display_items( $itemized_display_items );
			$data['result'] = 'success';
		} catch ( Exception $e ) {
			$data          += $this->build_display_items( $itemized_display_items );
			$data['result'] = 'invalid_shipping_address';
		}

		return $data;
	}

	/**
	 * Update shipping method.
	 */
	public function ajax_update_shipping_method() {
		check_ajax_referer( 'wcpay-update-shipping-method', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$shipping_methods = filter_input( INPUT_POST, 'shipping_method', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$this->update_shipping_method( $shipping_methods );

		WC()->cart->calculate_totals();

		$product_view_options      = filter_input_array( INPUT_POST, [ 'is_product_page' => FILTER_SANITIZE_STRING ] );
		$should_show_itemized_view = ! isset( $product_view_options['is_product_page'] ) ? true : filter_var( $product_view_options['is_product_page'], FILTER_VALIDATE_BOOLEAN );

		$data           = [];
		$data          += $this->build_display_items( $should_show_itemized_view );
		$data['result'] = 'success';

		wp_send_json( $data );
	}

	/**
	 * Updates shipping method in WC session
	 *
	 * @param array $shipping_methods Array of selected shipping methods ids.
	 */
	public function update_shipping_method( $shipping_methods ) {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shipping_methods ) ) {
			foreach ( $shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}

	/**
	 * Gets the selected product data.
	 *
	 * @throws Exception If product or stock is unavailable - caught inside function.
	 */
	public function ajax_get_selected_product_data() {
		check_ajax_referer( 'wcpay-get-selected-product-data', 'security' );

		try {
			$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : false;
			$qty          = ! isset( $_POST['qty'] ) ? 1 : apply_filters( 'woocommerce_add_to_cart_quantity', absint( $_POST['qty'] ), $product_id );
			$addon_value  = isset( $_POST['addon_value'] ) ? max( floatval( $_POST['addon_value'] ), 0 ) : 0;
			$product      = wc_get_product( $product_id );
			$variation_id = null;

			if ( ! is_a( $product, 'WC_Product' ) ) {
				/* translators: product ID */
				throw new Exception( sprintf( __( 'Product with the ID (%d) cannot be found.', 'woocommerce-payments' ), $product_id ) );
			}

			if ( 'variable' === $product->get_type() && isset( $_POST['attributes'] ) ) {
				$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

				if ( ! empty( $variation_id ) ) {
					$product = wc_get_product( $variation_id );
				}
			}

			// Force quantity to 1 if sold individually and check for existing item in cart.
			if ( $product->is_sold_individually() ) {
				$qty = apply_filters( 'wcpay_payment_request_add_to_cart_sold_individually_quantity', 1, $qty, $product_id, $variation_id );
			}

			if ( ! $product->has_enough_stock( $qty ) ) {
				/* translators: 1: product name 2: quantity in stock */
				throw new Exception( sprintf( __( 'You cannot add that amount of "%1$s"; to the cart because there is not enough stock (%2$s remaining).', 'woocommerce-payments' ), $product->get_name(), wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ) );
			}

			$total = $qty * $product->get_price() + $addon_value;

			$quantity_label = 1 < $qty ? ' (x' . $qty . ')' : '';

			$data  = [];
			$items = [];

			$items[] = [
				'label'  => $product->get_name() . $quantity_label,
				'amount' => WC_Payments_Utils::prepare_amount( $total ),
			];

			if ( wc_tax_enabled() ) {
				$items[] = [
					'label'   => __( 'Tax', 'woocommerce-payments' ),
					'amount'  => 0,
					'pending' => true,
				];
			}

			if ( wc_shipping_enabled() && $product->needs_shipping() ) {
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
				'label'   => $this->get_total_label(),
				'amount'  => WC_Payments_Utils::prepare_amount( $total ),
				'pending' => true,
			];

			$data['requestShipping'] = ( wc_shipping_enabled() && $product->needs_shipping() );
			$data['currency']        = strtolower( get_woocommerce_currency() );
			$data['country_code']    = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

			wp_send_json( $data );
		} catch ( Exception $e ) {
			wp_send_json( [ 'error' => wp_strip_all_tags( $e->getMessage() ) ] );
		}
	}

	/**
	 * Adds the current product to the cart. Used on product detail page.
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'wcpay-add-to-cart', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : false;
		$qty          = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );
		$product      = wc_get_product( $product_id );
		$product_type = $product->get_type();

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
			$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
		}

		if ( 'simple' === $product_type || 'subscription' === $product_type ) {
			WC()->cart->add_to_cart( $product->get_id(), $qty );
		}

		WC()->cart->calculate_totals();

		$data           = [];
		$data          += $this->build_display_items();
		$data['result'] = 'success';

		wp_send_json( $data );
	}

	/**
	 * Normalizes the state/county field because in some
	 * cases, the state/county field is formatted differently from
	 * what WC is expecting and throws an error. An example
	 * for Ireland the county dropdown in Chrome shows "Co. Clare" format
	 */
	public function normalize_state() {
		check_ajax_referer( 'woocommerce-process_checkout', '_wpnonce' );

		$billing_country  = ! empty( $_POST['billing_country'] ) ? wc_clean( wp_unslash( $_POST['billing_country'] ) ) : '';
		$shipping_country = ! empty( $_POST['shipping_country'] ) ? wc_clean( wp_unslash( $_POST['shipping_country'] ) ) : '';
		$billing_state    = ! empty( $_POST['billing_state'] ) ? wc_clean( wp_unslash( $_POST['billing_state'] ) ) : '';
		$shipping_state   = ! empty( $_POST['shipping_state'] ) ? wc_clean( wp_unslash( $_POST['shipping_state'] ) ) : '';

		if ( $billing_state && $billing_country ) {
			$valid_states = WC()->countries->get_states( $billing_country );

			// Valid states found for country.
			if ( ! empty( $valid_states ) && is_array( $valid_states ) && count( $valid_states ) > 0 ) {
				foreach ( $valid_states as $state_abbr => $state ) {
					if ( preg_match( '/' . preg_quote( $state, '/' ) . '/i', $billing_state ) ) {
						$_POST['billing_state'] = $state_abbr;
					}
				}
			}
		}

		if ( $shipping_state && $shipping_country ) {
			$valid_states = WC()->countries->get_states( $shipping_country );

			// Valid states found for country.
			if ( ! empty( $valid_states ) && is_array( $valid_states ) && count( $valid_states ) > 0 ) {
				foreach ( $valid_states as $state_abbr => $state ) {
					if ( preg_match( '/' . preg_quote( $state, '/' ) . '/i', $shipping_state ) ) {
						$_POST['shipping_state'] = $state_abbr;
					}
				}
			}
		}
	}

	/**
	 * Create order. Security is handled by WC.
	 */
	public function ajax_create_order() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'woocommerce-payments' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		$this->normalize_state();

		WC()->checkout()->process_checkout();

		die( 0 );
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
		$address_1 = $address['address'];
		$address_2 = $address['address_2'];
		$wc_states = WC()->countries->get_states( $country );

		/**
		 * In some versions of Chrome, state can be a full name. So we need
		 * to convert that to abbreviation as WC is expecting that.
		 */
		if ( 2 < strlen( $state ) && ! empty( $wc_states ) && ! isset( $wc_states[ $state ] ) ) {
			$state = array_search( ucwords( strtolower( $state ) ), $wc_states, true );
		}

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

	/**
	 * Builds the shipping methods to pass to Payment Request
	 *
	 * @param array $shipping_methods Shipping methods.
	 */
	protected function build_shipping_methods( $shipping_methods ) {
		if ( empty( $shipping_methods ) ) {
			return [];
		}

		$shipping = [];

		foreach ( $shipping_methods as $method ) {
			$shipping[] = [
				'id'     => $method['id'],
				'label'  => $method['label'],
				'detail' => '',
				'amount' => WC_Payments_Utils::prepare_amount( $method['amount']['value'] ),
			];
		}

		return $shipping;
	}

	/**
	 * Builds the line items to pass to Payment Request
	 *
	 * @param boolean $itemized_display_items Indicates whether to show subtotals or itemized views.
	 */
	protected function build_display_items( $itemized_display_items = false ) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$items     = [];
		$subtotal  = 0;
		$discounts = 0;

		// Default show only subtotal instead of itemization.
		if ( ! apply_filters( 'wcpay_payment_request_hide_itemization', true ) || $itemized_display_items ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$amount         = $cart_item['line_subtotal'];
				$subtotal      += $cart_item['line_subtotal'];
				$quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';

				$product_name = $cart_item['data']->get_name();

				$item = [
					'label'  => $product_name . $quantity_label,
					'amount' => WC_Payments_Utils::prepare_amount( $amount ),
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
		$order_total = version_compare( WC_VERSION, '3.2', '<' ) ? wc_format_decimal( $items_total + $tax + $shipping - $discounts, WC()->cart->dp ) : WC()->cart->get_total( false );

		if ( wc_tax_enabled() ) {
			$items[] = [
				'label'  => esc_html( __( 'Tax', 'woocommerce-payments' ) ),
				'amount' => WC_Payments_Utils::prepare_amount( $tax ),
			];
		}

		if ( WC()->cart->needs_shipping() ) {
			$items[] = [
				'label'  => esc_html( __( 'Shipping', 'woocommerce-payments' ) ),
				'amount' => WC_Payments_Utils::prepare_amount( $shipping ),
			];
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = [
				'label'  => esc_html( __( 'Discount', 'woocommerce-payments' ) ),
				'amount' => WC_Payments_Utils::prepare_amount( $discounts ),
			];
		}

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$cart_fees = WC()->cart->fees;
		} else {
			$cart_fees = WC()->cart->get_fees();
		}

		// Include fees and taxes as display items.
		foreach ( $cart_fees as $key => $fee ) {
			$items[] = [
				'label'  => $fee->name,
				'amount' => WC_Payments_Utils::prepare_amount( $fee->amount ),
			];
		}

		return [
			'displayItems' => $items,
			'total'        => [
				'label'   => $this->get_total_label(),
				'amount'  => max( 0, apply_filters( 'wcpay_calculated_total', WC_Payments_Utils::prepare_amount( $order_total ), $order_total, WC()->cart ) ),
				'pending' => false,
			],
		];
	}
}

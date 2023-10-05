<?php
/**
 * Class WC_Payments_WooPay_Button_Handler
 * Adds support for the WooPay express checkout button.
 *
 * Borrowed heavily from the WC_Payments_Payment_Request_Button_Handler class.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// TODO: Not sure which of these are needed yet.
use WCPay\Exceptions\Invalid_Price_Exception;
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\WooPay\WooPay_Utilities;

/**
 * WC_Payments_WooPay_Button_Handler class.
 */
class WC_Payments_WooPay_Button_Handler {
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
	 * WooPay_Utilities instance.
	 *
	 * @var WooPay_Utilities
	 */
	private $woopay_utilities;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payments_Account      $account Account information.
	 * @param WC_Payment_Gateway_WCPay $gateway WCPay gateway.
	 * @param WooPay_Utilities         $woopay_utilities WCPay gateway.
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $gateway, WooPay_Utilities $woopay_utilities ) {
		$this->account          = $account;
		$this->gateway          = $gateway;
		$this->woopay_utilities = $woopay_utilities;
	}

	/**
	 * Indicates eligibility for WooPay via feature flag.
	 *
	 * @var bool
	 */
	private $is_woopay_eligible;

	/**
	 * Indicates whether WooPay is enabled.
	 *
	 * @var bool
	 */
	private $is_woopay_enabled;

	/**
	 * Indicates whether WooPay express checkout is enabled.
	 *
	 * @var bool
	 */
	private $is_woopay_express_button_enabled;

	/**
	 * Indicates whether WooPay and WooPay express checkout are enabled.
	 *
	 * @return bool
	 */
	public function is_woopay_enabled() {
		return $this->is_woopay_eligible && $this->is_woopay_enabled && $this->is_woopay_express_button_enabled;
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

		// Checks if WooPay is enabled.
		$this->is_woopay_eligible               = WC_Payments_Features::is_woopay_eligible(); // Feature flag.
		$this->is_woopay_enabled                = 'yes' === $this->gateway->get_option( 'platform_checkout', 'no' );
		$this->is_woopay_express_button_enabled = WC_Payments_Features::is_woopay_express_checkout_enabled();

		if ( ! $this->is_woopay_enabled() ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_filter( 'wcpay_payment_fields_js_config', [ $this, 'add_woopay_config' ] );

		add_action( 'wc_ajax_wcpay_add_to_cart', [ $this, 'ajax_add_to_cart' ] );

		add_action( 'wp_ajax_woopay_express_checkout_button_show_error_notice', [ $this, 'show_error_notice' ] );
		add_action( 'wp_ajax_nopriv_woopay_express_checkout_button_show_error_notice', [ $this, 'show_error_notice' ] );
	}

	/**
	 * Add the woopay button config to wcpay_config.
	 *
	 * @param array $config The existing config array.
	 *
	 * @return array The modified config array.
	 */
	public function add_woopay_config( $config ) {
		$config['woopayButton']      = $this->get_button_settings();
		$config['woopayButtonNonce'] = wp_create_nonce( 'woopay_button_nonce' );
		$config['addToCartNonce']    = wp_create_nonce( 'wcpay-add-to-cart' );

		return $config;
	}

	/**
	 * Load public scripts and styles.
	 */
	public function scripts() {
		// Don't load scripts if we should not show the button.
		if ( ! $this->should_show_woopay_button() ) {
			return;
		}

		WC_Payments::register_script_with_dependencies( 'WCPAY_WOOPAY_EXPRESS_BUTTON', 'dist/woopay-express-button' );
		WC_Payments_Utils::enqueue_style(
			'WCPAY_WOOPAY_EXPRESS_BUTTON',
			plugins_url( 'dist/woopay-express-button.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/woopay-express-button.css' )
		);

		$wcpay_config = rawurlencode( wp_json_encode( WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config() ) );

		wp_add_inline_script(
			'WCPAY_WOOPAY_EXPRESS_BUTTON',
			"
			var wcpayConfig = wcpayConfig || JSON.parse( decodeURIComponent( '" . esc_js( $wcpay_config ) . "' ) );
			",
			'before'
		);

		wp_set_script_translations( 'WCPAY_WOOPAY_EXPRESS_BUTTON', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_WOOPAY_EXPRESS_BUTTON' );

		WC_Payments_Utils::enqueue_style(
			'WCPAY_WOOPAY',
			plugins_url( 'dist/woopay.css', WCPAY_PLUGIN_FILE ),
			[],
			WCPAY_VERSION_NUMBER,
			'all'
		);
	}

	/**
	 * Returns the error notice HTML.
	 */
	public function show_error_notice() {
		$is_nonce_valid = check_ajax_referer( 'woopay_button_nonce', false, false );

		if ( ! $is_nonce_valid ) {
			wp_send_json_error(
				__( 'You aren’t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';

		// $message has already been translated.
		wc_add_notice( $message, 'error' );
		$notice = wc_print_notices( true );

		wp_send_json_success(
			[
				'notice' => $notice,
			]
		);

		wp_die();
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
		$quantity     = ! isset( $_POST['quantity'] ) ? 1 : absint( $_POST['quantity'] );
		$product      = wc_get_product( $product_id );
		$product_type = $product->get_type();

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		$is_add_to_cart_valid = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

		if ( ! $is_add_to_cart_valid ) {
			// Some extensions error messages needs to be
			// submitted to show error messages.
			wp_send_json(
				[
					'error'  => true,
					'submit' => true,
				],
				400
			);
			return;
		}

		if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
			$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			WC()->cart->add_to_cart( $product->get_id(), $quantity, $variation_id, $attributes );
		}

		if ( in_array( $product_type, [ 'simple', 'subscription', 'bundle', 'mix-and-match' ], true ) ) {
			WC()->cart->add_to_cart( $product->get_id(), $quantity );
		}

		WC()->cart->calculate_totals();

		$data           = [];
		$data          += $this->build_display_items();
		$data['result'] = 'success';

		wp_send_json( $data );
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
		$currency  = get_woocommerce_currency();

		// Default show only subtotal instead of itemization.
		if ( ! apply_filters( 'wcpay_payment_request_hide_itemization', true ) || $itemized_display_items ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$amount         = $cart_item['line_subtotal'];
				$subtotal      += $cart_item['line_subtotal'];
				$quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';

				$product_name = $cart_item['data']->get_name();

				$item_tax = $this->prices_exclude_tax() ? 0 : ( $cart_item['line_subtotal_tax'] ?? 0 );

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

		if ( $this->prices_exclude_tax() ) {
			$items[] = [
				'label'  => esc_html( __( 'Tax', 'woocommerce-payments' ) ),
				'amount' => WC_Payments_Utils::prepare_amount( $tax, $currency ),
			];
		}

		if ( WC()->cart->needs_shipping() ) {
			$shipping_tax = $this->prices_exclude_tax() ? 0 : WC()->cart->shipping_tax_total;
			$items[]      = [
				'label'  => esc_html( __( 'Shipping', 'woocommerce-payments' ) ),
				'amount' => WC_Payments_Utils::prepare_amount( $shipping + $shipping_tax, $currency ),
			];
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = [
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
		foreach ( $cart_fees as $key => $fee ) {
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
	 * Whether tax should be displayed on seperate line.
	 * returns true if tax is enabled & display of tax in checkout is set to exclusive.
	 *
	 * @return boolean
	 */
	private function prices_exclude_tax() {
		return wc_tax_enabled() && 'incl' !== get_option( 'woocommerce_tax_display_cart' );
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
	 * Checks if payment request is available at a given location.
	 *
	 * @param string $location Location.
	 * @return boolean
	 */
	public function is_available_at( $location ) {
		$available_locations = $this->gateway->get_option( 'platform_checkout_button_locations' );
		if ( $available_locations && is_array( $available_locations ) ) {
			return in_array( $location, $available_locations, true );
		}

		return false;
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

		if ( $this->is_checkout() ) {
			return 'checkout';
		}

		if ( $this->is_pay_for_order_page() ) {
			return 'pay_for_order';
		}

		return '';
	}

	/**
	 * The settings for the `button` attribute - they depend on the "grouped settings" flag value.
	 *
	 * @return array
	 */
	public function get_button_settings() {
		$button_type = $this->gateway->get_option( 'payment_request_button_type', 'default' );
		return [
			'type'    => $button_type,
			'theme'   => $this->gateway->get_option( 'payment_request_button_theme', 'dark' ),
			'height'  => $this->get_button_height(),
			'size'    => $this->gateway->get_option( 'payment_request_button_size' ),
			'context' => $this->get_button_context(),
		];
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
			return '56';
		}

		// for the "default" and "catch-all" scenarios.
		return '40';
	}

	/**
	 * Checks whether Payment Request Button should be available on this page.
	 *
	 * @return bool
	 */
	public function should_show_woopay_button() {
		// WCPay is not available.
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( ! isset( $gateways['woocommerce_payments'] ) ) {
			return false;
		}

		// WooPay is not enabled.
		if ( ! $this->is_woopay_enabled() ) {
			return false;
		}

		// Page not supported.
		if ( ! $this->is_product() && ! $this->is_cart() && ! $this->is_checkout() ) {
			return false;
		}

		// Check if WooPay is available in the user country.
		if ( ! $this->woopay_utilities->is_country_available( $this->gateway ) ) {
			return false;
		}

		// Product page, but not available in settings.
		if ( $this->is_product() && ! $this->is_available_at( 'product' ) ) {
			return false;
		}

		// Checkout page, but not available in settings.
		if ( $this->is_checkout() && ! $this->is_available_at( 'checkout' ) ) {
			return false;
		}

		// Cart page, but not available in settings.
		if ( $this->is_cart() && ! $this->is_available_at( 'cart' ) ) {
			return false;
		}

		// Product page, but has unsupported product type.
		if ( $this->is_product() && ! $this->is_product_supported() ) {
			Logger::log( 'Product page has unsupported product type ( WooPay Express button disabled )' );
			return false;
		}

		// Cart has unsupported product type.
		if ( ( $this->is_checkout() || $this->is_cart() ) && ! $this->has_allowed_items_in_cart() ) {
			Logger::log( 'Items in the cart have unsupported product type ( WooPay Express button disabled )' );
			return false;
		}

		if ( ! is_user_logged_in() ) {
			// On product page for a subscription product, but not logged in, making WooPay unavailable.
			if ( $this->is_product() ) {
				$current_product = wc_get_product();

				if ( $current_product && $this->is_product_subscription( $current_product ) ) {
					return false;
				}
			}

			// On cart or checkout page with a subscription product in cart, but not logged in, making WooPay unavailable.
			if ( ( $this->is_checkout() || $this->is_cart() ) && class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
				// Check cart for subscription products.
				return false;
			}

			// If guest checkout is not allowed, and customer is not logged in, disable the WooPay button.
			if ( ! $this->woopay_utilities->is_guest_checkout_enabled() ) {
				return false;
			}
		}

		/**
		 * TODO: We need to do some research here and see if there are any product types that we
		 * absolutely cannot support with WooPay at this time. There are some examples in the
		 * `WC_Payments_Payment_Request_Button_Handler->is_product_supported()` method.
		 */

		return true;
	}

	/**
	 * Display the payment request button.
	 */
	public function display_woopay_button_html() {
		if ( ! $this->should_show_woopay_button() ) {
			return;
		}

		?>
		<div id="wcpay-woopay-button" data-product_page=<?php echo esc_attr( $this->is_product() ); ?>>
				<?php // The WooPay express checkout button React component will go here. ?>
			</div>
		<?php
	}

	/**
	 * Whether the product page has a product compatible with the WooPay Express button.
	 *
	 * @return boolean
	 */
	private function is_product_supported() {
		$product      = $this->get_product();
		$is_supported = true;

		if ( ! is_object( $product ) ) {
			$is_supported = false;
		}

		// Pre Orders products to be charged upon release are not supported.
		if ( class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) {
			$is_supported = false;
		}

		return apply_filters( 'wcpay_woopay_button_is_product_supported', $is_supported, $product );
	}

	/**
	 * Checks the cart to see if the WooPay Express button supports all of its items.
	 *
	 * @todo Abstract this. This is a copy of the same method in the `WC_Payments_Payment_Request_Button_Handler` class.
	 *
	 * @return boolean
	 */
	private function has_allowed_items_in_cart() {
		$is_supported = true;

		// We don't support pre-order products to be paid upon release.
		if (
			class_exists( 'WC_Pre_Orders_Cart' ) &&
			WC_Pre_Orders_Cart::cart_contains_pre_order() &&
			class_exists( 'WC_Pre_Orders_Product' ) &&
			WC_Pre_Orders_Product::product_is_charged_upon_release( WC_Pre_Orders_Cart::get_pre_order_product() )
		) {
			$is_supported = false;
		}

		return apply_filters( 'wcpay_platform_checkout_button_are_cart_items_supported', $is_supported );
	}

	/**
	 * Get product from product page or product_page shortcode.
	 *
	 * @todo Abstract this. This is a copy of the same method in the `WC_Payments_Payment_Request_Button_Handler` class.
	 *
	 * @return WC_Product|false|null Product object.
	 */
	private function get_product() {
		global $post;

		if ( is_product() ) {
			return wc_get_product( $post->ID );
		} elseif ( wc_post_content_has_shortcode( 'product_page' ) ) {
			// Get id from product_page shortcode.
			preg_match( '/\[product_page id="(?<id>\d+)"\]/', $post->post_content, $shortcode_match );
			if ( isset( $shortcode_match['id'] ) ) {
				return wc_get_product( $shortcode_match['id'] );
			}
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
	private function is_product_subscription( WC_Product $product ): bool {
		return 'subscription' === $product->get_type()
			|| 'subscription_variation' === $product->get_type()
			|| 'variable-subscription' === $product->get_type();
	}
}

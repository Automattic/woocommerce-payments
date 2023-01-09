<?php
/**
 * Class WC_Payments_Platform_Checkout_Button_Handler
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

/**
 * WC_Payments_Platform_Checkout_Button_Handler class.
 */
class WC_Payments_Platform_Checkout_Button_Handler {
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
	 * Initialize class actions.
	 *
	 * @param WC_Payments_Account      $account Account information.
	 * @param WC_Payment_Gateway_WCPay $gateway WCPay gateway.
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $gateway ) {
		$this->account = $account;
		$this->gateway = $gateway;

		add_action( 'init', [ $this, 'init' ] );
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
		$is_platform_checkout_eligible               = WC_Payments_Features::is_platform_checkout_eligible(); // Feature flag.
		$is_platform_checkout_enabled                = 'yes' === $this->gateway->get_option( 'platform_checkout', 'no' );
		$is_platform_checkout_express_button_enabled = WC_Payments_Features::is_woopay_express_checkout_enabled();

		if ( ! ( $is_platform_checkout_eligible && $is_platform_checkout_enabled && $is_platform_checkout_express_button_enabled ) ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_filter( 'wcpay_payment_fields_js_config', [ $this, 'add_platform_checkout_config' ] );

		add_action( 'wc_ajax_wcpay_add_to_cart', [ $this, 'ajax_add_to_cart' ] );

		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_platform_checkout_button_html' ], -2 );
		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );

		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_platform_checkout_button_html' ], -2 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );

		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_platform_checkout_button_html' ], -2 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );
	}

	/**
	 * Add the platform checkout button config to wcpay_config.
	 *
	 * @param array $config The existing config array.
	 *
	 * @return array The modified config array.
	 */
	public function add_platform_checkout_config( $config ) {
		$config['platformCheckoutButton'] = $this->get_button_settings();
		$config['addToCartNonce']         = wp_create_nonce( 'wcpay-add-to-cart' );

		return $config;
	}

	/**
	 * Load public scripts and styles.
	 */
	public function scripts() {
		// Don't load scripts if we should not show the button.
		if ( ! $this->should_show_platform_checkout_button() ) {
			return;
		}

		$script_src_url    = plugins_url( 'dist/platform-checkout-express-button.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/platform-checkout-express-button.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require $script_asset_path : [ 'dependencies' => [] ];

		wp_register_script( 'WCPAY_PLATFORM_CHECKOUT_EXPRESS_BUTTON', $script_src_url, $script_asset['dependencies'], WC_Payments::get_file_version( 'dist/platform-checkout-express-button.js' ), true );

		$wcpay_config = rawurlencode( wp_json_encode( WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config() ) );

		wp_add_inline_script(
			'WCPAY_PLATFORM_CHECKOUT_EXPRESS_BUTTON',
			"
			var wcpay_config = wcpay_config || JSON.parse( decodeURIComponent( '" . esc_js( $wcpay_config ) . "' ) );
			",
			'before'
		);

		wp_set_script_translations( 'WCPAY_PLATFORM_CHECKOUT_EXPRESS_BUTTON', 'woocommerce-payments' );

		wp_enqueue_script( 'WCPAY_PLATFORM_CHECKOUT_EXPRESS_BUTTON' );

		wp_register_style(
			'WCPAY_PLATFORM_CHECKOUT',
			plugins_url( 'dist/platform-checkout.css', WCPAY_PLUGIN_FILE ),
			[],
			WCPAY_VERSION_NUMBER
		);

		wp_enqueue_style( 'WCPAY_PLATFORM_CHECKOUT' );
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
		$button_type = $this->gateway->get_option( 'platform_checkout_button_type', 'default' );
		return [
			'type'    => $button_type,
			'text'    => ucfirst( $button_type ),
			'theme'   => $this->gateway->get_option( 'platform_checkout_button_theme', 'dark' ),
			'height'  => $this->get_button_height(),
			'size'    => $this->gateway->get_option( 'platform_checkout_button_size' ),
			'context' => $this->get_button_context(),
		];
	}

	/**
	 * Gets the button height.
	 *
	 * @return string
	 */
	public function get_button_height() {
		$height = $this->gateway->get_option( 'platform_checkout_button_size' );
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
	public function should_show_platform_checkout_button() {
		// Page not supported.
		if ( ! $this->is_product() && ! $this->is_cart() && ! $this->is_checkout() ) {
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
	public function display_platform_checkout_button_html() {
		if ( ! $this->should_show_platform_checkout_button() ) {
			return;
		}

		?>
		<div id="wcpay-payment-request-wrapper" style="clear:both;padding-top:1.5em;">
			<div id="wcpay-platform-checkout-button" data-product_page=<?php echo esc_attr( $this->is_product() ); ?>>
				<?php // The WooPay express checkout button React component will go here. ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Display payment request button separator.
	 */
	public function display_platform_checkout_button_separator_html() {
		if ( ! $this->should_show_platform_checkout_button() ) {
			return;
		}
		?>
		<p id="wcpay-payment-request-button-separator" style="margin-top:1.5em;text-align:center;">&mdash; <?php esc_html_e( 'OR', 'woocommerce-payments' ); ?> &mdash;</p>
		<?php
	}

}

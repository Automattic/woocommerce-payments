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

		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_platform_checkout_button_html' ], -2 );
		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );

		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_platform_checkout_button_html' ], -2 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );

		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_platform_checkout_button_html' ], -2 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );
	}

	/**
	 * Load public scripts and styles.
	 */
	public function scripts() {
		// Don't load scripts if we should not show the button.
		if ( ! $this->should_show_platform_checkout_button() ) {
			return;
		}

		wp_register_style(
			'wcpay-platform-checkout-express-button-styles',
			plugins_url( 'includes/platform-checkout/assets/css/platform-checkout-express-button.css', WCPAY_PLUGIN_FILE ),
			[],
			WCPAY_VERSION_NUMBER
		);

		wp_enqueue_style( 'wcpay-platform-checkout-express-button-styles' );
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
	 * The settings for the `button` attribute - they depend on the "grouped settings" flag value.
	 *
	 * @return array
	 */
	public function get_button_settings() {
		$button_type = $this->gateway->get_option( 'platform_checkout_button_type' );
		return [
			'type'   => $button_type,
			'theme'  => $this->gateway->get_option( 'platform_checkout_button_theme' ),
			'height' => $this->gateway->get_option( 'platform_checkout_button_size' ),
		];
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

		// TODO: Does WooPay have any unsupported product types?

		// phpcs:disable
		// Product page, but has unsupported product type.
		// if ( $this->is_product() && ! $this->is_product_supported() ) {
		// 	Logger::log( 'Product page has unsupported product type ( Payment Request button disabled )' );
		// 	return false;
		// }

		// Cart has unsupported product type.
		// if ( ( $this->is_checkout() || $this->is_cart() ) && ! $this->has_allowed_items_in_cart() ) {
		// 	Logger::log( 'Items in the cart have unsupported product type ( Payment Request button disabled )' );
		// 	return false;
		// }
		// phpcs:enable

		return true;
	}

	/**
	 * Display the payment request button.
	 */
	public function display_platform_checkout_button_html() {
		if ( ! $this->should_show_platform_checkout_button() ) {
			return;
		}

		$button_settings = $this->get_button_settings();
		$button_text     = 'default' !== $button_settings['type'] ? sprintf(
			// Translators: %s is the name of the button action.
			__( '%s with WooPay', 'woocommerce-payments' ),
			ucfirst( $button_settings['type'] )
		) : 'WooPay';

		?>
		<div id="wcpay-payment-request-wrapper" style="clear:both;padding-top:1.5em;display:none;">
			<div id="wcpay-platform-checkout-button">
				<button class="woopay-express-button <?php echo esc_attr( implode( '-', $this->get_button_settings() ) ); ?>"><?php echo esc_html( $button_text ); ?></button>
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
		<p id="wcpay-payment-request-button-separator" style="margin-top:1.5em;text-align:center;display:none;">&mdash; <?php esc_html_e( 'OR', 'woocommerce-payments' ); ?> &mdash;</p>
		<?php
	}

}

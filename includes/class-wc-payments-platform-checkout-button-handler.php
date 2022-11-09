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

		// TODO: I'm leaving a lot of these actions in place for now, but I'm not sure if they're all necessary.

		// phpcs:disable
		// add_action( 'template_redirect', [ $this, 'set_session' ] );
		// add_action( 'template_redirect', [ $this, 'handle_payment_request_redirect' ] );
		// add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_platform_checkout_button_html' ], -2 );
		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );

		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_platform_checkout_button_html' ], -2 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );

		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_platform_checkout_button_html' ], -2 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );

		add_action( 'before_woocommerce_pay_form', [ $this, 'display_pay_for_order_page_html' ], -2 );
		add_action( 'before_woocommerce_pay_form', [ $this, 'display_platform_checkout_button_separator_html' ], -1 );

		// add_action( 'wc_ajax_wcpay_get_cart_details', [ $this, 'ajax_get_cart_details' ] );
		// add_action( 'wc_ajax_wcpay_get_shipping_options', [ $this, 'ajax_get_shipping_options' ] );
		// add_action( 'wc_ajax_wcpay_update_shipping_method', [ $this, 'ajax_update_shipping_method' ] );
		// add_action( 'wc_ajax_wcpay_create_order', [ $this, 'ajax_create_order' ] );
		// add_action( 'wc_ajax_wcpay_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
		// add_action( 'wc_ajax_wcpay_get_selected_product_data', [ $this, 'ajax_get_selected_product_data' ] );
		// add_action( 'wc_ajax_wcpay_pay_for_order', [ $this, 'ajax_pay_for_order' ] );

		// add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
		// add_action( 'woocommerce_checkout_order_processed', [ $this, 'add_order_meta' ], 10, 2 );
		// add_filter( 'woocommerce_login_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );
		// add_filter( 'woocommerce_registration_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );

		// // Add a filter for the value of `wcpay_is_apple_pay_enabled`.
		// // This option does not get stored in the database at all, and this function
		// // will be used to calculate it whenever the option value is retrieved instead.
		// // It's used for displaying inbox notifications.
		// add_filter( 'pre_option_wcpay_is_apple_pay_enabled', [ $this, 'get_option_is_apple_pay_enabled' ], 10, 1 );
		// phpcs:enable
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
		// If account is not connected, then bail.
		if ( ! $this->account->is_stripe_connected( false ) ) {
			return false;
		}

		// If no SSL, bail.
		if ( ! $this->gateway->is_in_test_mode() && ! is_ssl() ) {
			Logger::log( 'Stripe Payment Request live mode requires SSL.' );
			return false;
		}

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

		// TODO: Add translations.
		$button_settings = $this->get_button_settings();
		$button_text     = 'default' !== $button_settings['type'] ? ucfirst( $button_settings['type'] ) . ' with WooPay' : 'WooPay';

		?>
		<style>
			.woopay-express-button {
				font-size: 20px;
				font-weight: normal;
				line-height: 42px;
				background: #fff;
				border: 1px solid #fff;
				color: #533582;
				width: 100%;
				border-radius: 4px;
				padding-top: 1px;
				padding-bottom: 1px;
			}
			.woopay-express-button[class*=dark] {
				background: #533582;
				color: #fff;
			}
			.woopay-express-button[class*=outline] {
				border-color: #533582;
			}
			.woopay-express-button[class*=medium] {
				font-size: 24px;
				line-height: 48px;
			}
			.woopay-express-button[class*=large] {
				font-size: 32px;
				line-height: 56px;
			}
		</style>
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

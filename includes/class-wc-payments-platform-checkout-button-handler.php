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

		$platform_express_button_params = [
			'button' => $this->get_button_settings(),
		];

		$script_src_url    = plugins_url( 'dist/platform-checkout-express-button.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/platform-checkout-express-button.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];

		wp_register_script( 'WCPAY_PLATFORM_CHECKOUT_EXPRESS_BUTTON', $script_src_url, $script_asset['dependencies'], WC_Payments::get_file_version( 'dist/platform-checkout-express-button.js' ), true );

		wp_localize_script( 'WCPAY_PLATFORM_CHECKOUT_EXPRESS_BUTTON', 'wcpayWooPayExpressParams', $platform_express_button_params );

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
		$button_type = $this->gateway->get_option( 'platform_checkout_button_type', 'default' );
		return [
			'type'   => $button_type,
			'text'   => ucfirst( $button_type ),
			'theme'  => $this->gateway->get_option( 'platform_checkout_button_theme', 'dark' ),
			'height' => $this->get_button_height(),
			'size'   => $this->gateway->get_option( 'platform_checkout_button_size' ),
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

		$button_settings = $this->get_button_settings();
		$button_text     = 'default' !== $button_settings['type'] ? sprintf(
			// Translators: %s is the name of the button action.
			__( '%s with WooPay', 'woocommerce-payments' ),
			ucfirst( $button_settings['type'] )
		) : 'WooPay';

		?>
		<div id="wcpay-payment-request-wrapper" style="clear:both;padding-top:1.5em;">
			<div id="wcpay-platform-checkout-button">
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

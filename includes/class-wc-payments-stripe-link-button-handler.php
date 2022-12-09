<?php
/**
 * Class WC_Payments_Stripe_Link_Button_Handler
 * Adds support for Stripe Link as a  Payment Request API buttons.
 *
 * Adapted from WC_Payments_Payment_Request_Button_Handler
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Payments_Stripe_Link_Button_Handler class.
 */
class WC_Payments_Stripe_Link_Button_Handler {
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
	 * Initialise hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Checks if WCPay is enabled.
		if ( ! $this->gateway->is_enabled() ) {
			return;
		}

		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_button_html' ], -2 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_button_separator_html' ], -1 );

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );
	}

	/**
	 * Load public scripts and styles.
	 *
	 * @return void
	 */
	public function scripts() {
		if ( ! WC_Payments_Features::is_link_enabled() ) {
			return;
		}

		if ( ! $this->is_checkout() ) {
			return;
		}

		$params = WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config();

		$script_src_url    = plugins_url( 'dist/stripe-link-button.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/stripe-link-button.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];

		wp_register_script( 'WCPAY_STRIPE_LINK_BUTTON', $script_src_url, $script_asset['dependencies'], WC_Payments::get_file_version( 'dist/stripe-link-button.js' ), true );
		wp_localize_script( 'WCPAY_STRIPE_LINK_BUTTON', 'wcpayStripeLinkParams', $params );
		wp_set_script_translations( 'WCPAY_STRIPE_LINK_BUTTON', 'woocommerce-payments' );
		wp_enqueue_script( 'WCPAY_STRIPE_LINK_BUTTON' );
		wp_register_style(
			'WCPAY_STRIPE_LINK',
			plugins_url( 'dist/stripe_link.css', WCPAY_PLUGIN_FILE ),
			[],
			WCPAY_VERSION_NUMBER
		);
		wp_enqueue_style( 'WCPAY_STRIPE_LINK' );
	}

	/**
	 * Display the payment request button.
	 */
	public function display_button_html() {
		if ( ! WC_Payments_Features::is_link_enabled() ) {
			return;
		}

		if ( ! $this->is_checkout() ) {
			return;
		}

		?>
		<div id="wcpay-payment-request-wrapper" style="clear:both;padding-top:1.5em;">
			<div id="wcpay-stripe-link-button"></div>
		</div>
		<?php
	}

	/**
	 * Display payment request button separator.
	 */
	public function display_button_separator_html() {
		if ( ! WC_Payments_Features::is_link_enabled() ) {
			return;
		}

		if ( ! $this->is_checkout() ) {
			return;
		}
		?>
		<p id="wcpay-payment-request-button-separator" style="margin-top:1.5em;text-align:center;">&mdash; <?php esc_html_e( 'OR', 'woocommerce-payments' ); ?> &mdash;</p>
		<?php
	}


	/**
	 * Checks if this is the checkout page or content contains a cart block.
	 *
	 * @return boolean
	 */
	public function is_checkout() {
		return is_checkout() || has_block( 'woocommerce/checkout' );
	}

}

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

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
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
			<div id="wcpay-stripe-link-button-wrapper">
				<button disabled><span id="logo"></span></button>
			</div>
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

<?php
/**
 * Class WC_Payments_Express_Checkout_Button_Display_Handler
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Payments_Express_Checkout_Button_Display_Handler
 */
class WC_Payments_Express_Checkout_Button_Display_Handler {

	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Instance of WC_Payments_Payment_Request_Button_Handler, created in init function
	 *
	 * @var WC_Payments_Payment_Request_Button_Handler
	 */
	private $payment_request_button_handler;

	/**
	 * Instance of WC_Payments_WooPay_Button_Handler, created in init function
	 *
	 * @var WC_Payments_WooPay_Button_Handler
	 */
	private $platform_checkout_button_handler;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payment_Gateway_WCPay                   $gateway WCPay gateway.
	 * @param WC_Payments_Payment_Request_Button_Handler $payment_request_button_handler Payment request button handler.
	 * @param WC_Payments_WooPay_Button_Handler          $platform_checkout_button_handler Platform checkout button handler.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway, WC_Payments_Payment_Request_Button_Handler $payment_request_button_handler, WC_Payments_WooPay_Button_Handler $platform_checkout_button_handler ) {
		$this->gateway                          = $gateway;
		$this->payment_request_button_handler   = $payment_request_button_handler;
		$this->platform_checkout_button_handler = $platform_checkout_button_handler;

		$this->platform_checkout_button_handler->init();
		$this->payment_request_button_handler->init();

		$is_woopay_enabled          = WC_Payments_Features::is_woopay_enabled();
		$is_payment_request_enabled = 'yes' === $this->gateway->get_option( 'payment_request' );

		if ( $is_woopay_enabled || $is_payment_request_enabled ) {
			add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_express_checkout_buttons' ], 1 );
			add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_express_checkout_buttons' ], 1 );
			add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_express_checkout_buttons' ], 1 );

			if ( $is_payment_request_enabled ) {
				// Load separator on the Pay for Order page.
				add_action( 'before_woocommerce_pay_form', [ $this, 'display_express_checkout_buttons' ], 1 );
			}
		}
	}

	/**
	 * Display express checkout separator only when express buttons are displayed.
	 *
	 * @return void
	 */
	public function display_express_checkout_separator_if_necessary() {
		$should_show_woopay          = $this->platform_checkout_button_handler->should_show_woopay_button();
		$should_show_payment_request = $this->payment_request_button_handler->should_show_payment_request_button();
		$should_hide                 = $should_show_payment_request && ! $should_show_woopay;
		if ( $should_show_woopay || $should_show_payment_request ) {
			?>
			<p id="wcpay-payment-request-button-separator" style="margin-top:1.5em;text-align:center;<?php echo $should_hide ? 'display:none;' : ''; ?>">&mdash; <?php esc_html_e( 'OR', 'woocommerce-payments' ); ?> &mdash;</p>
			<?php
		}
	}

	/**
	 * Display express checkout separator only when express buttons are displayed.
	 *
	 * @return void
	 */
	public function display_express_checkout_buttons() {
		$this->platform_checkout_button_handler->display_woopay_button_html();
		$this->payment_request_button_handler->display_payment_request_button_html();
		$this->display_express_checkout_separator_if_necessary();
	}

	/**
	 * Check if WooPay is enabled
	 *
	 * @return bool
	 */
	public function is_woopay_enabled() {
		return $this->platform_checkout_button_handler->is_woopay_enabled();
	}
}

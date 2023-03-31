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
	 * Platform_Checkout_Utilities instance.
	 *
	 * @var Platform_Checkout_Utilities
	 */
	private $platform_checkout_utilities;

	/**
	 * Instance of WC_Payments_Payment_Request_Button_Handler, created in init function
	 *
	 * @var WC_Payments_Payment_Request_Button_Handler
	 */
	private static $payment_request_button_handler;

	/**
	 * Instance of WC_Payments_Platform_Checkout_Button_Handler, created in init function
	 *
	 * @var WC_Payments_Platform_Checkout_Button_Handler
	 */
	private static $platform_checkout_button_handler;

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payments_Account         $account Account information.
	 * @param WC_Payment_Gateway_WCPay    $gateway WCPay gateway.
	 * @param Platform_Checkout_Utilities $platform_checkout_utilities Platform checkout utilities.
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $gateway, $platform_checkout_utilities ) {
		$this->account                     = $account;
		$this->gateway                     = $gateway;
		$this->platform_checkout_utilities = $platform_checkout_utilities;

		add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Initialize hooks.
	 *
	 * @return  void
	 */
	public function init() {
		self::$payment_request_button_handler   = new WC_Payments_Payment_Request_Button_Handler( $this->account, $this->gateway );
		self::$platform_checkout_button_handler = new WC_Payments_Platform_Checkout_Button_Handler( $this->account, $this->gateway, $this->platform_checkout_utilities );

		$is_woopay_express_checkout_enabled = WC_Payments_Features::is_woopay_express_checkout_enabled();

		if ( $is_woopay_express_checkout_enabled || $this->gateway->get_option( 'payment_request' ) ) {
			add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_express_checkout_buttons' ], 1 );
			add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_express_checkout_buttons' ], 1 );
			add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_express_checkout_buttons' ], 1 );

			if ( $this->gateway->get_option( 'payment_request' ) ) {
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
		$woopay          = self::$platform_checkout_button_handler->display_platform_checkout_button_html();
		$payment_request = self::$payment_request_button_handler->display_payment_request_button_html();
		$should_hide     = $payment_request && ! $woopay;
		if ( $woopay || $payment_request ) {
			?>
			<p id="wcpay-payment-request-button-separator" style="margin-top:1.5em;text-align:center;<?php echo $should_hide ? 'display:none;' : ''; ?>">&mdash; <?php esc_html_e( 'ORhey', 'woocommerce-payments' ); ?> &mdash;</p>
			<?php
		}
	}

	/**
	 * Display express checkout separator only when express buttons are displayed.
	 *
	 * @return void
	 */
	public function display_express_checkout_buttons() {
		self::$payment_request_button_handler->display_payment_request_button_html();
		self::$platform_checkout_button_handler->display_platform_checkout_button_html();
		self::display_express_checkout_separator_if_necessary();
	}

}

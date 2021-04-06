<?php
/**
 * Class WC_Payment_Gateway_WCPay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Exceptions\{ Add_Payment_Method_Exception, Process_Payment_Exception, Intent_Authentication_Exception, API_Exception, Connection_Exception };
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Payment_Initiated_By;
use WCPay\Constants\Payment_Capture_Type;
use WCPay\Tracker;

/**
 * Gateway class for WooCommerce Payments
 */
class WC_Payment_Gateway_Giropay extends WC_Payment_Gateway {

	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments_giropay';

	/**
	 * Set of parameters to build the URL to the gateway's settings page.
	 *
	 * @var string[]
	 */
	private static $settings_url_params = [
		'page'    => 'wc-settings',
		'tab'     => 'checkout',
		'section' => self::GATEWAY_ID,
	];

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payments_Customer instance for working with customer information
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * WC_Payments_Token instance for working with customer tokens
	 *
	 * @var WC_Payments_Token_Service
	 */
	private $token_service;

	/**
	 * WC_Payments_Action_Scheduler_Service instance for scheduling ActionScheduler jobs.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private $action_scheduler_service;

	/**
	 * WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                  - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service         - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service            - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service - Action Scheduler service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Token_Service $token_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service
	) {
		$this->payments_api_client      = $payments_api_client;
		$this->account                  = $account;
		$this->customer_service         = $customer_service;
		$this->token_service            = $token_service;
		$this->action_scheduler_service = $action_scheduler_service;

		$this->id                 = self::GATEWAY_ID;
		$this->icon               = ''; // TODO: icon.
		$this->has_fields         = false;
		$this->method_title       = __( 'WooCommerce Payments - Giropay', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via Giropay.', 'woocommerce-payments' );
		$this->title              = __( 'Giropay', 'woocommerce-payments' );
		$this->description        = __( 'Use Giropay to make purchase', 'woocommerce-payments' );
		$this->supports           = [
			'products',
			'refunds',
		];

		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
	}

	/**
	 * Registers all scripts, necessary for the gateway.
	 */
	public function register_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		// Register Stripe's JavaScript using the same ID as the Stripe Gateway plugin. This prevents this JS being
		// loaded twice in the event a site has both plugins enabled. We still run the risk of different plugins
		// loading different versions however. If Stripe release a v4 of their JavaScript, we could consider
		// changing the ID to stripe_v4. This would allow older plugins to keep using v3 while we used any new
		// feature in v4. Stripe have allowed loading of 2 different versions of stripe.js in the past (
		// https://stripe.com/docs/stripe-js/elements/migrating).
		wp_register_script(
			'stripe',
			'https://js.stripe.com/v3/',
			[],
			'3.0',
			true
		);

		wp_register_script(
			'wcpay-checkout-giropay',
			plugins_url( 'dist/giropay_checkout.js', WCPAY_PLUGIN_FILE ),
			[ 'stripe', 'wc-checkout' ],
			WC_Payments::get_file_version( 'dist/checkout.js' ),
			true
		);

		wp_enqueue_script( 'wcpay-checkout-giropay' );
	}

	/**
	 * Generates the configuration values, needed for payment fields.
	 *
	 * Isolated as a separate method in order to be avaiable both
	 * during the classic checkout, as well as the checkout block.
	 *
	 * @return array
	 */
	public function get_payment_fields_js_config() {
		return [
			'publishableKey'         => $this->account->get_publishable_key( $this->is_in_test_mode() ),
			'accountId'              => $this->account->get_stripe_account_id(),
			'ajaxUrl'                => admin_url( 'admin-ajax.php' ),
			'updateOrderStatusNonce' => wp_create_nonce( 'wcpay_update_order_status_nonce' ),
			'createSetupIntentNonce' => wp_create_nonce( 'wcpay_create_setup_intent_nonce' ),
			'genericErrorMessage'    => __( 'There was a problem processing the payment. Please check your email inbox and refresh the page to try again.', 'woocommerce-payments' ),
			'fraudServices'          => $this->account->get_fraud_services_config(),
			'features'               => $this->supports,
		];
	}

	/**
	 * Get payment capture type from WCPay settings.
	 *
	 * @return Payment_Capture_Type MANUAL or AUTOMATIC depending on the settings.
	 */
	protected function get_capture_type() {
		return 'yes' === $this->get_option( 'manual_capture' ) ? Payment_Capture_Type::MANUAL() : Payment_Capture_Type::AUTOMATIC();
	}

	/**
	 * Prepares the payment information object.
	 *
	 * @param WC_Order $order The order whose payment will be processed.
	 * @return Payment_Information An object, which describes the payment.
	 */
	protected function prepare_payment_information( $order ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_information = Payment_Information::from_payment_request( $_POST, $order, Payment_Type::SINGLE(), Payment_Initiated_By::CUSTOMER(), $this->get_capture_type() );

		// During normal orders the payment method is saved when the customer enters a new one and choses to save it.
		if ( ! empty( $_POST[ 'wc-' . self::GATEWAY_ID . '-new-payment-method' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$payment_information->must_save_payment_method();
		}

		return $payment_information;
	}

	/**
	 * Process the payment for a given order.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		return [
			'result'   => 'fail',
			'redirect' => '',
		];
	}
}

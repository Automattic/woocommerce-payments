<?php
/**
 * Class Standard_Payment_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Order;
use WC_Payments;
use WC_Payments_Utils;
use WC_Payment_Gateway_WCPay;
use WC_Payments_API_Client;
use WC_Payments_API_Intention;
use WCPay\Constants\Payment_Intent_Status;
use WCPay\Payment_Information;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Payment_Method\Saved_Payment_Method;

/**
 * Performs a standard payment with a positive amount.
 */
class Standard_Payment_Step extends Abstract_Step {
	/**
	 * The active gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * The server API client.
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * WC_Payments_Order_Service instance
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * Instantiates the step.
	 */
	public function __construct() {
		// @todo: Change this with proper dependencies.
		$this->gateway             = WC_Payments::get_gateway();
		$this->payments_api_client = WC_Payments::get_payments_api_client();
		$this->order_service       = WC_Payments::$order_service;
	}

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'standard-payment';
	}

	/**
	 * Checks if the step is applicable.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return is_a( $payment, Order_Payment::class );
	}

	/**
	 * Verifies the amount during the processing step.
	 * Tries catching the error without reaching the API.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return;
		}

		$order  = $payment->get_order();
		$amount = $order->get_total();

		// @todo: There are additional API parameters for subscription renewals.
		// @todo: Maybe the mandate params should not live in the gateway anymore.
		$additional_api_parameters = $this->gateway->get_mandate_params_for_order( $order );

		// @todo: Maybe this check could be contained within the payment method object?
		$additional_api_parameters['is_platform_payment_method'] = $this->gateway->is_platform_payment_method( $payment->get_payment_method() instanceof Saved_Payment_Method );

		// This meta is only set by WooPay.
		// We want to handle the intention creation differently when there are subscriptions.
		// We're using simple products on WooPay so the current logic for WCPay subscriptions won't work there.
		if ( '1' === $order->get_meta( '_woopay_has_subscription' ) ) {
			$additional_api_parameters['woopay_has_subscription'] = 'true';
		}

		$payment_information = Payment_Information::from_payment_request( [] );

		// Create intention, try to confirm it & capture the charge (if 3DS is not required).
		$intent = $this->payments_api_client->create_and_confirm_intention(
			WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ),
			strtolower( $order->get_currency() ),
			$payment->get_payment_method()->get_id(),
			$payment->get_var( 'customer_id' ),
			$payment->is( Payment::MANUAL_CAPTURE ),
			$payment->is( Payment::SAVE_PAYMENT_METHOD_TO_STORE ),
			$payment->is( Payment::SAVE_PAYMENT_METHOD_TO_PLATFORM ),
			$payment->get_var( 'metadata' ),
			$this->gateway->get_level3_data_from_order( $order ),
			$payment->is( Payment::MERCHANT_INITIATED ),
			$additional_api_parameters,
			$this->gateway->get_payment_method_ids_enabled_at_checkout( null, true ),
			$payment_information->get_cvc_confirmation(),
			$payment_information->get_fingerprint()
		);

		$intent_id     = $intent->get_id();
		$status        = $intent->get_status();
		$charge        = $intent->get_charge();
		$charge_id     = $charge ? $charge->get_id() : null;
		$client_secret = $intent->get_client_secret();
		$currency      = $intent->get_currency();
		$next_action   = $intent->get_next_action();
		$processing    = $intent->get_processing();
		// We update the payment method ID server side when it's necessary to clone payment methods,
		// for example when saving a payment method to a platform customer account. When this happens
		// we need to make sure the payment method on the order matches the one on the merchant account
		// not the one on the platform account. The payment method ID is updated on the order further
		// down.
		$payment_method = $intent->get_payment_method_id() ?? $payment_method;

		// Off-session payments, requiring action, make it impossible to continue.
		if ( $this->maybe_fail_if_action_is_required() ) {
			return;
		}

	}

	protected function maybe_fail_if_action_is_required( Order_Payment $payment, WC_Payments_API_Intention $intent ) {
		$status = $intent->get_status();

		if ( Payment_Intent_Status::REQUIRES_ACTION !== $status || ! $payment->is( Payment::MERCHANT_INITIATED ) ) {
			return false;
		}

		$order          = $payment->get_order();
		$payment_method = $intent->get_payment_method_id() ?? $payment->get_payment_method()->get_id();
		$intent_id      = $intent->get_id();
		$charge         = $intent->get_charge();
		$charge_id      = $charge ? $charge->get_id() : null;

		/**
		 * Allow 3rd-party to trigger some action if needed.
		 *
		 * @param WC_Order $order          Order that has a payment, requiring action.
		 * @param string   $intent_id      ID of the intent.
		 * @param string   $payment_method ID of the used payment method.
		 * @param string   $customer_id    ID of the customer.
		 * @param string   $charge_id      ID of the charge.
		 * @param string   $currency       Currency code.
		 */
		do_action( 'woocommerce_woocommerce_payments_payment_requires_action', $order, $intent_id, $payment_method, $payment->get_var( 'customer_id' ), $charge_id, strtolower( $order->get_currency() ) );

		// Mark the payment as failed.
		$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id );
		$payment->complete( [] ); // @todo: Subs don't require a response here.
	}
}

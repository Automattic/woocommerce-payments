<?php
/**
 * Class Standard_Payment_Strategy
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Strategy;

use WC_Payments;
use WC_Payments_Order_Service;
use WC_Payments_Account;
use WC_Payment_Gateway_WCPay;
use WC_Payments_API_Intention;
use WC_Payments_Features;
use WC_Payments_Utils;
use WCPay\Constants\Payment_Intent_Status;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Payment\Flags;
use WCPay\Payment\Payment;
use WCPay\Payment\State\Authentication_Required_State;
use WCPay\Payment\State\Processed_State;
use WCPay\Payment\State\Processing_Failed_State;
use WCPay\Payment_Process\Payment_Method\New_Payment_Method;
use WCPay\Payment\State\Payment_State;

/**
 * Represents a payment processing strategy.
 */
class Standard_Payment_Strategy extends Strategy {
	use Redirect_If_Action_Is_Required;

	/**
	 * The active gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * WC_Payments_Order_Service instance
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * WC_Payments_Account instance to get information about the account.
	 *
	 * @var WC_Payments_Account
	 */
	protected $account;

	/**
	 * Instantiates the step.
	 */
	public function __construct() {
		// @todo: Change this with proper dependencies.
		$this->gateway       = WC_Payments::get_gateway();
		$this->order_service = WC_Payments::$order_service;
		$this->account       = WC_Payments::get_account_service();
	}

	/**
	 * Processes the payment.
	 *
	 * @param  Payment $payment The payment to process.
	 * @return Payment_State    The next state of the payment.
	 */
	public function process( Payment $payment ): Payment_State {
		$intent = $this->request_intent_from_server( $payment );
		$status = $intent->get_status();

		// Store the intent in the payment, so it's available for follow-up steps.
		$payment->set_intent( $intent );

		// When WooPay is used, the PM is created on the platform account, and cloned to the merchant.
		if ( $intent->get_payment_method_id() !== $payment->get_payment_method()->get_id() ) {
			$payment_method = new New_Payment_Method( $intent->get_payment_method_id() );
			$payment->set_payment_method( $payment_method );
		}

		// The process cannot continue normally if the intent requires action.
		if ( Payment_Intent_Status::REQUIRES_ACTION === $status ) {
			if ( $payment->is( Flags::MERCHANT_INITIATED ) ) {
				// Off-session payments, requiring action, make it impossible to continue.
				$this->bail_if_action_is_required( $payment, $intent );
				return new Processing_Failed_State( $payment );
			} else {
				// Redirect if there is an action needed.
				$payment->set_response( $this->redirect_if_action_is_required( $payment, $intent ) );
				return new Authentication_Required_State( $payment );
			}
		}

		// This is the happy path.
		$payment->set_response(
			[
				'result'   => 'success',
				'redirect' => $this->gateway->get_return_url( $payment->get_order() ),
			]
		);
		return new Processed_State( $payment );
	}

	/**
	 * Prepares the request for creating an intention.
	 *
	 * @param Payment $payment The payment, which should be directed to the server.
	 * @return Create_And_Confirm_Intention
	 */
	protected function prepare_intent_request( Payment $payment ) {
		$order  = $payment->get_order();
		$amount = $order->get_total();

		// @todo: Bruh, this should really not be here.
		$upe_payment_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $upe_payment_method ) && 'woocommerce_payments' !== $upe_payment_method ) {
			$payment_methods = [ str_replace( 'woocommerce_payments_', '', $upe_payment_method ) ];
		} elseif ( WC_Payments_Features::is_upe_split_enabled() ) {
			$payment_methods = [ 'card' ];
		} else {
			$payment_methods = WC_Payments::get_gateway()->get_payment_method_ids_enabled_at_checkout( null, true );
		}

		$request = Create_And_Confirm_Intention::create();
		$request->set_amount( WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ) );
		$request->set_currency_code( strtolower( $order->get_currency() ) );
		$request->set_payment_method( $payment->get_payment_method()->get_id() );
		$request->set_customer( $payment->get_customer_id() );
		$request->set_capture_method( $payment->is( Flags::MANUAL_CAPTURE ) );
		$request->set_metadata( $payment->get_metadata() );
		$request->set_level3( $this->gateway->get_level3_data_from_order( $order ) );
		$request->set_off_session( $payment->is( Flags::MERCHANT_INITIATED ) );
		$request->set_payment_methods( $payment_methods );
		if ( $payment->is( Flags::SAVE_PAYMENT_METHOD_TO_STORE ) ) {
			$request->setup_future_usage();
		}

		// For customer-initiated payments, get some details from the request.
		if ( ! $payment->is( Flags::MERCHANT_INITIATED ) ) {
			$this->add_cvc_confirmation_to_request( $request );
			// $request->set_fingerprint( $payment->get_fingerprint() );
		}

		return $request;
	}

	/**
	 * Performs a create and confirm intention request, returning an intent object.
	 *
	 * @param Payment $payment The payment, which should be directed to the server.
	 * @return WC_Payments_API_Intention
	 */
	protected function request_intent_from_server( Payment $payment ) {
		// The request is prepared separately, can be modified by child classes.
		$request = $this->prepare_intent_request( $payment );

		/**
		 * Allows the request for creating and confirming intents to be modified.
		 *
		 * @param Payment $payment The payment, which is being processed.
		 */
		return $request->send( 'wcpay_create_and_confirm_intent_request', $payment );
	}

	/**
	 * Fails if an action is requried, but the customer is not present.
	 *
	 * @param Payment                   $payment A payment, being processed.
	 * @param WC_Payments_API_Intention $intent  The intention, returned from the server.
	 */
	protected function bail_if_action_is_required( Payment $payment, WC_Payments_API_Intention $intent ) {
		$order          = $payment->get_order();
		$status         = $intent->get_status();
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
		do_action( 'woocommerce_woocommerce_payments_payment_requires_action', $order, $intent_id, $payment_method, $payment->get_customer_id(), $charge_id, strtolower( $order->get_currency() ) );

		// Mark the payment as failed.
		$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id ); // @todo: Do this in the update order step!
	}

	/**
	 * Adds the CVC confirmation data from the POST request to the server request.
	 *
	 * @todo This should be done before invoking the payment process.
	 * @param Create_And_Confirm_Intention $request The request for creating and confirming an intention.
	 */
	protected function add_cvc_confirmation_to_request( Create_And_Confirm_Intention $request ) {
		$payment_method = $_POST['payment_method'] ?? null; // phpcs:ignore
		if ( null === $payment_method ) {
			return;
		}

		$cvc_request_key = 'wc-' . $payment_method . '-payment-cvc-confirmation';
		if (
			! isset( $_POST[ $cvc_request_key ] ) || // phpcs:ignore
			'new' === $_POST[ $cvc_request_key ] // phpcs:ignore
		) {
			return;
		}

		$request->set_cvc_confirmation( $_POST[ $cvc_request_key ] ); // phpcs:ignore
	}
}

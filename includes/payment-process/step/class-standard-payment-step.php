<?php
/**
 * Class Standard_Payment_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Order;
use WC_Payments;
use WC_Payment_Account;
use WC_Payments_Utils;
use WC_Payment_Gateway_WCPay;
use WC_Payments_API_Intention;
use WC_Payments_Features;
use WCPay\Constants\Payment_Intent_Status;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Payment_Method\New_Payment_Method;

/**
 * Performs a standard payment with a positive amount.
 */
class Standard_Payment_Step extends Abstract_Step {
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
	 * @var WC_Payment_Account
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
		if ( ! $payment instanceof Order_Payment ) {
			return false;
		}

		return $payment->get_order()->get_total() > 0;
	}

	/**
	 * Creates a new standard payment.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return;
		}

		$intent = $this->request_intent_from_server( $payment );
		$status = $intent->get_status();

		// Store the intent in the payment, so it's available for follow-up steps.
		$payment->set_var( 'intent', $intent );

		// @todo: Remove this in favor of a separate WooPay code path.
		if ( $intent->get_payment_method_id() !== $payment->get_payment_method()->get_id() ) {
			$payment_method = new New_Payment_Method( $intent->get_payment_method_id() );
			$payment->set_payment_method( $payment_method );
		}

		// The process cannot continue normally if the intent requires action.
		if ( Payment_Intent_Status::REQUIRES_ACTION === $status ) {
			if ( $payment->is( Payment::MERCHANT_INITIATED ) ) {
				// Off-session payments, requiring action, make it impossible to continue.
				$this->bail_if_action_is_required( $payment, $intent );
			} else {
				// Redirect if there is an action needed.
				$payment->complete( $this->redirect_if_action_is_required( $payment, $intent ) );
			}

			return;
		}

		// This is the happy path.
		$payment->complete(
			[
				'result'   => 'success',
				'redirect' => $this->gateway->get_return_url( $payment->get_order() ),
			]
		);
	}

	/**
	 * Performs a create and confirm intention request, returning an intent object.
	 *
	 * @param Order_Payment $payment The payment, which should be directed to the server.
	 * @return WC_Payments_API_Intention
	 */
	protected function request_intent_from_server( Order_Payment $payment ) {
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
		$request->set_customer( $payment->get_var( 'customer_id' ) );
		$request->set_capture_method( $payment->is( Payment::MANUAL_CAPTURE ) );
		$request->set_metadata( $payment->get_var( 'metadata' ) );
		$request->set_level3( $this->gateway->get_level3_data_from_order( $order ) );
		$request->set_off_session( $payment->is( Payment::MERCHANT_INITIATED ) );
		$request->set_payment_methods( $payment_methods );
		if ( $payment->is( Payment::SAVE_PAYMENT_METHOD_TO_STORE ) ) {
			$request->setup_future_usage();
		}

		// For customer-initiated payments, get some details from the request.
		if ( ! $payment->is( Payment::MERCHANT_INITIATED ) ) {
			$this->add_cvc_confirmation_to_request( $request );
			$this->add_fingerprint_to_request( $request );
		}

		/**
		 * Allows the request for creating and confirming intents to be modified.
		 *
		 * @param Order_Payment $payment The payment, which is being processed.
		 */
		return $request->send( 'wcpay_create_and_confirm_intent_request', $payment );
	}

	/**
	 * Fails if an action is requried, but the customer is not present.
	 *
	 * @param Order_Payment             $payment A payment, being processed.
	 * @param WC_Payments_API_Intention $intent  The intention, returned from the server.
	 */
	protected function bail_if_action_is_required( Order_Payment $payment, WC_Payments_API_Intention $intent ) {
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
		do_action( 'woocommerce_woocommerce_payments_payment_requires_action', $order, $intent_id, $payment_method, $payment->get_var( 'customer_id' ), $charge_id, strtolower( $order->get_currency() ) );

		// Mark the payment as failed.
		$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id );
		$payment->complete( [] ); // @todo: Subs don't require a response here.
	}

	/**
	 * Adds the CVC confirmation data from the POST request to the server request.
	 *
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

	/**
	 * Adds the fingerprint data from the POST request to the server request.
	 *
	 * @param Create_And_Confirm_Intention $request The request for creating and confirming an intention.
	 */
	protected function add_fingerprint_to_request( Create_And_Confirm_Intention $request ) {
		if ( empty( $_POST['wcpay-fingerprint'] ) ) { // phpcs:ignore
			return;
		}

		$normalized = wc_clean( $_POST['wcpay-fingerprint'] ); // phpcs:ignore
		$request->set_fingerprint( is_string( $normalized ) ? $normalized : '' );
	}
}

<?php
/**
 * Class Prepared_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use Exception;
use WC_Order;
use WC_Payment_Token_CC;
use WC_Payments_Utils;
use WC_Payments;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Payment\Duplicate_Payment_Prevention_Service;
use WCPay\Payment\Flags;
use WCPay\Payment\Payment;
use WCPay\Core\Exceptions\Amount_Too_Small_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Payment\Payment_Method\Payment_Method;
use WCPay\Payment\Payment_Method\Saved_Payment_Method;
use WCPay\Session_Rate_Limiter;

/**
 * Represents the payment in its prepared state,
 * where all details should be available, but the
 * payment might not be ok for processing yet.
 */
final class Prepared_State extends Payment_State {
	/**
	 * Session_Rate_Limiter instance for limiting failed transactions.
	 *
	 * @var Session_Rate_Limiter
	 */
	protected $failed_transaction_rate_limiter;

	/**
	 * Duplicate payment prevention service.
	 *
	 * @var Duplicate_Payment_Prevention_Service
	 */
	protected $duplicate_payment_prevention_service;

	/**
	 * Holds the active hateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Holds the fraud prevention service.
	 *
	 * @var Fraud_Prevention_Service
	 */
	protected $fraud_prevention_service;

	/**
	 * Instantiates the state and dependencies.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		parent::__construct( $payment );

		// @todo: Use a proper dependency here.
		$this->gateway                              = WC_Payments::get_gateway();
		$this->failed_transaction_rate_limiter      = WC_Payments::get_transaction_rate_limiter();
		$this->duplicate_payment_prevention_service = new Duplicate_Payment_Prevention_Service( WC_Payments::get_gateway(), WC()->session );
		$this->fraud_prevention_service             = Fraud_Prevention_Service::get_instance();
	}

	/**
	 * Verifies whether the payment can and should be processed.
	 *
	 * @param Payment_Method $payment_method         Payment method to use for the payment.
	 * @param string         $fraud_prevention_token Verification token to prevent fraud.
	 * @throws Exception In case the payment has already been verified.
	 */
	public function verify( Payment_Method $payment_method, string $fraud_prevention_token ) {
		try {
			// Store the payment method.
			$this->context->set_payment_method( $payment_method );

			// Verify.
			$this->check_transaction_limiter();
			$this->verify_fraud_token( $fraud_prevention_token );

			if ( $this->check_for_duplicate_order() ) {
				return; // State was already changed.
			}

			$this->store_order_in_session();

			if ( $this->check_attached_intent() ) {
				return; // State was already changed.
			}

			if ( $this->maybe_complete_without_payment() ) {
				return; // State was already changed.
			}

			$this->verify_minimum_amount();

			// If there are no exceptions or state changes, it's time to proceed.
			$this->context->switch_state( new Verified_State( $this->context ) );
		} catch ( Process_Payment_Exception $e ) {
			$this->context->switch_state( new Failed_Preparation_State( $this->context ) );
		}
	}

	/**
	 * While collecting data for a payment, checks if it should be prevented altogether.
	 *
	 * @param  string $token The token to verify.
	 * @throws Process_Payment_Exception In case the token could not be verified.
	 */
	public function verify_fraud_token( string $token ) {
		// Entry points should set a non-null token to enable checks.
		if (
			! is_string( $token )
			// The service might not be available if the WC session is not initialized.
			|| ! $this->fraud_prevention_service
			// We need the service to be not just there, but enabled.
			|| ! $this->fraud_prevention_service->is_enabled()
		) {
			return;
		}

		if ( $this->fraud_prevention_service->verify_token( $token ) ) {
			return;
		}

		throw new Process_Payment_Exception(
			__( 'We are not able to process this payment. Please refresh the page and try again.', 'woocommerce-payments' ),
			'fraud_prevention_enabled'
		);
	}

	/**
	 * Checks the transactions limited.
	 *
	 * @throws Exception In case the limiter has been hit.
	 */
	public function check_transaction_limiter() {
		// Check the failed transaction rate limit.
		if ( $this->failed_transaction_rate_limiter->is_limited() ) {
			throw new Exception( __( 'Your payment was not processed.', 'woocommerce-payments' ) );
		}
	}

	/**
	 * Stores the current order in session in order to
	 * detect duplicates upon futher attempts.
	 */
	public function store_order_in_session() {
		$order_id = $this->context->get_order()->get_id();
		$this->duplicate_payment_prevention_service->maybe_update_session_processing_order( $order_id );
	}

	/**
	 * Checks if a similar order was already paid for.
	 *
	 * @return bool|null True if there was a duplicate, null if not.
	 */
	public function check_for_duplicate_order() {
		$order = $this->context->get_order();

		// Check if there was another order, and complete the process already.
		$check_session_order = $this->duplicate_payment_prevention_service->check_against_session_processing_order( $order );
		if ( is_array( $check_session_order ) ) {
			$this->context->switch_state( new Completed_State( $this->context ) );
			return true;
		}
	}

	/**
	 * Checks whether an order already has a successful intent attached.
	 *
	 * @return bool|null True if the state was switched, null otherwise.
	 */
	public function check_attached_intent() {
		$order = $this->context->get_order();

		// Check if the order has a successful intent attached.
		$existing_intent = $this->duplicate_payment_prevention_service->check_attached_intent_success( $order );
		if ( is_null( $existing_intent ) ) {
			return;
		}

		// Generate the response, which would otherwise be generated while processing.
		$response = $this->duplicate_payment_prevention_service->get_successful_intent_response( $order );

		// Store the intent and response within the payment, used in follow-up states.
		$this->context->set_intent( $existing_intent );
		$this->context->set_response( $response );

		return;
		// Transition to the processed state to let the rest of the process use the intent.
		$this->context->switch_state( new Processed_State( $this->context ) );

		return true;
	}

	/**
	 * Potentially completes the process without an actual payment.
	 *
	 * @return bool An indicator whether the payment was completed.
	 */
	public function maybe_complete_without_payment() {
		$order                        = $this->context->get_order();
		$payment_method               = $this->context->get_payment_method();
		$save_payment_method_to_store = $this->context->is( Flags::SAVE_PAYMENT_METHOD_TO_STORE );
		$payment_needed               = $order->get_total() > 0;

		if ( ! apply_filters( 'wcpay_confirm_without_payment_intent', ! $payment_needed && ! $save_payment_method_to_store ) ) {
			return false;
		}

		$order->payment_complete();
		$order->set_payment_method_title( __( 'Credit / Debit Card', 'woocommerce-payments' ) );

		if ( $payment_method instanceof Saved_Payment_Method ) {
			// We need to make sure the saved payment method is saved to the order so we can
			// charge the payment method for a future payment.
			$this->gateway->add_token_to_order( $order, $payment_method->get_token() );

			if ( $this->context->is( Flags::CHANGING_SUBSCRIPTION_PAYMENT_METHOD ) ) {
				$this->add_subscription_payment_method_change_note( $order, $payment_method );
			}
		}

		$result = [
			'result'   => 'success',
			'redirect' => $this->gateway->get_return_url( $order ),
		];

		$this->context->set_response( $result );
		$this->context->switch_state( new Completed_Without_Payment_State( $this->context ) );

		return true;
	}

	/**
	 * Adds a note whenever the payment method for a subscription is changed.
	 *
	 * @todo This doesn't belong here...
	 *
	 * @param WC_Order             $order          The order, where the note should be added.
	 * @param Saved_Payment_Method $payment_method The new payment method.
	 */
	protected function add_subscription_payment_method_change_note( WC_Order $order, Saved_Payment_Method $payment_method ) {
		$token = $payment_method->get_token();
		$last4 = $token instanceof WC_Payment_Token_CC ? $token->get_last4() : '----';

		$note = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the last 4 digit of the credit card */
				__( 'Payment method is changed to: <strong>Credit card ending in %1$s</strong>.', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
				]
			),
			$last4
		);
		$order->add_order_note( $note );

		do_action( 'woocommerce_payments_changed_subscription_payment_method', $order, $token );
	}

	/**
	 * Verifies the minimum amount, required for payment.
	 *
	 * @throws Amount_Too_Small_Exception In case the amount is insufficient.
	 */
	public function verify_minimum_amount() {
		$order          = $this->context->get_order();
		$currency       = strtolower( $order->get_currency() );
		$minimum_amount = WC_Payments_Utils::get_cached_minimum_amount( $currency );
		if ( is_null( $minimum_amount ) ) {
			// If there is no cached minimum amount already, there is nothing to catch.
			return;
		}

		$amount           = $order->get_total();
		$converted_amount = WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() );

		if ( $minimum_amount > $converted_amount ) {
			throw new Amount_Too_Small_Exception( 'Amount too small', $minimum_amount, $currency, [], 400 );
		}
	}
}

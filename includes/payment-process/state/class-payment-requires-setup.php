<?php
/**
 * Payment_Requires_Setup class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\State;

use WC_Payments;
use WC_Payments_Utils;
use WCPay\Core\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Payment_Process\Exception\Existing_Processing_Order_Exception;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment_Method\Payment_Method_Factory;
use WCPay\Payment_Process\Storage\Payment_Storage;
use WCPay\Service\Duplicate_Order_Prevention_Service;
use WCPay\Session_Rate_Limiter;

/**
 * Represents payments in a state, which requires setup.
 */
class Payment_Requires_Setup extends Order_Payment {
	/**
	 * Holds the active hateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Holds the WC session.
	 *
	 * @var WC_Session|WC_Session_Handler
	 */
	protected $session;

	/**
	 * Session_Rate_Limiter instance for limiting failed transactions.
	 *
	 * @var Session_Rate_Limiter
	 */
	protected $failed_transaction_rate_limiter;

	/**
	 * Holds the fraud prevention service.
	 *
	 * @var Fraud_Prevention_Service
	 */
	protected $fraud_prevention_service;

	/**
	 * Service for processing orders in the session.1
	 *
	 * @var Duplicate_Order_Prevention_Service
	 */
	protected $duplicate_order_prevention_service;

	/**
	 * Instantiates the state, and sets up all required dependencies.
	 *
	 * @param Payment_Storage        $storage                Storage to load/save payments from/to.
	 * @param Payment_Method_Factory $payment_method_factory Factory for payment methods.
	 */
	public function __construct(
		Payment_Storage $storage,
		Payment_Method_Factory $payment_method_factory
	) {
		parent::__construct( $storage, $payment_method_factory );

		// Load local dependencies. This should be done better.
		$this->failed_transaction_rate_limiter    = WC_Payments::get_transaction_rate_limiter();
		$this->fraud_prevention_service           = Fraud_Prevention_Service::get_instance();
		$this->gateway                            = WC_Payments::get_gateway();
		$this->session                            = WC()->session;
		$this->duplicate_order_prevention_service = new Duplicate_Order_Prevention_Service();
	}

	/**
	 * Verifies that the payment can be process.
	 *
	 * @return Payment_Verified
	 *
	 * @throws Process_Payment_Exception If the transaction limiter has been hit already.
	 * @throws Existing_Processing_Order_Exception In case a similar order was already paid.
	 * @throws Amount_Too_Small_Exception Whenever the amount is insufficient for a transaction.
	 */
	public function verify() {
		$this->check_transaction_limiter();

		// The service might not be available if the WC session is not initialized, or disabled.
		if ( $this->fraud_prevention_service && $this->fraud_prevention_service->is_enabled() ) {
			$this->verify_fraud_token();
		}

		$this->check_session_against_processing_order();
		$this->verify_minimum_amount();

		return new Payment_Verified( $this->payment_storage, $this->payment_method_factory );
	}

	/**
	 * Checks the transactions rate limiter.
	 *
	 * @throws Process_Payment_Exception In case the limiter has already been hit.
	 */
	protected function check_transaction_limiter() {
		if ( $this->failed_transaction_rate_limiter->is_limited() ) {
			throw new Process_Payment_Exception(
				__( 'Your payment was not processed.', 'woocommerce-payments' ),
				'transaction_limiter_reached'
			);
		}
	}

	/**
	 * If there is a fraud token, verifies it.
	 *
	 * @throws Process_Payment_Exception In case the verification failed.
	 */
	protected function verify_fraud_token() {
		// Entry points should set a non-null token to the fraud token check.
		if ( ! is_string( $this->get_fraud_prevention_token() ) ) {
			return;
		}

		// Finally, verify the token.
		$token = $this->get_fraud_prevention_token();
		if ( ! $this->fraud_prevention_service->verify_token( $token ) ) {
			throw new Process_Payment_Exception(
				__( 'We are not able to process this payment. Please refresh the page and try again.', 'woocommerce-payments' ),
				'fraud_prevention_enabled'
			);
		}
	}

	/**
	 * Checks if the session contains an order that has already been paid.
	 *
	 * @throws Existing_Processing_Order_Exception
	 */
	protected function check_session_against_processing_order() {
		$order = $this->get_order();

		// If there is another order, interrupt the process in order to redirect to it.
		$redirect_array = $this->duplicate_order_prevention_service->check_against_session_processing_order( $order );
		if ( is_array( $redirect_array ) ) {
			$exception = new Existing_Processing_Order_Exception(
				__( 'An order with the same contents already exists, and was paid.', 'woocommerce-payments' ),
				'existing_processing_order'
			);
			$exception->set_redirect( $redirect_array );
			throw $exception;
		}

		// Store the current order in session.
		$this->duplicate_order_prevention_service->maybe_update_session_processing_order( $order->get_id() );
	}

	/**
	 * Verifies the amount before processing the order.
	 *
	 * Tries catching the error without reaching the API. If the amount has not been
	 * cached yet, the same exception will be thrown at the processing stage.
	 *
	 * @throws Amount_Too_Small_Exception
	 */
	protected function verify_minimum_amount() {
		$order          = $this->get_order();
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

<?php
/**
 * Class Verify_Fraud_Token_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Payment_Process\Payment;

/**
 * Checks if a fraud prevention token is present, and verifies it.
 */
final class Verify_Fraud_Token_Step extends Abstract_Step {
	/**
	 * Holds the fraud prevention service.
	 *
	 * @var Fraud_Prevention_Service
	 */
	protected $fraud_prevention_service;

	/**
	 * Gathers all required dependencies.
	 */
	public function __construct() {
		$this->fraud_prevention_service = Fraud_Prevention_Service::get_instance();
	}

	/**
	 * This step only works when WC's session is present.
	 *
	 * @param Payment $payment The payment process.
	 */
	public function is_applicable( Payment $payment ) {
		// Entry points should set a non-null token to enable checks.
		return is_string( $payment->get_fraud_prevention_token() );
	}

	/**
	 * While collecting data for a payment, checks if it should be prevented altogether.
	 *
	 * @param Payment $payment Payment proccess.
	 * @throws Process_Payment_Exception In case the token could not be verified.
	 */
	public function action( Payment $payment ) {
		if (
			// The service might not be available if the WC session is not initialized.
			! $this->fraud_prevention_service

			// We need the service to be not just there, but enabled.
			|| ! $this->fraud_prevention_service->is_enabled()
		) {
			return;
		}

		// Finally, verify the token.
		$token = $payment->get_fraud_prevention_token();
		if ( ! $this->fraud_prevention_service->verify_token( $token ) ) {
			throw new Process_Payment_Exception(
				__( 'We are not able to process this payment. Please refresh the page and try again.', 'woocommerce-payments' ),
				'fraud_prevention_enabled'
			);
		}
	}
}

<?php
/**
 * Class Payment_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use Exception;
use WCPay\Payment\Payment;
use WCPay\Payment\Payment_Method\Payment_Method;
use WCPay\Payment\Strategy\Strategy;

/**
 * Base class for all payment states.
 */
abstract class Payment_State {
	/**
	 * Holds the context of the state.
	 *
	 * @var Payment
	 */
	protected $context;

	/**
	 * Instantiates the state.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		$this->context = $payment;
	}

	/**
	 * Prepares all required payment details.
	 *
	 * @throws Exception In case the payment has already been prepared.
	 */
	public function prepare() {
		throw new Exception( 'This payment has already been prepared.' );
	}

	/**
	 * Verifies the payment, transitioning to a verified state.
	 *
	 * @param Payment_Method $payment_method         Payment method to use for the payment.
	 * @param string         $fraud_prevention_token Verification token to prevent fraud.
	 * @throws Exception In case the payment has already been verified.
	 */
	public function verify( Payment_Method $payment_method, string $fraud_prevention_token ) {
		throw new Exception( 'This payment has already been verified.' );
	}

	/**
	 * Processes the payment. Will do soon.
	 *
	 * @param Strategy $strategy The strategy to use to process the payment.
	 * @throws Exception In case the payment is either not ready to be processed, or already processed.
	 */
	public function process( Strategy $strategy ) {
		throw new Exception( 'The payment is not ready to be processed.' );
	}

	/**
	 * Loads the intent after authentication.
	 *
	 * @param string $intent_id The provided intent ID.
	 * @throws Exception In case the payment is either not ready to be receive an intent.
	 */
	public function load_intent_after_authentication( string $intent_id ) {
		throw new Exception( 'The payment is not awaiting authentication' );
	}

	/**
	 * Completes the process.
	 *
	 * @throws Exception In case the payment is either not ready to be processed, or already processed.
	 */
	public function complete() {
		throw new Exception( 'The payment is not ready to be completed.' );
	}

	/**
	 * Either retrieves the existing intent, or creates a new one.
	 *
	 * @param string[] $payment_method_types The allowed payment methods.
	 * @throws Exception In case the method is not available.
	 */
	public function get_or_create_intent( array $payment_method_types ) {
		throw new Exception( 'This method is only supported when creating UPE intents.' );
	}

	/**
	 * Updates an intent once an order is available.
	 *
	 * @throws Exception In case the method is not available.
	 */
	public function update_intent_with_order() {
		throw new Exception( 'This method is only supported when updating with UPE intents.' );
	}

	/**
	 * Loads an intent into the payment process once it has been confirmed,
	 * and transitions to the correct follow-up state.
	 *
	 * @param string $intent_id The ID of the intent to load.
	 */
	public function load_intent_after_confirmation( string $intent_id ) {
		throw new Exception( 'Incorrect payment state.' );
	}

	/**
	 * Indicates if the state should interrupt the processing loop.
	 * Overwrite in states inc ase they are final, and should interrupt the process.
	 *
	 * @return bool
	 */
	public function is_processing_finished() {
		return false;
	}
}

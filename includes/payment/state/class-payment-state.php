<?php
/**
 * Class Payment_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use Exception;
use WCPay\Payment\Payment;
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
	 * @throws Exception In case the payment has already been verified.
	 */
	public function verify() {
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
	 * Indicates if the state should interrupt the processing loop.
	 * Overwrite in states inc ase they are final, and should interrupt the process.
	 *
	 * @return bool
	 */
	public function is_processing_finished() {
		return false;
	}
}

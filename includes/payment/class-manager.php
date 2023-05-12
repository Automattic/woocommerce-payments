<?php
/**
 * Class Manager
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

use Exception;
use WC_Order;
use WCPay\Payment\Strategy\Strategy;
use WCPay\Payment\Storage\File_Storage;
use WCPay\Payment\Payment_Method\Payment_Method_Factory;
use WCPay\Payment\State\{ Initial_State, Intent_Without_Order_State, Prepared_State, Processed_State, Verified_State };

/**
 * Orchestrates the payment process.
 */
class Manager {
	/**
	 * Storage for payments.
	 *
	 * @param File_Storage
	 */
	protected $storage;

	/**
	 * Payment method factory for the payment process.
	 *
	 * @var Payment_Method_Factory
	 */
	protected $payment_method_factory;

	/**
	 * Instantiates the manager.
	 */
	public function __construct() {
		$this->storage                = new File_Storage();
		$this->payment_method_factory = new Payment_Method_Factory();
	}

	/**
	 * Instantiates a new payment.
	 *
	 * @param WC_Order $order Order to use for the payment (Optional, but recommended).
	 * @return Payment        Newly created payment.
	 */
	public function instantiate_payment( WC_Order $order = null ) {
		$payment = $this->create_payment_object();
		if ( $order ) {
			$payment->set_order( $order );
		}
		return $payment;
	}

	/**
	 * Loads the payment for an order.
	 *
	 * @param WC_Order $order Order to use for the payment.
	 * @return Payment        Loaded payment.
	 */
	public function load_payment( WC_Order $order ) {
		$payment = $this->create_payment_object();
		$payment->set_order( $order );
		$this->storage->load_from_order( $order, $payment );
		return $payment;
	}

	/**
	 * Attempts to load the payment for an order, and creates one if not found.
	 *
	 * @param WC_Order $order Order to look for.
	 * @return Payment
	 */
	public function load_or_create_payment( WC_Order $order ) {
		$payment = $this->create_payment_object();
		$payment->set_order( $order );
		if ( $this->storage->order_has_payment( $order ) ) {
			$this->storage->load_from_order( $order, $payment );
		}
		return $payment;
	}

	/**
	 * Loads the payment for an order.
	 *
	 * @param string $id    The ID of the payment.
	 * @return Payment|null Loaded payment.
	 */
	public function load_payment_by_id( string $id ) {
		$payment = $this->create_payment_object();
		$this->storage->load_by_id( $id, $payment );
		return $payment;
	}

	/**
	 * Processes a payment, going through all possible states.
	 *
	 * @param Payment  $payment  The payment object.
	 * @param Strategy $strategy Which strategy to use to process the payment (Optional if the payment is already processed).
	 * @throws Exception In case there is no payment to process.
	 */
	public function process( Payment $payment, Strategy $strategy = null ) {
		while ( true ) {
			$previous_state = $payment->get_state();

			switch ( get_class( $payment->get_state() ) ) {
				// Transitions to Prepared_State or Failed_Preparation_State.
				case Initial_State::class:
					$payment->prepare();
					break;

				// Transitions to
				// - Verified_State in the usual case.
				// - Completed_State Payment has already been completed for another order.
				// - Processed_State A successful intent has been found.
				// - Completed_Without_Payment_State Payment is not needed.
				case Prepared_State::class:
					$payment->verify();
					break;

				// Transitions to different states based on strategy, generally:
				// - Processed_State when the strategy yields a successful intent.
				// - Processing_Failed_State on error.
				// - Authentication_Required_State if authentication is required.
				case Verified_State::class:
					$payment->process( $strategy );
					break;

				// Transitions to Completed_State.
				case Processed_State::class:
					$payment->complete();
					break;

				case Intent_Without_Order_State::class:
					$payment->update_intent_with_order();
					break;

				// Add all states here...

				default:
					throw new Exception( 'The payment processed encountered an unexpected state. Is the payment already completed?' );
			}

			if ( $payment->get_state() === $previous_state ) {
				throw new Exception( 'Payment did not change state while being processed. This should not be possible' );
			}

			if ( $payment->is_processing_finished() ) {
				// Might be null, depending on the strategy/state.
				// Failing state will throw an exception here.
				return $payment->get_response();
			}
		}
	}

	/**
	 * Creates a new payment object with the correct dependencies.
	 *
	 * @return Payment
	 */
	protected function create_payment_object() {
		return new Payment( $this->storage, $this->payment_method_factory );
	}
}

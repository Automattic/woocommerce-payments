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
use WCPay\Payment\Storage\Filesystem_Storage;
use WCPay\Payment\Payment_Method\Payment_Method_Factory;
use WCPay\Payment\State\{ Initial_State, Prepared_State, Processed_State, Verified_State };

/**
 * Orchestrates the payment process.
 */
class Manager {
	/**
	 * Payment method factory for the payment process.
	 *
	 * @var Payment_Method_Factory
	 */
	protected $payment_method_factory;

	/**
	 * A factory for payment objects.
	 *
	 * @var Payment_factory
	 */
	protected $payment_factory;

	/**
	 * Instantiates the manager.
	 */
	public function __construct() {
		$storage                      = new Filesystem_Storage();
		$this->payment_method_factory = new Payment_Method_Factory();
		$this->payment_factory        = new Payment_Factory( $storage, $this->payment_method_factory );
	}

	/**
	 * Instantiates a new payment.
	 *
	 * @param WC_Order $order Order to use for the payment.
	 * @return Payment        Newly created payment.
	 */
	public function instantiate_payment( WC_Order $order ) {
		$payment = $this->payment_factory->create_payment();
		$payment->set_order( $order );
		return $payment;
	}

	/**
	 * Processes a payment, going through all possible states.
	 *
	 * @param Payment  $payment  The payment object.
	 * @param Strategy $strategy Which strategy to use to process the payment.
	 * @throws Exception In case there is no payment to process.
	 */
	public function process( Payment $payment, Strategy $strategy ) {
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
}

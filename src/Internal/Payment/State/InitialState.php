<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Payment\PaymentRequest;

/**
 * Initial payment state.
 */
class InitialState extends State {
	/**
	 * Processes a new payment.
	 *
	 * @param PaymentRequest $request Payment request.
	 */
	public function process( PaymentRequest $request ) {
		$context = $this->get_context();

		// Transfer the necessary data from the request into the payment object.
		$payment_method = $request->get_payment_method();
		$context->set_payment_method( $payment_method );

		return $this->create_state( CompletedState::class );
	}
}

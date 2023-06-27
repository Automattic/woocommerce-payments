<?php
/**
 * Payment Class
 *
 * @package WCPay
 */

namespace WCPay\Payment;

/**
 * This class will represent a payment, and much more.
 */
class Payment {
	/**
	 * Holds the current state of the payment.
	 *
	 * @var State
	 */
	protected $state;

	/**
	 * Holds the payment working data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Transitions the payment to a given state.
	 *
	 * @param State $state The state of the payment.
	 */
	public function transition_to( State $state ) {
		$this->state = $state;
		$state->set_context( $this );
	}

	/**
	 * Sets the data of the payment.
	 *
	 * @param array $data Working data.
	 */
	public function set_data( array $data ) {
		$this->data = $data;
	}

	/**
	 * Returns the response of the checkout process.
	 *
	 * @return array
	 */
	public function get_response() {
		return $this->state->get_response();
	}
}

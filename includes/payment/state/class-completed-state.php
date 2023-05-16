<?php
/**
 * Class Completed_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use WC_Payments;
use WC_Payment_Gateway_WCPay;
use WCPay\Payment\Payment;

/**
 * Represents a payment in the complete state, which requires no further processing.
 */
final class Completed_State extends Payment_State {
	/**
	 * Gateway object.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Instantiates the state.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		$this->context = $payment;
		$this->gateway = WC_Payments::get_gateway();
	}

	/**
	 * Returns the response from the payment process.
	 *
	 * Typically this is an array with `result` and `redirect`.
	 *
	 * @return array
	 */
	public function get_response() {
		return [
			'result'   => 'success',
			'redirect' => $this->gateway->get_return_url( $this->context->get_order() ),
		];
	}
}

<?php
/**
 * Initial payment state class.
 *
 * @package WCPay
 */

namespace WCPay\Payment\State;

use WC_Payment_Gateway_WCPay;
use WCPay\Payment\State;

/**
 * A payment in this state has no actions performed yet.
 */
class PreparedState extends State {
	/**
	 * Gateway instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Instantiates the state.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway The active gateway.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Returns the response of the checkout process.
	 *
	 * @return array
	 */
	public function get_response() {
		return [
			'result'   => 'success',
			'redirect' => $this->gateway->get_return_url( wc_get_order( 1460 ) ),
		];
	}
}

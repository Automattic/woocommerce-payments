<?php
/**
 * Class CompletedState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Internal\Service\GatewayService;

/**
 * Represents the payment in complete (successful) state.
 */
class CompletedState extends State {
	/**
	 * Gateway service.
	 *
	 * @var GatewayService
	 */
	private $gateway_service;

	/**
	 * Class constructor.
	 *
	 * @param GatewayService $gateway_service Service for gateway methods.
	 */
	public function __construct( GatewayService $gateway_service ) {
		$this->gateway_service = $gateway_service;
	}

	/**
	 * Returns the response, expected from a gateway's `process_payment()` method.
	 *
	 * @return array Whenever the current state implements the method.
	 */
	public function get_gateway_response() {
		$url = $this->gateway_service->get_return_url( $this->payment->get_order() );

		return [
			'result'   => 'success',
			'redirect' => $url,
		];
	}
}

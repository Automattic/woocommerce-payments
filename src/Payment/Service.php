<?php
/**
 * Payment service class
 *
 * @package WCPay
 */

namespace WCPay\Payment;

use WCPay\Container;
use WCPay\Payment\State\InitialState;

/**
 * This class will manage payments.
 */
class Service {
	/**
	 * Container instance, used to get states.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Instantiates the class, and all dependencies.
	 *
	 * @param Container $container The DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Generates a new payment.
	 *
	 * @param array $data Payment data.
	 * @return Payment
	 */
	public function create_payment( array $data ) {
		$state_class = $data['state'];
		unset( $data['state'] );

		$payment = new Payment();
		$payment->set_data( $data );
		$payment->transition_to( $this->container->get( $state_class ) );
		return $payment;
	}
}

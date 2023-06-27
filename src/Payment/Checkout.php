<?php
/**
 * Payment service class
 *
 * @package WCPay
 */

namespace WCPay\Payment;

/**
 * This class will load payments.
 */
class Checkout {
	/**
	 * Holds the dependency.
	 *
	 * @var Service
	 */
	protected $service;

	/**
	 * Instantiates the class.
	 *
	 * @param Service $service Dependency.
	 */
	public function __construct( Service $service ) {
		$this->service = $service;
	}
}

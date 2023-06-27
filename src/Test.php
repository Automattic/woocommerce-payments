<?php
/**
 * Autoloading and DI test class.
 *
 * @package WCPay
 */

namespace WCPay;

use WCPay\Payment\Service;
use WCPay\Payment\State\PreparedState;

/**
 * This class will be deleted.
 */
class Test {
	/**
	 * Tests things.
	 *
	 * @param Service $service The payment service.
	 */
	public function __construct( Service $service ) {
		$data = [
			'state' => PreparedState::class,
			'var'   => 'a',
		];

		$payment = $service->create_payment( $data );
		var_dump( $payment->get_response() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump
		exit;
	}
}

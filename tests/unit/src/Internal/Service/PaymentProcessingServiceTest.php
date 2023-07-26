<?php
/**
 * Class PaymentProcessingServiceTest
 *
 * @package WooPayments
 */

namespace WooPayments\Tests\Internal\Service;

use WCPAY_UnitTestCase;
use WooPayments\Internal\Service\PaymentProcessingService;

/**
 * Payment processing service unit tests.
 */
class PaymentProcessingServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Used to determine whether autoloading works.
	 */
	public function test_class_is_loaded() {
		$this->assertTrue( class_exists( PaymentProcessingService::class ) );

		$instance = new PaymentProcessingService();
		$this->assertInstanceOf( PaymentProcessingService::class, $instance );
	}
}

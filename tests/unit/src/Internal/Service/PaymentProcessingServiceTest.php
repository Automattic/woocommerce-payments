<?php
/**
 * Class PaymentProcessingServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WCPAY_UnitTestCase;
use WCPay\Internal\Service\PaymentProcessingService;

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

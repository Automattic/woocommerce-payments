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
	 * Service under test.
	 *
	 * @var PaymentProcessingService
	 */
	private $sut;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->sut = new PaymentProcessingService();
	}

	/**
	 * Used to determine whether autoloading works.
	 */
	public function test_class_is_loaded() {
		$this->assertTrue( class_exists( PaymentProcessingService::class ) );

		$instance = new PaymentProcessingService();
		$this->assertInstanceOf( PaymentProcessingService::class, $instance );
	}

	/**
	 * Checks if the `process_payment` method throws an exception.
	 */
	public function test_processing_payment_throws_exception() {
		$this->expectException( \Exception::class );
		$this->sut->process_payment( 1 );
	}
}

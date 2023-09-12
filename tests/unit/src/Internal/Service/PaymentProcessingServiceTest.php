<?php
/**
 * Class PaymentProcessingServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Internal\Payment\StateFactory;
use WCPay\Internal\Proxy\LegacyProxy;
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
	 * StateFactory mock.
	 *
	 * @var StateFactory|MockObject
	 */
	private $state_factory_mock;

	/**
	 * LegacyProxy mock.
	 *
	 * @var LegacyProxy|MockObject
	 */
	private $legacy_proxy_mock;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->state_factory_mock = $this->createMock( StateFactory::class );
		$this->legacy_proxy_mock  = $this->createMock( LegacyProxy::class );

		$this->sut = new PaymentProcessingService(
			$this->state_factory_mock,
			$this->legacy_proxy_mock
		);
	}

	/**
	 * Used to determine whether autoloading works.
	 */
	public function test_class_is_loaded() {
		$this->assertTrue( class_exists( PaymentProcessingService::class ) );

		$instance = new PaymentProcessingService( $this->state_factory_mock, $this->legacy_proxy_mock );
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

<?php
/**
 * Class ExtendedContainerTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests;

use stdClass;
use WCPAY_UnitTestCase;
use WCPay\Internal\DependencyManagement\ExtendedContainer;

/**
 * Internal extended container tests.
 *
 * The tests here make sure to cover all methods, which are
 * not used in order unit tests already.
 */
class ExtendedContainerTest extends WCPAY_UnitTestCase {
	/**
	 * Holds the extended container, which will be tested.
	 *
	 * @var ExtendedContainer
	 */
	private $sut;

	/**
	 * Sets up the container.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->sut = new ExtendedContainer();
	}

	/** The historical activity reader is available through normal provider registration. */
	public function test_payment_activity_reader_is_registered() {
		$this->sut->addServiceProvider( new \WCPay\Internal\DependencyManagement\ServiceProvider\GenericServiceProvider() );
		$this->assertTrue( $this->sut->has( \WCPay\Internal\Service\PaymentActivityReader::class ) );
		$this->assertInstanceOf( \WCPay\Internal\Service\PaymentActivityReader::class, $this->sut->get( \WCPay\Internal\Service\PaymentActivityReader::class ) );
		$this->assertSame( $this->sut->get( \WCPay\Internal\Service\PaymentActivityReader::class ), $this->sut->get( \WCPay\Internal\Service\PaymentActivityReader::class ) );
	}

	/**
	 * Tests that there is nothing done whenever a replacement to reset has been not found.
	 */
	public function test_reset_replacement_does_nothing_if_replacement_not_found() {
		$this->assertNull( $this->sut->reset_replacement( 'SomeUnknownClass' ) );
	}
}

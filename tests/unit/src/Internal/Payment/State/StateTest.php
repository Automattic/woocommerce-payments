<?php
/**
 * Class StateTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\State;

use ReflectionClass;
use WCPay\Internal\Payment\Exception\MethodUnavailableException;
use WCPay\Internal\Payment\Payment;
use WCPAY_UnitTestCase;
use WCPay\Internal\Payment\State\State;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * The base class is abstract, we need a dummy implementation.
 */
class SampleState extends State {}

/**
 * State factory unit tests.
 */
class StateTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var State
	 */
	private $sut;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->sut = new SampleState();
	}

	/**
	 * Tests that `set_context` stores the payment.
	 */
	public function test_set_context_stores_payment() {
		$mock_payment = $this->createMock( Payment::class );
		$this->sut->set_context( $mock_payment );

		// There is no `get_context()` method to verify. Using reflection is sufficient.
		$reflection = new ReflectionClass( $this->sut );
		$property   = $reflection->getProperty( 'payment' );
		$property->setAccessible( true );
		$this->assertSame( $mock_payment, $property->getValue( $this->sut ) );
	}

	/**
	 * Ensures that the `get_gateway_response` method in the base state throws an exception.
	 */
	public function test_get_gateway_response_throws_exception() {
		$this->expectException( MethodUnavailableException::class );
		$this->sut->get_gateway_response();
	}

	/**
	 * Ensures that the `prepare` method in the base state throws an exception.
	 */
	public function test_prepare_throws_exception() {
		$this->expectException( MethodUnavailableException::class );
		$this->sut->prepare( 'pm_ZYX' );
	}
}

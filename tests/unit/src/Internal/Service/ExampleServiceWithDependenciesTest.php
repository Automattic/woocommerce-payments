<?php
/**
 * Class ExampleServiceWithDependenciesTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use WCPAY_UnitTestCase;
use WCPay\Internal\Service\ExampleServiceWithDependencies;

/**
 * Tests for the example service, to increase test coverage.
 */
class ExampleServiceWithDependenciesTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var ExampleServiceWithDependencies
	 */
	private $sut;

	protected function setUp(): void {
		parent::setUp();

		// Loading through the container to increase coverage.
		$this->sut = wcpay_get_container()->get( ExampleServiceWithDependencies::class );
	}

	/**
	 * Tests the mode method of the service.
	 */
	public function test_the_mode_method() {
		$this->assertFalse( $this->sut->is_in_test_mode() );
	}

	/**
	 * Tests the exception method of the service.
	 */
	public function test_the_exception_method() {
		$this->assertFalse( $this->sut->handle_exception() );
	}
}

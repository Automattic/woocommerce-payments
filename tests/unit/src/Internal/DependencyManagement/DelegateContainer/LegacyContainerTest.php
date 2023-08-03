<?php
/**
 * Class LegacyContainerTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\DependencyManagement\DelegateContainer;

use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Mode;
use WCPay\Internal\DependencyManagement\DelegateContainer\LegacyContainer;

/**
 * Tests for the legacy container.
 */
class LegacyContainerTest extends WCPAY_UnitTestCase {
	/**
	 * Holds the container, which will be tested.
	 *
	 * @var Container
	 */
	private $sut;

	/**
	 * Sets up the container.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->sut = new LegacyContainer();
	}

	/**
	 * Makes sure that the container supports the WCPay\Core\Mode class.
	 */
	public function test_container_loads_mode() {
		$this->test_retrieval( Mode::class );
	}

	/**
	 * Makes sure that the container indicates that it has a certain class,
	 * and that it returns an instance of that very class.
	 *
	 * @param string $class_name The name of the needed class.
	 */
	private function test_retrieval( $class_name ) {
		$this->assertTrue( $this->sut->has( $class_name ) );
		$this->assertInstanceOf( $class_name, $this->sut->get( $class_name ) );
	}
}

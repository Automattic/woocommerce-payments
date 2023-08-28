<?php
/**
 * Class WooContainerTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\DependencyManagement\DelegateContainer;

use WCPAY_UnitTestCase;
use WCPay\Internal\DependencyManagement\DelegateContainer\WooContainer;
use Automattic\WooCommerce\Utilities\PluginUtil;

/**
 * Tests for the Woo delegate container.
 */
class WooContainerTest extends WCPAY_UnitTestCase {
	/**
	 * Holds the container, which will be tested.
	 *
	 * @var WooContainer
	 */
	private $sut;

	/**
	 * Sets up the container.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->sut = new WooContainer();
	}

	/**
	 * Makes sure that the container indicates that it has a certain class,
	 * and that it returns an instance of that very class.
	 */
	public function test_retrieval() {
		$this->assertTrue( $this->sut->has( PluginUtil::class ) );
		$this->assertInstanceOf( PluginUtil::class, $this->sut->get( PluginUtil::class ) );
	}
}

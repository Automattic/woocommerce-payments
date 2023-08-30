<?php
/**
 * Class WCPay_Core_Request_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request;
use WCPay\Core\Server\Request\Paginated;
use WCPay\Core\Server\Request\List_Transactions;

/**
 * WCPay\Core\Server\Capture_Intention_Test unit tests.
 */
class WCPay_Core_Request_Test extends WCPAY_UnitTestCase {
	/**
	 * Tests the most basic function of `traverse_class_constants`,
	 * which is to go though all classes in the tree, and return a constant in the right order.
	 */
	public function test_traverse_class_constants() {
		$expected = [];
		$tree     = [
			Request::class,
			Paginated::class,
			List_Transactions::class,
		];
		foreach ( $tree as $class_name ) {
			$expected = array_merge( $expected, constant( $class_name . '::DEFAULT_PARAMS' ) );
		}

		$result = List_Transactions::traverse_class_constants( 'DEFAULT_PARAMS' );
		$this->assertSame( $expected, $result );
	}
}

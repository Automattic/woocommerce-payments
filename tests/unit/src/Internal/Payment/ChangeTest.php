<?php
/**
 * Class FactorTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use WCPAY_UnitTestCase;
use WCPay\Internal\Payment\Change;

/**
 * Test class for the Change class.
 */
class ChangeTest extends WCPAY_UnitTestCase {

	/**
	 * Tests that all getters working correctly.
	 *
	 */
	public function test_getters() {
		// Create a new Change instance with some sample data.
		$key       = 'test_key';
		$old_value = 'old_value';
		$new_value = 'new_value';
		$change    = new Change( $key, $old_value, $new_value );

		// Assert that the getters return the correct values.
		$this->assertEquals( $key, $change->get_key() );
		$this->assertEquals( $old_value, $change->get_old_value() );
		$this->assertEquals( $new_value, $change->get_new_value() );
	}
}

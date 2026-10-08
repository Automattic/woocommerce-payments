<?php
/**
 * Class WCPAY_UnitTestCase_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Get_Intention;

/**
 * Tests for the helpers in WCPAY_UnitTestCase.
 */
class WCPAY_UnitTestCase_Test extends WCPAY_UnitTestCase {

	public function test_zero_call_mock_replaces_the_request() {
		$this->mock_wcpay_request( Get_Intention::class, 0 );

		$this->assertInstanceOf( MockObject::class, Get_Intention::create( 'pi_test' ) );
	}
}

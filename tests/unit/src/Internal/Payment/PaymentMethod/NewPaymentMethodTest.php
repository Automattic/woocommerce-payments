<?php
/**
 * Class NewPaymentMethodTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\PaymentMethod;

use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;
use WCPAY_UnitTestCase;

/**
 * Tests for the new payment method class
 */
class NewPaymentMethodTest extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var NewPaymentMethod
	 */
	private $sut;

	protected function setUp(): void {
		parent::setUp();
		$this->sut = new NewPaymentMethod( 'pm_test' );
	}

	public function test_get_id() {
		$this->assertSame( 'pm_test', $this->sut->get_id() );
	}

	public function test_get_data() {
		$this->assertSame(
			[
				'type' => 'new',
				'id'   => 'pm_test',
			],
			$this->sut->get_data()
		);
	}
}

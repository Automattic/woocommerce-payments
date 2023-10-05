<?php
/**
 * Class PaymentContextTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use PHPUnit\Framework\MockObject\MockObject;
use WCPAY_UnitTestCase;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;

/**
 * Tests for class PaymentContextUtilTest
 */
class PaymentContextTest extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var PaymentContext
	 */
	private $sut;

	/**
	 * Order ID to use throughout the tests.
	 *
	 * @var int
	 */
	private $order_id = 123;

	/**
	 * Tests setup.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sut = new PaymentContext( $this->order_id );
	}

	public function test_order_id() {
		$this->assertSame( $this->order_id, $this->sut->get_order_id() );
	}

	public function test_amount() {
		$amount = 456;

		$this->sut->set_amount( $amount );
		$this->assertSame( $amount, $this->sut->get_amount() );
	}
	public function test_payment_method() {
		$payment_method = new NewPaymentMethod( 'pm_XYZ' );

		$this->sut->set_payment_method( $payment_method );
		$this->assertSame( $payment_method, $this->sut->get_payment_method() );
	}
}

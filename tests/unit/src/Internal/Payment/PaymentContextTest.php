<?php
/**
 * Class PaymentContextTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

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

	public function test_currency() {
		$currency = 'eur';

		$this->sut->set_currency( $currency );
		$this->assertSame( $currency, $this->sut->get_currency() );
	}

	public function test_manual_capture_disabled() {
		$toggle_manual_capture = false;

		$this->sut->toggle_manual_capture( $toggle_manual_capture );
		$this->assertSame( $toggle_manual_capture, $this->sut->should_capture_manually() );
	}

	public function test_manual_capture_enabled() {
		$toggle_manual_capture = true;

		$this->sut->toggle_manual_capture( $toggle_manual_capture );
		$this->assertSame( $toggle_manual_capture, $this->sut->should_capture_manually() );
	}

	public function test_metadata() {
		$metadata = [ 'some_meta_key' => 'yes' ];

		$this->sut->set_metadata( $metadata );
		$this->assertSame( $metadata, $this->sut->get_metadata() );
	}

	public function test_level3_data() {
		$level3_data = [ 'items' => [] ];

		$this->sut->set_level3_data( $level3_data );
		$this->assertSame( $level3_data, $this->sut->get_level3_data() );
	}

	public function test_cvc_confirmation() {
		$cvc_confirmation = 'confirmation';

		$this->sut->set_cvc_confirmation( $cvc_confirmation );
		$this->assertSame( $cvc_confirmation, $this->sut->get_cvc_confirmation() );
	}

	public function test_fingerprint() {
		$fingerprint = 'fingerprint';

		$this->sut->set_fingerprint( $fingerprint );
		$this->assertSame( $fingerprint, $this->sut->get_fingerprint() );
	}

	public function test_user_id() {
		$user_id = 123;

		$this->sut->set_user_id( $user_id );
		$this->assertSame( $user_id, $this->sut->get_user_id() );
	}

	public function test_customer_id() {
		$customer_id = 'cus_ZYX';

		$this->sut->set_customer_id( $customer_id );
		$this->assertSame( $customer_id, $this->sut->get_customer_id() );
	}
}

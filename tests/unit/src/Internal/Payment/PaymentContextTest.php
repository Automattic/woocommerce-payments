<?php
/**
 * Class PaymentContextTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use WC_Helper_Intention;
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

	public function test_automatic_capture_disabled() {
		$toggle_automatic_capture = false;

		$this->sut->toggle_automatic_capture( $toggle_automatic_capture );
		$this->assertSame( $toggle_automatic_capture, $this->sut->should_capture_automatically() );
	}

	public function test_automatic_capture_enabled() {
		$toggle_automatic_capture = true;

		$this->sut->toggle_automatic_capture( $toggle_automatic_capture );
		$this->assertSame( $toggle_automatic_capture, $this->sut->should_capture_automatically() );
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

	public function test_duplicate_order_id() {
		$duplicate_order_id = 123;

		$this->sut->set_duplicate_order_id( $duplicate_order_id );
		$this->assertSame( $duplicate_order_id, $this->sut->get_duplicate_order_id() );
	}

	public function test_is_detected_authorized_intent() {
		$this->assertSame( false, $this->sut->is_detected_authorized_intent() );

		$this->sut->set_detected_authorized_intent();
		$this->assertSame( true, $this->sut->is_detected_authorized_intent() );
	}

	public function test_intent() {
		$intent = WC_Helper_Intention::create_intention();

		$this->sut->set_intent( $intent );
		$this->assertSame( $intent, $this->sut->get_intent() );
	}

	public function test_fraud_prevention_token() {
		$token = 'random_token';

		$this->sut->set_fraud_prevention_token( $token );
		$this->assertSame( $token, $this->sut->get_fraud_prevention_token() );
	}

	public function test_mode() {
		$mode = 'prod';

		$this->sut->set_mode( $mode );
		$this->assertSame( $mode, $this->sut->get_mode() );
	}

	public function test_log_state_transition() {
		$this->sut->log_state_transition( 'First_State' );
		// first transition has 'from_state' null and 'to_state' as 'First_State'.
		$this->assertNull( $this->sut->get_transitions()[0]->get_from_state() );
		$this->assertSame( 'First_State', $this->sut->get_transitions()[0]->get_to_state() );
		// next transition has 'from_state' as `First_State` and 'to_state' null.
		$this->assertSame( 'First_State', $this->sut->get_transitions()[1]->get_from_state() );
		$this->assertNull( $this->sut->get_transitions()[1]->get_to_state() );
	}
}

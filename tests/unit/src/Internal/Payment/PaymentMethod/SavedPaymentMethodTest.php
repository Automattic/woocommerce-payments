<?php
/**
 * Class SavedPaymentMethodTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\PaymentMethod;

use WCPAY_UnitTestCase;
use WCPay\Internal\Payment\PaymentMethod\SavedPaymentMethod;

/**
 * Tests for the saved payment method class
 */
class SavedPaymentMethodTest extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var SavedPaymentMethod
	 */
	private $sut;

	/**
	 * Saved token including the payment method.
	 *
	 * @var int
	 */
	private $saved_token_id = 234;

	/**
	 * Saved payment method ID.
	 *
	 * @var string
	 */
	private $payment_method_id = 'pm_saved_as_woo_token';

	protected function setUp(): void {
		parent::setUp();

		$this->sut = new SavedPaymentMethod( $this->payment_method_id, $this->saved_token_id );
	}

	public function test_get_id() {
		$this->assertSame( $this->payment_method_id, $this->sut->get_id() );
	}

	public function test_get_token_id() {
		$this->assertSame( $this->saved_token_id, $this->sut->get_token_id() );
	}

	public function test_get_data() {
		$this->assertSame(
			[
				'type'     => 'saved',
				'id'       => $this->payment_method_id,
				'token_id' => $this->saved_token_id,
			],
			$this->sut->get_data()
		);
	}
}

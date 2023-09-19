<?php
/**
 * Class SavedPaymentMethodTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\PaymentMethod;

use WC_Helper_Token;
use WC_Payment_Token;
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
	 * @var WC_Payment_Token
	 */
	private $saved_token;
	protected function setUp(): void {
		parent::setUp();
		$this->saved_token = WC_Helper_Token::create_token( 'pm_saved_as_woo_token' );
		$this->sut         = new SavedPaymentMethod( $this->saved_token );
	}

	public function test_get_id() {
		$this->assertSame( 'pm_saved_as_woo_token', $this->sut->get_id() );
	}

	public function test_get_data() {
		$this->assertSame(
			[
				'type' => 'saved',
				'id'   => $this->saved_token->get_id(),
			],
			$this->sut->get_data()
		);
	}
}

<?php
/**
 * Class SavedPaymentMethodTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment\PaymentMethod;

use WCPay\Internal\Payment\PaymentMethod\SavedPaymentMethod;
use WCPAY_UnitTestCase;

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

	protected function setUp(): void {
		parent::setUp();
		$saved_token = \WC_Helper_Token::create_token( 'pm_saved_as_woo_token' );
		$this->sut   = new SavedPaymentMethod( $saved_token );
	}

	public function test_get_id() {
		$this->assertSame( 'pm_saved_as_woo_token', $this->sut->get_id() );
	}

	public function test_get_data() {
		$this->assertSame(
			[
				'type' => 'saved',
				'id'   => 'pm_saved_as_woo_token',
			],
			$this->sut->get_data()
		);
	}
}

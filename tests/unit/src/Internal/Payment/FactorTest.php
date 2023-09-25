<?php
/**
 * Class FactorTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use WCPAY_UnitTestCase;
use WCPay\Internal\Payment\Factor;

/**
 * Test class for the Factor enum.
 */
class FactorTest extends WCPAY_UnitTestCase {
	/**
	 * Tests that all factors are returned correctly.
	 *
	 * This test is meant to make sure that a typo with
	 * factors doesn't randomly break the payment process.
	 */
	public function test_get_all_factors() {
		// Factors here are string intentionally.
		$factors = [
			'NEW_PAYMENT_PROCESS',
			'NO_PAYMENT',
			'USE_SAVED_PM',
			'SAVE_PM',
			'SUBSCRIPTION_SIGNUP',
			'SUBSCRIPTION_RENEWAL',
			'POST_AUTHENTICATION',
			'WOOPAY_ENABLED',
			'WOOPAY_PAYMENT',
			'WCPAY_SUBSCRIPTION_SIGNUP',
			'IPP_CAPTURE',
			'STRIPE_LINK',
			'DEFERRED_INTENT_SPLIT_UPE',
			'PAYMENT_REQUEST',
		];

		$result = Factor::get_all_factors();
		$this->assertEquals( $factors, $result );
	}
}

<?php
/**
 * Class Refund_Failure_Reason_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Refund_Failure_Reason;

/**
 * Refund_Failure_Reason unit tests.
 */
class Refund_Failure_Reason_Test extends WCPAY_UnitTestCase {

	/**
	 * Test that all failure reason constants are defined.
	 */
	public function test_failure_reason_constants_are_defined() {
		$this->assertTrue( defined( 'WCPay\Constants\Refund_Failure_Reason::LOST_OR_STOLEN_CARD' ) );
		$this->assertTrue( defined( 'WCPay\Constants\Refund_Failure_Reason::EXPIRED_OR_CANCELED_CARD' ) );
		$this->assertTrue( defined( 'WCPay\Constants\Refund_Failure_Reason::CHARGE_FOR_PENDING_REFUND_DISPUTED' ) );
		$this->assertTrue( defined( 'WCPay\Constants\Refund_Failure_Reason::INSUFFICIENT_FUNDS' ) );
		$this->assertTrue( defined( 'WCPay\Constants\Refund_Failure_Reason::DECLINED' ) );
		$this->assertTrue( defined( 'WCPay\Constants\Refund_Failure_Reason::MERCHANT_REQUEST' ) );
		$this->assertTrue( defined( 'WCPay\Constants\Refund_Failure_Reason::UNKNOWN' ) );
	}

	/**
	 * Test that get_failure_message returns correct messages for all failure reasons.
	 */
	public function test_get_failure_message_returns_correct_messages() {
		$test_cases = [
			Refund_Failure_Reason::LOST_OR_STOLEN_CARD => 'The card used for the original payment has been reported lost or stolen.',
			Refund_Failure_Reason::EXPIRED_OR_CANCELED_CARD => 'The card used for the original payment has expired or been canceled.',
			Refund_Failure_Reason::CHARGE_FOR_PENDING_REFUND_DISPUTED => 'The charge for this refund is being disputed by the customer.',
			Refund_Failure_Reason::INSUFFICIENT_FUNDS  => 'Insufficient funds in your WooPayments balance.',
			Refund_Failure_Reason::DECLINED            => 'The refund was declined by the card issuer.',
			Refund_Failure_Reason::MERCHANT_REQUEST    => 'The refund was canceled at your request.',
			Refund_Failure_Reason::UNKNOWN             => 'An unknown error occurred while processing the refund.',
		];

		foreach ( $test_cases as $reason => $expected_message ) {
			$this->assertEquals( $expected_message, Refund_Failure_Reason::get_failure_message( $reason ) );
		}
	}

	/**
	 * Test that get_failure_message returns unknown message for invalid reason.
	 */
	public function test_get_failure_message_returns_unknown_for_invalid_reason() {
		$invalid_reason   = 'invalid_reason';
		$expected_message = 'An unknown error occurred while processing the refund.';

		$this->assertEquals( $expected_message, Refund_Failure_Reason::get_failure_message( $invalid_reason ) );
	}

	/**
	 * Test that get_failure_message returns unknown message for empty reason.
	 */
	public function test_get_failure_message_returns_unknown_for_empty_reason() {
		$empty_reason     = '';
		$expected_message = 'An unknown error occurred while processing the refund.';

		$this->assertEquals( $expected_message, Refund_Failure_Reason::get_failure_message( $empty_reason ) );
	}
}

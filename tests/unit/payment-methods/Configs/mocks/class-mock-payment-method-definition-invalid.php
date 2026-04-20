<?php
/**
 * Mock payment method definitions for testing.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\PaymentMethods\Configs;

/**
 * Invalid mock class that doesn't implement the interface.
 */
class InvalidMockPaymentMethod {
	public static function get_id(): string {
		return 'invalid_mock';
	}
}

<?php
/**
 * Test double for a tracking provider that exposes the optional
 * `register_persistence_hooks` static method.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\Tracking_Providers\WooPay_Tracking_Provider;

/**
 * Counts invocations of `register_persistence_hooks` so the sync class test
 * can assert the constructor calls into providers that opt into persistence.
 */
class Fake_Persistence_Provider implements WooPay_Tracking_Provider {

	/**
	 * Number of times register_persistence_hooks has been invoked.
	 *
	 * @var int
	 */
	public static $register_calls = 0;

	/**
	 * Reset call counter between tests.
	 */
	public static function reset(): void {
		self::$register_calls = 0;
	}

	/**
	 * Static persistence-hook registration method picked up by the sync class.
	 */
	public static function register_persistence_hooks(): void {
		++self::$register_calls;
	}

	public function is_available( \WC_Order $order ): bool {
		return false;
	}

	public function get_shipments( \WC_Order $order ): array {
		return [];
	}

	public function get_hooks(): array {
		return [];
	}
}

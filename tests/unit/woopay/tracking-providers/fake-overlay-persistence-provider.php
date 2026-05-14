<?php
/**
 * Test fake for an overlay-only provider that also exposes the optional
 * `register_persistence_hooks()` static method. Mirrors TrackShip's
 * shape — overlay interface implemented, persistence hook registration
 * via the discovery convention.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\Tracking_Providers\WooPay_Status_Overlay_Provider;

/**
 * Used by the sync class test to verify that overlay-only providers can
 * participate in persistence-hook registration without needing a stub
 * primary-chain entry.
 *
 * @phpcs:ignore Squiz.Commenting.ClassComment.Missing
 */
class Fake_Overlay_Persistence_Provider implements WooPay_Status_Overlay_Provider {

	/**
	 * Count of `register_persistence_hooks()` invocations.
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

	public static function register_persistence_hooks(): void {
		++self::$register_calls;
	}

	public function overlay( \WC_Order $order, array $shipments ): array {
		return $shipments;
	}
}

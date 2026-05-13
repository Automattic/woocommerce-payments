<?php
/**
 * Test fake for `WooPay_Status_Overlay_Provider` — sets a configurable
 * canonical status on every shipment in the input array.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\Tracking_Providers\WooPay_Status_Overlay_Provider;

/**
 * Configurable overlay test double. Used by sync class tests to verify the
 * two-pass orchestration runs overlays in priority order and respects the
 * same-cardinality contract.
 *
 * @phpcs:ignore Squiz.Commenting.ClassComment.Missing
 */
class Fake_Overlay_Provider implements WooPay_Status_Overlay_Provider {
	/**
	 * Status to set on every shipment.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Count of times overlay() has been invoked across the process.
	 *
	 * @var int
	 */
	public $overlay_calls = 0;

	public function __construct( string $status ) {
		$this->status = $status;
	}

	public function overlay( \WC_Order $order, array $shipments ): array {
		++$this->overlay_calls;
		return array_map(
			function ( $shipment ) {
				$shipment['status'] = $this->status;
				return $shipment;
			},
			$shipments
		);
	}
}

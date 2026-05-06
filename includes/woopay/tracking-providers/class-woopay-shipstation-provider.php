<?php
/**
 * WooPay ShipStation Provider
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay\Tracking_Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Reads tracking data written by the ShipStation WooCommerce integration
 * (woocommerce-shipstation-integration) when running without WC Shipment
 * Tracking installed.
 *
 * When WC Shipment Tracking IS present, ShipStation calls
 * wc_st_add_tracking_number() instead and writes to the standard
 * _wc_shipment_tracking_items meta — those merchants are handled by
 * WooPay_Shipment_Tracking_Provider (Phase 1).
 *
 * In standalone mode ShipStation writes two flat meta keys before firing
 * woocommerce_shipstation_shipnotify, so get_shipments() can read them
 * directly from the order without any hook-arg threading.
 */
class WooPay_ShipStation_Provider implements WooPay_Tracking_Provider {

	/**
	 * Maximum length for forwarded string fields.
	 */
	const STRING_FIELD_MAX_LEN = 256;

	/**
	 * Whether ShipStation standalone is active and the order has tracking data.
	 *
	 * Returns false when WC Shipment Tracking is installed: in that case
	 * ShipStation writes to _wc_shipment_tracking_items and the Phase 1
	 * provider handles the order.
	 *
	 * Uses class_exists('WC_Shipment_Tracking') — the same sentinel ShipStation
	 * itself uses internally — rather than WC_Shipment_Tracking_Actions (Phase 1)
	 * to make the mutual-exclusion intent clear.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function is_available( \WC_Order $order ): bool {
		if ( class_exists( 'WC_Shipment_Tracking' ) ) {
			return false; // Phase 1 handles it.
		}
		if ( ! class_exists( 'WC_ShipStation_Integration' ) ) {
			return false;
		}
		return ! empty( $order->get_meta( '_tracking_number' ) );
	}

	/**
	 * Extract and normalize shipments from ShipStation's flat order meta.
	 *
	 * ShipStation writes _tracking_number and _tracking_provider before
	 * firing the hook; _date_shipped is only written on the legacy path
	 * (WC Shipment Tracking < 1.4.0) so it may be absent on modern installs.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array[]
	 */
	public function get_shipments( \WC_Order $order ): array {
		$tracking_number = self::sanitize_field( $order->get_meta( '_tracking_number' ) );
		if ( '' === $tracking_number ) {
			return [];
		}

		$date_shipped = '';
		$date_meta    = $order->get_meta( '_date_shipped' );
		if ( ! empty( $date_meta ) ) {
			$timestamp = (int) $date_meta;
			if ( $timestamp > 0 ) {
				$date_shipped = gmdate( 'Y-m-d', $timestamp );
			}
		}

		return [
			[
				'tracking_number' => $tracking_number,
				'carrier_name'    => self::sanitize_field( $order->get_meta( '_tracking_provider' ) ),
				'tracking_url'    => '',
				'date_shipped'    => $date_shipped,
				'status'          => 'fulfilled',
				'items'           => [],
			],
		];
	}

	/**
	 * Return the hook that signals a ShipStation shipment notification.
	 *
	 * ShipStation fires this after writing meta, so get_shipments() can
	 * read from the order directly — no hook-arg threading needed.
	 *
	 * arg_count is 2 ($order, $tracking_data) but send_webhook() only needs
	 * the first arg to resolve the order ID, which resolve_order_id() already
	 * handles for WC_Order instances.
	 *
	 * @return array[]
	 */
	public function get_hooks(): array {
		return [
			[
				'hook'      => 'woocommerce_shipstation_shipnotify',
				'arg_count' => 2,
			],
		];
	}

	/**
	 * Strip HTML/JS, trim, and bound the length of a string field.
	 *
	 * @param mixed $value Raw value from order meta.
	 * @return string
	 */
	private static function sanitize_field( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$clean = trim( wp_strip_all_tags( (string) $value ) );
		return self::truncate( $clean, self::STRING_FIELD_MAX_LEN );
	}

	/**
	 * Truncate a string to a max length, with mbstring fallback.
	 *
	 * @param string $value Already-sanitized string.
	 * @param int    $max   Max character length.
	 * @return string
	 */
	private static function truncate( string $value, int $max ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}
		return substr( $value, 0, $max );
	}
}

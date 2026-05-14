<?php
/**
 * WooPay ShipStation Provider
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay\Tracking_Providers;

use WCPay\WooPay\WooPay_Order_Tracking_Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Reads tracking data from a WooPay-owned meta key populated by a listener
 * on `woocommerce_shipstation_shipnotify`.
 *
 * Why a listener and an owned meta key:
 * ShipStation's standalone integration (no WC Shipment Tracking installed)
 * does NOT persist tracking data to order meta — it only adds an order note
 * and fires `woocommerce_shipstation_shipnotify` with the tracking payload
 * as a hook argument. The webhook payload, however, is built later by
 * `WC_Webhook` delivery (Action Scheduler), at which point the original hook
 * argument is gone. We therefore persist the hook arg to a meta key we own
 * (`_wcpay_shipstation_tracking_items`) the moment the action fires, and
 * read from that meta key during payload assembly.
 *
 * When WC Shipment Tracking IS present, ShipStation calls
 * `wc_st_add_tracking_number()` instead and writes to the standard
 * `_wc_shipment_tracking_items` meta — those merchants are handled by
 * `WooPay_Shipment_Tracking_Provider` (Phase 1). We therefore short-circuit
 * `is_available()` and skip the persistence listener whenever
 * `WC_Shipment_Tracking` is loaded.
 *
 * Out of scope: WC Shipment Tracking < 1.4.0 (released 2014). On those
 * installs ShipStation skips `wc_st_add_tracking_number()` and writes flat
 * meta keys (`_tracking_number`, `_tracking_provider`, `_date_shipped`)
 * without firing `woocommerce_shipment_tracking_added`. Both Phase 1 and
 * this provider miss those orders. The cohort is effectively zero, so the
 * gap is intentional.
 */
class WooPay_ShipStation_Provider implements WooPay_Tracking_Provider {

	/**
	 * Meta key WooPay writes ShipStation tracking data into. Private to
	 * this plugin — not consumed by ShipStation or any other plugin.
	 */
	const META_KEY = '_wcpay_shipstation_tracking_items';

	/**
	 * Maximum length for forwarded string fields.
	 */
	const STRING_FIELD_MAX_LEN = 256;

	/**
	 * Whether ShipStation standalone is active and the order has tracking
	 * data persisted by our listener.
	 *
	 * Returns false when WC Shipment Tracking is installed: in that case
	 * ShipStation writes to `_wc_shipment_tracking_items` and the Phase 1
	 * provider handles the order. Uses `class_exists('WC_Shipment_Tracking')`
	 * — the same sentinel ShipStation itself uses internally.
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
		$items = $order->get_meta( self::META_KEY );
		return ! empty( $items ) && is_array( $items );
	}

	/**
	 * Extract and normalize shipments from our owned meta.
	 *
	 * The meta value is a list of entries written by `persist_tracking_data()`.
	 * Each entry is already in shipment shape; this method only re-applies
	 * sanitization (defense-in-depth in case meta was filtered between
	 * write and read) and skips entries without a tracking number.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array[]
	 */
	public function get_shipments( \WC_Order $order ): array {
		$items = $order->get_meta( self::META_KEY );
		if ( empty( $items ) || ! is_array( $items ) ) {
			return [];
		}

		$shipments = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$tracking_number = self::sanitize_field( $item['tracking_number'] ?? '' );
			if ( '' === $tracking_number ) {
				continue;
			}
			$date_shipped = '';
			$raw_date     = $item['date_shipped'] ?? '';
			if ( ! empty( $raw_date ) ) {
				$timestamp = (int) $raw_date;
				if ( $timestamp > 0 ) {
					$date_shipped = gmdate( 'Y-m-d', $timestamp );
				}
			}
			$shipments[] = [
				'tracking_number' => $tracking_number,
				'carrier_name'    => self::sanitize_field( $item['carrier_name'] ?? '' ),
				'tracking_url'    => '',
				'date_shipped'    => $date_shipped,
				'status'          => WooPay_Order_Tracking_Sync::STATUS_FULFILLED,
				'items'           => [],
			];
		}
		return $shipments;
	}

	/**
	 * Return the hook that signals a ShipStation shipment notification.
	 *
	 * The sync class fires `send_webhook()` on this hook. Our persistence
	 * listener (registered separately via `register_persistence_hooks()`)
	 * runs on the same hook to capture the tracking payload before the
	 * webhook is built.
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
	 * Register the persistence listener for this provider.
	 *
	 * Called by `WooPay_Order_Tracking_Sync` during construction. Runs at
	 * priority 9 — earlier than `send_webhook()` (priority 10) — so that
	 * by the time `send_webhook()` triggers payload assembly, the meta
	 * key has already been populated.
	 */
	public static function register_persistence_hooks(): void {
		add_action(
			'woocommerce_shipstation_shipnotify',
			[ __CLASS__, 'persist_tracking_data' ],
			9,
			2
		);
	}

	/**
	 * Persist tracking data from the ShipStation hook arg into meta we own.
	 *
	 * Skips silently when WC Shipment Tracking is loaded (Phase 1 handles
	 * that case) or when the payload is missing a tracking number. Appends
	 * to the existing meta array so multiple shipments on one order are
	 * preserved.
	 *
	 * Sanitization happens on the read path (`get_shipments()`) — we store
	 * the raw payload here so the contract is "what ShipStation gave us."
	 *
	 * @param \WC_Order $order         Order being shipped.
	 * @param array     $tracking_data ShipStation hook payload:
	 *                                 tracking_number, carrier, ship_date.
	 */
	public static function persist_tracking_data( $order, $tracking_data ): void {
		if ( class_exists( 'WC_Shipment_Tracking' ) ) {
			return;
		}
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}
		if ( ! is_array( $tracking_data ) ) {
			return;
		}
		$tracking_number = isset( $tracking_data['tracking_number'] ) ? (string) $tracking_data['tracking_number'] : '';
		if ( '' === trim( $tracking_number ) ) {
			return;
		}

		$entry = [
			'tracking_number' => $tracking_number,
			'carrier_name'    => isset( $tracking_data['carrier'] ) ? (string) $tracking_data['carrier'] : '',
			'date_shipped'    => isset( $tracking_data['ship_date'] ) ? (int) $tracking_data['ship_date'] : 0,
		];

		$existing = $order->get_meta( self::META_KEY );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$existing[] = $entry;

		$order->update_meta_data( self::META_KEY, $existing );
		$order->save_meta_data();
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

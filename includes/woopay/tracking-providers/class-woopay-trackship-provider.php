<?php
/**
 * WooPay TrackShip Provider
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay\Tracking_Providers;

use WCPay\WooPay\WooPay_Order_Tracking_Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Captures carrier-status updates from the TrackShip plugin and enriches
 * shipments with canonical status + observation timestamp.
 *
 * **TrackShip is a status enricher, not a tracking-data writer.** It reads
 * `_wc_shipment_tracking_items` (written by WC Shipment Tracking / AST),
 * polls carriers via its SaaS backend, then pushes status updates back to
 * WordPress via the `wc/v1/tracking-webhook` REST endpoint. That endpoint
 * fires `trackship_shipment_status_trigger` synchronously *before* mutating
 * local state.
 *
 * Why both interfaces:
 *
 *   - `WooPay_Status_Overlay_Provider::overlay()` does the real work: looks
 *     up `_wcpay_trackship_tracking_items` meta and overlays `status` +
 *     `status_updated_at` on shipments produced by an earlier provider
 *     (typically Phase 1's WC Shipment Tracking / AST provider). This is
 *     why the class participates in the overlay chain.
 *
 *   - `WooPay_Tracking_Provider::get_shipments()` returns `[]` — TrackShip
 *     does NOT produce primary shipments. The class implements this
 *     interface anyway so it can sit alongside the other providers under
 *     a single shared contract for consumers that iterate either chain.
 *     The default configuration in
 *     `WooPay_Order_Tracking_Sync::get_providers()` does NOT register
 *     this class in the primary chain — TrackShip lives only in the
 *     overlay chain. The sync constructor discovers
 *     `register_persistence_hooks()` by iterating both chains with
 *     dedup-by-object-identity, so overlay-only providers register
 *     their listeners without needing a primary-chain stub entry.
 *
 * **What TrackShip never sees:** if a merchant doesn't have WC Shipment
 * Tracking or AST installed, TrackShip can't function — it has no source of
 * tracking numbers. That configuration is invalid from TrackShip's own
 * perspective, so no special handling is needed here.
 *
 * **Why we don't read TrackShip's DB table directly:** TrackShip stores
 * shipment state in a `{$wpdb->prefix}trackship_shipment` table whose schema
 * is not part of any public contract. Hooking the action keeps this provider
 * decoupled from internal table changes between TrackShip versions.
 */
class WooPay_TrackShip_Provider implements WooPay_Tracking_Provider, WooPay_Status_Overlay_Provider {

	/**
	 * Order meta key we own — stores normalized status entries keyed by
	 * tracking number, written by the persistence listener.
	 */
	const META_KEY = '_wcpay_trackship_tracking_items';

	/**
	 * Maximum length for forwarded string fields. Tracking numbers are
	 * overwhelmingly under 40 characters in practice; the cap is a defensive
	 * bound on pathological input from TrackShip's REST endpoint.
	 */
	const STRING_FIELD_MAX_LEN = 256;

	/**
	 * Hook fired by TrackShip's REST endpoint on every carrier-status update.
	 *
	 * Args: `($order_id, $previous_status, $tracking_event_status, $tracking_number)`.
	 *
	 * Verified against the TrackShip plugin source (tested against TrackShip
	 * for WooCommerce as published on wordpress.org). The hook is gated by
	 * TrackShip to fire only on real transitions and excludes orders already
	 * in 'delivered' status — our listener still does its own no-op check
	 * defensively.
	 */
	const TRACKING_HOOK = 'trackship_shipment_status_trigger';

	/**
	 * Detection sentinel for TrackShip. Used by `overlay()` and
	 * `persist_tracking_data()` to short-circuit when the plugin isn't
	 * loaded — even though both methods are also defensive about their
	 * inputs, gating on the canonical detection check makes the intent
	 * explicit and avoids unnecessary work.
	 *
	 * Note: `WooPay_Order_Tracking_Sync::get_order_shipments()` does NOT
	 * consult this method for overlay providers — the overlay chain runs
	 * all registered overlays regardless of `is_available()`. This method
	 * remains on the `WooPay_Tracking_Provider` interface contract; in
	 * practice it's used only as a shared sentinel by this class's own
	 * private codepaths.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function is_available( \WC_Order $order ): bool {
		return class_exists( 'Trackship_For_Woocommerce' );
	}

	/**
	 * Always returns an empty array. TrackShip does not produce primary
	 * shipments — it enriches shipments produced by other providers. The
	 * sync orchestrator falls through to the next provider when this
	 * returns empty.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array[]
	 */
	public function get_shipments( \WC_Order $order ): array {
		return [];
	}

	/**
	 * Intentionally returns an empty array — TrackShip has no hook that
	 * should trigger `send_webhook()` directly.
	 *
	 * The persistence listener for `trackship_shipment_status_trigger` is
	 * registered separately via the optional `register_persistence_hooks()`
	 * method, which the sync constructor discovers via `method_exists()`.
	 * That listener captures the status change into our owned meta key;
	 * the enriched status then rides the *next* `tracking_updated` webhook
	 * (fired by Phase 1 when AST/WC ST updates `_wc_shipment_tracking_items`)
	 * via the overlay path. No webhook fires off the TrackShip hook itself.
	 *
	 * @return array[]
	 */
	public function get_hooks(): array {
		return [];
	}

	/**
	 * Register the persistence listener for TrackShip's status update hook.
	 *
	 * Invoked by `WooPay_Order_Tracking_Sync::__construct()` once per request
	 * via `method_exists( $provider, 'register_persistence_hooks' )` —
	 * same convention Phase 2 (ShipStation) uses.
	 *
	 * Priority 9 matches the Phase 2 ShipStation listener. TrackShip's REST
	 * controller fires the action before mutating local state, so priority
	 * order does not matter in practice — but consistency with Phase 2 is
	 * worth more than the marginal simplification.
	 */
	public static function register_persistence_hooks(): void {
		add_action( self::TRACKING_HOOK, [ __CLASS__, 'persist_tracking_data' ], 9, 4 );
	}

	/**
	 * Persist a status update from TrackShip into our owned meta.
	 *
	 * Writes one entry per tracking number, upserting on
	 * `_wcpay_trackship_tracking_items`. The overlay path reads this key
	 * during webhook payload assembly.
	 *
	 * Noise suppression: skips writes when previous and new status match
	 * (defensive — TrackShip itself already gates this, but the check costs
	 * nothing and protects against future TrackShip behavior changes).
	 *
	 * Drops `unknown` and any non-mapped value silently — better to keep the
	 * previously-recorded status than to surface "I don't know" to the user.
	 *
	 * @param int    $order_id              Order ID.
	 * @param string $previous_status       Previous TrackShip status (`''` on first event).
	 * @param string $tracking_event_status New TrackShip status.
	 * @param string $tracking_number       Tracking number this event applies to.
	 */
	public static function persist_tracking_data( $order_id, $previous_status, $tracking_event_status, $tracking_number ): void {
		// Defensive: the persistence listener is registered unconditionally
		// during sync construction. If something else fires the same action
		// (hook-name collision, test code, unrelated plugin) while TrackShip
		// itself isn't loaded, bail out — we have no business writing to
		// `_wcpay_trackship_tracking_items` when the source plugin isn't
		// active.
		if ( ! class_exists( 'Trackship_For_Woocommerce' ) ) {
			return;
		}

		if ( ! is_numeric( $order_id ) ) {
			return;
		}

		$previous_status       = is_string( $previous_status ) ? $previous_status : '';
		$tracking_event_status = is_string( $tracking_event_status ) ? $tracking_event_status : '';
		// Tracking numbers from TrackShip's REST endpoint pass through
		// sanitize_field (wp_strip_all_tags + trim + length cap) before
		// storage. Phase 1-4 providers apply the same pattern; doing so
		// here keeps the wire payload guaranteed-printable without relying
		// on receiver re-escape, and defangs HTML/script content from a
		// compromised TrackShip API caller.
		$tracking_number = self::sanitize_field( $tracking_number );

		if ( '' === $tracking_event_status || '' === $tracking_number ) {
			return;
		}

		if ( $previous_status === $tracking_event_status ) {
			return;
		}

		$canonical = self::normalize_status( $tracking_event_status );
		if ( null === $canonical ) {
			return;
		}

		$order = wc_get_order( (int) $order_id );
		if ( ! $order ) {
			return;
		}

		$existing = $order->get_meta( self::META_KEY );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		$entries = [];
		foreach ( $existing as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$entry_number = isset( $entry['tracking_number'] ) ? (string) $entry['tracking_number'] : '';
			if ( $entry_number === $tracking_number ) {
				// Replace the existing entry for this tracking number.
				continue;
			}
			$entries[] = $entry;
		}

		$entries[] = [
			'tracking_number'   => $tracking_number,
			'status'            => $canonical,
			'status_updated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
		];

		$order->update_meta_data( self::META_KEY, $entries );
		$order->save_meta_data();
	}

	/**
	 * Enrich shipments with TrackShip-captured canonical status fields.
	 *
	 * Matches by `tracking_number`. Shipments without a corresponding
	 * TrackShip meta entry pass through untouched.
	 *
	 * Same-cardinality contract: never adds or removes shipments.
	 *
	 * @param \WC_Order $order     The WooCommerce order.
	 * @param array     $shipments Shipments produced by the primary chain.
	 * @return array Possibly-enriched shipments.
	 */
	public function overlay( \WC_Order $order, array $shipments ): array {
		if ( empty( $shipments ) ) {
			return $shipments;
		}

		// Detection-sentinel gate: if TrackShip isn't loaded, there's no
		// authoritative source of truth for the meta we'd be enriching from,
		// so skip the overlay even if stale entries linger from a previous
		// install. Symmetric with persist_tracking_data().
		if ( ! $this->is_available( $order ) ) {
			return $shipments;
		}

		$meta = $order->get_meta( self::META_KEY );
		if ( ! is_array( $meta ) || empty( $meta ) ) {
			return $shipments;
		}

		$by_number = [];
		foreach ( $meta as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$number = isset( $entry['tracking_number'] ) ? (string) $entry['tracking_number'] : '';
			if ( '' === $number ) {
				continue;
			}
			$by_number[ $number ] = $entry;
		}

		return array_map(
			static function ( $shipment ) use ( $by_number ) {
				$number = isset( $shipment['tracking_number'] ) ? (string) $shipment['tracking_number'] : '';
				if ( '' === $number || ! isset( $by_number[ $number ] ) ) {
					return $shipment;
				}
				$entry = $by_number[ $number ];
				if ( isset( $entry['status'] ) ) {
					$shipment['status'] = (string) $entry['status'];
				}
				if ( isset( $entry['status_updated_at'] ) ) {
					$validated = self::sanitize_status_updated_at( (string) $entry['status_updated_at'] );
					if ( '' !== $validated ) {
						$shipment['status_updated_at'] = $validated;
					}
				}
				return $shipment;
			},
			$shipments
		);
	}

	/**
	 * Map a TrackShip vocabulary status to the canonical
	 * `SHIPMENT_STATUSES` set. Returns null for values that should be
	 * dropped silently (e.g. `unknown`, garbage).
	 *
	 * Mapping notes:
	 *   - `pre_transit` → `in_transit` (carrier registered the shipment;
	 *     the 6-state canonical vocab doesn't differentiate this from
	 *     "carrier is moving the package")
	 *   - `available_for_pickup` is its own canonical state so the WooPay
	 *     UI can render distinct copy ("Ready for pickup" vs "Out for
	 *     delivery")
	 *   - `failure` / `return_to_sender` / `on_hold` all roll up under
	 *     `exception` — the canonical vocab doesn't distinguish exception
	 *     subtypes; the merchant's tracking page does
	 *   - `unknown` returns null (drop, don't propagate noise)
	 *
	 * @param string $trackship_status Raw status from TrackShip.
	 * @return string|null Canonical status, or null to drop the event.
	 */
	private static function normalize_status( string $trackship_status ): ?string {
		static $map = null;
		if ( null === $map ) {
			$map = [
				'pre_transit'          => WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT,
				'in_transit'           => WooPay_Order_Tracking_Sync::STATUS_IN_TRANSIT,
				'out_for_delivery'     => WooPay_Order_Tracking_Sync::STATUS_OUT_FOR_DELIVERY,
				'available_for_pickup' => WooPay_Order_Tracking_Sync::STATUS_AVAILABLE_FOR_PICKUP,
				'delivered'            => WooPay_Order_Tracking_Sync::STATUS_DELIVERED,
				'exception'            => WooPay_Order_Tracking_Sync::STATUS_EXCEPTION,
				'failure'              => WooPay_Order_Tracking_Sync::STATUS_EXCEPTION,
				'return_to_sender'     => WooPay_Order_Tracking_Sync::STATUS_EXCEPTION,
				'on_hold'              => WooPay_Order_Tracking_Sync::STATUS_EXCEPTION,
				// `unknown` and anything else falls through to null.
			];
		}

		return $map[ $trackship_status ] ?? null;
	}

	/**
	 * Strip HTML/JS, trim, and bound the length of a string field.
	 *
	 * Defense-in-depth: tracking numbers arrive via TrackShip's WP REST
	 * endpoint and are written to order meta before being forwarded in the
	 * webhook payload. Any string forwarded to WooPay should be defanged
	 * before transmission even though the receiver re-escapes on render.
	 *
	 * Mirrors the `sanitize_field()` pattern used by Phase 1-4 providers.
	 *
	 * @param mixed $value Raw value from the hook arg or order meta.
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
	 * Truncate a string to a max length safely on hosts without the mbstring
	 * extension. Falls back to byte-level substr() when mb_substr() is not
	 * available; that fallback may slice a multi-byte character mid-byte,
	 * but the values forwarded here (tracking numbers) are overwhelmingly
	 * ASCII.
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

	/**
	 * Validate an ISO 8601 UTC timestamp string before forwarding it to the
	 * webhook payload. Returns the validated value or `''` if invalid.
	 *
	 * Defense-in-depth: while `persist_tracking_data()` writes this field
	 * via `gmdate( 'Y-m-d\TH:i:s\Z' )`, order meta is mutable by other
	 * plugins/admins between write and read. The read path treats the
	 * stored value as untrusted: strict format match plus round-trip
	 * verification rejects any value that doesn't match the exact shape
	 * the receiver expects, so malformed/tampered values are silently
	 * dropped rather than propagated to WooPay.
	 *
	 * @param string $value Raw value from `_wcpay_trackship_tracking_items` meta.
	 * @return string Validated ISO 8601 UTC string, or '' if invalid.
	 */
	private static function sanitize_status_updated_at( string $value ): string {
		// Length bound and quick shape check; rejects newlines, control
		// chars, embedded NULs without needing a regex pass.
		if ( strlen( $value ) > 32 ) {
			return '';
		}
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) ) {
			return '';
		}
		// Round-trip via DateTime to reject calendar-invalid values like
		// "2026-13-99T25:99:99Z" that the regex alone would accept.
		$dt = \DateTime::createFromFormat( 'Y-m-d\TH:i:s\Z', $value, new \DateTimeZone( 'UTC' ) );
		if ( ! $dt || $dt->format( 'Y-m-d\TH:i:s\Z' ) !== $value ) {
			return '';
		}
		return $value;
	}
}

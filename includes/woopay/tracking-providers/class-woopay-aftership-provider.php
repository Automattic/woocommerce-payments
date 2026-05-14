<?php
/**
 * WooPay AfterShip Provider
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay\Tracking_Providers;

use WCPay\WooPay\WooPay_Order_Tracking_Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Reads tracking data written by the AfterShip WooCommerce Tracking plugin
 * (1M+ downloads).
 *
 * AfterShip stores tracking in `_aftership_tracking_items` order meta as an
 * array of per-shipment entries. It does NOT fire a custom `do_action` for
 * tracking add/update — verified against the plugin source: every save path
 * (admin metabox AJAX, REST API, legacy metabox save on
 * `woocommerce_process_shop_order_meta`) calls
 * `$order->update_meta_data('_aftership_tracking_items', ...)` and
 * `$order->save()` directly. We therefore hook the WordPress core
 * meta-write events filtered to this meta key, the same approach the
 * Phase 1 WC Shipment Tracking / AST provider uses for its own meta key.
 *
 * AfterShip's "fulfillments v2" mode stores some data in a separate
 * `_aftership_fulfillments` meta key, but every v2 write path also calls
 * `save_tracking_items()` which writes `_aftership_tracking_items` as a
 * sync step — so hooking only this key is sufficient regardless of the
 * AfterShip UI mode.
 */
class WooPay_AfterShip_Provider implements WooPay_Tracking_Provider {

	/**
	 * Meta key AfterShip writes its tracking entries to.
	 */
	const META_KEY = '_aftership_tracking_items';

	/**
	 * Maximum length for forwarded string fields.
	 */
	const STRING_FIELD_MAX_LEN = 256;

	/**
	 * Whether AfterShip is active and the order has tracking entries.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function is_available( \WC_Order $order ): bool {
		if ( ! class_exists( 'AfterShip' ) ) {
			return false;
		}

		$items = $order->get_meta( self::META_KEY );
		return ! empty( $items ) && is_array( $items );
	}

	/**
	 * Extract and normalize shipments from AfterShip meta.
	 *
	 * AfterShip stores `slug` (e.g. `fedex`) — a machine slug, not a display
	 * name — and a `Y-m-d` `ship_date` inside `additional_fields`. We pass
	 * the slug through as `carrier_name` (consistent with what AfterShip
	 * itself stores; WooPay handles display) and validate the date with a
	 * strict round-trip check to catch malformed values. AfterShip
	 * generates tracking URLs server-side and does not store them in meta,
	 * so `tracking_url` is always empty.
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

			// Sanitize before checking emptiness: a non-empty raw value can
			// reduce to empty after wp_strip_all_tags() (e.g. tags-only input
			// like "<script>X</script>"), and we must not emit a shipment
			// with an empty tracking_number.
			$tracking_number = self::sanitize_field( $item['tracking_number'] ?? '' );
			if ( '' === $tracking_number ) {
				continue;
			}

			// Defensive: tolerate `additional_fields` being a non-array (e.g. a
			// string or null) without raising a PHP 8+ "Cannot access offset of
			// type X" notice. The is_string guard on $raw_date below still
			// produces a safe empty date_shipped for any non-string value.
			$additional_fields = is_array( $item['additional_fields'] ?? null ) ? $item['additional_fields'] : [];

			$date_shipped = '';
			$raw_date     = $additional_fields['ship_date'] ?? '';
			if ( is_string( $raw_date ) && '' !== $raw_date ) {
				$dt = \DateTime::createFromFormat( 'Y-m-d', $raw_date );
				if ( $dt && $dt->format( 'Y-m-d' ) === $raw_date ) {
					$date_shipped = $raw_date;
				}
			}

			$shipments[] = [
				'tracking_number' => $tracking_number,
				'carrier_name'    => self::sanitize_field( $item['slug'] ?? '' ),
				// AfterShip generates tracking URLs server-side and doesn't
				// store them in meta. If a future AfterShip release starts
				// persisting URLs in `_aftership_tracking_items`, switch this
				// to `self::sanitize_url( $item['tracking_url'] ?? '' )` and
				// add a `sanitize_url()` helper mirroring Phase 1's pattern.
				'tracking_url'    => '',
				'date_shipped'    => $date_shipped,
				'status'          => WooPay_Order_Tracking_Sync::STATUS_FULFILLED,
				'items'           => [],
			];
		}

		return $shipments;
	}

	/**
	 * Return hooks that signal tracking changes.
	 *
	 * Verified against AfterShip's source: the plugin fires no custom
	 * `do_action` for tracking add/update — it only writes
	 * `_aftership_tracking_items` and saves the order. We therefore hook
	 * the WordPress core meta-write events instead, filtered to this key.
	 *
	 * Registering both `*_post_meta` (legacy CPT order storage) and
	 * `*_order_meta` (HPOS) covers WooCommerce's two storage backends.
	 *
	 * @return array[]
	 */
	public function get_hooks(): array {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- 'meta_key' here is an array key in the hook spec, not a DB query argument.
		return [
			[
				'hook'      => 'added_post_meta',
				'arg_count' => 4,
				'meta_key'  => self::META_KEY,
			],
			[
				'hook'      => 'updated_post_meta',
				'arg_count' => 4,
				'meta_key'  => self::META_KEY,
			],
			[
				'hook'      => 'added_order_meta',
				'arg_count' => 4,
				'meta_key'  => self::META_KEY,
			],
			[
				'hook'      => 'updated_order_meta',
				'arg_count' => 4,
				'meta_key'  => self::META_KEY,
			],
		];
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}

	/**
	 * Strip HTML/JS, trim, and bound the length of a string field.
	 *
	 * Defense-in-depth: tracking meta is written by 3rd-party plugins, so
	 * any string forwarded to WooPay should be defanged before transmission
	 * even though the receiver re-escapes on render.
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
	 * Truncate a string to a max length safely on hosts without the
	 * mbstring extension. Falls back to byte-level substr() when
	 * mb_substr() is not available; that fallback may slice a multi-byte
	 * character mid-byte, but the values we forward are tracking numbers
	 * and short carrier slugs which are overwhelmingly ASCII.
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

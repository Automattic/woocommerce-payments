<?php
/**
 * WooPay Fulfillments API Provider
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay\Tracking_Providers;

use WCPay\WooPay\WooPay_Order_Tracking_Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Reads tracking data from WooCommerce Core's Fulfillments API
 * (WC 10.2+, behind woocommerce_feature_fulfillments_enabled flag).
 *
 * Tracking data is stored as Fulfillment metadata: _tracking_number,
 * _shipment_provider, _tracking_url, _date_shipped.
 */
class WooPay_Fulfillments_API_Provider implements WooPay_Tracking_Provider {

	/**
	 * Maximum length for forwarded string fields.
	 */
	const STRING_FIELD_MAX_LEN = 256;

	/**
	 * Per-instance cache of the fulfillments-data-store read result, keyed
	 * by order ID. The provider chain calls `is_available()` and then
	 * `get_shipments()` for the same order in immediate succession; without
	 * this cache, the data store would be queried twice per qualifying
	 * order. Instances are constructed once per request so this naturally
	 * resets between requests.
	 *
	 * @var array<int, array>
	 */
	private $fulfillments_cache = [];

	/**
	 * Whether the Fulfillments API is available and the order has fulfillments.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function is_available( \WC_Order $order ): bool {
		if ( ! self::api_class_exists() ) {
			return false;
		}

		$fulfillments = $this->read_fulfillments( $order );
		return ! empty( $fulfillments );
	}

	/**
	 * Extract and normalize shipments from Fulfillment metadata.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return array[]
	 */
	public function get_shipments( \WC_Order $order ): array {
		if ( ! self::api_class_exists() ) {
			return [];
		}

		$fulfillments = $this->read_fulfillments( $order );
		if ( empty( $fulfillments ) ) {
			return [];
		}

		$shipments = [];

		foreach ( $fulfillments as $fulfillment ) {
			// Sanitize before checking emptiness: a non-empty raw value can
			// reduce to empty after wp_strip_all_tags() (e.g. tags-only
			// input), and we must not emit a shipment with an empty
			// tracking_number.
			$tracking_number = self::sanitize_field( self::read_meta( $fulfillment, '_tracking_number' ) );
			if ( '' === $tracking_number ) {
				continue;
			}

			$shipments[] = [
				'tracking_number' => $tracking_number,
				'carrier_name'    => self::sanitize_field( self::read_meta( $fulfillment, '_shipment_provider' ) ),
				'tracking_url'    => self::sanitize_url( self::read_meta( $fulfillment, '_tracking_url' ) ),
				'date_shipped'    => self::extract_date_shipped( $fulfillment ),
				'status'          => self::extract_status( $fulfillment ),
				'items'           => [],
			];
		}

		return $shipments;
	}

	/**
	 * Return hooks fired by the Fulfillments API DataStore on create/update/delete/fulfill.
	 *
	 * @return array[]
	 */
	public function get_hooks(): array {
		return [
			[
				'hook'      => 'woocommerce_fulfillment_after_create',
				'arg_count' => 1,
			],
			[
				'hook'      => 'woocommerce_fulfillment_after_update',
				'arg_count' => 3,
			],
			[
				'hook'      => 'woocommerce_fulfillment_after_delete',
				'arg_count' => 1,
			],
			[
				'hook'      => 'woocommerce_fulfillment_after_fulfill',
				'arg_count' => 1,
			],
		];
	}

	/**
	 * Detect Fulfillments API availability without coupling to internal DataStore class.
	 *
	 * @return bool
	 */
	private static function api_class_exists(): bool {
		return class_exists( '\Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment' );
	}

	/**
	 * Load fulfillments for an order via the WC data store.
	 *
	 * Caches the result per order ID so the provider chain's back-to-back
	 * `is_available()` + `get_shipments()` calls only hit the data store once.
	 *
	 * @param \WC_Order $order The order.
	 * @return array Array of Fulfillment objects (typed as WC_Data subclass).
	 */
	private function read_fulfillments( \WC_Order $order ): array {
		$order_id = (int) $order->get_id();

		if ( array_key_exists( $order_id, $this->fulfillments_cache ) ) {
			return $this->fulfillments_cache[ $order_id ];
		}

		try {
			$data_store   = \WC_Data_Store::load( 'order-fulfillment' );
			$fulfillments = $data_store->read_fulfillments( \WC_Order::class, (string) $order_id );
		} catch ( \Exception $e ) {
			$this->fulfillments_cache[ $order_id ] = [];
			return [];
		}

		$result                                = is_array( $fulfillments ) ? $fulfillments : [];
		$this->fulfillments_cache[ $order_id ] = $result;
		return $result;
	}

	/**
	 * Read a metadata value from a Fulfillment object.
	 *
	 * @param object $fulfillment Fulfillment instance (WC_Data).
	 * @param string $key         Meta key.
	 * @return string
	 */
	private static function read_meta( $fulfillment, string $key ): string {
		if ( ! method_exists( $fulfillment, 'get_meta' ) ) {
			return '';
		}
		$value = $fulfillment->get_meta( $key );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Pull a Y-m-d ship date from the fulfillment.
	 *
	 * Prefers the explicit `_date_shipped` meta key, falling back to the
	 * fulfillment's last-updated timestamp (which approximates the ship
	 * date when the fulfillment was created at-shipment-time).
	 *
	 * @param object $fulfillment Fulfillment instance.
	 * @return string
	 */
	private static function extract_date_shipped( $fulfillment ): string {
		$raw = self::read_meta( $fulfillment, '_date_shipped' );
		if ( '' !== $raw ) {
			$timestamp = strtotime( $raw );
			if ( false !== $timestamp && $timestamp > 0 ) {
				return gmdate( 'Y-m-d', $timestamp );
			}
		}

		if ( method_exists( $fulfillment, 'get_date_updated' ) ) {
			$date = $fulfillment->get_date_updated();
			if ( $date instanceof \WC_DateTime ) {
				return $date->date( 'Y-m-d' );
			}
		}

		return '';
	}

	/**
	 * Map a fulfillment status to a normalized shipment status.
	 *
	 * WC's Fulfillments API uses two statuses (`Fulfillment.php:214`):
	 * `fulfilled` (the `is_fulfilled` flag is true) and `unfulfilled` (the
	 * flag is false). Both correspond to the canonical `fulfilled` shipment
	 * state — we only emit a shipment here when a tracking number exists
	 * (`get_shipments()` skips empty tracking numbers), and "tracking added,
	 * carrier hasn't acknowledged" is exactly what canonical `fulfilled`
	 * means. Mapping both upstream avoids routing the expected
	 * `unfulfilled` value through `ensure_canonical_status()`, which would
	 * log a notice on every emission.
	 *
	 * Anything else (e.g. a new status WC adds in the future) passes through
	 * verbatim so the canonical-coercion logger can flag it as genuinely
	 * unexpected.
	 *
	 * @param object $fulfillment Fulfillment instance.
	 * @return string
	 */
	private static function extract_status( $fulfillment ): string {
		if ( ! method_exists( $fulfillment, 'get_status' ) ) {
			return WooPay_Order_Tracking_Sync::STATUS_FULFILLED;
		}
		$status = $fulfillment->get_status();
		if ( ! is_string( $status ) || '' === $status ) {
			return WooPay_Order_Tracking_Sync::STATUS_FULFILLED;
		}
		if ( 'fulfilled' === $status || 'unfulfilled' === $status ) {
			return WooPay_Order_Tracking_Sync::STATUS_FULFILLED;
		}
		return self::truncate( $status, 64 );
	}

	/**
	 * Strip HTML/JS, trim, and bound the length of a string field.
	 *
	 * @param mixed $value Raw value.
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
	 * Validate that a tracking URL uses an allowed scheme.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function sanitize_url( $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		$clean = esc_url_raw( $value, [ 'http', 'https' ] );
		return is_string( $clean ) ? self::truncate( $clean, 2048 ) : '';
	}

	/**
	 * Truncate a string to a max length safely on hosts without the
	 * mbstring extension. Falls back to byte-level substr() when
	 * mb_substr() is not available.
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

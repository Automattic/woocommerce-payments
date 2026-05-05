<?php
/**
 * WooPay Shipment Tracking Provider
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay\Tracking_Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Reads tracking data from the WC Shipment Tracking plugin and
 * Advanced Shipment Tracking (AST) — both use the same meta key.
 */
class WooPay_Shipment_Tracking_Provider implements WooPay_Tracking_Provider {

	/**
	 * Meta key used by both WC Shipment Tracking and AST.
	 */
	const META_KEY = '_wc_shipment_tracking_items';

	/**
	 * Maximum length for forwarded string fields. Tracking numbers and
	 * carrier names should be well under this in practice; the cap defends
	 * against pathological input from third-party plugins.
	 */
	const STRING_FIELD_MAX_LEN = 256;

	/**
	 * Whether a compatible tracking plugin is active and the order has tracking data.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function is_available( \WC_Order $order ): bool {
		if ( ! class_exists( 'WC_Shipment_Tracking_Actions' ) && ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			return false;
		}

		$items = $order->get_meta( self::META_KEY );
		return ! empty( $items ) && is_array( $items );
	}

	/**
	 * Extract and normalize shipments from WC Shipment Tracking meta.
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
			// Sanitize before checking emptiness: a non-empty raw value can
			// reduce to empty after wp_strip_all_tags() (e.g. tags-only input
			// like "<script>X</script>"), and we must not emit a shipment
			// with an empty tracking_number.
			$tracking_number = self::sanitize_field( $item['tracking_number'] ?? '' );
			if ( '' === $tracking_number ) {
				continue;
			}

			$carrier_raw = ! empty( $item['tracking_provider'] )
				? $item['tracking_provider']
				: ( $item['custom_tracking_provider'] ?? '' );

			$date_shipped = '';
			if ( ! empty( $item['date_shipped'] ) ) {
				$timestamp = (int) $item['date_shipped'];
				if ( $timestamp > 0 ) {
					$date_shipped = gmdate( 'Y-m-d', $timestamp );
				}
			}

			$shipments[] = [
				'tracking_number' => $tracking_number,
				'carrier_name'    => self::sanitize_field( $carrier_raw ),
				'tracking_url'    => self::sanitize_url( $item['custom_tracking_link'] ?? '' ),
				'date_shipped'    => $date_shipped,
				'status'          => 'fulfilled',
				'items'           => [],
			];
		}

		return $shipments;
	}

	/**
	 * Return hooks that signal tracking changes.
	 *
	 * Both WC Shipment Tracking and AST fire these hooks.
	 *
	 * @return array[]
	 */
	public function get_hooks(): array {
		return [
			[
				'hook'      => 'woocommerce_shipment_tracking_added',
				'arg_count' => 2,
			],
			[
				'hook'      => 'woocommerce_shipment_tracking_deleted',
				'arg_count' => 2,
			],
		];
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
		return mb_substr( $clean, 0, self::STRING_FIELD_MAX_LEN );
	}

	/**
	 * Validate that a tracking URL uses an allowed scheme. Returns empty
	 * string for any non-http(s) URL (rejects javascript:, data:, file:, etc.).
	 *
	 * @param mixed $value Raw value from order meta.
	 * @return string
	 */
	private static function sanitize_url( $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		$clean = esc_url_raw( $value, [ 'http', 'https' ] );
		return is_string( $clean ) ? mb_substr( $clean, 0, 2048 ) : '';
	}
}

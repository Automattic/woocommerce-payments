<?php
/**
 * WooPay Fulfillments API Provider
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay\Tracking_Providers;

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
			$tracking_number = self::read_meta( $fulfillment, '_tracking_number' );
			if ( '' === $tracking_number ) {
				continue;
			}

			$shipments[] = [
				'tracking_number' => self::sanitize_field( $tracking_number ),
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
	 * @param \WC_Order $order The order.
	 * @return array Array of Fulfillment objects (typed as WC_Data subclass).
	 */
	private function read_fulfillments( \WC_Order $order ): array {
		try {
			$data_store   = \WC_Data_Store::load( 'order-fulfillment' );
			$fulfillments = $data_store->read_fulfillments( \WC_Order::class, (string) $order->get_id() );
		} catch ( \Exception $e ) {
			return [];
		}

		return is_array( $fulfillments ) ? $fulfillments : [];
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
	 * Pull a Y-m-d ship date from the fulfillment, falling back to creation time.
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
	 * @param object $fulfillment Fulfillment instance.
	 * @return string
	 */
	private static function extract_status( $fulfillment ): string {
		if ( ! method_exists( $fulfillment, 'get_status' ) ) {
			return 'fulfilled';
		}
		$status = $fulfillment->get_status();
		if ( ! is_string( $status ) || '' === $status ) {
			return 'fulfilled';
		}
		// WC fulfillment statuses include 'fulfilled', 'unfulfilled', etc.
		// Pass through verbatim — WooPay normalizes for display.
		return mb_substr( $status, 0, 64 );
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
		$clean = wp_strip_all_tags( (string) $value );
		return mb_substr( $clean, 0, self::STRING_FIELD_MAX_LEN );
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
		return is_string( $clean ) ? mb_substr( $clean, 0, 2048 ) : '';
	}
}

<?php
/**
 * WooPay Shipment Tracking Provider
 *
 * @package WooCommerce\Payments
 */

declare( strict_types=1 );

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
			$tracking_number = $item['tracking_number'] ?? '';
			if ( empty( $tracking_number ) ) {
				continue;
			}

			$carrier_name = ! empty( $item['tracking_provider'] )
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
				'tracking_number' => (string) $tracking_number,
				'carrier_name'    => (string) $carrier_name,
				'tracking_url'    => (string) ( $item['custom_tracking_link'] ?? '' ),
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
}

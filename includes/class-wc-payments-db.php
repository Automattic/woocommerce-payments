<?php
/**
 * WC_Payments_DB class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wrapper class for accessing the database.
 */
class WC_Payments_DB {
	const META_KEY_INTENT_ID = '_intent_id';
	const META_KEY_CHARGE_ID = '_charge_id';

	/**
	 * Retrieve an order from the DB using a corresponding Stripe charge ID.
	 *
	 * @param string $charge_id Charge ID corresponding to an order ID.
	 *
	 * @return boolean|WC_Order|WC_Order_Refund
	 */
	public function order_from_charge_id( $charge_id ) {
		$order_id = $this->order_id_from_meta_key_value( self::META_KEY_CHARGE_ID, $charge_id );

		if ( $order_id ) {
			return $this->order_from_order_id( $order_id );
		}
		return false;
	}

	/**
	 * Retrieve orders from the DB using a list of corresponding Stripe charge IDs.
	 *
	 * @param array $charge_ids List of charge IDs corresponding to an order ID.
	 *
	 * @return array[]
	 */
	public function orders_with_charge_id_from_charge_ids( array $charge_ids ): array {
		global $wpdb;

		$charge_id_placeholder = implode( ',', array_fill( 0, count( $charge_ids ), '%s' ) );

		// The order ID is saved to DB in `WC_Payment_Gateway_WCPay::process_payment()`.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"SELECT DISTINCT ID as order_id, meta.meta_value as charge_id FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_key = '_charge_id' AND meta.meta_value IN ($charge_id_placeholder)",
				$charge_ids
			)
		);

		return array_map(
			function ( stdClass $row ) : array {
				return [
					'order'     => $this->order_from_order_id( $row->order_id ),
					'charge_id' => $row->charge_id,
				];
			},
			$results
		);
	}

	/**
	 * Retrieve an order from the DB using a corresponding Stripe intent ID.
	 *
	 * @param string $intent_id Intent ID corresponding to an order ID.
	 *
	 * @return boolean|WC_Order|WC_Order_Refund
	 */
	public function order_from_intent_id( $intent_id ) {
		$order_id = $this->order_id_from_meta_key_value( self::META_KEY_INTENT_ID, $intent_id );

		if ( $order_id ) {
			return $this->order_from_order_id( $order_id );
		}
		return false;
	}

	/**
	 * Retrieve an order ID from the DB using a meta key value pair.
	 *
	 * @param string $meta_key   Either '_intent_id' or '_charge_id'.
	 * @param string $meta_value Value for the meta key.
	 *
	 * @return null|string
	 */
	private function order_id_from_meta_key_value( $meta_key, $meta_value ) {
		global $wpdb;

		// The order ID is saved to DB in `WC_Payment_Gateway_WCPay::process_payment()`.
		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DISTINCT MAX(ID) FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_key = %s AND meta.meta_value = %s",
				$meta_key,
				$meta_value
			)
		);
		return $order_id;
	}

	/**
	 * Retrieve an order using order ID.
	 *
	 * @param string $order_id       WC Order Id.
	 *
	 * @return bool|WC_Order|WC_Order_Refund
	 */
	public function order_from_order_id( $order_id ) {
		return wc_get_order( ( $order_id ) );
	}
}

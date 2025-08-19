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

		// The order ID is saved to DB in `WC_Payment_Gateway_WCPay::process_payment()`.
		$orders = wc_get_orders(
			[
				'limit'        => count( $charge_ids ),
				'meta_key'     => self::META_KEY_CHARGE_ID, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'   => $charge_ids, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare' => 'IN',
			]
		);

		return array_map(
			function ( WC_Order $order ): array {
				return [
					'order'     => $order,
					'charge_id' => $order->get_meta( self::META_KEY_CHARGE_ID ),
				];
			},
			$orders
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

		// Don't proceed if the meta key or value is empty.
		if ( ! $meta_key || ! $meta_value ) {
			return null;
		}
		$orders = wc_get_orders(
			[
				'limit'      => 1,
				'meta_key'   => $meta_key, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $meta_value, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		if ( $orders && ! empty( $orders ) ) {
			/**
			 * As wc_get_orders may also return stdClass, Psalm infers error.
			 *
			 * @psalm-suppress UndefinedMethod
			 */
			return (string) $orders[0]->get_id();
		}
		return null;
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

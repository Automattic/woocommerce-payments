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
	public function order_from_charge_id( string $charge_id ) {
		return $this->order_from_meta_key_value( self::META_KEY_CHARGE_ID, $charge_id );
	}

	/**
	 * Retrieve orders from the DB using a list of corresponding Stripe charge IDs.
	 *
	 * @param array $charge_ids List of charge IDs corresponding to an order ID.
	 *
	 * @return array|WC_Order[]|WC_Order_Refund[]
	 */
	public function orders_from_charge_ids( array $charge_ids ): array {
		return $this->orders_from_meta_key_values( self::META_KEY_CHARGE_ID, $charge_ids );
	}

	/**
	 * Retrieve an order from the DB using a corresponding Stripe intent ID.
	 *
	 * @param string $intent_id Intent ID corresponding to an order ID.
	 *
	 * @return boolean|WC_Order|WC_Order_Refund
	 */
	public function order_from_intent_id( $intent_id ) {
		return $this->order_from_meta_key_value( self::META_KEY_INTENT_ID, $intent_id );
	}

	/**
	 * Retrieve an order using a meta key value pair.
	 *
	 * @param string $meta_key   Either '_intent_id' or '_charge_id'.
	 * @param string $meta_value Value for the meta key.
	 *
	 * @return boolean|WC_Order|WC_Order_Refund
	 */
	private function order_from_meta_key_value( string $meta_key, string $meta_value ) {
		$custom_query_var_handler = function( $query, $query_vars ) use ( $meta_key ) {
			if ( ! empty( $query_vars[ $meta_key ] ) ) {
				$query['meta_query'][] = [
					'key'   => $meta_key,
					'value' => esc_attr( $query_vars[ $meta_key ] ),
				];
			}

			return $query;
		};

		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $custom_query_var_handler, 10, 2 );
		$orders = wc_get_orders(
			[
				'limit'   => 1,
				$meta_key => $meta_value,
			]
		);
		remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $custom_query_var_handler, 10 );

		return $orders[0] ?? false;
	}

	/**
	 * Retrieve orders using a meta key value pair for each value provided.
	 *
	 * @param string $meta_key   Either '_intent_id' or '_charge_id'.
	 * @param array  $meta_value Value for the meta key.
	 *
	 * @return array|WC_Order[]|WC_Order_Refund[]
	 */
	private function orders_from_meta_key_values( string $meta_key, array $meta_value ): array {
		$custom_query_var_handler = function( $query, $query_vars ) use ( $meta_key ) {
			if ( ! empty( $query_vars[ $meta_key ] ) ) {
				$query['meta_query'][] = [
					'key'     => $meta_key,
					'value'   => $query_vars[ $meta_key ],
					'compare' => 'IN',
				];
			}

			return $query;
		};

		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $custom_query_var_handler, 10, 2 );
		$orders = wc_get_orders(
			[
				'limit'   => -1,
				$meta_key => $meta_value,
			]
		);
		remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $custom_query_var_handler, 10 );

		return $orders ?? [];
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

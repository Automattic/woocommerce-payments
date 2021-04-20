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
			return wc_get_order( $order_id );
		}
		return false;
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
			return wc_get_order( $order_id );
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
				"SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_key = %s AND meta.meta_value = %s",
				$meta_key,
				$meta_value
			)
		);
		return $order_id;
	}
}

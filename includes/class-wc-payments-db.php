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
	/**
	 * Retrieve an order from the DB using a corresponding Stripe charge ID.
	 *
	 * @param string $charge_id Charge ID corresponding to an order ID.
	 *
	 * @return boolean|WC_Order|WC_Order_Refund
	 */
	public function order_from_charge_id( string $charge_id ) {
		$order_id = $this->order_id_from_order_meta( '_charge_id', $charge_id );
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
	public function order_from_intent_id( string $intent_id ) {
		$order_id = $this->order_id_from_order_meta( '_intent_id', $intent_id );
		if ( $order_id ) {
			return wc_get_order( $order_id );
		}
		return false;
	}

	/**
	 * Retrieve an order ID from the DB using a corresponding Stripe charge ID.
	 *
	 * @param string $meta_key   The order meta key.
	 * @param string $meta_value The order meta value.
	 *
	 * @return null|string
	 */
	private function order_id_from_order_meta( string $meta_key, string $meta_value ) {
		global $wpdb;

		// The order ID is saved to DB in `WC_Payment_Gateway_WCPay::process_payment()`.
		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s",
				$meta_value,
				$meta_key
			)
		);
		return $order_id;
	}
}

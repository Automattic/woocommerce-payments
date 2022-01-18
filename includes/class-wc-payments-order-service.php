<?php
/**
 * Class WC_Payments_Order_Service
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class handling order functionality.
 */
class WC_Payments_Order_Service {

	/**
	 * Updates an order when the payment is complete. Also implements a lock to ensure the order cannot be marked as complete multiple times due to
	 * possible race conditions when the paid webhook from Stripe is handled during this request.
	 *
	 * @param WC_Order $order     The order.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 *
	 * @return void
	 */
	public function mark_payment_completed( $order, $intent_id ) {
		// Read the latest order properties from the database to avoid race conditions when the paid webhook was handled during this request.
		$order->get_data_store()->read( $order );

		if ( $order->has_status( [ 'processing', 'completed' ] ) ) {
			return;
		}

		if ( self::is_order_locked( $order, $intent_id ) ) {
			return;
		}

		self::lock_order_payment( $order, $intent_id );
		$order->payment_complete( $intent_id );
		self::unlock_order_payment( $order );
	}

	/**
	 * Check if order is locked for payment processing
	 *
	 * @param WC_Order $order  The order that is being paid.
	 * @param string   $intent_id The id of the intent that is being processed.
	 *
	 * @return bool    A flag that indicates whether the order is already locked.
	 */
	private function is_order_locked( $order, $intent_id = null ) {
		$order_id       = $order->get_id();
		$transient_name = 'wcpay_processing_intent_' . $order_id;
		$processing     = get_transient( $transient_name );

		// Block the process if the same intent is already being handled.
		return ( '-1' === $processing || ( isset( $intent_id ) && $processing === $intent_id ) );
	}

	/**
	 * Lock an order for payment intent processing for 5 minutes.
	 *
	 * @param WC_Order $order  The order that is being paid.
	 * @param string   $intent_id The id of the intent that is being processed.
	 *
	 * @return void
	 */
	private function lock_order_payment( $order, $intent_id = null ) {
		$order_id       = $order->get_id();
		$transient_name = 'wcpay_processing_intent_' . $order_id;

		set_transient( $transient_name, empty( $intent_id ) ? '-1' : $intent_id, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Unlocks an order for processing by payment intents.
	 *
	 * @param WC_Order $order The order that is being unlocked.
	 *
	 * @return void
	 */
	private function unlock_order_payment( $order ) {
		$order_id = $order->get_id();
		delete_transient( 'wcpay_processing_intent_' . $order_id );
	}
}

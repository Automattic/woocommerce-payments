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

	public function update_payment_status( $status, $order, $intent_id, $message = '' ) {
		// Read the latest order properties from the database to avoid race conditions when the paid webhook was handled during this request.
		$order->get_data_store()->read( $order );

		if ( $this->is_order_locked( $order, $intent_id ) ) {
			return;
		}

		$this->order   = $order;
		$this->intent  = $this->payments_api_client->get_intent( $intent_id );
		$this->message = $message;

		// Lock the order.
		$this->lock_order_payment();

		// Process the order/payment status change.
		switch ( $status ) {
			case 'completed':
				$this->mark_payment_completed();
				break;

			case 'failed':
				$this->mark_payment_failed();
				break;
		}

		// Unlock the order.
		$this->unlock_order_payment();
	}

	/**
	 * Updates an order when the payment is complete.
	 *
	 * @return void
	 */
	private function mark_payment_completed() {

		if ( $this->order->has_status( [ 'processing', 'completed' ] )
			|| 'succeeded' === $this->order->get_meta( '_intention_status' ) ) {
			return;
		}

		$this->add_success_note();
		$this->order->payment_complete( $this->intent->get_id() );
	}

	private function add_success_note() {
		$amount         = $this->order->get_total();
		$payment_needed = $amount > 0;

		if ( $payment_needed ) {
			$transaction_url = $this->compose_transaction_url();
			$note            = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
					/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
					__( 'A payment of %1$s was <strong>successfully charged</strong> using WooCommerce Payments (<a>%2$s</a>).', 'woocommerce-payments' ),
					[
						'strong' => '<strong>',
						'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
					]
				),
				WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $amount, [ 'currency' => $this->order->get_currency() ] ), $this->order ),
				$this->intent->get_charge_id()
			);
		}

		if ( $note ) {
			$note .= ' ' . $this->message;
		}

		if ( $note ) {
			$this->order->add_order_note( $note );
		}
	}

	/**
	 * Updates an order to failed status, while adding a note with a link to the failed transaction.
	 *
	 * @param WC_Order $order   Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $message Optional message to add to the failed note.
	 *
	 * @return void
	 */
	private function mark_payment_failed( $order, $intent_id, $message = '' ) {
		// Read the latest order properties from the database to avoid race conditions when the paid webhook was handled during this request.
		$order->get_data_store()->read( $order );

		if ( $order->has_status( [ 'failed' ] )
			|| 'failed' === $order->get_meta( '_intention_status' )
			|| 'succeeded' === $order->get_meta( '_intention_status' )
			|| $this->is_order_locked( $order, $intent_id ) ) {
			return;
		}

		$transaction_url = self::compose_transaction_url( $order->get_meta( '_charge_id' ) );
		$note            = sprintf(
			self::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( 'A payment of %1$s <strong>failed</strong> using WooCommerce Payments (<a>%2$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ), $order ),
			$order->get_meta( '_intent_id' )
		);

		if ( $message ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
		$order->update_meta_data( '_intention_status', 'failed' );
		$order->update_status( 'failed' );
	}

	/**
	 * Composes url for transaction details page.
	 *
	 * @return string            Transaction details page url.
	 */
	protected function compose_transaction_url() {
		$charge_id = $this->intent->get_charge_id();
		if ( empty( $charge_id ) ) {
			return '';
		}

		return add_query_arg(
			[
				'page' => 'wc-admin',
				'path' => '/payments/transactions/details',
				'id'   => $charge_id,
			],
			admin_url( 'admin.php' )
		);
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
	 * @return void
	 */
	private function lock_order_payment() {
		$order_id       = $this->order->get_id();
		$intent_id      = $this->intent->get_id();
		$transient_name = 'wcpay_processing_intent_' . $order_id;

		set_transient( $transient_name, empty( $intent_id ) ? '-1' : $intent_id, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Unlocks an order for processing by payment intents.
	 *
	 * @return void
	 */
	private function unlock_order_payment() {
		$order_id = $this->order->get_id();
		delete_transient( 'wcpay_processing_intent_' . $order_id );
	}
}

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
	 * Stripe intents that are treated as successfully created.
	 *
	 * @type array
	 */
	const SUCCESSFUL_INTENT_STATUS = [ 'succeeded', 'requires_capture', 'processing' ];

	/**
	 * Updates an order to processing/completed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	public function mark_payment_completed( $order, $intent_id, $intent_status, $charge_id ) {

		if ( ! $this->order_prepared_for_processing( $order, $intent_id, $intent_status ) ) {
			return;
		}

		$order->payment_complete( $intent_id );
		$this->add_success_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_id, $intent_status );
	}

	/**
	 * Updates an order to failed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 * @param string   $message       Optional message to add to the failed note.
	 *
	 * @return void
	 */
	public function mark_payment_failed( $order, $intent_id, $intent_status, $charge_id, $message = '' ) {

		if ( ! $this->order_prepared_for_processing( $order, $intent_id )
			|| $order->has_status( [ 'failed' ] )
			|| 'failed' === $order->get_meta( '_intention_status' ) ) {
			return;
		}

		$order->update_status( 'failed' );
		$this->add_failure_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_id, $intent_status );
	}

	/**
	 * Updates an order to on-hold status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	public function mark_payment_on_hold( $order, $intent_id, $intent_status, $charge_id ) {

		if ( ! $this->order_prepared_for_processing( $order, $intent_id )
			|| $order->has_status( [ 'on-hold' ] )
			|| 'requires_capture' === $order->get_meta( '_intention_status' ) ) {
			return;
		}

		$order->update_status( 'on-hold' );
		$this->add_on_hold_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_id, $intent_status );
	}

	/**
	 * Leaves an order in pending status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	public function mark_payment_pending( $order, $intent_id, $intent_status, $charge_id ) {

		if ( ! $this->order_prepared_for_processing( $order, $intent_id )
			|| ! $order->has_status( [ 'pending' ] )
			|| 'requires_action' === $order->get_meta( '_intention_status' ) ) {
			return;
		}

		$this->add_pending_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_id, $intent_status );
	}

	/**
	 * Updates an order to processing/completed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	public function mark_payment_capture_completed( $order, $intent_id, $intent_status, $charge_id ) {

		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$order->payment_complete( $intent_id );
		$this->add_capture_success_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_id, $intent_status );
	}

	/**
	 * Updates an order to failed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 * @param string   $message       Optional message to add to the note.
	 *
	 * @return void
	 */
	public function mark_payment_capture_failed( $order, $intent_id, $intent_status, $charge_id, $message = '' ) {

		if ( ! $this->order_prepared_for_processing( $order, $intent_id )
			|| $order->has_status( [ 'failed' ] )
			|| 'failed' === $order->get_meta( '_intention_status' ) ) {
			return;
		}

		$order->update_status( 'failed' );
		$this->add_capture_failed_note( $order, $intent_id, $charge_id, $message );
		$this->complete_order_processing( $order, $intent_id, $intent_status );
	}

	/**
	 * Updates an order to canceled status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	public function mark_payment_expired( $order, $intent_id, $intent_status, $charge_id ) {

		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$order->update_status( 'cancelled' );
		$this->add_expired_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_id, $intent_status );
	}

	/**
	 * Adds the success order note, if needed, and additional message, if included.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	private function add_success_note( $order, $intent_id, $charge_id ) {
		$payment_needed = $order->get_total() > 0;

		if ( $payment_needed ) {
			$transaction_url = $this->compose_transaction_url( $charge_id );
			$note            = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
					/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
					__( 'A payment of %1$s was <strong>successfully charged</strong> using WooCommerce Payments (<a>%2$s</a>).', 'woocommerce-payments' ),
					[
						'strong' => '<strong>',
						'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
					]
				),
				$this->get_order_amount( $order ),
				$charge_id
			);
		}

		if ( $note ) {
			$order->add_order_note( $note );
		}
	}

	/**
	 * Adds the failure order note and additional message, if included.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 * @param string   $message   Optional message to add to the note.
	 *
	 * @return void
	 */
	private function add_failure_note( $order, $intent_id, $charge_id, $message ) {
		$transaction_url = $this->compose_transaction_url( $charge_id );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( 'A payment of %1$s <strong>failed</strong> using WooCommerce Payments (<a>%2$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$this->get_order_amount( $order ),
			$intent_id
		);

		if ( $message ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Adds the on-hold order note and additional message, if included.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	private function add_on_hold_note( $order, $intent_id, $charge_id ) {
		$transaction_url = $this->compose_transaction_url( $charge_id );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( 'A payment of %1$s was <strong>authorized</strong> using WooCommerce Payments (<a>%2$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$this->get_order_amount( $order ),
			$intent_id
		);

		$order->add_order_note( $note );
	}

	/**
	 * Adds the pending order note and additional message, if included.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	private function add_pending_note( $order, $intent_id, $charge_id ) {
		$note = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( 'A payment of %1$s was <strong>started</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'code'   => '<code>',
				]
			),
			$this->get_order_amount( $order ),
			$intent_id
		);

		$order->add_order_note( $note );
	}

	/**
	 * Adds the successful capture order note, if needed, and additional message, if included.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	private function add_capture_success_note( $order, $intent_id, $charge_id ) {
		$transaction_url = $this->compose_transaction_url( $charge_id );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
				__( 'A payment of %1$s was <strong>successfully captured</strong> using WooCommerce Payments (<a>%2$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$this->get_order_amount( $order ),
			$charge_id
		);

		$order->add_order_note( $note );
	}

	/**
	 * Adds the failure order note and additional message, if included.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 * @param string   $message   Optional message to add to the note.
	 *
	 * @return void
	 */
	private function add_capture_failure_note( $order, $intent_id, $charge_id, $message ) {
		$transaction_url = $this->compose_transaction_url( $charge_id );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( 'A capture of %1$s <strong>failed</strong> to complete using WooCommerce Payments (<a>%2$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$this->get_order_amount( $order ),
			$intent_id
		);

		if ( $message ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Adds the expired order note and additional message, if included.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	private function add_expired_note( $order, $intent_id, $charge_id ) {
		$transaction_url = $this->compose_transaction_url( $charge_id );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( 'Payment authorization has <strong>expired</strong> (<a>%1$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$intent_id
		);

		$order->add_order_note( $note );
	}

	/**
	 * Composes url for transaction details page.
	 *
	 * @param  string $charge_id Charge id.
	 *
	 * @return string Transaction details page url.
	 */
	private function compose_transaction_url( $charge_id ) {
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
	 * @param WC_Order $order     The order that is being paid.
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

	/**
	 * Refreshes the order from the database, checks if it is locked, and locks it.
	 *
	 * TODO: Update to throw exceptions so try/catch can be used.
	 * TODO: Maybe add checks to see if there is already a successful intent, or the intent status passed is already set.
	 *
	 * @param WC_Order $order   Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 *
	 * @return bool
	 */
	private function order_prepared_for_processing( $order, $intent_id ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		// Read the latest order properties from the database to avoid race conditions when the paid webhook was handled during this request.
		$order->get_data_store()->read( $order );

		// Check if the order is already complete.
		if ( function_exists( 'wc_get_is_paid_statuses' ) ) {
			if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
				return false;
			}
		}

		if ( $this->is_order_locked( $order, $intent_id ) ) {
			return false;
		}

		// Lock the order.
		$this->lock_order_payment( $order, $intent_id );

		return true;
	}

	/**
	 * Completes order processing by updating the intent meta, unlocking the order, and saving the order.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 *
	 * @return void
	 */
	private function complete_order_processing( $order, $intent_id, $intent_status ) {
		$order->update_meta_data( '_intention_status', $intent_status );
		$this->unlock_order_payment( $order );
		$order->save();
	}

	/**
	 * Gets the total for the order in explicit format.
	 *
	 * @param WC_Order $order     Order object.
	 *
	 * @return string The formatted order total.
	 */
	private function get_order_amount( $order ) {
		return WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ), $order );
	}
}

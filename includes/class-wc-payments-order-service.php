<?php
/**
 * Class WC_Payments_Order_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling order functionality.
 */
class WC_Payments_Order_Service {
	/**
	 * Order status constants.
	 */
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED    = 'failed';
	const STATUS_ON_HOLD   = 'on-hold';
	const STATUS_PENDING   = 'pending';

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
		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$this->update_order_status( $order, 'payment_complete', $intent_id );
		$this->add_payment_success_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_status );
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
		if ( $order->has_status( [ self::STATUS_FAILED ] )
			|| 'failed' === $order->get_meta( '_intention_status' )
			|| ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$this->update_order_status( $order, self::STATUS_FAILED );
		$this->add_payment_failure_note( $order, $intent_id, $charge_id, $message );
		$this->complete_order_processing( $order, $intent_status );
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
	public function mark_payment_authorized( $order, $intent_id, $intent_status, $charge_id ) {
		if ( $order->has_status( [ self::STATUS_ON_HOLD ] )
			|| ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$this->update_order_status( $order, self::STATUS_ON_HOLD );
		$this->add_payment_authorized_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_status );
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
	public function mark_payment_started( $order, $intent_id, $intent_status, $charge_id ) {
		if ( ! $order->has_status( [ self::STATUS_PENDING ] )
			|| 'requires_action' === $order->get_meta( '_intention_status' )
			|| ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$this->add_payment_started_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_status );
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

		$this->update_order_status( $order, 'payment_complete', $intent_id );
		$this->add_capture_success_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Leaves order in current status (should be on-hold), adds a note with a link to the transaction.
	 *
	 * @param WC_Order    $order         Order object.
	 * @param string      $intent_id     The ID of the intent associated with this order.
	 * @param string|null $intent_status The status of the intent related to this order.
	 * @param string      $charge_id     The charge ID related to the intent/order.
	 * @param string      $message       Optional message to add to the note.
	 *
	 * @return void
	 */
	public function mark_payment_capture_failed( $order, $intent_id, $intent_status, $charge_id, $message = '' ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$this->add_capture_failed_note( $order, $intent_id, $charge_id, $message );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Updates an order to cancelled status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	public function mark_payment_capture_expired( $order, $intent_id, $intent_status, $charge_id ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$this->update_order_status( $order, self::STATUS_CANCELLED );
		$this->add_capture_expired_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Updates an order to cancelled status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	public function mark_payment_capture_cancelled( $order, $intent_id, $intent_status, $charge_id ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$this->update_order_status( $order, self::STATUS_CANCELLED );
		$this->add_capture_cancelled_note( $order, $intent_id, $charge_id );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Updates the order to on-hold status and adds a note about the dispute.
	 *
	 * @param WC_Order $order      Order object.
	 * @param string   $dispute_id The ID of the dispute associated with this order.
	 * @param string   $reason     The reason for the dispute.
	 *
	 * @return void
	 */
	public function mark_payment_dispute_created( $order, $dispute_id, $reason ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$this->update_order_status( $order, self::STATUS_ON_HOLD );
		$this->add_dispute_created_note( $order, $dispute_id, $reason );
		$order->save();
	}

	/**
	 * Updates the order status based on dispute status and adds a note about the dispute.
	 *
	 * @param WC_Order $order      Order object.
	 * @param string   $dispute_id The ID of the dispute associated with this order.
	 * @param string   $status     The status of the dispute.
	 *
	 * @return void
	 */
	public function mark_payment_dispute_closed( $order, $dispute_id, $status ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		if ( 'lost' === $status ) {
			wc_create_refund(
				[
					'amount'     => $order->get_total(),
					'reason'     => __( 'Dispute lost.', 'woocommerce-payments' ),
					'order_id'   => $order->get_id(),
					'line_items' => $order->get_items(),
				]
			);
		} else {
			// TODO: This should revert to the status the order was in before the dispute was created.
			$this->update_order_status( $order, self::STATUS_COMPLETED );
		}

		$this->add_dispute_closed_note( $order, $dispute_id, $status );
		$order->save();
	}

	/**
	 * Updates a terminal order to completed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 * @param string   $charge_id     The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	public function mark_terminal_payment_completed( $order, $intent_id, $intent_status, $charge_id ) {
		$this->update_order_status( $order, self::STATUS_COMPLETED, $intent_id );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Adds the success order note, if needed.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	private function add_payment_success_note( $order, $intent_id, $charge_id ) {
		$payment_needed = $order->get_total() > 0;

		if ( ! $payment_needed ) {
			return;
		}

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
	private function add_payment_failure_note( $order, $intent_id, $charge_id, $message ) {
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

		if ( ! empty( $message ) ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Adds the payment authorized order note.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	private function add_payment_authorized_note( $order, $intent_id, $charge_id ) {
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
	 * Adds the payment started order note.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	private function add_payment_started_note( $order, $intent_id, $charge_id ) {
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
	 * Adds the successful capture order note, if needed.
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
	private function add_capture_failed_note( $order, $intent_id, $charge_id, $message ) {
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

		if ( ! empty( $message ) ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Adds the expired order note.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return void
	 */
	private function add_capture_expired_note( $order, $intent_id, $charge_id ) {
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
	 * Adds the cancelled order note.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return void
	 */
	private function add_capture_cancelled_note( $order ) {
		$note = WC_Payments_Utils::esc_interpolated_html(
			__( 'Payment authorization was successfully <strong>cancelled</strong>.', 'woocommerce-payments' ),
			[ 'strong' => '<strong>' ]
		);

		$order->add_order_note( $note );
	}

	/**
	 * Adds the dispute created order note.
	 *
	 * @param WC_Order $order      Order object.
	 * @param string   $dispute_id The ID of the dispute associated with this order.
	 * @param string   $reason     The reason for the dispute.
	 *
	 * @return void
	 */
	private function add_dispute_created_note( $order, $dispute_id, $reason ) {
		$dispute_url = $this->compose_dispute_url( $dispute_id );
		$note        = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the dispute reason */
				__( 'Payment has been disputed as %1$s. See <a>dispute overview</a> for more details.', 'woocommerce-payments' ),
				[
					'a' => '<a href="' . $dispute_url . '" target="_blank" rel="noopener noreferrer">',
				]
			),
			$reason
		);

		$order->add_order_note( $note );
	}

	/**
	 * Adds the dispute closed order note.
	 *
	 * @param WC_Order $order      Order object.
	 * @param string   $dispute_id The ID of the dispute associated with this order.
	 * @param string   $status     The status of the dispute.
	 *
	 * @return void
	 */
	private function add_dispute_closed_note( $order, $dispute_id, $status ) {
		$dispute_url = $this->compose_dispute_url( $dispute_id );
		$note        = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the dispute status */
				__( 'Payment dispute has been closed with status %1$s. See <a>dispute overview</a> for more details.', 'woocommerce-payments' ),
				[
					'a' => '<a href="' . $dispute_url . '" target="_blank" rel="noopener noreferrer">',
				]
			),
			$status
		);

		$order->add_order_note( $note );
	}

	/**
	 * Composes url for transaction details page.
	 *
	 * @param string $charge_id Charge id.
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
	 * Composes url for dispute details page.
	 *
	 * @param string $dispute_id Dispute id.
	 *
	 * @return string Dispute details page url.
	 */
	private function compose_dispute_url( $dispute_id ) {
		return add_query_arg(
			[
				'page' => 'wc-admin',
				'path' => '/payments/disputes/details',
				'id'   => $dispute_id,
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

		if ( $this->is_order_paid( $order ) ) {
			return false;
		}

		if ( $this->is_order_locked( $order, $intent_id ) ) {
			return false;
		}

		// Lock the order.
		$this->lock_order_payment( $order, $intent_id );

		return true;
	}

	/**
	 * Checks to see if the current order, and a fresh copy of the order from the database are paid.
	 *
	 * @param WC_Order $order The order being checked.
	 *
	 * @return boolean True if it has a paid status, false if not.
	 */
	private function is_order_paid( $order ) {
		// Read the latest order properties from the database to avoid race conditions if webhook was handled during this request.
		$clone_order = clone $order;
		$clone_order->get_data_store()->read( $clone_order );

		// Check if the order is already complete.
		if ( function_exists( 'wc_get_is_paid_statuses' ) ) {
			if ( $order->has_status( wc_get_is_paid_statuses() )
				|| $clone_order->has_status( wc_get_is_paid_statuses() ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Completes order processing by updating the intent meta, unlocking the order, and saving the order.
	 *
	 * @param WC_Order    $order         Order object.
	 * @param string|null $intent_status The status of the intent related to this order.
	 *
	 * @return void
	 */
	private function complete_order_processing( $order, $intent_status = null ) {
		if ( ! empty( $intent_status ) ) {
			$order->update_meta_data( '_intention_status', $intent_status );
		}
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

	/**
	 * Updates the order status and catches any exceptions so that processing can continue.
	 *
	 * @param WC_Order    $order        Order object.
	 * @param string      $order_status The status to change the order to.
	 * @param null|string $intent_id    The ID of the intent associated with this order.
	 *
	 * @throws Exception Throws exception if intent id is not included if order needs to be marked as paid.
	 *
	 * @return void
	 */
	private function update_order_status( $order, $order_status, $intent_id = '' ) {
		try {
			/**
			 * In this instance payment_complete is not an order status, but a flag to mark the order as paid. In a default WooCommerce store, the order
			 * may move to Processing or Completed status depending on the contents of the cart, so we let WooCommerce core decide what to do.
			 */
			if ( 'payment_complete' === $order_status ) {
				if ( empty( $intent_id ) ) {
					throw new Exception( __( 'Intent id was not included for payment complete status change.', 'woocommerce-payments' ) );
				}
				$order->payment_complete( $intent_id );
			} else {
				$order->update_status( $order_status );
			}
		} catch ( Exception $e ) {
			// Continue further, something unexpected happened, but we can't really do anything with that.
			Logger::log( 'Error when updating status for order ' . $order->get_id() . ': ' . $e->getMessage() );
		}
	}
}

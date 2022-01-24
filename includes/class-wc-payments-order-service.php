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
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	public function mark_payment_completed( $order, $intent, $message = '' ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent ) ) {
			return;
		}

		$this->add_success_note( $order, $intent, $message );
		$order->payment_complete( $intent->get_id() );
		$this->complete_order_processing( $order, $intent );
	}

	/**
	 * Updates an order to failed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the failed note.
	 *
	 * @return void
	 */
	public function mark_payment_failed( $order, $intent, $message = '' ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent )
			|| $order->has_status( [ 'failed' ] ) ) {
			return;
		}

		$this->add_failure_note( $order, $intent, $message );
		$order->update_status( 'failed' );
		$this->complete_order_processing( $order, $intent );
	}

	/**
	 * Updates an order to on-hold status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the failed note.
	 *
	 * @return void
	 */
	public function mark_payment_on_hold( $order, $intent, $message = '' ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent )
			|| $order->has_status( [ 'on-hold' ] ) ) {
			return;
		}

		$this->add_on_hold_note( $order, $intent, $message );
		$order->update_status( 'on-hold' );
		$this->complete_order_processing( $order, $intent );
	}

	/**
	 * Leaves an order in pending status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	public function mark_payment_pending( $order, $intent, $message = '' ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent )
			|| ! $order->has_status( [ 'pending' ] ) ) {
			return;
		}

		$this->add_pending_note( $order, $intent, $message );
		$this->complete_order_processing( $order, $intent );
	}

	/**
	 * Updates an order to processing/completed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	public function mark_payment_capture_completed( $order, $intent, $message = '' ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent ) ) {
			return;
		}

		$this->add_capture_success_note( $order, $intent, $message );
		$order->payment_complete( $intent->get_id() );
		$this->complete_order_processing( $order, $intent );
	}

	/**
	 * Updates an order to canceled status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	public function mark_payment_expired( $order, $intent, $message = '' ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent ) ) {
			return;
		}

		$this->add_expired_note( $order, $intent, $message );
		$order->update_status( 'cancelled' );
		$this->complete_order_processing( $order, $intent );
	}

	/**
	 * Adds the success order note, if needed, and additional message, if included.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	private function add_success_note( $order, $intent, $message ) {
		$payment_needed = $order->get_total() > 0;

		if ( $payment_needed ) {
			$charge_id       = $intent->get_charge_id();
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

		if ( $note && $message ) {
			$note .= ' ' . $message;
		} elseif ( $message ) {
			$note = $message;
		}

		if ( $note ) {
			$order->add_order_note( $note );
		}
	}

	/**
	 * Adds the failure order note and additional message, if included.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	private function add_failure_note( $order, $intent, $message ) {
		$transaction_url = $this->compose_transaction_url( $order->get_meta( '_charge_id' ) );
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
			$intent->get_id()
		);

		if ( $message ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Adds the on-hold order note and additional message, if included.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	private function add_on_hold_note( $order, $intent, $message ) {
		$transaction_url = $this->compose_transaction_url( $order->get_meta( '_charge_id' ) );
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
			$intent->get_id()
		);

		if ( $message ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Adds the pending order note and additional message, if included.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	private function add_pending_note( $order, $intent, $message ) {
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
			$intent->get_id()
		);

		if ( $message ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Adds the successful capture order note, if needed, and additional message, if included.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	private function add_capture_success_note( $order, $intent, $message ) {
		$charge_id       = $intent->get_charge_id();
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

		if ( $message ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Adds the expired order note and additional message, if included.
	 *
	 * @param WC_Order                  $order   Order object.
	 * @param WC_Payments_API_Intention $intent  The intent associated with this order.
	 * @param string                    $message Optional message to add to the note.
	 *
	 * @return void
	 */
	private function add_expired_note( $order, $intent, $message ) {
		$transaction_url = $this->compose_transaction_url( $order->get_meta( '_charge_id' ) );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( 'Payment authorization has <strong>expired</strong> (<a>%1$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$intent->get_id()
		);

		if ( $message ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Composes url for transaction details page.
	 *
	 * @param  string $charge_id Charge id.
	 *
	 * @return string            Transaction details page url.
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
	 * @param WC_Order                  $order  The order that is being paid.
	 * @param WC_Payments_API_Intention $intent The id of the intent that is being processed.
	 *
	 * @return bool    A flag that indicates whether the order is already locked.
	 */
	private function is_order_locked( $order, $intent = null ) {
		$order_id       = $order->get_id();
		$transient_name = 'wcpay_processing_intent_' . $order_id;
		$processing     = get_transient( $transient_name );

		// Block the process if the same intent is already being handled.
		return ( '-1' === $processing || ( isset( $intent ) && $processing === $intent->get_id() ) );
	}

	/**
	 * Lock an order for payment intent processing for 5 minutes.
	 *
	 * @param WC_Order                  $order  The order that is being paid.
	 * @param WC_Payments_API_Intention $intent The id of the intent that is being processed.
	 *
	 * @return void
	 */
	private function lock_order_payment( $order, $intent = null ) {
		$order_id       = $order->get_id();
		$transient_name = 'wcpay_processing_intent_' . $order_id;

		set_transient( $transient_name, empty( $intent ) ? '-1' : $intent->get_id(), 5 * MINUTE_IN_SECONDS );
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
	 * @param WC_Order                  $order  Order object.
	 * @param WC_Payments_API_Intention $intent The intent associated with this order.
	 *
	 * @return bool
	 */
	private function order_prepared_for_processing( $order, $intent ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		// Read the latest order properties from the database to avoid race conditions when the paid webhook was handled during this request.
		$order->get_data_store()->read( $order );

		// Check if the order is already complete.
		if ( function_exists( 'wc_get_is_paid_statuses' ) && $order->has_status( wc_get_is_paid_statuses() ) ) {
			return false;
		}

		// Does it already have a successful intent?
		if ( in_array( $order->get_meta( '_intention_status' ), self::SUCCESSFUL_INTENT_STATUS, true ) ) {
			return false;
		}

		// Does the order already have the intent status?
		if ( $intent->get_status() === $order->get_meta( '_intention_status' ) ) {
			return false;
		}

		if ( $this->is_order_locked( $order, $intent ) ) {
			return false;
		}

		// Lock the order.
		$this->lock_order_payment( $order, $intent );

		return true;
	}

	/**
	 * Completes order processing by updating the intent meta, unlocking the order, and saving the order.
	 *
	 * @param WC_Order                  $order  Order object.
	 * @param WC_Payments_API_Intention $intent The intent associated with this order.
	 *
	 * @return void
	 */
	private function complete_order_processing( $order, $intent ) {
		$this->update_order_intention_status( $order, $intent );
		$this->unlock_order_payment( $order );
		$order->save();
	}

	/**
	 * Updates the order's intention status meta data based on the status of the intent.
	 *
	 * @param WC_Order                  $order  Order object.
	 * @param WC_Payments_API_Intention $intent The intent associated with this order.
	 *
	 * @return void
	 */
	private function update_order_intention_status( $order, $intent ) {
		$order->update_meta_data( '_intention_status', $intent->get_status() );
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

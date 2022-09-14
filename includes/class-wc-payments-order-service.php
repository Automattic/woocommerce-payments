<?php
/**
 * Class WC_Payments_Order_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Logger;
use WCPay\Constants\Payment_Method;

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

	const ADD_FEE_BREAKDOWN_TO_ORDER_NOTES = 'wcpay_add_fee_breakdown_to_order_notes';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $api_client;

	/**
	 * WC_Payments_Order_Service constructor.
	 *
	 * @param WC_Payments_API_Client $api_client - WooCommerce Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $api_client ) {
		$this->api_client = $api_client;

		add_action( self::ADD_FEE_BREAKDOWN_TO_ORDER_NOTES, [ $this, 'add_fee_breakdown_to_order_notes' ], 10, 3 );
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
	public function mark_payment_completed( $order, $intent_id, $intent_status, $charge_id ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$note = $this->generate_payment_success_note( $intent_id, $charge_id, $this->get_order_amount( $order ) );

		if ( $this->order_note_exists( $order, $note ) ) {
			return;
		}

		// Update the note with the fee breakdown details async.
		WC_Payments::get_action_scheduler_service()->schedule_job(
			time(),
			self::ADD_FEE_BREAKDOWN_TO_ORDER_NOTES,
			[
				'order_id'     => $order->get_id(),
				'intent_id'    => $intent_id,
				'is_test_mode' => WC_Payments::get_gateway()->is_in_test_mode(),
			]
		);

		$this->update_order_status( $order, 'payment_complete', $intent_id );
		$order->add_order_note( $note );
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

		$note = $this->generate_payment_failure_note( $intent_id, $charge_id, $message, $this->get_order_amount( $order ) );

		if ( $this->order_note_exists( $order, $note ) ) {
			return;
		}

		$this->update_order_status( $order, self::STATUS_FAILED );
		$order->add_order_note( $note );
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
			|| ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$this->add_payment_started_note( $order, $intent_id );
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

		$note = $this->generate_capture_expired_note( $intent_id, $charge_id );

		if ( $this->order_note_exists( $order, $note ) ) {
			return;
		}

		$this->update_order_status( $order, self::STATUS_CANCELLED );
		$order->add_order_note( $note );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Updates an order to cancelled status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 *
	 * @return void
	 */
	public function mark_payment_capture_cancelled( $order, $intent_id, $intent_status ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$this->update_order_status( $order, self::STATUS_CANCELLED );
		$this->add_capture_cancelled_note( $order );
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

		$note = $this->generate_dispute_created_note( $dispute_id, $reason );

		if ( $this->order_note_exists( $order, $note ) ) {
			return;
		}

		$this->update_order_status( $order, self::STATUS_ON_HOLD );
		$order->add_order_note( $note );
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

		$note = $this->generate_dispute_closed_note( $dispute_id, $status );

		if ( $this->order_note_exists( $order, $note ) ) {
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
			$order->save();
		}

		$order->add_order_note( $note );
	}

	/**
	 * Updates a terminal order to completed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 *
	 * @return void
	 */
	public function mark_terminal_payment_completed( $order, $intent_id, $intent_status ) {
		$this->update_order_status( $order, self::STATUS_COMPLETED, $intent_id );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Check if a note content has already existed in the order.
	 *
	 * @param WC_Order $order        The order object to add the note.
	 * @param string   $note_content Note content.
	 *
	 * @return bool true if the note content exists, false otherwise.
	 */
	public function order_note_exists( WC_Order $order, string $note_content ): bool {
		// Get current notes of the order.
		$current_notes = wc_get_order_notes(
			[ 'order_id' => $order->get_id() ]
		);

		foreach ( $current_notes as $current_note ) {
			if ( $current_note->content === $note_content ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Adds a note with the fee breakdown for the order.
	 *
	 * @param string $order_id     WC Order Id.
	 * @param string $intent_id    The intent id for the payment.
	 * @param bool   $is_test_mode Whether to run the CRON job in test mode.
	 */
	public function add_fee_breakdown_to_order_notes( $order_id, $intent_id, $is_test_mode = false ) {
		// Since this CRON job may have been created in test_mode, when the CRON job runs, it
		// may lose the test_mode context. So, instead, we pass that context when creating
		// the CRON job and apply the context here.
		$apply_test_mode_context = function () use ( $is_test_mode ) {
			return $is_test_mode;
		};
		add_filter( 'wcpay_test_mode', $apply_test_mode_context );

		$order = wc_get_order( $order_id );
		try {
			$events = $this->api_client->get_timeline( $intent_id );

			$captured_event = current(
				array_filter(
					$events['data'],
					function ( array $event ) {
						return 'captured' === $event['type'];
					}
				)
			);

			$details = ( new WC_Payments_Captured_Event_Note( $captured_event ) )->generate_html_note();

			// Add fee breakdown details to the note.
			$title = WC_Payments_Utils::esc_interpolated_html(
				// phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings
				__( '<strong>Fee details:</strong>', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
				]
			);
			$note = $title . $details;
			// Update the order with the new note.
			$order->add_order_note( $note );
			$order->save();

		} catch ( Exception $e ) {
			Logger::log( sprintf( 'Can not generate the detailed note for intent_id %1$s. Reason: %2$s', $intent_id, $e->getMessage() ) );
		}
	}

	/**
	 * Get content for the success order note.
	 *
	 * @param string $intent_id        The payment intent ID related to the intent/order.
	 * @param string $charge_id        The charge ID related to the intent/order.
	 * @param string $formatted_amount The formatted order total.
	 *
	 * @return string Note content.
	 */
	private function generate_payment_success_note( $intent_id, $charge_id, $formatted_amount ) {
		$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );

		return sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the successfully charged amount, %2: transaction ID of the payment */
				__( 'A payment of %1$s was <strong>successfully charged</strong> using WooCommerce Payments (<a>%2$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$formatted_amount,
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
		);
	}

	/**
	 * Get content for the failure order note and additional message, if included.
	 *
	 * @param string $intent_id        The ID of the intent associated with this order.
	 * @param string $charge_id        The charge ID related to the intent/order.
	 * @param string $message          Optional message to add to the note.
	 * @param string $formatted_amount The formatted order total.
	 *
	 * @return string Note content.
	 */
	private function generate_payment_failure_note( $intent_id, $charge_id, $message, $formatted_amount ) {
		$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( 'A payment of %1$s <strong>failed</strong> using WooCommerce Payments (<a>%2$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$formatted_amount,
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
		);

		if ( ! empty( $message ) ) {
			$note .= ' ' . $message;
		}

		return $note;
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
		$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
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
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
		);

		$order->add_order_note( $note );
	}

	/**
	 * Adds the payment started order note.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 *
	 * @return void
	 */
	private function add_payment_started_note( $order, $intent_id ) {
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
		$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
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
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
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
		$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
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
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
		);

		if ( ! empty( $message ) ) {
			$note .= ' ' . $message;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Get content for the capture expired note.
	 *
	 * @param string $intent_id The ID of the intent associated with this order.
	 * @param string $charge_id The charge ID related to the intent/order.
	 *
	 * @return string Note content.
	 */
	private function generate_capture_expired_note( $intent_id, $charge_id ) {
		$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );

		return sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( 'Payment authorization has <strong>expired</strong> (<a>%1$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
		);

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
	 * Get content for the dispute created order note.
	 *
	 * @param string $dispute_id The ID of the dispute associated with this order.
	 * @param string $reason     The reason for the dispute.
	 *
	 * @return string Note content.
	 */
	private function generate_dispute_created_note( $dispute_id, $reason ) {
		$dispute_url = $this->compose_dispute_url( $dispute_id );

		return sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the dispute reason */
				__( 'Payment has been disputed as %1$s. See <a>dispute overview</a> for more details.', 'woocommerce-payments' ),
				[
					'a' => '<a href="' . $dispute_url . '" target="_blank" rel="noopener noreferrer">',
				]
			),
			$reason
		);
	}

	/**
	 * Get content for the dispute closed order note.
	 *
	 * @param string $dispute_id The ID of the dispute associated with this order.
	 * @param string $status     The status of the dispute.
	 *
	 * @return string Note content.
	 */
	private function generate_dispute_closed_note( $dispute_id, $status ) {
		$dispute_url = $this->compose_dispute_url( $dispute_id );
		return sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the dispute status */
				__( 'Payment dispute has been closed with status %1$s. See <a>dispute overview</a> for more details.', 'woocommerce-payments' ),
				[
					'a' => '<a href="' . $dispute_url . '" target="_blank" rel="noopener noreferrer">',
				]
			),
			$status
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
		wp_cache_delete( $order->get_id(), 'posts' );

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

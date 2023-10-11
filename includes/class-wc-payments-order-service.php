<?php
/**
 * Class WC_Payments_Order_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Constants\Fraud_Meta_Box_Type;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Intent_Status;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Fraud_Prevention\Models\Rule;
use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling order functionality.
 */
class WC_Payments_Order_Service {
	const ADD_FEE_BREAKDOWN_TO_ORDER_NOTES = 'wcpay_add_fee_breakdown_to_order_notes';

	/**
	 * Meta key used to store intent Id.
	 *
	 * @const string
	 */
	const INTENT_ID_META_KEY = '_intent_id';

	/**
	 * Meta key used to store payment method Id.
	 *
	 * @const string
	 */
	const PAYMENT_METHOD_ID_META_KEY = '_payment_method_id';

	/**
	 * Meta key used to store charge Id.
	 *
	 * @const string
	 */
	const CHARGE_ID_META_KEY = '_charge_id';

	/**
	 * Meta key used to store intention status.
	 *
	 * @const string
	 */
	const INTENTION_STATUS_META_KEY = '_intention_status';

	/**
	 * Meta key used to store customer Id.
	 *
	 * @const string
	 */
	const CUSTOMER_ID_META_KEY = '_stripe_customer_id';

	/**
	 * Meta key used to store WCPay fraud meta box type.
	 *
	 * @const string
	 */
	const WCPAY_FRAUD_META_BOX_TYPE_META_KEY = '_wcpay_fraud_meta_box_type';

	/**
	 * Meta key used to store WCPay fraud outcome status.
	 *
	 * @const string
	 */
	const WCPAY_FRAUD_OUTCOME_STATUS_META_KEY = '_wcpay_fraud_outcome_status';

	/**
	 * Meta key used to store WCPay intent currency.
	 *
	 * @const string
	 */
	const WCPAY_INTENT_CURRENCY_META_KEY = '_wcpay_intent_currency';

	/**
	 * Meta key used to store WCPay refund id.
	 *
	 * @const string
	 */
	const WCPAY_REFUND_ID_META_KEY = '_wcpay_refund_id';

	/**
	 * Meta key used to store WCPay refund status.
	 *
	 * @const string
	 */
	const WCPAY_REFUND_STATUS_META_KEY = '_wcpay_refund_status';

	/**
	 * Meta key used to store WCPay transaction fee.
	 *
	 * @const string
	 */
	const WCPAY_TRANSACTION_FEE_META_KEY = '_wcpay_transaction_fee';

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
	}

	/**
	 * Parse the payment intent data and add any necessary notes to the order and update the order status accordingly.
	 *
	 * @param WC_Order                           $order   The order to update.
	 * @param WC_Payments_API_Abstract_Intention $intent  Setup or payment intent to pull the data from.
	 */
	public function update_order_status_from_intent( $order, $intent ) {
		$intent_data = $this->get_intent_data( $intent );

		if ( ! isset( $intent_data['intent_id'] ) || ! $this->order_prepared_for_processing( $order, $intent_data['intent_id'] ) ) {
			return;
		}

		switch ( $intent_data['intent_status'] ) {
			case Intent_Status::CANCELED:
				$this->mark_payment_capture_cancelled( $order, $intent_data );
				break;
			case Intent_Status::SUCCEEDED:
				if ( Intent_Status::REQUIRES_CAPTURE === $this->get_intention_status_for_order( $order ) ) {
					$this->mark_payment_capture_completed( $order, $intent_data );
				} else {
					$this->mark_payment_completed( $order, $intent_data );
				}
				break;
			case Intent_Status::PROCESSING:
			case Intent_Status::REQUIRES_CAPTURE:
				if ( Rule::FRAUD_OUTCOME_REVIEW === $intent_data['fraud_outcome'] ) {
					$this->mark_order_held_for_review_for_fraud( $order, $intent_data );
				} else {
					$this->mark_payment_authorized( $order, $intent_data );
				}
				break;
			case Intent_Status::REQUIRES_ACTION:
			case Intent_Status::REQUIRES_PAYMENT_METHOD:
				$this->mark_payment_started( $order, $intent_data );
				break;
			default:
				Logger::error( 'Uncaught payment intent status of ' . $intent_data['intent_status'] . ' passed for order id: ' . $order->get_id() );
				break;
		}

		$this->complete_order_processing( $order );
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
		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$note = $this->generate_payment_failure_note( $intent_id, $charge_id, $message, $this->get_order_amount( $order ) );
		if ( $this->order_note_exists( $order, $note )
			|| $order->has_status( [ Order_Status::FAILED ] ) ) {
			$this->complete_order_processing( $order );
			return;
		}

		$this->update_order_status( $order, Order_Status::FAILED );
		$order->add_order_note( $note );
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

		$note = $this->generate_capture_failed_note( $order, $intent_id, $charge_id, $message );
		if ( $this->order_note_exists( $order, $note ) ) {
			$this->complete_order_processing( $order );
			return;
		}

		if ( Rule::FRAUD_OUTCOME_REVIEW === $this->get_fraud_outcome_status_for_order( $order ) ) {
			$this->set_fraud_meta_box_type_for_order( $order, Fraud_Meta_Box_Type::REVIEW_FAILED );
		}

		$order->add_order_note( $note );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Update an order to failed status, and add note with a link to the transaction.
	 *
	 * Context - when a Payment Intent expires. Changing the status to failed will enable the buyer to re-attempt payment.
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
			$this->complete_order_processing( $order );
			return;
		}

		if ( Rule::FRAUD_OUTCOME_REVIEW === $this->get_fraud_outcome_status_for_order( $order ) ) {
			$this->set_fraud_meta_box_type_for_order( $order, Fraud_Meta_Box_Type::REVIEW_EXPIRED );
		}

		$this->update_order_status( $order, Order_Status::FAILED );
		$order->add_order_note( $note );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Leaves order status as Pending, adds fraud meta data, and adds the fraud blocked note.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $intent_id     The ID of the intent associated with this order.
	 * @param string   $intent_status The status of the intent related to this order.
	 *
	 * @return void
	 */
	public function mark_order_blocked_for_fraud( $order, $intent_id, $intent_status ) {
		if ( ! $this->order_prepared_for_processing( $order, $intent_id ) ) {
			return;
		}

		$note = $this->generate_fraud_blocked_note( $order );
		if ( $this->order_note_exists( $order, $note ) ) {
			$this->complete_order_processing( $order );
			return;
		}

		$this->set_fraud_outcome_status_for_order( $order, Rule::FRAUD_OUTCOME_BLOCK );
		$this->set_fraud_meta_box_type_for_order( $order, Fraud_Meta_Box_Type::BLOCK );
		$order->add_order_note( $note );
		$this->complete_order_processing( $order, $intent_status );
	}

	/**
	 * Updates the order to on-hold status and adds a note about the dispute.
	 *
	 * @param WC_Order $order      Order object.
	 * @param string   $charge_id  The ID of the disputed charge associated with this order.
	 * @param string   $amount     The disputed amount – formatted currency value.
	 * @param string   $reason     The reason for the dispute – human-readable text.
	 * @param string   $due_by     The deadline for responding to the dispute - formatted date string.
	 *
	 * @return void
	 */
	public function mark_payment_dispute_created( $order, $charge_id, $amount, $reason, $due_by ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$note = $this->generate_dispute_created_note( $charge_id, $amount, $reason, $due_by );
		if ( $this->order_note_exists( $order, $note ) ) {
			return;
		}

		$this->update_order_status( $order, Order_Status::ON_HOLD );
		$order->add_order_note( $note );
		$order->save();
	}

	/**
	 * Updates the order status based on dispute status and adds a note about the dispute.
	 *
	 * @param WC_Order $order      Order object.
	 * @param string   $charge_id  The ID of the disputed charge associated with this order.
	 * @param string   $status     The status of the dispute.
	 *
	 * @return void
	 */
	public function mark_payment_dispute_closed( $order, $charge_id, $status ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$note = $this->generate_dispute_closed_note( $charge_id, $status );

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
			$this->update_order_status( $order, Order_Status::COMPLETED );
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
		$this->update_order_status( $order, Order_Status::COMPLETED, $intent_id );
		$this->set_fraud_meta_box_type_for_order( $order, Fraud_Meta_Box_Type::TERMINAL_PAYMENT );
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
	 * Get the payment metadata for intent id.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_intent_id_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::INTENT_ID_META_KEY, true );
	}

	/**
	 * Set the payment metadata for intent id.
	 *
	 * @param  WC_Order $order The order object.
	 * @param  string   $intent_id The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_intent_id_for_order( $order, $intent_id ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::INTENT_ID_META_KEY, $intent_id );
		$order->save_meta_data();
		/**
		 * Hook: When the order meta data _intent_id is updated.
		 *
		 * @since 5.4.0
		 */
		do_action( 'wcpay_order_intent_id_updated' );
	}

	/**
	 * Get the payment metadata for payment method id.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_payment_method_id_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::PAYMENT_METHOD_ID_META_KEY, true );
	}

	/**
	 * Set the payment metadata for payment method id.
	 *
	 * @param  mixed  $order The order.
	 * @param  string $payment_method_id The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_payment_method_id_for_order( $order, $payment_method_id ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::PAYMENT_METHOD_ID_META_KEY, $payment_method_id );
		$order->save_meta_data();
		/**
		 * Hook: When the order meta data _payment_method_id is updated.
		 *
		 * @since 5.4.0
		 */
		do_action( 'wcpay_order_payment_method_id_updated' );
	}

	/**
	 * Set the payment metadata for charge id.
	 *
	 * @param  mixed  $order The order.
	 * @param  string $charge_id The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_charge_id_for_order( $order, $charge_id ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::CHARGE_ID_META_KEY, $charge_id );
		$order->save_meta_data();
	}

	/**
	 * Get the payment metadata for charge id.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_charge_id_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::CHARGE_ID_META_KEY, true );
	}

	/**
	 * Set the payment metadata for intention status.
	 *
	 * @param  mixed  $order The order.
	 * @param  string $intention_status The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_intention_status_for_order( $order, $intention_status ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::INTENTION_STATUS_META_KEY, $intention_status );
		$order->save_meta_data();
	}

	/**
	 * Get the payment metadata for intention status.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_intention_status_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::INTENTION_STATUS_META_KEY, true );
	}

	/**
	 * Set the payment metadata for customer id.
	 *
	 * @param  mixed  $order The order.
	 * @param  string $customer_id The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_customer_id_for_order( $order, $customer_id ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::CUSTOMER_ID_META_KEY, $customer_id );
		$order->save_meta_data();
	}

	/**
	 * Get the payment metadata for customer id.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_customer_id_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::CUSTOMER_ID_META_KEY, true );
	}

	/**
	 * Set the payment metadata for intent currency.
	 *
	 * @param  mixed  $order The order.
	 * @param  string $wcpay_intent_currency The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_wcpay_intent_currency_for_order( $order, $wcpay_intent_currency ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::WCPAY_INTENT_CURRENCY_META_KEY, $wcpay_intent_currency );
		$order->save_meta_data();
	}

	/**
	 * Get the payment metadata for intent currency.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_wcpay_intent_currency_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::WCPAY_INTENT_CURRENCY_META_KEY, true );
	}

	/**
	 * Set the payment metadata for refund id.
	 *
	 * @param  mixed  $order The order.
	 * @param  string $wcpay_refund_id The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_wcpay_refund_id_for_order( $order, $wcpay_refund_id ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::WCPAY_REFUND_ID_META_KEY, $wcpay_refund_id );
		$order->save_meta_data();
	}

	/**
	 * Get the payment metadata for refund id.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_wcpay_refund_id_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::WCPAY_REFUND_ID_META_KEY, true );
	}

	/**
	 * Set the payment metadata for refund status.
	 *
	 * @param  mixed  $order The order.
	 * @param  string $wcpay_refund_status The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_wcpay_refund_status_for_order( $order, $wcpay_refund_status ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::WCPAY_REFUND_STATUS_META_KEY, $wcpay_refund_status );
		$order->save_meta_data();
	}

	/**
	 * Get the payment metadata for refund status.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_wcpay_refund_status_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::WCPAY_REFUND_STATUS_META_KEY, true );
	}

	/**
	 * Set the fraud_outcome_status for an order.
	 *
	 * @param  mixed  $order The order.
	 * @param  string $fraud_outcome_status The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_fraud_outcome_status_for_order( $order, $fraud_outcome_status ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::WCPAY_FRAUD_OUTCOME_STATUS_META_KEY, $fraud_outcome_status );
		$order->save_meta_data();
	}

	/**
	 * Get the fraud_outcome_status for an order.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_fraud_outcome_status_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::WCPAY_FRAUD_OUTCOME_STATUS_META_KEY, true );
	}

	/**
	 * Set the fraud_meta_box_type for an order.
	 *
	 * @param  mixed  $order The order.
	 * @param  string $fraud_meta_box_type The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_fraud_meta_box_type_for_order( $order, $fraud_meta_box_type ) {
		$order = $this->get_order( $order );
		$order->update_meta_data( self::WCPAY_FRAUD_META_BOX_TYPE_META_KEY, $fraud_meta_box_type );
		$order->save_meta_data();
	}

	/**
	 * Get the fraud_meta_box_type for an order.
	 *
	 * @param  mixed $order The order Id or order object.
	 *
	 * @return string
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_fraud_meta_box_type_for_order( $order ) : string {
		$order = $this->get_order( $order );
		return $order->get_meta( self::WCPAY_FRAUD_META_BOX_TYPE_META_KEY, true );
	}

	/**
	 * Given the payment intent data, adds it to the given order as metadata and parses any notes that need to be added
	 *
	 * @param WC_Order $order The order.
	 * @param string   $intent_id The intent ID.
	 * @param string   $intent_status Intent status.
	 * @param string   $payment_method Payment method ID.
	 * @param string   $customer_id Customer ID.
	 * @param string   $charge_id Charge ID.
	 * @param string   $currency Currency code.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function attach_intent_info_to_order( $order, $intent_id, $intent_status, $payment_method, $customer_id, $charge_id, $currency ) {
		// first, let's save all the metadata that needed for refunds, required for status change etc.
		$order->set_transaction_id( $intent_id );
		$this->set_intent_id_for_order( $order, $intent_id );
		$this->set_payment_method_id_for_order( $order, $payment_method );
		$this->set_charge_id_for_order( $order, $charge_id );
		$this->set_intention_status_for_order( $order, $intent_status );
		$this->set_customer_id_for_order( $order, $customer_id );
		$this->set_wcpay_intent_currency_for_order( $order, $currency );
		$order->save();
	}

	/**
	 * Updates an order to cancelled status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param array    $intent_data   The intent data associated with this order.
	 *
	 * @return void
	 */
	private function mark_payment_capture_cancelled( $order, $intent_data ) {
		$note = $this->generate_capture_cancelled_note();
		if ( $this->order_note_exists( $order, $note ) ) {
			$this->complete_order_processing( $order );
			return;
		}

		/**
		 * If we have a status for the fraud outcome, we want to add the proper meta data.
		 */
		if ( isset( $intent_data['fraud_outcome'] )
			&& Rule::is_valid_fraud_outcome_status( $intent_data['fraud_outcome'] )
			&& Rule::FRAUD_OUTCOME_ALLOW !== $intent_data['fraud_outcome'] ) {
			$this->set_fraud_outcome_status_for_order( $order, $intent_data['fraud_outcome'] );
			$this->set_fraud_meta_box_type_for_order( $order, Fraud_Meta_Box_Type::REVIEW_BLOCKED );
		}

		$this->update_order_status( $order, Order_Status::CANCELLED );
		$order->add_order_note( $note );
		$this->complete_order_processing( $order, $intent_data['intent_status'] );
	}

	/**
	 * Updates an order to processing/completed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param array    $intent_data   The data of the intent associated with this order.
	 *
	 * @return void
	 */
	private function mark_payment_completed( $order, $intent_data ) {
		// Need to have a check for the intention status of `requires_capture`.
		$note = $this->generate_payment_success_note( $intent_data['intent_id'], $intent_data['charge_id'], $this->get_order_amount( $order ) );
		if ( $this->order_note_exists( $order, $note ) ) {
			return;
		}

		// Update the note with the fee breakdown details async, update order status, add order note.
		$this->enqueue_add_fee_breakdown_to_order_notes( $order, $intent_data['intent_id'] );

		/**
		 * If we have a status for the fraud outcome, we want to add the proper meta data.
		 * If auth/capture is enabled and the transaction is allowed, it will be 'allow'.
		 * If it was held for review for any reason, it will be 'review'.
		 */
		if ( '' !== $intent_data['fraud_outcome'] && Rule::is_valid_fraud_outcome_status( $intent_data['fraud_outcome'] ) ) {
			$fraud_meta_box_type = Order_Status::ON_HOLD === $order->get_status() ? Fraud_Meta_Box_Type::REVIEW_ALLOWED : Fraud_Meta_Box_Type::ALLOW;
			$this->set_fraud_outcome_status_for_order( $order, $intent_data['fraud_outcome'] );
			$this->set_fraud_meta_box_type_for_order( $order, $fraud_meta_box_type );
		}

		if ( ! $this->intent_has_card_payment_type( $intent_data ) ) {
			$this->set_fraud_meta_box_type_for_order( $order, Fraud_Meta_Box_Type::NOT_CARD );
		}

		$this->update_order_status( $order, 'payment_complete', $intent_data['intent_id'] );
		$order->add_order_note( $note );
		$this->set_intention_status_for_order( $order, $intent_data['intent_status'] );
	}

	/**
	 * Updates an order to on-hold status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param array    $intent_data   The intent data associated with this order.
	 *
	 * @return void
	 */
	private function mark_payment_authorized( $order, $intent_data ) {
		$note = $this->generate_payment_authorized_note( $order, $intent_data['intent_id'], $intent_data['charge_id'] );
		if ( $this->order_note_exists( $order, $note )
			|| $order->has_status( [ Order_Status::ON_HOLD ] ) ) {
			return;
		}

		if ( Rule::FRAUD_OUTCOME_ALLOW === $intent_data['fraud_outcome'] ) {
			$this->set_fraud_outcome_status_for_order( $order, Rule::FRAUD_OUTCOME_ALLOW );
			$this->set_fraud_meta_box_type_for_order( $order, Fraud_Meta_Box_Type::ALLOW );
		}

		$this->update_order_status( $order, Order_Status::ON_HOLD );
		$order->add_order_note( $note );
		$this->set_intention_status_for_order( $order, $intent_data['intent_status'] );
	}

	/**
	 * Updates an order to processing/completed status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param array    $intent_data   The intent data associated with this order.
	 *
	 * @return void
	 */
	private function mark_payment_capture_completed( $order, $intent_data ) {
		$note = $this->generate_capture_success_note( $order, $intent_data['intent_id'], $intent_data['charge_id'] );
		if ( $this->order_note_exists( $order, $note ) ) {
			return;
		}

		// Update the note with the fee breakdown details async.
		$this->enqueue_add_fee_breakdown_to_order_notes( $order, $intent_data['intent_id'] );

		/**
		 * If we have a status for the fraud outcome, we want to add the proper meta data.
		 * If auth/capture is enabled and the transaction is allowed, it will be 'allow'.
		 * If it was held for review for any reason, it will be 'review'.
		 */
		if ( '' !== $intent_data['fraud_outcome'] && Rule::is_valid_fraud_outcome_status( $intent_data['fraud_outcome'] ) ) {
			$fraud_meta_box_type = Rule::FRAUD_OUTCOME_REVIEW === $this->get_fraud_outcome_status_for_order( $order ) ? Fraud_Meta_Box_Type::REVIEW_ALLOWED : Fraud_Meta_Box_Type::ALLOW;
			$this->set_fraud_outcome_status_for_order( $order, $intent_data['fraud_outcome'] );
			$this->set_fraud_meta_box_type_for_order( $order, $fraud_meta_box_type );
		}

		$this->update_order_status( $order, 'payment_complete', $intent_data['intent_id'] );
		$order->add_order_note( $note );
		$this->set_intention_status_for_order( $order, $intent_data['intent_status'] );
	}

	/**
	 * Leaves an order in pending status, while adding a note with a link to the transaction.
	 *
	 * @param WC_Order $order         Order object.
	 * @param array    $intent_data   The intent data associated with this order.
	 *
	 * @return void
	 */
	private function mark_payment_started( $order, $intent_data ) {
		$note = $this->generate_payment_started_note( $order, $intent_data['intent_id'] );
		if ( $this->order_note_exists( $order, $note )
			|| ! $order->has_status( [ Order_Status::PENDING ] ) ) {
			return;
		}

		$fraud_meta_box_type = $this->intent_has_card_payment_type( $intent_data ) ? Fraud_Meta_Box_Type::PAYMENT_STARTED : Fraud_Meta_Box_Type::NOT_CARD;
		$this->set_fraud_meta_box_type_for_order( $order, $fraud_meta_box_type );

		$order->add_order_note( $note );
		$this->set_intention_status_for_order( $order, $intent_data['intent_status'] );
	}

	/**
	 * Changes status to On-Hold, adds fraud meta data, and adds the fraud held for review note.
	 *
	 * @param WC_Order $order         Order object.
	 * @param array    $intent_data   The intent data associated with this order.
	 *
	 * @return void
	 */
	private function mark_order_held_for_review_for_fraud( $order, $intent_data ) {
		$note = $this->generate_fraud_held_for_review_note( $order, $intent_data['intent_id'], $intent_data['charge_id'] );
		if ( $this->order_note_exists( $order, $note ) ) {
			return;
		}

		$this->update_order_status( $order, Order_Status::ON_HOLD );
		$this->set_fraud_outcome_status_for_order( $order, Rule::FRAUD_OUTCOME_REVIEW );
		$this->set_fraud_meta_box_type_for_order( $order, Fraud_Meta_Box_Type::REVIEW );
		$order->add_order_note( $note );
		$this->set_intention_status_for_order( $order, $intent_data['intent_status'] );
	}

	/**
	 * Given the charge, adds the application_fee_amount from the charge to the given order as metadata.
	 *
	 * @param WC_Order                    $order The order to update.
	 * @param WC_Payments_API_Charge|null $charge The charge to get the application_fee_amount from.
	 */
	public function attach_transaction_fee_to_order( $order, $charge ) {
		try {
			if ( $charge && null !== $charge->get_application_fee_amount() ) {
				$order->update_meta_data(
					self::WCPAY_TRANSACTION_FEE_META_KEY,
					WC_Payments_Utils::interpret_stripe_amount( $charge->get_application_fee_amount(), $charge->get_currency() )
				);
				$order->save_meta_data();
			}
		} catch ( Exception $e ) {
			// Log the error and don't block checkout.
			Logger::log( 'Error saving transaction fee into metadata for the order ' . $order->get_id() . ': ' . $e->getMessage() );
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
				/* translators: %1: the successfully charged amount, %2: WooPayments, %3: transaction ID of the payment */
				__( 'A payment of %1$s was <strong>successfully charged</strong> using %2$s (<a>%3$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$formatted_amount,
			'WooPayments',
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
				/* translators: %1: the authorized amount, %2: WooPayments, %3: transaction ID of the payment */
				__( 'A payment of %1$s <strong>failed</strong> using %2$s (<a>%3$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$formatted_amount,
			'WooPayments',
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
		);

		if ( ! empty( $message ) ) {
			$note .= ' ' . $message;
		}

		return $note;
	}

	/**
	 * Generates the payment authorized order note.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return string
	 */
	private function generate_payment_authorized_note( $order, $intent_id, $charge_id ): string {
		$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: WooPayments, %3: transaction ID of the payment */
				__( 'A payment of %1$s was <strong>authorized</strong> using %2$s (<a>%3$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$this->get_order_amount( $order ),
			'WooPayments',
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
		);

		return $note;
	}

	/**
	 * Generates the payment started order note.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 *
	 * @return string
	 */
	private function generate_payment_started_note( $order, $intent_id ): string {
		$note = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: WooPayments, %3: intent ID of the payment */
				__( 'A payment of %1$s was <strong>started</strong> using %2$s (<code>%3$s</code>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'code'   => '<code>',
				]
			),
			$this->get_order_amount( $order ),
			'WooPayments',
			$intent_id
		);

		return $note;
	}

	/**
	 * Generates the successful capture order note.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return string
	 */
	private function generate_capture_success_note( $order, $intent_id, $charge_id ) {
		$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the successfully charged amount, %2: WooPayments, %3: transaction ID of the payment */
				__( 'A payment of %1$s was <strong>successfully captured</strong> using %2$s (<a>%3$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$this->get_order_amount( $order ),
			'WooPayments',
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
		);
		return $note;
	}

	/**
	 * Generates the failure order note and additional message, if included.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 * @param string   $message   Optional message to add to the note.
	 *
	 * @return string
	 */
	private function generate_capture_failed_note( $order, $intent_id, $charge_id, $message ): string {
		$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
		$note            = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: WooPayments, %3: transaction ID of the payment */
				__( 'A capture of %1$s <strong>failed</strong> to complete using %2$s (<a>%3$s</a>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'a'      => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$this->get_order_amount( $order ),
			'WooPayments',
			WC_Payments_Utils::get_transaction_url_id( $intent_id, $charge_id )
		);

		if ( ! empty( $message ) ) {
			$note .= ' ' . $message;
		}

		return $note;
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
	 * Generates the capture cancelled order note.
	 *
	 * @return string
	 */
	private function generate_capture_cancelled_note(): string {
		$note = WC_Payments_Utils::esc_interpolated_html(
			__( 'Payment authorization was successfully <strong>cancelled</strong>.', 'woocommerce-payments' ),
			[ 'strong' => '<strong>' ]
		);

		return $note;
	}

	/**
	 * Generates the fraud held for review order note.
	 *
	 * @param WC_Order $order     Order object.
	 * @param string   $intent_id The ID of the intent associated with this order.
	 * @param string   $charge_id The charge ID related to the intent/order.
	 *
	 * @return string
	 */
	private function generate_fraud_held_for_review_note( $order, $intent_id, $charge_id ): string {
		$transaction_url = WC_Payments_Utils::compose_transaction_url(
			$intent_id,
			$charge_id,
			[
				'status_is' => Rule::FRAUD_OUTCOME_REVIEW,
				'type_is'   => 'order_note',
			]
		);

		$note = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the authorized amount, %2: transaction ID of the payment */
				__( '&#x26D4; A payment of %1$s was <strong>held for review</strong> by one or more risk filters.<br><br><a>View more details</a>.', 'woocommerce-payments' ),
				[
					'&#x26D4;' => '&#x26D4;',
					'strong'   => '<strong>',
					'br'       => '<br>',
					'a'        => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$this->get_order_amount( $order )
		);

		return $note;
	}

	/**
	 * Generates the fraud blocked order note.
	 *
	 * @param WC_Order $order     Order object.
	 *
	 * @return string
	 */
	private function generate_fraud_blocked_note( $order ): string {
		$transaction_url = WC_Payments_Utils::compose_transaction_url(
			$order->get_id(),
			'',
			[
				'status_is' => Rule::FRAUD_OUTCOME_BLOCK,
				'type_is'   => 'order_note',
			]
		);

		$note = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the blocked amount, %2: transaction ID of the payment */
				__( '&#x1F6AB; A payment of %1$s was <strong>blocked</strong> by one or more risk filters.<br><br><a>View more details</a>.', 'woocommerce-payments' ),
				[
					'&#x1F6AB;' => '&#x1F6AB;',
					'strong'    => '<strong>',
					'br'        => '<br>',
					'a'         => ! empty( $transaction_url ) ? '<a href="' . $transaction_url . '" target="_blank" rel="noopener noreferrer">' : '<code>',
				]
			),
			$this->get_order_amount( $order )
		);

		return $note;
	}

	/**
	 * Get content for the dispute created order note.
	 *
	 * @param string $charge_id  The ID of the disputes charge associated with this order.
	 * @param string $amount     The disputed amount – formatted currency value.
	 * @param string $reason     The reason for the dispute – human-readable text.
	 * @param string $due_by     The deadline for responding to the dispute - formatted date string.
	 *
	 * @return string Note content.
	 */
	private function generate_dispute_created_note( $charge_id, $amount, $reason, $due_by ) {
		$dispute_url = $this->compose_dispute_url( $charge_id );

		// Get merchant-friendly dispute reason description.
		$reason = WC_Payments_Utils::get_dispute_reason_description( $reason );

		return sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the disputed amount and currency; %2: the dispute reason; %3 the deadline date for responding to dispute */
				__( 'Payment has been disputed for %1$s with reason "%2$s". <a>Response due by %3$s</a>.', 'woocommerce-payments' ),
				[
					'a' => '<a href="' . $dispute_url . '" target="_blank" rel="noopener noreferrer">',
				]
			),
			$amount,
			$reason,
			$due_by
		);
	}

	/**
	 * Get content for the dispute closed order note.
	 *
	 * @param string $charge_id The ID of the disputed charge associated with this order.
	 * @param string $status    The status of the dispute.
	 *
	 * @return string Note content.
	 */
	private function generate_dispute_closed_note( $charge_id, $status ) {
		$dispute_url = $this->compose_dispute_url( $charge_id );
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
	 * @param string $charge_id The disputed charge ID.
	 *
	 * @return string Transaction details page url.
	 */
	private function compose_dispute_url( $charge_id ) {
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
			$this->set_intention_status_for_order( $order, $intent_status );
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

	/**
	 * Takes an intent object or array and returns our needed data as an array.
	 * This is needed due to intents can either be objects or arrays.
	 *
	 * @param WC_Payments_API_Abstract_Intention $intent  Setup or payment intent to pull the data from.
	 *
	 * @return array The data we need to continue processing.
	 */
	private function get_intent_data( WC_Payments_API_Abstract_Intention $intent ): array {

		$intent_data = [
			'intent_id'           => $intent->get_id(),
			'intent_status'       => $intent->get_status(),
			'charge_id'           => '',
			'fraud_outcome'       => $intent->get_metadata()['fraud_outcome'] ?? '',
			'payment_method_type' => $intent->get_payment_method_type(),
		];

		if ( $intent instanceof WC_Payments_API_Payment_Intention ) {
			$charge                   = $intent->get_charge();
			$intent_data['charge_id'] = $charge ? $charge->get_id() : null;
		}

		return $intent_data;
	}

	/**
	 * Schedules an action to add the fee breakdown to order notes.
	 *
	 * @param WC_Order $order The order to add the note to.
	 * @param string   $intent_id The intent ID for the order.
	 *
	 * @return void
	 */
	private function enqueue_add_fee_breakdown_to_order_notes( WC_Order $order, string $intent_id ) {
		WC_Payments::get_action_scheduler_service()->schedule_job(
			time(),
			self::ADD_FEE_BREAKDOWN_TO_ORDER_NOTES,
			[
				'order_id'     => $order->get_id(),
				'intent_id'    => $intent_id,
				'is_test_mode' => WC_Payments::mode()->is_test(),
			]
		);
	}

	/**
	 * If an order object is passed in, return that, else try to get the order.
	 * This is needed due to mocked orders cannot be retrieved from the database in tests.
	 *
	 * @param mixed $order The order to be returned.
	 *
	 * @return WC_Order|WC_Order_Refund
	 *
	 * @throws Order_Not_Found_Exception
	 */
	private function get_order( $order ) {
		$order = $this->is_order_type_object( $order ) ? $order : wc_get_order( $order );
		if ( ! $this->is_order_type_object( $order ) ) {
			throw new Order_Not_Found_Exception(
				__( 'The requested order was not found.', 'woocommerce-payments' ),
				'order_not_found'
			);
		}
		return $order;
	}

	/**
	 * Checks to see if the given argument is an order type object.
	 *
	 * @param mixed $order The order to be checked.
	 *
	 * @return bool
	 */
	private function is_order_type_object( $order ): bool {
		if ( is_a( $order, 'WC_Order' ) || is_a( $order, 'WC_Order_Refund' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks to see if the intent data has just card set as the payment method type.
	 *
	 * @param array $intent_data The intent data obtained from get_intent_data.
	 *
	 * @return bool
	 */
	private function intent_has_card_payment_type( $intent_data ): bool {
		return isset( $intent_data['payment_method_type'] ) && 'card' === $intent_data['payment_method_type'];
	}
}

<?php
/**
 * WC_Payments_Webhook_Processing_Service class
 *
 * @package WooCommerce\Payments
 */

use WCPay\Constants\Payment_Method;
use WCPay\Exceptions\Invalid_Payment_Method_Exception;
use WCPay\Exceptions\Invalid_Webhook_Data_Exception;
use WCPay\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Service to process webhook data.
 */
class WC_Payments_Webhook_Processing_Service {
	/**
	 * DB wrapper.
	 *
	 * @var WC_Payments_DB
	 */
	private $wcpay_db;

	/**
	 * WC Payments Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC Payments Remote Note Service.
	 *
	 * @var WC_Payments_Remote_Note_Service
	 */
	private $remote_note_service;

	/**
	 * WC_Payments_Webhook_Processing_Service constructor.
	 *
	 * @param WC_Payments_DB                  $wcpay_db            WC_Payments_DB instance.
	 * @param WC_Payments_Account             $account             WC_Payments_Account instance.
	 * @param WC_Payments_Remote_Note_Service $remote_note_service WC_Payments_Remote_Note_Service instance.
	 */
	public function __construct(
		WC_Payments_DB $wcpay_db,
		WC_Payments_Account $account,
		WC_Payments_Remote_Note_Service $remote_note_service
	) {
		$this->wcpay_db            = $wcpay_db;
		$this->account             = $account;
		$this->remote_note_service = $remote_note_service;
	}

	/**
	 * Process webhook event data.
	 *
	 * @param  array $event_body Body data of webhook request.
	 *
	 * @return void
	 *
	 * @throws Invalid_Webhook_Data_Exception
	 */
	public function process( array $event_body ) {

		$event_type = $this->read_webhook_property( $event_body, 'type' );

		switch ( $event_type ) {
			case 'charge.refund.updated':
				$this->process_webhook_refund_updated( $event_body );
				break;
			case 'charge.dispute.created':
				$this->process_webhook_dispute_created( $event_body );
				break;
			case 'charge.dispute.closed':
				$this->process_webhook_dispute_closed( $event_body );
				break;
			case 'charge.dispute.funds_reinstated':
			case 'charge.dispute.funds_withdrawn':
			case 'charge.dispute.updated':
				$this->process_webhook_dispute_updated( $event_body );
				break;
			case 'charge.expired':
				$this->process_webhook_expired_authorization( $event_body );
				break;
			case 'account.updated':
				$this->account->refresh_account_data();
				break;
			case 'wcpay.notification':
				$note = $this->read_webhook_property( $event_body, 'data' );
				$this->remote_note_service->put_note( $note );
				break;
			case 'payment_intent.payment_failed':
				$this->process_webhook_payment_intent_failed( $event_body );
				break;
			case 'payment_intent.succeeded':
				$this->process_webhook_payment_intent_succeeded( $event_body );
				break;
			case 'invoice.upcoming':
				WC_Payments_Subscriptions::get_event_handler()->handle_invoice_upcoming( $event_body );
				break;
			case 'invoice.paid':
				WC_Payments_Subscriptions::get_event_handler()->handle_invoice_paid( $event_body );
				break;
			case 'invoice.payment_failed':
				WC_Payments_Subscriptions::get_event_handler()->handle_invoice_payment_failed( $event_body );
				break;
		}
	}

	/**
	 * Safely get a value from the webhook event body array.
	 *
	 * @param array  $array Array to read from.
	 * @param string $key   ID to fetch on.
	 *
	 * @return string|array|int
	 * @throws Invalid_Webhook_Data_Exception Thrown if ID not set.
	 */
	public function read_webhook_property( $array, $key ) {
		if ( ! isset( $array[ $key ] ) ) {
			throw new Invalid_Webhook_Data_Exception(
				sprintf(
				/* translators: %1: ID being fetched */
					__( '%1$s not found in array', 'woocommerce-payments' ),
					$key
				)
			);
		}
		return $array[ $key ];
	}

	/**
	 * Process webhook refund updated.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve charge ID to order.
	 */
	private function process_webhook_refund_updated( $event_body ) {
		$event_data   = $this->read_webhook_property( $event_body, 'data' );
		$event_object = $this->read_webhook_property( $event_data, 'object' );

		// First, check the reason for the update. We're only interested in a status of failed.
		$status = $this->read_webhook_property( $event_object, 'status' );
		if ( 'failed' !== $status ) {
			return;
		}

		// Fetch the details of the failed refund so that we can find the associated order and write a note.
		$charge_id = $this->read_webhook_property( $event_object, 'charge' );
		$refund_id = $this->read_webhook_property( $event_object, 'id' );
		$amount    = $this->read_webhook_property( $event_object, 'amount' );
		$currency  = $this->read_webhook_property( $event_object, 'currency' );

		// Look up the order related to this charge.
		$order = $this->wcpay_db->order_from_charge_id( $charge_id );
		if ( ! $order ) {
			throw new Invalid_Payment_Method_Exception(
				sprintf(
				/* translators: %1: charge ID */
					__( 'Could not find order via charge ID: %1$s', 'woocommerce-payments' ),
					$charge_id
				),
				'order_not_found'
			);
		}

		$note = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
			/* translators: %1: the refund amount, %2: ID of the refund */
				__( 'A refund of %1$s was <strong>unsuccessful</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'code'   => '<code>',
				]
			),
			WC_Payments_Explicit_Price_Formatter::get_explicit_price(
				wc_price( WC_Payments_Utils::interpret_stripe_amount( $amount, $currency ), [ 'currency' => strtoupper( $currency ) ] ),
				$order
			),
			$refund_id
		);
		$order->add_order_note( $note );
		$order->update_meta_data( '_wcpay_refund_status', 'failed' );
		$order->save();
	}

	/**
	 * Process webhook for an expired uncaptured payment.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve charge ID to order.
	 */
	private function process_webhook_expired_authorization( $event_body ) {
		$event_data   = $this->read_webhook_property( $event_body, 'data' );
		$event_object = $this->read_webhook_property( $event_data, 'object' );

		// Fetch the details of the expired auth so that we can find the associated order.
		$charge_id = $this->read_webhook_property( $event_object, 'id' );

		// Look up the order related to this charge.
		$order = $this->wcpay_db->order_from_charge_id( $charge_id );
		if ( ! $order ) {
			throw new Invalid_Payment_Method_Exception(
				sprintf(
				/* translators: %1: charge ID */
					__( 'Could not find order via charge ID: %1$s', 'woocommerce-payments' ),
					$charge_id
				),
				'order_not_found'
			);
		}

		// TODO: Revisit this logic once we support partial captures or multiple charges for order. We'll need to handle the "payment_intent.canceled" event too.
		WC_Payments_Utils::mark_payment_expired( $order );
	}

	/**
	 * Process webhook for a failed payment intent.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve charge ID to order.
	 */
	private function process_webhook_payment_intent_failed( $event_body ) {
		// Check to make sure we should process this according to the payment method.
		$charges_data        = $event_body['data']['object']['charges']['data'][0] ?? null;
		$payment_method_type = $charges_data['payment_method_details']['type'] ?? null;

		$actionable_methods = [
			Payment_Method::US_BANK_ACCOUNT,
			Payment_Method::BECS,
		];

		if ( empty( $payment_method_type ) || ! in_array( $payment_method_type, $actionable_methods, true ) ) {
			return;
		}

		// Get the order and make sure it is an order, it is not already in failed status, and the payment methods match.
		$order             = $this->get_order_from_event_body_intent_id( $event_body );
		$payment_method_id = $charges_data['payment_method'] ?? null;

		if ( ! $order
			|| $order->has_status( [ 'failed' ] )
			|| empty( $payment_method_id )
			|| $payment_method_id !== $order->get_meta( '_payment_method_id' ) ) {
			return;
		}

		WC_Payments_Utils::mark_payment_failed( $order, $this->get_failure_message_from_event( $event_body ) );
	}

	/**
	 * Process webhook for a successful payment intent.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve charge ID to order.
	 */
	private function process_webhook_payment_intent_succeeded( $event_body ) {
		$event_data   = $this->read_webhook_property( $event_body, 'data' );
		$event_object = $this->read_webhook_property( $event_data, 'object' );
		$intent_id    = $this->read_webhook_property( $event_object, 'id' );
		$order        = $this->get_order_from_event_body_intent_id( $event_body );

		WC_Payments_Utils::mark_payment_completed( $order, $intent_id );
	}

	/**
	 * Process webhook dispute created.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception Required parameters not found.
	 */
	private function process_webhook_dispute_created( $event_body ) {
		$event_type   = $this->read_webhook_property( $event_body, 'type' );
		$event_data   = $this->read_webhook_property( $event_body, 'data' );
		$event_object = $this->read_webhook_property( $event_data, 'object' );
		$dispute_id   = $this->read_webhook_property( $event_object, 'id' );
		$charge_id    = $this->read_webhook_property( $event_object, 'charge' );
		$reason       = $this->read_webhook_property( $event_object, 'reason' );
		$order        = $this->wcpay_db->order_from_charge_id( $charge_id );

		if ( ! $order ) {
			throw new Invalid_Webhook_Data_Exception(
				sprintf(
				/* translators: %1: charge ID */
					__( 'Could not find order via charge ID: %1$s', 'woocommerce-payments' ),
					$charge_id
				)
			);
		}

		$note = sprintf(
		/* translators: %1: the dispute reason, %2: the dispute details URL */
			__( 'Payment has been disputed as %1$s. See <a href="%2$s">dispute overview</a> for more details.', 'woocommerce-payments' ),
			$reason,
			add_query_arg(
				[ 'id' => $dispute_id ],
				admin_url( 'admin.php?page=wc-admin&path=/payments/disputes/details' )
			)
		);

		$order->add_order_note( $note );
		$order->update_status( 'on-hold' );
	}

	/**
	 * Process webhook dispute closed.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception Required parameters not found.
	 */
	private function process_webhook_dispute_closed( $event_body ) {
		$event_type   = $this->read_webhook_property( $event_body, 'type' );
		$event_data   = $this->read_webhook_property( $event_body, 'data' );
		$event_object = $this->read_webhook_property( $event_data, 'object' );
		$dispute_id   = $this->read_webhook_property( $event_object, 'id' );
		$charge_id    = $this->read_webhook_property( $event_object, 'charge' );
		$status       = $this->read_webhook_property( $event_object, 'status' );
		$order        = $this->wcpay_db->order_from_charge_id( $charge_id );

		if ( ! $order ) {
			throw new Invalid_Webhook_Data_Exception(
				sprintf(
				/* translators: %1: charge ID */
					__( 'Could not find order via charge ID: %1$s', 'woocommerce-payments' ),
					$charge_id
				)
			);
		}

		$note = sprintf(
		/* translators: %1: the dispute status, %2: the dispute details URL */
			__( 'Payment dispute has been closed with status %1$s. See <a href="%2$s">dispute overview</a> for more details.', 'woocommerce-payments' ),
			$status,
			add_query_arg(
				[ 'id' => $dispute_id ],
				admin_url( 'admin.php?page=wc-admin&path=/payments/disputes/details' )
			)
		);

		$order->add_order_note( $note );

		if ( 'lost' === $status ) {
			wc_create_refund(
				[
					'amount'     => $order->get_total(),
					'reason'     => __( 'dispute lost', 'woocommerce-payments' ),
					'order_id'   => $order->get_id(),
					'line_items' => $order->get_items(),
				]
			);
		} else {
			$order->update_status( 'completed' );
		}
	}

	/**
	 * Process webhook dispute updated.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception Required parameters not found.
	 */
	private function process_webhook_dispute_updated( $event_body ) {
		$event_type   = $this->read_webhook_property( $event_body, 'type' );
		$event_data   = $this->read_webhook_property( $event_body, 'data' );
		$event_object = $this->read_webhook_property( $event_data, 'object' );
		$dispute_id   = $this->read_webhook_property( $event_object, 'id' );
		$charge_id    = $this->read_webhook_property( $event_object, 'charge' );
		$order        = $this->wcpay_db->order_from_charge_id( $charge_id );

		if ( ! $order ) {
			throw new Invalid_Webhook_Data_Exception(
				sprintf(
				/* translators: %1: charge ID */
					__( 'Could not find order via charge ID: %1$s', 'woocommerce-payments' ),
					$charge_id
				)
			);
		}

		switch ( $event_type ) {
			case 'charge.dispute.funds_withdrawn':
				$message = __( 'Payment dispute funds have been withdrawn', 'woocommerce-payments' );
				break;
			case 'charge.dispute.funds_reinstated':
				$message = __( 'Payment dispute funds have been reinstated', 'woocommerce-payments' );
				break;
			default:
				$message = __( 'Payment dispute has been updated', 'woocommerce-payments' );
		}

		$note = sprintf(
		/* translators: %1: the dispute message, %2: the dispute details URL */
			__( '%1$s. See <a href="%2$s">dispute overview</a> for more details.', 'woocommerce-payments' ),
			$message,
			add_query_arg(
				[ 'id' => $dispute_id ],
				admin_url( 'admin.php?page=wc-admin&path=/payments/disputes/details' )
			)
		);

		$order->add_order_note( $note );
	}

	/**
	 * Gets the order related to the event intent id.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve charge ID to order.
	 *
	 * @return boolean|WC_Order|WC_Order_Refund
	 */
	private function get_order_from_event_body_intent_id( $event_body ) {
		$event_data   = $this->read_webhook_property( $event_body, 'data' );
		$event_object = $this->read_webhook_property( $event_data, 'object' );
		$intent_id    = $this->read_webhook_property( $event_object, 'id' );

		// Look up the order related to this charge.
		$order = $this->wcpay_db->order_from_intent_id( $intent_id );

		if ( ! $order ) {
			// Retrieving order with order_id in case intent_id was not properly set.
			Logger::debug( 'intent_id not found, using order_id to retrieve order' );
			$metadata = $this->read_webhook_property( $event_object, 'metadata' );

			if ( isset( $metadata['order_id'] ) ) {
				$order_id = $metadata['order_id'];
				$order    = $this->wcpay_db->order_from_order_id( $order_id );
			} elseif ( ! empty( $event_object['invoice'] ) ) {
				// If the payment intent contains an invoice it is a WCPay Subscription-related intent and will be handled by the `invoice.paid` event.
				return false;
			}
		}

		if ( ! $order ) {
			throw new Invalid_Payment_Method_Exception(
				sprintf(
				/* translators: %1: charge ID */
					__( 'Could not find order via intent ID: %1$s', 'woocommerce-payments' ),
					$intent_id
				),
				'order_not_found'
			);
		}

		// Get an updated set of order properties to avoid race conditions when the server sends the paid webhook before we've finished processing the original payment request.
		$order->get_data_store()->read( $order );

		return $order;
	}

	/**
	 * Gets the proper failure message from the code in the event.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @return string The failure message.
	 */
	private function get_failure_message_from_event( $event_body ):string {
		// Get the failure code from the event body.
		$event_data    = $this->read_webhook_property( $event_body, 'data' );
		$event_object  = $this->read_webhook_property( $event_data, 'object' );
		$event_charges = $this->read_webhook_property( $event_object, 'charges' );
		$charges_data  = $this->read_webhook_property( $event_charges, 'data' );
		$failure_code  = $charges_data[0]['failure_code'] ?? '';

		switch ( $failure_code ) {
			case 'account_closed':
				$failure_message = __( "The customer's bank account has been closed.", 'woocommerce-payments' );
				break;
			case 'debit_not_authorized':
				$failure_message = __( 'The customer has notified their bank that this payment was unauthorized.', 'woocommerce-payments' );
				break;
			case 'insufficient_funds':
				$failure_message = __( "The customer's account has insufficient funds to cover this payment.", 'woocommerce-payments' );
				break;
			case 'no_account':
				$failure_message = __( "The customer's bank account could not be located.", 'woocommerce-payments' );
				break;
			case 'payment_method_microdeposit_failed':
				$failure_message = __( 'Microdeposit transfers failed. Please check the account, institution and transit numbers.', 'woocommerce-payments' );
				break;
			case 'payment_method_microdeposit_verification_attempts_exceeded':
				$failure_message = __( 'You have exceeded the number of allowed verification attempts.', 'woocommerce-payments' );
				break;

			default:
				$failure_message = __( 'The payment was not able to be processed.', 'woocommerce-payments' );
				break;
		}

		return $failure_message;
	}
}

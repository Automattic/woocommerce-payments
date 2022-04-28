<?php
/**
 * WC_Payments_Webhook_Processing_Service class
 *
 * @package WooCommerce\Payments
 */

use WCPay\Constants\Payment_Method;
use WCPay\Exceptions\Invalid_Payment_Method_Exception;
use WCPay\Exceptions\Invalid_Webhook_Data_Exception;
use WCPay\Exceptions\Rest_Request_Exception;
use WCPay\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Service to process webhook data.
 */
class WC_Payments_Webhook_Processing_Service {
	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $api_client;

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
	 * WC_Payments_Order_Service instance
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * WC_Payments_In_Person_Payments_Receipts_Service
	 *
	 * @var WC_Payments_In_Person_Payments_Receipts_Service
	 */
	private $receipt_service;

	/**
	 * WC_Payment_Gateway_WCPay
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * WC_Payment_Gateway_WCPay
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * WC_Payments_Webhook_Processing_Service constructor.
	 *
	 * @param WC_Payments_API_Client                          $api_client          WooCommerce Payments API client.
	 * @param WC_Payments_DB                                  $wcpay_db            WC_Payments_DB instance.
	 * @param WC_Payments_Account                             $account             WC_Payments_Account instance.
	 * @param WC_Payments_Remote_Note_Service                 $remote_note_service WC_Payments_Remote_Note_Service instance.
	 * @param WC_Payments_Order_Service                       $order_service       WC_Payments_Order_Service instance.
	 * @param WC_Payments_In_Person_Payments_Receipts_Service $receipt_service     WC_Payments_In_Person_Payments_Receipts_Service instance.
	 * @param WC_Payment_Gateway_WCPay                        $wcpay_gateway       WC_Payment_Gateway_WCPay instance.
	 * @param WC_Payments_Customer_Service                    $customer_service    WC_Payments_Customer_Service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_DB $wcpay_db,
		WC_Payments_Account $account,
		WC_Payments_Remote_Note_Service $remote_note_service,
		WC_Payments_Order_Service $order_service,
		WC_Payments_In_Person_Payments_Receipts_Service $receipt_service,
		WC_Payment_Gateway_WCPay $wcpay_gateway,
		WC_Payments_Customer_Service $customer_service
	) {
		$this->wcpay_db            = $wcpay_db;
		$this->account             = $account;
		$this->remote_note_service = $remote_note_service;
		$this->order_service       = $order_service;
		$this->api_client          = $api_client;
		$this->receipt_service     = $receipt_service;
		$this->wcpay_gateway       = $wcpay_gateway;
		$this->customer_service    = $customer_service;
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
		// Extract information about the webhook event.
		$event_type = $this->read_webhook_property( $event_body, 'type' );

		Logger::debug( 'Webhook received: ' . $event_type );
		Logger::debug(
			'Webhook body: '
			. var_export( WC_Payments_Utils::redact_array( $event_body, WC_Payments_API_Client::API_KEYS_TO_REDACT ), true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

		try {
			do_action( 'woocommerce_payments_before_webhook_delivery', $event_type, $event_body );
		} catch ( Exception $e ) {
			Logger::error( $e );
		}

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
				$this->customer_service->delete_cached_payment_methods();
				break;
			case 'wcpay.notification':
				$this->process_wcpay_notification( $event_body );
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

		try {
			do_action( 'woocommerce_payments_after_webhook_delivery', $event_type, $event_body );
		} catch ( Exception $e ) {
			Logger::error( $e );
		}
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

		if ( $this->order_service->order_note_exists( $order, $note ) ) {
			return;
		}

		/**
		 * Get refunds from order and delete refund if matches wcpay refund id.
		 *
		 * @var $wc_refunds WC_Order_Refund[]
		 * */
		$wc_refunds = $order->get_refunds();
		if ( ! empty( $wc_refunds ) ) {
			foreach ( $wc_refunds as $wc_refund ) {
				$wcpay_refund_id = $wc_refund->get_meta( '_wcpay_refund_id', true );
				if ( $refund_id === $wcpay_refund_id ) {
					// Delete WC Refund.
					$wc_refund->delete();
					break;
				}
			}
		}

		// Update order status if order is fully refunded.
		$current_order_status = $order->get_status();
		if ( 'refunded' === $current_order_status ) {
			$order->update_status( 'failed' );
		}

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

		// Get the intent_id and then its status.
		$intent_id     = $event_object['payment_intent'] ?? $order->get_meta( '_intent_id' );
		$intent        = $this->api_client->get_intent( $intent_id );
		$intent_status = $intent->get_status();

		// TODO: Revisit this logic once we support partial captures or multiple charges for order. We'll need to handle the "payment_intent.canceled" event too.
		$this->order_service->mark_payment_capture_expired( $order, $intent_id, $intent_status, $charge_id );
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

		// Get the order and make sure it is an order and the payment methods match.
		$order             = $this->get_order_from_event_body_intent_id( $event_body );
		$payment_method_id = $charges_data['payment_method'] ?? null;

		if ( ! $order
			|| empty( $payment_method_id )
			|| $payment_method_id !== $order->get_meta( '_payment_method_id' ) ) {
			return;
		}

		$event_data    = $this->read_webhook_property( $event_body, 'data' );
		$event_object  = $this->read_webhook_property( $event_data, 'object' );
		$intent_id     = $this->read_webhook_property( $event_object, 'id' );
		$intent_status = $this->read_webhook_property( $event_object, 'status' );
		$charge_id     = $this->read_webhook_property( $charges_data, 'id' );

		$this->order_service->mark_payment_failed( $order, $intent_id, $intent_status, $charge_id, $this->get_failure_message_from_event( $event_body ) );  }

	/**
	 * Process webhook for a successful payment intent.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception   Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve intent ID to order.
	 */
	private function process_webhook_payment_intent_succeeded( $event_body ) {
		$event_data    = $this->read_webhook_property( $event_body, 'data' );
		$event_object  = $this->read_webhook_property( $event_data, 'object' );
		$intent_id     = $this->read_webhook_property( $event_object, 'id' );
		$order         = $this->get_order_from_event_body_intent_id( $event_body );
		$intent_status = $this->read_webhook_property( $event_object, 'status' );
		$event_charges = $this->read_webhook_property( $event_object, 'charges' );
		$charges_data  = $this->read_webhook_property( $event_charges, 'data' );
		$charge_id     = $this->read_webhook_property( $charges_data[0], 'id' );

		// update _charge_id meta if it doesn't exist - happens when maybe_process_upe_redirect fails sometimes.
		if ( $charge_id && ! $order->get_meta( '_charge_id' ) ) {
			$order->update_meta_data( '_charge_id', $charge_id );
		}

		$this->order_service->mark_payment_completed( $order, $intent_id, $intent_status, $charge_id );

		// Send the customer a card reader receipt if it's an in person payment type.
		$payment_method = $charges_data[0]['payment_method_details']['type'] ?? null;
		if ( Payment_Method::CARD_PRESENT === $payment_method || Payment_Method::INTERAC_PRESENT === $payment_method ) {
			$merchant_settings = [
				'business_name' => $this->wcpay_gateway->get_option( 'account_business_name' ),
				'support_info'  => [
					'address' => $this->wcpay_gateway->get_option( 'account_business_support_address' ),
					'phone'   => $this->wcpay_gateway->get_option( 'account_business_support_phone' ),
					'email'   => $this->wcpay_gateway->get_option( 'account_business_support_email' ),
				],
			];
			$this->receipt_service->send_customer_ipp_receipt_email( $order, $merchant_settings, $charges_data[0] );
		}
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

		$this->order_service->mark_payment_dispute_created( $order, $dispute_id, $reason );
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

		$this->order_service->mark_payment_dispute_closed( $order, $dispute_id, $status );
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

		if ( $this->order_service->order_note_exists( $order, $note ) ) {
			return;
		}

		$order->add_order_note( $note );
	}

	/**
	 * Process notification data.
	 *
	 * @param  array $event_body The event that triggered the webhook.
	 *
	 * @return void
	 *
	 * @throws Invalid_Webhook_Data_Exception When data is not valid.
	 */
	private function process_wcpay_notification( array $event_body ) {
		$note = $this->read_webhook_property( $event_body, 'data' );

		// Convert exception Rest_Request_Exception to Invalid_Webhook_Data_Exception
		// to be compatible with the expected exception in process().
		try {
			$this->remote_note_service->put_note( $note );
		} catch ( Rest_Request_Exception $e ) {
			throw new Invalid_Webhook_Data_Exception( $e->getMessage() );
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
	private function read_webhook_property( $array, $key ) {
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
	 * Gets the order related to the event intent id.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Invalid_Webhook_Data_Exception   Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve intent ID to order.
	 *
	 * @return boolean|WC_Order|WC_Order_Refund
	 */
	private function get_order_from_event_body_intent_id( $event_body ) {
		$event_data   = $this->read_webhook_property( $event_body, 'data' );
		$event_object = $this->read_webhook_property( $event_data, 'object' );
		$intent_id    = $this->read_webhook_property( $event_object, 'id' );

		// Look up the order related to this intent.
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
				/* translators: %1: intent ID */
					__( 'Could not find order via intent ID: %1$s', 'woocommerce-payments' ),
					$intent_id
				),
				'order_not_found'
			);
		}

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

<?php
/**
 * Class WC_REST_Payments_Webhook_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Constants\Payment_Method;
use WCPay\Exceptions\Invalid_Payment_Method_Exception;
use WCPay\Exceptions\Rest_Request_Exception;
use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for webhooks.
 */
class WC_REST_Payments_Webhook_Controller extends WC_Payments_REST_Controller {

	/**
	 * Result codes for returning to the WCPay server API. They don't have any special meaning, but can will be logged
	 * and are therefore useful when debugging how we reacted to a webhook.
	 */
	const RESULT_SUCCESS     = 'success';
	const RESULT_BAD_REQUEST = 'bad_request';
	const RESULT_ERROR       = 'error';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/webhook';

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
	 * WC_REST_Payments_Webhook_Controller constructor.
	 *
	 * @param WC_Payments_API_Client          $api_client          WC_Payments_API_Client instance.
	 * @param WC_Payments_DB                  $wcpay_db            WC_Payments_DB instance.
	 * @param WC_Payments_Account             $account             WC_Payments_Account instance.
	 * @param WC_Payments_Remote_Note_Service $remote_note_service WC_Payments_Remote_Note_Service instance.
	 * @param WC_Payments_Order_Service       $order_service       WC_Payments_Order_Service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_DB $wcpay_db,
		WC_Payments_Account $account,
		WC_Payments_Remote_Note_Service $remote_note_service,
		WC_Payments_Order_Service $order_service
	) {
		parent::__construct( $api_client );
		$this->wcpay_db            = $wcpay_db;
		$this->account             = $account;
		$this->remote_note_service = $remote_note_service;
		$this->order_service       = $order_service;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_webhook' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve transactions to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$body = $request->get_json_params();

		try {
			// Extract information about the webhook event.
			$event_type = $this->read_rest_property( $body, 'type' );

			Logger::debug( 'Webhook recieved: ' . $event_type );
			Logger::debug(
				'Webhook body: '
				. var_export( WC_Payments_Utils::redact_array( $body, WC_Payments_API_Client::API_KEYS_TO_REDACT ), true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			);

			try {
				do_action( 'woocommerce_payments_before_webhook_delivery', $event_type, $body );
			} catch ( Exception $e ) {
				Logger::error( $e );
			}

			switch ( $event_type ) {
				case 'charge.refund.updated':
					$this->process_webhook_refund_updated( $body );
					break;
				case 'charge.dispute.created':
					$this->process_webhook_dispute_created( $body );
					break;
				case 'charge.dispute.closed':
					$this->process_webhook_dispute_closed( $body );
					break;
				case 'charge.dispute.funds_reinstated':
				case 'charge.dispute.funds_withdrawn':
				case 'charge.dispute.updated':
					$this->process_webhook_dispute_updated( $body );
					break;
				case 'charge.expired':
					$this->process_webhook_expired_authorization( $body );
					break;
				case 'account.updated':
					$this->account->refresh_account_data();
					break;
				case 'wcpay.notification':
					$note = $this->read_rest_property( $body, 'data' );
					$this->remote_note_service->put_note( $note );
					break;
				case 'payment_intent.payment_failed':
					$this->process_webhook_payment_intent_failed( $body );
					break;
				case 'payment_intent.succeeded':
					$this->process_webhook_payment_intent_succeeded( $body );
					break;
				case 'invoice.upcoming':
					WC_Payments_Subscriptions::get_event_handler()->handle_invoice_upcoming( $body );
					break;
				case 'invoice.paid':
					WC_Payments_Subscriptions::get_event_handler()->handle_invoice_paid( $body );
					break;
				case 'invoice.payment_failed':
					WC_Payments_Subscriptions::get_event_handler()->handle_invoice_payment_failed( $body );
					break;
			}

			try {
				do_action( 'woocommerce_payments_after_webhook_delivery', $event_type, $body );
			} catch ( Exception $e ) {
				Logger::error( $e );
			}
		} catch ( Rest_Request_Exception $e ) {
			Logger::error( $e );
			return new WP_REST_Response( [ 'result' => self::RESULT_BAD_REQUEST ], 400 );
		} catch ( Exception $e ) {
			Logger::error( $e );
			return new WP_REST_Response( [ 'result' => self::RESULT_ERROR ], 500 );
		}

		return new WP_REST_Response( [ 'result' => self::RESULT_SUCCESS ] );
	}

	/**
	 * Process webhook refund updated.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Rest_Request_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve charge ID to order.
	 */
	private function process_webhook_refund_updated( $event_body ) {
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );

		// First, check the reason for the update. We're only interesting in a status of failed.
		$status = $this->read_rest_property( $event_object, 'status' );
		if ( 'failed' !== $status ) {
			return;
		}

		// Fetch the details of the failed refund so that we can find the associated order and write a note.
		$charge_id = $this->read_rest_property( $event_object, 'charge' );
		$refund_id = $this->read_rest_property( $event_object, 'id' );
		$amount    = $this->read_rest_property( $event_object, 'amount' );
		$currency  = $this->read_rest_property( $event_object, 'currency' );

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
	 * @throws Rest_Request_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve charge ID to order.
	 */
	private function process_webhook_expired_authorization( $event_body ) {
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );

		// Fetch the details of the expired auth so that we can find the associated order.
		$charge_id = $this->read_rest_property( $event_object, 'id' );

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
	 * @throws Rest_Request_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve intent ID to order.
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

		$event_data    = $this->read_rest_property( $event_body, 'data' );
		$event_object  = $this->read_rest_property( $event_data, 'object' );
		$intent_id     = $this->read_rest_property( $event_object, 'id' );
		$intent_status = $this->read_rest_property( $event_object, 'status' );
		$charge_id     = $this->read_rest_property( $charges_data, 'id' );

		$this->order_service->mark_payment_failed( $order, $intent_id, $intent_status, $charge_id, $this->get_failure_message_from_event( $event_body ) );
	}

	/**
	 * Process webhook for a successful payment intent.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Rest_Request_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve intent ID to order.
	 */
	private function process_webhook_payment_intent_succeeded( $event_body ) {
		$event_data    = $this->read_rest_property( $event_body, 'data' );
		$event_object  = $this->read_rest_property( $event_data, 'object' );
		$intent_id     = $this->read_rest_property( $event_object, 'id' );
		$order         = $this->get_order_from_event_body_intent_id( $event_body );
		$intent_status = $this->read_rest_property( $event_object, 'status' );
		$event_charges = $this->read_rest_property( $event_object, 'charges' );
		$charges_data  = $this->read_rest_property( $event_charges, 'data' );
		$charge_id     = $this->read_rest_property( $charges_data[0], 'id' );

		$this->order_service->mark_payment_completed( $order, $intent_id, $intent_status, $charge_id );
	}

	/**
	 * Process webhook dispute created.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Rest_Request_Exception Required parameters not found.
	 */
	private function process_webhook_dispute_created( $event_body ) {
		$event_type   = $this->read_rest_property( $event_body, 'type' );
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );
		$dispute_id   = $this->read_rest_property( $event_object, 'id' );
		$charge_id    = $this->read_rest_property( $event_object, 'charge' );
		$reason       = $this->read_rest_property( $event_object, 'reason' );
		$order        = $this->wcpay_db->order_from_charge_id( $charge_id );

		if ( ! $order ) {
			throw new Rest_Request_Exception(
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
	 * @throws Rest_Request_Exception Required parameters not found.
	 */
	private function process_webhook_dispute_closed( $event_body ) {
		$event_type   = $this->read_rest_property( $event_body, 'type' );
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );
		$dispute_id   = $this->read_rest_property( $event_object, 'id' );
		$charge_id    = $this->read_rest_property( $event_object, 'charge' );
		$status       = $this->read_rest_property( $event_object, 'status' );
		$order        = $this->wcpay_db->order_from_charge_id( $charge_id );

		if ( ! $order ) {
			throw new Rest_Request_Exception(
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
	 * @throws Rest_Request_Exception Required parameters not found.
	 */
	private function process_webhook_dispute_updated( $event_body ) {
		$event_type   = $this->read_rest_property( $event_body, 'type' );
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );
		$dispute_id   = $this->read_rest_property( $event_object, 'id' );
		$charge_id    = $this->read_rest_property( $event_object, 'charge' );
		$order        = $this->wcpay_db->order_from_charge_id( $charge_id );

		if ( ! $order ) {
			throw new Rest_Request_Exception(
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
	 * Safely get a value from the REST request body array.
	 *
	 * @param array  $array Array to read from.
	 * @param string $key   ID to fetch on.
	 *
	 * @return string|array|int
	 * @throws Rest_Request_Exception Thrown if ID not set.
	 */
	private function read_rest_property( $array, $key ) {
		if ( ! isset( $array[ $key ] ) ) {
			throw new Rest_Request_Exception(
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
	 * @throws Rest_Request_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve intent ID to order.
	 *
	 * @return boolean|WC_Order|WC_Order_Refund
	 */
	private function get_order_from_event_body_intent_id( $event_body ) {
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );
		$intent_id    = $this->read_rest_property( $event_object, 'id' );

		// Look up the order related to this intent.
		$order = $this->wcpay_db->order_from_intent_id( $intent_id );

		if ( ! $order ) {
			// Retrieving order with order_id in case intent_id was not properly set.
			Logger::debug( 'intent_id not found, using order_id to retrieve order' );
			$metadata = $this->read_rest_property( $event_object, 'metadata' );

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
		$event_data    = $this->read_rest_property( $event_body, 'data' );
		$event_object  = $this->read_rest_property( $event_data, 'object' );
		$event_charges = $this->read_rest_property( $event_object, 'charges' );
		$charges_data  = $this->read_rest_property( $event_charges, 'data' );
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

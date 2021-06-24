<?php
/**
 * Class WC_REST_Payments_Webhook_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

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
	 * WC_REST_Payments_Webhook_Controller constructor.
	 *
	 * @param WC_Payments_API_Client          $api_client          WC_Payments_API_Client instance.
	 * @param WC_Payments_DB                  $wcpay_db            WC_Payments_DB instance.
	 * @param WC_Payments_Account             $account             WC_Payments_Account instance.
	 * @param WC_Payments_Remote_Note_Service $remote_note_service WC_Payments_Remote_Note_Service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_DB $wcpay_db,
		WC_Payments_Account $account,
		WC_Payments_Remote_Note_Service $remote_note_service
	) {
		parent::__construct( $api_client );
		$this->wcpay_db            = $wcpay_db;
		$this->account             = $account;
		$this->remote_note_service = $remote_note_service;
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
				case 'payment_intent.succeeded':
					$this->process_webhook_payment_intent_succeeded( $body );
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
			wc_price( WC_Payments_Utils::interpret_stripe_amount( $amount, $currency ), [ 'currency' => strtoupper( $currency ) ] ),
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

		// TODO: Revisit this logic once we support partial captures or multiple charges for order. We'll need to handle the "payment_intent.canceled" event too.
		WC_Payments_Utils::mark_payment_expired( $order );
	}

	/**
	 * Process webhook for a successul payment intent.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws Rest_Request_Exception           Required parameters not found.
	 * @throws Invalid_Payment_Method_Exception When unable to resolve charge ID to order.
	 */
	private function process_webhook_payment_intent_succeeded( $event_body ) {
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );

		$intent_id = $this->read_rest_property( $event_object, 'id' );

		// Look up the order related to this charge.
		$order = $this->wcpay_db->order_from_intent_id( $intent_id );
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

		if ( ! $order->has_status( [ 'processing', 'completed' ] ) ) {
			$order->payment_complete();
		}
	}

	/**
	 * Safely get a value from the REST request body array.
	 *
	 * @param array  $array Array to read from.
	 * @param string $key   ID to fetch on.
	 *
	 * @return string|array
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
}

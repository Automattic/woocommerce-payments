<?php
/**
 * Class WC_REST_Payments_Webhook_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\WC_Payments_Rest_Request_Exception;
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
	 * WC_REST_Payments_Webhook_Controller constructor.
	 *
	 * @param WC_Payments_API_Client $api_client WC_Payments_API_Client instance.
	 * @param WC_Payments_DB         $wcpay_db   WC_Payments_DB instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payments_DB $wcpay_db ) {
		parent::__construct( $api_client );
		$this->wcpay_db = $wcpay_db;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
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
			$event_type   = $this->read_rest_property( $body, 'type' );
			$event_data   = $this->read_rest_property( $body, 'data' );
			$event_object = $this->read_rest_property( $event_data, 'object' );

			switch ( $event_type ) {
				case 'charge.refund.updated':
					$this->process_webhook_refund_updated( $event_object );
					break;
			}
		} catch ( WC_Payments_Rest_Request_Exception $e ) {
			Logger::error( $e );
			return new WP_REST_Response( array( 'result' => self::RESULT_BAD_REQUEST ), 400 );
		} catch ( Exception $e ) {
			Logger::error( $e );
			return new WP_REST_Response( array( 'result' => self::RESULT_ERROR ), 500 );
		}

		return new WP_REST_Response( array( 'result' => self::RESULT_SUCCESS ) );
	}

	/**
	 * Process webhook refund updated.
	 *
	 * @param array $event_object The event that triggered the webhook.
	 *
	 * @throws WC_Payments_Rest_Request_Exception Required parameters not found.
	 * @throws Exception                  Unable to resolve charge ID to order.
	 */
	private function process_webhook_refund_updated( $event_object ) {
		// First, check the reason for the update. We're only interesting in a status of failed.
		$status = $this->read_rest_property( $event_object, 'status' );
		if ( 'failed' !== $status ) {
			return;
		}

		// Fetch the details of the failed refund so that we can find the associated order and write a note.
		$charge_id = $this->read_rest_property( $event_object, 'charge' );
		$refund_id = $this->read_rest_property( $event_object, 'id' );
		$amount    = $this->read_rest_property( $event_object, 'amount' );

		// Look up the order related to this charge.
		$order = $this->wcpay_db->order_from_charge_id( $charge_id );
		if ( ! $order ) {
			throw new Exception(
				sprintf(
					/* translators: %1: charge ID */
					__( 'Could not find order via charge ID: %1$s', 'woocommerce-payments' ),
					$charge_id
				)
			);
		}

		$note = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the refund amount, %2: ID of the refund */
				__( 'A refund of %1$s was <strong>unsuccessful</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
				array(
					'strong' => '<strong>',
					'code'   => '<code>',
				)
			),
			wc_price( $amount / 100 ),
			$refund_id
		);
		$order->add_order_note( $note );
	}

	/**
	 * Safely get a value from the REST request body array.
	 *
	 * @param array  $array Array to read from.
	 * @param string $key   ID to fetch on.
	 *
	 * @return string|array
	 * @throws WC_Payments_Rest_Request_Exception Thrown if ID not set.
	 */
	private function read_rest_property( $array, $key ) {
		if ( ! isset( $array[ $key ] ) ) {
			throw new WC_Payments_Rest_Request_Exception(
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

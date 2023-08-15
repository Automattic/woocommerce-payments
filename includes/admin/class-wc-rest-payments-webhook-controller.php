<?php
/**
 * Class WC_REST_Payments_Webhook_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\Invalid_Webhook_Data_Exception;
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
	 * Webhook Processing Service.
	 *
	 * @var WC_Payments_Webhook_Processing_Service
	 */
	private $webhook_processing_service;

	/**
	 * Webhook Reliability Service.
	 *
	 * @var WC_Payments_Webhook_Reliability_Service
	 */
	private $webhook_reliability_service;

	/**
	 * WC_REST_Payments_Webhook_Controller constructor.
	 *
	 * @param WC_Payments_API_Client                  $api_client                 WC_Payments_API_Client instance.
	 * @param WC_Payments_Webhook_Processing_Service  $webhook_processing_service WC_Payments_Webhook_Processing_Service instance.
	 * @param WC_Payments_Webhook_Reliability_Service $webhook_reliability_service WC_Payments_Webhook_Reliability_Service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_Webhook_Processing_Service $webhook_processing_service,
		WC_Payments_Webhook_Reliability_Service $webhook_reliability_service
	) {
		parent::__construct( $api_client );
		$this->webhook_processing_service  = $webhook_processing_service;
		$this->webhook_reliability_service = $webhook_reliability_service;
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
			if ( $this->webhook_reliability_service->should_delay_event( $body ) ) {
				$this->webhook_reliability_service->store_event( $body );
			} else {
				$this->webhook_processing_service->process( $body );
			}
		} catch ( Invalid_Webhook_Data_Exception $e ) {
			Logger::error( $e );
			return new WP_REST_Response( [ 'result' => self::RESULT_BAD_REQUEST ], 400 );
		} catch ( Exception $e ) {
			Logger::error( $e );
			return new WP_REST_Response( [ 'result' => self::RESULT_ERROR ], 500 );
		}

		return new WP_REST_Response( [ 'result' => self::RESULT_SUCCESS ] );
	}
}

<?php
/**
 * Class WC_REST_Payments_Tos_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\Rest_Request_Exception;
use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for Terms of Services routes.
 */
class WC_REST_Payments_Tos_Controller extends WC_Payments_REST_Controller {

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
	protected $rest_base = 'payments/tos';

	/**
	 * Instance of WC_Payment_Gateway_WCPay
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * WC_REST_Payments_Webhook_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client WC_Payments_API_Client instance.
	 * @param WC_Payment_Gateway_WCPay $gateway WC_Payment_Gateway_WCPay instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payment_Gateway_WCPay $gateway ) {
		parent::__construct( $api_client );
		$this->gateway = $gateway;
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
				'callback'            => [ $this, 'handle_tos' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reactivate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reactivate' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Record ToS acceptance.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 * @throws Rest_Request_Exception Throw if accept param is missing.
	 */
	public function handle_tos( $request ) {
		$body = $request->get_json_params();

		try {
			if ( ! isset( $body['accept'] ) ) {
				throw new Rest_Request_Exception( __( 'ToS accept parameter is missing', 'woocommerce-payments' ) );
			}

			$is_accepted = (bool) $body['accept'];

			Logger::debug( sprintf( 'ToS acceptance request received. Accept: %s', $is_accepted ? 'yes' : 'no' ) );

			if ( $is_accepted ) {
				$this->handle_tos_accepted();
			} else {
				$this->handle_tos_declined();
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
	 * Process ToS accepted.
	 */
	private function handle_tos_accepted() {
		$this->gateway->enable();

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$user_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';

		$this->api_client->add_tos_agreement( 'settings-popup', $user_ip, $user_agent );
	}

	/**
	 * Process ToS declined.
	 */
	private function handle_tos_declined() {
		// TODO: maybe record ToS declined data.
		$this->gateway->disable();
	}

	/**
	 * Activates the gateway again, after it's been disabled.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function reactivate( $request ) {
		try {
			$this->gateway->enable();
			Logger::debug( 'Gateway re-enabled after ToS decline.' );
		} catch ( Exception $e ) {
			Logger::error( $e );
			return new WP_REST_Response( [ 'result' => self::RESULT_ERROR ], 500 );
		}

		return new WP_REST_Response( [ 'result' => self::RESULT_SUCCESS ] );
	}
}

<?php
/**
 * Class WC_REST_Payments_Authorizations_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for authorizations.
 */
class WC_REST_Payments_Authorizations_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/authorizations';

	/**
	 * Customer service.
	 *
	 * @var WC_Payments_Customer_Service $cutomer_service
	 */
	protected $customer_service;

	public function __construct( WC_Payments_API_Client $api_client,  WC_Payments_Customer_Service $customer_service ) {
		parent::__construct( $api_client );
		$this->customer_service = $customer_service;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_authorizations' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_authorization' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/capture',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'capture_authorization' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_authorizations_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<payment_intent_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_authorization' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve authorizations to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_authorizations( WP_REST_Request $request ) {
		$page      = (int) $request->get_param( 'page' );
		$page_size = (int) $request->get_param( 'pagesize' );
		$sort      = $request->get_param( 'sort' );
		$direction = $request->get_param( 'direction' );
		return $this->forward_request( 'list_authorizations', [ $page, $page_size, $sort, $direction ] );
	}

	/**
	 * Create authorizations.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 * @throws \WCPay\Exceptions\API_Exception
	 */
	public function create_authorization( $request ) {
		$order_id = $request->get_param( 'order_id' );

		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, WC_Order::class ) ) {
			return rest_ensure_response( new WP_Error( 'wcpay_authorization_order_not_exist', 'Invalid order' ) );
		}
		$order_data = $order->get_meta('_authorization_id');
		if ( $order_data ) {
			return rest_ensure_response( new WP_Error( 'wcpay_authorization_exist', 'Order already have authorization' ) );
		}
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $order->get_user_id() );

		if ( ! $customer_id) {
			return rest_ensure_response( new WP_Error( 'wcpay_authorization_missing_customer', 'Missing customer from order' ) );
		}

		$payment_methods = $this->customer_service->get_payment_methods_for_customer( $customer_id );

		$payment_method = $payment_methods[0] ?? null;

		if ( ! $payment_method ) {
			return rest_ensure_response( new WP_Error( 'wcpay_authorization_missing_payment_method', 'Payment method missing.' ) );
		}

		$currency = strtolower( $order->get_currency() );
		$amount = WC_Payments_Utils::prepare_amount( $order->get_total(), $currency );
		$authorization =  $this->api_client->create_authorization( $amount, $order_id, $currency, $customer_id, $payment_method['id'], $order->get_order_number());
		$order->add_meta_data('_authorization_id', $authorization->get_id() );
		$order->save();

		return rest_ensure_response( $authorization );
	}

	/**
	 * Create authorizations.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 * @throws \WCPay\Exceptions\API_Exception
	 */
	public function capture_authorization( $request ) {
		$authorization_id = $request->get_param( 'id' );
		$order_id = $request->get_param( 'order_id' );

		if ( ! $order_id ) {
			if ( ! $authorization_id ) {
				return rest_ensure_response( new WP_Error( 'wcpay_authorization_invalid_parameter', 'Order id or authorization ID needs to be passed.' ) );
			}
			$authorization = $this->api_client->get_authorization( $authorization_id, false );
			$order_id = $authorization['metadata']['order_id'] ?? null;
			if ( null === $order_id ) {
				return rest_ensure_response( new WP_Error( 'wcpay_authorization_invalid_authorization', 'This authorization cannot be captured.' ) );
			}
		}

		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, WC_Order::class ) ) {
			return rest_ensure_response( new WP_Error( 'wcpay_authorization_order_not_exist', 'Invalid order' ) );
		}
		$order_authorization_id = $order->get_meta('_authorization_id');
		if ( ! $order_authorization_id ) {
			return rest_ensure_response( new WP_Error( 'wcpay_authorization_does_not_exist', 'Order does not have authorization' ) );
		}
		if ( $authorization_id && $order_authorization_id !== $authorization_id ) {
			return rest_ensure_response( new WP_Error( 'wcpay_authorization_invalid_id', 'Invalid authorization id' ) );
		}
		$currency = strtolower( $order->get_currency() );
		$amount = WC_Payments_Utils::prepare_amount( $order->get_total(), $currency );

		$capture =  $this->api_client->capture_authorization( $authorization_id, $amount );
		$order->add_meta_data('_capture_id', $capture->get_id() ); //in case if it's different from original authorization id.
		$order->update_status( 'completed' ); // Mark order as completed.

		return rest_ensure_response( $capture );
	}

	/**
	 * Retrieve authorization to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_authorization( WP_REST_Request $request ) {
		$payment_intent_id = $request->get_param( 'payment_intent_id' );
		return $this->forward_request( 'get_authorization', [ $payment_intent_id ] );
	}

	/**
	 * Retrieve authorizations summary to respond with via API.
	 */
	public function get_authorizations_summary() {
		return $this->forward_request( 'get_authorizations_summary', [] );
	}
}

<?php
/**
 * Class WC_REST_Payments_Orders_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for order processing.
 */
class WC_REST_Payments_Orders_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/orders';

	/**
	 * Instance of WC_Payment_Gateway_WCPay
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * WC_Payments_REST_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client - WooCommerce Payments API client.
	 * @param WC_Payment_Gateway_WCPay $gateway - WooCommerce Payments payment gateway.
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
			$this->rest_base . '/(?P<order_id>\w+)/capture',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'capture_order' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [ 'payment_intent_id' ],
			]
		);
	}

	/**
	 * Capture an order.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function capture_order( $request ) {
		$order_id = $request['order_id'];
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'wcpay_missing_order', 'Order not found', [ 'status' => 404 ] );
		}

		$result = $this->gateway->capture_charge( $order );

		return rest_ensure_response( $result );
	}
}

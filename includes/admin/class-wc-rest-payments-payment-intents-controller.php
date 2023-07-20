<?php
/**
 * Class WC_REST_Payments_Charges_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\API_Exception;
use WCPay\Core\Server\Request\Create_Intention;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for charges.
 */
class WC_REST_Payments_Payment_Intents_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/payment_intents';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<payment_intent_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payment_intent' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'post_payment_intent' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve charge to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_payment_intent( $request ) {
		$payment_intent_id = $request->get_param( 'payment_intent_id' );

		return $this->forward_request( 'get_intent', [ $payment_intent_id ] );
	}

	/**
	 * Create a payment intent.
	 *
	 * @param WP_REST_Request $request data about the request.
	 */
	public function post_payment_intent( $request ) {
		$metadata = $request->get_param( 'metadata' );
		$order_id = $metadata['order_number'];
		$order    = wc_get_order( $order_id );

		$wcpay_server_request = Create_Intention::create();
		$currency             = strtolower( $order->get_currency() );
		$amount               = WC_Payments_Utils::prepare_amount( $order->get_total(), $currency );
		$wcpay_server_request->set_currency_code( $currency );
		$wcpay_server_request->set_amount( $amount );
		$wcpay_server_request->set_metadata( $metadata );
		$wcpay_server_request->set_customer( $request->get_param( 'customer' ) );
		$wcpay_server_request->set_level3( $request->get_param( 'level3' ) );
		$wcpay_server_request->set_payment_method_types( $request->get_param( 'payment_method_types' ) );
		$wcpay_server_request->set_capture_method( WC_Payments::get_gateway()->get_option( 'manual_capture' ) && ( 'yes' === self::get_gateway()->get_option( 'manual_capture' ) ) );

		$intent = $wcpay_server_request->send( 'wcpay_create_intent_request', $order );
		return rest_ensure_response( $intent );
	}

}

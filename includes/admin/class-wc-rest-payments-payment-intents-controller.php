<?php
/**
 * Class WC_REST_Payments_Payment_Intents_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for charges.
 */
class WC_REST_Payments_Payment_Intents_Controller extends WC_Payments_REST_Controller {

	/**
	 * Instance of WC_Payment_Gateway_WCPay
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

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
	 * WC_REST_Payments_Payment_Intents_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client       WooCommerce Payments API client.
	 * @param WC_Payment_Gateway_WCPay $gateway          WooCommerce Payments payment gateway.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payment_Gateway_WCPay $gateway ) {
		parent::__construct( $api_client );
		$this->gateway = $gateway;
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
		try {

			$order_id = $request->get_param( 'order_id' );
			$order    = wc_get_order( $order_id );
			$currency = strtolower( $order->get_currency() );
			$amount   = WC_Payments_Utils::prepare_amount( $order->get_total(), $currency );
			$name     = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
			$email    = sanitize_email( $order->get_billing_email() );
			$metadata = [
				'customer_name'  => $name,
				'customer_email' => $email,
				'site_url'       => esc_url( get_site_url() ),
				'order_id'       => $order->get_id(),
				'order_number'   => $order->get_order_number(),
				'order_key'      => $order->get_order_key(),
				'payment_type'   => 'single',
			];

			$wcpay_server_request = Create_And_Confirm_Intention::create();
			$wcpay_server_request->set_currency_code( $currency );
			$wcpay_server_request->set_amount( $amount );
			$wcpay_server_request->set_metadata( $metadata );
			$wcpay_server_request->set_customer( $request->get_param( 'customer' ) );
			$wcpay_server_request->set_level3( $this->gateway->get_level3_data_from_order( $order ) );
			$wcpay_server_request->set_payment_method( $request->get_param( 'payment_method' ) );
			$wcpay_server_request->set_payment_method_types( [ 'card' ] );
			$wcpay_server_request->set_capture_method( WC_Payments::get_gateway()->get_option( 'manual_capture' ) && ( 'yes' === WC_Payments::get_gateway()->get_option( 'manual_capture' ) ) );

			$intent = $wcpay_server_request->send( 'wcpay_create_intent_request', $order );
			return rest_ensure_response( $intent );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to create an intention via REST API: ' . $e );
			return new WP_Error( 'wcpay_server_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

}

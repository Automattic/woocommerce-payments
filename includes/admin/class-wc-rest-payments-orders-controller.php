<?php
/**
 * Class WC_REST_Payments_Orders_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Logger;

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
			$this->rest_base . '/(?P<order_id>\w+)/capture_terminal_payment',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'capture_terminal_payment' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'payment_intent_id' => [
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Given an intent ID and an order ID, add the intent ID to the order and capture it.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function capture_terminal_payment( $request ) {
		try {
			$intent_id = $request['payment_intent_id'];
			$order_id  = $request['order_id'];
			$order     = wc_get_order( $order_id );
			if ( ! $order ) {
				return new WP_Error( 'wcpay_missing_order', __( 'Order not found', 'woocommerce-payments' ), [ 'status' => 404 ] );
			}

			$intent = $this->api_client->get_intent( $intent_id );

			// Do not process intents that can't be captured.
			if ( ! in_array( $intent->get_status(), [ 'processing', 'requires_capture' ], true ) ) {
				return new WP_Error( 'wcpay_payment_uncapturable', __( 'The payment cannot be captured', 'woocommerce-payments' ), [ 'status' => 409 ] );
			}

			// Set the payment method on the order.
			$order->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );

			// Mark the order as paid for with WCPay and the intent.
			$this->gateway->attach_intent_info_to_order(
				$order,
				$intent->get_id(),
				$intent->get_status(),
				$intent->get_charge_id(),
				$intent->get_customer_id(),
				$intent->get_payment_method_id(),
				$intent->get_currency()
			);

			// Capture the intent.
			$result = $this->gateway->capture_charge( $order );

			if ( 'succeeded' === $result['status'] ) {
				$order->update_status( 'completed' );
			} else {
				return new WP_Error(
					'wcpay_capture_error',
					sprintf(
						// translators: %s: the error message.
						__( 'Payment capture failed to complete with the following message: %s', 'woocommerce-payments' ),
						$result['message'] ?? __( 'Unknown error', 'woocommerce-payments' )
					),
					[ 'status' => 502 ]
				);
			}

			return rest_ensure_response(
				[
					'status' => $result['status'],
					'id'     => $result['id'],
				]
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to capture a terminal payment via REST API: ' . $e );
			return new WP_Error( 'wcpay_server_error', __( 'Unexpected server error', 'woocommerce-payments' ), [ 'status' => 500 ] );
		}
	}
}

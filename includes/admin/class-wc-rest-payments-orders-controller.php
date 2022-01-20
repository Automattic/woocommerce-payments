<?php
/**
 * Class WC_REST_Payments_Orders_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Constants\Payment_Method;
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
	 * WC_Payments_Customer_Service instance for working with customer information
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * WC_Payments_REST_Controller constructor.
	 *
	 * @param WC_Payments_API_Client       $api_client       WooCommerce Payments API client.
	 * @param WC_Payment_Gateway_WCPay     $gateway          WooCommerce Payments payment gateway.
	 * @param WC_Payments_Customer_Service $customer_service Customer class instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payment_Gateway_WCPay $gateway, WC_Payments_Customer_Service $customer_service ) {
		parent::__construct( $api_client );
		$this->gateway          = $gateway;
		$this->customer_service = $customer_service;
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
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<order_id>\w+)/create_terminal_intent',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_terminal_intent' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<order_id>\d+)/create_customer',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_customer' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Given an intent ID and an order ID, add the intent ID to the order and capture it.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function capture_terminal_payment( $request ) {
		try {
			$intent_id = $request['payment_intent_id'];
			$order_id  = $request['order_id'];

			// Do not process non-existing orders.
			$order = wc_get_order( $order_id );
			if ( false === $order ) {
				return new WP_Error( 'wcpay_missing_order', __( 'Order not found', 'woocommerce-payments' ), [ 'status' => 404 ] );
			}

			// Do not process orders with refund(s).
			if ( 0 < $order->get_total_refunded() ) {
				return new WP_Error(
					'wcpay_refunded_order_uncapturable',
					__( 'Payment cannot be captured for partially or fully refunded orders.', 'woocommerce-payments' ),
					[ 'status' => 400 ]
				);
			}

			// Do not process intents that can't be captured.
			$intent = $this->api_client->get_intent( $intent_id );
			if ( ! in_array( $intent->get_status(), [ 'processing', 'requires_capture' ], true ) ) {
				return new WP_Error( 'wcpay_payment_uncapturable', __( 'The payment cannot be captured', 'woocommerce-payments' ), [ 'status' => 409 ] );
			}

			// Update the order: set the payment method and attach intent attributes.
			$order->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
			$order->set_payment_method_title( __( 'WooCommerce In-Person Payments', 'woocommerce-payments' ) );
			$this->gateway->attach_intent_info_to_order(
				$order,
				$intent->get_id(),
				$intent->get_status(),
				$intent->get_payment_method_id(),
				$intent->get_customer_id(),
				$intent->get_charge_id(),
				$intent->get_currency()
			);
			$this->gateway->update_order_status_from_intent(
				$order,
				$intent->get_id(),
				$intent->get_status(),
				$intent->get_charge_id(),
				$intent->get_currency()
			);

			// Capture the intent and update the order attributes.
			$result = $this->gateway->capture_charge( $order );
			if ( 'succeeded' !== $result['status'] ) {
				$http_code = $result['http_code'] ?? 502;
				return new WP_Error(
					'wcpay_capture_error',
					sprintf(
						// translators: %s: the error message.
						__( 'Payment capture failed to complete with the following message: %s', 'woocommerce-payments' ),
						$result['message'] ?? __( 'Unknown error', 'woocommerce-payments' )
					),
					[ 'status' => $http_code ]
				);
			}
			// Store receipt generation URL for mobile applications in order meta-data.
			$order->add_meta_data( 'receipt_url', get_rest_url( null, sprintf( '%s/payments/readers/receipts/%s', $this->namespace, $intent->get_id() ) ) );
			// Actualize order status.
			$order->update_status( 'completed' );

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

	/**
	 * Returns customer id from order. Create or update customer if needed.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_customer( $request ) {
		try {
			$order_id = $request['order_id'];

			// Do not process non-existing orders.
			$order = wc_get_order( $order_id );
			if ( false === $order || ! ( $order instanceof WC_Order ) ) {
				return new WP_Error( 'wcpay_missing_order', __( 'Order not found', 'woocommerce-payments' ), [ 'status' => 404 ] );
			}

			$disallowed_order_statuses = apply_filters( 'wcpay_create_customer_disallowed_order_statuses', [ 'completed', 'cancelled', 'refunded', 'failed' ] );
			if ( $order->has_status( $disallowed_order_statuses ) ) {
				return new WP_Error( 'wcpay_invalid_order_status', __( 'Invalid order status', 'woocommerce-payments' ), [ 'status' => 400 ] );
			}

			$order_user        = $order->get_user();
			$customer_id       = $order->get_meta( '_stripe_customer_id' );
			$customer_data     = WC_Payments_Customer_Service::map_customer_data( $order );
			$is_guest_customer = false === $order_user;

			// If the order is created for a registered customer, try extracting it's Stripe customer ID.
			if ( ! $customer_id && ! $is_guest_customer ) {
				$customer_id = $this->customer_service->get_customer_id_by_user_id( $order_user->ID );
			}

			$order_user  = $is_guest_customer ? new WP_User() : $order_user;
			$customer_id = $customer_id
				? $this->customer_service->update_customer_for_user( $customer_id, $order_user, $customer_data )
				: $this->customer_service->create_customer_for_user( $order_user, $customer_data );

			$order->update_meta_data( '_stripe_customer_id', $customer_id );
			$order->save();

			return rest_ensure_response(
				[
					'id' => $customer_id,
				]
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to create / update customer from order via REST API: ' . $e );
			return new WP_Error( 'wcpay_server_error', __( 'Unexpected server error', 'woocommerce-payments' ), [ 'status' => 500 ] );
		}
	}

	/**
	 * Create a new in-person payment intent for the given order ID without confirming it.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_terminal_intent( $request ) {
		// Do not process non-existing orders.
		$order = wc_get_order( $request['order_id'] );
		if ( false === $order ) {
			return new WP_Error( 'wcpay_missing_order', __( 'Order not found', 'woocommerce-payments' ), [ 'status' => 404 ] );
		}

		try {
			$result = $this->gateway->create_intent( $order, [ Payment_Method::CARD_PRESENT ], 'manual' );
			return rest_ensure_response( $result );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to create an intention via REST API: ' . $e );
			return new WP_Error( 'wcpay_server_error', __( 'Unexpected server error', 'woocommerce-payments' ), [ 'status' => 500 ] );
		}
	}
}

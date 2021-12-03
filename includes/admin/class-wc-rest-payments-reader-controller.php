<?php
/**
 * Class WC_REST_Payments_Reader_Charges
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for reader charges.
 */
class WC_REST_Payments_Reader_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/readers';

	/**
	 * Instance of WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * Instance of WC_Payments_In_Person_Payments_Receipts_Service.
	 *
	 * @var WC_Payments_In_Person_Payments_Receipts_Service
	 */
	private $receipts_service;

	/**
	 * WC_REST_Payments_Reader_Controller
	 *
	 * @param  WC_Payments_API_Client                          $api_client WC_Payments_API_Client.
	 * @param  WC_Payment_Gateway_WCPay                        $wcpay_gateway WC_Payment_Gateway_WCPay.
	 * @param  WC_Payments_In_Person_Payments_Receipts_Service $receipts_service WC_Payments_In_Person_Payments_Receipts_Service.
	 * @return void
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payment_Gateway_WCPay $wcpay_gateway, WC_Payments_In_Person_Payments_Receipts_Service $receipts_service ) {
		parent::__construct( $api_client );

		$this->wcpay_gateway    = $wcpay_gateway;
		$this->receipts_service = $receipts_service;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/charges/(?P<transaction_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/receipts/(?P<payment_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'generate_print_receipt' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve payment readers charges to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function get_summary( $request ) {

		$transaction_id = $request->get_param( 'transaction_id' );

		try {
			// retrieve transaction details to get the charge date.
			$transaction = $this->api_client->get_transaction( $transaction_id );

			if ( empty( $transaction ) ) {
				return rest_ensure_response( [] );
			}
			$summary = $this->api_client->get_readers_charge_summary( gmdate( 'Y-m-d', $transaction['created'] ) );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'wcpay_get_summary', $e->getMessage() ) );
		}

		return rest_ensure_response( $summary );
	}

	/**
	 * Renders HTML for a print receipt
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_HTTP_RESPONSE|WP_Error
	 * @throws \RuntimeException Error collecting data.
	 */
	public function generate_print_receipt( $request ) {
		try {
			/* Collect the data, available on the server side. */
			$payment_intent = $this->api_client->get_intent( $request->get_param( 'payment_id' ) );
			if ( 'succeeded' !== $payment_intent->get_status() ) {
				throw new \RuntimeException( __( 'Invalid payment intent', 'woocommerce-payments' ) );
			}
			$charge = $this->api_client->get_charge( $payment_intent->get_charge_id() );

			/* Collect receipt data, stored on the store side. */
			$order = wc_get_order( $charge['order']['number'] );
			if ( false === $order ) {
				throw new \RuntimeException( __( 'Order not found', 'woocommerce-payments' ) );
			}

			/* Collect merchant settings */
			$settings = [
				'business_name' => $this->wcpay_gateway->get_option( 'account_business_name' ),
				'support_info'  => [
					'address' => $this->wcpay_gateway->get_option( 'account_business_support_address' ),
					'phone'   => $this->wcpay_gateway->get_option( 'account_business_support_phone' ),
					'email'   => $this->wcpay_gateway->get_option( 'account_business_support_email' ),
				],
			];

			/* Generate receipt */
			$receipt_data = $this->receipts_service->get_receipt_markup( $settings, $order, $charge );
		} catch ( \Throwable $e ) {
			$error_status_code = $e instanceof API_Exception ? $e->get_http_code() : 500;
			return rest_ensure_response( new WP_Error( 'generate_print_receipt_error', $e->getMessage(), [ 'status' => $error_status_code ] ) );
		}

		/**
		 * WP_REST_Server will convert the response data to JSON prior to output it.
		 * Using this filter to prevent it, and output the data from WP_HTTP_Response instead.
		 */
		add_filter(
			'rest_pre_serve_request',
			function ( bool $served, WP_HTTP_Response $response ) : bool {
				echo $response->get_data(); // @codingStandardsIgnoreLine
				return true;
			},
			10,
			2
		);

		return new WP_HTTP_Response(
			$receipt_data,
			200,
			[ 'Content-Type' => 'text/html; charset=UTF-8' ]
		);
	}
}

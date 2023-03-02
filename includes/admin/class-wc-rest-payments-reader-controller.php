<?php
/**
 * Class WC_REST_Payments_Reader_Charges
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Get_Charge;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Constants\Payment_Intent_Status;
use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

require_once WCPAY_ABSPATH . 'includes/in-person-payments/class-wc-payments-printed-receipt-sample-order.php';

/**
 * REST controller for reader charges.
 */
class WC_REST_Payments_Reader_Controller extends WC_Payments_REST_Controller {
	const STORE_READERS_TRANSIENT_KEY = 'wcpay_store_terminal_readers';

	const PREVIEW_RECEIPT_CHARGE_DATA = [
		'amount_captured'        => 0,
		'payment_method_details' => [
			'card_present' => [
				'brand'   => 'Sample',
				'last4'   => '0000',
				'receipt' => [
					'application_preferred_name' => 'Sample, Receipts preview',
					'dedicated_file_name'        => '0000',
					'account_type'               => 'Sample',
				],
			],
		],
	];

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
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_readers' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'register_reader' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'location'          => [
						'type'     => 'string',
						'required' => true,
					],
					'registration_code' => [
						'type'     => 'string',
						'required' => true,
					],
					'label'             => [
						'type' => 'string',
					],
					'metadata'          => [
						'type' => 'object',
					],
				],
			]
		);

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
			'/' . $this->rest_base . '/receipts/preview',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'preview_print_receipt' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/receipts/(?P<payment_intent_id>\w+)',
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
	 * Proxies the get all readers request to the server.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_all_readers( $request ) {
		try {
			return rest_ensure_response( $this->fetch_readers() );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Links a card reader to an account and terminal location.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register_reader( $request ) {
		try {
			$response = $this->api_client->register_terminal_reader(
				$request->get_param( 'location' ),
				$request->get_param( 'registration_code' ),
				$request->get_param( 'label' ),
				$request->get_param( 'metadata' )
			);

			$reader = wp_array_slice_assoc( $response, [ 'id', 'livemode', 'device_type', 'label', 'location', 'metadata', 'status' ] );

			return rest_ensure_response( $reader );
		} catch ( API_Exception $e ) {
			return rest_ensure_response(
				new WP_Error(
					$e->get_error_code(),
					$e->getMessage(),
					[ 'status' => $e->get_http_code() ]
				)
			);
		}
	}

	/**
	 * Check if the reader status is active
	 *
	 * @param array  $readers The readers charges object.
	 * @param string $id      The reader ID.
	 * @return bool
	 */
	private function is_reader_active( $readers, $id ) {
		foreach ( $readers as $reader ) {
			if ( $reader['reader_id'] === $id && 'active' === $reader['status'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Attempts to read readers from transient and re-populates it if needed.
	 *
	 * @return array         Terminal readers.
	 * @throws API_Exception If request to server fails.
	 */
	private function fetch_readers(): array {
		$readers = get_transient( static::STORE_READERS_TRANSIENT_KEY );

		if ( ! $readers ) {
			// Retrieve terminal readers.
			$readers_data = $this->api_client->get_terminal_readers();

			// Retrieve the readers by charges.
			$reader_by_charges = $this->api_client->get_readers_charge_summary( gmdate( 'Y-m-d', time() ) );

			$readers = [];
			foreach ( $readers_data as $reader ) {
				$readers[] = [
					'id'          => $reader['id'],
					'livemode'    => $reader['livemode'],
					'device_type' => $reader['device_type'],
					'label'       => $reader['label'],
					'location'    => $reader['location'],
					'metadata'    => $reader['metadata'],
					'status'      => $reader['status'],
					'is_active'   => $this->is_reader_active( $reader_by_charges, $reader['id'] ),
				];
			}

			set_transient( static::STORE_READERS_TRANSIENT_KEY, $readers, 2 * HOUR_IN_SECONDS );
		}

		return $readers;
	}

	/**
	 * Renders HTML for a print receipt
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_HTTP_Response|WP_Error
	 * @throws \RuntimeException Error collecting data.
	 */
	public function generate_print_receipt( $request ) {
		try {
			/* Collect the data, available on the server side. */
			$wcpay_request  = Get_Intention::create( $request->get_param( 'payment_intent_id' ) );
			$payment_intent = $wcpay_request->send( 'wcpay_get_intent_request' );
			if ( Payment_Intent_Status::SUCCEEDED !== $payment_intent->get_status() ) {
				throw new \RuntimeException( __( 'Invalid payment intent', 'woocommerce-payments' ) );
			}

			$charge         = $payment_intent->get_charge();
			$charge_id      = $charge ? $charge->get_id() : null;
			$charge_request = Get_Charge::create( $charge_id );
			$charge_array   = $charge_request->send( 'wcpay_get_charge_request' );

			/* Collect receipt data, stored on the store side. */
			$order = wc_get_order( $charge_array['order']['number'] );
			if ( false === $order ) {
				throw new \RuntimeException( __( 'Order not found', 'woocommerce-payments' ) );
			}

			// Retrieve branding logo file ID.
			$branding_logo = $this->wcpay_gateway->get_option( 'account_branding_logo', '' );

			/* Collect merchant settings */
			$settings = [
				'branding_logo' => ( ! empty( $branding_logo ) ) ? $this->api_client->get_file_contents( $branding_logo, false ) : [],
				'business_name' => $this->wcpay_gateway->get_option( 'account_business_name' ),
				'support_info'  => [
					'address' => $this->wcpay_gateway->get_option( 'account_business_support_address' ),
					'phone'   => $this->wcpay_gateway->get_option( 'account_business_support_phone' ),
					'email'   => $this->wcpay_gateway->get_option( 'account_business_support_email' ),
				],
			];

			/* Generate receipt */
			$response = [ 'html_content' => $this->receipts_service->get_receipt_markup( $settings, $order, $charge_array ) ];
		} catch ( \Throwable $e ) {
			$error_status_code = $e instanceof API_Exception ? $e->get_http_code() : 500;
			$response          = new WP_Error( 'generate_print_receipt_error', $e->getMessage(), [ 'status' => $error_status_code ] );
		}

		return rest_ensure_response( $response );
	}
	/**
	 * Returns HTML to preview a print receipt
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_HTTP_Response|WP_Error
	 * @throws \RuntimeException Error collecting data.
	 */
	public function preview_print_receipt( WP_REST_Request $request ) {
		$preview = $this->receipts_service->get_receipt_markup(
			$this->create_print_preview_receipt_settings_data( $request->get_json_params() ),
			new WC_Payments_Printed_Receipt_Sample_Order(),
			self::PREVIEW_RECEIPT_CHARGE_DATA
		);

		return rest_ensure_response( [ 'html_content' => $preview ] );
	}

	/**
	 * Creates settings data to be used on the printed receipt preview. Defaults to stored settings if one parameter is not provided.
	 *
	 * @param  array $receipt_settings Array of settings to use to create the receipt preview.
	 * @return array
	 */
	private function create_print_preview_receipt_settings_data( array $receipt_settings ): array {
		$support_address = empty( $receipt_settings['accountBusinessSupportAddress'] ) ? $this->wcpay_gateway->get_option( 'account_business_support_address' ) : $receipt_settings['accountBusinessSupportAddress'];
		return [
			'business_name' => empty( $receipt_settings['accountBusinessName'] ) ? $this->wcpay_gateway->get_option( 'account_business_name' ) : $receipt_settings['accountBusinessName'],
			'support_info'  => [
				'address' => [
					'line1'       => $support_address['line1'],
					'line2'       => $support_address['line2'],
					'city'        => $support_address['city'],
					'state'       => $support_address['state'],
					'postal_code' => $support_address['postal_code'],
					'country'     => $support_address['country'],
				],
				'phone'   => empty( $receipt_settings['accountBusinessSupportPhone'] ) ? $this->wcpay_gateway->get_option( 'account_business_support_phone' ) : $receipt_settings['accountBusinessSupportPhone'],
				'email'   => empty( $receipt_settings['accountBusinessSupportEmail'] ) ? $this->wcpay_gateway->get_option( 'account_business_support_email' ) : $receipt_settings['accountBusinessSupportEmail'],
			],
		];
	}
}

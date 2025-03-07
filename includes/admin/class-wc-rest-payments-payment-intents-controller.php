<?php
/**
 * Class WC_REST_Payments_Payment_Intents_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Logger;
use WCPay\Exceptions\Rest_Request_Exception;
use WCPay\Constants\Payment_Type;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;

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
	 * Order service instance.
	 *
	 * @var OrderService
	 */
	private $order_service;

	/**
	 * Level3 service instance.
	 *
	 * @var Level3Service
	 */
	private $level3_service;

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
				'callback'            => [ $this, 'create_payment_intent' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'schema'              => [ $this, 'get_item_schema' ],
			]
		);
	}

	/**
	 * WC_REST_Payments_Payment_Intents_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client       WooCommerce Payments API client.
	 * @param WC_Payment_Gateway_WCPay $gateway          WooCommerce Payments payment gateway.
	 * @param OrderService             $order_service    The new order service.
	 * @param Level3Service            $level3_service   Level3 service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payment_Gateway_WCPay $gateway,
		OrderService $order_service,
		Level3Service $level3_service
	) {
		parent::__construct( $api_client );

		$this->gateway        = $gateway;
		$this->order_service  = $order_service;
		$this->level3_service = $level3_service;
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
	 *
	 * @throws Rest_Request_Exception
	 */
	public function create_payment_intent( $request ) {
		try {

			$order_id = $request->get_param( 'order_id' );
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new Rest_Request_Exception( __( 'Order not found', 'woocommerce-payments' ) );
			}

			$wcpay_server_request = Create_And_Confirm_Intention::create();

			$currency = strtolower( $order->get_currency() );
			$amount   = WC_Payments_Utils::prepare_amount( $order->get_total(), $currency );
			$wcpay_server_request->set_currency_code( $currency );
			$wcpay_server_request->set_amount( $amount );

			$metadata = $this->order_service->get_payment_metadata( $order_id, Payment_Type::SINGLE() );
			$wcpay_server_request->set_metadata( $metadata );

			$wcpay_server_request->set_customer( $request->get_param( 'customer' ) );
			$wcpay_server_request->set_level3( $this->level3_service->get_data_from_order( $order_id ) );
			$wcpay_server_request->set_payment_method( $request->get_param( 'payment_method' ) );
			$wcpay_server_request->set_payment_method_types( [ 'card' ] );
			$wcpay_server_request->set_off_session( true );
			$wcpay_server_request->set_capture_method( $this->gateway->get_option( 'manual_capture' ) && ( 'yes' === $this->gateway->get_option( 'manual_capture' ) ) );

			$wcpay_server_request->assign_hook( 'wcpay_create_and_confirm_intent_request_api' );
			$intent = $wcpay_server_request->send();

			$response = $this->prepare_item_for_response( $intent, $request );
			return rest_ensure_response( $this->prepare_response_for_collection( $response ) );

		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to create an intention via REST API: ' . $e );
			return new WP_Error( 'wcpay_server_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}


	/**
	 * Item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'payment_intent',
			'type'       => 'object',
			'properties' => [
				'id'       => [
					'description' => __( 'ID for the payment intent.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'amount'   => [
					'description' => __( 'The amount of the transaction.', 'woocommerce-payments' ),
					'type'        => 'integer',
					'context'     => [ 'view' ],
				],
				'currency' => [
					'description' => __( 'The currency of the transaction.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'created'  => [
					'description' => __( 'The date when the payment intent was created.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'customer' => [
					'description' => __( 'The customer id of the intent', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'status'   => [
					'description' => __( 'The status of the payment intent.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'charge'   => [
					'description' => __( 'Charge object associated with this payment intention.', 'woocommerce-payments' ),
					'type'        => 'object',
					'context'     => [ 'view' ],
					'properties'  => [
						'id'                     => [
							'description' => 'ID for the charge.',
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
						'amount'                 => [
							'description' => 'The amount of the charge.',
							'type'        => 'integer',
							'context'     => [ 'view' ],
						],
						'payment_method_details' => [
							'description' => 'Details for the payment method used for the charge.',
							'type'        => 'object',
							'properties'  => [
								'card' => [
									'description' => 'Details for a card payment method.',
									'type'        => 'object',
									'properties'  => [
										'amount_authorized' => [
											'description' => 'The amount authorized by the card.',
											'type'        => 'integer',
										],
										'brand'          => [
											'description' => 'The brand of the card.',
											'type'        => 'string',
										],
										'capture_before' => [
											'description' => 'Timestamp for when the authorization must be captured.',
											'type'        => 'string',
										],
										'country'        => [
											'description' => 'The ISO country code.',
											'type'        => 'string',
										],
										'exp_month'      => [
											'description' => 'The expiration month of the card.',
											'type'        => 'integer',
										],
										'exp_year'       => [
											'description' => 'The expiration year of the card.',
											'type'        => 'integer',
										],
										'last4'          => [
											'description' => 'The last 4 digits of the card.',
											'type'        => 'string',
										],
										'three_d_secure' => [
											'description' => 'Details for 3D Secure authentication.',
											'type'        => 'object',
										],
									],
								],
							],
						],
						'billing_details'        => [
							'description' => __( 'Billing details for the payment method.', 'woocommerce-payments' ),
							'type'        => 'object',
							'context'     => [ 'view' ],
							'properties'  => [
								'address' => [
									'description' => __( 'Address associated with the billing details.', 'woocommerce-payments' ),
									'type'        => 'object',
									'context'     => [ 'view' ],
									'properties'  => [
										'city'        => [
											'description' => __( 'City of the billing address.', 'woocommerce-payments' ),
											'type'        => 'string',
											'context'     => [ 'view' ],
										],
										'country'     => [
											'description' => __( 'Country of the billing address.', 'woocommerce-payments' ),
											'type'        => 'string',
											'context'     => [ 'view' ],
										],
										'line1'       => [
											'description' => __( 'Line 1 of the billing address.', 'woocommerce-payments' ),
											'type'        => 'string',
											'context'     => [ 'view' ],
										],
										'line2'       => [
											'description' => __( 'Line 2 of the billing address.', 'woocommerce-payments' ),
											'type'        => 'string',
											'context'     => [ 'view' ],
										],
										'postal_code' => [
											'description' => __( 'Postal code of the billing address.', 'woocommerce-payments' ),
											'type'        => 'string',
											'context'     => [ 'view' ],
										],
										'state'       => [
											'description' => __( 'State of the billing address.', 'woocommerce-payments' ),
											'type'        => 'string',
											'context'     => [ 'view' ],
										],
									],
								],
								'email'   => [
									'description' => __( 'Email associated with the billing details.', 'woocommerce-payments' ),
									'type'        => 'string',
									'format'      => 'email',
									'context'     => [ 'view' ],
								],
								'name'    => [
									'description' => __( 'Name associated with the billing details.', 'woocommerce-payments' ),
									'type'        => 'string',
									'context'     => [ 'view' ],
								],
								'phone'   => [
									'description' => __( 'Phone number associated with the billing details.', 'woocommerce-payments' ),
									'type'        => 'string',
									'context'     => [ 'view' ],
								],
							],
						],
						'payment_method'         => [
							'description' => 'The payment method associated with this charge.',
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
						'application_fee_amount' => [
							'description' => 'The application fee amount.',
							'type'        => 'integer',
							'context'     => [ 'view' ],
						],
						'status'                 => [
							'description' => 'The status of the payment intent created.',
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
					],
				],

			],
		];
	}

	/**
	 * Prepare each item for response.
	 *
	 * @param array|mixed     $item Item to prepare.
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function prepare_item_for_response( $item, $request ) {
		$prepared_item                   = [];
		$prepared_item['id']             = $item->get_id();
		$prepared_item['amount']         = $item->get_amount();
		$prepared_item['currency']       = $item->get_currency();
		$prepared_item['created']        = $item->get_created()->format( 'Y-m-d H:i:s' );
		$prepared_item['customer']       = $item->get_customer_id();
		$prepared_item['payment_method'] = $item->get_payment_method_id();
		$prepared_item['status']         = $item->get_status();

		try {
			$charge                            = $item->get_charge();
			$prepared_item['charge']['id']     = $charge->get_id();
			$prepared_item['charge']['amount'] = $charge->get_amount();
			$prepared_item['charge']['application_fee_amount'] = $charge->get_application_fee_amount();
			$prepared_item['charge']['status']                 = $charge->get_status();

			$billing_details = $charge->get_billing_details();
			if ( isset( $billing_details['address'] ) ) {
				$prepared_item['charge']['billing_details']['address']['city']        = $billing_details['address']['city'] ?? '';
				$prepared_item['charge']['billing_details']['address']['country']     = $billing_details['address']['country'] ?? '';
				$prepared_item['charge']['billing_details']['address']['line1']       = $billing_details['address']['line1'] ?? '';
				$prepared_item['charge']['billing_details']['address']['line2']       = $billing_details['address']['line2'] ?? '';
				$prepared_item['charge']['billing_details']['address']['postal_code'] = $billing_details['address']['postal_code'] ?? '';
				$prepared_item['charge']['billing_details']['address']['state']       = $billing_details['address']['state'] ?? '';
			}
			$prepared_item['charge']['billing_details']['email'] = $billing_details['email'] ?? '';
			$prepared_item['charge']['billing_details']['name']  = $billing_details['name'] ?? '';
			$prepared_item['charge']['billing_details']['phone'] = $billing_details['phone'] ?? '';

			$payment_method_details = $charge->get_payment_method_details();
			if ( isset( $payment_method_details['card'] ) ) {
				$prepared_item['charge']['payment_method_details']['card']['amount_authorized'] = $payment_method_details['card']['amount_authorized'] ?? '';
				$prepared_item['charge']['payment_method_details']['card']['brand']             = $payment_method_details['card']['brand'] ?? '';
				$prepared_item['charge']['payment_method_details']['card']['capture_before']    = $payment_method_details['card']['capture_before'] ?? '';
				$prepared_item['charge']['payment_method_details']['card']['country']           = $payment_method_details['card']['country'] ?? '';
				$prepared_item['charge']['payment_method_details']['card']['exp_month']         = $payment_method_details['card']['exp_month'] ?? '';
				$prepared_item['charge']['payment_method_details']['card']['exp_year']          = $payment_method_details['card']['exp_year'] ?? '';
				$prepared_item['charge']['payment_method_details']['card']['last4']             = $payment_method_details['card']['last4'] ?? '';
				$prepared_item['charge']['payment_method_details']['card']['three_d_secure']    = $payment_method_details['card']['three_d_secure'] ?? '';
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to prepare payment intent for response: ' . $e );
		}

		$context       = $request['context'] ?? 'view';
		$prepared_item = $this->add_additional_fields_to_object( $prepared_item, $request );
		$prepared_item = $this->filter_response_by_context( $prepared_item, $context );

		return rest_ensure_response( $prepared_item );
	}
}

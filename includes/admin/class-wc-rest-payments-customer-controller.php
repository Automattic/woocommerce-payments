<?php
/**
 * Class WC_REST_Payments_Customer_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request;
use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for customers.
 */
class WC_REST_Payments_Customer_Controller extends WC_Payments_REST_Controller {

	/**
	 * Onboarding Service.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/customers';

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client       $api_client    WooCommerce Payments API client.
	 * @param WC_Payments_Customer_Service $customer_service Token service.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_Customer_Service $customer_service
	) {
		parent::__construct( $api_client );
		$this->customer_service = $customer_service;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>\w+)/payment_methods',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_customer_payment_methods' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);
	}

	/**
	 * Retrieve transaction to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_customer_payment_methods( $request ) {
		$customer_id           = $request->get_param( 'customer_id' );
		$payment_methods_types = WC_Payments::get_gateway()->get_upe_enabled_payment_method_ids() ?? [];
		$payment_methods       = [];

		// Perhaps we can fetch it directly from server and avoid looping to get payment methods from cache.
		foreach ( $payment_methods_types as $type ) {
			try {
				$payment_methods[] = $this->customer_service->get_payment_methods_for_customer( $customer_id, $type );
			} catch ( API_Exception $e ) {
				wp_send_json_error(
					wp_strip_all_tags( $e->getMessage() ),
					403
				);
			}
		}

		$payment_methods = array_merge( ...$payment_methods );
		$data            = [];
		foreach ( $payment_methods as $payment_method ) {
			$response = $this->prepare_item_for_response( $payment_method, $request );
			$data[]   = $this->prepare_response_for_collection( $response );
		}

		return rest_ensure_response( $data );
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

		$prepared_item = [];

		$prepared_item['id']              = $item['id'];
		$prepared_item['type']            = $item['type'];
		$prepared_item['billing_details'] = $item['billing_details'];
		if ( array_key_exists( 'card', $item ) ) {
			$prepared_item['card'] = [
				'brand'     => $item['card']['brand'],
				'last4'     => $item['card']['last4'],
				'exp_month' => $item['card']['exp_month'],
				'exp_year'  => $item['card']['exp_year'],
			];
		}
		if ( array_key_exists( 'card', $item ) ) {
			$prepared_item['card'] = [
				'brand'     => $item['card']['brand'],
				'last4'     => $item['card']['last4'],
				'exp_month' => $item['card']['exp_month'],
				'exp_year'  => $item['card']['exp_year'],
			];
		} elseif ( array_key_exists( 'sepa_debit', $item ) ) {
			$prepared_item['sepa_debit'] = [
				'last4' => $item['sepa_debit']['last4'],
			];
		} elseif ( array_key_exists( 'link', $item ) ) {
			$prepared_item['link'] = [
				'email' => $item['link']['email'],
			];
		}

		$context       = $request['context'] ?? 'view';
		$prepared_item = $this->add_additional_fields_to_object( $prepared_item, $request );
		$prepared_item = $this->filter_response_by_context( $prepared_item, $context );

		return rest_ensure_response( $prepared_item );
	}

	/**
	 * Item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'payment_method',
			'type'       => 'object',
			'properties' => [
				'id'              => [
					'description' => __( 'ID for the payment method.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'type'            => [
					'description' => __( 'Type of the payment method.', 'woocommerce-payments' ),
					'type'        => 'string',
					'enum'        => [ 'card', 'sepa_debit', 'link' ],
					'context'     => [ 'view' ],
				],
				'billing_details' => [
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
				'card'            => [
					'description' => __( 'Card details for the payment method.', 'woocommerce-payments' ),
					'type'        => 'object',
					'context'     => [ 'view' ],
					'properties'  => [
						'brand'     => [
							'description' => __( 'Brand of the card.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
						'last4'     => [
							'description' => __( 'Last 4 digits of the card.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
						'exp_month' => [
							'description' => __( 'Expiration month of the card.', 'woocommerce-payments' ),
							'type'        => 'integer',
							'context'     => [ 'view' ],
						],
						'exp_year'  => [
							'description' => __( 'Expiration year of the card.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
					],
				],
				'sepa_debit'      => [
					'description' => __( 'SEPA Debit details for the payment method.', 'woocommerce-payments' ),
					'type'        => 'object',
					'context'     => [ 'view' ],
					'properties'  => [
						'last4' => [
							'description' => __( 'Last 4 digits of the SEPA Debit.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
					],
				],
				'link'            => [
					'description' => __( 'Link details for the payment method.', 'woocommerce-payments' ),
					'type'        => 'object',
					'context'     => [ 'view' ],
					'properties'  => [
						'email' => [
							'description' => __( 'Email associated with the link.', 'woocommerce-payments' ),
							'type'        => 'string',
							'format'      => 'email',
							'context'     => [ 'view' ],
						],
					],
				],
			],
		];
	}
}

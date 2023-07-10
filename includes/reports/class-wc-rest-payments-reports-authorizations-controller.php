<?php
/**
 * Class WC_REST_Payments_Reports_Authorizations_Controller
 *
 * @package WooCommerce\Payments\Reports
 */

use WCPay\Core\Server\Request\List_Authorizations;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the authorization reports.
 */
class WC_REST_Payments_Reports_Authorizations_Controller extends WC_Payments_REST_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/reports/authorizations';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_authorizations' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<charge_id>\w+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_authorization' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);
	}

	/**
	 * Retrieve authorizations to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_authorizations( $request ) {
		$wcpay_request = List_Authorizations::from_rest_request( $request );

		$wcpay_request->set_include_capturable_only( (bool) $request->get_param( 'include_capturable_only' ) ); // By default, we want to include all authorizations.
		$authorizations = $wcpay_request->handle_rest_request( 'wcpay_list_authorizations_request' );
		if ( is_wp_error( $authorizations ) ) {
			return $authorizations;
		}
		$data = [];
		foreach ( $authorizations['data'] ?? [] as $authorization ) {
			$response = $this->prepare_item_for_response( $authorization, $request );
			$data[]   = $this->prepare_response_for_collection( $response );
		}

		return rest_ensure_response( $data );

	}

	/**
	 * Retrieve authorization to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_authorization( $request ) {
		$charge_id     = $request->get_param( 'charge_id' );
		$wcpay_request = List_Authorizations::create();
		$wcpay_request->set_charge_id_is( $charge_id );
		$wcpay_request->set_include_capturable_only( false );
		$wcpay_request->set_page_size( 1 );

		$authorizations = $wcpay_request->handle_rest_request( 'wcpay_list_authorizations_request' );
		if ( is_wp_error( $authorizations ) ) {
			return $authorizations;
		}
		$authorization = $authorizations['data'][0] ?? null;
		if ( ! $authorization ) {
			return rest_ensure_response( [] );
		}
		$response = $this->prepare_item_for_response( $authorization, $request );

		return rest_ensure_response( $this->prepare_response_for_collection( $response ) );
	}

	/**
	 * Prepare each item for response.
	 *
	 * @param array           $item Item to prepare.
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {

		$prepared_item = [];

		$prepared_item['charge_id']        = $item['charge_id'];
		$prepared_item['transaction_id']   = $item['transaction_id'];
		$prepared_item['channel']          = $item['channel'];
		$prepared_item['timestamp']        = $item['created'];
		$prepared_item['order_id']         = $item['order_id'];
		$prepared_item['payment_method']   = [
			'type' => $item['source'],
			'id'   => $item['payment_method_id'] ?? null,
		];
		$prepared_item['customer_name']    = $item['customer_name'];
		$prepared_item['customer_email']   = $item['customer_email'];
		$prepared_item['customer_country'] = $item['customer_country'];
		$prepared_item['amount']           = $item['amount'];
		$prepared_item['amount_captured']  = $item['amount_captured'];
		$prepared_item['amount_refunded']  = $item['amount_refunded'];
		$prepared_item['is_captured']      = $item['is_captured'];
		$prepared_item['refunded']         = $item['refunded'];
		$prepared_item['net']              = $item['net'];
		$prepared_item['fees']             = $item['fees'];
		$prepared_item['currency']         = $item['currency'];
		$prepared_item['risk_level']       = $item['risk_level'];
		$prepared_item['outcome_type']     = $item['outcome_type'];
		$prepared_item['status']           = $item['status'];

		$context       = $request['context'] ?? 'view';
		$prepared_item = $this->add_additional_fields_to_object( $prepared_item, $request );
		$prepared_item = $this->filter_response_by_context( $prepared_item, $context );

		return rest_ensure_response( $prepared_item );
	}

	/**
	 * Collection args params.
	 *
	 * @return array[]
	 */
	public function get_collection_params() {
		return [
			'charge_id_is'            => [
				'description' => __( 'Filter authorizations based on their unique authorization ID.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'order_id_is'             => [
				'description' => __( 'Filter authorizations based on the associated order ID.', 'woocommerce-payments' ),
				'type'        => 'integer',
				'required'    => false,
			],
			'transaction_id_is'       => [
				'description' => __( 'Filter authorizations based on the associated transaction ID.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'payment_method_id_is'    => [
				'description' => __( 'Filter authorizations based on the payment method used.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'match'                   => [
				'description' => __( 'Match filter for the authorizations.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'created_before'          => [
				'description' => __( 'Filter authorizations before this date.', 'woocommerce-payments' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'required'    => false,
			],
			'created_after'           => [
				'description' => __( 'Filter authorizations after this date.', 'woocommerce-payments' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'required'    => false,
			],
			'created_between'         => [
				'description' => __( 'Filter authorizations between these dates.', 'woocommerce-payments' ),
				'type'        => 'array',
			],
			'outcome_type_is'         => [
				'description' => __( 'Filter authorizations where outcome type is a specific value.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'outcome_type_is_not'     => [
				'description' => __( 'Filter authorizations where outcome type is not a specific value.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'currency_is'             => [
				'description' => __( 'Filter authorizations based on currency.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'currency_is_not'         => [
				'description' => __( 'Filter authorizations not based on a specific currency.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'status_is'               => [
				'description' => __( 'Filter authorizations based on status.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'status_is_not'           => [
				'description' => __( 'Filter authorizations not based on a specific status.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'search'                  => [
				'description' => __( 'Search parameter for the authorizations.', 'woocommerce-payments' ),
				'type'        => 'array',
				'required'    => false,
			],
			'sort'                    => [
				'description' => __( 'Sort authorizations based on the passed field.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'include_capturable_only' => [
				'description' => __( 'Only include authorizations that are already captured (older than 7 days, refunded and already captured by payment provider),', 'woocommerce-payments' ),
				'type'        => 'boolean',
				'required'    => false,
			],
		];
	}


	/**
	 * Item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'authorization',
			'type'       => 'object',
			'properties' => [
				'timestamp'        => [
					'description' => __( 'The date and time when the authorization was created.', 'woocommerce-payments' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view' ],
				],
				'charge_id'        => [
					'description' => __( 'A unique identifier for each charge.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'transaction_id'   => [
					'description' => __( 'Associated transaction id for specific authorization', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'channel'          => [
					'description' => __( 'Indicates whether the authorization was made online or offline.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'payment_method'   => [
					'description' => __( 'Specifies whether the payment method used was a card (Visa, Mastercard, etc.) or an Alternative Payment Method (APM) or Local Payment Method (LPM) (iDEAL, Apple Pay, Google Pay, etc.).', 'woocommerce-payments' ),
					'type'        => 'object',
					'context'     => [ 'view' ],
					'properties'  => [
						'type' => [
							'description' => __( 'Specifies whether the payment method used was a card (Visa, Mastercard, etc.) or an Alternative Payment Method (APM) or Local Payment Method (LPM) (iDEAL, Apple Pay, Google Pay, etc.).', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
						'id'   => [
							'description' => __( 'The payment method ID used to create the authorization type.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
					],
				],
				'outcome_type'     => [
					'description' => __( 'The outcome type of the authorization.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'status'           => [
					'description' => __( 'The status of the authorization.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'currency'         => [
					'description' => __( 'The currency of the authorization.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'amount'           => [
					'description' => __( 'The amount of the authorization.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'amount_captured'  => [
					'description' => __( 'The captured amount.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'amount_refunded'  => [
					'description' => __( 'The refunded amount.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'is_captured'      => [
					'description' => __( 'Determinate is authorization captured.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'refunded'         => [
					'description' => __( 'Determinate is authorization refunded.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'fees'             => [
					'description' => __( 'authorization fees.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'net'              => [
					'description' => __( 'Net amount.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'risk_level'       => [
					'description' => __( 'Fraud risk level.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'order_id'         => [
					'description' => __( 'The identifier of the WooCommerce order associated with this authorization.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'customer_name'    => [
					'description' => __( 'The customer\'s name.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'customer_email'   => [
					'description' => __( 'The customer\'s email.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'customer_country' => [
					'description' => __( 'The country of the customer\'s address', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
			],
		];

		return $this->add_additional_fields_schema( $schema );
	}


}

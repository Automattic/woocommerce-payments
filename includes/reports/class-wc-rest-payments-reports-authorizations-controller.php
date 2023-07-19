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
			'/' . $this->rest_base . '/(?P<id>\w+)',
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
		$wcpay_request = List_Authorizations::from_reports_rest_request( $request );

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
		$wcpay_request = List_Authorizations::create();
		$wcpay_request->set_payment_intent_id_is( $request->get_param( 'id' ) );
		$wcpay_request->set_type_id( '' ); // With empty type we will skip default(legacy) behavior where we load authorizations that are only capturable.
		$wcpay_request->set_sort_by( 'created' ); // Default sort.
		$wcpay_request->set_page_size( 1 ); // Set page size to limit to only one record.

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

		$type          = 'captured'; // Default type.
		$is_captured   = 0 === $item['is_captured'];
		$record_date   = new \DateTime( $item['created'] );
		$current_date  = new \DateTime();
		$date_interval = $current_date->diff( $record_date );
		if ( $is_captured && 'failed' === $item['status'] ) {
			$type = 'failed'; // In case when authorization failed because it is blocked or issuer declined transaction.
		} elseif ( $is_captured && 1 === $item['refunded'] ) {
			$type = 'cancelled'; // When payment is authorized, but canceled before capture.
		} elseif ( 'authorized' === $item['outcome_type'] && $is_captured && $date_interval->days > 7 ) {
			$type = 'expired'; // If the diff is older than 7 days, we mark it as expired.
		} elseif ( 'authorized' === $item['outcome_type'] && $is_captured && $date_interval->days <= 7 ) {
			$type = 'uncaptured'; // If the diff is within 7 days, we mark it as uncaptured.
		}
		$prepared_item['date']                 = $item['created'];
		$prepared_item['transaction_id']       = $item['transaction_id'];
		$prepared_item['payment_intent_id']    = $item['payment_intent_id'];
		$prepared_item['channel']              = $item['channel'];
		$prepared_item['payment_method']       = [
			'type' => $item['source'],
			'id'   => $item['payment_method_id'],
		];
		$prepared_item['type']                 = $type;
		$prepared_item['transaction_currency'] = $item['currency'];
		$prepared_item['amount']               = $item['amount'];
		$prepared_item['fees']                 = $item['fees'];
		$prepared_item['customer']             = [
			'name'    => $item['customer_name'],
			'email'   => $item['customer_email'],
			'country' => $item['customer_country'],
		];
		$prepared_item['net_amount']           = $item['net'];
		$prepared_item['order_id']             = $item['order_id'];
		$prepared_item['risk_level']           = $item['risk_level'];

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
			'date_before'       => [
				'description' => __( 'Filter authorizations before this date.', 'woocommerce-payments' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'required'    => false,
			],
			'date_after'        => [
				'description' => __( 'Filter authorizations after this date.', 'woocommerce-payments' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'required'    => false,
			],
			'date_between'      => [
				'description' => __( 'Filter authorizations between these dates.', 'woocommerce-payments' ),
				'type'        => 'array',
			],
			'order_id'          => [
				'description'       => __( 'Filter authorizations based on the associated order ID.', 'woocommerce-payments' ),
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'email'             => [
				'description'       => __( 'Filter authorizations based on the customer email.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'payment_method_id' => [
				'description'       => __( 'Filter authorizations based on the payment method used.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'type'              => [
				'description'       => __( 'Filter authorizations where type is a specific value.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
				'enum'              => [ 'failed', 'cancelled', 'expired', 'uncaptured' ],
			],
			'transaction_id'    => [
				'description'       => __( 'Filter authorizations based on their unique transaction ID.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'payment_intent_id' => [
				'description'       => __( 'Filter authorizations based on their unique payment intent ID.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'match'             => [
				'description' => __( 'Match filter for the authorizations.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'search'            => [
				'description'       => __( 'Search parameter for the authorizations.', 'woocommerce-payments' ),
				'type'              => 'array',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'user_timezone'     => [
				'description' => __( 'Include timezone into date filtering.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'orderby'           => [
				'description' => __( 'Sort authorizations based on the passed field.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => 'created',
			],
			'order'             => [
				'description' => __( 'Order authorizations based on the passed field.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => 'desc',
				'enum'        => [ 'asc', 'desc' ],
			],
			'page'              => [
				'description' => __( 'Page number.', 'woocommerce-payments' ),
				'type'        => 'integer',
				'required'    => false,
				'default'     => 1,
				'minimum'     => 1,
			],
			'per_page'          => [
				'description' => __( 'Page size.', 'woocommerce-payments' ),
				'type'        => 'integer',
				'required'    => false,
				'default'     => 25,
				'minimum'     => 1,
				'maximum'     => 100,
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
			'title'      => 'transaction',
			'type'       => 'object',
			'properties' => [
				'date'                 => [
					'description' => __( 'The date and time when the transaction was created.', 'woocommerce-payments' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view' ],
				],
				'transaction_id'       => [
					'description' => __( 'A unique identifier for each transaction based on its transaction type.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'payment_intent_id'    => [
					'description' => __( 'A unique payment intent identifier for each transaction.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'channel'              => [
					'description' => __( 'Indicates whether the transaction was made online or offline.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'payment_method'       => [
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
							'description' => __( 'The payment method ID used to create the transaction type.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
					],
				],
				'type'                 => [
					'description' => __( 'The type of the transaction.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'transaction_currency' => [
					'description' => __( 'The currency of the transaction.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'amount'               => [
					'description' => __( 'The amount of the transaction.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'fees'                 => [
					'description' => __( 'Transaction fees.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'customer'             => [
					'description' => __( 'Customer details.', 'woocommerce-payments' ),
					'type'        => 'object',
					'context'     => [ 'view' ],
					'properties'  => [
						'name'    => [
							'name'    => __( 'Customer name.', 'woocommerce-payments' ),
							'type'    => 'string',
							'context' => [ 'view' ],
						],
						'email'   => [
							'description' => __( 'Customer email.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
						'country' => [
							'description' => __( 'Customer country.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
					],
				],
				'net_amount'           => [
					'description' => __( 'Net amount.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'order_id'             => [
					'description' => __( 'The identifier of the WooCommerce order associated with this transaction.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'risk_level'           => [
					'description' => __( 'Fraud risk level.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
			],
		];

		return $this->add_additional_fields_schema( $schema );
	}

}

<?php
/**
 * Class WC_REST_Payments_Reports_Transactions_Controller
 *
 * @package WooCommerce\Payments\Reports
 */

use WCPay\Core\Server\Request\List_Transactions;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the transaction reports.
 */
class WC_REST_Payments_Reports_Transactions_Controller extends WC_Payments_REST_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/reports/transactions';

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
					'callback'            => [ $this, 'get_transactions' ],
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
					'callback'            => [ $this, 'get_transaction' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);
	}

	/**
	 * Retrieve transactions to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transactions( $request ) {

		$wcpay_request = List_Transactions::from_rest_request( $request );
		$wcpay_request->set_page_size( $request->get_param( 'per_page' ) ?? 25 );

		// Setting the request with filters.
		$request->get_param( 'type' ) ? $wcpay_request->set_type_is( $request->get_param( 'type' ) ) : null;
		$filters = [
			'order_id_is'       => $request->get_param( 'order_id' ),
			'customer_email_is' => $request->get_param( 'customer_email' ),
			'source_is'         => $request->get_param( 'payment_method_type' ),
		];
		$wcpay_request->set_filters( $filters );

		$response = $wcpay_request->handle_rest_request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = [];
		foreach ( $response['data'] ?? [] as $transaction ) {
			$response = $this->prepare_item_for_response( $transaction, $request );
			$data[]   = $this->prepare_response_for_collection( $response );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Retrieve transaction to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transaction( $request ) {
		$wcpay_request = List_Transactions::create();

		$wcpay_request->set_filters( [ 'transaction_id_is' => $request->get_param( 'id' ) ] );
		$wcpay_request->set_sort_by( 'date' ); // Default sort.
		$wcpay_request->set_page_size( 1 ); // Set page size to limit to only one record.

		$response = $wcpay_request->handle_rest_request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$transaction = $response['data'][0] ?? null;
		if ( ! $transaction ) {
			return rest_ensure_response( [] );
		}
		$response = $this->prepare_item_for_response( $transaction, $request );
		$response = rest_ensure_response( $this->prepare_response_for_collection( $response ) );

		return $response;
	}

	/**
	 * Prepare each item for response.
	 *
	 * @param array|mixed     $item Item to prepare.
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error|WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {

		$prepared_item = [];

		$prepared_item['transaction_id']       = $item['transaction_id'];
		$prepared_item['date']                 = $item['date'];
		$prepared_item['payment_id']           = $item['payment_intent_id'];
		$prepared_item['channel']              = $item['channel'];
		$prepared_item['payment_method']       = [
			'type' => $item['source'],
		];
		$prepared_item['type']                 = $item['type'];
		$prepared_item['transaction_currency'] = $item['customer_currency'];
		$prepared_item['amount']               = $item['amount'];
		$prepared_item['exchange_rate']        = $item['exchange_rate'];
		$prepared_item['deposit_currency']     = $item['currency'];
		$prepared_item['fees']                 = $item['fees'];
		$prepared_item['customer']             = [
			'name'    => $item['customer_name'],
			'email'   => $item['customer_email'],
			'country' => $item['customer_country'],
		];
		$prepared_item['net_amount']           = $item['net'];
		$prepared_item['order_id']             = $item['order_id'];
		$prepared_item['risk_level']           = $item['risk_level'];
		$prepared_item['deposit_date']         = $item['available_on'];
		$prepared_item['deposit_id']           = $item['deposit_id'];
		$prepared_item['deposit_status']       = $item['deposit_status'] ?? null;

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
			'date_before'         => [
				'description' => __( 'Filter transactions before this date.', 'woocommerce-payments' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'required'    => false,
			],
			'date_after'          => [
				'description' => __( 'Filter transactions after this date.', 'woocommerce-payments' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'required'    => false,
			],
			'date_between'        => [
				'description' => __( 'Filter transactions between these dates.', 'woocommerce-payments' ),
				'type'        => 'array',
			],
			'order_id'            => [
				'description'       => __( 'Filter transactions based on the associated order ID.', 'woocommerce-payments' ),
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'deposit_id'          => [
				'description'       => __( 'Filter transactions based on the associated deposit ID.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'customer_email'      => [
				'description'       => __( 'Filter transactions based on the customer email.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'payment_method_type' => [
				'description'       => __( 'Filter transactions based on the payment method used.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'type'                => [
				'description'       => __( 'Filter transactions where type is a specific value.', 'woocommerce-payments' ),
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => 'rest_validate_request_arg',
			],
			'match'               => [
				'description' => __( 'Match filter for the transactions.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'user_timezone'       => [
				'description' => __( 'Include timezone into date filtering.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'page'                => [
				'description' => __( 'Page number.', 'woocommerce-payments' ),
				'type'        => 'integer',
				'required'    => false,
				'default'     => 1,
				'minimum'     => 1,
			],
			'per_page'            => [
				'description' => __( 'Page size.', 'woocommerce-payments' ),
				'type'        => 'integer',
				'required'    => false,
				'default'     => 25,
				'minimum'     => 1,
				'maximum'     => 100,
			],
			'sort'                => [
				'description' => __( 'Field on which to sort.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => 'date',
			],
			'direction'           => [
				'description' => __( 'Direction on which to sort.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => 'desc',
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
				'source_id'            => [
					'description' => __( 'A unique source id for each transaction.', 'woocommerce-payments' ),
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
				'exchange_rate'        => [
					'description' => __( 'The exchange rate of the transaction.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'deposit_currency'     => [
					'description' => __( 'The currency of the store.', 'woocommerce-payments' ),
					'type'        => 'string',
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
				'deposit_date'         => [
					'description' => __( 'Deposit date of transaction', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'deposit_id'           => [
					'description' => __( 'A unique identifier for the deposit.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'deposit_status'       => [
					'description' => __( 'The status of the deposit', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
			],
		];

		return $this->add_additional_fields_schema( $schema );
	}
}

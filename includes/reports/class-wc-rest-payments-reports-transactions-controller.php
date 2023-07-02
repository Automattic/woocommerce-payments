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
			'/' . $this->rest_base . '/(?P<transaction_id>\w+)',
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

		$transactions = $wcpay_request->handle_rest_request( 'wcpay_list_transactions_request' );
		if ( is_wp_error( $transactions ) ) {
			return $transactions;
		}
		$data = [];
		foreach ( $transactions['data'] ?? [] as $transaction ) {
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
		$transaction_id = $request->get_param( 'transaction_id' );
		$wcpay_request  = List_Transactions::create();
		$wcpay_request->set_filters(
			[
				'transaction_id_is' => $transaction_id,
				'sort'              => 'date', // There is a issue with class defaults, so we can quickly fix it fow now with this param.
			]
		);
		$transactions = $wcpay_request->handle_rest_request( 'wcpay_list_transactions_request' );
		if ( is_wp_error( $transactions ) ) {
			return $transactions;
		}
		$transaction = $transactions['data'][0] ?? null;
		if ( ! $transaction ) {
			rest_ensure_response( $transactions );
		}
		$response = $this->prepare_item_for_response( $transaction, $request );
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

		$prepared_item['transaction_id']    = $item['transaction_id'];
		$prepared_item['type']              = $item['type'];
		$prepared_item['channel']           = $item['channel'];
		$prepared_item['timestamp']         = $item['date'];
		$prepared_item['order']             = $item['order'];
		$prepared_item['payment_method']    = [
			'type' => $item['source'],
			'id'   => $item['payment_method_id'],
		];
		$prepared_item['customer_name']     = $item['customer_name'];
		$prepared_item['customer_email']    = $item['customer_email'];
		$prepared_item['customer_currency'] = $item['customer_currency'];
		$prepared_item['customer_country']  = $item['customer_country'];
		$prepared_item['amount']            = $item['amount'];
		$prepared_item['net']               = $item['net'];
		$prepared_item['fees']              = $item['fees'];
		$prepared_item['currency']          = $item['currency'];
		$prepared_item['exchange_rate']     = $item['exchange_rate'];
		$prepared_item['risk_level']        = $item['risk_level'];
		$prepared_item['deposit_id']        = $item['deposit_id'];
		$prepared_item['deposit_status']    = $item['deposit_status'] ?? null;

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
			'order_id_is'              => [
				'description' => __( 'Filter transactions based on the associated order ID.', 'woocommerce-payments' ),
				'type'        => 'integer',
				'required'    => false,
			],
			'deposit_id_is'            => [
				'description' => __( 'Filter transactions based on the associated deposit ID.', 'woocommerce-payments' ),
				'type'        => 'integer',
				'required'    => false,
			],
			'payment_method_id_is'     => [
				'description' => __( 'Filter transactions based on the payment method used.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'transaction_id_is'        => [
				'description' => __( 'Filter transactions based on their unique transaction ID.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'match'                    => [
				'description' => __( 'Match filter for the transactions.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'date_before'              => [
				'description' => __( 'Filter transactions before this date.', 'woocommerce-payments' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'required'    => false,
			],
			'date_after'               => [
				'description' => __( 'Filter transactions after this date.', 'woocommerce-payments' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'required'    => false,
			],
			'date_between'             => [
				'description' => __( 'Filter transactions between these dates.', 'woocommerce-payments' ),
				'type'        => 'array',
			],
			'type_is'                  => [
				'description' => __( 'Filter transactions where type is a specific value.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'type_is_not'              => [
				'description' => __( 'Filter transactions where type is not a specific value.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'source_device_is'         => [
				'description' => __( 'Filter transactions based on source device.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'source_device_is_not'     => [
				'description' => __( 'Filter transactions not based on a specific source device.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'store_currency_is'        => [
				'description' => __( 'Filter transactions based on store currency.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'customer_currency_is'     => [
				'description' => __( 'Filter transactions based on customer currency.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'customer_currency_is_not' => [
				'description' => __( 'Filter transactions not based on a specific customer currency.', 'woocommerce-payments' ),
				'type'        => 'string',
				'required'    => false,
			],
			'loan_id_is'               => [
				'description' => __( 'Filter transactions based on loan ID.', 'woocommerce-payments' ),
				'type'        => 'integer',
				'required'    => false,
			],
			'search'                   => [
				'description' => __( 'Search parameter for the transactions.', 'woocommerce-payments' ),
				'type'        => 'array',
				'required'    => false,
			],
			'sort'                     => [
				'description' => __( 'Sort transactions based on the passed field.', 'woocommerce-payments' ),
				'type'        => 'string',
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
			'title'      => 'transaction',
			'type'       => 'object',
			'properties' => [
				'timestamp'         => [
					'description' => __( 'The date and time when the transaction was created.', 'woocommerce-payments' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => [ 'view' ],
				],
				'transaction_id'    => [
					'description' => __( 'A unique identifier for each transaction based on its transaction type.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'channel'           => [
					'description' => __( 'Indicates whether the transaction was made online or offline.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'payment_method'    => [
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
				'order'             => [
					'description' => __( 'WooCommerce Order details', 'woocommerce-payments' ),
					'type'        => 'object',
					'context'     => [ 'view' ],
					'properties'  => [
						'number'        => [
							'description' => __( 'Order number', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
						'url'           => [
							'description' => __( 'Admin order URL.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
						'customer_url'  => [
							'description' => __( 'Customer order URL.', 'woocommerce-payments' ),
							'type'        => 'string',
							'context'     => [ 'view' ],
						],
						'subscriptions' => [
							'description' => __( 'List of subscriptions associated with order.', 'woocommerce-payments' ),
							'type'        => 'array',
							'context'     => [ 'view' ],
						],
					],
				],
				'type'              => [
					'description' => __( 'The type of the transaction.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'currency'          => [
					'description' => __( 'The currency of the transaction.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'customer_currency' => [
					'description' => __( 'The currency of the customer.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'amount'            => [
					'description' => __( 'The amount of the transaction.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'exchange_rate'     => [
					'description' => __( 'The exchange rate of the transaction.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'fees'              => [
					'description' => __( 'Transaction fees.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'net'               => [
					'description' => __( 'Net amount.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'risk_level'        => [
					'description' => __( 'Fraud risk level.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'order_id'          => [
					'description' => __( 'The identifier of the WooCommerce order associated with this transaction.', 'woocommerce-payments' ),
					'type'        => 'number',
					'context'     => [ 'view' ],
				],
				'customer_name'     => [
					'description' => __( 'The customer\'s name.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'customer_email'    => [
					'description' => __( 'The customer\'s email.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'customer_country'  => [
					'description' => __( 'The country of the customer\'s address', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'deposit_id'        => [
					'description' => __( 'A unique identifier for the deposit.', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
				'deposit_status'    => [
					'description' => __( 'The status of the deposit', 'woocommerce-payments' ),
					'type'        => 'string',
					'context'     => [ 'view' ],
				],
			],
		];

		return $this->add_additional_fields_schema( $schema );
	}


}

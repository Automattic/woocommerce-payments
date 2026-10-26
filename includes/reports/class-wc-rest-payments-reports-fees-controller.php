<?php
/**
 * Class WC_REST_Payments_Reports_Fees_Controller
 *
 * @package WooCommerce\Payments\Reports
 */

use WCPay\Core\Server\Request\Get_Transactions_Summary;
use WCPay\Core\Server\Request\List_Transactions;
use WCPay\Core\Server\Request\Request_Utils;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the Fees report.
 */
class WC_REST_Payments_Reports_Fees_Controller extends WC_REST_Payments_Reports_Transactions_Controller {
	/**
	 * Default transaction types shown in the Fees ledger.
	 *
	 * @var string[]
	 */
	const DEFAULT_FEE_BEARING_TYPES = [
		'charge',
		'payment',
		'payment_failure_refund',
		'payment_refund',
		'refund',
		'refund_failure',
		'dispute',
		'dispute_reversal',
		'fee_refund',
		'network_costs',
	];

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/reports/fees';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		if ( ! WC_Payments_Features::is_reports_area_enabled() ) {
			return;
		}

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
			'/' . $this->rest_base . '/summary',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_fees_summary' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_collection_params(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/download',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'get_fees_export' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/download/(?P<export_id>[^/\\\\%]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_export_url' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	/**
	 * Retrieve transactions to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transactions( $request ) {
		$wcpay_request = List_Transactions::create();
		$wcpay_request->set_page( (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$wcpay_request->set_page_size( (int) ( $request->get_param( 'per_page' ) ?? 25 ) );

		$sort = $request->get_param( 'sort' );
		if ( null !== $sort ) {
			$wcpay_request->set_sort_by( (string) $sort );
		}

		$direction = $request->get_param( 'direction' );
		if ( null !== $direction ) {
			$wcpay_request->set_sort_direction( (string) $direction );
		}

		$wcpay_request->set_filters( $this->get_fees_transaction_filters( $request ) );

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
	 * Retrieves the Fees report summary.
	 *
	 * @see \WCPay\Internal\Abilities\Domain\GetFeesSummary
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_fees_summary( $request ) {
		$filters    = $this->get_fees_transaction_filters( $request );
		$deposit_id = $filters['deposit_id'] ?? null;
		unset( $filters['deposit_id'] );

		$wcpay_request = Get_Transactions_Summary::create();
		$wcpay_request->set_filters( $filters );
		if ( null !== $deposit_id ) {
			$wcpay_request->set_deposit_id( (string) $deposit_id );
		}

		$response = $wcpay_request->handle_rest_request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Initiates a Fees CSV export via the transactions/download backend.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Backend response, typically `{ export_id: string }`, or a WP_Error on API failure.
	 */
	public function get_fees_export( $request ) {
		$filters    = $this->get_fees_transaction_filters( $request );
		$deposit_id = $filters['deposit_id'] ?? null;
		unset( $filters['deposit_id'] );

		$user_email = (string) ( $request->get_param( 'user_email' ) ?? '' );
		$locale     = $request->get_param( 'locale' );

		return $this->forward_request( 'get_transactions_export', [ $filters, $user_email, $deposit_id, $locale ] );
	}

	/**
	 * Returns the signed download URL for a previously requested Fees export.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error Backend response, typically `{ status, download_url }`, or a WP_Error on API failure.
	 */
	public function get_export_url( $request ) {
		$export_id = (string) $request->get_param( 'export_id' );

		return $this->forward_request( 'get_transactions_export_url', [ $export_id ] );
	}

	/**
	 * Prepares a Fees transaction item for the REST response.
	 *
	 * @param array           $item    Transaction data.
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$response = parent::prepare_item_for_response( $item, $request );
		$data     = $response->get_data();

		unset( $data['customer'] );
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Returns the response schema for a Fees report row.
	 *
	 * Removes the `customer` property inherited from the Transactions schema —
	 * the Fees endpoint strips customer data from every response, so advertising
	 * it would mislead schema-discovery consumers.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = parent::get_item_schema();
		unset( $schema['properties']['customer'] );

		return $schema;
	}

	/**
	 * Collection args params.
	 *
	 * @return array[]
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		// The Fees endpoint strips customer data from every response, so the
		// matching input filter must be removed too — leaving it advertised
		// would invite raw REST calls that leak emails to access logs and
		// forward them to the backend.
		unset( $params['customer_email'] );

		$params['search'] = [
			'description'       => __( 'Search transactions by known identifiers.', 'woocommerce-payments' ),
			'type'              => 'array',
			'required'          => false,
			'items'             => [
				'type' => 'string',
			],
			'sanitize_callback' => function ( $value ) {
				return self::sanitize_string_list( $value );
			},
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['type']   = [
			'description'       => __( 'Filter transactions where type matches any listed value.', 'woocommerce-payments' ),
			'type'              => 'array',
			'required'          => false,
			'items'             => [
				'type' => 'string',
			],
			'sanitize_callback' => function ( $value ) {
				return self::sanitize_string_list( $value );
			},
			'validate_callback' => 'rest_validate_request_arg',
		];

		return $params;
	}

	/**
	 * Maps report query params to transaction API filters.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return array
	 */
	protected function get_fees_transaction_filters( $request ): array {
		$user_timezone       = $request->get_param( 'user_timezone' );
		$date_between_filter = $request->get_param( 'date_between' );
		$search              = $request->get_param( 'search' );
		$identifier_filters  = self::get_identifier_filters_from_search( $search );

		if ( is_array( $date_between_filter ) ) {
			$date_between_filter = array_map(
				function ( $transaction_date ) use ( $user_timezone ) {
					return Request_Utils::format_transaction_date_by_timezone( $transaction_date, $user_timezone );
				},
				$date_between_filter
			);
		} else {
			$date_between_filter = null;
		}

		$filters = [
			'source_is' => $request->get_param( 'payment_method_type' ),
		];

		$type = $request->get_param( 'type' );
		if ( is_array( $type ) ) {
			$filters['type_is_in'] = array_map( 'sanitize_text_field', $type );
		} elseif ( ! empty( $type ) ) {
			$filters['type_is_in'] = [ sanitize_text_field( $type ) ];
		} else {
			$filters['type_is_in'] = self::DEFAULT_FEE_BEARING_TYPES;
		}

		$filters = array_merge(
			$filters,
			[
				'order_id_is'   => $request->get_param( 'order_id' ),
				'deposit_id'    => $request->get_param( 'deposit_id' ),
				'date_before'   => Request_Utils::format_transaction_date_by_timezone( $request->get_param( 'date_before' ), $user_timezone ),
				'date_after'    => Request_Utils::format_transaction_date_by_timezone( $request->get_param( 'date_after' ), $user_timezone ),
				'date_between'  => $date_between_filter,
				'match'         => $request->get_param( 'match' ),
				'search'        => [] === $identifier_filters ? $search : null,
				'user_timezone' => $user_timezone,
			]
		);
		$filters = array_merge( $filters, $identifier_filters );

		return array_filter(
			$filters,
			static function ( $filter ) {
				return null !== $filter && '' !== $filter && [] !== $filter;
			}
		);
	}

	/**
	 * Maps supported identifier search terms to explicit transaction filters.
	 *
	 * @param mixed $search Raw REST search value.
	 *
	 * @return array
	 */
	private static function get_identifier_filters_from_search( $search ): array {
		if ( ! is_array( $search ) || 1 !== count( $search ) ) {
			return [];
		}

		$term = reset( $search );
		if ( ! is_string( $term ) ) {
			return [];
		}

		if ( 1 === preg_match( '/^po_\w+$/', $term ) ) {
			return [ 'deposit_id' => $term ];
		}

		if ( 1 === preg_match( '/^txn_\w+$/', $term ) ) {
			return [ 'transaction_id_is' => $term ];
		}

		return [];
	}

	/**
	 * Sanitizes a REST query value that may arrive as either one string or a list.
	 *
	 * @param mixed $value Raw REST request value.
	 *
	 * @return array
	 */
	private static function sanitize_string_list( $value ): array {
		if ( null === $value || '' === $value ) {
			return [];
		}

		$values    = is_array( $value ) ? $value : [ $value ];
		$sanitized = [];

		foreach ( $values as $item ) {
			if ( null === $item || '' === $item || is_array( $item ) || is_object( $item ) ) {
				continue;
			}

			$item = sanitize_text_field( $item );
			if ( '' !== $item ) {
				$sanitized[] = $item;
			}
		}

		return $sanitized;
	}
}

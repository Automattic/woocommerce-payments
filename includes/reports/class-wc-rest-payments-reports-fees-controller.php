<?php
/**
 * Class WC_REST_Payments_Reports_Fees_Controller
 *
 * @package WooCommerce\Payments\Reports
 */

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
					'args'                => $this->get_collection_params(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/download/(?P<export_id>[^/\\%]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_fees_export_url' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
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
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_fees_summary( $request ) {
		$filters = $this->get_fees_transaction_filters( $request );

		// Fees summary reuses the transactions summary endpoint as an isolated legacy-pattern exception.
		return $this->forward_request( 'get_reports_fees_summary', [ $filters ] );
	}

	/**
	 * Initiates the Fees report export.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_fees_export( $request ) {
		$filters    = $this->get_fees_transaction_filters( $request );
		$user_email = $request->get_param( 'user_email' );
		$locale     = $request->get_param( 'locale' );

		// Fees export reuses the transactions export endpoint as an isolated legacy-pattern exception.
		return $this->forward_request( 'get_reports_fees_export', [ $filters, $user_email, $locale ] );
	}

	/**
	 * Retrieves the Fees report export URL.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_fees_export_url( $request ) {
		$export_id = $request->get_param( 'export_id' );

		return $this->forward_request( 'get_reports_fees_export_url', [ $export_id ] );
	}

	/**
	 * Collection args params.
	 *
	 * @return array[]
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['available_on_after']  = [
			'description' => __( 'Filter transactions available after this date.', 'woocommerce-payments' ),
			'type'        => 'string',
			'format'      => 'date-time',
			'required'    => false,
		];
		$params['available_on_before'] = [
			'description' => __( 'Filter transactions available before this date.', 'woocommerce-payments' ),
			'type'        => 'string',
			'format'      => 'date-time',
			'required'    => false,
		];
		$params['search']              = [
			'description' => __( 'Search transactions by known identifiers.', 'woocommerce-payments' ),
			'type'        => 'array',
			'required'    => false,
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
		$user_timezone               = $request->get_param( 'user_timezone' );
		$date_between_filter         = $request->get_param( 'date_between' );
		$available_on_between_filter = $request->get_param( 'available_on_between' );

		if ( ! is_null( $date_between_filter ) ) {
			$date_between_filter = array_map(
				function ( $transaction_date ) use ( $user_timezone ) {
					return Request_Utils::format_transaction_date_by_timezone( $transaction_date, $user_timezone );
				},
				$date_between_filter
			);
		}

		if ( ! is_null( $available_on_between_filter ) ) {
			$available_on_between_filter = array_map(
				function ( $transaction_date ) use ( $user_timezone ) {
					return Request_Utils::format_transaction_date_by_timezone( $transaction_date, $user_timezone );
				},
				$available_on_between_filter
			);
		}

		$filters = [
			'source_is' => $request->get_param( 'payment_method_type' ),
		];

		$type = $request->get_param( 'type' );
		if ( is_array( $type ) ) {
			$filters['type_is_in'] = $type;
		} elseif ( is_string( $type ) && false !== strpos( $type, ',' ) ) {
			$filters['type_is_in'] = array_map( 'trim', explode( ',', $type ) );
		} elseif ( ! empty( $type ) ) {
			$filters['type_is'] = $type;
		} else {
			$filters['type_is_in'] = self::DEFAULT_FEE_BEARING_TYPES;
		}

		$filters = array_merge(
			$filters,
			[
				'order_id_is'          => $request->get_param( 'order_id' ),
				'customer_email_is'    => $request->get_param( 'customer_email' ),
				'deposit_id'           => $request->get_param( 'deposit_id' ),
				'date_before'          => Request_Utils::format_transaction_date_by_timezone( $request->get_param( 'date_before' ), $user_timezone ),
				'date_after'           => Request_Utils::format_transaction_date_by_timezone( $request->get_param( 'date_after' ), $user_timezone ),
				'date_between'         => $date_between_filter,
				'available_on_before'  => Request_Utils::format_transaction_date_by_timezone( $request->get_param( 'available_on_before' ), $user_timezone ),
				'available_on_after'   => Request_Utils::format_transaction_date_by_timezone( $request->get_param( 'available_on_after' ), $user_timezone ),
				'available_on_between' => $available_on_between_filter,
				'match'                => $request->get_param( 'match' ),
				'search'               => $request->get_param( 'search' ),
				'user_timezone'        => $user_timezone,
			]
		);

		return array_filter(
			$filters,
			static function ( $filter ) {
				return null !== $filter && '' !== $filter && [] !== $filter;
			}
		);
	}
}

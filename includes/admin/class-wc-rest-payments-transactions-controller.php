<?php
/**
 * Class WC_REST_Payments_Transactions_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\List_Transactions;
use WCPay\Core\Server\Request\List_Fraud_Outcome_Transactions;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for transactions.
 */
class WC_REST_Payments_Transactions_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/transactions';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_transactions' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/download',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_transactions_export' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/download/(?P<export_id>[^/\\\\%]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_export_url' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_transactions_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/search',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_transactions_search_autocomplete' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fraud-outcomes',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fraud_outcome_transactions' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fraud-outcomes/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fraud_outcome_transactions_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fraud-outcomes/search',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fraud_outcome_transactions_search_autocomplete' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fraud-outcomes/download',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fraud_outcome_transactions_export' ],
				'permission_callback' => [ $this, 'check_permission' ],
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

		return $wcpay_request->handle_rest_request();
	}

	/**
	 * Retrieve fraud outcome transactions to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_fraud_outcome_transactions( $request ) {
		$wcpay_request = List_Fraud_Outcome_Transactions::from_rest_request( $request );

		return $this->forward_request( 'list_fraud_outcome_transactions', [ $wcpay_request ] );
	}

	/**
	 * Retrieve fraud outcome transactions summary to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_fraud_outcome_transactions_summary( $request ) {
		$wcpay_request = List_Fraud_Outcome_Transactions::from_rest_request( $request );

		return $this->forward_request( 'list_fraud_outcome_transactions_summary', [ $wcpay_request ] );
	}

	/**
	 * Retrieve transactions search options to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_fraud_outcome_transactions_search_autocomplete( $request ) {
		$wcpay_request = List_Fraud_Outcome_Transactions::from_rest_request( $request );

		return $this->forward_request( 'get_fraud_outcome_transactions_search_autocomplete', [ $wcpay_request ] );
	}

	/**
	 * Initiate transactions export via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_fraud_outcome_transactions_export( $request ) {
		$wcpay_request = List_Fraud_Outcome_Transactions::from_rest_request( $request );

		return $this->forward_request( 'get_fraud_outcome_transactions_export', [ $wcpay_request ] );
	}

	/**
	 * Initiate transactions export via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transactions_export( $request ) {
		$user_email = $request->get_param( 'user_email' );
		$deposit_id = $request->get_param( 'deposit_id' );
		$locale     = $request->get_param( 'locale' );
		$filters    = $this->get_transactions_filters( $request );

		return $this->forward_request( 'get_transactions_export', [ $filters, $user_email, $deposit_id, $locale ] );
	}

	/**
	 * Get the export URL for a given export ID, if available.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_export_url( $request ) {
		$export_id = $request->get_param( 'export_id' );
		return $this->forward_request( 'get_transactions_export_url', [ $export_id ] );
	}

	/**
	 * Retrieve transactions summary to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transactions_summary( $request ) {
		$deposit_id = $request->get_param( 'deposit_id' );
		$filters    = $this->get_transactions_filters( $request );
		return $this->forward_request( 'get_transactions_summary', [ $filters, $deposit_id ] );
	}

	/**
	 * Retrieve transactions search options to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transactions_search_autocomplete( $request ) {
		$search_term = $request->get_param( 'search_term' );
		return $this->forward_request( 'get_transactions_search_autocomplete', [ $search_term ] );
	}

	/**
	 * Extract transactions filters from request
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	private function get_transactions_filters( $request ) {
		return array_filter(
			[
				'match'                    => $request->get_param( 'match' ),
				'date_before'              => $request->get_param( 'date_before' ),
				'date_after'               => $request->get_param( 'date_after' ),
				'date_between'             => $request->get_param( 'date_between' ),
				'type_is'                  => $request->get_param( 'type_is' ),
				'type_is_not'              => $request->get_param( 'type_is_not' ),
				'source_device_is'         => $request->get_param( 'source_device_is' ),
				'source_device_is_not'     => $request->get_param( 'source_device_is_not' ),
				'channel_is'               => $request->get_param( 'channel_is' ),
				'channel_is_not'           => $request->get_param( 'channel_is_not' ),
				'customer_country_is'      => $request->get_param( 'customer_country_is' ),
				'customer_country_is_not'  => $request->get_param( 'customer_country_is_not' ),
				'risk_level_is'            => $request->get_param( 'risk_level_is' ),
				'risk_level_is_not'        => $request->get_param( 'risk_level_is_not' ),
				'store_currency_is'        => $request->get_param( 'store_currency_is' ),
				'customer_currency_is'     => $request->get_param( 'customer_currency_is' ),
				'customer_currency_is_not' => $request->get_param( 'customer_currency_is_not' ),
				'source_is'                => $request->get_param( 'source_is' ),
				'source_is_not'            => $request->get_param( 'source_is_not' ),
				'loan_id_is'               => $request->get_param( 'loan_id_is' ),
				'search'                   => $request->get_param( 'search' ),
			],
			static function ( $filter ) {
				return null !== $filter;
			}
		);
	}
}

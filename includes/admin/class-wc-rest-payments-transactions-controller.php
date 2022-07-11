<?php
/**
 * Class WC_REST_Payments_Transactions_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

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
			'/' . $this->rest_base . '/(?P<transaction_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_transaction' ],
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
		$page       = (int) $request->get_param( 'page' );
		$page_size  = (int) $request->get_param( 'pagesize' );
		$sort       = $request->get_param( 'sort' );
		$direction  = $request->get_param( 'direction' );
		$deposit_id = $request->get_param( 'deposit_id' );
		$filters    = $this->get_transactions_filters( $request );
		return $this->forward_request( 'list_transactions', [ $page, $page_size, $sort, $direction, $filters, $deposit_id ] );
	}

	/**
	 * Initiate transactions export via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transactions_export( $request ) {
		$user_email = $request->get_param( 'user_email' );
		$deposit_id = $request->get_param( 'deposit_id' );
		$filters    = $this->get_transactions_filters( $request );

		return $this->forward_request( 'get_transactions_export', [ $filters, $user_email, $deposit_id ] );
	}

	/**
	 * Retrieve transaction to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transaction( $request ) {
		$transaction_id = $request->get_param( 'transaction_id' );
		return $this->forward_request( 'get_transactions', [ 'transaction_id' ] );
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
				'store_currency_is'        => $request->get_param( 'store_currency_is' ),
				'customer_currency_is'     => $request->get_param( 'customer_currency_is' ),
				'customer_currency_is_not' => $request->get_param( 'customer_currency_is_not' ),
				'loan_id_is'               => $request->get_param( 'loan_id_is' ),
				'search'                   => $request->get_param( 'search' ),
			],
			static function ( $filter ) {
				return null !== $filter;
			}
		);
	}
}

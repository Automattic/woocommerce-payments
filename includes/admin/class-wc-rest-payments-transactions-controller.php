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

		$wcpay_request = List_Transactions::from_rest_request( $request );

		return $wcpay_request->handle_rest_request( 'wcpay_list_transactions_request' );
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
		$date_between_filter = $request->get_param( 'date_between' );
		$user_timezone       = $request->get_param( 'user_timezone' );

		if ( ! is_null( $date_between_filter ) ) {
			$date_between_filter = array_map(
				function ( $transaction_date ) use ( $user_timezone ) {
					return $this->format_transaction_date_with_timestamp( $transaction_date, $user_timezone );
				},
				$date_between_filter
			);
		}

		return array_filter(
			[
				'match'                    => $request->get_param( 'match' ),
				'date_before'              => $this->format_transaction_date_with_timestamp( $request->get_param( 'date_before' ), $user_timezone ),
				'date_after'               => $this->format_transaction_date_with_timestamp( $request->get_param( 'date_after' ), $user_timezone ),
				'date_between'             => $date_between_filter,
				'type_is'                  => $request->get_param( 'type_is' ),
				'type_is_not'              => $request->get_param( 'type_is_not' ),
				'source_device_is'         => $request->get_param( 'source_device_is' ),
				'source_device_is_not'     => $request->get_param( 'source_device_is_not' ),
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

	/**
	 * Formats the incoming transaction date as per the blog's timezone.
	 *
	 * @param string|null $transaction_date Transaction date to format.
	 * @param string|null $user_timezone         User's timezone passed from client.
	 *
	 * @return string|null The formatted transaction date as per timezone.
	 */
	private function format_transaction_date_with_timestamp( $transaction_date, $user_timezone ) {
		if ( is_null( $transaction_date ) || is_null( $user_timezone ) ) {
			return $transaction_date;
		}

		// Get blog timezone.
		$blog_time = new DateTime( $transaction_date );
		$blog_time->setTimezone( new DateTimeZone( wp_timezone_string() ) );

		// Get local timezone.
		$local_time = new DateTime( $transaction_date );
		$local_time->setTimezone( new DateTimeZone( $user_timezone ) );

		// Compute time difference in minutes.
		$time_difference = ( strtotime( $local_time->format( 'Y-m-d H:i:s' ) ) - strtotime( $blog_time->format( 'Y-m-d H:i:s' ) ) ) / 60;

		// Shift date by time difference.
		$formatted_date = new DateTime( $transaction_date );
		date_modify( $formatted_date, $time_difference . 'minutes' );

		return $formatted_date->format( 'Y-m-d H:i:s' );
	}
}

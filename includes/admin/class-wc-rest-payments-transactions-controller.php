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
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_transactions' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_transactions_summary' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<transaction_id>\w+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_transaction' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Retrieve transactions to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transactions( $request ) {
		$page       = intval( $request->get_params()['page'] );
		$page_size  = intval( $request->get_params()['pagesize'] );
		$sort       = $request->get_params()['sort'];
		$direction  = $request->get_params()['direction'];
		$deposit_id = $request->get_params()['deposit_id'];
		return $this->forward_request( 'list_transactions', [ $page, $page_size, $sort, $direction, $deposit_id ] );
	}

	/**
	 * Retrieve transaction to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transaction( $request ) {
		$transaction_id = $request->get_params()['transaction_id'];
		return $this->forward_request( 'get_transactions', [ 'transaction_id' ] );
	}

	/**
	 * Retrieve transactions summary to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_transactions_summary( $request ) {
		$deposit_id = $request->get_params()['deposit_id'];
		return $this->forward_request( 'get_transactions_summary', [ $deposit_id ] );
	}
}

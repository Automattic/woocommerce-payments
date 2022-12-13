<?php
/**
 * Class WC_REST_Payments_Authorizations_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Paginated as Paginated_Request;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for authorizations.
 */
class WC_REST_Payments_Authorizations_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/authorizations';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_authorizations' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_authorizations_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<payment_intent_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_authorization' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve authorizations to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_authorizations( WP_REST_Request $request ) {
		$wcpay_request = Paginated_Request::create();
		$wcpay_request->set_uri( WC_Payments_API_Client::AUTHORIZATIONS_API );
		$wcpay_request->set_page( (int) $request->get_param( 'page' ) );
		$wcpay_request->set_page_size( (int) $request->get_param( 'pagesize' ) );
		$wcpay_request->set_sort_by( $request->get_param( 'sort' ) );
		$wcpay_request->set_sort_direction( $request->get_param( 'direction' ) );

		return $wcpay_request->handle_request( 'wcpay_list_authorizations_request' );
	}

	/**
	 * Retrieve authorization to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_authorization( WP_REST_Request $request ) {
		$payment_intent_id = $request->get_param( 'payment_intent_id' );
		return $this->forward_request( 'get_authorization', [ $payment_intent_id ] );
	}

	/**
	 * Retrieve authorizations summary to respond with via API.
	 */
	public function get_authorizations_summary() {
		return $this->forward_request( 'get_authorizations_summary', [] );
	}
}

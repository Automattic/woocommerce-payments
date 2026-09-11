<?php
/**
 * Class WC_REST_Payments_Early_Fraud_Warnings_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Database_Cache;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the payments carrying an actionable early fraud warning.
 *
 * The Overview task reads this on mount rather than from the localized settings blob, so a
 * warning resolved during the session stops being reported without a full page reload, and
 * so the bounded order query behind it does not run on every wc-admin screen.
 */
class WC_REST_Payments_Early_Fraud_Warnings_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/early_fraud_warnings';

	/**
	 * Order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Database cache.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client    $api_client     WooCommerce Payments API client.
	 * @param WC_Payments_Order_Service $order_service  Order service.
	 * @param Database_Cache            $database_cache Database cache.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_Order_Service $order_service,
		Database_Cache $database_cache
	) {
		parent::__construct( $api_client );
		$this->order_service  = $order_service;
		$this->database_cache = $database_cache;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/active',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_active_early_fraud_warnings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve the orders whose latest early fraud warning is still actionable.
	 *
	 * @param WP_REST_Request $_unused_request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_active_early_fraud_warnings( $_unused_request ) {
		$cache_key = WC_Payments::mode()->is_test()
			? Database_Cache::EARLY_FRAUD_WARNING_ORDERS_KEY_TEST_MODE
			: Database_Cache::EARLY_FRAUD_WARNING_ORDERS_KEY;

		$early_fraud_warnings = $this->database_cache->get_or_add(
			$cache_key,
			function () {
				return $this->order_service->get_actionable_early_fraud_warning_orders();
			},
			'is_array'
		);

		return rest_ensure_response( is_array( $early_fraud_warnings ) ? $early_fraud_warnings : [] );
	}
}

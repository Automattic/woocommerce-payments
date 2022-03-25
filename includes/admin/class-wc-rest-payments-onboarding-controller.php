<?php
/**
 * Class WC_REST_Payments_Onboarding_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for account details and status.
 */
class WC_REST_Payments_Onboarding_Controller extends WC_Payments_REST_Controller {

	/**
	 * Onboarding Service.
	 *
	 * @var WC_Payments_Onboarding_Service
	 */
	protected $onboarding_service;

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/onboarding';

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client         $api_client         WooCommerce Payments API client.
	 * @param WC_Payments_Onboarding_Service $onboarding_service Onboarding Service class instance.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_Onboarding_Service $onboarding_service
	) {
		parent::__construct( $api_client );
		$this->onboarding_service = $onboarding_service;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/business_types',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_business_types' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Get business types via API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_business_types( $request ) {
		$business_types = $this->onboarding_service->get_cached_business_types();
		return rest_ensure_response( [ 'data' => $business_types ] );
	}
}

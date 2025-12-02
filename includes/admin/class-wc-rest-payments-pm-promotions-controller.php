<?php
/**
 * Class WC_REST_Payments_Promotions_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for payment methods (PM) promotions functionality.
 */
class WC_REST_Payments_PM_Promotions_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/pm-promotions';

	/**
	 * The PM promotions service.
	 *
	 * @var WC_Payments_PM_Promotions_Service
	 */
	private $promotions_service;

	/**
	 * WC_REST_Payments_PM_Promotions_Controller constructor.
	 *
	 * @param WC_Payments_API_Client            $api_client          WooPayments API client.
	 * @param WC_Payments_PM_Promotions_Service $promotions_service  The PM promotions service.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_PM_Promotions_Service $promotions_service
	) {
		parent::__construct( $api_client );
		$this->promotions_service = $promotions_service;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_promotions' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<identifier>[a-zA-Z0-9_-]+)/activate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'activate_promotion' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'identifier'   => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'accept_terms' => [
						'required' => false,
						'type'     => 'boolean',
						'default'  => true,
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<identifier>[a-zA-Z0-9_-]+)/dismiss',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'dismiss_promotion' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'identifier' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'id'         => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'The promotion unique identifier (e.g., klarna-2026-promo__spotlight).',
					],
				],
			]
		);
	}

	/**
	 * Retrieve the active promotions list.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_promotions() {
		$promotions = $this->promotions_service->get_visible_promotions();
		return rest_ensure_response( $promotions ?? [] );
	}

	/**
	 * Activate a promotion.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_promotion( $request ) {
		$identifier   = $request->get_param( 'identifier' );
		$accept_terms = $request->get_param( 'accept_terms' );

		$response = $this->promotions_service->activate_promotion( $identifier, $accept_terms );

		return rest_ensure_response( $response );
	}

	/**
	 * Dismiss a promotion.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss_promotion( $request ) {
		$id = $request->get_param( 'id' );

		$response = $this->promotions_service->dismiss_promotion( $id );

		return rest_ensure_response( $response );
	}
}

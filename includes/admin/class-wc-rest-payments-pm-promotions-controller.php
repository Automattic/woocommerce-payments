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
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/activate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'activate_promotion' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'string',
						'description'       => __( 'The promotion unique identifier.', 'woocommerce-payments' ),
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ $this, 'validate_promotion_id' ],
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/dismiss',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'dismiss_promotion' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'string',
						'description'       => __( 'The promotion unique identifier.', 'woocommerce-payments' ),
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ $this, 'validate_promotion_id' ],
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
	public function activate_promotion( WP_REST_Request $request ) {
		$result = $this->promotions_service->activate_promotion( $request->get_param( 'id' ) );

		return rest_ensure_response( [ 'success' => $result ] );
	}

	/**
	 * Dismiss a promotion.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss_promotion( WP_REST_Request $request ) {
		$result = $this->promotions_service->dismiss_promotion( $request->get_param( 'id' ) );

		return rest_ensure_response( [ 'success' => $result ] );
	}

	/**
	 * Validate the promotion ID parameter.
	 *
	 * @param mixed           $value   The parameter value.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The parameter name.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_promotion_id( $value, WP_REST_Request $request, string $param ): bool {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return false;
		}

		// Match alphanumeric characters, underscores, and hyphens only.
		return (bool) preg_match( '/^[a-zA-Z0-9_-]+$/', $value );
	}
}

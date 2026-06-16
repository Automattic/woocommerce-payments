<?php
/**
 * Class WC_REST_Payments_Survey_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for Payments survey feedback.
 */
class WC_REST_Payments_Survey_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/survey';

	/**
	 * The HTTP client used to forward the request.
	 *
	 * @var WC_Payments_Http_Interface
	 */
	protected $http_client;

	/**
	 * The constructor.
	 *
	 * @param WC_Payments_Http_Interface $http_client The HTTP client used to forward the request.
	 */
	public function __construct( WC_Payments_Http_Interface $http_client ) {
		$this->http_client = $http_client;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		if ( ! WC_Payments_Features::is_reports_area_enabled() ) {
			return;
		}

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reports-feedback',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'submit_reports_feedback_survey' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'rating'   => [
						'type'              => 'string',
						'enum'              => [
							'thumbs-up',
							'thumbs-down',
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'comments' => [
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'wp_filter_nohtml_kses',
					],
				],
			]
		);
	}

	/**
	 * Submits the reports feedback survey through the WPCOM API.
	 *
	 * @param WP_REST_Request $request The request being made.
	 *
	 * @return WP_REST_Response
	 */
	public function submit_reports_feedback_survey( WP_REST_Request $request ): WP_REST_Response {
		$comments = $request->get_param( 'comments' ) ?? '';
		$rating   = $request->get_param( 'rating' ) ?? '';

		if ( empty( $comments ) && empty( $rating ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'err'     => 'No answers provided',
				],
				400
			);
		}

		// Jetpack Connection 1.27.0 added a default for this constant. Keep a fallback for older bundled versions.
		defined( 'JETPACK__WPCOM_JSON_API_BASE' ) || define( 'JETPACK__WPCOM_JSON_API_BASE', 'https://public-api.wordpress.com' );

		$wpcom_request = $this->http_client->wpcom_json_api_request_as_user(
			'/marketing/survey',
			'2',
			[
				'method'  => 'POST',
				'headers' => [
					'Content-Type'    => 'application/json',
					'X-Forwarded-For' => \WC_Geolocation::get_ip_address(),
				],
			],
			[
				'site_id'          => $this->http_client->get_blog_id(),
				'survey_id'        => 'wcpay-reports-feedback',
				'survey_responses' => [
					'rating'   => $rating,
					'comments' => [ 'text' => $comments ],
				],
			]
		);

		if ( is_wp_error( $wpcom_request ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'err'     => $wpcom_request->get_error_message(),
				],
				500
			);
		}

		return new WP_REST_Response(
			json_decode( wp_remote_retrieve_body( $wpcom_request ) ),
			wp_remote_retrieve_response_code( $wpcom_request )
		);
	}

	/**
	 * Verify access.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}

<?php
/**
 * Class WC_REST_Payments_Survey_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for settings.
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
	 * The HTTP client, used to forward the request to WPCom.
	 *
	 * @var WC_Payments_Http
	 */
	protected $http_client;

	/**
	 * The constructor.
	 * WC_REST_Payments_Survey_Controller constructor.
	 *
	 * @param WC_Payments_Http_Interface $http_client - The HTTP client, used to forward the request to WPCom.
	 */
	public function __construct( WC_Payments_Http_Interface $http_client ) {
		$this->http_client = $http_client;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/payments-overview',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'submit_payments_overview_survey' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'rating'   => [
						'type'              => 'string',
						'required'          => true,
						'enum'              => [
							'very-unhappy',
							'unhappy',
							'neutral',
							'happy',
							'very-happy',
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
	 * Submits the overview survey trhough the WPcom API.
	 *
	 * @param WP_REST_Request $request the request being made.
	 *
	 * @return WP_REST_Response
	 */
	public function submit_payments_overview_survey( WP_REST_Request $request ): WP_REST_Response {
		$comments = $request->get_param( 'comments' ) ?? '';
		$rating   = $request->get_param( 'rating' ) ?? '';

		if ( empty( $rating ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'err'     => 'No answers provided',
				],
				400
			);
		}

		$request_args     = [
			'url'     => WC_Payments_API_Client::ENDPOINT_BASE . '/marketing/survey',
			'method'  => 'POST',
			'headers' => [
				'Content-Type'    => 'application/json',
				'X-Forwarded-For' => \WC_Geolocation::get_ip_address(),
			],
		];
		$request_body     = wp_json_encode(
			[
				'site_id'          => $this->http_client->get_blog_id(),
				'survey_id'        => 'wcpay-payment-activity',
				'survey_responses' => [
					'rating'        => $rating,
					'comments'      => [ 'text' => $comments ],
					'wcpay-version' => [ 'text' => WCPAY_VERSION_NUMBER ],
				],
			]
		);
		$is_site_specific = true;
		$use_user_token   = true;

		$wpcom_response = $this->http_client->remote_request(
			$request_args,
			$request_body,
			$is_site_specific,
			$use_user_token
		);

		$wpcom_response_status_code = wp_remote_retrieve_response_code( $wpcom_response );

		if ( 200 === $wpcom_response_status_code ) {
			update_option( 'wcpay_survey_payment_overview_submitted', true );
		}

		return new WP_REST_Response( $wpcom_response, $wpcom_response_status_code );
	}

	/**
	 * Verify access.
	 *
	 * Override this method if custom permissions required.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}
}

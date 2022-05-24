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
	protected $rest_base = 'payments/upe_survey';

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
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'submit_survey' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'why-disable' => [
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							'enum' => [
								'slow',
								'buggy',
								'theme-compatibility',
								'missing-features',
								'store-sales',
								'poor-customer-experience',
								'other',
							],
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'comments'    => [
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'wp_filter_nohtml_kses',
					],
				],
			]
		);
	}

	/**
	 * Submits the survey trhough the WPcom API.
	 *
	 * @param WP_REST_Request $request the request being made.
	 *
	 * @return WP_REST_Response
	 */
	public function submit_survey( WP_REST_Request $request ): WP_REST_Response {
		$cancellation_comments = $request->get_param( 'comments' ) ?? '';
		$cancellation_reason   = $request->get_param( 'why-disable' ) ?? '';
		$cancellation_ssr      = $request->get_param( 'ssr' ) ?? '';

		if ( empty( $cancellation_comments ) && empty( $cancellation_reason ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'err'     => 'No answers provided',
				],
				400
			);
		}

		// Jetpack connection 1.27.0 created a default value for this constant, but we're using an older version of the package
		// https://github.com/Automattic/jetpack/blob/master/projects/packages/connection/CHANGELOG.md#1270---2021-05-25
		// - Connection: add the default value of JETPACK__WPCOM_JSON_API_BASE to the Connection Utils class
		// this is just a patch so that we don't need to upgrade.
		// as an alternative, I could have also used the `jetpack_constant_default_value` filter, but this is shorter and also provides a fallback.
		defined( 'JETPACK__WPCOM_JSON_API_BASE' ) || define( 'JETPACK__WPCOM_JSON_API_BASE', 'https://public-api.wordpress.com' );

		$wpcom_request = $this->http_client->wpcom_json_api_request_as_user(
			'/marketing/survey',
			'2',
			[
				'method'  => 'POST',
				'headers' => [
					'Content-Type'    => 'application/json',
					'X-Forwarded-For' => $this->get_current_user_ip(),
				],
			],
			[
				'site_id'          => $this->http_client->get_blog_id(),
				'survey_id'        => 'wcpay-upe-disable-early-access-2022-may',
				'survey_responses' => [
					'why-disable' => $cancellation_reason,
					'comments'    => [ 'text' => $cancellation_comments ],
					'ssr'         => [ 'text' => $cancellation_ssr ],
				],
			]
		);

		$wpcom_request_body = json_decode( wp_remote_retrieve_body( $wpcom_request ) );

		return new WP_REST_Response( $wpcom_request_body, wp_remote_retrieve_response_code( $wpcom_request ) );
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

	/**
	 * Gets current user IP address.
	 *
	 * @return string Current user IP address.
	 */
	public function get_current_user_ip() {
		foreach (
			[
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'HTTP_VIA',
				'REMOTE_ADDR',
			] as $key
		) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				return $_SERVER[ $key ];
			}
		}

		return '';
	}
}

<?php
/**
 * Class WC_REST_Payments_Survey_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use Automattic\Jetpack\Connection\Client;

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
				'args'                => [],
			]
		);
	}

	/**
	 * @param WP_REST_Request $request the request being made.
	 *
	 * @return WP_REST_Response
	 */
	public function submit_survey( WP_REST_Request $request ) {
		$wpcom_request = Client::wpcom_json_api_request_as_user(
			'/marketing/survey',
			'v2',
			array(
				'method'  => 'POST',
				'headers' => array(
					'Content-Type'    => 'application/json',
					'X-Forwarded-For' => $this->get_current_user_ip(),
				),
			),
			$request->get_json_params()
//			[
//				'site_id'          => 999999999,
//				'survey_id'        => 'calypso-disconnect-jetpack-july2019',
//				'survey_responses' => [
//					"purchase"   => "jetpack_personal",
//					"why-cancel" => [ "response" => "other", "text" => "a8c dev test" ],
//					"source"     => [ "from" => "Jetpack-Test" ]
//				]
//			]
		);


		$wpcom_request_body = json_decode( wp_remote_retrieve_body( $wpcom_request ) );

		return new WP_REST_Response( $wpcom_request_body, wp_remote_retrieve_response_code( $wpcom_request ) );
	}

	/**
	 * Verify access.
	 *
	 * Override this method if custom permissions required.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'jetpack_disconnect' );
	}

	/**
	 * Gets current user IP address.
	 *
	 * @return string                  Current user IP address.
	 */
	public function get_current_user_ip() {
		foreach (
			array(
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'HTTP_VIA',
				'REMOTE_ADDR',
			) as $key
		) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				return $_SERVER[ $key ];
			}
		}

		return '';
	}
}

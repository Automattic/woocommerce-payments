<?php
/**
 * Class WC_REST_Payments_Files_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for files.
 */
class WC_REST_Payments_Files_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/file';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_file' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<file_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_file' ],
				'permission_callback' => [],
			]
		);
	}

	/**
	 * Create file and respond with file object via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function upload_file( $request ) {
		return $this->forward_request( 'upload_evidence', [ $request ] );
	}

	/**
	 * Retrieve a file content via API.
	 *
	 * @param WP_REST_Request $request - request object.
	 *
	 * @return WP_Error|WP_HTTP_Response
	 */
	public function get_file( WP_REST_Request $request ) {
		$file_id = $request->get_param( 'file_id' );
		$result  = $this->forward_request( 'get_file_contents', [ $file_id ] );

		if ( $result instanceof WP_Error ) {
			$error_status_code = 'resource_missing' === $result->get_error_code() ? WP_Http::NOT_FOUND : WP_Http::INTERNAL_SERVER_ERROR;
			return rest_ensure_response(
				new WP_Error(
					$result->get_error_code(),
					$result->get_error_messages(),
					[ 'status' => $error_status_code ]
				)
			);
		}

		/**
		 * WP_REST_Server will convert the response data to JSON prior to output it.
		 * Using this filter to prevent it, and output the data from WP_HTTP_Response instead.
		 */
		add_filter(
			'rest_pre_serve_request',
			function ( bool $served, WP_HTTP_Response $response ) : bool {
				echo $response->get_data(); // @codingStandardsIgnoreLine
				return true;
			},
			10,
			2
		);

		return new WP_HTTP_Response(
			base64_decode( $result->get_data()['file_content'] ), // @codingStandardsIgnoreLine
			200,
			[ 'Content-Type' => $result->get_data()['content_type'] ]
		);

	}
}

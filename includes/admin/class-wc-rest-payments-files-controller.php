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
		return $this->forward_request( 'upload_file', [ $request ] );
	}

	/**
	 * Retrieve a file content via API.
	 *
	 * @param WP_REST_Request $request - request object.
	 *
	 * @return WP_Error|WP_HTTP_Response
	 */
	public function get_file( WP_REST_Request $request ) {
		$file_id    = $request->get_param( 'file_id' );
		$as_account = (bool) $request->get_param( 'as_account' );

		$file_service = new WC_Payments_File_Service();
		$purpose      = get_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . $file_id . '_' . ( $as_account ? '1' : '0' ) );

		if ( ! $purpose ) {
			$file = $this->forward_request( 'get_file', [ $file_id, $as_account ] );

			if ( is_wp_error( $file ) ) {
				return $this->file_error_response( $file );
			}
			$purpose = $file->get_data()['purpose'];
			set_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . $file_id, $purpose, WC_Payments_File_Service::CACHE_PERIOD );
		}

		if ( ! $file_service->is_file_public( $purpose ) && ! $this->check_permission() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to do that.', 'woocommerce-payments' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		$result = $this->forward_request( 'get_file_contents', [ $file_id, $as_account ] );

		if ( is_wp_error( $result ) ) {
			return $this->file_error_response( $result );
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
			[
				'Content-Type'        => $result->get_data()['content_type'],
				'Content-Disposition' => 'inline',
			]
		);

	}

	/**
	 * Convert error response
	 *
	 * @param WP_Error $error  - error.
	 *
	 * @return WP_Error
	 */
	private function file_error_response( WP_Error $error ) : WP_Error {
		$error_status_code = 'resource_missing' === $error->get_error_code() ? WP_Http::NOT_FOUND : WP_Http::INTERNAL_SERVER_ERROR;
		return new WP_Error(
			$error->get_error_code(),
			$error->get_error_message(),
			[ 'status' => $error_status_code ]
		);
	}
}

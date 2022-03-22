<?php
/**
 * Class WC_REST_Payments_Documents_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for documents.
 */
class WC_REST_Payments_Documents_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/documents';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<document_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_document' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_documents' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_documents_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve and serve a document for API requests.
	 * This method serves the document directly and halts execution, skipping the REST return
	 * and preventing additional data to be sent.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_document( $request ) {
		$document_id = $request->get_param( 'document_id' );

		$response = $this->api_client->get_document( $document_id );

		// Set the headers to match what was returned from the server.
		if ( ! headers_sent() ) {
			nocache_headers();
			status_header( $response['response']['code'], $response['response']['message'] ?? '' );
			header( 'Content-Type: ' . $response['headers']['content-type'] );
			header( 'Content-Disposition: ' . $response['headers']['content-disposition'] ?? '' );
		}

		// We should output the server's file without escaping.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $response['body'];

		exit;
	}

	/**
	 * Retrieve documents to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_documents( $request ) {
		$page      = (int) $request->get_param( 'page' );
		$page_size = (int) $request->get_param( 'pagesize' );
		$sort      = $request->get_param( 'sort' );
		$direction = $request->get_param( 'direction' );
		$filters   = $this->get_documents_filters( $request );
		return $this->forward_request( 'list_documents', [ $page, $page_size, $sort, $direction, $filters ] );
	}

	/**
	 * Retrieve documents summary to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_documents_summary( $request ) {
		$filters = $this->get_documents_filters( $request );
		return $this->forward_request( 'get_documents_summary', [ $filters ] );
	}

	/**
	 * Extract documents filters from request
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	private function get_documents_filters( $request ) {
		return array_filter(
			[
				'match'        => $request->get_param( 'match' ),
				'date_before'  => $request->get_param( 'date_before' ),
				'date_after'   => $request->get_param( 'date_after' ),
				'date_between' => $request->get_param( 'date_between' ),
				'type_is'      => $request->get_param( 'type_is' ),
				'type_is_not'  => $request->get_param( 'type_is_not' ),
			],
			static function ( $filter ) {
				return null !== $filter;
			}
		);
	}
}

<?php
/**
 * Class WC_REST_Payments_Documents_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\List_Documents;
use WCPay\Exceptions\API_Exception;

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
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<document_id>[\w-]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_document' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve documents to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_documents( $request ) {
		$wcpay_request = List_Documents::from_rest_request( $request );
		return $wcpay_request->handle_rest_request();
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
	 * Retrieve and serve a document for API requests.
	 * This method serves the document directly and halts execution, skipping the REST return
	 * and preventing additional data to be sent.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_document( $request ) {
		$response    = [];
		$document_id = $request->get_param( 'document_id' );

		try {
			$response = $this->api_client->get_document( $document_id );
		} catch ( API_Exception $e ) {
			$message = sprintf(
				/* translators: %1: The document ID. %2: The error message.*/
				esc_html__( 'There was an error accessing document %1$s. %2$s', 'woocommerce-payments' ),
				$document_id,
				$e->getMessage()
			);
			wp_die( esc_html( $message ), '', (int) $e->get_http_code() );
		}

		// WooCommerce core only includes Tracks in admin, not the REST API, so we need to use this wc_admin method
		// that includes WC_Tracks in case it's not loaded.
		if ( function_exists( 'wc_admin_record_tracks_event' ) ) {
			wc_admin_record_tracks_event(
				'wcpay_document_downloaded',
				[
					'document_id' => $document_id,
					'mode'        => WC_Payments::mode()->is_test() ? 'test' : 'live',
				]
			);
		}

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

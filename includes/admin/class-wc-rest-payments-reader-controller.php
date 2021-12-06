<?php
/**
 * Class WC_REST_Payments_Reader_Charges
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for reader charges.
 */
class WC_REST_Payments_Reader_Controller extends WC_Payments_REST_Controller {
	const STORE_READERS_TRANSIENT_KEY = 'wcpay_store_terminal_readers';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/readers';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_readers' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/charges/(?P<transaction_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve payment readers charges to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function get_summary( $request ) {

		$transaction_id = $request->get_param( 'transaction_id' );

		try {
			// retrieve transaction details to get the charge date.
			$transaction = $this->api_client->get_transaction( $transaction_id );

			if ( empty( $transaction ) ) {
				return rest_ensure_response( [] );
			}
			$summary = $this->api_client->get_readers_charge_summary( gmdate( 'Y-m-d', $transaction['created'] ) );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'wcpay_get_summary', $e->getMessage() ) );
		}

		return rest_ensure_response( $summary );
	}

	/**
	 * Proxies the get all readers request to the server.
	 *
	 * @param WP_REST_REQUEST $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_all_readers( $request ) {
		try {
			return rest_ensure_response( $this->fetch_readers() );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Check if the reader status is active
	 *
	 * @param array  $readers The readers charges object.
	 * @param string $id      The reader ID.
	 * @return bool
	 */
	private function is_reader_active( $readers, $id ) {
		foreach ( $readers as $reader ) {
			if ( $reader['reader_id'] === $id && 'active' === $reader['status'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Attempts to read readers from transient and re-populates it if needed.
	 *
	 * @return array         Terminal readers.
	 * @throws API_Exception If request to server fails.
	 */
	private function fetch_readers(): array {
		$readers = get_transient( static::STORE_READERS_TRANSIENT_KEY );

		if ( ! $readers ) {
			// Retrieve terminal readers.
			$readers_data = $this->api_client->get_terminal_readers();

			// Retrieve the readers by charges.
			$reader_by_charges = $this->api_client->get_readers_charge_summary( gmdate( 'Y-m-d', time() ) );

			$readers = [];
			foreach ( $readers_data as $reader ) {
				$readers[] = [
					'id'          => $reader['id'],
					'livemode'    => $reader['livemode'],
					'device_type' => $reader['device_type'],
					'label'       => $reader['label'],
					'location'    => $reader['location'],
					'metadata'    => $reader['metadata'],
					'status'      => $reader['status'],
					'is_active'   => $this->is_reader_active( $reader_by_charges, $reader['id'] ),
				];
			}

			set_transient( static::STORE_READERS_TRANSIENT_KEY, $readers, 2 * HOUR_IN_SECONDS );
		}

		return $readers;
	}

}

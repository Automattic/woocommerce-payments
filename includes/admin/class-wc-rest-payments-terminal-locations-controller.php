<?php
/**
 * Class WC_REST_Payments_Terminal_Locations_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Exceptions\API_Exception;
/**
 * REST controller for account details and status.
 */
class WC_REST_Payments_Terminal_Locations_Controller extends WC_Payments_REST_Controller {

	const STORE_LOCATIONS_TRANSIENT_KEY = 'wcpay_store_terminal_locations';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/terminal/locations';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/store',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_store_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<location_id>\w+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<location_id>\w+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'display_name' => [
						'type'     => 'string',
						'required' => false,
					],
					'address'      => [
						'type'     => 'object',
						'required' => false,
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<location_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_location' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'display_name' => [
						'type'     => 'string',
						'required' => true,
					],
					'address'      => [
						'type'     => 'object',
						'required' => true,
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_locations' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Get store terminal location.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_store_location( $request ) {
		$store_address    = WC()->countries;
		$location_address = array_filter(
			[
				'city'        => $store_address->get_base_city(),
				'country'     => $store_address->get_base_country(),
				'line1'       => $store_address->get_base_address(),
				'line2'       => $store_address->get_base_address_2(),
				'postal_code' => $store_address->get_base_postcode(),
				'state'       => $store_address->get_base_state(),
			]
		);

		// If address is not populated, emit an error and specify the URL where this can be done.
		// See also https://tosbourn.com/list-of-countries-without-a-postcode/ when launching in new countries.
		$is_address_populated = isset( $location_address['country'], $location_address['city'], $location_address['postal_code'], $location_address['line1'] );
		if ( ! $is_address_populated ) {
			return rest_ensure_response(
				new \WP_Error(
					'store_address_is_incomplete',
					admin_url(
						add_query_arg(
							[
								'page' => 'wc-settings',
								'tab'  => 'general',
							],
							'admin.php'
						)
					)
				)
			);
		}

		try {
			// Check the existing locations to see if one of them matches the store.
			// Originally we picked `get_bloginfo` for generating names, but later switched to `site_url` for max immutability.
			$store_hostname = str_replace( [ 'https://', 'http://' ], '', get_site_url() );
			$possible_names = [ get_bloginfo(), $store_hostname ];
			foreach ( $this->fetch_locations() as $location ) {
				if ( in_array( $location['display_name'], $possible_names, true ) ) {
					$matching_address_fields = array_intersect( $location['address'], $location_address );
					if ( count( $matching_address_fields ) === count( $location_address ) ) {
						return rest_ensure_response( $this->extract_location_fields( $location ) );
					}
				}
			}

			// If the location is missing, Create a new one and actualize the transient.
			$location = $this->api_client->create_terminal_location( $store_hostname, $location_address );
			$this->reload_locations();

			return rest_ensure_response( $this->extract_location_fields( $location ) );
		} catch ( API_Exception $e ) {
			$error = new WP_Error( $e->get_error_code(), $e->getMessage() );
			// Stripe will return a 400 for incorrect city, state, or country. Ideally, we should return
			// a more appropriate error code like 'store_address_is_incorrect', but that will break older mobile app clients.
			// Until we have a more granular versioning support for WCPay REST endpoints, this is the best we can do.
			if ( 'invalid_request_error' === $e->get_error_code() ) {
				$error = new WP_Error(
					'store_address_is_incomplete',
					admin_url(
						add_query_arg(
							[
								'page' => 'wc-settings',
								'tab'  => 'general',
							],
							'admin.php'
						)
					)
				);
			}
			return rest_ensure_response( $error );
		}
	}

	/**
	 * Proxies the delete location request to the server.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_location( $request ) {
		try {
			// Delete the location and reload the transient.
			$location = $this->api_client->delete_terminal_location( $request->get_param( 'location_id' ) );
			$this->reload_locations();

			return rest_ensure_response( $location );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Proxies the update location request to the server.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_location( $request ) {
		try {
			// Update the location and reload the transient.
			$location = $this->api_client->update_terminal_location( $request->get_param( 'location_id' ), $request['display_name'], $request['address'] );
			$this->reload_locations();

			return rest_ensure_response( $this->extract_location_fields( $location ) );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Proxies the get location request to the server.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_location( $request ) {
		try {
			// Check if the location is already in the transient.
			$location_id = $request->get_param( 'location_id' );
			foreach ( $this->fetch_locations() as $location ) {
				if ( $location['id'] === $location_id ) {
					return rest_ensure_response( $this->extract_location_fields( $location ) );
				}
			}

			// If the location is missing, fetch it individually and reload the transient.
			$location = $this->api_client->get_terminal_location( $location_id );
			$this->reload_locations();

			return rest_ensure_response( $this->extract_location_fields( $location ) );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Proxies the create location request to the server.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_location( $request ) {
		try {
			// Create location and reload the transient.
			$location = $this->api_client->create_terminal_location( $request['display_name'], $request['address'] );
			$this->reload_locations();

			return rest_ensure_response( $this->extract_location_fields( $location ) );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Proxies the get all locations request to the server.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_all_locations( $request ) {
		try {
			return rest_ensure_response( array_map( [ $this, 'extract_location_fields' ], $this->fetch_locations() ) );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Extracts the relevant fields from a terminal location object.
	 *
	 * @param array $location The location.
	 * @return array          The picked fields from location object.
	 */
	private function extract_location_fields( array $location ): array {
		return [
			'id'           => $location['id'],
			'address'      => $location['address'],
			'display_name' => $location['display_name'],
			'livemode'     => $location['livemode'],
		];
	}

	/**
	 * Attempts to read locations from transient and re-populates it if needed.
	 *
	 * @return array         Terminal locations.
	 * @throws API_Exception If request to server fails.
	 */
	private function fetch_locations(): array {
		$locations = get_transient( static::STORE_LOCATIONS_TRANSIENT_KEY );
		if ( ! $locations ) {
			$locations = $this->api_client->get_terminal_locations();
			set_transient( static::STORE_LOCATIONS_TRANSIENT_KEY, $locations, DAY_IN_SECONDS );
		}

		return $locations;
	}

	/**
	 * Refreshes the locations stored in transient.
	 *
	 * @return void
	 * @throws API_Exception If request to server fails.
	 */
	private function reload_locations() {
		$locations = $this->api_client->get_terminal_locations();
		set_transient( static::STORE_LOCATIONS_TRANSIENT_KEY, $locations, DAY_IN_SECONDS );
	}
}

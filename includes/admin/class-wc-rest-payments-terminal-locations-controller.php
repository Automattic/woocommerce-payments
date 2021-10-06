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
					'display_name',
					'address',
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
		$is_address_unpopulated = 2 === count( $location_address ) && isset( $location_address['country'], $location_address['state'] );
		if ( $is_address_unpopulated ) {
			return rest_ensure_response(
				new \WP_Error(
					'store_address_is_incomplete',
					__( 'The store address is incomplete, please update your settings.', 'woocommerce-payments' ),
					[
						'url' => admin_url(
							add_query_arg(
								[
									'page' => 'wc-settings',
									'tab'  => 'general',
								],
								'admin.php'
							)
						),
					]
				)
			);
		}

		try {
			// Load the cached locations or retrieve them from the API.
			$locations = get_transient( static::STORE_LOCATIONS_TRANSIENT_KEY );
			if ( ! $locations ) {
				$locations = $this->api_client->get_terminal_locations();
				set_transient( static::STORE_LOCATIONS_TRANSIENT_KEY, $locations, DAY_IN_SECONDS );
			}

			// Check the existing locations to see if one of them matches the store.
			$name = get_bloginfo();
			foreach ( $locations as $location ) {
				if (
					$location['display_name'] === $name
					&& count( array_intersect( $location['address'], $location_address ) ) === count( $location_address )
				) {
					return $this->format_location_response( $location );
				}
			}

			// Create a new location, and add it to the cached list.
			$location    = $this->api_client->create_terminal_location( $name, $location_address );
			$locations[] = $location;
			set_transient( static::STORE_LOCATIONS_TRANSIENT_KEY, $locations, DAY_IN_SECONDS );

			return $this->format_location_response( $location );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Formats a Stripe terminal location object into a correct response.
	 *
	 * @param array $location The location.
	 * @return WP_REST_Response
	 */
	private function format_location_response( $location ) {
		return rest_ensure_response( $this->extract_location_fields( $location ) );
	}

	/**
	 * Extracts the relevant fields from a terminal location object.
	 *
	 * @param array $location The location.
	 * @return array The picked fields from location object.
	 */
	private function extract_location_fields( $location ) {
		return [
			'id'           => $location['id'],
			'address'      => $location['address'],
			'display_name' => $location['display_name'],
			'livemode'     => $location['livemode'],
		];
	}

	/**
	 * Proxies the delete location request to the server.
	 *
	 * @param WP_REST_REQUEST $request Request object.
	 *
	 * @throws API_Exception - If the downstream call fails.
	 */
	public function delete_location( $request ) {
		try {
			$deletion_response = $this->api_client->delete_terminal_location(
				$request->get_param( 'location_id' )
			);

			// Delete the transient in case the delete call goes through to avoid caching side effects.
			if ( true === $deletion_response['deleted'] ?? false ) {
				delete_transient( self::STORE_LOCATIONS_TRANSIENT_KEY );
			}

			return $deletion_response;
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}

	}

	/**
	 * Proxies the update location request to the server.
	 *
	 * @param WP_REST_REQUEST $request Request object.
	 *
	 * @throws API_Exception - If the downstream call fails.
	 */
	public function update_location( $request ) {
		try {
			$location_id  = $request->get_param( 'location_id' );
			$display_name = $request['display_name'];
			$address      = $request['address'];

			$updation_response = $this->api_client->update_terminal_location( $location_id, $display_name, $address );

			// Delete the transient in case the update call goes through to avoid caching side effects.
			delete_transient( self::STORE_LOCATIONS_TRANSIENT_KEY );

			return $this->format_location_response( $updation_response );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Proxies the get location request to the server.
	 *
	 * @param WP_REST_REQUEST $request Request object.
	 *
	 * @throws API_Exception - If the downstream call fails.
	 */
	public function get_location( $request ) {
		try {
			$locations   = get_transient( static::STORE_LOCATIONS_TRANSIENT_KEY );
			$location_id = $request->get_param( 'location_id' );

			// Check if the location exists in cache before making the request.
			if ( $locations ) {
				foreach ( $locations as $location ) {
					if ( $location['id'] === $location_id ) {
						return $this->format_location_response( $location );
					}
				}
			}

			// Fetch the location from server and update the cache to include it.
			$location    = $this->api_client->get_terminal_location( $location_id );
			$locations[] = $location;
			set_transient( static::STORE_LOCATIONS_TRANSIENT_KEY, $locations, DAY_IN_SECONDS );

			return $this->format_location_response( $location );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Proxies the create location request to the server.
	 *
	 * @param WP_REST_REQUEST $request Request object.
	 *
	 * @throws API_Exception - If the downstream call fails.
	 */
	public function create_location( $request ) {
		try {
			$display_name = $request['display_name'];
			$address      = $request['address'];

			$location = $this->api_client->create_terminal_location( $display_name, $address );

			// Update the transient with the newly created location.
			$locations   = get_transient( static::STORE_LOCATIONS_TRANSIENT_KEY );
			$locations[] = $location;
			set_transient( static::STORE_LOCATIONS_TRANSIENT_KEY, $locations, DAY_IN_SECONDS );

			return $this->format_location_response( $location );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}

	/**
	 * Proxies the get all locations request to the server.
	 *
	 * @param WP_REST_REQUEST $request Request object.
	 *
	 * @throws API_Exception - If the downstream call fails.
	 */
	public function get_all_locations( $request ) {
		try {
			// Check if locations are cached already and return them if present.
			$locations = get_transient( static::STORE_LOCATIONS_TRANSIENT_KEY );

			// Fetch from downstream in case of a cache miss and update cache for subsequent calls.
			if ( ! $locations ) {
				$locations = $this->api_client->get_terminal_locations();
				set_transient( static::STORE_LOCATIONS_TRANSIENT_KEY, $locations, DAY_IN_SECONDS );
			}

			// Format the response to pick the required fields.
			$formatted_location_response = [];
			foreach ( $locations as $location ) {
				$formatted_location_response[] = $this->extract_location_fields( $location );
			}

			return rest_ensure_response( $formatted_location_response );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( $e->get_error_code(), $e->getMessage() ) );
		}
	}
}

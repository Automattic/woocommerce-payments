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
	}

	/**
	 * Get store terminal location.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_store_location( $request ) {
		try {
			$name    = get_bloginfo();
			$address = [
				'city'        => WC()->countries->get_base_city(),
				'country'     => WC()->countries->get_base_country(),
				'line1'       => WC()->countries->get_base_address(),
				'line2'       => WC()->countries->get_base_address_2(),
				'postal_code' => WC()->countries->get_base_postcode(),
				'state'       => WC()->countries->get_base_state(),
			];

			// Load the cached locations or retrieve them from the API.
			$locations = get_transient( static::STORE_LOCATIONS_TRANSIENT_KEY );
			if ( ! $locations ) {
				$locations = $this->api_client->get_terminal_locations();
				set_transient( static::STORE_LOCATIONS_TRANSIENT_KEY, $locations, DAY_IN_SECONDS );
			}

			// Check the existing locations to see if one of them matches the store.
			foreach ( $locations as $location ) {
				if (
					$location['display_name'] === $name
					&& count( array_intersect( $location['address'], $address ) ) === count( $address )
				) {
					return $this->format_location_response( $location );
				}
			}

			// Create a new location, and add it to the cached list.
			$location    = $this->api_client->create_terminal_location( $name, $address );
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
		return rest_ensure_response(
			[
				'id'           => $location['id'],
				'address'      => $location['address'],
				'display_name' => $location['display_name'],
				'livemode'     => $location['livemode'],
			]
		);
	}
}

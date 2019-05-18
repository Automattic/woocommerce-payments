<?php
/**
 * WC_Payments_API_Client class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Communicates with WooCommerce Payments API.
 */
class WC_Payments_API_Client {

	const ENDPOINT = 'https://public-api.wordpress.com/wpcom/v2/wcpay/';

	const POST = 'POST';
	const GET  = 'GET';

	const CHARGES_API = 'charges';
	const SOURCES_API = 'sources';

	/**
	 * Create a charge
	 *
	 * @param int    $amount    - Amount to charge.
	 * @param string $source_id - ID of the source to associate with charge.
	 *
	 * @return WC_Payments_API_Charge
	 * @throws Exception - Exception thrown on payment failure.
	 */
	public function create_charge( $amount, $source_id ) {

		$request           = array();
		$request['amount'] = $amount;
		$request['source'] = $source_id;

		$response_array = $this->request( $request, self::CHARGES_API, self::POST );

		return $this->deserialize_charge_object_from_array( $response_array );
	}

	/**
	 * Send the request to the WooCommerce Payment API
	 *
	 * @param array  $request - Details of the request to make.
	 * @param string $api     - The API endpoint to call.
	 * @param string $method  - The HTTP method to make the request with.
	 *
	 * @return array
	 */
	private function request( $request, $api, $method ) {

		// TODO: Send the request to the API.
		// Mock up a response for now.
		$response            = array();
		$response['id']      = wp_generate_uuid4();
		$response['amount']  = $request['amount'];
		$response['created'] = time();

		return $response;
	}

	/**
	 * De-serialize a charge array into a charge object
	 *
	 * @param array $charge_array - The charge array to de-serialize.
	 *
	 * @return WC_Payments_API_Charge
	 * @throws Exception - Unable to deserialize charge array.
	 */
	private function deserialize_charge_object_from_array( array $charge_array ) {
		// TODO: Throw an exception if the response array doesn't contain mandatory properties.
		$created = new DateTime( '@' . $charge_array['created'] );

		$charge = new WC_Payments_API_Charge(
			$charge_array['id'],
			$charge_array['amount'],
			$created
		);

		if ( isset( $charge_array['captured'] ) ) {
			$charge->set_captured( $charge_array['captured'] );
		}

		return $charge;
	}
}

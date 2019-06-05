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

	const ENDPOINT = 'https://public-api.wordpress.com/wpcom/v2/wcpay';

	const POST = 'POST';
	const GET  = 'GET';

	const CHARGES_API      = 'charges';
	const INTENTIONS_API   = 'intentions';
	const TRANSACTIONS_API = 'balance/history';

	/**
	 * User agent string to report in requests.
	 *
	 * @var string
	 */
	private $user_agent;

	/**
	 * The ID of the Stripe account this client will be used for.
	 *
	 * @var string
	 */
	private $account_id;

	/**
	 * WC_Payments_API_Client constructor.
	 *
	 * @param string $user_agent     - User agent string to report in requests.
	 */
	public function __construct( $user_agent ) {
		$this->user_agent = $user_agent;
	}

	/**
	 * Set the account ID to use for requests to the API.
	 *
	 * @param string $account_id - The ID of the Stripe account this client will be used for.
	 */
	public function set_account_id( $account_id ) {
		$this->account_id = $account_id;
	}

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
	 * Create an intention
	 *
	 * @param int    $amount        - Amount to charge.
	 * @param string $currency_code - Currency to charge in.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws Exception - Exception thrown on intention creation failure.
	 */
	public function create_intention( $amount, $currency_code ) {
		// TODO: There's scope to have amount and currency bundled up into an object.
		$request             = array();
		$request['amount']   = $amount;
		$request['currency'] = $currency_code;

		$response_array = $this->request( $request, self::INTENTIONS_API, self::POST );

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Confirm an intention
	 *
	 * @param WC_Payments_API_Intention $intent            - The intention to confirm.
	 * @param string                    $payment_method_id - ID of payment method to process charge with.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws Exception - Exception thrown on intention confirmation failure.
	 */
	public function confirm_intention( WC_Payments_API_Intention $intent, $payment_method_id ) {
		$request                   = array();
		$request['payment_method'] = $payment_method_id;

		$response_array = $this->request(
			$request,
			self::INTENTIONS_API . '/' . $intent->get_id() . '/confirm',
			self::POST
		);

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * List transactions
	 *
	 * @return array
	 * @throws Exception - Exception thrown on request failure.
	 */
	public function list_transactions() {
		return $this->request( array(), self::TRANSACTIONS_API, self::GET );
	}

	/**
	 * Send the request to the WooCommerce Payment API
	 *
	 * @param array  $request - Details of the request to make.
	 * @param string $api     - The API endpoint to call.
	 * @param string $method  - The HTTP method to make the request with.
	 *
	 * @return array
	 * @throws Exception - If the account ID hasn't been set.
	 */
	private function request( $request, $api, $method ) {
		// Add account ID to the request.
		if ( ! isset( $this->account_id ) ) {
			throw new Exception( __( 'Account ID must be set', 'woocommerce-payments' ) );
		}
		$request['account_id'] = $this->account_id;

		// Build the URL we want to send the URL to.
		$url = self::ENDPOINT . '/' . $api;
		$body = null;

		if ( self::GET === $method ) {
			$url .= '?' . http_build_query( $request );
		} else {
			// Encode the request body as JSON.
			$body = wp_json_encode( $request );
			if ( ! $body ) {
				throw new Exception(
					__( 'Unable to encode body for request to WooCommerce Payments API.', 'woocommerce-payments' )
				);
			}
		}

		// Create standard headers.
		$headers                 = array();
		$headers['Content-Type'] = 'application/json; charset=utf-8';
		$headers['User-Agent']   = $this->user_agent;

		// TODO: Either revamp this auth before releasing WCPay, or properly check that Jetpack is installed & connected.
		$response = Jetpack_Client::remote_request(
			array(
				'url'     => $url,
				'method'  => $method,
				'headers' => $headers,
				'blog_id' => Jetpack_Options::get_option( 'id' ),
				'user_id' => JETPACK_MASTER_USER,
			),
			$body
		);

		// Extract the response body and decode it from JSON into an array.
		$response_body_json = wp_remote_retrieve_body( $response );

		$response_body = json_decode( $response_body_json, true );
		if ( null === $response_body ) {
			throw new Exception(
				__( 'Unable to decode response from WooCommerce Payments API', 'woocommerce-payments' )
			);
		}

		// Check the response code and handle any errors.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			// TODO: Handle non-200 codes better.
			throw new Exception( __( 'Server Error.', 'woocommerce-payments' ) );
		}

		return $response_body;
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
		$created = new DateTime();
		$created->setTimestamp( $charge_array['created'] );

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

	/**
	 * De-serialize an intention array into a intention object
	 *
	 * @param array $intention_array - The intention array to de-serialize.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws Exception - Unable to deserialize intention array.
	 */
	private function deserialize_intention_object_from_array( array $intention_array ) {
		// TODO: Throw an exception if the response array doesn't contain mandatory properties.
		$created = new DateTime();
		$created->setTimestamp( $intention_array['created'] );

		$intent = new WC_Payments_API_Intention(
			$intention_array['id'],
			$intention_array['amount'],
			$created,
			$intention_array['status']
		);

		return $intent;
	}
}

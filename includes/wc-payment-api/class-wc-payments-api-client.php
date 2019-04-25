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

	const CHARGES_API         = 'charges';
	const SOURCES_API         = 'sources';
	const PAYMENT_INTENTS_API = 'payment_intents';

	/**
	 * The ID of the Stripe account this client will be used for.
	 *
	 * @var string
	 */
	private $account_id;

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
	 * Create a payment intent
	 *
	 * @param int    $amount        - Amount to charge.
	 * @param string $currency_code - Currency to charge in.
	 *
	 * @return WC_Payments_API_Payment_Intent
	 * @throws Exception - Exception thrown on payment intent creation failure.
	 */
	public function create_payment_intent( $amount, $currency_code ) {
		// TODO: There's scope to have amount and currency bundled up into an object.
		$request             = array();
		$request['amount']   = $amount;
		$request['currency'] = $currency_code;

		$response_array = $this->request( $request, self::PAYMENT_INTENTS_API, self::POST );

		return $this->deserialize_payment_intent_object_from_array( $response_array );
	}

	/**
	 * Confirm a payment intent
	 *
	 * @param WC_Payments_API_Payment_Intent $intent    - The payment intent to confirm.
	 * @param string                         $source_id - ID of source to process charge with.
	 *
	 * @return WC_Payments_API_Payment_Intent
	 * @throws Exception - Exception thrown on payment intent confirmation failure.
	 */
	public function confirm_payment_intent( WC_Payments_API_Payment_Intent $intent, $source_id ) {
		$request           = array();
		$request['source'] = $source_id;

		$response_array = $this->request(
			$request,
			self::PAYMENT_INTENTS_API . '/' . $intent->get_id() . '/confirm',
			self::POST
		);

		return $this->deserialize_payment_intent_object_from_array( $response_array );
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

		// TODO: Send the request to the API.
		// Add account ID to the request.
		if ( ! isset( $this->account_id ) ) {
			throw new Exception( __( 'Account ID must be set', 'woocommerce-payments' ) );
		}
		$request['account_id'] = $this->account_id;

		// Mock up a response for now.
		$response            = array();
		$response['id']      = wp_generate_uuid4();
		$response['amount']  = $request['amount'];
		$response['created'] = time();
		$response['status']  = 'success';

		// TODO: Handle error responses by throwing exceptions.
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

	/**
	 * De-serialize a payment intent array into a payment intent object
	 *
	 * @param array $payment_intent_array - The payment intent array to de-serialize.
	 *
	 * @return WC_Payments_API_Payment_Intent
	 * @throws Exception - Unable to deserialize payment intent array.
	 */
	private function deserialize_payment_intent_object_from_array( array $payment_intent_array ) {
		// TODO: Throw an exception if the response array doesn't contain mandatory properties.
		$created = new DateTime( '@' . $payment_intent_array['created'] );

		$intent = new WC_Payments_API_Payment_Intent(
			$payment_intent_array['id'],
			$payment_intent_array['amount'],
			$created,
			$payment_intent_array['status']
		);

		return $intent;
	}
}

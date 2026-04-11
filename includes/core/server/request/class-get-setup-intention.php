<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Setup_Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Setup_Intention;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting setup intents.
 */
class Get_Setup_Intention extends Request {

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_get_setup_intent_request';

	/**
	 * Sets the intent ID, which will be used in the request URL.
	 *
	 * @param string $setup_intent_id Sets the setup intent ID, which will be used in the request URL.
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	protected function set_id( string $setup_intent_id ) {
		$this->validate_stripe_id( $setup_intent_id );
		$this->id = $setup_intent_id;
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::SETUP_INTENTS_API . '/' . $this->id;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * Formats the response from the server.
	 *
	 * @param  mixed $response The response from `WC_Payments_API_Client::request`.
	 * @return WC_Payments_API_Setup_Intention Either the same response, or the correct object.
	 */
	public function format_response( $response ) {
		return $this->api_client->deserialize_setup_intention_object_from_array( $response );
	}
}

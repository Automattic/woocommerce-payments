<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Setup_Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_Http_Interface;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting setup intents.
 */
class Get_Setup_Intention extends Request {
	/**
	 * Setup intent id.
	 *
	 * @var string $setup_intent_id
	 */
	private $setup_intent_id;

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_API_Client     $api_client Api client.
	 * @param WC_Payments_Http_Interface $http_interface Http interface.
	 * @param string                     $setup_intent_id Setup intent id.
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payments_Http_Interface $http_interface, string $setup_intent_id ) {
		$this->validate_stripe_id( $setup_intent_id );
		parent::__construct( $api_client, $http_interface );
		$this->setup_intent_id = $setup_intent_id;
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::SETUP_INTENTS_API . '/' . $this->setup_intent_id;
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
	 * @return mixed           Either the same response, or the correct object.
	 */
	public function format_response( $response ) {
		return $this->api_client->deserialize_setup_intention_object_from_array( $response );
	}
}

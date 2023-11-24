<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Intent.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;
use WC_Payments_Http_Interface;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting intents.
 */
class Get_Intention extends Request {
	/**
	 * Intent id.
	 *
	 * @var string $intent_id
	 */
	private $intent_id;

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_get_intent_request';

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_API_Client     $api_client Api client.
	 * @param WC_Payments_Http_Interface $http_interface Http interface.
	 * @param string                     $intent_id Intent id.
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payments_Http_Interface $http_interface, string $intent_id ) {
		$this->validate_stripe_id( $intent_id );
		parent::__construct( $api_client, $http_interface );
		$this->intent_id = $intent_id;
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::INTENTIONS_API . '/' . $this->intent_id;
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
		return $this->api_client->deserialize_payment_intention_object_from_array( $response );
	}
}

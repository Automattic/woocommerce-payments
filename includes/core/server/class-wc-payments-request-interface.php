<?php

namespace WCPay\Core\Server;

use WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception;
use WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;

/**
 * Base for requests to the WCPay server.
 */
interface WC_Payments_Request_Interface {
	/**
	 * Allows the request to be modified, and then sends it.
	 *
	 * @return mixed               Either the response array, or the correct object.
	 *
	 * @throws Extend_Request_Exception
	 * @throws Immutable_Parameter_Exception
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function send();

	/**
	 * Formats the response from the server.
	 *
	 * @param mixed $response The response from `WC_Payments_API_Client::request`.
	 *
	 * @return mixed           Either the same response, or the correct object.
	 */
	public function format_response( $response );
	/**
	 * This is mimic of send method, but where API execption is handled.
	 * The reason behind this is that sometimes API request can fail for valid reasons and instead of handling this exception on every request, you could use this function.
	 *
	 * @return mixed               Either the response array, or the correct object.
	 *
	 * @throws Extend_Request_Exception
	 * @throws Immutable_Parameter_Exception
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function handle_rest_request();
}

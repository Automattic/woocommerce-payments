<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Account.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting intents.
 */
class Get_Account extends Request {
	use Use_Test_Mode_Only_When_Dev_Mode;

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::ACCOUNTS_API;
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
	 * @return mixed           The same response from REST API.
	 */
	public function format_response( $response ) {
		return $response;
	}
}

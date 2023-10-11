<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Payment_Process_Factors.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting routing data for the new payment process.
 */
class Get_Payment_Process_Factors extends Request {
	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::PAYMENT_PROCESS_CONFIG_API . '/factors';
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
	}
}

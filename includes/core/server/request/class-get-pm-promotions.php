<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_PM_Promotions.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;

/**
 * GET WCPay payment method (PM) promotions from the Transact Platform.
 */
class Get_PM_Promotions extends Request {

	/**
	 * Get API route.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::PROMOTIONS_API;
	}

	/**
	 * Get method.
	 *
	 * @return string
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * We want the raw response so we can look at the response headers.
	 *
	 * @return bool
	 */
	public function should_return_raw_response(): bool {
		return true;
	}
}

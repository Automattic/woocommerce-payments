<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Fraud_Services.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting public fraud services config.
 */
class Get_Fraud_Services extends Request {
	use Use_Test_Mode_Only_When_Dev_Mode;

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_get_fraud_services';

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::FRAUD_SERVICES_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
	}
}

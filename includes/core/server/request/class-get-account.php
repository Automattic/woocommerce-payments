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
 * Request class for getting account data.
 */
class Get_Account extends Request {
	use Use_Test_Mode_Only_When_Dev_Mode;

	/**
	 * Used to set WordPress filter hook that will be executed when send() function is called.
	 *
	 * @var string $hook
	 */
	protected $hook = 'wcpay_get_account';

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
}

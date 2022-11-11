<?php
/**
 * Class file for WCPay\Core\Server\Request.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server;

/**
 * Base for requests to the WCPay server.
 */
abstract class Request {
	/**
	 * Holds the parameters of the request.
	 *
	 * @var mixed[]
	 */
	protected $params = [];

	/**
	 * Prevents the class from being constructed directly.
	 */
	protected function __construct() {
		// Nothing to do here yet.
	}

	/**
	 * Returns the needed API.
	 *
	 * @return string Check WCPay\Core\Server\APIs.
	 */
	abstract public function get_api(): string;

	/**
	 * Returns the method of the request.
	 *
	 * @return string See the constants in WordPress's `Requests` class.
	 */
	abstract public function get_method(): string;

	/**
	 * This is a legacy method, and is the same throughout the codebase.
	 * Might be worth removing while refactoring to use the Core\Server API.
	 *
	 * @return bool
	 */
	public function is_site_specific(): bool {
		return false;
	}

	/**
	 * If true, the request will be signed with the user token rather than blog token. Defaults to false.
	 *
	 * @return bool
	 */
	public function use_user_token(): bool {
		return false;
	}

	/**
	 * Indicates if the raw response should be returned.
	 *
	 * @return bool
	 */
	public function should_return_raw_response(): bool {
		return false;
	}
}

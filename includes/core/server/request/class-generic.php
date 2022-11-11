<?php
/**
 * Class file for WCPay\Core\Server\Request\Generic.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Server\Request;

/**
 * Generic WCPay Server Request.
 *
 * This class is not extendable, if you need something specific,
 * it will not be generic anymore, create your own.
 */
final class Generic extends Request {
	/**
	 * The request's API.
	 *
	 * @var string Check WCPay\Core\Server\APIs.
	 */
	private $api;

	/**
	 * The method of the request.
	 *
	 * @var string See the constants in WordPress's `Requests` class.
	 */
	private $method;

	/**
	 * If true, the request will be signed with the user token rather than blog token. Defaults to false.
	 *
	 * @var bool
	 */
	private $should_use_user_token = false;

	/**
	 * Instantiates the request object.
	 *
	 * @param string $api        The API to use. See WCPay\Core\Server\APIs.
	 * @param string $method     The request method. See the `Requests` class.
	 * @param array  $parameters The parameters for the request.
	 */
	public function __construct( $api, $method, $parameters = null ) {
		$this->api    = $api; // ToDo: Verify.
		$this->method = $method; // ToDo: Verify.

		if ( $parameters ) {
			// We'll get to that later.
		}
	}

	/**
	 * Returns the needed API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return $this->api;
	}

	/**
	 * Returns the method of the request.
	 *
	 * @return string
	 */
	public function get_method(): string {
		return $this->method;
	}

	/**
	 * If true, the request will be signed with the user token rather than blog token. Defaults to false.
	 *
	 * @return bool
	 */
	public function should_use_user_token(): bool {
		return $this->should_use_user_token;
	}

	/**
	 * Sets the request to use the user token.
	 *
	 * @return void
	 */
	public function use_user_token() {
		$this->should_use_user_token = true;
	}
}

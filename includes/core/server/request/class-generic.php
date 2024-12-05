<?php
/**
 * Class file for WCPay\Core\Server\Request\Generic.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Server_Request_Exception;
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
	 * Creates a new instance of the class.
	 *
	 * @param mixed $id The identifier for various update/get/delete requests.
	 *
	 * @throws Server_Request_Exception
	 */
	public static function create( $id = null ) {
		throw new Server_Request_Exception( 'You cannot create request this way.', 'wcpay_core_server_request_invalid_method_call' );
	}

	/**
	 * Instantiates the request object.
	 *
	 * @param  string $api                                 The API to use. See WCPay\Core\Server\APIs.
	 * @param  string $method                              The request method. See the `Requests` class.
	 * @param  array  $parameters                          The parameters for the request.
	 * @throws Invalid_Request_Parameter_Exception         An exception if there are invalid properties.
	 */
	public function __construct( string $api, string $method, ?array $parameters = null ) {
		if ( ! defined( \WC_Payments_Utils::get_wpcore_request_class() . "::$method" ) ) {
			throw new Invalid_Request_Parameter_Exception( 'Invalid generic request method', 'wcpay_core_invalid_request_parameter_method_not_defined' );
		}

		$this->api    = $api;
		$this->method = $method;

		if ( ! empty( $parameters ) ) {
			foreach ( $parameters as $key => $value ) {
				$this->set( $key, $value );
			}
		}
	}

	/**
	 * Generic setter for parameters.
	 *
	 * @param  string $key   Key of the parameter.
	 * @param  mixed  $value Value of the parameter.
	 * @return Generic       Instance of the class for method chaining.
	 */
	public function set( $key, $value ) {
		// Use the `Request` setter here.
		$this->set_param( $key, $value );

		return $this;
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

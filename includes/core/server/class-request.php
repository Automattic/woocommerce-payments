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
	private $params = [];

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
		return true;
	}

	/**
	 * If true, the request will be signed with the user token rather than blog token. Defaults to false.
	 *
	 * @return bool
	 */
	public function should_use_user_token(): bool {
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

	/**
	 * Returns all of the parameters for the request.
	 *
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Stores a parameter within the internal props.
	 *
	 * Use this method within child classes in order to allow
	 * those properties to be protected by overwriting.
	 *
	 * @param string $key   The name of the parameter.
	 * @param mixed  $value And the value to set.
	 */
	protected function set_param( string $key, $value ) {
		$this->params[ $key ] = $value;
	}

	/**
	 * Replaces all internal parameters of the class.
	 * Only accessible from this class, this method is used for `extend`.
	 *
	 * @param array $params The new parameters to use.
	 */
	private function set_params( $params ) {
		$this->params = $params;
	}

	/**
	 * Creates a new instance the class with the same props as the parent.
	 *
	 * @param  Request $base_request The request to extend.
	 * @return static                An instance of the class.
	 * @throws \Exception            In case this is not a subclass of the base request.
	 */
	final public static function extend( Request $base_request ) {
		if ( ! is_subclass_of( static::class, get_class( $base_request ) ) ) {
			throw new \Exception(
				sprintf(
					'Failed to extend request. %s is not a subclass of %s',
					static::class,
					get_class( $base_request )
				)
			);
		}

		$obj = new static();
		$obj->set_params( $base_request->params );

		return $obj;
	}
}

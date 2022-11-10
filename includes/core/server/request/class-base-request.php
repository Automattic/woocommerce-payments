<?php
/**
 * Base request value object.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Server\Request;

use \WCPay\Core\Contracts\Server\Request\Base_Request_Interface;

/**
 * Base request value object.
 */
abstract class Base_Request implements Base_Request_Interface {

	/**
	 * Get request params.
	 *
	 * @return array
	 */
	abstract public function get_parameters();

	/**
	 * Get request method.
	 *
	 * @return string
	 */
	abstract public function get_method();

	/**
	 * Get request route.
	 *
	 * @return string
	 */
	abstract public function get_route();

	/**
	 * Validate params of request. Make sure that override this function in child class.
	 *
	 * @return bool
	 */
	abstract public function is_request_data_valid();

	/**
	 * Is site specific request.
	 *
	 * @return bool
	 */
	public function is_site_specific() {
		return false;
	}

	/**
	 * Use user token for auth.
	 *
	 * @return bool
	 */
	public function use_user_token() {
		return false;
	}


	/**
	 * Return array of all properties.
	 *
	 * @return array
	 */
	public function to_array() {
		return get_object_vars( $this );
	}

	/**
	 * Validate and get request data.
	 *
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	final public function get_request_data() {
		if ( ! $this->is_request_data_valid() ) {
			throw new \InvalidArgumentException( 'Request data is not valid' );
		}
		if ( ! in_array( $this->get_method(), [ \Requests::GET, \Requests::HEAD, \Requests::OPTIONS, \Requests::POST, \Requests::PUT, \Requests::PATCH, \Requests::DELETE ], true ) ) {
			throw new \InvalidArgumentException( 'Request method is not valid' );
		}

		return $this->get_parameters();
	}

	/**
	 * Validates is value string and not empty.
	 *
	 * @param string $value Value to validate.
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	public function is_string_and_not_empty( $value ) {
		if ( is_string( $value ) && $value ) {
			return true;
		}
		throw new \InvalidArgumentException( "The property $value is invalid! It needs to be string type and not empty" );
	}

	/**
	 * Validates is value greater than compared value.
	 *
	 * @param int $value Value to validate.
	 * @param int $compared_value Value to compare.
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	public function is_greater_than( $value, $compared_value ) {
		if ( ! is_numeric( $value ) ) {
			throw new \InvalidArgumentException( 'Passed value is not numeric value' );
		}

		if ( $compared_value > $value ) {
			throw new \InvalidArgumentException( 'Passed value is not grater than compared value' );
		}
	}

	/**
	 * Create new request class without using new Class().
	 *
	 * @param mixed $arguments Constructor arguments (if any).
	 * @param bool  $splat_arguments Split arguments if needed. Used to pass arguments as an array where elements will be used as a arguments for constructor.
	 * @return Base_Request
	 */
	public static function new( $arguments = null, $splat_arguments = false ) {

		$class = get_called_class();
		if ( $arguments ) {
			if ( is_array( ( $arguments ) ) && $splat_arguments ) {
				return new $class( ... $arguments );
			} else {
				return new $class( $arguments );
			}
		}
		return new $class();

	}

	/**
	 * Dynamic getters and setters for value objects without them.
	 *
	 * @param string $name Method name.
	 * @param array  $arguments Method arguments.
	 * @return self|mixed
	 */
	public function __call( $name, $arguments ) {

		/**
		 * If get or set method is not defined, this call function will simply add them and use basic get and set functionality.
		 */

		$method_data = explode( '_', $name, 2 );
		$prefix      = $method_data[0] ?? null;
		$property    = $method_data[1] ?? null;
		if ( $property && property_exists( $this, $property ) ) {
			if ( 'get' === $prefix ) {
				return $this->{$property};
			}
		}
	}
}

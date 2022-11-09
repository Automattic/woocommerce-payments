<?php
/**
 * Base request value object.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Server\Request;

use \WCPay\Core\Contracts\Server\Request\Base_Request as Base_Request_Contract;

/**
 * Base request value object.
 */
class Base_Request implements Base_Request_Contract {


	/**
	 * Request headers.
	 *
	 * @var array $headers
	 */
	private $headers;
	/**
	 * Get request params.
	 *
	 * @return array
	 */
	public function get_parameters() {
		return $this->to_array();
	}

	/**
	 * Get request method.
	 *
	 * @return string
	 */
	public function get_method() {
		return '';
	}

	/**
	 * Get request route.
	 *
	 * @return string
	 */
	public function get_route() {
		return '';
	}

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
	 * Get headers.
	 *
	 * @return array
	 */
	public function get_headers() {
		return $this->headers;
	}

	/**
	 * Set headers.
	 *
	 * @param array $headers Headers to set.
	 */
	public function set_headers( $headers ) {
		$this->headers = $headers;
	}

	/**
	 * Add custom header.
	 *
	 * @param string $name Header name or key.
	 * @param string $value Header value.
	 */
	public function add_header( $name, $value ) {
		$this->headers[ $name ] = $value;
	}

	/**
	 * Return array of all properties.
	 *
	 * @return array
	 */
	public function to_array() {
		$data = [];
		foreach ( get_object_vars( $this ) as $key => $value ) {
			$data[ $key ] = $value;
		}

		return $data;
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

		if ( $arguments ) {
			if ( is_array( ( $arguments ) ) && $splat_arguments ) {
				return new self( ... $arguments );
			} else {
				return new self( $arguments );
			}
		}
		return new self();

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

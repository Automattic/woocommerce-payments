<?php
/**
 * Create charge request.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\DataTransferObjects;

/**
 * Base Value Object class.
 */
abstract class Value_Object {

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

		$method_data = explode( '_', $name );
		$prefix      = $method_data[0] ?? null;
		$property    = $method_data[1] ?? null;
		if ( $property && property_exists( $this, $property ) ) {
			if ( 'set' === $prefix && 1 === count( $arguments ) ) {
				$this->{$property} = $arguments[0];
				return $this;
			}
			if ( 'get' === $prefix ) {
				return $this->{$property};
			}
		}
	}

	/**
	 * Custom value object setter.
	 *
	 * @param string $name Name of variable.
	 * @param mixed  $value Value of variable.
	 * @return $this
	 */
	public function __set( $name, $value ) {
		$method_name = 'set_' . $name;
		if ( method_exists( $this, $method_name ) ) {
			return $this->$method_name( $value );
		}
		$this->$name = $value;
		return $this;
	}

	/**
	 * Fill array from data.
	 *
	 * @param array $data Data to fill.
	 * @return void
	 */
	public function fill_from_array( $data ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
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

}

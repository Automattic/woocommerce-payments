<?php
/**
 * Class file for WCPay\Core\Server\Response.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server;

use ArrayAccess;

/**
 * Represents responses from the WCPay server.
 */
class Response implements ArrayAccess {
	/**
	 * Holds the data of the response.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Constructs the class.
	 *
	 * @param array $data The data for the response.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * Checks if a key exists.
	 *
	 * @param mixed $offset The key to check.
	 * @return bool
	 */
	public function offsetExists( $offset ) : bool {
		return isset( $this->data[ $offset ] );
	}

	/**
	 * Retrieves the value with a certain key.
	 *
	 * @param mixed $offset The key to retrieve.
	 * @return mixed
	 */
	public function offsetGet( $offset ) {
		return $this->data[ $offset ];
	}

	/**
	 * Attempts to set a value in the response.
	 *
	 * @param mixed $offset The key of the value.
	 * @param mixed $value  The value.
	 * @throws \Exception   It is not possible.
	 */
	public function offsetSet( $offset, $value ) {
		throw new \Exception( 'Server responses cannot be mutated.' );
	}

	/**
	 * Removes a value from the response.
	 *
	 * @param mixed $offset The offset to remove.
	 * @throws \Exception   It is not possible.
	 */
	public function offsetUnset( $offset ) {
		throw new \Exception( 'Server responses cannot be mutated.' );
	}
}

<?php
/**
 * Class file for WCPay\Core\Server\Response.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server;

use ArrayAccess;
use WCPay\Core\Exceptions\Server\Response\Server_Response_Exception;

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
	public function offsetExists( $offset ): bool {
		return isset( $this->data[ $offset ] );
	}

	/**
	 * Retrieves the value with a certain key.
	 *
	 * @param mixed $offset The key to retrieve.
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->data[ $offset ];
	}

	/**
	 * Attempts to set a value in the response.
	 *
	 * @param mixed $offset              The key of the value.
	 * @param mixed $value               The value.
	 * @throws Server_Response_Exception It is not possible.
	 */
	public function offsetSet( $offset, $value ): void {
		throw new Server_Response_Exception( 'Server responses cannot be mutated.', 'wcpay_core_server_response_malformed' );
	}

	/**
	 * Removes a value from the response.
	 *
	 * @param mixed $offset                The offset to remove.
	 * @throws Server_Response_Exception   It is not possible.
	 */
	public function offsetUnset( $offset ): void {
		throw new Server_Response_Exception( 'Server responses cannot be mutated.', 'wcpay_core_server_response_malformed' );
	}

	/**
	 * Return data as an array.
	 *
	 * @return array
	 */
	public function to_array() {
		return $this->data;
	}
}

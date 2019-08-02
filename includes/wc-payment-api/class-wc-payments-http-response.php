<?php
/**
 * WC_Payments_Http_Response class
 *
 * @package WooCommerce\Payments
 */

/**
 * An HTTP response returned from the Payments API.
 */
class WC_Payments_Http_Response {

	/**
	 * The response code.
	 *
	 * @var int|string
	 */
	private $code;

	/**
	 * The response body.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * WC_Payments_Http_Response constructor.
	 *
	 * @param string|int $code - Response code.
	 * @param string     $body - Response body.
	 */
	public function __construct( $code, $body ) {
		$this->code = $code;
		$this->body = $body;
	}

	/**
	 * Get response code.
	 *
	 * @return int|string
	 */
	public function get_code() {
		return $this->code;
	}

	/**
	 * Get response body.
	 *
	 * @return string
	 */
	public function get_body() {
		return $this->body;
	}
}

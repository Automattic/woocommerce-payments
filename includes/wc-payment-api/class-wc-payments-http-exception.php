<?php
/**
 * Class WC_Payments_Http_Exception
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class representing WC_Payments_API_Exception
 */
class WC_Payments_Http_Exception extends Exception {
	/**
	 * HTTP error code, for example 404, 500 etc.
	 *
	 * @var int
	 */
	private $http_code = 0;

	/**
	 * Constructor
	 *
	 * @param string    $message    The Exception message to throw.
	 * @param int       $http_code  HTTP response code.
	 * @param int       $code       The Exception code.
	 * @param Throwable $previous   The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $http_code = 0, $code = 0, $previous = null ) {
		$this->http_code = $http_code;

		return parent::__construct( $message, $code, $previous );
	}

	/**
	 * Returns the HTTP code.
	 *
	 * @return int HTTP code, for example 404.
	 */
	public function get_http_code() {
		return $this->http_code;
	}
}

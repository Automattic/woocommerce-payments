<?php
/**
 * Class WC_Payments_Exception
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class representing WC_Payments_API_Exception
 */
class WC_Payments_API_Exception extends Exception {
	/**
	 * String error code, for example wcpay_account_not_found.
	 *
	 * @var string
	 */
	private $error_code = '';

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
	 * @param string    $error_code Error code returned by the server, for example wcpay_account_not_found.
	 * @param int       $http_code  HTTP response code.
	 * @param int       $code       The Exception code.
	 * @param Throwable $previous   The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $error_code, $http_code, $code = 0, $previous = null ) {
		$this->error_code = $error_code;
		$this->http_code  = $http_code;

		return parent::__construct( $message, $code, $previous );
	}

	/**
	 * Returns the error code.
	 *
	 * @return string Error code, for example wcpay_account_not_found.
	 */
	public function get_error_code() {
		return $this->error_code;
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

<?php
/**
 * Class API_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Class representing API_Exception
 */
class API_Exception extends Base_Exception {
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
		$this->http_code = $http_code;

		parent::__construct( $message, $error_code, $code, $previous );
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

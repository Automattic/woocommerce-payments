<?php
/**
 * Class Base_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract class for payments extension exceptions, where we allow to inject
 * human-friendly error codes, e.g. 'order_not_found'.
 */
abstract class Base_Exception extends Exception {
	/**
	 * String error code, for example 'order_not_found'.
	 *
	 * @var string
	 */
	private $error_code;

	/**
	 * Constructor, including the usual $message, $code, and $previous,
	 * and a new parameter $error_code.
	 *
	 * @param string     $message    The Exception message to throw.
	 * @param string     $error_code String error code.
	 * @param int        $code       The Exception code.
	 * @param \Throwable $previous   The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $error_code, $code = 0, $previous = null ) {
		$this->error_code = $error_code;

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Returns the error code.
	 *
	 * @return string Error code, for example 'order_not_found'.
	 */
	public function get_error_code() {
		return $this->error_code;
	}
}

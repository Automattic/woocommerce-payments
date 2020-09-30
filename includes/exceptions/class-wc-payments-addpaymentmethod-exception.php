<?php
/**
 * Class WC_Payments_AddPaymentMethod_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when we have issues with creating
 * payment methods, e.g. invalid requests or errors on the server side.
 */
class WC_Payments_AddPaymentMethod_Exception extends WC_Payments_Base_Exception {
	/**
	 * String error code, for example 'invalid_something'.
	 *
	 * @var string
	 */
	private $error_code;

	/**
	 * Constructor, including the usual $message, $code, and $previous,
	 * and a new parameter $error_code.
	 *
	 * @param string    $message    The Exception message to throw.
	 * @param string    $error_code String error code.
	 * @param int       $code       The Exception code.
	 * @param Throwable $previous   The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $error_code, $code = 0, $previous = null ) {
		$this->error_code = $error_code;

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Returns the error code.
	 *
	 * @return string Error code, for example 'invalid_something'.
	 */
	public function get_error_code() {
		return $this->error_code;
	}
}

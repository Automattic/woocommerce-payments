<?php
/**
 * Class WC_Payments_Intent_Authentication_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when there's a problem updating an order after a payment
 * authentication attempt was made by the customer, e.g. for 3DS authentication.
 */
class WC_Payments_Intent_Authentication_Exception extends Exception {
	/**
	 * String error code, for example 'intent_id_mismatch'.
	 *
	 * @var string
	 */
	private $error_code = '';

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

		return parent::__construct( $message, $code, $previous );
	}

	/**
	 * Returns the error code.
	 *
	 * @return string Error code, for example 'intent_id_mismatch'.
	 */
	public function get_error_code() {
		return $this->error_code;
	}
}

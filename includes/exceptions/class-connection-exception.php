<?php
/**
 * Class Connection_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Class representing Connection_Exception
 */
class Connection_Exception extends API_Exception {

	/**
	 * Constructor
	 *
	 * @param string    $message         The Exception message to throw.
	 * @param string    $error_code      Error code returned by the server, for example wcpay_account_not_found.
	 * @param int       $http_code       HTTP response code.
	 * @param array     $additional_data Additional data for the exception.
	 * @param int       $code            The Exception code.
	 * @param Throwable $previous        The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $error_code, $http_code, $additional_data = null, $code = 0, $previous = null ) {
		$message = __(
			'There was an error while processing the payment. If you continue to see this notice, please contact the admin.',
			'woocommerce-payments'
		);

		parent::__construct( $message, $error_code, $http_code, $additional_data, $code, $previous );
	}
}

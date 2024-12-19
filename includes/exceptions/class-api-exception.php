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
	 * Error type attribute from the server.
	 *
	 * @var string
	 */
	private $error_type = null;

	/**
	 * Decline code if it is a card error.
	 *
	 * @var string
	 */
	private $decline_code = null;

	/**
	 * Constructor
	 *
	 * @param string     $message    The Exception message to throw.
	 * @param string     $error_code Error code returned by the server, for example wcpay_account_not_found.
	 * @param int        $http_code  HTTP response code.
	 * @param string     $error_type Error type attribute.
	 * @param string     $decline_code The decline code if it is a card error.
	 * @param int        $code       The Exception code.
	 * @param \Throwable $previous   The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $error_code, $http_code, $error_type = null, $decline_code = null, $code = 0, $previous = null ) {
		$this->http_code    = $http_code;
		$this->error_type   = $error_type;
		$this->decline_code = $decline_code;

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

	/**
	 * Returns the error type attribute from the server.
	 *
	 * @return string|null Error type, for example 'api_error' or 'card_error'.
	 */
	public function get_error_type() {
		return $this->error_type;
	}

	/**
	 * Returns the decline code attribute from the server.
	 *
	 * @return string|null Decline code, for example 'expired_card' or 'insufficient_funds'.
	 */
	public function get_decline_code() {
		return $this->decline_code;
	}
}

<?php
/**
 * Class API_Merchant_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Class extending API_Exception to include the error message for merchants only.
 */
class API_Merchant_Exception extends API_Exception {
	/**
	 * Merchant message. This message should not be shown to shoppers.
	 *
	 * @var string
	 */
	private $merchant_message;

	/**
	 * Constructor
	 *
	 * @param string          $message    The Exception message to throw.
	 * @param string          $error_code Error code returned by the server, for example wcpay_account_not_found.
	 * @param int             $http_code  HTTP response code.
	 * @param string          $merchant_message The merchant message. This message should not be shown to shoppers.
	 * @param string|null     $error_type Error type attribute.
	 * @param string|null     $decline_code The decline code if it is a card error.
	 * @param int             $code       The Exception code.
	 * @param \Throwable|null $previous   The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $error_code, $http_code, $merchant_message, $error_type = null, $decline_code = null, $code = 0, $previous = null ) {
		$this->merchant_message = $merchant_message;

		parent::__construct( $message, $error_code, $http_code, $error_type, $decline_code, $code, $previous );
	}

	/**
	 * Returns the merchant message.
	 *
	 * @return string Merchant message.
	 */
	public function get_merchant_message(): string {
		return $this->merchant_message;
	}
}

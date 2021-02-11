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
	 * Additional data about exception
	 *
	 * @var array
	 */
	private $additional_data = [];

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
		$this->http_code       = $http_code;
		$this->additional_data = is_array( $additional_data ) ? $additional_data : [];

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
	 * Returns additional data
	 *
	 * @return array Additional data if available.
	 */
	public function get_additional_data() {
		return $this->additional_data;
	}


	/**
	 * Returns a user-friendly error message which can be displayed on
	 * front-end.
	 *
	 * @return string User-friendly error message.
	 */
	public function get_user_message() {

		if ( 'wcpay_amount_too_low' === $this->get_error_code() ) {
			if ( isset( $this->additional_data['min_amount'], $this->additional_data['currency'] ) ) {
				$min_amount = $this->additional_data['min_amount'];
				$currency   = strtolower( $this->additional_data['currency'] );

				if ( ! in_array( $currency, \WC_Payments_Utils::zero_decimal_currencies(), true ) ) {
					$min_amount = $min_amount / 100;
				}

				// Response contains a minumum order amount which we will
				// use to build a specific message for a client.
				return sprintf(
					// translators: %1$s is a formatted amount with currency code.
					__(
						'Sorry, the minimum allowed order total is %1$s to use this payment method.',
						'woocommerce-payments'
					),
					wc_price( $min_amount, [ 'currency' => strtoupper( $currency ) ] )
				);
			}

			return __(
				'The specified amount is less than the minimum amount allowed. Use a higher amount and try again.',
				'woocommerce-payments'
			);
		}

		return $this->getMessage();
	}
}

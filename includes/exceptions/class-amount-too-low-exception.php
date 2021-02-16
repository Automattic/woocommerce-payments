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
class Amount_Too_Low_Exception extends API_Exception {

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
			'The specified amount is less than the minimum amount allowed. Use a higher amount and try again.',
			'woocommerce-payments'
		);
		if ( is_array( $additional_data ) && isset( $additional_data['min_amount'], $additional_data['currency'] ) ) {
			$currency   = strtolower( $additional_data['currency'] );
			$min_amount = \WC_Payments_Utils::interpret_stripe_amount( $additional_data['min_amount'], $currency );

			// Response contains a minumum order amount which we will
			// use to build a specific message for a client.
			$message = sprintf(
				// translators: %1$s is a formatted amount with currency code.
				__(
					'Sorry, the minimum allowed order total is %1$s to use this payment method.',
					'woocommerce-payments'
				),
				wc_price( $min_amount, [ 'currency' => strtoupper( $currency ) ] )
			);
		}

		parent::__construct( $message, $error_code, $http_code, $additional_data, $code, $previous );
	}
}

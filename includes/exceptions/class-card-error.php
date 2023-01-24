<?php
/**
 * Class Card_Error
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Class representing Card_Error exception.
 */
class Card_Error extends API_Exception {
	/**
	 * Error codes, which are also card errors, though there is no `decline_code`.
	 */
	const ERROR_CODES = [
		'expired_card',
		'incorrect_cvc',
		'processing_error',
		'incorrect_number',
	];

	/**
	 * Holds the decline code for card errors.
	 *
	 * @var string
	 */
	private $decline_code;

	/**
	 * Constructor
	 *
	 * @param string     $message      The Exception message to throw.
	 * @param string     $error_code   Error code returned by the server, for example wcpay_account_not_found.
	 * @param string     $decline_code Card decline code, ex. `insufficient_funds`.
	 * @param int        $http_code    HTTP response code.
	 * @param string     $error_type   Error type attribute.
	 * @param int        $code         The Exception code.
	 * @param \Throwable $previous     The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $error_code, $decline_code, $http_code, $error_type = null, $code = 0, $previous = null ) {
		$this->decline_code = $decline_code;

		parent::__construct( $message, $error_code, $http_code, $error_type, $code, $previous );
	}

	/**
	 * Returns the decline code of the card error.
	 *
	 * @return string
	 */
	public function get_decline_code() {
		return $this->decline_code;
	}

	/**
	 * Returns a localized message.
	 *
	 * @return string
	 */
	public function getLocalizedMessage() {
		switch ( $this->get_decline_code() ) {
			case 'insufficient_funds':
				return __( 'Error: Your card has insufficient funds.', 'woocommerce-payments' );

			case 'expired_card':
				return __( 'Error: Your card has expired.', 'woocommerce-payments' );

			case 'incorrect_cvc':
				return __( 'Error: Your card\'s security code is incorrect.', 'woocommerce-payments' );

			case 'processing_error':
				return __( 'Error: An error occurred while processing your card. Try again in a little bit.', 'woocommerce-payments' );

			case 'incorrect_number':
				return __( 'Error: Your card number is invalid.', 'woocommerce-payments' );

			// Some card errors do not have specific customer-facing representations.
			case 'lost_card':
			case 'generic_decline':
			case 'stolen_card':
			default:
				return __( 'Error: Your card was declined.', 'woocommerce-payments' );
		}
	}

	/**
	 * Checks if an error code is a card error.
	 *
	 * @param string $error_code The error code to check.
	 * @return bool              Whether the error code responds to a card error.
	 */
	public static function is_card_error( $error_code ) {
		return in_array( $error_code, static::ERROR_CODES, true );
	}
}

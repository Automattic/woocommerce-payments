<?php
/**
 * Generic exception class for WooCommerce Payments API errors.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * An exception object used by the WooCommerce Payments API.
 */
class WC_Payments_API_Exception extends Exception {

	/**
	 * Type of API response error.
	 *
	 * One of these several types:
	 * https://stripe.com/docs/api/errors#errors-type
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * WC_Payments_API_Intention constructor.
	 *
	 * @param string $type - type of error.
	 * @param string $message - error message.
	 * @param int    $status - HTTP status code of API response.
	 */
	public function __construct( $type, $message, $status ) {
		$this->type    = $type;
		$this->message = $message;

		if ( 'card_error' !== $this->type ) {
			$this->message = __(
				'There was a problem with your payment.',
				'woocommerce-payments'
			);
		}
	}

	/**
	 * Gets the error type.
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Creates a new WC_Payments_API_Exception object directly from the API response body and status.
	 *
	 * @param array $response_body - response body.
	 * @param int   $response_status_code - HTTP status code of API response.
	 * @return WC_Payments_API_Exception
	 * @throws Exception - If no error is found in $response_body.
	 */
	public static function build_from_api_response( $response_body, $response_status_code ) {
		if ( ! isset( $response_body['error'] ) ) {
			throw new Exception( __( 'No error found in API response.', 'woocommerce-payments' ) );
		}

		$error   = $response_body['error'];
		$type    = $error['type'];
		$message = $error['message'];

		return new WC_Payments_API_Exception( $type, $message, $response_status_code );
	}
}

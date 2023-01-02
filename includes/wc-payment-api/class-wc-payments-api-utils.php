<?php
/**
 * WC_Payments_Api_Utils class
 *
 * @package WooCommerce\Payments
 */

/**
 * Helper class to perform operations on a response.
 */
class WC_Payments_Api_Utils {

	const HTTP_CODES_TO_RETRY = [ 302 ];

	/**
	 * Checks if the response code is present and doesn't need a retry.
	 *
	 * @param int $response_code - HTTP response code.
	 * @return bool - True if there is no response code or the response code requires a retry. False otherwise.
	 */
	public function is_response_code_present_and_does_not_need_a_retry( $response_code ) {
		return $response_code && ! in_array( $response_code, self::HTTP_CODES_TO_RETRY, true );
	}

	/**
	 * Checks if the Location header contains WP Cron related query parameter.
	 *
	 * @param array $response - A presumably redirection response which is used to ensure, if the redirection occurred due to the WP Cron task.
	 * @return bool - True if there is the doing_wp_cron query parameter attached to the redirect URL. False otherwise.
	 */
	public function is_doing_wp_cron_query_parameter_present( $response ) {
		return str_contains( $response['headers']['location'], 'doing_wp_cron' );
	}
}

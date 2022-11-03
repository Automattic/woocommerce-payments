<?php
/**
 * Base response interface.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Contracts\API\Response;

use WCPay\Core\DataTransferObjects\Response;
use WP_Http_Cookie;

/**
 * Base response interface.
 */
interface Base_Response {

	/**
	 * Get body.
	 *
	 * @return string
	 */
	public function get_body();

	/**
	 * Get status code.
	 *
	 * @return int
	 */
	public function get_code();

	/**
	 * Get headers.
	 *
	 * @return array
	 */
	public function get_headers();

	/**
	 * Get message.
	 *
	 * @return string
	 */
	public function get_message();

	/**
	 * Get cookies.
	 *
	 * @return WP_Http_Cookie[]
	 */
	public function get_cookies();

	/**
	 * Get response body as array.
	 *
	 * @return array|mixed
	 */
	public function get_response_data();

	/**
	 * Determinate does error exists in response data.
	 *
	 * @return bool
	 */
	public function has_error_in_response_data();

	/**
	 * Create DTO from WCPay response.
	 *
	 * @param array $response Server response.
	 * @return Response
	 */
	public static function create_from_wc_pay_response( $response);

}

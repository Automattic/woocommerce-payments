<?php
/**
 * Base response interface.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Contracts\API\Response;

use WCPay\Core\Server\Response;
use WP_Http_Cookie;

/**
 * Base response interface.
 */
interface Base_Response_Interface {

	/**
	 * Get response data.
	 *
	 * @return array
	 */
	public function get_data();

	/**
	 * Get response properties in array format.
	 *
	 * @return array
	 */
	public function to_array();

	/**
	 * Create DTO from WCPay response.
	 *
	 * @param array $response Server response.
	 * @return Response
	 */
	public static function create_from_wc_pay_server_response( $response );

}

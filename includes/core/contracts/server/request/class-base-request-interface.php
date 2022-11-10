<?php
/**
 * Base request interface.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Contracts\Server\Request;

/**
 * Base request interface.
 */
interface Base_Request_Interface {

	/**
	 * Get params.
	 *
	 * @return array
	 */
	public function get_parameters();

	/**
	 * Validate and get request data.
	 *
	 * @return array
	 */
	public function get_request_data();

	/**
	 * Get request method.
	 *
	 * @return string
	 */
	public function get_method();

	/**
	 * Get method.
	 *
	 * @return string
	 */
	public function get_route();

	/**
	 * Is site specific request.
	 *
	 * @return bool
	 */
	public function is_site_specific();

	/**
	 * Use user token for auth.
	 *
	 * @return bool
	 */
	public function use_user_token();
}

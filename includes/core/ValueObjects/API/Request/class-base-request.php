<?php
/**
 * Base request value object.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\ValueObjects\API\Request;

use WCPay\Core\Contracts\API\Request\Base_Request as Base_Request_Contract;
use WCPay\Core\DataTransferObjects\Value_Object;

/**
 * Base request value object.
 */
class Base_Request extends Value_Object implements Base_Request_Contract {


	/**
	 * Request headers.
	 *
	 * @var array $headers
	 */
	private $headers;
	/**
	 * Get request params.
	 *
	 * @return array
	 */
	public function get_parameters() {
		return $this->to_array();
	}

	/**
	 * Get request method.
	 *
	 * @return string
	 */
	public function get_method() {
		return '';
	}

	/**
	 * Get request route.
	 *
	 * @return string
	 */
	public function get_route() {
		return '';
	}

	/**
	 * Is site specific request.
	 *
	 * @return bool
	 */
	public function is_site_specific() {
		return false;
	}

	/**
	 * Use user token for auth.
	 *
	 * @return bool
	 */
	public function use_user_token() {
		return false;
	}

	/**
	 * Get headers.
	 *
	 * @return array
	 */
	public function get_headers() {
		return $this->headers;
	}

	/**
	 * Set headers.
	 *
	 * @param array $headers Headers to set.
	 */
	public function set_headers( $headers ) {
		$this->headers = $headers;
	}

	/**
	 * Add custom header.
	 *
	 * @param string $name Header name or key.
	 * @param string $value Header value.
	 */
	public function add_header( $name, $value ) {
		$this->headers[ $name ] = $value;
	}
}

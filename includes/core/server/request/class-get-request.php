<?php
/**
 * Class file for WCPay\Core\Server\Request\Generic.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;

/**
 * Generic GET WCPay Server Request.
 */
class Get_Request extends Request {
	/**
	 * The request's API.
	 *
	 * @var string Check WCPay\Core\Server\APIs.
	 */
	private $api = ''; // Just to be safe in case someone misses to add it. It will be validated by default.


	/**
	 * Set api route.
	 *
	 * @param string $api Api route.
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_api( string $api ) {
		$this->validate_api_route( $api );

		$this->api = null === $this->id ? $api : $api . "/$this->id";
	}


	/**
	 * Get API route.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return $this->api;
	}

	/**
	 * Get method.
	 *
	 * @return string
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * Override from parent class.
	 *
	 * @param mixed $id ID to set.
	 *
	 * @return void
	 */
	public function set_request_route_id_parameter( $id ) {
		$this->set_id( $id ); // We don't need extra set_id method validation here.
	}

	/**
	 * Set ID:
	 *
	 * @param mixed $id Set id.
	 *
	 * @return void
	 */
	public function set_id( $id ) {
		// We won't validate it in detail. Just to be sure it is not empty.
		if ( $id ) {
			$this->id = $id;
		}
	}
}

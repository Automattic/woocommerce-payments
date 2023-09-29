<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Charge_Refunds.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for charge refunds.
 */
class List_Charge_Refunds extends Request {

	const DEFAULT_PARAMS = [
		'limit' => 100,
	];

	const IMMUTABLE_PARAMS = [ 'charge' ];

	const REQUIRED_PARAMS = [ 'charge' ];

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_list_charge_refunds_request';

	/**
	 * Sets the charge ID, which will be used in the request URL.
	 *
	 * @param string $charge_id Sets the charge ID, which will be used in the request URL.
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_charge( string $charge_id ) {
		/**
		 * `py_XYZ` objects are identical to charges, and sometimes occur
		 * whenever the payment was made in a non-deposit currency.
		 */
		$this->validate_stripe_id( $charge_id, [ 'ch', 'py' ] );
		$this->set_param( 'charge', $charge_id );
	}

	/**
	 * Stores the limit for the lists.
	 *
	 * @param int $limit Number to limit results.
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_limit( int $limit ) {
		$this->validate_is_larger_than( $limit, 0 );
		$this->set_param( 'limit', $limit );
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::REFUNDS_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
	}
}

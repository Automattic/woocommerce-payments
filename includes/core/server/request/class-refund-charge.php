<?php
/**
 * Class file for WCPay\Core\Server\Request\Refund_Charge.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for refund charge.
 */
class Refund_Charge extends Request {

	const DEFAULT_PARAMS = [
		'amount' => null,
	];

	const IMMUTABLE_PARAMS = [ 'charge' ];

	const REQUIRED_PARAMS = [ 'charge' ];

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
	 * Stores the amount for the refund.
	 *
	 * @param int $amount The amount to refund.
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_amount( int $amount ) {
		$this->validate_is_larger_then( $amount, 0 );
		$this->set_param( 'amount', $amount );
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
		return 'POST';
	}
}

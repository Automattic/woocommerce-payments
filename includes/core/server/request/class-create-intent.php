<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Intent.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for creating intents.
 */
class Create_Intent extends Request {
	const IMMUTABLE_PARAMS = [ 'amount' ];
	const REQUIRED_PARAMS  = [ 'amount', 'currency' ];

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::INTENTIONS_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * Stores the amount for the intent.
	 *
	 * @param int $amount The amount in ToDo units.
	 * @throws Invalid_Request_Parameter_Exception
	 */
	final public function set_amount( int $amount ) {
		$this->validate_is_larger_then( $amount, 0 );
		$this->set_param( 'amount', $amount );
	}

	/**
	 * Updates the currency of the intent.
	 *
	 * This is an example of a non-protected property.
	 *
	 * @param string $currency The currency to use.
	 */
	public function set_currency( string $currency ) {
		$this->set_param( 'currency', $currency );
	}
}

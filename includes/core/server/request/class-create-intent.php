<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Intent.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Server\Request;

/**
 * Request class for creating intents.
 */
class Create_Intent extends Request {
	const IMMUTABLE_PARAMS = [ 'amount' ];
	const REQUIRED_PARAMS  = [ 'amount', 'currency' ];

	/**
	 * Allows the class to be constructed.
	 *
	 * @todo This could be replaced by static methods.
	 */
	public function __construct() {
		// Nothing to do here yet.
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return 'intents';
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
	 * @param  int $amount   The amount in ToDo units.
	 * @return Create_Intent The instance of the class for method chaining.
	 */
	final public function set_amount( int $amount ) {
		// Validation here...
		$this->set_param( 'amount', $amount );
		return $this;
	}

	/**
	 * Updates the currency of the intent.
	 *
	 * This is an example of a non-protected property.
	 *
	 * @param string $currency The currency to use.
	 * @return Create_Intent The instance of the class for method chaining.
	 */
	public function set_currency( string $currency ) {
		$this->set_param( 'currency', $currency );
		return $this;
	}
}

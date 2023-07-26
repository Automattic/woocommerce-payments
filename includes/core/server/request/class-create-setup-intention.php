<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Setup_Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for creating setup intents.
 */
class Create_Setup_Intention extends Request {
	use Intention;
	use Level3;

	const IMMUTABLE_PARAMS = [ 'customer', 'confirm' ];
	const REQUIRED_PARAMS  = [ 'customer', 'payment_method_types' ];

	const DEFAULT_PARAMS = [
		'confirm' => 'false',
	];

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::SETUP_INTENTS_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * Payment methods type setter.
	 *
	 * @param array $payment_methods List of payment methods.
	 *
	 * @return void
	 */
	public function set_payment_method_types( array $payment_methods ) {
		$this->set_param( 'payment_method_types', $payment_methods );
	}

	/**
	 * Customer setter.
	 *
	 * @param string $customer_id ID of the customer making the payment.
	 * @return void
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_customer( string $customer_id ) {
		$this->validate_stripe_id( $customer_id, 'cus' );
		$this->set_param( 'customer', $customer_id );
	}
}

<?php
/**
 * Class file for WCPay\Core\Server\Request\Prepare_Terminal_Payment.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for preparing a terminal payment before confirmation.
 */
class Prepare_Terminal_Payment extends Request {
	use Intention;

	/**
	 * Order ID required for server-side intent metadata validation.
	 *
	 * @var string[]
	 */
	const REQUIRED_PARAMS = [ 'order_id' ];

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_prepare_terminal_payment_request';

	/**
	 * Sets the intent ID, which will be used in the request URL.
	 *
	 * @param string $id Sets the intent ID, which will be used in the request URL.
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	protected function set_id( string $id ) {
		$this->validate_stripe_id( $id );
		$this->id = $id;
	}

	/**
	 * Sets the order ID used to validate the PaymentIntent belongs to the order before preparation.
	 *
	 * @param int $order_id Order ID.
	 */
	public function set_order_id( int $order_id ): void {
		$this->set_param( 'order_id', $order_id );
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::INTENTIONS_API . '/' . $this->id . '/prepare_terminal_payment';
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}
}

<?php
/**
 * Class file for WCPay\Core\Server\Request\Activate_PM_Promotion.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Server\Request;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WC_Payments_API_Client;

/**
 * Request class for activating a PM promotion.
 */
class Activate_PM_Promotion extends Request {
	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_activate_pm_promotion_request';

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException If ID is not set.
	 */
	public function get_api(): string {
		if ( empty( $this->id ) ) {
			throw new \InvalidArgumentException( 'Promotion ID is required for activation' );
		}
		return WC_Payments_API_Client::PROMOTIONS_API . '/' . $this->id . '/activate';
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * Sets the promotion instance ID, which will be used in the request URL.
	 *
	 * @param string $id Sets the promotion instance ID, which will be used in the request URL.
	 */
	protected function set_id( string $id ) {
		$this->id = $id;
	}
}

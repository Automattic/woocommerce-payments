<?php
/**
 * Class file for WCPay\Core\Server\Request\Dismiss_Promotion.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for dismissing a promotion.
 */
class Dismiss_Promotion extends Request {
	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_dismiss_promotion_request';

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::PROMOTIONS_API . '/' . $this->id . '/dismiss';
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}
}

<?php
/**
 * Class file for WCPay\Core\Server\Request\Activate_Promotion.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for activating a promotion.
 */
class Activate_Promotion extends Request {
	const IMMUTABLE_PARAMS = [ 'accept_terms' ];

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_activate_promotion_request';

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::PROMOTIONS_API . '/' . $this->id . '/activate';
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * Sets whether the merchant accepts the promotion terms.
	 *
	 * @param  bool $accept_terms Whether the merchant accepts the terms.
	 *
	 * @return void
	 */
	public function set_accept_terms( bool $accept_terms ) {
		$this->set_param( 'accept_terms', $accept_terms );
	}
}

<?php
/**
 * Class file for WCPay\Core\Mode.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core;

use WC_Payment_Gateway_WCPay;

/**
 * Controls the working mode of WooCommerce Payments.
 */
class Mode {
	/**
	 * Holds the gateway class for settings.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Stores the gateway for later retrieval of options.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway The active gateway.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;
	}
}

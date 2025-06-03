<?php
/**
 * Class WC_Gateway_Google_Pay
 *
 * Adds Google Pay as a payment gateway.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Pay Payment Gateway.
 */
class WC_Gateway_Google_Pay extends WC_Payment_Gateway_CC {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'woocommerce_payments_googlePay';
		$this->method_title = __( 'Google Pay', 'woocommerce-payments' );
		$this->title        = __( 'Google Pay', 'woocommerce-payments' );
		$this->has_fields   = false;
		$this->enabled      = true;
		$this->description  = '';
		$this->supports     = [
			'products',
			'refunds',
		];
	}

	/**
	 * Make this gateway always available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}
}

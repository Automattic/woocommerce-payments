<?php
/**
 * Autoloading and DI test class.
 *
 * @package WCPay
 */

namespace WCPay;

use WCPay\Payment\Checkout;

/**
 * This class will be deleted.
 */
class Test {
	/**
	 * Tests things.
	 */
	public function __construct() {
		$checkout = wcpay_get_container()->get( Checkout::class );
		var_dump( $checkout ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump
		exit;
	}
}

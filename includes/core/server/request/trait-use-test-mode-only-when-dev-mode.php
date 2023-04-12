<?php
/**
 * Trait file for WCPay\Core\Server\Request\Use_Test_Mode_Only_When_Dev_Mode.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;

/**
 * Trait for requests that we only send a test mode request if in dev mode.
 */
trait Use_Test_Mode_Only_When_Dev_Mode {
	/**
	 * Set the test_mode parameter to true only if in dev mode.
	 *
	 * @return void
	 */
	public function set_test_mode_according_to_dev_mode() {
		$this->set_param( 'test_mode', WC_Payments::mode()->is_dev() );
	}
}

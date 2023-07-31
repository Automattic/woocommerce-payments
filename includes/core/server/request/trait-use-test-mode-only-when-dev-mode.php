<?php
/**
 * Trait file for WCPay\Core\Server\Request\Use_Test_Mode_Only_When_Dev_Mode.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;

/**
 * Trait for requests that we send the test_mode flag only when the site is in the dev mode.
 */
trait Use_Test_Mode_Only_When_Dev_Mode {
	/**
	 * Set the test_mode parameter to true only if in dev mode.
	 *
	 * @return array
	 */
	public static function get_default_params() {
		$params              = parent::get_default_params();
		$params['test_mode'] = WC_Payments::mode()->is_dev();
		return $params;
	}
}

<?php
/**
 * Trait file for WCPay\Core\Server\Request\Use_Test_Mode_Only_When_Test_Mode_Onboarding.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

/**
 * Trait for requests that we send the test_mode flag only when the site is onboarding in test mode.
 */
trait Use_Test_Mode_Only_When_Test_Mode_Onboarding {
	/**
	 * Set the test_mode parameter to true only if in dev mode.
	 *
	 * @return array
	 */
	public static function get_default_params() {
		$params              = parent::get_default_params();
		$params['test_mode'] = \WC_Payments::mode()->is_test_mode_onboarding();

		return $params;
	}
}

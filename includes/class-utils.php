<?php
/**
 * Class Utils
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Class handling any account connection functionality
 */
class Utils {
	/**
	 * Check the defined constant to determine the current plugin mode.
	 *
	 * @return bool
	 */
	public static function is_in_dev_mode() {
		return defined( 'WCPAY_DEV_MODE' ) && WCPAY_DEV_MODE;
	}
}

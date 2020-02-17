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

	/**
	 * Check the defined constant to determine the current plugin mode.
	 *
	 * @return bool
	 */
	public static function not_in_dev_mode() {
		return ! self::is_in_dev_mode();
	}

	/**
	 * Check the connected_account_is_dev option to see which way the current account
	 * is connected. False may also mean that nothing was set so this should
	 * only be used to confirm a dev account and not to confirm a live account.
	 *
	 * @return bool
	 */
	public static function is_connected_account_type_dev() {
		return get_option( 'connected_account_is_dev', false );
	}

}

<?php
/**
 * Class WC_Payments_Features
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC Payments Features class
 */
class WC_Payments_Features {
	/**
	 * Checks whether the grouped settings feature is enabled
	 *
	 * @return bool
	 */
	public static function is_grouped_settings_enabled() {
		return get_option( '_wcpay_feature_grouped_settings', '0' ) === '1';
	}
}

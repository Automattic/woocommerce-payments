<?php
/**
 * WooCommerce Payments WC_Payments_Settings_Service Class
 *
 * @package WooCommerce\Payments
 */

use WCPay\MultiCurrency\Interfaces\MultiCurrencySettingsInterface;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Settings_Service.
 */
class WC_Payments_Settings_Service implements MultiCurrencySettingsInterface {
	/**
	 * Checks if dev mode is enabled.
	 *
	 * @return bool
	 */
	public function is_dev_mode(): bool {
		return WC_Payments::mode()->is_dev();
	}

	/**
	 * Gets the plugin file path.
	 *
	 * @return string
	 */
	public function get_plugin_file_path(): string {
		return WCPAY_PLUGIN_FILE;
	}

	/**
	 * Gets the plugin version.
	 *
	 * @return string
	 */
	public function get_plugin_version(): string {
		return WCPAY_VERSION_NUMBER;
	}
}

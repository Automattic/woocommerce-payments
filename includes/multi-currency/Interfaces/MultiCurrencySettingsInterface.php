<?php
/**
 * Interface MultiCurrencySettingsInterface
 *
 * @package WooCommerce\Payments\MultiCurrency\Interfaces
 */

namespace WCPay\MultiCurrency\Interfaces;

defined( 'ABSPATH' ) || exit;

interface MultiCurrencySettingsInterface {

	/**
	 * Checks if dev mode is enabled.
	 *
	 * @return bool
	 */
	public function is_dev_mode(): bool;

	/**
	 * Gets the plugin file path.
	 *
	 * @return string
	 */
	public function get_plugin_file_path(): string;

	/**
	 * Gets the plugin version.
	 *
	 * @return string
	 */
	public function get_plugin_version(): string;
}

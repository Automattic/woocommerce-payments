<?php
/**
 * Class Payment_Request_To_Digital_Wallets_Settings
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Payment_Request_To_Digital_Wallets_Settings
 *
 * Renames settings from "payment_request_*" to "*digital_wallets_*" and remaps values for button type.
 *
 * @since 2.6.0
 */
class Payment_Request_To_Digital_Wallets_Settings {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '2.6.0';

	/**
	 * Value of the `woocommerce_woocommerce_payments_version` option that will be modified.
	 *
	 * @var array
	 */
	private $wcpay_settings;

	/**
	 * Only execute the migration if not applied yet.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( self::VERSION_SINCE, $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Does the actual migration as described in the class docblock.
	 */
	private function migrate() {
		$this->wcpay_settings = get_option( 'woocommerce_woocommerce_payments_settings' );

		$this->copy_settings_under_new_names();
		$this->map_button_type_value();

		update_option( 'woocommerce_woocommerce_payments_settings', $this->wcpay_settings );
	}

	/**
	 * Copies setting values to new names.
	 *
	 * If a setting under the old name does not exist, nothing will be written under the new name so defaults can apply.
	 * If a setting under the new name already exists, nothing will be written so it is not overwritten.
	 */
	private function copy_settings_under_new_names() {
		$settings_to_rename = [
			'payment_request'              => 'is_digital_wallets_enabled',
			'payment_request_button_theme' => 'digital_wallets_button_theme',
			'payment_request_button_type'  => 'digital_wallets_button_type',
		];

		foreach ( $settings_to_rename as $old_name => $new_name ) {
			if ( ! isset( $this->wcpay_settings[ $old_name ] ) || isset( $this->wcpay_settings[ $new_name ] ) ) {
				continue;
			}

			$this->wcpay_settings[ $new_name ] = $this->wcpay_settings[ $old_name ];
		}
	}

	/**
	 * Maps the button type value based on updated set of allowed values.
	 */
	private function map_button_type_value() {
		if ( ! isset( $this->wcpay_settings['digital_wallets_button_type'] ) ) {
			return;
		}

		$button_type  = $this->wcpay_settings['digital_wallets_button_type'];
		$branded_type = $this->wcpay_settings['payment_request_button_branded_type'] ?? '';

		if ( 'branded' === $button_type && 'short' === $branded_type ) {
			$button_type = 'default';
		} elseif ( 'branded' === $button_type && 'short' !== $branded_type ) {
			$button_type = 'buy';
		} elseif ( 'custom' === $button_type ) {
			$button_type = 'buy';
		}

		$this->wcpay_settings['digital_wallets_button_type'] = $button_type;
	}
}

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
	const UPE_FLAG_NAME = '_wcpay_feature_upe';

	/**
	 * Checks whether the grouped settings feature is enabled
	 *
	 * @return bool
	 */
	public static function is_grouped_settings_enabled() {
		return '1' === get_option( '_wcpay_feature_grouped_settings', '1' );
	}

	/**
	 * Checks whether the Giropay gateway feature is enabled
	 *
	 * @return bool
	 */
	public static function is_giropay_enabled() {
		return '1' === get_option( '_wcpay_feature_giropay', '0' );
	}

	/**
	 * Checks whether the Sepa gateway feature is enabled
	 *
	 * @return bool
	 */
	public static function is_sepa_enabled() {
		return '1' === get_option( '_wcpay_feature_sepa', '0' );
	}

	/**
	 * Checks whether the Sofort gateway feature is enabled
	 *
	 * @return bool
	 */
	public static function is_sofort_enabled() {
		return '1' === get_option( '_wcpay_feature_sofort', '0' );
	}

	/**
	 * Checks whether the UPE gateway is enabled
	 *
	 * @return bool
	 */
	public static function is_upe_enabled() {
		return '1' === get_option( self::UPE_FLAG_NAME, '0' );
	}

	/**
	 * Checks whether the UPE settings redesign is enabled
	 *
	 * @return bool
	 */
	public static function is_upe_settings_preview_enabled() {
		return '1' === get_option( '_wcpay_feature_upe_settings_preview', '0' );
	}

	/**
	 * Checks whether the customer multi-currency feature is enabled
	 *
	 * @return bool
	 */
	public static function is_customer_multi_currency_enabled() {
		return '1' === get_option( '_wcpay_feature_customer_multi_currency', '0' );
	}

	/**
	 * Returns feature flags as an array suitable for display on the front-end.
	 *
	 * @return bool[]
	 */
	public static function to_array() {
		return array_filter(
			[
				'upe'                => self::is_upe_enabled(),
				'upeSettingsPreview' => self::is_upe_settings_preview_enabled(),
			]
		);
	}

	/**
	 * Enable/Disable UPE gateway.
	 *
	 * @param bool $enable True to enable UPE, false otherwise.
	 */
	public static function update_upe_enabled( $enable ) {
		$enabled_flag = true ? 1 : 0 === $enable;
		update_option( '_wcpay_feature_upe', $enabled_flag );
	}
}

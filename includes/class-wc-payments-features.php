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
	const UPE_FLAG_NAME                 = '_wcpay_feature_upe';
	const WCPAY_SUBSCRIPTIONS_FLAG_NAME = '_wcpay_feature_subscriptions';

	/**
	 * Checks whether the UPE gateway is enabled
	 *
	 * @return bool
	 */
	public static function is_upe_enabled() {
		return '1' === get_option( self::UPE_FLAG_NAME, '0' );
	}

	/**
	 * Checks whether the UPE gateway is enabled
	 *
	 * @return bool
	 */
	public static function did_merchant_disable_upe() {
		return 'disabled' === get_option( self::UPE_FLAG_NAME, '0' );
	}

	/**
	 * Checks whether the UPE settings redesign is enabled
	 *
	 * @return bool
	 */
	public static function is_upe_settings_preview_enabled() {
		return '1' === get_option( '_wcpay_feature_upe_settings_preview', '1' );
	}

	/**
	 * Checks whether the customer Multi-Currency feature is enabled
	 *
	 * @return bool
	 */
	public static function is_customer_multi_currency_enabled() {
		return '1' === get_option( '_wcpay_feature_customer_multi_currency', '1' );
	}

	/**
	 * Checks whether Account Overview page is enabled
	 *
	 * @return bool
	 */
	public static function is_account_overview_task_list_enabled() {
		return get_option( '_wcpay_feature_account_overview_task_list' );
	}

	/**
	 * Checks whether WCPay Subscriptions is enabled.
	 *
	 * @return bool
	 */
	public static function is_wcpay_subscriptions_enabled() {
		$enabled = get_option( self::WCPAY_SUBSCRIPTIONS_FLAG_NAME, null );

		// Enable the feature by default for stores that are eligible.
		if ( null === $enabled && function_exists( 'wc_get_base_location' ) && self::is_wcpay_subscriptions_eligible() ) {
			$enabled = '1';
			update_option( self::WCPAY_SUBSCRIPTIONS_FLAG_NAME, $enabled );
		}

		return apply_filters( 'wcpay_is_wcpay_subscriptions_enabled', '1' === $enabled );
	}

	/**
	 * Returns whether WCPay Subscriptions is eligible, based on the stores base country.
	 *
	 * @return bool
	 */
	public static function is_wcpay_subscriptions_eligible() {
		$store_base_location = wc_get_base_location();
		return ! empty( $store_base_location['country'] ) && 'US' === $store_base_location['country'];
	}

	/**
	 * Checks whether platform checkout is enabled.
	 *
	 * @return bool
	 */
	public static function is_platform_checkout_eligible() {
		$account = WC_Payments::get_database_cache()->get( WCPay\Database_Cache::ACCOUNT_KEY );
		return is_array( $account ) && ( $account['platform_checkout_eligible'] ?? false );
	}

	/**
	 * Checks whether documents section is enabled.
	 *
	 * @return bool
	 */
	public static function is_documents_section_enabled() {
		return '1' === get_option( '_wcpay_feature_documents', '0' );
	}

	/**
	 * Returns feature flags as an array suitable for display on the front-end.
	 *
	 * @return bool[]
	 */
	public static function to_array() {
		return array_filter(
			[
				'upe'                     => self::is_upe_enabled(),
				'upeSettingsPreview'      => self::is_upe_settings_preview_enabled(),
				'multiCurrency'           => self::is_customer_multi_currency_enabled(),
				'accountOverviewTaskList' => self::is_account_overview_task_list_enabled(),
				'platformCheckout'        => self::is_platform_checkout_eligible(),
				'documents'               => self::is_documents_section_enabled(),
			]
		);
	}
}

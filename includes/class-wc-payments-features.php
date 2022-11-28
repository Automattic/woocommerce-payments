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
	const UPE_FLAG_NAME                     = '_wcpay_feature_upe';
	const WCPAY_SUBSCRIPTIONS_FLAG_NAME     = '_wcpay_feature_subscriptions';
	const WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME = '_wcpay_feature_woopay_express_checkout';
	const AUTH_AND_CAPTURE_FLAG_NAME        = '_wcpay_feature_auth_and_capture';

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
	 * Returns if the encryption libraries are loaded and the encrypt method exists.
	 *
	 * @return bool
	 */
	public static function is_client_secret_encryption_eligible() {
		return extension_loaded( 'openssl' ) && function_exists( 'openssl_encrypt' );
	}

	/**
	 * Checks whether the client secret encryption feature is enabled.
	 *
	 * @return  bool
	 */
	public static function is_client_secret_encryption_enabled() {
		$enabled = '1' === get_option( '_wcpay_feature_client_secret_encryption', '0' );
		// Check if it can be enabled when it's enabled, it needs openssl to operate.
		if ( $enabled && ! self::is_client_secret_encryption_eligible() ) {
			update_option( '_wcpay_feature_client_secret_encryption', '0' );
			$enabled = false;
		}
		return $enabled;
	}

	/**
	 * Checks whether Account Overview page is enabled
	 *
	 * @return bool
	 */
	public static function is_account_overview_task_list_enabled() {
		return get_option( '_wcpay_feature_account_overview_task_list', '1' );
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
		// read directly from cache, ignore cache expiration check.
		$account = WC_Payments::get_database_cache()->get( WCPay\Database_Cache::ACCOUNT_KEY, true );
		return is_array( $account ) && ( $account['platform_checkout_eligible'] ?? false );
	}

	/**
	 * Checks whether documents section is enabled.
	 *
	 * @return bool
	 */
	public static function is_documents_section_enabled() {
		$account              = WC_Payments::get_database_cache()->get( WCPay\Database_Cache::ACCOUNT_KEY );
		$is_documents_enabled = is_array( $account ) && ( $account['is_documents_enabled'] ?? false );
		return '1' === get_option( '_wcpay_feature_documents', $is_documents_enabled ? '1' : '0' );
	}

	/**
	 * Checks whether custom deposit schedules are enabled.
	 *
	 * @return bool
	 */
	public static function is_custom_deposit_schedules_enabled() {
		return '1' === get_option( '_wcpay_feature_custom_deposit_schedules', '1' );
	}

	/**
	 * Checks whether WooPay Express Checkout is enabled.
	 *
	 * @return bool
	 */
	public static function is_woopay_express_checkout_enabled() {
		// Confirm platform checkout eligibility as well.
		return '1' === get_option( self::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '0' ) && self::is_platform_checkout_eligible();
	}

	/**
	 * Checks whether Auth & Capture (uncaptured transactions tab, capture from payment details page) is enabled.
	 *
	 * @return bool
	 */
	public static function is_auth_and_capture_enabled() {
		return '1' === get_option( self::AUTH_AND_CAPTURE_FLAG_NAME, '1' );
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
				'customDepositSchedules'  => self::is_custom_deposit_schedules_enabled(),
				'clientSecretEncryption'  => self::is_client_secret_encryption_enabled(),
				'woopayExpressCheckout'   => self::is_woopay_express_checkout_enabled(),
				'isAuthAndCaptureEnabled' => self::is_auth_and_capture_enabled(),
			]
		);
	}
}

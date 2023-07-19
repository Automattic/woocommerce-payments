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
	const UPE_SPLIT_FLAG_NAME               = '_wcpay_feature_upe_split';
	const UPE_DEFERRED_INTENT_FLAG_NAME     = '_wcpay_feature_upe_deferred_intent';
	const WCPAY_SUBSCRIPTIONS_FLAG_NAME     = '_wcpay_feature_subscriptions';
	const WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME = '_wcpay_feature_woopay_express_checkout';
	const AUTH_AND_CAPTURE_FLAG_NAME        = '_wcpay_feature_auth_and_capture';
	const PROGRESSIVE_ONBOARDING_FLAG_NAME  = '_wcpay_feature_progressive_onboarding';
	const MC_ORDER_META_HELPER_FLAG_NAME    = '_wcpay_feature_mc_order_meta_helper';

	/**
	 * Checks whether any UPE gateway is enabled.
	 *
	 * @return bool
	 */
	public static function is_upe_enabled() {
		return self::is_upe_legacy_enabled() || self::is_upe_split_enabled() || self::is_upe_deferred_intent_enabled();
	}

	/**
	 * Returns the "type" of UPE that will be displayed at checkout.
	 *
	 * @return string
	 */
	public static function get_enabled_upe_type() {
		if ( self::is_upe_split_enabled() || self::is_upe_deferred_intent_enabled() ) {
			return 'split';
		}

		if ( self::is_upe_legacy_enabled() ) {
			return 'legacy';
		}

		return '';
	}

	/**
	 * Checks whether the legacy UPE gateway is enabled
	 *
	 * @return bool
	 */
	public static function is_upe_legacy_enabled() {
		$upe_flag_value = '1' === get_option( self::UPE_FLAG_NAME, '0' );
		if ( $upe_flag_value ) {
			return true;
		}

		$upe_split_flag_value    = '1' === get_option( self::UPE_SPLIT_FLAG_NAME, '0' );
		$upe_deferred_flag_value = '1' === get_option( self::UPE_DEFERRED_INTENT_FLAG_NAME, '0' );

		// if the merchant is not eligible for the Split UPE, but they have the flag enabled, fallback to the "legacy" UPE (for now).
		return ( $upe_split_flag_value || $upe_deferred_flag_value )
			&& ! self::is_upe_split_eligible();
	}

	/**
	 * Checks whether the Split-UPE gateway is enabled
	 */
	public static function is_upe_split_enabled() {
		return '1' === get_option( self::UPE_SPLIT_FLAG_NAME, '0' ) && self::is_upe_split_eligible();
	}

	/**
	 * Checks whether the Split UPE with deferred intent is enabled
	 */
	public static function is_upe_deferred_intent_enabled() {
		return '1' === get_option( self::UPE_DEFERRED_INTENT_FLAG_NAME, '0' ) && self::is_upe_split_eligible();
	}

	/**
	 * Checks for the requirements to have the split-UPE enabled.
	 */
	private static function is_upe_split_eligible() {
		$account = WC_Payments::get_database_cache()->get( WCPay\Database_Cache::ACCOUNT_KEY, true );
		if ( empty( $account['capabilities']['sepa_debit_payments'] ) ) {
			return true;
		}

		return 'active' !== $account['capabilities']['sepa_debit_payments'];
	}

	/**
	 * Checks whether the UPE gateway is enabled
	 *
	 * @return bool
	 */
	public static function did_merchant_disable_upe() {
		return 'disabled' === get_option( self::UPE_FLAG_NAME, '0' ) || 'disabled' === get_option( self::UPE_SPLIT_FLAG_NAME, '0' );
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
	 * Indicates whether card payments are enabled for this (Stripe) account.
	 *
	 * @return bool True if account can accept card payments, false otherwise.
	 */
	public static function are_payments_enabled() {
		$account = WC_Payments::get_database_cache()->get( WCPay\Database_Cache::ACCOUNT_KEY, true );

		return is_array( $account ) && ( $account['payments_enabled'] ?? false );
	}

	/**
	 * Checks if WooPay is enabled.
	 *
	 * @return bool
	 */
	public static function is_woopay_enabled() {
		$is_woopay_eligible               = self::is_woopay_eligible(); // Feature flag.
		$is_woopay_enabled                = 'yes' === WC_Payments::get_gateway()->get_option( 'platform_checkout' );
		$is_woopay_express_button_enabled = self::is_woopay_express_checkout_enabled();

		return $is_woopay_eligible && $is_woopay_enabled && $is_woopay_express_button_enabled;
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
	 * Checks whether WCPay Subscriptions is enabled.
	 *
	 * @return bool
	 */
	public static function is_wcpay_subscriptions_enabled() {
		// After completing the WooCommerce onboarding, check if the merchant has chosen Subscription product types and enable the feature flag.
		if ( (bool) get_option( 'wcpay_check_subscriptions_eligibility_after_onboarding', false ) ) {
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.9.0', '<' ) ) {
				self::maybe_enable_wcpay_subscriptions_after_onboarding( [], get_option( 'woocommerce_onboarding_profile', [] ) );
			}

			delete_option( 'wcpay_check_subscriptions_eligibility_after_onboarding' );
		}

		return apply_filters( 'wcpay_is_wcpay_subscriptions_enabled', '1' === get_option( self::WCPAY_SUBSCRIPTIONS_FLAG_NAME, '0' ) );
	}

	/**
	 * Returns whether WCPay Subscriptions is eligible, based on the stores base country.
	 *
	 * @return bool
	 */
	public static function is_wcpay_subscriptions_eligible() {
		if ( ! function_exists( 'wc_get_base_location' ) ) {
			return false;
		}

		$store_base_location = wc_get_base_location();
		return ! empty( $store_base_location['country'] ) && 'US' === $store_base_location['country'];
	}

	/**
	 * Checks whether the merchant has chosen Subscription product types during onboarding
	 * WooCommerce and is elible for WCPay Subscriptions, if so, enables the feature flag.
	 *
	 * @since 6.2.0
	 *
	 * @param array $onboarding_data Onboarding data.
	 * @param array $updated         Updated onboarding settings.
	 *
	 * @return void
	 */
	public static function maybe_enable_wcpay_subscriptions_after_onboarding( $onboarding_data, $updated ) {
		if ( empty( $updated['product_types'] ) || ! is_array( $updated['product_types'] ) || ! in_array( 'subscriptions', $updated['product_types'], true ) ) {
			return;
		}

		if ( ! self::is_wcpay_subscriptions_eligible() ) {
			return;
		}

		update_option( self::WCPAY_SUBSCRIPTIONS_FLAG_NAME, '1' );
	}

	/**
	 * Checks whether woopay is enabled.
	 *
	 * @return bool
	 */
	public static function is_woopay_eligible() {
		// Checks for the dependency on Store API AbstractCartRoute.
		if ( ! class_exists( 'Automattic\WooCommerce\StoreApi\Routes\V1\AbstractCartRoute' ) ) {
			return false;
		}

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
	 * Checks whether WooPay Express Checkout is enabled.
	 *
	 * @return bool
	 */
	public static function is_woopay_express_checkout_enabled() {
		// Confirm woopay eligibility as well.
		return '1' === get_option( self::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' ) && self::is_woopay_eligible();
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
	 * Checks whether Progressive Onboarding is enabled.
	 *
	 * @return bool
	 */
	public static function is_progressive_onboarding_enabled(): bool {
		return '1' === get_option( self::PROGRESSIVE_ONBOARDING_FLAG_NAME, '0' );
	}

	/**
	 * Checks whether the Fraud and Risk Tools feature flag is enabled.
	 *
	 * @return  bool
	 */
	public static function is_frt_review_feature_active(): bool {
		return '1' === get_option( 'wcpay_frt_review_feature_active', '0' );
	}

	/**
	 * Checks whether the Fraud and Risk Tools welcome tour was dismissed.
	 *
	 * @return bool
	 */
	public static function is_fraud_protection_welcome_tour_dismissed(): bool {
		return '1' === get_option( 'wcpay_fraud_protection_welcome_tour_dismissed', '0' );
	}

	/**
	 * Checks whether the BNPL Affirm Afterpay is enabled.
	 */
	public static function is_bnpl_affirm_afterpay_enabled(): bool {
		$account = WC_Payments::get_account_service()->get_cached_account_data();
		return ! isset( $account['is_bnpl_affirm_afterpay_enabled'] ) || true === $account['is_bnpl_affirm_afterpay_enabled'];
	}

	/**
	 * Checks whether Multi-Currency Order Meta Helper is enabled.
	 *
	 * @return bool
	 */
	public static function is_mc_order_meta_helper_enabled(): bool {
		return '1' === get_option( self::MC_ORDER_META_HELPER_FLAG_NAME, '0' );
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
				'upeSplit'                => self::is_upe_split_enabled(),
				'upeDeferred'             => self::is_upe_deferred_intent_enabled(),
				'upeSettingsPreview'      => self::is_upe_settings_preview_enabled(),
				'multiCurrency'           => self::is_customer_multi_currency_enabled(),
				'woopay'                  => self::is_woopay_eligible(),
				'documents'               => self::is_documents_section_enabled(),
				'clientSecretEncryption'  => self::is_client_secret_encryption_enabled(),
				'woopayExpressCheckout'   => self::is_woopay_express_checkout_enabled(),
				'isAuthAndCaptureEnabled' => self::is_auth_and_capture_enabled(),
				'progressiveOnboarding'   => self::is_progressive_onboarding_enabled(),
				'mcOrderMetaHelper'       => self::is_mc_order_meta_helper_enabled(),
			]
		);
	}
}

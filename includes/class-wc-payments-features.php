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
	const DISPUTE_ON_TRANSACTION_PAGE       = '_wcpay_feature_dispute_on_transaction_page';

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
		return ( '1' === get_option( self::UPE_DEFERRED_INTENT_FLAG_NAME, '0' ) && self::is_upe_split_eligible() ) || self::is_upe_split_enabled();
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
	 * Returns whether the store is eligible to use WCPay Subscriptions (the free subscriptions bundled in WooPayments)
	 *
	 * Stores are eligible for the WCPay Subscriptions feature if:
	 * 1. The store has existing WCPay Subscriptions, or
	 * 2. The store has Stripe Billing product metadata on at least 1 product subscription product.
	 *
	 * @return bool
	 */
	public static function is_wcpay_subscriptions_eligible() {
		/**
		 * Check if they have at least 1 WCPay Subscription.
		 *
		 * Note: this is only possible if WCPay Subscriptions is enabled, otherwise the wcs_get_subscriptions function wouldn't exist.
		 */
		if ( function_exists( 'wcs_get_subscriptions' ) ) {
			$wcpay_subscriptions = wcs_get_subscriptions(
				[
					'subscriptions_per_page' => 1,
					'subscription_status'    => 'any',
					'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						[
							'key'     => '_wcpay_subscription_id',
							'compare' => 'EXISTS',
						],
					],
				]
			);

			if ( count( $wcpay_subscriptions ) > 0 ) {
				return true;
			}
		}

		/**
		 * Check if they have at least 1 Stripe Billing enabled product.
		 */
		$stripe_billing_meta_query_handler = function ( $query, $query_vars ) {
			if ( ! empty( $query_vars['stripe_billing_product'] ) ) {
				$query['meta_query'][] = [
					'key'     => '_wcpay_product_hash',
					'compare' => 'EXISTS',
				];
			}

			return $query;
		};

		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', $stripe_billing_meta_query_handler, 10, 2 );

		$subscription_products = wc_get_products(
			[
				'limit'                  => 1,
				'type'                   => [ 'subscription', 'variable-subscription' ],
				'status'                 => 'publish',
				'return'                 => 'ids',
				'stripe_billing_product' => 'true',
			]
		);

		remove_filter( 'woocommerce_product_data_store_cpt_get_products_query', $stripe_billing_meta_query_handler, 10, 2 );

		if ( count( $subscription_products ) > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether Deposits details UI on Transaction Details page is enabled. Disabled by default.
	 *
	 * @return bool
	 */
	public static function is_dispute_on_transaction_page_enabled(): bool {
		return '1' === get_option( self::DISPUTE_ON_TRANSACTION_PAGE, '0' );
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
	 * Returns whether WCPay Subscription migration is enabled
	 *
	 * @return bool
	 */
	public static function is_subscription_migration_enabled() {
		return '1' === get_option( '_wcpay_feature_allow_subscription_migrations', '0' );
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
	 * Returns feature flags as an array suitable for display on the front-end.
	 *
	 * @return bool[]
	 */
	public static function to_array() {
		return array_filter(
			[
				'upe'                               => self::is_upe_enabled(),
				'upeSplit'                          => self::is_upe_split_enabled(),
				'upeDeferred'                       => self::is_upe_deferred_intent_enabled(),
				'upeSettingsPreview'                => self::is_upe_settings_preview_enabled(),
				'multiCurrency'                     => self::is_customer_multi_currency_enabled(),
				'woopay'                            => self::is_woopay_eligible(),
				'documents'                         => self::is_documents_section_enabled(),
				'clientSecretEncryption'            => self::is_client_secret_encryption_enabled(),
				'woopayExpressCheckout'             => self::is_woopay_express_checkout_enabled(),
				'isAuthAndCaptureEnabled'           => self::is_auth_and_capture_enabled(),
				'progressiveOnboarding'             => self::is_progressive_onboarding_enabled(),
				'isDisputeOnTransactionPageEnabled' => self::is_dispute_on_transaction_page_enabled(),
			]
		);
	}
}

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
	const STRIPE_BILLING_FLAG_NAME          = '_wcpay_feature_stripe_billing';
	const WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME = '_wcpay_feature_woopay_express_checkout';
	const WOOPAY_FIRST_PARTY_AUTH_FLAG_NAME = '_wcpay_feature_woopay_first_party_auth';
	const AUTH_AND_CAPTURE_FLAG_NAME        = '_wcpay_feature_auth_and_capture';
	const PROGRESSIVE_ONBOARDING_FLAG_NAME  = '_wcpay_feature_progressive_onboarding';
	const PAY_FOR_ORDER_FLOW                = '_wcpay_feature_pay_for_order_flow';
	const DEFERRED_UPE_SERVER_FLAG_NAME     = 'is_deferred_intent_creation_upe_enabled';
	const DISPUTE_ISSUER_EVIDENCE           = '_wcpay_feature_dispute_issuer_evidence';

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
		// New stores created in 6.4.0 or 6.5.0, and legacy card stores that self-migrated to dUPE in 6.4.0 or 6.5.0.
		$has_store_enabled_dupe_from_previous_plugin_version = self::has_store_enabled_dupe_from_previous_plugin_version();
		$has_store_enabled_upe_and_dupe_server_flag          = self::has_store_enabled_upe_and_dupe_server_flag();

		// Stores which first self-migrated and then had deferred intent creation UPE enabled by default.
		if ( $has_store_enabled_dupe_from_previous_plugin_version && $has_store_enabled_upe_and_dupe_server_flag ) {
			return 'deferred_intent_upe_without_fallback';
		}

		// Stores which have deferred intent creation UPE enabled by default.
		if ( $has_store_enabled_upe_and_dupe_server_flag ) {
			return 'deferred_intent_upe_without_fallback';
		}

		// Stores which self-migrated to dUPE (e.g. legacy card stores from 6.4.0 or 6.5.0).
		if ( $has_store_enabled_dupe_from_previous_plugin_version ) {
			return 'deferred_intent_upe_with_fallback';
		}

		if ( self::is_upe_split_enabled() ) {
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
		return '1' === get_option( self::UPE_FLAG_NAME, '0' );
	}

	/**
	 * Checks whether the Split-UPE gateway is enabled
	 */
	public static function is_upe_split_enabled() {
		return '1' === get_option( self::UPE_SPLIT_FLAG_NAME, '0' );
	}

	/**
	 * Checks whether the Split UPE with deferred intent creation is enabled
	 */
	public static function is_upe_deferred_intent_enabled() {
		// Support new stores created in 6.4.0 or 6.5.0, and legacy card stores that migrated to deferred UPE in 6.4.0 or 6.5.0.
		$has_store_enabled_dupe_from_previous_plugin_version = self::has_store_enabled_dupe_from_previous_plugin_version();
		$has_store_enabled_upe_and_dupe_server_flag          = self::has_store_enabled_upe_and_dupe_server_flag();

		return $has_store_enabled_dupe_from_previous_plugin_version || $has_store_enabled_upe_and_dupe_server_flag;
	}

	/**
	 * Checks if the store has deferred intent creation UPE enabled from a previous version.
	 * This is applicable to:
	 * * legacy card stores that self-migrated to deferred UPE in 6.4.0 or 6.5.0
	 * * new stores starting 6.4.0
	 */
	private static function has_store_enabled_dupe_from_previous_plugin_version() {
		return '1' === get_option( self::UPE_DEFERRED_INTENT_FLAG_NAME, '0' );
	}

	/**
	 * Checks if the store has UPE enabled and the server-side feature flag is enabled.
	 * This is applicable to:
	 * * Split UPE stores starting 6.4.0
	 * * Legacy UPE stores starting 6.6.0
	 */
	private static function has_store_enabled_upe_and_dupe_server_flag() {
		return ( self::is_upe_split_enabled() || self::is_upe_legacy_enabled() ) && self::is_deferred_upe_server_flag_enabled();
	}


	/**
	 * Checks if the Deferred UPE server-side feature flag is enabled.
	 * The flag should be always returned, and if it's not present, server assumes it's enabled.
	 */
	private static function is_deferred_upe_server_flag_enabled() {
		$account = WC_Payments::get_database_cache()->get( WCPay\Database_Cache::ACCOUNT_KEY, true );
		return is_array( $account ) && ( $account[ self::DEFERRED_UPE_SERVER_FLAG_NAME ] ?? false );
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
	 * Checks whether WooPay First Party Auth is enabled.
	 *
	 * @return bool
	 */
	public static function is_woopay_first_party_auth_enabled() {
		return '1' === get_option( self::WOOPAY_FIRST_PARTY_AUTH_FLAG_NAME, '1' ) && self::is_woopay_express_checkout_enabled();
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
	 * Checks whether the Stripe Billing feature is enabled.
	 *
	 * @return bool
	 */
	public static function is_stripe_billing_enabled(): bool {
		return '1' === get_option( self::STRIPE_BILLING_FLAG_NAME, '0' );
	}

	/**
	 * Checks if the site is eligible for Stripe Billing.
	 *
	 * Only US merchants are eligible for Stripe Billing.
	 *
	 * @return bool
	 */
	public static function is_stripe_billing_eligible() {
		if ( ! function_exists( 'wc_get_base_location' ) ) {
			return false;
		}

		$store_base_location = wc_get_base_location();
		return ! empty( $store_base_location['country'] ) && 'US' === $store_base_location['country'];
	}

	/**
	 * Checks whether the merchant is using WCPay Subscription or opted into Stripe Billing.
	 *
	 * Note: Stripe Billing is only used when the merchant is using WooCommerce Subscriptions and turned it on or is still using WCPay Subscriptions.
	 *
	 * @return bool
	 */
	public static function should_use_stripe_billing() {
		// We intentionally check for the existence of the 'WC_Subscriptions' class here as we want to confirm the Plugin is active.
		if ( self::is_wcpay_subscriptions_enabled() && ! class_exists( 'WC_Subscriptions' ) ) {
			return true;
		}

		if ( self::is_stripe_billing_enabled() && class_exists( 'WC_Subscriptions' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether the pay for order flow is enabled
	 *
	 * @return bool
	 */
	public static function is_pay_for_order_flow_enabled() {
		return '1' === get_option( self::PAY_FOR_ORDER_FLOW, '0' );
	}

	/**
	 * Checks whether Dispute issuer evidence feature should be enabled. Disabled by default.
	 *
	 * @return bool
	 */
	public static function is_dispute_issuer_evidence_enabled(): bool {
		return '1' === get_option( self::DISPUTE_ISSUER_EVIDENCE, '0' );
	}

	/**
	 * Returns feature flags as an array suitable for display on the front-end.
	 *
	 * @return bool[]
	 */
	public static function to_array() {
		return array_filter(
			[
				'upe'                            => self::is_upe_enabled(),
				'upeSplit'                       => self::is_upe_split_enabled(),
				'upeDeferred'                    => self::is_upe_deferred_intent_enabled(),
				'upeSettingsPreview'             => self::is_upe_settings_preview_enabled(),
				'multiCurrency'                  => self::is_customer_multi_currency_enabled(),
				'woopay'                         => self::is_woopay_eligible(),
				'documents'                      => self::is_documents_section_enabled(),
				'clientSecretEncryption'         => self::is_client_secret_encryption_enabled(),
				'woopayExpressCheckout'          => self::is_woopay_express_checkout_enabled(),
				'isAuthAndCaptureEnabled'        => self::is_auth_and_capture_enabled(),
				'progressiveOnboarding'          => self::is_progressive_onboarding_enabled(),
				'isPayForOrderFlowEnabled'       => self::is_pay_for_order_flow_enabled(),
				'isDisputeIssuerEvidenceEnabled' => self::is_dispute_issuer_evidence_enabled(),
			]
		);
	}
}

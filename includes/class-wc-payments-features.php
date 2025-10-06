<?php
/**
 * Class WC_Payments_Features
 *
 * @package WooCommerce\Payments
 */

use WCPay\Constants\Country_Code;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC Payments Features class
 */
class WC_Payments_Features {
	/**
	 * If you need to remove or deprecate a flag:
	 * - Please update the `Erase_Deprecated_Flags_And_Options` migration with:
	 *   - The next version of WooPayments.
	 *   - The flag to be deleted.
	 */
	const WCPAY_SUBSCRIPTIONS_FLAG_NAME                       = '_wcpay_feature_subscriptions';
	const STRIPE_BILLING_FLAG_NAME                            = '_wcpay_feature_stripe_billing';
	const WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME                   = '_wcpay_feature_woopay_express_checkout';
	const WOOPAY_FIRST_PARTY_AUTH_FLAG_NAME                   = '_wcpay_feature_woopay_first_party_auth';
	const WOOPAY_DIRECT_CHECKOUT_FLAG_NAME                    = '_wcpay_feature_woopay_direct_checkout';
	const DISPUTE_ISSUER_EVIDENCE                             = '_wcpay_feature_dispute_issuer_evidence';
	const WOOPAY_GLOBAL_THEME_SUPPORT_FLAG_NAME               = '_wcpay_feature_woopay_global_theme_support';
	const WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME = '_wcpay_feature_dynamic_checkout_place_order_button';
	const ACCOUNT_DETAILS_FLAG_NAME                           = '_wcpay_feature_account_details';

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

			if ( ( is_countable( $wcpay_subscriptions ) ? count( $wcpay_subscriptions ) : 0 ) > 0 ) {
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

		if ( ( is_countable( $subscription_products ) ? count( $subscription_products ) : 0 ) > 0 ) {
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

		$is_account_rejected = WC_Payments::get_account_service()->is_account_rejected();

		$is_account_under_review = WC_Payments::get_account_service()->is_account_under_review();

		return is_array( $account )
			&& ( $account['platform_checkout_eligible'] ?? false )
			&& ! $is_account_rejected
			&& ! $is_account_under_review;
	}

	/**
	 * Checks whether documents section is enabled.
	 *
	 * @return bool
	 */
	public static function is_documents_section_enabled() {
		$account = WC_Payments::get_database_cache()->get( WCPay\Database_Cache::ACCOUNT_KEY, true );
		return is_array( $account ) && ( $account['is_documents_enabled'] ?? false );
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
	 * Checks whether WooPay Direct Checkout is enabled.
	 *
	 * @return bool True if Direct Checkout is enabled, false otherwise.
	 */
	public static function is_woopay_direct_checkout_enabled() {
		$account_cache                   = WC_Payments::get_database_cache()->get( WCPay\Database_Cache::ACCOUNT_KEY, true );
		$is_direct_checkout_eligible     = is_array( $account_cache ) && ( $account_cache['platform_direct_checkout_eligible'] ?? false );
		$is_direct_checkout_flag_enabled = '1' === get_option( self::WOOPAY_DIRECT_CHECKOUT_FLAG_NAME, '1' );

		return $is_direct_checkout_eligible && $is_direct_checkout_flag_enabled && self::is_woopayments_gateway_enabled() && self::is_woopay_enabled();
	}

	/**
	 * Checks whether WooPay global theme support is eligible.
	 *
	 * @return bool
	 */
	public static function is_woopay_global_theme_support_eligible() {
		$account_cache = WC_Payments::get_database_cache()->get( WCPay\Database_Cache::ACCOUNT_KEY, true );

		return is_array( $account_cache ) && ( $account_cache['platform_global_theme_support_enabled'] ?? false );
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
		return ! empty( $store_base_location['country'] ) && Country_Code::UNITED_STATES === $store_base_location['country'];
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
	 * Checks whether Dispute issuer evidence feature should be enabled. Disabled by default.
	 *
	 * @return bool
	 */
	public static function is_dispute_issuer_evidence_enabled(): bool {
		return '1' === get_option( self::DISPUTE_ISSUER_EVIDENCE, '0' );
	}

	/**
	 * Checks whether the next deposit notice on the deposits list screen has been dismissed.
	 *
	 * @return bool
	 */
	public static function is_next_deposit_notice_dismissed(): bool {
		return '1' === get_option( 'wcpay_next_deposit_notice_dismissed', '0' );
	}

	/**
	 * Checks whether the dynamic checkout place order button feature is enabled.
	 *
	 * @return bool
	 */
	public static function is_dynamic_checkout_place_order_button_enabled(): bool {
		return '1' === get_option( self::WCPAY_DYNAMIC_CHECKOUT_PLACE_ORDER_BUTTON_FLAG_NAME, '0' );
	}

	/**
	 * Checks whether the Account Details feature is enabled.
	 *
	 * @return bool
	 */
	public static function is_account_details_enabled(): bool {
		return '1' === get_option( self::ACCOUNT_DETAILS_FLAG_NAME, '0' );
	}

	/**
	 * Returns feature flags as an array suitable for display on the front-end.
	 *
	 * @return bool[]
	 */
	public static function to_array() {
		return array_filter(
			[
				'multiCurrency'                            => self::is_customer_multi_currency_enabled(),
				'woopay'                                   => self::is_woopay_eligible(),
				'documents'                                => self::is_documents_section_enabled(),
				'woopayExpressCheckout'                    => self::is_woopay_express_checkout_enabled(),
				'isDisputeIssuerEvidenceEnabled'           => self::is_dispute_issuer_evidence_enabled(),
				'isFRTReviewFeatureActive'                 => self::is_frt_review_feature_active(),
				'isDynamicCheckoutPlaceOrderButtonEnabled' => self::is_dynamic_checkout_place_order_button_enabled(),
				'isAccountDetailsEnabled'                  => self::is_account_details_enabled(),
			]
		);
	}

	/**
	 * Checks if WooCommerce Payments gateway is enabled.
	 *
	 * @return bool True if WooCommerce Payments gateway is enabled, false otherwise.
	 */
	private static function is_woopayments_gateway_enabled() {
		$woopayments_settings        = get_option( 'woocommerce_woocommerce_payments_settings' );
		$woopayments_enabled_setting = $woopayments_settings['enabled'] ?? 'no';

		return 'yes' === $woopayments_enabled_setting;
	}
}

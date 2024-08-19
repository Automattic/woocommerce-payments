<?php
/**
 * Class Erase_Deprecated_Flags_And_Options
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Erase_Deprecated_Flags_And_Options
 *
 * Clearing up all flags and options that are no longer in use.
 *
 * @since 8.1.0
 */
class Erase_Deprecated_Flags_And_Options {
	/**
	 * Checks whether it's worth doing the migration.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		// feel free to modify the version here to the next one, if you add a new flag to be deleted in the `migrate` method.
		if ( version_compare( '8.1.0', $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Does the actual migration.
	 */
	private function migrate() {
		// please update as necessary, when feature flags are removed.
		// you don't need to check for previous values or if the migration already ran in previous versions.
		// if the migration did already run, the operations below will just be noop.
		delete_option( '_wcpay_feature_progressive_onboarding' );
		delete_option( '_wcpay_feature_client_secret_encryption' );
		delete_option( '_wcpay_feature_allow_subscription_migrations' );
		delete_option( '_wcpay_feature_custom_deposit_schedules' );
		delete_option( '_wcpay_feature_account_overview_task_list' );
		delete_option( '_wcpay_feature_account_overview' );
		delete_option( '_wcpay_feature_sepa' );
		delete_option( '_wcpay_feature_sofort' );
		delete_option( '_wcpay_feature_giropay' );
		delete_option( '_wcpay_feature_grouped_settings' );
		delete_option( '_wcpay_feature_upe_settings_preview' );
		delete_option( '_wcpay_feature_upe' );
		delete_option( '_wcpay_feature_upe_split' );
		delete_option( '_wcpay_feature_upe_deferred_intent' );
		delete_option( '_wcpay_feature_dispute_on_transaction_page' );
		delete_option( '_wcpay_feature_streamline_refunds' );
		delete_option( 'wcpay_fraud_protection_settings_active' );
		delete_option( '_wcpay_feature_mc_order_meta_helper' );
		delete_option( '_wcpay_feature_pay_for_order_flow' );
		delete_option( '_wcpay_feature_simplify_deposits_ui' );
		delete_option( '_wcpay_fraud_protection_settings_enabled' );
		delete_option( '_wcpay_feature_platform_checkout_subscriptions_enabled' );
		delete_option( '_wcpay_feature_platform_checkout' );
		delete_option( '_wcpay_feature_capital' );
	}
}

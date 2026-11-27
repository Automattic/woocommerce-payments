<?php
/**
 * Plugin Name: WooPayments QIT Dev Tools equivalents
 * Description: The two WooPayments Dev Tools hooks the QIT E2E tests rely on. QIT cannot install Dev Tools.
 *
 * Copies `maybe_force_disconnected` and `maybe_force_card_testing_protection_on` from
 * woocommerce-payments-dev-tools, keyed by the same options, so the tests toggle the same mechanism legacy E2E does.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Database_Cache;

add_action(
	'init',
	function () {
		if ( get_option( 'wcpaydev_force_disconnected', false ) && class_exists( 'WC_Payments', false ) ) {
			WC_Payments::get_database_cache()->add( Database_Cache::ACCOUNT_KEY, [] );
		}
	}
);

add_action(
	'woocommerce_payments_account_refreshed',
	function ( $account ) {
		if ( ! get_option( 'wcpaydev_force_card_testing_protection_on', '0' ) || empty( $account ) ) {
			return;
		}

		$account['card_testing_protection_eligible'] = true;
		WC_Payments::get_database_cache()->add( Database_Cache::ACCOUNT_KEY, $account );
	}
);

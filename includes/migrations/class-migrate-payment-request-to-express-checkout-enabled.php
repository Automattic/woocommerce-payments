<?php
/**
 * Class Migrate_Payment_Request_To_Express_Checkout_Enabled
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

use WC_Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Class Migrate_Payment_Request_To_Express_Checkout_Enabled
 *
 * Migrates the payment_request setting to Google Pay and Apple Pay enabled settings.
 *
 * @since 10.3.0
 */
class Migrate_Payment_Request_To_Express_Checkout_Enabled {

	/**
	 * Execute the migration if the payment_request setting exists.
	 */
	public function maybe_migrate() {
		// Get card gateway's settings.
		$card_settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );

		// Check if payment_request setting exists (indicates migration not yet done).
		if ( ! isset( $card_settings['payment_request'] ) ) {
			return;
		}

		$this->migrate();
	}

	/**
	 * Does the actual migration as described in the class docblock.
	 */
	private function migrate() {
		// Get card gateway's settings.
		$card_settings           = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$payment_request_enabled = ( $card_settings['payment_request'] ?? 'no' ) === 'yes' ? 'yes' : 'no';

		// Update Google Pay enabled setting.
		$google_pay_gateway = WC_Payments::get_payment_gateway_by_id( 'google_pay' );
		if ( $google_pay_gateway ) {
			$google_pay_gateway->update_option( 'enabled', $payment_request_enabled );
		}

		// Update Apple Pay enabled setting.
		$apple_pay_gateway = WC_Payments::get_payment_gateway_by_id( 'apple_pay' );
		if ( $apple_pay_gateway ) {
			$apple_pay_gateway->update_option( 'enabled', $payment_request_enabled );
		}

		// Delete the payment_request setting from card gateway.
		unset( $card_settings['payment_request'] );
		update_option( 'woocommerce_woocommerce_payments_settings', $card_settings );
	}
}

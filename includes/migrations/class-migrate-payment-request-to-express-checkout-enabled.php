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
 * In PR #11144, Google Pay and Apple Pay were split into individual payment gateways.
 * In PR #11182, the `payment_request` setting on the card gateway was replaced with
 * individual `enabled` settings on each of the new gateways.
 *
 * This migration transfers the old `payment_request` value to the new gateway settings
 * and removes it from the card gateway.
 *
 * @see https://github.com/Automattic/woocommerce-payments/pull/11144
 * @see https://github.com/Automattic/woocommerce-payments/pull/11182
 * @since 10.4.0
 */
class Migrate_Payment_Request_To_Express_Checkout_Enabled {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '10.4.0';

	/**
	 * Execute the migration if upgrading from a version before 10.4.0
	 * and the payment_request setting exists.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( self::VERSION_SINCE, $previous_version, '<=' ) ) {
			return;
		}

		$card_settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		if ( ! isset( $card_settings['payment_request'] ) ) {
			return;
		}

		$this->migrate( $card_settings );
	}

	/**
	 * Does the actual migration as described in the class docblock.
	 *
	 * @param array $card_settings The card gateway settings.
	 */
	private function migrate( $card_settings ) {
		$payment_request_enabled = ( $card_settings['payment_request'] ?? 'no' ) === 'yes' ? 'yes' : 'no';

		update_option( 'woocommerce_woocommerce_payments_apple_pay_settings', [ 'enabled' => $payment_request_enabled ], true );
		update_option( 'woocommerce_woocommerce_payments_google_pay_settings', [ 'enabled' => $payment_request_enabled ], true );

		unset( $card_settings['payment_request'] );
		update_option( 'woocommerce_woocommerce_payments_settings', $card_settings );
	}
}

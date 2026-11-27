<?php
/**
 * Class Link_WooPay_Mutual_Exclusion_Cleanup
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

use WC_Payment_Gateway_WCPay;
use WC_Payments_Utils;
use WCPay\PaymentMethods\Configs\Definitions\LinkDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * Turns off Link when both methods are enabled so the store can keep its existing WooPay checkout.
 *
 * WooPay uses the platform account for card checkout. Stripe can't share Link payment
 * methods from that account with the merchant's account.
 *
 * @since 11.4.0
 */
class Link_WooPay_Mutual_Exclusion_Cleanup {

	/**
	 * Version in which this migration runs.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '11.4.0';

	/**
	 * WCPay gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $main_gateway;

	/**
	 * All registered gateways.
	 *
	 * @var WC_Payment_Gateway_WCPay[]
	 */
	private $all_registered_gateways;

	/**
	 * Link_WooPay_Mutual_Exclusion_Cleanup constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay   $main_gateway WCPay gateway.
	 * @param WC_Payment_Gateway_WCPay[] $all_registered_gateways All registered gateways.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $main_gateway, array $all_registered_gateways ) {
		$this->main_gateway            = $main_gateway;
		$this->all_registered_gateways = $all_registered_gateways;
	}

	/**
	 * Only execute the migration if not applied yet.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( self::VERSION_SINCE, $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Removes Link from every gateway's list of enabled payment methods and disables the Link gateway.
	 */
	private function migrate() {
		// A store that can't use WooPay yet may qualify after this one-time cleanup.
		if ( 'yes' !== $this->main_gateway->get_option( 'platform_checkout', 'no' ) ) {
			return;
		}

		$link_id                 = LinkDefinition::get_id();
		$enabled_payment_methods = $this->main_gateway->get_upe_enabled_payment_method_ids();
		if ( ! in_array( $link_id, $enabled_payment_methods, true ) ) {
			return;
		}

		WC_Payments_Utils::log_to_wc( 'Disabling Link during the WooPayments update because WooPay is enabled.', 'info' );

		$filtered_payment_methods = array_values( array_diff( $enabled_payment_methods, [ $link_id ] ) );

		foreach ( $this->all_registered_gateways as $gateway ) {
			if ( $link_id === $gateway->get_stripe_id() ) {
				$gateway->disable();
			}
			$gateway->update_option( 'upe_enabled_payment_method_ids', $filtered_payment_methods );
		}
	}
}

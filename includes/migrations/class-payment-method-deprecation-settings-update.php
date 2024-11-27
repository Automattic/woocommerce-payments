<?php
/**
 * Class Payment_Method_Deprecation_Settings_Update
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

use WC_Payment_Gateway_WCPay;

defined( 'ABSPATH' ) || exit;

/**
 * Class Payment_Method_Deprecation_Settings_Update
 *
 * Aligns settings object for every gateway to support new approach of settings handling without the need of using the settings controller.
 */
class Payment_Method_Deprecation_Settings_Update {
	/**
	 * WCPay gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $main_gateway;

	/**
	 * All registered gateways.
	 *
	 * @var array
	 */
	private $all_registered_gateways;

	/**
	 * Payment method type ID.
	 *
	 * @var string
	 */
	private $payment_method_id;

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	private $migration_version;

	/**
	 * Gateway_Settings_Sync constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $main_gateway WCPay gateway.
	 * @param array                    $all_registered_gateways All registered gateways.
	 * @param string                   $payment_method_id Stripe payment method ID of payment method to deprecate.
	 * @param string                   $migration_version Plugin version after which migration should run.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $main_gateway, $all_registered_gateways, $payment_method_id, $migration_version ) {
		$this->main_gateway            = $main_gateway;
		$this->all_registered_gateways = $all_registered_gateways;
		$this->payment_method_id       = $payment_method_id;
		$this->migration_version       = $migration_version;
	}

	/**
	 * Checks whether we should trigger the event.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( $this->migration_version, $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Syncs gateway setting objects.
	 */
	private function migrate() {
		$enabled_payment_methods = $this->main_gateway->get_option( 'upe_enabled_payment_method_ids', [] );

		$filtered_payment_methods = array_filter(
			$enabled_payment_methods,
			function ( $method ) {
				return $this->payment_method_id !== $method;
			}
		);

		foreach ( $this->all_registered_gateways as $gateway ) {
			if ( $this->payment_method_id === $gateway->get_stripe_id() ) {
				if ( in_array( $gateway->get_stripe_id(), $enabled_payment_methods, true ) ) {
					$gateway->disable();
					$gateway->update_option( 'upe_enabled_payment_method_ids', $filtered_payment_methods );
				} else {
					$gateway->update_option( 'upe_enabled_payment_method_ids', $filtered_payment_methods );
				}
			} else {
				$gateway->update_option( 'upe_enabled_payment_method_ids', $filtered_payment_methods );
			}
		}
	}
}

<?php
/**
 * Class Giropay_Deprecation_Settings_Update
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

use WC_Payment_Gateway_WCPay;

defined( 'ABSPATH' ) || exit;

/**
 * Class Delete_Active_WooPay_Webhook
 *
 * Aligns settings object for every gateway to support new approach of settings handling without the need of using the settings controller.
 */
class Giropay_Deprecation_Settings_Update {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '7.9.0';

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
	 * Gateway_Settings_Sync constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $main_gateway WCPay gateway.
	 * @param array                    $all_registered_gateways All registered gateways.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $main_gateway, $all_registered_gateways ) {
		$this->main_gateway            = $main_gateway;
		$this->all_registered_gateways = $all_registered_gateways;
	}

	/**
	 * Checks whether we should trigger the event.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( self::VERSION_SINCE, $previous_version, '>' ) ) {
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
				return 'giropay' !== $method;
			}
		);

		foreach ( $this->all_registered_gateways as $gateway ) {
			if ( 'giropay' === $gateway->get_stripe_id() ) {
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

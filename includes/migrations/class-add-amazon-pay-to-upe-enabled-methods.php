<?php
/**
 * Class Add_Amazon_Pay_To_Upe_Enabled_Methods
 *
 * @package WooCommerce\Payments
 * @since 10.7.0
 */

namespace WCPay\Migrations;

use WC_Payment_Gateway_WCPay;
use WCPay\Constants\Payment_Method;

defined( 'ABSPATH' ) || exit;

/**
 * Migrates Amazon Pay from a standalone feature flag to the upe_enabled_payment_method_ids list.
 *
 * Previously, Amazon Pay was controlled by the `_wcpay_feature_amazon_pay` option (default '1').
 * This migration reads that flag and syncs its state into `upe_enabled_payment_method_ids`,
 * which is now the single source of truth for all payment method enabled states.
 */
class Add_Amazon_Pay_To_Upe_Enabled_Methods {
	/**
	 * Version in which this migration was introduced.
	 */
	const VERSION_SINCE = '10.7.0';

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
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $main_gateway            WCPay gateway.
	 * @param array                    $all_registered_gateways All registered gateways.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $main_gateway, $all_registered_gateways ) {
		$this->main_gateway            = $main_gateway;
		$this->all_registered_gateways = $all_registered_gateways;
	}

	/**
	 * Checks whether the migration should run.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( self::VERSION_SINCE, $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Migrates the Amazon Pay feature flag state into upe_enabled_payment_method_ids.
	 */
	private function migrate() {
		$feature_flag            = get_option( '_wcpay_feature_amazon_pay', '1' );
		$enabled_payment_methods = $this->main_gateway->get_option( 'upe_enabled_payment_method_ids', [] );
		$amazon_pay_in_list      = in_array( Payment_Method::AMAZON_PAY, $enabled_payment_methods, true );
		$updated_payment_methods = $enabled_payment_methods;

		if ( '1' === $feature_flag && ! $amazon_pay_in_list ) {
			$updated_payment_methods[] = Payment_Method::AMAZON_PAY;
		} elseif ( '0' === $feature_flag && $amazon_pay_in_list ) {
			$updated_payment_methods = array_values(
				array_filter(
					$updated_payment_methods,
					function ( $method ) {
						return Payment_Method::AMAZON_PAY !== $method;
					}
				)
			);
		}

		foreach ( $this->all_registered_gateways as $gateway ) {
			$gateway->update_option( 'upe_enabled_payment_method_ids', $updated_payment_methods );

			if ( Payment_Method::AMAZON_PAY === $gateway->get_stripe_id() ) {
				if ( '1' === $feature_flag ) {
					$gateway->enable();
				} else {
					$gateway->disable();
				}
			}
		}

		delete_option( '_wcpay_feature_amazon_pay' );
	}
}

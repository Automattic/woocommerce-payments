<?php
/**
 * Class Manual_Capture_Payment_Method_Settings_Update
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

use WCPay\Constants\Payment_Method;
use WC_Payment_Gateway_WCPay;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manual_Capture_Payment_Method_Settings_Update
 *
 * Updates payment methods when manual capture is enabled.
 */
class Manual_Capture_Payment_Method_Settings_Update {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '8.3.0';

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
	 * Manual_Capture_Payment_Method_Settings_Update constructor.
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
	 * Disables payment methods that do not support manual capture, when manual capture is enabled and updates
	 * the enabled payment methods for each gateway.
	 */
	private function migrate() {
		$enabled_payment_methods   = $this->main_gateway->get_option( 'upe_enabled_payment_method_ids', [] );
		$is_manual_capture_enabled = 'yes' === $this->main_gateway->get_option( 'manual_capture' );

		if ( $is_manual_capture_enabled ) {
			$filtered_payment_methods = array_filter(
				$enabled_payment_methods,
				function ( $method ) {
					return in_array( $method, [ Payment_Method::CARD, Payment_Method::LINK ], true );
				}
			);

			foreach ( $this->all_registered_gateways as $gateway ) {
				$stripe_id = $gateway->get_stripe_id();
				if ( Payment_Method::CARD !== $stripe_id && Payment_Method::LINK !== $stripe_id ) {
					$gateway->disable();
				}
				$gateway->update_option( 'upe_enabled_payment_method_ids', $filtered_payment_methods );
			}
		}
	}
}

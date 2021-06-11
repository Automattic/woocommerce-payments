<?php
/**
 * Class Allowed_Payment_Request_Button_Types_Update
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Allowed_Payment_Request_Button_Types_Update
 *
 * Remaps deprecated payment request button types to fallback values.
 *
 * @since 2.6.0
 */
class Allowed_Payment_Request_Button_Types_Update {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '2.6.0';

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
	 * Does the actual migration as described in the class docblock.
	 */
	private function migrate() {
		$wcpay_settings = get_option( 'woocommerce_woocommerce_payments_settings' );

		if ( ! isset( $wcpay_settings['payment_request_button_type'] ) ) {
			return;
		}

		$button_type  = $wcpay_settings['payment_request_button_type'];
		$branded_type = $wcpay_settings['payment_request_button_branded_type'] ?? '';

		$wcpay_settings['payment_request_button_type'] = $this->map_button_type( $button_type, $branded_type );

		update_option( 'woocommerce_woocommerce_payments_settings', $wcpay_settings );
	}

	/**
	 * Maps deprecated button types to fallback values.
	 *
	 * @param mixed $button_type "payment_request_button_type" value.
	 * @param mixed $branded_type "payment_request_button_branded_type" value.
	 *
	 * @return mixed
	 */
	private function map_button_type( $button_type, $branded_type ) {
		if ( 'branded' === $button_type && 'short' === $branded_type ) {
			return 'default';
		} elseif ( 'branded' === $button_type && 'short' !== $branded_type ) {
			return 'buy';
		} elseif ( 'custom' === $button_type ) {
			return 'buy';
		}

		return $button_type;
	}
}

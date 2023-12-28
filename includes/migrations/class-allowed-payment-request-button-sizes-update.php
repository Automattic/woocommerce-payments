<?php
/**
 * Class Allowed_Payment_Request_Button_Sizes_Update
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

use WC_Payment_Gateway_WCPay;

defined( 'ABSPATH' ) || exit;

/**
 * Class Allowed_Payment_Request_Button_Sizes_Update
 *
 * In version 6.9.0, the "default" size got renamed to "small" - ensure that the settings are up-to-date for existing merchants.
 *
 * @since 6.9.0
 */
class Allowed_Payment_Request_Button_Sizes_Update {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '6.9.0';

	/**
	 * WCPay gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Allowed_Payment_Request_Button_Sizes_Update constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway WCPay gateway.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;
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
	 * Does the actual migration as described in the class docblock.
	 */
	private function migrate() {
		$button_size = $this->gateway->get_option( 'payment_request_button_size' );
		if ( 'default' === $button_size ) {
			$this->gateway->update_option(
				'payment_request_button_size',
				'small'
			);
		}
	}
}

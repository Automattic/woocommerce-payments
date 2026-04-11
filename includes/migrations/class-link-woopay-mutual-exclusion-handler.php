<?php
/**
 * Class Link_WooPay_Mutual_Exclusion_Handler
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

use WC_Payment_Gateway_WCPay;
use WC_Payments_Features;

defined( 'ABSPATH' ) || exit;

/**
 * Class Link_WooPay_Mutual_Exclusion_Handler
 *
 * In version 7.3.0, the logic responsible for disabling Stripe Link if WooPay is by default enabled, is moved from the gateways registration step to the migration.
 *
 * @since 7.3.0
 */
class Link_WooPay_Mutual_Exclusion_Handler {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '7.3.0';

	/**
	 * WCPay gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Link_WooPay_Mutual_Exclusion_Handler constructor.
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
		// check if both Stripe Link and WooPay are enabled and if so - disable Stripe Link.
		$enabled_payment_methods   = $this->gateway->get_payment_method_ids_enabled_at_checkout();
		$enabled_stripe_link_index = array_search( 'link', $enabled_payment_methods, true );

		if ( false !== $enabled_stripe_link_index && WC_Payments_Features::is_woopay_enabled() ) {
			unset( $enabled_payment_methods[ $enabled_stripe_link_index ] );

			$this->gateway->update_option( 'upe_enabled_payment_method_ids', $enabled_payment_methods );
		}
	}
}

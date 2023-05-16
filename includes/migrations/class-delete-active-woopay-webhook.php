<?php
/**
 * Class Delete_Active_WooPay_Webhook
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Delete_Active_WooPay_Webhook
 *
 * Fires an event on plugin upgrade to delete the active webhook.
 * Runs only once. We want to know whether existing install had it
 * enabled before the current version.
 */
class Delete_Active_WooPay_Webhook {
	/**
	 * Checks whether we should trigger the event.
	 */
	public static function maybe_delete() {
		if ( version_compare( get_option( 'woocommerce_woocommerce_payments_version' ), '4.9.0', '>=' ) ) {
			return;
		}

		self::delete();
	}

	/**
	 * Deletes the active webhook.
	 */
	private static function delete() {
		\WCPay\WooPay\WooPay_Order_Status_Sync::remove_webhook();
	}
}

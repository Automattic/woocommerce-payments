<?php
/**
 * Class Erase_Bnpl_Announcement_Meta
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Erase_Bnpl_Announcement_Meta
 *
 * Clearing up all meta data related to the BNPL April 2024 announcement.
 *
 * @since 8.1.0
 */
class Erase_Bnpl_Announcement_Meta {
	/**
	 * Checks whether it's worth doing the migration.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( '8.1.0', $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Does the actual migration.
	 */
	private function migrate() {
		global $wpdb;

		delete_transient( 'wcpay_bnpl_april15_successful_purchases_count' );
		// using a query directly, so we can delete the meta for all users.
		$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key = '_wcpay_bnpl_april15_viewed'" );
	}
}

<?php
/**
 * Class Qualitative_Feedback_Admin_Note_Removal
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Removes the deprecated qualitative feedback admin note during plugin update.
 *
 * @since 11.2.0
 */
class Qualitative_Feedback_Admin_Note_Removal {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '11.2.0';

	/**
	 * Only execute the migration if it was not applied yet.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( self::VERSION_SINCE, $previous_version, '>' ) ) {
			$this->migrate();
		}
	}

	/**
	 * Removes the deprecated note.
	 */
	private function migrate() {
		\WC_Payments::remove_deprecated_notes();
	}
}

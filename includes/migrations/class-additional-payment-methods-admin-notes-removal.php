<?php
/**
 * Class Allowed_Payment_Request_Button_Sizes_Update
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

use WC_Payments_Notes_Additional_Payment_Methods;

defined( 'ABSPATH' ) || exit;

/**
 * Class Allowed_Payment_Request_Button_Sizes_Update
 *
 * In version 7.0.0, the UPE is further cleaned up and it is also the default now. This migration removes the admin notes for the additional payment methods.
 *
 * @since 7.0.0
 */
class Additional_Payment_Methods_Admin_Notes_Removal {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '7.0.0';

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
		// Delete UPE admin notes as UPE is the default now. This is part of the migration and should be removed in the future.
		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-additional-payment-methods.php';
		WC_Payments_Notes_Additional_Payment_Methods::possibly_delete_note();
	}
}

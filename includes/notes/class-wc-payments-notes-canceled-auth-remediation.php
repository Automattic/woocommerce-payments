<?php
/**
 * WC Admin Inbox note for canceled authorization fee remediation.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Canceled_Auth_Remediation
 *
 * Displays an inbox note to merchants who may be affected by the canceled
 * authorization analytics bug, prompting them to run the remediation tool.
 */
class WC_Payments_Notes_Canceled_Auth_Remediation {
	use NoteTraits {
		can_be_added as protected trait_can_be_added;
	}

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-canceled-auth-remediation';

	/**
	 * URL to the WooCommerce Tools page.
	 */
	const NOTE_TOOLS_URL = 'admin.php?page=wc-status&tab=tools';

	/**
	 * Checks if a note can and should be added.
	 *
	 * @return bool
	 */
	public static function can_be_added() {
		// Don't show if remediation is already complete.
		if ( 'completed' === get_option( 'wcpay_fee_remediation_status', '' ) ) {
			return false;
		}

		// Don't show if remediation is already running.
		if ( self::is_remediation_running() ) {
			return false;
		}

		// Only show if there are affected orders.
		if ( ! self::has_affected_orders() ) {
			return false;
		}

		return self::trait_can_be_added();
	}

	/**
	 * Get the note.
	 *
	 * @return Note
	 */
	public static function get_note() {
		$note = new Note();

		$note->set_title( __( 'WooPayments: Fix incorrect order data', 'woocommerce-payments' ) );
		$note->set_content(
			__(
				'Some orders with canceled payment authorizations have incorrect data that may cause negative values in your WooCommerce Analytics. This affects stores using manual capture (authorize and capture separately). Run the fix tool to correct this.',
				'woocommerce-payments'
			)
		);
		$note->set_content_data( (object) [] );
		$note->set_type( Note::E_WC_ADMIN_NOTE_WARNING );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			'run-remediation-tool',
			__( 'Go to Tools page', 'woocommerce-payments' ),
			admin_url( self::NOTE_TOOLS_URL ),
			'actioned',
			false
		);

		return $note;
	}

	/**
	 * Check if there are orders that need remediation.
	 *
	 * @return bool
	 */
	private static function has_affected_orders() {
		include_once WCPAY_ABSPATH . 'includes/migrations/class-wc-payments-remediate-canceled-auth-fees.php';
		$remediation = new WC_Payments_Remediate_Canceled_Auth_Fees();
		return $remediation->has_affected_orders();
	}

	/**
	 * Check if remediation is currently running.
	 *
	 * @return bool
	 */
	private static function is_remediation_running() {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		include_once WCPAY_ABSPATH . 'includes/migrations/class-wc-payments-remediate-canceled-auth-fees.php';
		return as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK );
	}
}

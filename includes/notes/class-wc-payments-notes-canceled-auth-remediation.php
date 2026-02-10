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
		include_once WCPAY_ABSPATH . 'includes/migrations/class-wc-payments-remediate-canceled-auth-fees.php';

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
	 * Uses a state machine backed by an option to avoid running the expensive
	 * query inline. On the first call, schedules an async Action Scheduler job
	 * and returns false. The note will be added on a subsequent admin_init
	 * once the async check completes.
	 *
	 * @return bool
	 */
	private static function has_affected_orders() {
		$state = get_option( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_STATE_OPTION_KEY );

		if ( false === $state ) {
			self::schedule_check();
			return false;
		}

		if ( 'has_affected_orders' === $state ) {
			return true;
		}

		// 'scheduled', 'no_affected_orders', or any unexpected value.
		return false;
	}

	/**
	 * Schedule the async affected orders check via Action Scheduler.
	 *
	 * @return void
	 */
	private static function schedule_check() {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		update_option( WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_STATE_OPTION_KEY, 'scheduled', true );

		as_schedule_single_action(
			time() + 10,
			WC_Payments_Remediate_Canceled_Auth_Fees::CHECK_AFFECTED_ORDERS_HOOK,
			[],
			'woocommerce-payments'
		);
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

		return as_has_scheduled_action( WC_Payments_Remediate_Canceled_Auth_Fees::ACTION_HOOK );
	}
}

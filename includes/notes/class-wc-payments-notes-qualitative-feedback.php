<?php
/**
 * Set up refund policy note for WooCommerce inbox.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Set_Up_Refund_Policy
 */
class WC_Payments_Notes_Qualitative_Feedback {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-qualitative-feedback';

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_DOCUMENTATION_URL = 'http://automattic.survey.fm/wc-pay-existing';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		global $wpdb;

		// Store must be at least 60 days old.
		if ( ! self::wc_admin_active_for( 60 * DAY_IN_SECONDS ) ) {
			return;
		}

		// We should have at least one transaction.
		if ( WC_Payments_Utils::is_hpos_tables_usage_enabled() ) {
			$result = $wpdb->get_var(
				"SELECT EXISTS(
					SELECT 1
					FROM {$wpdb->prefix}wc_orders_meta
					WHERE meta_key = '_wcpay_transaction_fee'
					LIMIT 1)
				AS count;"
			);
		} else {
			$result = $wpdb->get_var(
				"SELECT EXISTS(
					SELECT 1
					FROM {$wpdb->postmeta}
					WHERE meta_key = '_wcpay_transaction_fee'
					LIMIT 1)
				AS count;"
			);
		}

		if ( 1 !== intval( $result ) ) {
			return;
		}

		$note = new Note();

		$note->set_title(
			sprintf(
				/* translators: %s: WooPayments */
				__( 'Help us make improvements to %s', 'woocommerce-payments' ),
				'WooPayments'
			)
		);
		$note->set_content( __( 'Share your feedback in this 2 minute survey about how we can make the process of accepting payments more useful for your store.', 'woocommerce-payments' ) );
		$note->set_content_data( (object) [] );
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Share feedback', 'woocommerce-payments' ),
			self::NOTE_DOCUMENTATION_URL,
			'unactioned',
			true
		);

		return $note;
	}
}

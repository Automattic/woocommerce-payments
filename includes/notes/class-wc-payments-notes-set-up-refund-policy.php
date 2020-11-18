<?php
/**
 * Set up refund policy note for WooCommerce inbox.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Set_Up_Refund_Policy
 */
class WC_Payments_Notes_Set_Up_Refund_Policy {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-set-up-refund-policy';

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_DOCUMENTATION_URL = 'https://docs.woocommerce.com/document/woocommerce-refunds#refund-policy-howto';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		if ( class_exists( 'Automattic\WooCommerce\Admin\Notes\Note' ) ) {
			$note_class = Note::class;
		} else {
			$note_class = WC_Admin_Note::class;
		}

		$note = new $note_class();

		$note->set_title( __( 'Set up refund policy', 'woocommerce-payments' ) );
		$note->set_content( __( 'Protect your merchant account from fraudulent disputes by defining the policy and making it accessible to customers.', 'woocommerce-payments' ) );
		$note->set_content_data( (object) [] );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Read more', 'woocommerce-payments' ),
			self::NOTE_DOCUMENTATION_URL,
			'unactioned',
			true
		);

		return $note;
	}
}

<?php
/**
 * Notify merchant that there is a new document available.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Set_Https_For_Checkout
 */
class WC_Payments_Notes_New_Document_Available {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-new-document-available';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'New document available', 'woocommerce-payments' ) );
		$note->set_content( __( 'A new document is available for your review.', 'woocommerce-payments' ) );
		$note->set_content_data( (object) [] );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'View documents', 'woocommerce-payments' ),
			admin_url( 'admin.php?page=wc-admin&path=/payments/documents' ),
			$note_class::E_WC_ADMIN_NOTE_UNACTIONED,
			true
		);

		return $note;
	}
}

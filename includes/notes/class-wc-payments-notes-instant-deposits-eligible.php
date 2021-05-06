<?php
/**
 * Notify merchant that they are eligible for Instant Deposits.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Set_Https_For_Checkout
 */
class WC_Payments_Notes_Instant_Deposits_Eligible {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-instant-deposits-eligible';

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_DOCUMENTATION_URL = 'https://docs.woocommerce.com/document/payments/instant-deposits/';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Instant deposits available', 'woocommerce-payments' ) );
		$note->set_content( __( 'You are now eligible for Instant Deposits! Skip the wait and get paid instantly.', 'woocommerce-payments' ) );
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

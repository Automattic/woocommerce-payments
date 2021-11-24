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
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'You’re now eligible to receive Instant Deposits with WooCommerce Payments', 'woocommerce-payments' ) );
		$note->set_content(
			WC_Payments_Utils::esc_interpolated_html(
				__( "Get immediate access to your funds when you need them – including nights, weekends, and holidays. With WooCommerce Payments' <a>Instant Deposits feature</a>, you're able to transfer your earnings to a debit card within minutes.", 'woocommerce-payments' ),
				[ 'a' => '<a href="https://woocommerce.com/document/payments/instant-deposits/">' ]
			)
		);
		$note->set_content_data( (object) [] );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Request an instant deposit', 'woocommerce-payments' ),
			'https://woocommerce.com/document/payments/instant-deposits/#section-2',
			'unactioned',
			true
		);

		return $note;
	}
}

<?php
/**
 * Notify merchant that they are eligible for Instant Deposits.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Instant_Deposits_Eligible
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
		$note = new Note();

		$note->set_title(
			sprintf(
				/* translators: %s: WooPayments */
				__( 'You’re now eligible to receive Instant Deposits with %s', 'woocommerce-payments' ),
				'WooPayments'
			)
		);
		$note->set_content(
			WC_Payments_Utils::esc_interpolated_html(
				sprintf(
					/* translators: %s: WooPayments */
					__( "Get immediate access to your funds when you need them – including nights, weekends, and holidays. With %s' <a>Instant Deposits feature</a>, you're able to transfer your earnings to a debit card within minutes.", 'woocommerce-payments' ),
					'WooPayments'
				),
				[ 'a' => '<a href="https://woo.com/document/woopayments/deposits/instant-deposits/">' ]
			)
		);
		$note->set_content_data( (object) [] );
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Request an instant deposit', 'woocommerce-payments' ),
			'https://woo.com/document/woopayments/deposits/instant-deposits/#request-an-instant-deposit',
			'unactioned',
			true
		);

		return $note;
	}
}

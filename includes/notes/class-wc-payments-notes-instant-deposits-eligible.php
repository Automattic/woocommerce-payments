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
	use NoteTraits {
		can_be_added as protected trait_can_be_added;
	}

	/**
	 * The transient that the account data is stored in.
	 */
	const ACCOUNT_TRANSIENT = 'wcpay_account_data';

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-instant-deposits-eligible';

	/**
	 * Name of the note for use in the database.
	 */
	// TODO: Get the proper doc url.
	const NOTE_DOCUMENTATION_URL = '';

	/**
	 * Checks if a note can and should be added.
	 *
	 * @return bool
	 */
	public static function can_be_added() {
		$account = get_transient( self::ACCOUNT_TRANSIENT );

		// If the flag exists and is true, we return that the note should be added.
		if ( isset( $account['instant_deposits_eligible'] ) && $account['instant_deposits_eligible'] ) {
			return self::trait_can_be_added();
		}
	}

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();

		// TODO: get the proper title and message.
		$note->set_title( __( 'Instant deposits available', 'woocommerce-payments' ) );
		$note->set_content( __( 'You are now eligible for Instant Deposits!', 'woocommerce-payments' ) );
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

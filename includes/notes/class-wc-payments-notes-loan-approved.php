<?php
/**
 * Display a notice to merchants that have WCPay installed to inform them
 * that a loan offer has been approved from Stripe.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Additional_Payment_Methods
 */
class WC_Payments_Notes_Loan_Approved {
	use NoteTraits;

	/**
	 * Prefix of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-loan-approved';

	/**
	 * Nonce action name
	 */
	const NOTE_ACTION = 'view-capital-page';

	/**
	 * Loan information to build the message.
	 *
	 * @var array
	 */
	private static $loan_info;

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class  = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note        = new $note_class();
		$dummy_order = wc_create_order();
		$dummy_order->set_currency( self::$loan_info['details']['currency'] );

		$note->set_title( __( 'Your capital loan has been approved!', 'woocommerce-payments' ) );
		$note->set_content(
			sprintf(
				// Translators: %1: total amount lent to the merchant formatted in the account currency.
				__(
					'Congratulations! Your capital loan has been approved and %1$s was deposited in to the bank account linked to WooCommerce Payments. You\'ll automatically repay the loan, plus a flat fee, through a fixed percentage of each WooCommerce Payments transaction.',
					'woocommerce-payments'
				),
				WC_Payments_Explicit_Price_Formatter::get_explicit_price(
					wc_price(
						WC_Payments_Utils::interpret_stripe_amount( self::$loan_info['details']['advance_amount'] ),
						[ 'currency' => self::$loan_info['details']['currency'] ]
					),
					$dummy_order
				)
			)
		);

		$note->set_content_data(
			(object) [
				'advance_amount'      => self::$loan_info['details']['advance_amount'],
				'advance_paid_out_at' => self::$loan_info['details']['advance_paid_out_at'],
			]
		);
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'View loan details', 'woocommerce-payments' ),
			admin_url( 'admin.php?page=wc-admin&path=/payments/loans' ),
			$note_class::E_WC_ADMIN_NOTE_UNACTIONED,
			true
		);

		return $note;
	}

	/**
	 * Add the note if it passes predefined conditions.
	 */
	public static function possibly_add_note() {
		// If we have the correct information, proceed. Otherwise, delete existing notes.
		if ( ! self::validate_inputs() ) {
			// We don't have the necessary info to create a note, do nothing.
			return;
		}

		// Check if the current loan info matches with the received one. If it matches, don't add a new one.
		if ( ! self::check_attached_loan_data_is_different() ) {
			// Loan paid out dates are the same, do nothing.
			return;
		}

		// Do the overridden work.
		if ( ! self::can_be_added() ) {
			return;
		}

		$new_note = self::get_note();
		$new_note->save();
	}

	/**
	 * Check if the stored loan info has all the values we need.
	 *
	 * @return bool
	 */
	private static function validate_inputs() {
		// If the loan amount isn't set correctly, don't push the note, and delete the old one if exists.
		if ( ! isset(
			self::$loan_info['details']['currency'],
			self::$loan_info['details']['advance_amount'],
			self::$loan_info['details']['advance_paid_out_at']
		)
			|| ! is_numeric( self::$loan_info['details']['advance_amount'] )
			|| empty( self::$loan_info['details']['currency'] )
		) {
			// There's something wrong with the loan information, delete the existing note, just in case of wrong information.
			return false;
		}

		self::$loan_info['details']['currency'] = strtoupper( self::$loan_info['details']['currency'] );

		return true;
	}

	/**
	 * Checks the saved paid out date on the previous note and deletes it if it doesn't match, to create a new one.
	 *
	 * @return bool
	 */
	private static function check_attached_loan_data_is_different() {
		// Check if the note already exists, and the stored paid out date matches our current loan before adding a new one.
		/**
		 * Note class. Suppressed psalm error for WC<=5.5.0 because it uses an old class for the note.
		 *
		 * @var WC_Admin_Note|Note
		 * @psalm-suppress UndefinedDocblockClass
		 */
		$note_class  = WC_Payment_Woo_Compat_Utils::get_note_class();
		$notes_class = WC_Payment_Woo_Compat_Utils::get_notes_class();
		$data_store  = WC_Data_Store::load( 'admin-note' );
		$note_ids    = $data_store->get_notes_with_name( self::NOTE_NAME );

		if ( ! empty( $note_ids ) ) {
			$note = $notes_class::get_note( $note_ids[0] );
			if ( $note instanceof $note_class ) {
				$content_data = (array) $note->get_content_data();
				if ( isset( $content_data['advance_paid_out_at'], $content_data['advance_amount'] ) ) {
					if ( self::$loan_info['details']['advance_paid_out_at'] === $content_data['advance_paid_out_at'] &&
						self::$loan_info['details']['advance_amount'] === $content_data['advance_amount'] ) {
						// Note already exists for the current loan. No action will be taken.
						return false;
					}
				}
				// The note isn't for the current loan. Delete it to create a new one.
				$data_store->delete( $note );
			}
		}

		return true;
	}

	/**
	 * Sets the loan information on the class.
	 *
	 * @param array $loan_info loan information.
	 */
	public static function set_loan_details( array $loan_info ) {
		self::$loan_info = $loan_info;
	}
}

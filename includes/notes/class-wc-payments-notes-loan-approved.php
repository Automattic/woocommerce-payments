<?php
/**
 * Display a notice to merchants that have WCPay installed to inform them
 * that a loan offer has been approved from Stripe.
 *
 * @package WooCommerce\Payments\Admin
 */

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
	 * The account service instance.
	 *
	 * @var WC_Payments_Account
	 */
	private static $account;

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
		// Show this notice only if capital feature is enabled.
		if ( false === WC_Payments_Features::is_capital_enabled() ) {
			return;
		}

		// if the user hasn't connected their account (or the account got disconnected) do not add the note.
		if ( self::$account instanceof WC_Payments_Account ) {
			if ( ! self::$account->is_stripe_connected() ) {
				return;
			}
		}

		// If the loan amount isn't set correctly, don't push the note.
		if ( empty( self::$loan_info ) || ! is_array( self::$loan_info ) || ! array_key_exists( 'advance_amount', self::$loan_info ) || ! is_numeric( self::$loan_info['advance_amount'] ) ) {
			return;
		}

		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Your capital loan has been approved!', 'woocommerce-payments' ) );
		$note->set_content(
			sprintf(
				// Translators: %1: total amount lent to the merchant formatted in the account currency.
				__(
					'Congratulations! Your capital loan has been approved and %1$s was deposited in to the bank account linked to WooCommerce Payments. You\'ll automatically repay the loan, plus a flat fee, through a fixed percentage of each WooCommerce Payments transaction.',
					'woocommerce-payments'
				),
				wc_price( self::$loan_info['advance_amount'] / 100 )
			)
		);
		$note->set_content_data( (object) [] );
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
	 * Sets the account service instance reference on the class.
	 *
	 * @param WC_Payments_Account $account account service instance.
	 */
	public static function set_account( WC_Payments_Account $account ) {
		self::$account = $account;
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

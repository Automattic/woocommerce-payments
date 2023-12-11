<?php
/**
 * Display a notice to merchants that have WCPay installed (and don't have UPE enabled)
 * to inform them that they can enable additional payment methods.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Additional_Payment_Methods
 */
class WC_Payments_Notes_Additional_Payment_Methods {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-additional-payment-methods';

	/**
	 * Nonce action name
	 */
	const NOTE_ACTION = 'enable-upe';

	/**
	 * The account service instance.
	 *
	 * @var WC_Payments_Account
	 */
	private static $account;

	/**
	 * Get the note.
	 */
	public static function get_note() {
		// The notice should not be shown anymore because UPE is the default now.
		return false;
	}

	/**
	 * Sets the account service instance reference on the class.
	 *
	 * @param WC_Payments_Account $account account service instance.
	 */
	public static function set_account( WC_Payments_Account $account ) {
		self::$account = $account;
	}
}

<?php
/**
 * Notify merchant that Multi-Currency is available.
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency\Notes;

use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class NoteMultiCurrencyAvailable
 */
class NoteMultiCurrencyAvailable {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-multi-currency-available';

	/**
	 * Url to start the setup process. Now redirects to the wizard page.
	 */
	// TODO: Proper url needed for setup process.
	const NOTE_SETUP_URL = 'admin.php?page=wc-admin&path=/payments/multi-currency-setup';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = \WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Sell worldwide in multiple currencies', 'woocommerce-payments' ) );
		$note->set_content( __( 'Boost your international sales by allowing your customers to shop and pay in their local currency.', 'woocommerce-payments' ) );
		$note->set_content_data( (object) [] );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Set up now', 'woocommerce-payments' ),
			self::NOTE_SETUP_URL,
			'unactioned',
			true
		);

		return $note;
	}
}

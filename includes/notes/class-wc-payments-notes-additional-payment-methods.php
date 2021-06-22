<?php
/**
 * Display a notice to merchants that have WCPay installed (and don't have UPE enabled)
 * to inform them that they can enable additional payment methods.
 *
 * @package WooCommerce\Payments\Admin
 */

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
	 * Learn more link.
	 */
	const NOTE_DOCUMENTATION_URL = 'https://docs.woocommerce.com/document/payments/';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		// Show this notice only if UPE is not enabled.
		if ( WC_Payments_Features::is_upe_enabled() ) {
			return;
		}

		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Boost your sales by accepting new payment methods', 'woocommerce-payments' ) );
		$note->set_content( __( 'Get early access to additional payment methods and an improved checkout experience, coming soon to WooCommerce Payments.', 'woocommerce-payments' ) );
		$note->set_content_data( (object) [] );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Enable on your store', 'woocommerce-payments' ),
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&action=enable-upe' ),
			$note_class::E_WC_ADMIN_NOTE_UNACTIONED,
			true
		);
		$note->add_action( 'learn-more', __( 'Learn more', 'woocommerce-payments' ), self::NOTE_DOCUMENTATION_URL );

		return $note;
	}
}

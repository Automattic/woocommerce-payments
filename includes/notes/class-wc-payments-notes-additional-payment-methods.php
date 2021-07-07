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
	 * Nonce action name
	 */
	const NOTE_ACTION = 'enable-upe';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		// Show this notice only if UPE settings preview is disabled, and UPE flag is not enabled.
		if ( ! WC_Payments_Features::is_upe_settings_preview_enabled() || WC_Payments_Features::is_upe_enabled() ) {
			return;
		}

		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();
		$nonce      = wp_create_nonce( self::NOTE_ACTION );

		$note->set_title( __( 'Boost your sales by accepting new payment methods', 'woocommerce-payments' ) );
		$note->set_content( __( 'Get early access to additional payment methods and an improved checkout experience, coming soon to WooCommerce Payments. <a href="https://docs.woocommerce.com/document/payments/" target="wcpay_upe_learn_more">Learn more</a>', 'woocommerce-payments' ) );
		$note->set_content_data( (object) [] );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Enable on your store', 'woocommerce-payments' ),
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&action=' . self::NOTE_ACTION . '&_wpnonce=' . $nonce ),
			$note_class::E_WC_ADMIN_NOTE_UNACTIONED,
			true
		);

		return $note;
	}

	/**
	 * Enable UPE feature flag. CTA from Admin Notes. See WC_Payments_Notes_Additional_Payment_Methods.
	 */
	public static function maybe_enable_upe_feature_flag() {
		if (
			empty( $_GET['page'] ) ||
			'wc-settings' !== $_GET['page'] ||
			empty( $_GET['action'] ) ||
			'enable-upe' !== $_GET['action'] ||
			! check_admin_referer( self::NOTE_ACTION )
		) {
			return;
		}

		// Enable UPE.
		update_option( WC_Payments_Features::UPE_FLAG_NAME, '1' );
		self::possibly_delete_note();

		$wcpay_settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' );
		wp_safe_redirect( $wcpay_settings_url );
		exit;
	}
}

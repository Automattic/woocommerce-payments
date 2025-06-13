<?php
/**
 * Set up Stripe Billing deprecation note for WooCommerce inbox.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Stripe_Billing_Deprecation
 */
class WC_Payments_Notes_Stripe_Billing_Deprecation {
	use NoteTraits {
		can_be_added as protected trait_can_be_added;
	}

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-stripe-billing-deprecation';

	/**
	 * URL to the WooCommerce Subscriptions plugin page.
	 */
	const NOTE_SUBSCRIPTIONS_URL = 'https://woocommerce.com/products/woocommerce-subscriptions/';

	/**
	 * Checks if a note can and should be added.
	 *
	 * @return bool
	 */
	public static function can_be_added() {
		// Only show if Stripe Billing is enabled and WooCommerce Subscriptions is not active.
		if ( ! WC_Payments_Features::is_stripe_billing_enabled() || class_exists( 'WC_Subscriptions' ) ) {
			return false;
		}

		return self::trait_can_be_added();
	}

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note = new Note();

		$note->set_title( __( 'WooPayments no longer supports bundled subscriptions', 'woocommerce-payments' ) );
		$note->set_content( __( 'To continue offering subscriptions to your customers, please install the standalone WooCommerce Subscriptions plugin.', 'woocommerce-payments' ) );
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action( 'get-woocommerce-subscriptions', __( 'Get WooCommerce Subscriptions', 'woocommerce-payments' ), self::NOTE_SUBSCRIPTIONS_URL );

		return $note;
	}
}

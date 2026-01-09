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
		// Only show if Stripe Billing is enabled and WooCommerce Subscriptions is not installed.
		if ( ! self::is_bundled_subscriptions_enabled() ) {
			return false;
		}

		// If wcpay version is > 10.2.99 bail to not show the notice indefinitely.
		if ( version_compare( WC_Payments::get_file_version( WCPAY_PLUGIN_FILE ), '10.2.99', '>' ) ) {
			return false;
		}

		return self::trait_can_be_added();
	}

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note = new Note();

		$note->set_title( __( 'Built-in subscriptions functionality has been removed. Here\'s what to do', 'woocommerce-payments' ) );
		$note->set_content( __( 'To continue offering subscriptions and gain access to your data, please install WooCommerce Subscriptions. WooPayments no longer supports this feature.', 'woocommerce-payments' ) );

		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action( 'get-woocommerce-subscriptions', __( 'Install WooCommerce Subscriptions', 'woocommerce-payments' ), self::NOTE_SUBSCRIPTIONS_URL );

		return $note;
	}

	/**
	 * Check if bundled subscriptions are enabled.
	 *
	 * @return bool
	 */
	protected static function is_bundled_subscriptions_enabled() {
		$has_bundled_subs = WC_Payments_Features::is_wcpay_subscriptions_enabled() || WC_Payments_Features::is_stripe_billing_enabled();
		$has_wc_subs      = class_exists( 'WC_Subscriptions' );

		return $has_bundled_subs && ! $has_wc_subs;
	}
}

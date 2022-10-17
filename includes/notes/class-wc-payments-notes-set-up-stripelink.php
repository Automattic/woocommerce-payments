<?php
/**
 * Set up Stripe Link note for WooCommerce inbox.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Notes_Setup_StripeLink
 */
class WC_Payments_Notes_Set_Up_StripeLink {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-set-up-stripe-link';

	/**
	 * CTA button link
	 */
	const NOTE_DOCUMENTATION_URL = 'https://woocommerce.com/document/payments/woocommerce-payments-stripe-link/';

	/**
	 * The payment gateway service instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private static $gateway;

	/**
	 * Checks if a note can and should be added.
	 *
	 * @return bool
	 */
	public static function should_display_note() {
		$payment_method_statuses = self::$gateway->get_upe_enabled_payment_method_statuses();
		$status                  = $payment_method_statuses['link_payments']['status'] ?? null;

		if ( 'unrequested' === $status ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the note.
	 */
	public static function get_note() {
		if ( ! self::should_display_note() ) {
			return;
		}

		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Increase conversion at checkout', 'woocommerce-payments' ) );
		$note->set_content( __( 'Reduce cart abandonment and create a frictionless checkout experience with Link by Stripe. Link autofills your customer’s payment and shipping details so they can check out in just six seconds with the Link optimized experience. That’s 9x faster than shoppers who don’t use Link. Link increases conversion rates by over 7% for logged-in Link customers.', 'woocommerce-payments' ) );
		$note->set_content_data( (object) [] );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Set up now', 'woocommerce-payments' ),
			self::NOTE_DOCUMENTATION_URL,
			'unactioned',
			true
		);

		return $note;
	}

	/**
	 * Sets the payment gateway instance reference on the class.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway Payment gateway.
	 */
	public static function set_gateway( WC_Payment_Gateway_WCPay $gateway ) {
		self::$gateway = $gateway;
	}
}

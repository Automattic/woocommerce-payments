<?php
/**
 * Set up Stripe Link note for WooCommerce inbox.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use WCPay\Payment_Methods\Link_Payment_Method;
use WCPay\Payment_Methods\CC_Payment_Method;


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
	const NOTE_DOCUMENTATION_URL = 'https://woo.com/document/woopayments/payment-methods/link-by-stripe/';

	/**
	 * The account service instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private static $gateway;

	/**
	 * Checks if a note can and should be added.
	 *
	 * @return bool
	 */
	public static function should_display_note():bool {
		// Check if Link payment is available.
		$available_upe_payment_methods = self::$gateway->get_upe_available_payment_methods();
		if ( ! in_array( Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID, $available_upe_payment_methods, true ) ) {
			return false;
		}

		// Retrieve enabled payment methods at checkout.
		$enabled_payment_methods = self::$gateway->get_payment_method_ids_enabled_at_checkout_filtered_by_fees( null, true );
		// If card payment method is not enabled or Link payment method is enabled, skip.
		if ( ! in_array( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID, $enabled_payment_methods, true )
				|| in_array( Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID, $enabled_payment_methods, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the note.
	 */
	public static function get_note() {
		if ( ! self::should_display_note() ) {
			return;
		}

		$note = new Note();

		$note->set_title( __( 'Increase conversion at checkout', 'woocommerce-payments' ) );
		$note->set_content( __( 'Reduce cart abandonment and create a frictionless checkout experience with Link by Stripe. Link autofills your customer’s payment and shipping details, so they can check out in just six seconds with the Link optimized experience.', 'woocommerce-payments' ) );
		$note->set_content_data( (object) [] );
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
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
	 * Sets the WCPay gateway instance reference on the class.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway WCPay gateway instance.
	 */
	public static function set_gateway( WC_Payment_Gateway_WCPay $gateway ) {
		self::$gateway = $gateway;
	}
}

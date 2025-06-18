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

		// If wcpay version is >= 10.0, bail to not show the notice indefinitely.
		if ( version_compare( WC_Payments::get_file_version( WCPAY_PLUGIN_FILE ), '10.0.0', '>=' ) ) {
			return false;
		}

		return self::trait_can_be_added();
	}

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note          = new Note();
		$wcpay_version = WC_Payments::get_file_version( WCPAY_PLUGIN_FILE );

		if ( version_compare( $wcpay_version, '9.7.0', '<' ) ) {
			$note->set_title( __( 'Important information regarding subscriptions in WooPayments', 'woocommerce-payments' ) );
			$note->set_content( __( 'From version 9.7 of WooPayments (scheduled for 23 July, 2025), you\'ll no longer be able to offer new product subscriptions using the built-in subscriptions functionality. To avoid disruption, please install WooCommerce Subscriptions for free.', 'woocommerce-payments' ) );
		} elseif ( version_compare( $wcpay_version, '9.8.0', '<' ) ) {
			$note->set_title( __( 'WooPayments subscriptions update', 'woocommerce-payments' ) );
			$note->set_content( __( 'WooPayments no longer allows customers to create new subscriptions. Beginning in version 9.8, billing for existing customer subscriptions will no longer be supported. To ensure there is no interruption of service, please install WooCommerce Subscriptions.', 'woocommerce-payments' ) );
		} elseif ( version_compare( $wcpay_version, '9.9.0', '<' ) ) {
			$note->set_title( __( 'WooPayments subscriptions update', 'woocommerce-payments' ) );
			$note->set_content( __( 'WooPayments no longer supports billing for existing customer subscriptions. All subscriptions data is read-only. Please install WooCommerce Subscriptions to continue managing your subscriptions.', 'woocommerce-payments' ) );
		} else {
			$note->set_title( __( 'WooPayments subscriptions update', 'woocommerce-payments' ) );
			$note->set_content( __( 'WooPayments no longer supports subscriptions capabilities and subscriptions data can no longer be accessed. Please install WooCommerce Subscriptions to continue managing your subscriptions.', 'woocommerce-payments' ) );
		}

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
		return WC_Payments_Features::is_stripe_billing_enabled() && ! class_exists( 'WC_Subscriptions' );
	}
}

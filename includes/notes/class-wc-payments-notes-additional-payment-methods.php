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

use WCPay\Tracker;

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
		// Show this notice only if UPE settings preview is disabled, and UPE flag is not enabled.
		if ( false === WC_Payments_Features::is_upe_settings_preview_enabled() ) {
			return;
		}

		if ( WC_Payments_Features::is_upe_enabled() ) {
			return;
		}

		if ( WC_Payments_Features::did_merchant_disable_upe() ) {
			return;
		}

		// if the user hasn't connected their account (or the account got disconnected) do not add the note.
		if ( self::$account instanceof WC_Payments_Account ) {
			if ( ! self::$account->is_stripe_connected() ) {
				return;
			}
		}

		$note = new Note();

		$note->set_title( __( 'Boost your sales by accepting new payment methods', 'woocommerce-payments' ) );
		$note->set_content( __( 'Get early access to additional payment methods and an improved checkout experience, coming soon to WooCommerce Payments. <a href="https://woocommerce.com/document/payments/additional-payment-methods/" target="wcpay_upe_learn_more">Learn more</a>', 'woocommerce-payments' ) );
		$note->set_content_data( (object) [] );
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Enable on your store', 'woocommerce-payments' ),
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&action=' . self::NOTE_ACTION ),
			Note::E_WC_ADMIN_NOTE_UNACTIONED,
			true
		);

		return $note;
	}

	/**
	 * Enable UPE feature flag. CTA from Admin Notes. See WC_Payments_Notes_Additional_Payment_Methods.
	 */
	public static function maybe_enable_upe_feature_flag() {
		/**
		 * TODO: Add nonce to verify https://github.com/woocommerce/woocommerce-admin/pull/6726/
		 * requires WC requirement is > 5.4.
		 */
		if (
			! current_user_can( 'manage_woocommerce' ) ||
			empty( $_GET['page'] ) || // phpcs:disable WordPress.Security.NonceVerification.Recommended
			'wc-settings' !== $_GET['page'] || // phpcs:disable WordPress.Security.NonceVerification.Recommended
			empty( $_GET['action'] ) || // phpcs:disable WordPress.Security.NonceVerification.Recommended
			'enable-upe' !== $_GET['action'] // phpcs:disable WordPress.Security.NonceVerification.Recommended
		) {
			return;
		}

		// once we get to this point, it means we should enable UPE,
		// but we also need to check whether the account is connected or not.

		// if we get to this point and the account is not connected,
		// it means that the note has been displayed at some point, but the account got disconnected afterwards.
		// in that case, let's redirect the user to the account connection page.
		if ( self::$account instanceof WC_Payments_Account ) {
			if ( false === self::$account->is_stripe_connected() ) {
				// account is not connected, redirecting to connection page.
				self::$account->redirect_to_onboarding_page( __( 'We detected a temporary issue with your account. Please try and connect your Stripe account.', 'woocommerce-payments' ) );

				return;
			}
		}

		// Track enabling UPE if it wasn't enabled before.
		if ( ! WC_Payments_Features::is_upe_enabled() && class_exists( 'WC_Tracks' ) ) {
			// We're not using Tracker::track_admin() here because
			// WC_Pay\record_tracker_events() is never triggered due to the redirect below.
			WC_Tracks::record_event( 'wcpay_split_upe_enabled' );
		}

		// Enable UPE, deletes the note and redirect to onboarding task.
		update_option( WC_Payments_Features::UPE_SPLIT_FLAG_NAME, '1' );
		self::possibly_delete_note();

		$wcpay_settings_url = admin_url( 'admin.php?page=wc-admin&path=/payments/additional-payment-methods' );
		wp_safe_redirect( $wcpay_settings_url );
		exit;
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

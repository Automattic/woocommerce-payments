<?php
/**
 * Display a notice to merchants explaining that WooPay is now available.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

defined( 'ABSPATH' ) || exit;



/**
 * Class WC_Payments_Notes_WooPay_Beta_Recruitment
 */
class WC_Payments_Notes_WooPay_Beta_Recruitment {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-payments-notes-woopay-beta-recruitment';

	/**
	 * Nonce action name
	 */
	const NOTE_ACTION = 'woopay-beta-recruitment';

	/**
	 * Minimum WCPay version to enable WooPay
	 */
	const MINIMUM_WCPAY_VERSION = '5.7.1';

	/**
	 * Platform checkout button handler
	 *
	 * @var WC_Payments_Platform_Checkout_Button_Handler
	 */
	private static $platform_checkout_button_handler;

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note = new Note();

		$note->set_title( self::get_title() );
		$note->set_content( self::get_content() );
		$note->set_content_data( (object) [] );
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-payments' );

		if ( ! self::has_minimum_wcpay_version() ) {
			$note->add_action(
				self::NOTE_NAME,
				__( 'Update WooCommerce Payments', 'woocommerce-payments' ),
				admin_url( 'plugins.php' ),
				Note::E_WC_ADMIN_NOTE_UNACTIONED,
				true
			);
		} elseif ( ! self::is_woopay_enabled() ) {
			$note->add_action(
				self::NOTE_NAME,
				__( 'Get started in seconds.', 'woocommerce-payments' ),
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=platform_checkout' ),
				Note::E_WC_ADMIN_NOTE_UNACTIONED,
				true
			);
		}

		return $note;
	}

	/**
	 * Sets the platform checkout button handler on the class.
	 *
	 * @param WC_Payments_Platform_Checkout_Button_Handler $platform_checkout_button_handler Platform checkout button handler instance.
	 * @return void
	 */
	public static function set_platform_checkout_button_handler( $platform_checkout_button_handler ) {
		self::$platform_checkout_button_handler = $platform_checkout_button_handler;
	}

	/**
	 * Returns title for note, depending on certain conditions.
	 *
	 * @return string
	 */
	private static function get_title() {
		return self::is_woopay_enabled() ?
			__( 'WooPay is back!', 'woocommerce-payments' ) :
			__( 'Increase conversions with WooPay - our fastest checkout yet', 'woocommerce-payments' );
	}

	/**
	 * Returns content for note, depending on certain conditions.
	 *
	 * @return string
	 */
	private static function get_content() {
		if ( self::is_woopay_enabled() ) {
			if ( self::has_minimum_wcpay_version() ) {
				return __( 'Thank you for previously trying WooPay—a new express checkout feature built into WooCommerce Payments. We’re excited to announce that WooPay availability has resumed. No action is required on your part. You can now continue boosting conversions by offering your customers a simple, secure way to pay with a single click.', 'woocommerce-payments' );
			} else {
				return __( 'Thank you for previously trying WooPay—a new express checkout feature built into WooCommerce Payments. We’re excited to announce that WooPay availability has resumed. Update WooCommerce Payments to continue boosting conversions by offering your customers a simple, secure way to pay with a single click.', 'woocommerce-payments' );
			}
		} else {
			if ( self::has_minimum_wcpay_version() ) {
				return __( 'WooPay, a new express checkout feature built into WooCommerce Payments, is now available—and you’re invited to be one of the first to try it. Boost conversions by offering your customers a simple, secure way to pay with a single click.', 'woocommerce-payments' );
			} else {
				return __( 'WooPay, a new express checkout feature built into WooCommerce Payments, is now available—and you’re invited to try it. Boost conversions by offering customers a simple, secure way to pay with a single click. Update WooCommerce Payments to get started.', 'woocommerce-payments' );
			}
		}
	}

	/**
	 * Checks whether store has minimum WCPay version.
	 *
	 * @return boolean
	 */
	private static function has_minimum_wcpay_version() {
		return version_compare( self::MINIMUM_WCPAY_VERSION, get_option( 'woocommerce_woocommerce_payments_version' ), '>=' );
	}

	/**
	 * Checks whether merchant has enabled WooPay.
	 *
	 * @return boolean
	 */
	private static function is_woopay_enabled() {
		return self::$platform_checkout_button_handler->is_woopay_enabled();
	}
}

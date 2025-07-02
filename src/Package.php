<?php
/**
 * WooCommerce Payments Package class.
 *
 * @package namespace WCPay;
 */

declare( strict_types = 1 );
namespace WCPay;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class for WooCommerce Payments.
 */
class Package {

	/**
	 * Version.
	 *
	 * @var string
	 */
	const VERSION = '9.5.0';

	/**
	 * Init the package.
	 *
	 * @return void
	 */
	public static function init() {
		// Initialize the WooCommerce Payments functionality
		// This replaces the current wcpay_init() function logic
		require_once dirname( __DIR__ ) . '/includes/class-wc-payments.php';
		require_once dirname( __DIR__ ) . '/includes/class-wc-payments-payment-request-session.php';
		
		// Use the global class names as they exist in the includes directory
		\WC_Payments::init();
		
		/**
		 * Needs to be loaded as soon as possible
		 * Check https://github.com/Automattic/woocommerce-payments/issues/4759
		 */
		\WCPay\WooPay\WooPay_Session::init();
		( new \WC_Payments_Payment_Request_Session() )->init();
	}

	/**
	 * Return the version of the package.
	 *
	 * @return string
	 */
	public static function get_version() {
		return self::VERSION;
	}

	/**
	 * Return the path to the package.
	 *
	 * @return string
	 */
	public static function get_path() {
		return dirname( __DIR__ );
	}
} 
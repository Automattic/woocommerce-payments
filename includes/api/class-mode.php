<?php
/**
 * Class file for WCPay\API\Mode.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\API;

use WC_Payment_Gateway_WCPay;
use Exception;

/**
 * Controls the working mode of WooCommerce Payments.
 */
class Mode {
	/**
	 * Holds the test mode flag.
	 *
	 * @var bool
	 */
	private static $test_mode;

	/**
	 * Holds the dev mode flag.
	 *
	 * @var bool
	 */
	private static $dev_mode;

	/**
	 * Environment types, which are used to automatically enter dev mode.
	 *
	 * @see wp_get_environment_type()
	 * @see https://developer.wordpress.org/reference/functions/wp_get_environment_type/#description
	 */
	const DEV_MODE_ENVIRONMENTS = [
		'development',
		'staging',
	];

	/**
	 * Initializes the working mode of WooCommerce Payments.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway The active gateway.
	 */
	public static function init( WC_Payment_Gateway_WCPay $gateway ) {
		$dev_mode = (
			// Plugin-specific dev mode.
			( defined( 'WCPAY_DEV_MODE' ) && WCPAY_DEV_MODE )

			// WordPress Dev Environment.
			|| (
				function_exists( 'wp_get_environment_type' )
				&& in_array( wp_get_environment_type(), self::DEV_MODE_ENVIRONMENTS, true )
			)
		);

		self::$dev_mode = apply_filters( 'wcpay_dev_mode', $dev_mode );

		$test_mode       = self::$dev_mode || 'yes' === $gateway->get_option( 'test_mode' );
		self::$test_mode = apply_filters( 'wcpay_test_mode', $test_mode );
	}

	/**
	 * Check the defined constant to determine the current plugin mode.
	 *
	 * @return bool
	 * @throws Exception When the mode has not been initialized yet.
	 */
	public static function is_dev() {
		self::throw_exception_if_uninitialized();

		return self::$dev_mode;
	}

	/**
	 * Returns whether test_mode or dev_mode is active for the gateway
	 *
	 * @return boolean Test mode enabled if true, disabled if false
	 * @throws Exception When the mode has not been initialized yet.
	 */
	public static function is_test() {
		self::throw_exception_if_uninitialized();

		return self::$test_mode;
	}

	/**
	 * Enters test mode.
	 */
	public static function enter_test_mode() {
		self::prevent_switching_when_not_testing();

		self::$test_mode = true;
		self::$dev_mode  = false;
	}

	/**
	 * Enters dev mode.
	 */
	public static function enter_dev_mode() {
		self::prevent_switching_when_not_testing();

		self::$test_mode = true;
		self::$dev_mode  = true;
	}

	/**
	 * Enters live mode.
	 */
	public static function enter_live_mode() {
		self::prevent_switching_when_not_testing();

		self::$test_mode = false;
		self::$dev_mode  = false;
	}

	/**
	 * Throws an exception if the class has not been initialized yes.
	 *
	 * @throws Exception
	 */
	private static function throw_exception_if_uninitialized() {
		if ( isset( self::$dev_mode ) && isset( self::$test_mode ) ) {
			return;
		}

		throw new Exception( 'WooCommerce Payments\' working mode is not initialized yet. Wait for the `init` action.' );
	}

	/**
	 * Prevents modes from being switched when not running tests to avoid quirky behavior.
	 */
	private static function prevent_switching_when_not_testing() {
		if ( defined( 'WCPAY_TEST_ENV' ) && WCPAY_TEST_ENV ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		trigger_error(
			'Toggling test/dev mode for WooCommerce Payments when not running tests is strongly discouraged.',
			E_USER_DEPRECATED
		);
	}
}

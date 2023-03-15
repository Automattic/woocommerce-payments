<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Logger;
use WC_Payments;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * A wrapper class for interacting with WC_Logger.
 */
class Logger {
	/**
	 * The holding property for our WC_Logger instance.
	 *
	 * @var WC_Logger $logger
	 */
	private static $logger;

	const LOG_FILENAME = 'woocommerce-payments';

	/**
	 * Add a log entry.
	 *
	 * Note that this depends on WC_Payments gateway property to be initialized as
	 * we need this to access the plugins debug setting to figure out if the setting
	 * is turned on.
	 *
	 * @param string $message Log message.
	 *
	 * @param string $level One of the following:
	 *     'emergency': System is unusable.
	 *     'alert': Action must be taken immediately.
	 *     'critical': Critical conditions.
	 *     'error': Error conditions.
	 *     'warning': Warning conditions.
	 *     'notice': Normal but significant condition.
	 *     'info': Informational messages.
	 *     'debug': Debug-level messages.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! self::can_log() ) {
			return;
		}

		self::init_logger();
		self::$logger->log( $level, $message, [ 'source' => self::LOG_FILENAME ] );
	}

	/**
	 * Initiate logger property with the WooCommerce core logger only if it's not set already
	 */
	public static function init_logger() {
		if ( ! isset( self::$logger ) && ! is_object( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}
	}

	/**
	 * Checks if the gateway setting logging toggle is enabled.
	 *
	 * @return bool Depending on the enable_logging setting.
	 */
	public static function can_log() {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return false;
		}

		if ( is_null( WC_Payments::get_gateway() ) ) {
			return false;
		}

		if ( WC_Payments::mode()->is_dev() ) {
			return true;
		}

		return 'yes' === WC_Payments::get_gateway()->get_option( 'enable_logging' );
	}

	/**
	 * Creates a log entry of type emergency
	 *
	 * @param string $message To send to the log file.
	 */
	public static function emergency( $message ) {
		self::log( $message, 'emergency' );
	}

	/**
	 * Creates a log entry of type alert
	 *
	 * @param string $message To send to the log file.
	 */
	public static function alert( $message ) {
		self::log( $message, 'alert' );
	}

	/**
	 * Creates a log entry of type critical
	 *
	 * @param string $message To send to the log file.
	 */
	public static function critical( $message ) {
		self::log( $message, 'critical' );
	}

	/**
	 * Creates a log entry of type error
	 *
	 * @param string $message To send to the log file.
	 */
	public static function error( $message ) {
		self::log( $message, 'error' );
	}

	/**
	 * Creates a log entry of type warning
	 *
	 * @param string $message To send to the log file.
	 */
	public static function warning( $message ) {
		self::log( $message, 'warning' );
	}

	/**
	 * Creates a log entry of type notice
	 *
	 * @param string $message To send to the log file.
	 */
	public static function notice( $message ) {
		self::log( $message, 'notice' );
	}

	/**
	 * Creates a log entry of type info
	 *
	 * @param string $message To send to the log file.
	 */
	public static function info( $message ) {
		self::log( $message, 'info' );
	}

	/**
	 * Creates a log entry of type debug
	 *
	 * @param string $message To send to the log file.
	 */
	public static function debug( $message ) {
		self::log( $message, 'debug' );
	}
}

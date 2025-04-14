<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WCPay\Internal\Logger as InternalLogger;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * A wrapper class for interacting with WC_Logger.
 */
class Logger {

	/**
	 * Add a log entry.
	 *
	 * Note that this depends on WC_Payments gateway property to be initialized as
	 * we need this to access the plugins debug setting to figure out if the setting
	 * is turned on.
	 *
	 * @param string               $message Log message.
	 *
	 * @param string               $level One of the following:
	 *                   'emergency': System is unusable.
	 *                   'alert': Action must be taken immediately.
	 *                   'critical': Critical conditions.
	 *                   'error': Error conditions.
	 *                   'warning': Warning conditions.
	 *                   'notice': Normal but significant condition.
	 *                   'info': Informational messages.
	 *                   'debug': Debug-level messages.
	 *  @param array<string, mixed> $context Context data.
	 */
	public static function log( $message, $level = 'info', $context = [] ) {
		wcpay_get_container()->get( InternalLogger::class )->log( $message, $level, $context );
	}

	/**
	 * Checks if the gateway setting logging toggle is enabled.
	 *
	 * @return bool Depending on the enable_logging setting.
	 */
	public static function can_log() {
		return wcpay_get_container()->get( InternalLogger::class )->can_log();
	}

	/**
	 * Creates a log entry of type emergency
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function emergency( $message, $context = [] ) {
		self::log( $message, 'emergency', $context );
	}

	/**
	 * Creates a log entry of type alert
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function alert( $message, $context = [] ) {
		self::log( $message, 'alert', $context );
	}

	/**
	 * Creates a log entry of type critical
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function critical( $message, $context = [] ) {
		self::log( $message, 'critical', $context );
	}

	/**
	 * Creates a log entry of type error
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function error( $message, $context = [] ) {
		self::log( $message, 'error', $context );
	}

	/**
	 * Creates a log entry of type warning
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function warning( $message, $context = [] ) {
		self::log( $message, 'warning', $context );
	}

	/**
	 * Creates a log entry of type notice
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function notice( $message, $context = [] ) {
		self::log( $message, 'notice', $context );
	}

	/**
	 * Creates a log entry of type info
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function info( $message, $context = [] ) {
		self::log( $message, 'info', $context );
	}

	/**
	 * Creates a log entry of type debug
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public static function debug( $message, $context = [] ) {
		self::log( $message, 'debug', $context );
	}
}

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
	 * @param string                $message Log message.
	 *
	 * @param string                $level One of the following:
	 *                    'emergency': System is unusable.
	 *                    'alert': Action must be taken immediately.
	 *                    'critical': Critical conditions.
	 *                    'error': Error conditions.
	 *                    'warning': Warning conditions.
	 *                    'notice': Normal but significant condition.
	 *                    'info': Informational messages.
	 *                    'debug': Debug-level messages.
	 *  @param array<string, string> $context Context data.
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

	/**
	 * Formats an object for logging.
	 *
	 * @param string $label  Label for the object.
	 * @param mixed  $object Object to format.
	 * @return string
	 */
	public static function format_object( $label, $object ) {
		try {
			$encoded = wp_json_encode( $object, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			return sprintf( 'Error encoding object "%s": %s', $label, $e->getMessage() );
		}
		return sprintf( '%s (JSON): %s', $label, $encoded );
	}
}

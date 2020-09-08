<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

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

	const STRIPE_KEYS_TO_REDACT = [
		'client_secret',
		'email',
		'name',
		'phone',
		'line1',
		'line2',
		'postal_code',
		'state',
		'city',
		'country',
		'customer_name',
		'customer_email',
	];

	const LOG_MAX_RECURSION = 10;

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

		if ( WC_Payments::get_gateway()->is_in_dev_mode() ) {
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

	/**
	 * Redacts the provided assoc array and logs it using the ::log() method.
	 *
	 * @param string $prefix Log message prefix.
	 * @param array  $array  The array to log.
	 */
	public static function redact_and_log( $prefix, $array ) {
		// Check if logging is enabled to avoid unneccessary redact calls.
		if ( ! self::can_log() ) {
			return;
		}

		$redacted = self::redact_array( $array );

		self::log( $prefix . var_export( $redacted, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
	}

	/**
	 * Redacts the provided array, removing the sensitive information, and limits its depth to LOG_MAX_RECURSION.
	 *
	 * @param array   $array The array to redact.
	 * @param integer $level The current recursion level.
	 *
	 * @return array The redacted array.
	 */
	private static function redact_array( $array, $level = 0 ) {
		if ( $level >= self::LOG_MAX_RECURSION ) {
			return '(recursion limit reached)';
		}

		if ( is_object( $array ) ) {
			// TODO: if we ever want to log objects, they could implement a method returning an array or string.
			return get_class( $array );
		}

		if ( ! is_array( $array ) ) {
			return $array;
		}

		$result = [];

		foreach ( $array as $key => $value ) {
			if ( in_array( $key, self::STRIPE_KEYS_TO_REDACT, true ) ) {
				$result[ $key ] = '(redacted)';
				continue;
			}

			if ( is_array( $value ) ) {
				$result[ $key ] = self::redact_array( $value, $level + 1 );
				continue;
			}

			$result[ $key ] = $value;
		}

		return $result;
	}
}

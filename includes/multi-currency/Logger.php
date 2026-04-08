<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

/**
 * Logger for multi-currency.
 */
class Logger {

	/**
	 * Log source identifier.
	 */
	const LOG_FILE = 'woopayments-multi-currency';

	/**
	 * The WooCommerce logger instance.
	 *
	 * @var \WC_Logger|null
	 */
	private $logger;

	/**
	 * Log a debug message.
	 *
	 * @param string $message The message to log.
	 */
	public static function debug( $message ) {
		self::log( 'debug', $message );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The message to log.
	 */
	public static function error( $message ) {
		self::log( 'error', $message );
	}

	/**
	 * Log a notice message.
	 *
	 * @param string $message The message to log.
	 */
	public static function notice( $message ) {
		self::log( 'notice', $message );
	}

	/**
	 * Log a message with a specific level.
	 *
	 * @param string $level   The log level (debug, error, notice, etc.).
	 * @param string $message The message to log.
	 */
	private static function log( $level, $message ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->log( $level, $message, [ 'source' => self::LOG_FILE ] );
	}
}

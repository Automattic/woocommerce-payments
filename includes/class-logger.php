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

	/**
	 * Add a log entry.
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
}

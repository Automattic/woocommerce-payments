<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal;

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
	private $logger;

	const LOG_FILENAME = 'woocommerce-payments';

	/**
	 * Add a log entry.
	 *
	 * Note that this depends on WC_Payments gateway property to be initialized as
	 * we need this to access the plugins debug setting to figure out if the setting
	 * is turned on.
	 *
	 * @param string $message Log message.
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
	public function log( $message, $level = 'info' ) {
		if ( ! $this->can_log() ) {
			return;
		}

		$this->init_logger();
		$this->logger->log( $level, $message, [ 'source' => self::LOG_FILENAME ] );
	}

	/**
	 * Initiate logger property with the WooCommerce core logger only if it's not set already
	 */
	public function init_logger() {
		if ( ! isset( $this->logger ) && ! is_object( $this->logger ) ) {
			$this->logger = wc_get_logger();
		}
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->init_logger();
	}

	/**
	 * Checks if the gateway setting logging toggle is enabled.
	 *
	 * @return bool Depending on the enable_logging setting.
	 */
	public function can_log() {
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
	public function emergency( $message ) {
		$this->log( $message, 'emergency' );
	}

	/**
	 * Creates a log entry of type alert
	 *
	 * @param string $message To send to the log file.
	 */
	public function alert( $message ) {
		$this->log( $message, 'alert' );
	}

	/**
	 * Creates a log entry of type critical
	 *
	 * @param string $message To send to the log file.
	 */
	public function critical( $message ) {
		$this->log( $message, 'critical' );
	}

	/**
	 * Creates a log entry of type error
	 *
	 * @param string $message To send to the log file.
	 */
	public function error( $message ) {
		$this->log( $message, 'error' );
	}

	/**
	 * Creates a log entry of type warning
	 *
	 * @param string $message To send to the log file.
	 */
	public function warning( $message ) {
		$this->log( $message, 'warning' );
	}

	/**
	 * Creates a log entry of type notice
	 *
	 * @param string $message To send to the log file.
	 */
	public function notice( $message ) {
		$this->log( $message, 'notice' );
	}

	/**
	 * Creates a log entry of type info
	 *
	 * @param string $message To send to the log file.
	 */
	public function info( $message ) {
		$this->log( $message, 'info' );
	}

	/**
	 * Creates a log entry of type debug
	 *
	 * @param string $message To send to the log file.
	 */
	public function debug( $message ) {
		$this->log( $message, 'debug' );
	}
}

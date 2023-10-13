<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal;

use WC_Logger;
use WC_Payments;
use WCPay\Core\Mode;
use WCPay\Internal\Proxy\LegacyProxy;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * A wrapper class for interacting with WC_Logger.
 */
class Logger {

	const LOG_FILENAME = 'woocommerce-payments';

	/**
	 * The holding property for our WC_Logger instance.
	 *
	 * @var WC_Logger $logger
	 */
	private $wc_logger;

	/**
	 * Mode
	 *
	 * @var Mode
	 */
	private $mode;

	/**
	 * Logger constructor.
	 *
	 * @param WC_Logger $wc_logger    Legacy proxy.
	 * @param Mode      $mode            Mode.
	 */
	public function __construct( WC_Logger $wc_logger, Mode $mode ) {
		$this->wc_logger = $wc_logger;
		$this->mode      = $mode;
	}

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
	 * @throws \Exception Throws Exception if log cannot be written.
	 */
	public function log( $message, $level = 'info' ) {
		if ( ! $this->can_log() ) {
			return;
		}

		$this->logger->log( $level, $message, [ 'source' => self::LOG_FILENAME ] );
	}


	/**
	 * Checks if the gateway setting logging toggle is enabled.
	 *
	 * @return bool Depending on the enable_logging setting.
	 * @throws \Exception Throws Exception for any issue in accessing mode.
	 */
	public function can_log() {

		if ( $this->mode->is_dev() ) {
			return true;
		}

		return 'yes' === WC_Payments::get_gateway()->get_option( 'enable_logging' );
	}

	/**
	 * Creates a log entry of type emergency
	 *
	 * @param string $message To send to the log file.
	 * @throws \Exception Throws Exception if log cannot be written.
	 */
	public function emergency( $message ) {
		$this->log( $message, 'emergency' );
	}

	/**
	 * Creates a log entry of type alert
	 *
	 * @param string $message To send to the log file.
	 * @throws \Exception Throws Exception if log cannot be written.
	 */
	public function alert( $message ) {
		$this->log( $message, 'alert' );
	}

	/**
	 * Creates a log entry of type critical
	 *
	 * @param string $message To send to the log file.
	 * @throws \Exception Throws Exception if log cannot be written.
	 */
	public function critical( $message ) {
		$this->log( $message, 'critical' );
	}

	/**
	 * Creates a log entry of type error
	 *
	 * @param string $message To send to the log file.
	 * @throws \Exception Throws Exception if log cannot be written.
	 */
	public function error( $message ) {
		$this->log( $message, 'error' );
	}

	/**
	 * Creates a log entry of type warning
	 *
	 * @param string $message To send to the log file.
	 * @throws \Exception Throws Exception if log cannot be written.
	 */
	public function warning( $message ) {
		$this->log( $message, 'warning' );
	}

	/**
	 * Creates a log entry of type notice
	 *
	 * @param string $message To send to the log file.
	 * @throws \Exception Throws Exception if log cannot be written.
	 */
	public function notice( $message ) {
		$this->log( $message, 'notice' );
	}

	/**
	 * Creates a log entry of type info
	 *
	 * @param string $message To send to the log file.
	 * @throws \Exception Throws Exception if log cannot be written.
	 */
	public function info( $message ) {
		$this->log( $message, 'info' );
	}

	/**
	 * Creates a log entry of type debug
	 *
	 * @param string $message To send to the log file.
	 * @throws \Exception Throws Exception if log cannot be written.
	 */
	public function debug( $message ) {
		$this->log( $message, 'debug' );
	}
}

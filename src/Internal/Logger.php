<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal;

use Exception;
use WC_Logger;
use WC_Payment_Gateway_WCPay;
use WCPay\Core\Mode;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * A wrapper class for interacting with WC_Logger.
 * Intended as an internal logger for classes within src.
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
	 * WC_Payment_Gateway_WCPay
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Logger constructor.
	 *
	 * @param WC_Logger $wc_logger    WC_Logger.
	 * @param Mode      $mode         Mode.
	 */
	public function __construct( WC_Logger $wc_logger, Mode $mode, WC_Payment_Gateway_WCPay $gateway ) {
		$this->wc_logger = $wc_logger;
		$this->mode      = $mode;
		$this->gateway   = $gateway;
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
	 */
	public function log( $message, $level = 'info' ) : void {
		if ( ! $this->can_log() ) {
			return;
		}
		$this->wc_logger->log( $level, $message, [ 'source' => self::LOG_FILENAME ] );
	}

	/**
	 * Checks if the gateway setting logging toggle is enabled.
	 *
	 * @return bool Depending on the enable_logging setting.
	 */
	public function can_log() {
		try {
			if ( $this->mode->is_dev() ) {
				return true;
			}
		} catch ( Exception $e ) {
			return false;
		}
		return 'yes' === $this->gateway->get_option( 'enable_logging' );
	}

	/**
	 * Creates a log entry of type emergency
	 *
	 * @param string $message To send to the log file.
	 */
	public function emergency( $message ) : void {
		$this->log( $message, 'emergency' );
	}

	/**
	 * Creates a log entry of type alert
	 *
	 * @param string $message To send to the log file.
	 */
	public function alert( $message ) : void {
		$this->log( $message, 'alert' );
	}

	/**
	 * Creates a log entry of type critical
	 *
	 * @param string $message To send to the log file.
	 */
	public function critical( $message ) : void {
		$this->log( $message, 'critical' );
	}

	/**
	 * Creates a log entry of type error
	 *
	 * @param string $message To send to the log file.
	 */
	public function error( $message ) : void {
		$this->log( $message, 'error' );
	}

	/**
	 * Creates a log entry of type warning
	 *
	 * @param string $message To send to the log file.
	 */
	public function warning( $message ) : void {
		$this->log( $message, 'warning' );
	}

	/**
	 * Creates a log entry of type notice
	 *
	 * @param string $message To send to the log file.
	 */
	public function notice( $message ) : void {
		$this->log( $message, 'notice' );
	}

	/**
	 * Creates a log entry of type info
	 *
	 * @param string $message To send to the log file.
	 */
	public function info( $message ) : void {
		$this->log( $message, 'info' );
	}

	/**
	 * Creates a log entry of type debug
	 *
	 * @param string $message To send to the log file.
	 */
	public function debug( $message ) : void {
		$this->log( $message, 'debug' );
	}
}

<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal;

use Exception;
use WC_Log_Levels;
use WC_Logger_Interface;
use WC_Payment_Gateway_WCPay;
use WCPay\Core\Mode;

/**
 * A wrapper class for interacting with WC_Logger.
 */
class Logger {

	const LOG_FILENAME = 'woocommerce-payments';

	/**
	 * The holding property for our WC_Logger_Interface instance.
	 *
	 * @var WC_Logger_Interface $logger
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
	 * @param WC_Logger_Interface      $wc_logger    WC_Logger_Interface.
	 * @param Mode                     $mode         Mode.
	 * @param WC_Payment_Gateway_WCPay $gateway      WC_Payment_Gateway_WCPay.
	 */
	public function __construct( WC_Logger_Interface $wc_logger, Mode $mode, WC_Payment_Gateway_WCPay $gateway ) {
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
		$this->log( $message, WC_Log_Levels::EMERGENCY );
	}

	/**
	 * Creates a log entry of type alert
	 *
	 * @param string $message To send to the log file.
	 */
	public function alert( $message ) : void {
		$this->log( $message, WC_Log_Levels::ALERT );
	}

	/**
	 * Creates a log entry of type critical
	 *
	 * @param string $message To send to the log file.
	 */
	public function critical( $message ) : void {
		$this->log( $message, WC_Log_Levels::CRITICAL );
	}

	/**
	 * Creates a log entry of type error
	 *
	 * @param string $message To send to the log file.
	 */
	public function error( $message ) : void {
		$this->log( $message, WC_Log_Levels::ERROR );
	}

	/**
	 * Creates a log entry of type warning
	 *
	 * @param string $message To send to the log file.
	 */
	public function warning( $message ) : void {
		$this->log( $message, WC_Log_Levels::WARNING );
	}

	/**
	 * Creates a log entry of type notice
	 *
	 * @param string $message To send to the log file.
	 */
	public function notice( $message ) : void {
		$this->log( $message, WC_Log_Levels::NOTICE );
	}

	/**
	 * Creates a log entry of type info
	 *
	 * @param string $message To send to the log file.
	 */
	public function info( $message ) : void {
		$this->log( $message, WC_Log_Levels::INFO );
	}

	/**
	 * Creates a log entry of type debug
	 *
	 * @param string $message To send to the log file.
	 */
	public function debug( $message ) : void {
		$this->log( $message, WC_Log_Levels::DEBUG );
	}
}

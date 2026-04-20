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
use WCPay\Logger_Context;

/**
 * A wrapper class for interacting with WC_Logger.
 */
class Logger {

	const LOG_FILENAME = 'woopayments';

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
	 * Logger constructor.
	 *
	 * @param WC_Logger_Interface $wc_logger    WC_Logger_Interface.
	 * @param Mode                $mode         Mode.
	 */
	public function __construct( WC_Logger_Interface $wc_logger, Mode $mode ) {
		$this->wc_logger = $wc_logger;
		$this->mode      = $mode;
	}

	/**
	 * Add a log entry.
	 *
	 * @param string               $message Log message.
	 * @param string               $level One of the following:
	 *                   'emergency': System is unusable.
	 *                   'alert': Action must be taken immediately.
	 *                   'critical': Critical conditions.
	 *                   'error': Error conditions.
	 *                   'warning': Warning conditions.
	 *                   'notice': Normal but significant condition.
	 *                   'info': Informational messages.
	 *                   'debug': Debug-level messages.
	 * @param array<string, mixed> $context Context data.
	 */
	public function log( $message, $level = 'info', $context = [] ): void {
		if ( ! $this->can_log() ) {
			return;
		}
		$context = array_merge( Logger_Context::get_context(), $context, [ 'source' => self::LOG_FILENAME ] );
		$this->wc_logger->log( $level, $message, $context );
	}

	/**
	 * Checks if the setting logging toggle is enabled.
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

		// Getting the gateway settings directly from the database so the gateway doesn't need to be initialized.
		$settings_option_name = 'woocommerce_' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '_settings';
		$wcpay_settings       = get_option( $settings_option_name );

		return 'yes' === ( $wcpay_settings['enable_logging'] ?? false );
	}

	/**
	 * Creates a log entry of type emergency
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public function emergency( $message, $context = [] ): void {
		$this->log( $message, WC_Log_Levels::EMERGENCY, $context );
	}

	/**
	 * Creates a log entry of type alert
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public function alert( $message, $context = [] ): void {
		$this->log( $message, WC_Log_Levels::ALERT, $context );
	}

	/**
	 * Creates a log entry of type critical
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public function critical( $message, $context = [] ): void {
		$this->log( $message, WC_Log_Levels::CRITICAL, $context );
	}

	/**
	 * Creates a log entry of type error
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public function error( $message, $context = [] ): void {
		$this->log( $message, WC_Log_Levels::ERROR, $context );
	}

	/**
	 * Creates a log entry of type warning
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public function warning( $message, $context = [] ): void {
		$this->log( $message, WC_Log_Levels::WARNING, $context );
	}

	/**
	 * Creates a log entry of type notice
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public function notice( $message, $context = [] ): void {
		$this->log( $message, WC_Log_Levels::NOTICE, $context );
	}

	/**
	 * Creates a log entry of type info
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public function info( $message, $context = [] ): void {
		$this->log( $message, WC_Log_Levels::INFO, $context );
	}

	/**
	 * Creates a log entry of type debug
	 *
	 * @param string               $message To send to the log file.
	 * @param array<string, mixed> $context Context data.
	 */
	public function debug( $message, $context = [] ): void {
		$this->log( $message, WC_Log_Levels::DEBUG, $context );
	}
}

<?php
/**
 * Class WC_Payments_Migration_Log_Handler
 *
 * @package WooCommerce\Payments
 */

use Automattic\Jetpack\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Class for handling Stripe billing -> tokenized migration logs.
 */
class WC_Payments_Subscription_Migration_Log_Handler {

	/**
	 * Log handle (name).
	 *
	 * @var string
	 */
	const HANDLE = 'woopayments-subscription-migration';

	/**
	 * A flag temporarily stored in the context column used to identify DB log entries that have been extended to avoid automated deletion.
	 *
	 * @var array
	 */
	const EXTENDED_DB_ENTRY_FLAG = 'extended_migration_log';

	/**
	 * The number of years to extend the life of a DB log entry for temporarily.
	 *
	 * @var int
	 */
	const DB_ENTRY_EXTENSION_IN_YEARS = 5;

	/**
	 * The holding property for our WC_Logger instance.
	 *
	 * @var WC_Logger
	 */
	private $logger = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// WC Core will delete logs on priority 10, so we need to run before that.
		if ( $this->has_file_logger_enabled() ) {
			add_action( 'woocommerce_cleanup_logs', [ $this, 'extend_life_of_migration_file_logs' ], 5 );
		} elseif ( $this->has_db_logger_enabled() ) {
			add_action( 'woocommerce_cleanup_logs', [ $this, 'extend_life_of_migration_db_logs' ], 5 );
			add_action( 'woocommerce_cleanup_logs', [ $this, 'restore_db_log_timestamps' ], 100 );
		}
	}

	/**
	 * Logs a message to the migration log.
	 *
	 * @param string $message The message to log.
	 */
	public function log( $message ) {
		if ( ! $this->logger ) {
			$this->logger = wc_get_logger();
		}

		$this->logger->debug( $message, [ 'source' => self::HANDLE ] );
	}

	/**
	 * Extends the life of all Stripe billing -> tokenized migration log files to prevent WC Core deleting them.
	 *
	 * WC uses the file's last modified timestamp to determine whether to delete it. This function simply
	 * touches all migration log files to update their last modified timestamp and to bypass WC core's process.
	 */
	public function extend_life_of_migration_file_logs() {
		foreach ( WC_Log_Handler_File::get_log_files() as $log_file_name ) {
			// If the log file name starts with our handle, "touch" it to update the last modified timestamp.
			if ( strpos( $log_file_name, self::HANDLE ) === 0 ) {
				touch( trailingslashit( WC_LOG_DIR ) . $log_file_name );
			}
		}
	}

	/**
	 * Extends the life of all Stripe billing -> tokenized migration DB log entries to prevent WC Core deleting them.
	 *
	 * This function temporarily adds 5 years to all migration log entries, and adds a flag to the context column to
	 * identify them for reinstating the actual timestamp later. Hooked in after this function,
	 * `restore_db_log_timestamps` will reinstate the actual timestamps by subtracting the 5 years.
	 *
	 * @see restore_db_log_timestamps()
	 */
	public function extend_life_of_migration_db_logs() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}woocommerce_log
				SET timestamp = DATE_ADD( timestamp, INTERVAL %d YEAR ), context = %s
				WHERE source = %s",
				self::DB_ENTRY_EXTENSION_IN_YEARS,
				self::EXTENDED_DB_ENTRY_FLAG,
				self::HANDLE
			)
		);
	}

	/**
	 * Reinstates the actual timestamps of all Stripe billing -> tokenized migration DB log entries.
	 *
	 * This function is hooked in after `extend_life_of_migration_db_logs` to reinstate the actual timestamps
	 * of all migration log entries by subtracting the 5 years that were added to them.
	 *
	 * @see extend_life_of_migration_db_logs()
	 */
	public function restore_db_log_timestamps() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}woocommerce_log
				SET timestamp = DATE_SUB( timestamp, INTERVAL %d YEAR ), context = NULL
				WHERE source = %s AND context = %s",
				self::DB_ENTRY_EXTENSION_IN_YEARS,
				self::HANDLE,
				self::EXTENDED_DB_ENTRY_FLAG
			)
		);
	}

	/**
	 * Gets the default log handler class.
	 *
	 * @return string The default log handler class.
	 */
	private function get_default_log_handler_class() {
		$handler_class = Constants::get_constant( 'WC_LOG_HANDLER' );

		if ( is_null( $handler_class ) || ! class_exists( $handler_class ) ) {
			$handler_class = WC_Log_Handler_File::class;
		}

		return $handler_class;
	}

	/**
	 * Determines whether the file logger is enabled.
	 *
	 * @return boolean
	 */
	private function has_file_logger_enabled() {
		return $this->get_default_log_handler_class() === WC_Log_Handler_File::class;
	}

	/**
	 * Determines whether the DB logger is enabled.
	 *
	 * @return boolean
	 */
	private function has_db_logger_enabled() {
		return $this->get_default_log_handler_class() === WC_Log_Handler_DB::class;
	}
}

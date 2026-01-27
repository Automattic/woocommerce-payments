<?php
/**
 * Class WC_Payments_Subscription_Migration_Log_Handler_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\Jetpack\Constants;

/**
 * WC_Payments_Subscription_Migration_Log_Handler_Test unit tests.
 */
class WC_Payments_Subscription_Migration_Log_Handler_Test extends WCPAY_UnitTestCase {

	/**
	 * A log source used for testing dummy logs.
	 *
	 * @var string
	 */
	private $test_log_source = 'dummy-log-source';

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		global $wpdb;

		// Remove all log files.
		foreach ( $this->get_log_files() as $log_file ) {
			unlink( $log_file );
		}

		// Remove all log entries.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}woocommerce_log
				WHERE source IN ( %s, %s )",
				WC_Payments_Subscription_Migration_Log_Handler::HANDLE,
				$this->test_log_source
			)
		);

		parent::tearDown();
	}

	/**
	 * Test that the logger is called with the correct arguments.
	 */
	public function test_log() {
		// Mock the logger.
		$mock_logger = $this->getMockBuilder( 'WC_Logger' )
			->disableOriginalConstructor()
			->getMock();

		$mock_logger->expects( $this->once() )
			->method( 'debug' )
			->with( 'test message', [ 'source' => WC_Payments_Subscription_Migration_Log_Handler::HANDLE ] );

		$logger = new WC_Payments_Subscription_Migration_Log_Handler();

		$reflection = new ReflectionClass( $logger );
		$property   = $reflection->getProperty( 'logger' );
		$property->setAccessible( true );
		$property->setValue( $logger, $mock_logger );

		$logger->log( 'test message' );
	}

	/**
	 * Tests that migration log files are renamed to today's date when the cleanup action runs.
	 *
	 * This ensures our migration files won't be deleted by WC's date-based log cleanup.
	 */
	public function test_extend_life_of_migration_file_logs() {
		$message = 'Test message 1234567890';
		$log_dir = trailingslashit( WC_LOG_DIR );

		// Log a message to create the migration file.
		$logger = new WC_Payments_Subscription_Migration_Log_Handler();
		$logger->log( $message );

		$log_files = $this->get_log_files();
		$this->assertNotEmpty( $log_files, 'Migration log file should be created.' );

		// Rename migration file to have an old date in the filename to simulate an aged log file.
		$old_date = gmdate( 'Y-m-d', time() - YEAR_IN_SECONDS );
		foreach ( $log_files as $log_file ) {
			$old_filename  = basename( $log_file );
			$new_filename  = preg_replace( '/\d{4}-\d{2}-\d{2}/', $old_date, $old_filename );
			$new_file_path = $log_dir . $new_filename;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			rename( $log_file, $new_file_path );
		}

		// Verify the file now has the old date.
		$log_files_after_rename = $this->get_log_files();
		$this->assertNotEmpty( $log_files_after_rename, 'Migration log file should exist after renaming.' );
		$this->assertStringContainsString( $old_date, basename( $log_files_after_rename[0] ) );

		// Trigger WC's log cleanup - our handler runs at priority 5 to rename files before WC deletes them.
		do_action( 'woocommerce_cleanup_logs' );

		// Verify migration file still exists and has been renamed to today's date.
		$migration_file_found = false;
		$today                = gmdate( 'Y-m-d' );

		foreach ( WC_Log_Handler_File::get_log_files() as $log_file_name ) {
			if ( strpos( $log_file_name, WC_Payments_Subscription_Migration_Log_Handler::HANDLE ) === 0 ) {
				$migration_file_found = true;
				$migration_file_path  = $log_dir . $log_file_name;

				// Verify file exists and has today's date in the filename.
				$this->assertFileExists( $migration_file_path );
				$this->assertStringContainsString( $today, $log_file_name, 'Migration file should be renamed to today\'s date.' );
				$this->assertStringContainsString( $message, file_get_contents( $migration_file_path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		$this->assertTrue( $migration_file_found, 'Migration log file should exist after cleanup.' );
	}

	/**
	 * Tests that when merging log files, chronological order is preserved (old logs first, then new logs).
	 *
	 * This ensures memory-efficient stream-based merging maintains proper log order.
	 */
	public function test_extend_life_of_migration_file_logs_merges_files_in_chronological_order() {
		$log_dir       = trailingslashit( WC_LOG_DIR );
		$old_message   = 'Old message from yesterday';
		$new_message   = 'New message from today';
		$today         = gmdate( 'Y-m-d' );
		$old_date      = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
		$hash          = wp_hash( WC_Payments_Subscription_Migration_Log_Handler::HANDLE );
		$old_file_name = WC_Payments_Subscription_Migration_Log_Handler::HANDLE . "-{$old_date}-{$hash}.log";
		$new_file_name = WC_Payments_Subscription_Migration_Log_Handler::HANDLE . "-{$today}-{$hash}.log";
		$old_file_path = $log_dir . $old_file_name;
		$new_file_path = $log_dir . $new_file_name;

		// Create an "old" log file with old date in filename.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $old_file_path, $old_message . "\n" );

		// Create a "new" log file with today's date in filename.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $new_file_path, $new_message . "\n" );

		// Verify both files exist before cleanup.
		$this->assertFileExists( $old_file_path, 'Old log file should exist before cleanup.' );
		$this->assertFileExists( $new_file_path, 'New log file should exist before cleanup.' );

		// Trigger WC's log cleanup - our handler merges and renames files.
		do_action( 'woocommerce_cleanup_logs' );

		// Old file should be gone (merged into new file).
		$this->assertFileDoesNotExist( $old_file_path, 'Old log file should be deleted after merge.' );

		// New file should exist with merged content.
		$this->assertFileExists( $new_file_path, 'New log file should exist after merge.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$merged_content = file_get_contents( $new_file_path );

		// Verify both messages are present.
		$this->assertStringContainsString( $old_message, $merged_content, 'Old message should be in merged file.' );
		$this->assertStringContainsString( $new_message, $merged_content, 'New message should be in merged file.' );

		// Verify chronological order: old message should appear before new message.
		$old_position = strpos( $merged_content, $old_message );
		$new_position = strpos( $merged_content, $new_message );
		$this->assertLessThan( $new_position, $old_position, 'Old logs should appear before new logs in merged file.' );
	}

	/**
	 * Tests that log db entries that a created by WC_Payments_Subscription_Migration_Log_Handler are not deleted by WC's log cleanup.
	 *
	 * Confirms that our log entries are not deleted by WC's log cleanup and that mock log entries are deleted.
	 */
	public function test_extend_life_of_migration_db_logs() {
		global $wpdb;

		$db_logger = $this->get_logger_with_db_log_handler();
		$logger    = new WC_Payments_Subscription_Migration_Log_Handler();

		$this->set_logger_property( $logger, $db_logger );

		$message = 'Test message 1234567890';

		// Log messages - Log a migration entry and a dummy log.
		$logger->log( $message );
		$db_logger->log( 'debug', $message, [ 'source' => $this->test_log_source ] );

		// Mock the log entries being very old.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}woocommerce_log
				SET timestamp = DATE_SUB( timestamp, INTERVAL 1 YEAR )
				WHERE source IN ( %s, %s )",
				WC_Payments_Subscription_Migration_Log_Handler::HANDLE,
				$this->test_log_source
			)
		);

		// Confirm initial state.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source, COUNT(*) as count
				FROM {$wpdb->prefix}woocommerce_log
				WHERE source IN ( %s, %s )
				GROUP BY source",
				WC_Payments_Subscription_Migration_Log_Handler::HANDLE,
				$this->test_log_source
			),
			ARRAY_A
		);

		$results = wp_list_pluck( $results, 'count', 'source' );

		// Confirm our migration and dummy entries exist.
		$this->assertArrayHasKey( WC_Payments_Subscription_Migration_Log_Handler::HANDLE, $results );
		$this->assertEquals( 1, $results[ WC_Payments_Subscription_Migration_Log_Handler::HANDLE ] );
		$this->assertArrayHasKey( $this->test_log_source, $results );
		$this->assertEquals( 1, $results[ $this->test_log_source ] );

		// Trigger WC's log cleanup.
		do_action( 'woocommerce_cleanup_logs' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source, COUNT(*) as count
				FROM {$wpdb->prefix}woocommerce_log
				WHERE source IN ( %s, %s )
				GROUP BY source",
				WC_Payments_Subscription_Migration_Log_Handler::HANDLE,
				$this->test_log_source
			),
			ARRAY_A
		);

		$results = wp_list_pluck( $results, 'count', 'source' );

		// Assert that the dummy log entries have been deleted.
		$this->assertFalse( isset( $results[ $this->test_log_source ] ) );

		// Confirm our migration entries are still there.
		$this->assertArrayHasKey( WC_Payments_Subscription_Migration_Log_Handler::HANDLE, $results );
		$this->assertEquals( 1, $results[ WC_Payments_Subscription_Migration_Log_Handler::HANDLE ] );
	}

	// HELPER FUNCTIONS.

	/**
	 * Get all logs files associated with our log handler or these tests.
	 */
	private function get_log_files() {
		$logs = [];

		foreach ( WC_Log_Handler_File::get_log_files() as $log_file_name ) {
			$is_migration_log = strpos( $log_file_name, WC_Payments_Subscription_Migration_Log_Handler::HANDLE ) === 0;
			$is_test_log      = strpos( $log_file_name, $this->test_log_source ) === 0;

			if ( $is_migration_log || $is_test_log ) {
				$logs[] = trailingslashit( WC_LOG_DIR ) . $log_file_name;
			}
		}

		return $logs;
	}

	/**
	 * Sets up the DB log handler and returns the logger.
	 *
	 * @return WC_Logger
	 */
	private function get_logger_with_db_log_handler() {
		Constants::set_constant( 'WC_LOG_HANDLER', 'WC_Log_Handler_DB' );
		$mock_db_log_handler = new WC_Log_Handler_DB();
		$db_logger           = new WC_Logger( [ $mock_db_log_handler ] );

		add_action( 'woocommerce_cleanup_logs', [ $db_logger, 'clear_expired_logs' ] );

		// Since we changed the default log handler, we need to re-instantiate our log handler.
		return $db_logger;
	}

	/**
	 * Sets the private $logger property of a WC_Payments_Subscription_Migration_Log_Handler instance.
	 *
	 * @param WC_Payments_Subscription_Migration_Log_Handler $logger
	 * @param WC_Logger $wc_logger
	 */
	private function set_logger_property( $logger, $wc_logger ) {
		$reflection = new ReflectionClass( $logger );
		$property   = $reflection->getProperty( 'logger' );
		$property->setAccessible( true );
		$property->setValue( $logger, $wc_logger );
	}
}

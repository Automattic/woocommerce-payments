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
	 * Tests that log files that a created by WC_Payments_Subscription_Migration_Log_Handler are not deleted by WC's log cleanup.
	 *
	 * Confirms that log files are not deleted by WC's log cleanup and that mock log files are deleted.
	 */
	public function test_extend_life_of_migration_file_logs() {
		$message = 'Test message 1234567890';

		// Log messages - Log to the migration file and a dummy log.
		$logger = new WC_Payments_Subscription_Migration_Log_Handler();
		$logger->log( $message );
		wc_get_logger()->log( 'debug', $message, [ 'source' => $this->test_log_source ] );

		$log_files = $this->get_log_files();

		// Mock the log files being very old (1 year old).
		foreach ( $log_files as $log_file ) {
			touch( $log_file, time() - YEAR_IN_SECONDS );
		}

		// Trigger WC's log cleanup.
		do_action( 'woocommerce_cleanup_logs' );

		foreach ( $log_files as $log_file ) {
			// Confirm our test mock log file is deleted and our migration files aren't.
			if ( strpos( basename( $log_file ), $this->test_log_source ) === 0 ) {
				$this->assertFileDoesNotExist( $log_file );
			} else {
				$this->assertFileExists( $log_file );
				$this->assertStringContainsString( $message, file_get_contents( $log_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
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

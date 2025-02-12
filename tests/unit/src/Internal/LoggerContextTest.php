<?php
/**
 * Class LoggerContextTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use WCPAY_UnitTestCase;
use WCPay\Core\Mode;
use WCPay\Internal\LoggerContext;
use WCPay\Internal\Logger;
use WC_Log_Levels;
use WCPay\Logger_Context;

/**
 * Internal Logger Context tests.
 *
 */
class LoggerContextTest extends WCPAY_UnitTestCase {
	/**
	 * Holds the service under test, i.e. LoggerContext.
	 *
	 * @var LoggerContext&MockObject
	 */
	private $sut;

	public function setUp(): void {
		parent::setUp();

		$this->sut = new LoggerContext();
	}

	/**
	 * Test that the logger context is initialized correctly.
	 *
	 * @dataProvider provider_logger_context_applied
	 * @return void
	 */
	public function test_logger_context_applied( $setup_callback ) {
		$setup_callback( $this->sut );

		$entry_time = '2021-01-01 00:00:00 UTC';
		$timestamp  = strtotime( $entry_time );
		$message    = "Test log entry...\non two lines";
		$level      = WC_Log_Levels::INFO;

		$filtered_entry = apply_filters(
			'woocommerce_format_log_entry',
			$message,
			[
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
				'context'   => [ 'source' => Logger::LOG_FILENAME ],
			]
		);

		$this->assertEquals( 12, count( explode( "\n", $filtered_entry ) ), 'Filtered entry contains 3 lines with the context and 2 with the original message' );

		foreach ( explode( "\n", $filtered_entry ) as $line ) {
			$this->assertTrue( strpos( $line, '2021-01-01T00:00:00+00:00 INFO' ) === 0, 'Each line starts with the timestamp and log level' );
			$this->assertTrue( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2} [A-Z]+ [a-f0-9]+-(\d{4}) /', $line, $matches ) === 1, 'Each line contains timestamp, log level, request identifier and entry number' );
			$entry_number = (int) ltrim( $matches[1], '0' );
			$this->assertTrue( $entry_number > 0, 'Entry number is a positive integer' );
		}
	}

	/**
	 * Data provider for test_logger_context_applied.
	 *
	 * @return array
	 */
	public function provider_logger_context_applied() {
		return [
			[
				function ( $instance ) {
						$instance->init_hooks();
						$instance->set_value( 'foo', 'bar' );
				},
			],
			[
				function ( $instance ) {
					wcpay_get_container()->get( LoggerContext::class )->init_hooks();
					Logger_Context::set_value( 'foo', 'bar' );
				},
			],
			[
				function ( $instance ) {
						$instance->init_hooks();
						$instance->set_value( 'foo', 'bar' );
						// Set a value to be removed.
						$instance->set_value( 'baz', 'qux' );
						$instance->set_value( 'baz', null );
				},
			],
			[
				function ( $instance ) {
						$instance->init_hooks();
						$instance->init_hooks();
						$instance->set_value( 'foo', 'bar' );
				},
			],
		];
	}

	/**
	 * Test that logger filters are not active for other log sources.
	 *
	 * @return void
	 */
	public function test_logger_context_not_applied() {
		$this->sut->init_hooks();

		$entry_time = '2021-01-01 00:00:00 UTC';
		$timestamp  = strtotime( $entry_time );
		$message    = "Test log entry...\non two lines";
		$level      = WC_Log_Levels::INFO;

		$this->sut->set_value( 'foo', 'bar' );

		$filtered_entry = apply_filters(
			'woocommerce_format_log_entry',
			$message,
			[
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
				'context'   => [],
			]
		);

		$this->assertSame( $message, $filtered_entry, 'Filtered entry is the same as the original message' );

		$filtered_entry = apply_filters(
			'woocommerce_format_log_entry',
			$message,
			[
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
			]
		);

		$this->assertSame( $message, $filtered_entry, 'Filtered entry is the same as the original message' );
	}
}

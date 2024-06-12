<?php
/**
 * Class LoggerTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests;

use WCPAY_UnitTestCase;
use WC_Logger;
use WC_Log_Levels;
use WC_Payment_Gateway_WCPay;
use Exception;
use WCPay\Core\Mode;
use WCPay\Internal\Logger;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Internal Logger tests.
 *
 */
class LoggerTest extends WCPAY_UnitTestCase {
	/**
	 * Holds the logger, which will be tested.
	 *
	 * @var Logger|MockObject
	 */
	private $sut;

	/**
	 * Holds the underlying WC_Logger
	 *
	 * @var WC_Logger|MockObject
	 */
	private $mock_wc_logger;

	/**
	 * Holds the underlying WC_Logger
	 *
	 * @var Mode|MockObject
	 */
	private $mode;

	/**
	 * Sets up the logger.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_wc_logger = $this->createMock( WC_Logger::class );
		$this->mode           = $this->createMock( Mode::class );
		$this->sut            = $this->getMockBuilder( Logger::class )
			->setConstructorArgs(
				[
					$this->mock_wc_logger,
					$this->mode,
				]
			)
			->onlyMethods( [ 'can_log' ] )
			->getMock();
	}

	/**
	 * Test log method of the Logger class.
	 */
	public function test_log_success() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::INFO, 'Test Log 1' );
		$this->sut->log( 'Test Log 1' );
	}

	/**
	 * Test log method of the Logger class when can_log returns false.
	 */
	public function test_log_failure() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( false );
		$this->mock_wc_logger
			->expects( $this->never() )
			->method( 'log' );
		$this->sut->log( 'Test Log 1' );
	}

	/**
	 * Test logging at different log levels using the Logger.
	 * @dataProvider provider_log_levels
	 */
	public function test_log_levels( $method_name, $log_level ) {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( $log_level, 'Test Log 1' );
		$this->sut->{$method_name}( 'Test Log 1' );
	}

	/**
	 * Data provider for test_log_levels.
	 */
	public function provider_log_levels() {
		return [
			[ 'emergency', WC_Log_Levels::EMERGENCY ],
			[ 'alert', WC_Log_Levels::ALERT ],
			[ 'critical', WC_Log_Levels::CRITICAL ],
			[ 'error', WC_Log_Levels::ERROR ],
			[ 'warning', WC_Log_Levels::WARNING ],
			[ 'notice', WC_Log_Levels::NOTICE ],
			[ 'info', WC_Log_Levels::INFO ],
			[ 'debug', WC_Log_Levels::DEBUG ],
		];
	}

	/**
	 * Test that the can_log method of the Logger class returns true when the mode is dev.
	 */
	public function test_can_log_dev_mode() {
		$this->sut = new Logger(
			$this->mock_wc_logger,
			$this->mode
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willReturn( true );
		$this->assertTrue( $this->sut->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns false when the mode throws an exception.
	 */
	public function test_can_log_exception() {
		$this->sut = new Logger(
			$this->mock_wc_logger,
			$this->mode
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willThrowException( new Exception() );
		$this->assertFalse( $this->sut->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns false when the gateway option is not set.
	 */
	public function test_can_log_no_option() {
		$this->sut = new Logger(
			$this->mock_wc_logger,
			$this->mode
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willReturn( false );
		update_option( 'woocommerce_woocommerce_payments_settings', [] );
		$this->assertFalse( $this->sut->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns false when the gateway option is set to 'no'.
	 */
	public function test_can_log_disabled() {
		$this->sut = new Logger(
			$this->mock_wc_logger,
			$this->mode
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willReturn( false );
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'enable_logging' => 'no' ] );
		$this->assertFalse( $this->sut->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns true when the gateway option is set to 'yes'.
	 */
	public function test_can_log_enabled() {
		$this->sut = new Logger(
			$this->mock_wc_logger,
			$this->mode
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willReturn( false );
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'enable_logging' => 'yes' ] );
		$this->assertTrue( $this->sut->can_log() );
	}
}

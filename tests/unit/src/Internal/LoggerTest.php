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
	 * WC_Payment_Gateway_WCPay
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * Sets up the logger.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_wc_logger = $this->createMock( WC_Logger::class );
		$this->mode           = $this->createMock( Mode::class );
		$this->mock_gateway   = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->sut         = $this->getMockBuilder( Logger::class )
			->setConstructorArgs(
				[
					$this->mock_wc_logger,
					$this->mode,
					$this->mock_gateway,
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
	 * Test emergency method of the Logger class.
	 */
	public function test_log_level_emergency() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::EMERGENCY, 'Test Log Emergency' );
		$this->sut->emergency( 'Test Log Emergency' );
	}

	/**
	 * Test alert method of the Logger class.
	 */
	public function test_log_level_alert() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::ALERT, 'Test Log Alert' );
		$this->sut->alert( 'Test Log Alert' );
	}

	/**
	 * Test critical method of the Logger class.
	 */
	public function test_log_level_critical() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::CRITICAL, 'Test Log Critical' );
		$this->sut->critical( 'Test Log Critical' );
	}

	/**
	 * Test error method of the Logger class.
	 */
	public function test_log_level_error() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::ERROR, 'Test Log Error' );
		$this->sut->error( 'Test Log Error' );
	}

	/**
	 * Test warning method of the Logger class.
	 */
	public function test_log_level_warning() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::WARNING, 'Test Log Warning' );
		$this->sut->warning( 'Test Log Warning' );
	}

	/**
	 * Test notice method of the Logger class.
	 */
	public function test_log_level_notice() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::NOTICE, 'Test Log Notice' );
		$this->sut->notice( 'Test Log Notice' );
	}

	/**
	 * Test info method of the Logger class.
	 */
	public function test_log_level_info() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::INFO, 'Test Log Warning' );
		$this->sut->info( 'Test Log Warning' );
	}

	/**
	 * Test debug method of the Logger class.
	 */
	public function test_log_level_debug() {
		$this->sut->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::DEBUG, 'Test Log Debug' );
		$this->sut->debug( 'Test Log Debug' );
	}

	/**
	 * Test that the can_log method of the Logger class returns true when the mode is dev.
	 */
	public function test_can_log_dev_mode() {
		$this->sut = new Logger(
			$this->mock_wc_logger,
			$this->mode,
			$this->mock_gateway
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
			$this->mode,
			$this->mock_gateway
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
			$this->mode,
			$this->mock_gateway
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willReturn( false );
		$this->mock_gateway->expects( $this->once() )
			->method( 'get_option' )
			->with( 'enable_logging' )
			->willReturn( null );
		$this->assertFalse( $this->sut->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns false when the gateway option is set to 'no'.
	 */
	public function test_can_log_disabled() {
		$this->sut = new Logger(
			$this->mock_wc_logger,
			$this->mode,
			$this->mock_gateway
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willReturn( false );
		$this->mock_gateway->expects( $this->once() )
			->method( 'get_option' )
			->with( 'enable_logging' )
			->willReturn( 'no' );
		$this->assertFalse( $this->sut->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns true when the gateway option is set to 'yes'.
	 */
	public function test_can_log_enabled() {
		$this->sut = new Logger(
			$this->mock_wc_logger,
			$this->mode,
			$this->mock_gateway
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willReturn( false );
		$this->mock_gateway->expects( $this->once() )
			->method( 'get_option' )
			->with( 'enable_logging' )
			->willReturn( 'yes' );
		$this->assertTrue( $this->sut->can_log() );
	}

}
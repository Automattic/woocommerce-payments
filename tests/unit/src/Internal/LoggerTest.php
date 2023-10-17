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
	 * @var Logger
	 */
	private $logger;

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
		$this->logger         = $this->getMockBuilder( Logger::class )
			->setConstructorArgs(
				[
					$this->mock_wc_logger,
					$this->mode,
					$this->mock_gateway,
				]
			)
			->setMethods( [ 'can_log' ] )
			->getMock();
	}

	/**
	 * Test log method of the Logger class.
	 */
	public function test_log_success() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::INFO, 'Test Log 1' );
		$this->logger->log( 'Test Log 1' );
	}

	/**
	 * Test log method of the Logger class when can_log returns false.
	 */
	public function test_log_failure() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( false );
		$this->mock_wc_logger
			->expects( $this->never() )
			->method( 'log' );
		$this->logger->log( 'Test Log 1' );
	}

		/**
		 * Test emergency method of the Logger class.
		 */
	public function test_log_level_emergency() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::EMERGENCY, 'Test Log Emergency' );
		$this->logger->emergency( 'Test Log Emergency' );
	}

	/**
	 * Test alert method of the Logger class.
	 */
	public function test_log_level_alert() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::ALERT, 'Test Log Alert' );
		$this->logger->alert( 'Test Log Alert' );
	}

	/**
	 * Test critical method of the Logger class.
	 */
	public function test_log_level_critical() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::CRITICAL, 'Test Log Critical' );
		$this->logger->critical( 'Test Log Critical' );
	}

	/**
	 * Test error method of the Logger class.
	 */
	public function test_log_level_error() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::ERROR, 'Test Log Error' );
		$this->logger->error( 'Test Log Error' );
	}

	/**
	 * Test warning method of the Logger class.
	 */
	public function test_log_level_warning() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::WARNING, 'Test Log Warning' );
		$this->logger->warning( 'Test Log Warning' );
	}

	/**
	 * Test notice method of the Logger class.
	 */
	public function test_log_level_notice() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::NOTICE, 'Test Log Notice' );
		$this->logger->notice( 'Test Log Notice' );
	}

	/**
	 * Test info method of the Logger class.
	 */
	public function test_log_level_info() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::INFO, 'Test Log Warning' );
		$this->logger->info( 'Test Log Warning' );
	}


	/**
	 * Test debug method of the Logger class.
	 */
	public function test_log_level_debug() {
		$this->logger->expects( $this->once() )
			->method( 'can_log' )
			->willReturn( true );
		$this->mock_wc_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( WC_Log_Levels::DEBUG, 'Test Log Debug' );
		$this->logger->debug( 'Test Log Debug' );
	}

	/**
	 * Test that the can_log method of the Logger class returns true when the mode is dev.
	 */
	public function test_can_log_dev_mode() {
		$this->logger = new Logger(
			$this->mock_wc_logger,
			$this->mode,
			$this->mock_gateway
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willReturn( true );
		$this->assertTrue( $this->logger->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns false when the mode throws an exception.
	 */
	public function test_can_log_exception() {
		$this->logger = new Logger(
			$this->mock_wc_logger,
			$this->mode,
			$this->mock_gateway
		);
		$this->mode->expects( $this->once() )
			->method( 'is_dev' )
			->willThrowException( new Exception() );
		$this->assertFalse( $this->logger->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns false when the gateway option is not set.
	 */
	public function test_can_log_no_option() {
		$this->logger = new Logger(
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
		$this->assertFalse( $this->logger->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns false when the gateway option is set to 'no'.
	 */
	public function test_can_log_disabled() {
		$this->logger = new Logger(
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
		$this->assertFalse( $this->logger->can_log() );
	}

	/**
	 * Test that the can_log method of the Logger class returns true when the gateway option is set to 'yes'.
	 */
	public function test_can_log_enabled() {
		$this->logger = new Logger(
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
		$this->assertTrue( $this->logger->can_log() );
	}

}

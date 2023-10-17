<?php
/**
 * Class LoggerTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests;

use WCPAY_UnitTestCase;
use WC_Logger;
use WC_Payment_Gateway_WCPay;
use WCPay\Core\Mode;
use WCPay\Internal\Logger;

/**
 * Internal Logger tests.
 *
 */
class LoggerTest extends WCPAY_UnitTestCase {
	/**
	 * Holds the extended container, which will be tested.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Sets up the container.
	 */
	protected function setUp(): void {
		parent::setUp();

		$mock_wc_logger = $this->createMock( WC_Logger::class );
		$mode           = $this->createMock( Mode::class );
		$mock_gateway   = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->logger   = new Logger( $mock_wc_logger, $mode, $mock_gateway );
	}

	/**
	 * Test TBD
	 */
	public function test_tbd() {

	}
}

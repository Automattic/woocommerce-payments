<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Prices_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Frontend_Prices unit tests.
 */
class WCPay_Multi_Currency_Frontend_Prices_Tests extends WP_UnitTestCase {
	/**
	 * Mock WCPay\Multi_Currency\Multi_Currency.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\Multi_Currency\Frontend_Prices instance.
	 *
	 * @var WCPay\Multi_Currency\Frontend_Prices
	 */
	private $frontend_prices;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( WCPay\Multi_Currency\Multi_Currency::class );

		$this->frontend_prices = new WCPay\Multi_Currency\Frontend_Prices( $this->mock_multi_currency );
	}

	public function test_placeholder() {
		$this->assertTrue( true );
	}
}

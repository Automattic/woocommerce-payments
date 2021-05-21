<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Currencies_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Frontend_Currencies unit tests.
 */
class WCPay_Multi_Currency_Frontend_Currencies_Tests extends WP_UnitTestCase {
	/**
	 * Mock WCPay\Multi_Currency\Multi_Currency.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\Multi_Currency\Frontend_Currencies instance.
	 *
	 * @var WCPay\Multi_Currency\Frontend_Currencies
	 */
	private $frontend_currencies;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( WCPay\Multi_Currency\Multi_Currency::class );

		$this->frontend_currencies = new WCPay\Multi_Currency\Frontend_Currencies( $this->mock_multi_currency );
	}

	public function test_get_currency_code() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'USD' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertSame( 'USD', $this->frontend_currencies->get_current_currency_code() );
	}
}

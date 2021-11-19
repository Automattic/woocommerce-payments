<?php
/**
 * Class WCPay_Multi_Currency_WooCommercePreOrders_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility\WooCommercePreOrders;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * WCPay\MultiCurrency\Compatibility\WooCommercePreOrders unit tests.
 */
class WCPay_Multi_Currency_WooCommercePreOrders_Tests extends WP_UnitTestCase {

	/**
	 * Mock WCPay\MultiCurrency\MultiCurrency.
	 *
	 * @var WCPay\MultiCurrency\MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * Mock WCPay\MultiCurrency\Utils.
	 *
	 * @var WCPay\MultiCurrency\Utils|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_utils;

	/**
	 * WCPay\MultiCurrency\Compatibility\WooCommercePreOrders instance.
	 *
	 * @var WCPay\MultiCurrency\Compatibility\WooCommercePreOrders
	 */
	private $woocommerce_pre_orders;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency    = $this->createMock( MultiCurrency::class );
		$this->mock_utils             = $this->createMock( Utils::class );
		$this->woocommerce_pre_orders = new WooCommercePreOrders( $this->mock_multi_currency, $this->mock_utils );
	}

	public function test_wc_pre_orders_fee() {
		$expected = [ 'amount' => 42.0 ];
		$args     = [ 'amount' => 21.0 ];
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( $args['amount'] )
			->willReturn( 42.0 );
		$this->assertSame( $expected, $this->woocommerce_pre_orders->wc_pre_orders_fee( $args ) );
	}
}

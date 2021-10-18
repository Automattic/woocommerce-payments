<?php
/**
 * Class WCPay_Multi_Currency_WooCommerceUPS_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility\WooCommerceUPS;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * WCPay\MultiCurrency\Compatibility\WooCommerceUPS unit tests.
 */
class WCPay_Multi_Currency_WooCommerceUPS_Tests extends WP_UnitTestCase {

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
	 * WCPay\MultiCurrency\Compatibility\WooCommerceUPS instance.
	 *
	 * @var WCPay\MultiCurrency\Compatibility\WooCommerceUPS
	 */
	private $woocommerce_ups;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( MultiCurrency::class );
		$this->mock_utils          = $this->createMock( Utils::class );
		$this->woocommerce_ups     = new WooCommerceUPS( $this->mock_multi_currency, $this->mock_utils );
	}

	// If true is passed, it should automatically return true.
	public function test_should_return_store_currency_returns_true_if_true_passed() {
		$this->mock_utils->expects( $this->exactly( 0 ) )->method( 'is_call_in_backtrace' );
		$this->assertTrue( $this->woocommerce_ups->should_return_store_currency( true ) );
	}

	// If the calls are found, it should return true.
	public function test_should_return_store_currency_returns_true_if_calls_found() {
		$calls = [
			'WC_Shipping_UPS->per_item_shipping',
			'WC_Shipping_UPS->box_shipping',
			'WC_Shipping_UPS->calculate_shipping',
		];
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( $calls )
			->willReturn( true );
		$this->assertTrue( $this->woocommerce_ups->should_return_store_currency( false ) );
	}

	// If the calls are found, it should return true.
	public function test_should_return_store_currency_returns_false_if_no_calls_found() {
		$calls = [
			'WC_Shipping_UPS->per_item_shipping',
			'WC_Shipping_UPS->box_shipping',
			'WC_Shipping_UPS->calculate_shipping',
		];
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( $calls )
			->willReturn( false );
		$this->assertFalse( $this->woocommerce_ups->should_return_store_currency( false ) );
	}
}

<?php
/**
 * Class WCPay_Multi_Currency_WooCommerceDeposits_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility\WooCommerceDeposits;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * WCPay\MultiCurrency\Compatibility\WooCommerceDeposits unit tests.
 */
class WCPay_Multi_Currency_WooCommerceDeposits_Tests extends WP_UnitTestCase {

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
	 * WCPay\MultiCurrency\Compatibility\WooCommerceDeposits instance.
	 *
	 * @var WCPay\MultiCurrency\Compatibility\WooCommerceDeposits
	 */
	private $woocommerce_deposits;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency  = $this->createMock( MultiCurrency::class );
		$this->mock_utils           = $this->createMock( Utils::class );
		$this->woocommerce_deposits = new WooCommerceDeposits( $this->mock_multi_currency, $this->mock_utils );
	}

	public function test_should_modify_cart_item_deposit_amounts_when_item_is_a_deposit() {
		$this->mock_multi_currency
			->method( 'get_price' )
			->willReturnCallback(
				function( $input ) {
					return $input * 2;
				}
			);

		$cart_contents_mock     = [
			[
				'is_deposit'     => true,
				'deposit_amount' => 250,
			],
			[
				'is_deposit'     => false,
				'deposit_amount' => 100,
			],
			[
				'item_price' => 150,
			],
		];

		$cart_contents_modified = $this->woocommerce_deposits->modify_cart_item_deposit_amounts( $cart_contents_mock );

		$this->assertEquals( 500, $cart_contents_modified[0]['deposit_amount'] );
		$this->assertEquals( 100, $cart_contents_modified[1]['deposit_amount'] );
		$this->assertEquals( 150, $cart_contents_modified[2]['item_price'] );
		$this->assertFalse( isset( $cart_contents_modified[2]['deposit_amount'] ) );
	}
}

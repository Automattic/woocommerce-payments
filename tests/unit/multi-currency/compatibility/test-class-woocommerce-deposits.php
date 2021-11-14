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

		$cart_contents_mock = [
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

	public function test_should_modify_cart_item_deposit_amount_metas_when_item_is_not_a_plan_type_deposit() {
		$this->mock_multi_currency
		->method( 'get_price' )
		->willReturnCallback(
			function( $input ) {
				return $input * 2;
			}
		);

		$this->mock_utils->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Deposits_Cart_Manager->deposits_form_output' ] )
			->willReturn( true );

		$amount  = 10.00;
		$product = WC_Helper_Product::create_simple_product();
		$product->add_meta_data( '_wc_deposits_enabled', true );
		$product->add_meta_data( '_wc_deposit_type', 'plan' );
		$product->save();

		$this->assertEquals( $amount, $this->woocommerce_deposits->modify_cart_item_deposit_amount_meta( $amount, $product ) );

		$product->update_meta_data( '_wc_deposit_type', 'percent' );
		$product->save();

		$this->assertEquals( $amount * 2, $this->woocommerce_deposits->modify_cart_item_deposit_amount_meta( $amount, $product ) );
	}

	public function test_maybe_convert_product_prices_for_deposits() {
		$this->mock_utils->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Cart->calculate_totals' ] )
			->willReturn( true );

		$product = WC_Helper_Product::create_simple_product();
		$product->add_meta_data( '_wc_deposits_enabled', true );
		$product->add_meta_data( '_wc_deposit_type', 'plan' );
		$product->save();

		$this->assertEquals( false, $this->woocommerce_deposits->maybe_convert_product_prices_for_deposits( true, $product ) );

		$product->update_meta_data( '_wc_deposit_type', 'percent' );
		$product->save();

		$this->assertEquals( true, $this->woocommerce_deposits->maybe_convert_product_prices_for_deposits( true, $product ) );
	}

	public function test_should_modify_order_currency() {
		$original_order = WC_Helper_Order::create_order();
		$original_order->set_currency( 'JPY' );
		$original_order->save();

		$next_order = WC_Helper_Order::create_order();
		$next_order->set_currency( 'USD' );
		$next_order_items = $next_order->get_items();
		foreach ( $next_order_items as $item ) {
			$item->add_meta_data( '_original_order_id', $original_order->get_id() );
			$item->save();
		}

		$this->woocommerce_deposits->modify_order_currency( $next_order->get_id() );

		// Refresh the order.
		$next_order = wc_get_order( $next_order->get_id() );

		$this->assertEquals( 'JPY', $next_order->get_currency() );
	}

}

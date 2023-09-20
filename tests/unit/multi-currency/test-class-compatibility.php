<?php
/**
 * Class WCPay_Multi_Currency_Compatibility_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility;
use WCPay\MultiCurrency\Currency;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * WCPay\MultiCurrency\Compatibility unit tests.
 */
class WCPay_Multi_Currency_Compatibility_Tests extends WCPAY_UnitTestCase {
	/**
	 * WCPay\MultiCurrency\Compatibility instance.
	 *
	 * @var WCPay\MultiCurrency\Compatibility
	 */
	private $compatibility;

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
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_multi_currency = $this->createMock( MultiCurrency::class );
		$this->mock_utils          = $this->createMock( Utils::class );
		$this->compatibility       = new Compatibility( $this->mock_multi_currency, $this->mock_utils );
	}

	public function test_init_compatibility_classes_does_not_add_classes_if_one_enabled_currencies() {
		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn( [ 'USD' ] );

		$this->compatibility->init_compatibility_classes();
		$this->assertEquals( 0, count( $this->compatibility->get_compatibility_classes() ) );
	}

	public function test_init_compatibility_classes_adds_classes_if_enabled_currencies() {
		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn( [ 'USD', 'EUR' ] );

		$this->compatibility->init_compatibility_classes();
		$this->assertGreaterThan( 0, count( $this->compatibility->get_compatibility_classes() ) );
	}

	public function test_should_convert_coupon_amount_return_true_on_null_coupon() {
		$this->assertTrue( $this->compatibility->should_convert_coupon_amount( null ) );
	}

	public function test_should_convert_product_price_return_true_on_null_product() {
		$this->assertTrue( $this->compatibility->should_convert_product_price( null ) );
	}

	public function test_filter_woocommerce_order_query_with_call_not_in_backtrace() {
		$order = wc_create_order();
		$order->set_total( 1000 );
		$order->set_currency( 'GBP' );

		$this->mock_utils->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with(
				[
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::sum_sales_for_date',
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::possibly_add_note',
				]
			)->willReturn( false );

		$this->assertEquals( [ $order ], $this->compatibility->convert_order_prices( [ $order ], [] ) );
	}

	public function test_filter_woocommerce_order_query_with_order_in_default_currency() {
		$order = wc_create_order();
		$order->set_total( 1000 );
		$order->set_currency( 'USD' );

		// Even if these keys are set, the order currency matches the store currency.
		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', 1.0 );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'GBP' );

		$this->mock_multi_currency->expects( $this->once() )
			->method( 'get_default_currency' )
			->willReturn( new Currency( 'USD', 1.0 ) );

		$this->mock_utils->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with(
				[
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::sum_sales_for_date',
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::possibly_add_note',
				]
			)->willReturn( true );

		$this->assertEquals( [ $order ], $this->compatibility->convert_order_prices( [ $order ], [] ) );
	}

	public function test_filter_woocommerce_order_query_with_order_with_no_exchange_rate_meta() {
		$order = wc_create_order();
		$order->set_total( 1000 );
		$order->set_currency( 'GBP' );

		// If the exchange rate meta isn't set, nothing should be done.
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'USD' );

		$this->mock_multi_currency->expects( $this->once() )
			->method( 'get_default_currency' )
			->willReturn( new Currency( 'USD', 1.0 ) );

		$this->mock_utils->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with(
				[
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::sum_sales_for_date',
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::possibly_add_note',
				]
			)->willReturn( true );

		$this->assertEquals( [ $order ], $this->compatibility->convert_order_prices( [ $order ], [] ) );
	}

	public function test_filter_woocommerce_order_query_with_no_meta() {
		$order = wc_create_order();
		$order->set_total( 1000 );
		$order->set_currency( 'USD' );

		$this->mock_multi_currency->expects( $this->once() )
			->method( 'get_default_currency' )
			->willReturn( new Currency( 'USD', 1.0 ) );

		$this->mock_utils->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with(
				[
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::sum_sales_for_date',
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::possibly_add_note',
				]
			)->willReturn( true );

		$this->assertEquals( [ $order ], $this->compatibility->convert_order_prices( [ $order ], [] ) );
	}

	public function test_filter_woocommerce_order_query() {
		$order = wc_create_order();
		$order->set_total( 1000 );
		$order->set_currency( 'GBP' );
		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', 0.5 );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'USD' );
		$order->save();

		$this->mock_multi_currency->expects( $this->once() )
			->method( 'get_default_currency' )
			->willReturn( new Currency( 'USD', 1.0 ) );

		$this->mock_utils->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with(
				[
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::sum_sales_for_date',
					'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::possibly_add_note',
				]
			)->willReturn( true );

		/**
		 * @var WC_Order $result
		 */
		$result = $this->compatibility->convert_order_prices( [ $order ], [] )[0];
		$this->assertEquals( 2000, $result->get_total() );

		// Assert the actual order wasn't changed (only modified for returning from the filter).
		$order = wc_get_order( $result->get_id() );
		$this->assertEquals( 1000, $order->get_total() );
	}

	public function test_filter_woocommerce_order_query_with_object_not_array() {
		$order = wc_create_order();
		$order->set_total( 1000 );
		$order->set_currency( 'GBP' );

		// Turn the order array into an object to confirm the object is returned as is.
		$expected = (object) [ $order ];

		$this->assertEquals( $expected, $this->compatibility->convert_order_prices( $expected, [] ) );
	}

	// The should_disable_currency_switching should return false by default.
	public function test_should_disable_currency_switching_return_false_by_default() {
		// Act/Assert: Confirm false is returned by default.
		$this->assertFalse( $this->compatibility->should_disable_currency_switching() );
	}

	// If on the pay_for_order page, then should_disable_currency_switching should return true.
	public function test_should_disable_currency_switching_return_true_on_pay_for_order() {
		// Arrange: Blatantly hack mock request params for the test.
		$_GET['pay_for_order'] = true;

		// Act/Assert: Confirm true is returned if on the pay_for_order page.
		$this->assertTrue( $this->compatibility->should_disable_currency_switching() );
	}

	// If filtered to true, then should_disable_currency_switching should return true.
	public function test_should_disable_currency_switching_return_true_on_filtered_true() {
		// Arrange: Add filter to return true.
		add_filter( MultiCurrency::FILTER_PREFIX . 'should_disable_currency_switching', '__return_true' );

		// Act/Assert: Confirm true is returned if filtered to true.
		$this->assertTrue( $this->compatibility->should_disable_currency_switching() );

		// Arrange: Remove our filter.
		remove_all_filters( MultiCurrency::FILTER_PREFIX . 'should_disable_currency_switching' );
	}
}

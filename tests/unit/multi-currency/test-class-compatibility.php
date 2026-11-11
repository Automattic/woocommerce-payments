<?php
/**
 * Class WCPay_Multi_Currency_Compatibility_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility;
use WCPay\MultiCurrency\Currency;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyLocalizationInterface;
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
	 * MultiCurrencyLocalizationInterface.
	 *
	 * @var MultiCurrencyLocalizationInterface
	 */
	private $mock_localization_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_multi_currency       = $this->createMock( MultiCurrency::class );
		$this->mock_utils                = $this->createMock( Utils::class );
		$this->mock_localization_service = $this->createMock( MultiCurrencyLocalizationInterface::class );
		$this->mock_localization_service
			->method( 'get_currency_format' )
			->with( 'USD' )
			->willReturn( [ 'num_decimals' => 2 ] );

		$this->compatibility = new Compatibility( $this->mock_multi_currency, $this->mock_utils );
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

	public function test_should_convert_coupon_min_max_amount_return_true_on_null_coupon() {
		$this->assertTrue( $this->compatibility->should_convert_coupon_min_max_amount( null ) );
	}

	public function test_should_convert_coupon_min_max_amount_passes_coupon_to_filter() {
		$coupon          = new WC_Coupon();
		$filtered_coupon = null;
		$skip_conversion = function ( $_unused_should_convert, $coupon_arg ) use ( &$filtered_coupon ) {
			$filtered_coupon = $coupon_arg;
			return false;
		};

		// Without a callback, the amounts are converted.
		$this->assertTrue( $this->compatibility->should_convert_coupon_min_max_amount( $coupon ) );

		// A callback receives the coupon and can skip the conversion.
		add_filter( 'wcpay_multi_currency_should_convert_coupon_min_max_amount', $skip_conversion, 10, 2 );
		$should_convert = $this->compatibility->should_convert_coupon_min_max_amount( $coupon );
		remove_filter( 'wcpay_multi_currency_should_convert_coupon_min_max_amount', $skip_conversion, 10 );

		$this->assertFalse( $should_convert );
		$this->assertSame( $coupon, $filtered_coupon );
	}

	public function test_should_convert_product_price_return_true_on_null_product() {
		$this->assertTrue( $this->compatibility->should_convert_product_price( null ) );
	}

	public function test_should_convert_shipping_amount_return_true_on_null_method() {
		$this->assertTrue( $this->compatibility->should_convert_shipping_amount( null ) );
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
		$GLOBALS['wp']->query_vars['order-pay'] = 1;

		// Act/Assert: Confirm true is returned if on the Order Pay endpoint.
		$this->assertTrue( $this->compatibility->should_disable_currency_switching() );

		unset( $GLOBALS['wp']->query_vars['order-pay'] );
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

	/**
	 * Converts order totals to the store currency only while WooCommerce runs the new sales record operation.
	 */
	public function test_convert_order_prices_converts_only_in_new_sales_record_operation() {
		$this->mock_multi_currency->method( 'get_default_currency' )->willReturn( new Currency( $this->mock_localization_service, 'USD' ) );

		$outside_order = $this->create_eur_order_mock();
		$outside_order->expects( $this->never() )->method( 'set_total' );
		$this->compatibility->convert_order_prices( [ $outside_order ] );

		$inside_order = $this->create_eur_order_mock();
		$inside_order->expects( $this->once() )->method( 'set_total' )->with( '80.00' );
		\Automattic\WooCommerce\Currency\ActiveCurrency::run(
			'',
			fn() => $this->compatibility->convert_order_prices( [ $inside_order ] ),
			'admin.new_sales_record'
		);
	}

	/**
	 * An EUR order of 100 that was recorded at a rate of 1.25 against the USD store currency.
	 *
	 * @return \WC_Order|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function create_eur_order_mock() {
		$order = $this->createMock( \WC_Order::class );
		$order->method( 'get_currency' )->willReturn( 'EUR' );
		$order->method( 'get_total' )->willReturn( 100 );
		$meta = [
			'_wcpay_multi_currency_order_exchange_rate'    => 1.25,
			'_wcpay_multi_currency_order_default_currency' => 'USD',
		];
		$order->method( 'get_meta' )->willReturnCallback(
			function ( $key ) use ( $meta ) {
				return $meta[ $key ] ?? '';
			}
		);

		return $order;
	}
}

<?php
/**
 * Class WCPay_Multi_Currency_WooCommerceFedEx_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility\WooCommerceFedEx;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- The carrier stub below.
// phpcs:disable Squiz.Commenting.ClassComment
if ( ! class_exists( 'WC_Shipping_Fedex' ) ) {
	// The FedEx shipping extension is not loaded in unit tests; this stub is the subject FedEx passes to shipping.rates.
	class WC_Shipping_Fedex {}
}
// phpcs:enable Squiz.Commenting.ClassComment

/**
 * WCPay\MultiCurrency\Compatibility\WooCommerceFedEx unit tests.
 */
class WCPay_Multi_Currency_WooCommerceFedEx_Tests extends WCPAY_UnitTestCase {

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
	 * WCPay\MultiCurrency\Compatibility\WooCommerceFedEx instance.
	 *
	 * @var WCPay\MultiCurrency\Compatibility\WooCommerceFedEx
	 */
	private $woocommerce_fedex;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_multi_currency = $this->createMock( MultiCurrency::class );
		$this->mock_utils          = $this->createMock( Utils::class );
		$this->woocommerce_fedex   = new WooCommerceFedEx( $this->mock_multi_currency, $this->mock_utils );
	}

	// If true is passed, it should automatically return true.
	public function test_should_return_store_currency_returns_true_if_true_passed() {
		$this->mock_utils->expects( $this->exactly( 0 ) )->method( 'is_in_shipping_method_rates' );
		$this->assertTrue( $this->woocommerce_fedex->should_return_store_currency( true ) );
	}

	// If the calls are found, it should return true.
	public function test_should_return_store_currency_returns_true_if_calls_found() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_in_shipping_method_rates' )
			->with( 'WC_Shipping_Fedex' )
			->willReturn( true );

		$this->assertTrue( $this->woocommerce_fedex->should_return_store_currency( false ) );
	}

	// If the calls are not found, it should return false.
	public function test_should_return_store_currency_returns_false_if_no_calls_found() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_in_shipping_method_rates' )
			->with( 'WC_Shipping_Fedex' )
			->willReturn( false );

		$this->assertFalse( $this->woocommerce_fedex->should_return_store_currency( false ) );
	}

	// If true is passed to should_convert_product_price and no calls are found, it should return true.
	public function test_should_convert_product_price_returns_true_if_true_passed_and_no_calls_found() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_in_shipping_method_rates' )
			->with( 'WC_Shipping_Fedex' )
			->willReturn( false );

		$this->assertTrue( $this->woocommerce_fedex->should_convert_product_price( true ) );
	}

	// If calls are found, should_convert_product_price should return false even if true was passed.
	public function test_should_convert_product_price_returns_false_if_calls_found() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_in_shipping_method_rates' )
			->with( 'WC_Shipping_Fedex' )
			->willReturn( true );

		$this->assertFalse( $this->woocommerce_fedex->should_convert_product_price( true ) );
	}

	/**
	 * FedEx's store-currency rule applies only to FedEx rate calculation, not to other rates or operations.
	 *
	 * @dataProvider fedex_operation_provider
	 */
	public function test_fedex_rules_apply_only_to_fedex_rate_calculation( $operation, $subject, $is_fedex_rates ) {
		$woocommerce_fedex = new WooCommerceFedEx( $this->mock_multi_currency, new Utils() );

		[ $store_currency, $convert ] = $this->in_operation(
			fn() => [
				$woocommerce_fedex->should_return_store_currency( false ),
				$woocommerce_fedex->should_convert_product_price( true ),
			],
			$operation,
			$subject
		);

		$this->assertSame( $is_fedex_rates, $store_currency );
		$this->assertSame( ! $is_fedex_rates, $convert );
	}

	public function fedex_operation_provider() {
		return [
			'fedex rates'                => [ 'shipping.rates', new WC_Shipping_Fedex(), true ],
			'rates of another method'    => [ 'shipping.rates', new \stdClass(), false ],
			'fedex in another operation' => [ 'cart.calculate_totals', new WC_Shipping_Fedex(), false ],
			'no operation'               => [ null, new WC_Shipping_Fedex(), false ],
		];
	}

	/**
	 * Call a function while an ActiveCurrency scope with the given operation and subject is held.
	 *
	 * @param callable    $callback  Function to call.
	 * @param string|null $operation Operation name.
	 * @param object|null $subject   Operation subject.
	 * @return mixed What the callback returns.
	 */
	private function in_operation( callable $callback, ?string $operation = null, ?object $subject = null ) {
		$scope = \Automattic\WooCommerce\Currency\ActiveCurrency::scope( '', $operation, $subject );

		try {
			return $callback();
		} finally {
			$scope->release();
		}
	}
}

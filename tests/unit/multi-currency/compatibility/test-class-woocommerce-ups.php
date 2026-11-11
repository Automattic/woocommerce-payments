<?php
/**
 * Class WCPay_Multi_Currency_WooCommerceUPS_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility\WooCommerceUPS;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- The carrier stub below.
// phpcs:disable Squiz.Commenting.ClassComment
if ( ! class_exists( 'WC_Shipping_UPS' ) ) {
	// The UPS shipping extension is not loaded in unit tests; this stub is the subject UPS passes to shipping.rates.
	class WC_Shipping_UPS {}
}
// phpcs:enable Squiz.Commenting.ClassComment

/**
 * WCPay\MultiCurrency\Compatibility\WooCommerceUPS unit tests.
 */
class WCPay_Multi_Currency_WooCommerceUPS_Tests extends WCPAY_UnitTestCase {

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
	public function set_up() {
		parent::set_up();

		$this->mock_multi_currency = $this->createMock( MultiCurrency::class );
		$this->mock_utils          = $this->createMock( Utils::class );
		$this->woocommerce_ups     = new WooCommerceUPS( $this->mock_multi_currency, $this->mock_utils );
	}

	// If true is passed, it should automatically return true.
	public function test_should_return_store_currency_returns_true_if_true_passed() {
		$this->mock_utils->expects( $this->exactly( 0 ) )->method( 'is_in_shipping_method_rates' );
		$this->assertTrue( $this->woocommerce_ups->should_return_store_currency( true ) );
	}

	// While UPS calculates its rates, it should return true.
	public function test_should_return_store_currency_returns_true_if_calls_found() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_in_shipping_method_rates' )
			->with( 'WC_Shipping_UPS' )
			->willReturn( true );
		$this->assertTrue( $this->woocommerce_ups->should_return_store_currency( false ) );
	}

	// Outside UPS rate calculation, it should return false.
	public function test_should_return_store_currency_returns_false_if_no_calls_found() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_in_shipping_method_rates' )
			->with( 'WC_Shipping_UPS' )
			->willReturn( false );
		$this->assertFalse( $this->woocommerce_ups->should_return_store_currency( false ) );
	}

	/**
	 * UPS's store-currency rule applies only to UPS rate calculation, not to other rates or operations.
	 *
	 * @dataProvider ups_operation_provider
	 */
	public function test_ups_rules_apply_only_to_ups_rate_calculation( $operation, $subject, $is_ups_rates ) {
		$woocommerce_ups = new WooCommerceUPS( $this->createMock( MultiCurrency::class ), new Utils() );

		$this->assertSame(
			$is_ups_rates,
			\Automattic\WooCommerce\Currency\ActiveCurrency::run( '', fn() => $woocommerce_ups->should_return_store_currency( false ), $operation, $subject )
		);
	}

	public function ups_operation_provider() {
		return [
			'ups rates'                => [ 'shipping.rates', new WC_Shipping_UPS(), true ],
			'rates of another method'  => [ 'shipping.rates', new \stdClass(), false ],
			'ups in another operation' => [ 'cart.calculate_totals', new WC_Shipping_UPS(), false ],
			'no operation'             => [ null, new WC_Shipping_UPS(), false ],
		];
	}
}

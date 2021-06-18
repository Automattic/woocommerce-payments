<?php
/**
 * Class WCPay_Multi_Currency_Compatibility_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Compatibility unit tests.
 */
class WCPay_Multi_Currency_Compatibility_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\Multi_Currency\Compatibility instance.
	 *
	 * @var WCPay\Multi_Currency\Compatibility
	 */
	private $compatibility;

	/**
	 * Mock WCPay\Multi_Currency\Utils.
	 *
	 * @var WCPay\Multi_Currency\Utils|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_utils;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_utils    = $this->createMock( WCPay\Multi_Currency\Utils::class );
		$this->compatibility = new WCPay\Multi_Currency\Compatibility( $this->mock_utils );

		$this->mock_product = $this->createMock( \WC_Product::class );
		$this->mock_product
			->method( 'get_id' )
			->willReturn( 42 );
	}

	public function test_is_product_subscription_renewal_false_on_no_match() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->assertFalse( $this->compatibility->is_product_subscription_renewal( $this->mock_product ) );
	}

	public function test_is_product_subscription_renewal_true_on_match() {
		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertTrue( $this->compatibility->is_product_subscription_renewal( $this->mock_product ) );
	}

	public function test_override_selected_currency_return_false() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->assertFalse( $this->compatibility->override_selected_currency() );
	}

	public function test_override_selected_currency_return_true() {
		$this->mock_wcs_cart_contains_renewal( true );
		update_post_meta( 42, '_order_currency', 'CAD', true );
		$this->assertSame( 'CAD', $this->compatibility->override_selected_currency() );
	}

	public function test_should_hide_widgets_return_false() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->assertFalse( $this->compatibility->should_hide_widgets() );
	}

	public function test_should_hide_widgets_return_true() {
		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertTrue( $this->compatibility->should_hide_widgets() );
	}

	public function test_should_convert_product_price_return_false_when_renewal_in_cart() {
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->with(
				[
					'WC_Cart_Totals->calculate_item_totals',
					'WC_Cart->get_product_subtotal',
					'wc_get_price_excluding_tax',
					'wc_get_price_including_tax',
				]
			)
			->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertFalse( $this->compatibility->should_convert_product_price( $this->mock_product ) );
	}

	public function test_should_convert_product_price_return_true_when_backtrace_does_not_match() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertTrue( $this->compatibility->should_convert_product_price( $this->mock_product ) );
	}

	public function test_should_convert_product_price_return_true_with_no_renewal_in_cart() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( false );
		$this->assertTrue( $this->compatibility->should_convert_product_price( $this->mock_product ) );
	}

	public function test_should_convert_product_price_return_true_when_product_null() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertTrue( $this->compatibility->should_convert_product_price( null ) );
	}

	private function mock_wcs_cart_contains_renewal( $value ) {
		WC_Subscriptions::wcs_cart_contains_renewal(
			function () use ( $value ) {
				if ( $value ) {
					return [
						'product_id'           => 42,
						'subscription_renewal' => [
							'renewal_order_id' => 42,
						],
					];
				}

				return false;
			}
		);
	}
}

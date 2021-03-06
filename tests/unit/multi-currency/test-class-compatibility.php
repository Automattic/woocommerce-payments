<?php
/**
 * Class WCPay_Multi_Currency_Compatibility_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\MultiCurrency\Compatibility unit tests.
 */
class WCPay_Multi_Currency_Compatibility_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\MultiCurrency\Compatibility instance.
	 *
	 * @var WCPay\MultiCurrency\Compatibility
	 */
	private $compatibility;

	/**
	 * Mock WCPay\MultiCurrency\Utils.
	 *
	 * @var WCPay\MultiCurrency\Utils|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_utils;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_utils    = $this->createMock( WCPay\MultiCurrency\Utils::class );
		$this->compatibility = new WCPay\MultiCurrency\Compatibility( $this->mock_utils );

		$this->mock_product = $this->createMock( \WC_Product::class );
		$this->mock_product
			->method( 'get_id' )
			->willReturn( 42 );

		$this->mock_coupon = $this->createMock( \WC_Coupon::class );
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
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->assertFalse( $this->compatibility->should_hide_widgets() );
	}

	public function test_should_hide_widgets_return_true_when_renewal_in_cart() {
		$this->mock_wcs_cart_contains_renewal( true );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->assertTrue( $this->compatibility->should_hide_widgets() );
	}

	public function test_should_hide_widgets_return_true_when_resubscribe_in_cart() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( true );
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
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->assertFalse( $this->compatibility->should_convert_product_price( $this->mock_product ) );
	}

	public function test_should_convert_product_price_return_false_when_resubscribe_in_cart() {
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
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( true );
		$this->assertFalse( $this->compatibility->should_convert_product_price( $this->mock_product ) );
	}

	public function test_should_convert_product_price_return_true_when_backtrace_does_not_match() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( true );
		$this->mock_wcs_cart_contains_resubscribe( true );
		$this->assertTrue( $this->compatibility->should_convert_product_price( $this->mock_product ) );
	}

	public function test_should_convert_product_price_return_true_with_no_subscription_actions_in_cart() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->assertTrue( $this->compatibility->should_convert_product_price( $this->mock_product ) );
	}

	public function test_should_convert_product_price_return_true_when_product_null() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( true );
		$this->mock_wcs_cart_contains_resubscribe( true );
		$this->assertTrue( $this->compatibility->should_convert_product_price( null ) );
	}

	public function test_should_convert_coupon_amount_return_false_when_renewal_in_cart() {
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WCS_Cart_Early_Renewal->setup_cart' ] ],
				[ [ 'WC_Discounts->apply_coupon' ] ]
			)
			->willReturn( false, true );

		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( 'recurring_fee' );

		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertFalse( $this->compatibility->should_convert_coupon_amount( $this->mock_coupon ) );
	}

	public function test_should_convert_coupon_amount_return_true_with_no_renewal_in_cart() {
		$this->mock_utils
		->expects( $this->exactly( 0 ) )
		->method( 'is_call_in_backtrace' );

		$this->mock_wcs_cart_contains_renewal( false );
		$this->assertTrue( $this->compatibility->should_convert_coupon_amount( $this->mock_coupon ) );
	}

	public function test_should_convert_coupon_amount_return_true_with_early_renewal_in_backtrace() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WCS_Cart_Early_Renewal->setup_cart' ] )
			->willReturn( true );

		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( 'recurring_fee' );

		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertTrue( $this->compatibility->should_convert_coupon_amount( $this->mock_coupon ) );
	}

	public function test_should_convert_coupon_amount_return_true_when_apply_coupon_not_in_backtrace() {
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WCS_Cart_Early_Renewal->setup_cart' ] ],
				[ [ 'WC_Discounts->apply_coupon' ] ]
			)
			->willReturn( false, false );

		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( 'recurring_fee' );

		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertTrue( $this->compatibility->should_convert_coupon_amount( $this->mock_coupon ) );
	}

	public function test_should_convert_coupon_amount_return_true_when_coupon_type_does_not_match() {
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WCS_Cart_Early_Renewal->setup_cart' ] ],
				[ [ 'WC_Discounts->apply_coupon' ] ]
			)
			->willReturn( false, true );

		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( 'failing_fee' );

		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertTrue( $this->compatibility->should_convert_coupon_amount( $this->mock_coupon ) );
	}

	public function test_should_convert_coupon_amount_return_false_when_percentage_coupon_used() {
		$this->mock_utils
			->expects( $this->exactly( 0 ) )
			->method( 'is_call_in_backtrace' );

		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( 'recurring_percent' );

		$this->mock_wcs_cart_contains_renewal( false );
		$this->assertFalse( $this->compatibility->should_convert_coupon_amount( $this->mock_coupon ) );
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

	private function mock_wcs_cart_contains_resubscribe( $value ) {
		WC_Subscriptions::wcs_cart_contains_resubscribe(
			function () use ( $value ) {
				if ( $value ) {
					return [
						'product_id'               => 42,
						'subscription_resubscribe' => [
							'subscription_id' => 42,
						],
					];
				}

				return false;
			}
		);
	}
}

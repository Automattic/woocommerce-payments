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
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );
		$this->mock_utils          = $this->createMock( WCPay\MultiCurrency\Utils::class );
		$this->compatibility       = new WCPay\MultiCurrency\Compatibility( $this->mock_multi_currency, $this->mock_utils );

		$this->mock_meta_data = $this->createMock( \WC_Meta_Data::class );

		$this->mock_product = $this->createMock( \WC_Product::class );
		$this->mock_product
			->method( 'get_id' )
			->willReturn( 42 );

		$this->mock_coupon = $this->createMock( \WC_Coupon::class );
	}

	/**
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_registers_woocommerce_filters_properly( $filter, $function_name ) {
		$priority = has_filter( $filter, [ $this->compatibility, $function_name ] );
		$this->assertGreaterThan(
			10,
			$priority,
			"Filter '$filter' was not registered with '$function_name' with a priority higher than the default."
		);
		$this->assertLessThan(
			100,
			$priority,
			"Filter '$filter' was registered with '$function_name' with a priority higher than than 100, which can cause double conversions."
		);
	}

	public function woocommerce_filter_provider() {
		return [
			[ 'option_woocommerce_subscriptions_multiple_purchase', 'maybe_disable_mixed_cart' ],
			[ 'woocommerce_subscriptions_product_price', 'get_subscription_product_price' ],
			[ 'woocommerce_product_get__subscription_sign_up_fee', 'get_subscription_product_signup_fee' ],
			[ 'woocommerce_product_variation_get__subscription_sign_up_fee', 'get_subscription_product_signup_fee' ],
		];
	}

	public function test_get_subscription_product_price_does_not_convert_price() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( true );
		$this->assertSame( 10.0, $this->compatibility->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	public function test_get_subscription_product_price_converts_price_test_1() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->compatibility->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	public function test_get_subscription_product_price_converts_price_test_2() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->compatibility->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	public function test_get_subscription_product_price_converts_price_test_3() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( true );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->compatibility->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Does not convert price due to first backtrace check returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_test_1() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Subscriptions_Cart::set_subscription_prices_for_calculation' ] )
			->willReturn( true );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->assertSame( 10.0, $this->compatibility->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Does not convert price due to second check with backtrace and cart item key check returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_test_2() {
		$this->mock_utils
			->expects( $this->exactly( 4 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WC_Subscriptions_Cart::set_subscription_prices_for_calculation' ] ],
				[ [ 'WC_Subscriptions_Product::get_sign_up_fee' ] ],
				[ [ 'WC_Cart->calculate_totals' ] ],
				[ [ 'WCS_Switch_Totals_Calculator->apportion_sign_up_fees' ] ]
			)
			->willReturn( false, true, true, false );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->compatibility->switch_cart_item = 'abc123';
		$this->assertSame( 10.0, $this->compatibility->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Does not convert due to third check for changes in the meta data returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_test_3() {
		$this->mock_utils
			->expects( $this->any() )
			->method( 'is_call_in_backtrace' )
			->willReturn( false );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->compatibility->switch_cart_item = 'abc123';

		$this->mock_meta_data
			->method( 'get_data' )
			->willReturn( [ 'key' => '_subscription_sign_up_fee' ] );
		$this->mock_meta_data
			->method( 'get_changes' )
			->willReturn( [ 1, 2 ] );

		$this->mock_product
			->method( 'get_meta_data' )
			->willReturn( [ $this->mock_meta_data ] );

		$this->assertSame( 10.0, $this->compatibility->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Converts due to backtraces are not found and the check for changes in meta data returns false.
	public function test_get_subscription_product_signup_fee_converts_price_test_1() {
		$this->mock_utils
			->expects( $this->any() )
			->method( 'is_call_in_backtrace' )
			->willReturn( false );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->compatibility->switch_cart_item = 'abc123';

		$this->mock_meta_data
			->method( 'get_data' )
			->willReturn( [ 'key' => '_subscription_sign_up_fee' ] );
		$this->mock_meta_data
			->method( 'get_changes' )
			->willReturn( [] );

		$this->mock_product
			->method( 'get_meta_data' )
			->willReturn( [ $this->mock_meta_data ] );

		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->compatibility->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Converts due to the same as above, and the cart item keys do not match.
	public function test_get_subscription_product_signup_fee_converts_price_test_2() {
		$this->mock_utils
			->expects( $this->any() )
			->method( 'is_call_in_backtrace' )
			->willReturn( false );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->compatibility->switch_cart_item = 'def456';

		$this->mock_product
			->method( 'get_meta_data' )
			->willReturn( [] );

		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->compatibility->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	public function test_maybe_disable_mixed_cart_return_no() {
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->assertSame( 'no', $this->compatibility->maybe_disable_mixed_cart( 'yes' ) );
	}

	public function test_maybe_disable_mixed_cart_return_yes() {
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertSame( 'yes', $this->compatibility->maybe_disable_mixed_cart( 'yes' ) );
	}

	public function test_override_selected_currency_return_false() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertFalse( $this->compatibility->override_selected_currency() );
	}

	// Returns code due to cart contains a subscription renewal.
	public function test_override_selected_currency_return_currency_code_test_1() {
		$this->mock_wcs_cart_contains_renewal( true );
		update_post_meta( 42, '_order_currency', 'CAD', true );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertSame( 'CAD', $this->compatibility->override_selected_currency() );
	}

	// Returns code due to GET states there is a subscription switch.
	public function test_override_selected_currency_return_currency_code_test_2() {
		$this->mock_wcs_cart_contains_renewal( false );
		$_GET['switch-subscription'] = 42;
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );
		update_post_meta( 42, '_order_currency', 'CAD', true );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertSame( 'CAD', $this->compatibility->override_selected_currency() );
	}

	// Returns code due to cart contains a subscription renewal.
	public function test_override_selected_currency_return_currency_code_test_3() {
		$this->mock_wcs_cart_contains_renewal( false );
		update_post_meta( 42, '_order_currency', 'CAD', true );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->assertSame( 'CAD', $this->compatibility->override_selected_currency() );
	}

	public function test_should_hide_widgets_return_false() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertFalse( $this->compatibility->should_hide_widgets() );
	}

	public function test_should_hide_widgets_return_true_test_1() {
		$this->mock_wcs_cart_contains_renewal( true );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertTrue( $this->compatibility->should_hide_widgets() );
	}

	public function test_should_hide_widgets_return_true_test_2() {
		$this->mock_wcs_cart_contains_renewal( false );
		$_GET['switch-subscription'] = 42;
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertTrue( $this->compatibility->should_hide_widgets() );
	}

	public function test_should_hide_widgets_return_true_test_3() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_get_order_type_cart_items( true );
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

	private function mock_wcs_get_order_type_cart_items( $value ) {
		WC_Subscriptions::wcs_get_order_type_cart_items(
			function () use ( $value ) {
				if ( $value ) {
					return [
						[
							'product_id'          => 42,
							'key'                 => 'abc123',
							'subscription_switch' => [
								'subscription_id' => 42,
							],
						],
					];
				}

				return [];
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

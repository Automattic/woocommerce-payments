<?php
/**
 * Class WCPay_Multi_Currency_WooCommerceSubscriptions_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Compatibility\WooCommerceSubscriptions;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * WCPay\MultiCurrency\Compatibility\WooCommerceSubscriptions unit tests.
 */
class WCPay_Multi_Currency_WooCommerceSubscriptions_Tests extends WCPAY_UnitTestCase {

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
	 * WCPay\MultiCurrency\Compatibility\WooCommerceSubscriptions instance.
	 *
	 * @var WCPay\MultiCurrency\Compatibility\WooCommerceSubscriptions
	 */
	private $woocommerce_subscriptions;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_multi_currency       = $this->createMock( MultiCurrency::class );
		$this->mock_utils                = $this->createMock( Utils::class );
		$this->woocommerce_subscriptions = new WooCommerceSubscriptions( $this->mock_multi_currency, $this->mock_utils );

		$this->mock_meta_data = $this->createMock( \WC_Meta_Data::class );

		$this->mock_product = $this->createMock( \WC_Product::class );
		$this->mock_product
			->method( 'get_id' )
			->willReturn( 42 );

		$this->mock_coupon = $this->createMock( \WC_Coupon::class );
	}

	public function tear_down() {
		// Reset cart checks so future tests can pass.
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_wcs_get_order_type_cart_items( false );

		parent::tear_down();
	}


	/**
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_registers_woocommerce_filters_properly( $filter, $function_name ) {
		$priority = has_filter( $filter, [ $this->woocommerce_subscriptions, $function_name ] );
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
			[ 'woocommerce_subscriptions_product_price', 'get_subscription_product_price' ],
			[ 'woocommerce_product_get__subscription_sign_up_fee', 'get_subscription_product_signup_fee' ],
			[ 'woocommerce_product_variation_get__subscription_sign_up_fee', 'get_subscription_product_signup_fee' ],
			[ 'option_woocommerce_subscriptions_multiple_purchase', 'maybe_disable_mixed_cart' ],
			[ 'wcpay_multi_currency_override_selected_currency', 'override_selected_currency' ],
			[ 'wcpay_multi_currency_should_convert_product_price', 'should_convert_product_price' ],
			[ 'wcpay_multi_currency_should_convert_coupon_amount', 'should_convert_coupon_amount' ],
			[ 'wcpay_multi_currency_should_hide_widgets', 'should_hide_widgets' ],
		];
	}

	// Test should not convert the product price due to all checks return true.
	public function test_get_subscription_product_price_does_not_convert_price() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->mock_wcs_cart_contains_resubscribe( 42 );
		$this->assertSame( 10.0, $this->woocommerce_subscriptions->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Test should convert product price due to all checks return false.
	public function test_get_subscription_product_price_converts_price_with_all_checks_false() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->woocommerce_subscriptions->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Test should convert product price due to the backtrace check returns true but the cart contains renewal/resubscribe return checks false.
	public function test_get_subscription_product_price_converts_price_if_only_backtrace_found() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] )
			->willReturn( false );

		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->woocommerce_subscriptions->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Test should convert product price due to the backtrace check returns false after the cart contains renewal check returns true.
	public function test_get_subscription_product_price_converts_price_if_only_renewal_in_cart() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->woocommerce_subscriptions->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Test should convert product price due to the backtrace check returns false after the cart contains resubscribe check returns true.
	public function test_get_subscription_product_price_converts_price_if_only_resubscribe_in_cart() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( 42 );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->woocommerce_subscriptions->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Does not convert price due to first backtrace check returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_on_first_backtrace_match() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Subscriptions_Cart::set_subscription_prices_for_calculation' ] )
			->willReturn( true );
		$this->mock_wcs_get_order_type_cart_items( 42 );
		$this->assertSame( 10.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Does not convert price due to second check with backtrace and cart item key check returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_during_proration_calculation() {
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
		$this->mock_wcs_get_order_type_cart_items( 42 );
		$this->woocommerce_subscriptions->switch_cart_item = 'abc123';
		$this->assertSame( 10.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Does not convert due to third check for changes in the meta data returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_when_meta_already_updated() {
		$this->mock_utils
			->expects( $this->any() )
			->method( 'is_call_in_backtrace' )
			->willReturn( false );
		$this->mock_wcs_get_order_type_cart_items( 42 );
		$this->woocommerce_subscriptions->switch_cart_item = 'abc123';

		$this->mock_meta_data
			->method( 'get_data' )
			->willReturn( [ 'key' => '_subscription_sign_up_fee' ] );
		$this->mock_meta_data
			->method( 'get_changes' )
			->willReturn( [ 1, 2 ] );

		$this->mock_product
			->method( 'get_meta_data' )
			->willReturn( [ $this->mock_meta_data ] );

		$this->assertSame( 10.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Converts due to backtraces are not found and the check for changes in meta data returns false.
	public function test_get_subscription_product_signup_fee_converts_price_when_meta_not_updated() {
		$this->mock_utils
			->expects( $this->any() )
			->method( 'is_call_in_backtrace' )
			->willReturn( false );
		$this->mock_wcs_get_order_type_cart_items( 42 );
		$this->woocommerce_subscriptions->switch_cart_item = 'abc123';

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
		$this->assertSame( 25.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Converts due to the same as above, and the cart item keys do not match.
	public function test_get_subscription_product_signup_fee_converts_price_when_cart_item_keys_do_not_match() {
		$this->mock_utils
			->expects( $this->any() )
			->method( 'is_call_in_backtrace' )
			->willReturn( false );
		$this->mock_wcs_get_order_type_cart_items( 42 );
		$this->woocommerce_subscriptions->switch_cart_item = 'def456';

		$this->mock_product
			->method( 'get_meta_data' )
			->willReturn( [] );

		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	public function test_maybe_disable_mixed_cart_return_no() {
		$this->mock_wcs_get_order_type_cart_items( 42 );
		$this->assertSame( 'no', $this->woocommerce_subscriptions->maybe_disable_mixed_cart( 'yes' ) );
	}

	public function test_maybe_disable_mixed_cart_return_yes() {
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertSame( 'yes', $this->woocommerce_subscriptions->maybe_disable_mixed_cart( 'yes' ) );
	}

	// Returns code due to code was passed.
	public function test_override_selected_currency_return_currency_code_when_code_passed() {
		// Conditions added to return EUR, but CAD should be returned at the beginning of the method.
		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->mock_wcs_cart_contains_resubscribe( false );
		update_post_meta( 42, '_order_currency', 'EUR', true );
		$this->mock_wcs_get_order_type_cart_items( false );

		$this->assertSame( 'CAD', $this->woocommerce_subscriptions->override_selected_currency( 'CAD' ) );
	}

	// Returns false due to all checks return false.
	public function test_override_selected_currency_return_false() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertFalse( $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	// Returns code due to cart contains a subscription renewal.
	public function test_override_selected_currency_return_currency_code_when_renewal_in_cart() {
		// Set up an order with a non-default currency.
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'JPY' );
		$order->save();

		// Mock that order as the renewal in the cart.
		$this->mock_wcs_cart_contains_renewal( 42, $order->get_id() );

		$this->assertSame( 'JPY', $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	// Test correct currency when shopper clicks upgrade/downgrade button in My Account – "switch".
	public function test_override_selected_currency_return_currency_code_for_switch_request() {
		// Reset/clear any previous mocked state.
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_wcs_get_order_type_cart_items( false );

		// Set up an order with a non-default currency.
		// This might need to be a subscription object, not an order.
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'JPY' );
		$order->save();

		// Blatantly hack mock request params for the test.
		$_GET['switch-subscription'] = $order->get_id();
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );

		$this->assertSame( 'JPY', $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	// Returns code due to cart contains a subscription switch.
	public function test_override_selected_currency_return_currency_code_when_switch_in_cart() {
		// Reset/clear any previous mocked state.
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );

		// Mock order with custom currency for switch cart item.
		// Note we're using a WC_Order as a stand-in for a true WC_Subscription.
		$mock_subscription = WC_Helper_Order::create_order();
		$mock_subscription->set_currency( 'JPY' );
		$mock_subscription->save();

		// Mock wcs_get_subscription to return our mock subscription.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		// Mock cart to simulate a switch cart item referencing our subscription.
		$this->mock_wcs_get_order_type_cart_items( $mock_subscription->get_id() );

		$this->assertSame( 'JPY', $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	// Returns code due to cart contains a subscription resubscribe.
	public function test_override_selected_currency_return_currency_code_when_resubscribe_in_cart() {
		// Reset/clear any previous mocked state.
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_get_order_type_cart_items( false );

		// Mock order with custom currency for switch cart item.
		// Note we're using a WC_Order as a stand-in for a true WC_Subscription.
		$mock_subscription = WC_Helper_Order::create_order();
		$mock_subscription->set_currency( 'JPY' );
		$mock_subscription->save();

		// Mock wcs_get_subscription to return our mock subscription.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		// Mock cart to simulate a resubscribe cart item referencing our subscription.
		$this->mock_wcs_cart_contains_resubscribe( $mock_subscription->get_id() );

		$this->assertSame( 'JPY', $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	public function test_should_convert_product_price_return_false_when_false_passed() {
		// Conditions added to return true, but it should return false if passed.
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->mock_wcs_cart_contains_resubscribe( 42 );

		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_product_price( false, $this->mock_product ) );
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
		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
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
		$this->mock_wcs_cart_contains_resubscribe( 42 );
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	public function test_should_convert_product_price_return_true_when_backtrace_does_not_match() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->mock_wcs_cart_contains_resubscribe( 42 );
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	public function test_should_convert_product_price_return_true_with_no_subscription_actions_in_cart() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] )
			->willReturn( false );

		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	// Test for when WCPay Subs is getting the product's price for the sub creation.
	public function test_should_convert_product_price_return_false_when_get_recurring_item_data_for_subscription() {
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] ],
				[ [ 'WC_Product->get_price' ] ]
			)
			->willReturn( true, true );

		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_coupon ) );
	}

	public function test_should_convert_coupon_amount_return_false_if_false_passed() {
		// Conditions added to return true, but should return false if false passed.
		$this->mock_utils
		->expects( $this->exactly( 0 ) )
		->method( 'is_call_in_backtrace' );
		$this->mock_wcs_cart_contains_renewal( false );

		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_amount( false, $this->mock_coupon ) );
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

		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	public function test_should_convert_coupon_amount_return_true_with_no_renewal_in_cart() {
		$this->mock_utils
		->expects( $this->exactly( 0 ) )
		->method( 'is_call_in_backtrace' );

		$this->mock_wcs_cart_contains_renewal( false );
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
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

		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
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

		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
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

		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	public function test_should_convert_coupon_amount_return_false_when_percentage_coupon_used() {
		$this->mock_utils
			->expects( $this->exactly( 0 ) )
			->method( 'is_call_in_backtrace' );

		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( 'recurring_percent' );

		$this->mock_wcs_cart_contains_renewal( false );
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	public function test_should_hide_widgets_return_true_if_true_passed() {
		// Conditions set to return false, but should return true if true passed.
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_wcs_get_order_type_cart_items( false );

		$this->assertTrue( $this->woocommerce_subscriptions->should_hide_widgets( true ) );
	}

	// Should return false since all checks return false.
	public function test_should_hide_widgets_return_false() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertFalse( $this->woocommerce_subscriptions->should_hide_widgets( false ) );
	}

	public function test_should_hide_widgets_return_true_when_renewal_in_cart() {
		$this->mock_wcs_cart_contains_renewal( 42, 43 );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->assertTrue( $this->woocommerce_subscriptions->should_hide_widgets( false ) );
	}

	public function test_should_hide_widgets_return_true_when_resubscribe_in_cart() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( 42 );
		$this->assertTrue( $this->woocommerce_subscriptions->should_hide_widgets( false ) );
	}

	// Should return true if switch found in GET, like on product page.
	public function test_should_hide_widgets_return_true_when_starting_subscrition_switch() {
		$this->mock_wcs_cart_contains_renewal( false );
		$_GET['switch-subscription'] = 42;
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertTrue( $this->woocommerce_subscriptions->should_hide_widgets( false ) );
	}

	// Should return true if switch found in cart.
	public function test_should_hide_widgets_return_true_when_switch_found_in_cart() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->assertTrue( $this->woocommerce_subscriptions->should_hide_widgets( false ) );
	}

	// Simulate (mock) a renewal in the cart.
	// Pass 0 / no args to unmock.
	private function mock_wcs_cart_contains_renewal( $product_id = 0, $renewal_order_id = 0 ) {
		WC_Subscriptions::wcs_cart_contains_renewal(
			function () use ( $product_id, $renewal_order_id ) {
				if ( $product_id && $renewal_order_id ) {
					return [
						'product_id'           => $product_id,
						'subscription_renewal' => [
							'renewal_order_id' => $renewal_order_id,
						],
					];
				}

				return false;
			}
		);
	}

	private function mock_wcs_get_order_type_cart_items( $switch_id = 0 ) {
		WC_Subscriptions::wcs_get_order_type_cart_items(
			function () use ( $switch_id ) {
				if ( $switch_id ) {
					return [
						[
							'product_id'          => 42,
							'key'                 => 'abc123',
							'subscription_switch' => [
								'subscription_id' => $switch_id,
							],
						],
					];
				}

				return [];
			}
		);
	}

	private function mock_wcs_cart_contains_resubscribe( $subscription_id = 0 ) {
		WC_Subscriptions::wcs_cart_contains_resubscribe(
			function () use ( $subscription_id ) {
				if ( $subscription_id ) {
					return [
						'product_id'               => 42,
						'subscription_resubscribe' => [
							'subscription_id' => $subscription_id,
						],
					];
				}

				return false;
			}
		);
	}
}

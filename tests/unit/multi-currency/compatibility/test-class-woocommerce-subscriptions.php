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
	 * Mock meta data.
	 *
	 * @var \WC_Meta_Data|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_meta_data;

	/**
	 * Mock product.
	 *
	 * @var \WC_Product|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_product;

	/**
	 * Mock coupon.
	 *
	 * @var \WC_Coupon|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_coupon;

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
		// Clear our cart on every iteration, also clears the session cart.
		WC()->cart->empty_cart();

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
			[ 'wcpay_multi_currency_should_disable_currency_switching', 'should_disable_currency_switching' ],
		];
	}

	// Will not convert the sub price due null is passed as the price.
	public function test_get_subscription_product_price_does_not_convert_price_when_no_price_passed() {
		// Act: Attempt to convert the subscription price.
		$result = $this->woocommerce_subscriptions->get_subscription_product_price( null, $this->mock_product );

		// Assert: Confirm the result value is null.
		$this->assertNull( $result );
	}

	/**
	 * Will not convert the sub price due to the is_call_in_backtrace calls in should_convert_product_price return true, which
	 * causes should_convert_product_price to return false to not convert the price.
	 */
	public function test_get_subscription_product_price_does_not_convert_price() {
		// Arrange: Set our mock return values.
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturn( true );

		// Act/Assert: Confirm the result value is not converted.
		$this->assertSame( 10.0, $this->woocommerce_subscriptions->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Will not convert the sub signup fee due null is passed as the fee.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_when_no_fee_passed() {
		// Act/Assert: Confirm the result value is null.
		$this->assertNull( $this->woocommerce_subscriptions->get_subscription_product_signup_fee( null, $this->mock_product ) );
	}

	// If there is no switch in the cart, then the signup fee should be converted.
	public function test_get_subscription_product_signup_fee_converts_fee_when_no_switch_in_cart() {
		// Arrange: Set the expectation and return for the call to get_price.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( 10.0, 'product' )
			->willReturn( 25.0 );

		// Act/Assert: Confirm the result value is converted.
		$this->assertSame( 25.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Does not convert price due to first backtrace check returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_on_first_backtrace_match() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

		// Arrange: Set the expectation and return for the is_call_in_backtrace call.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Subscriptions_Cart::set_subscription_prices_for_calculation' ] )
			->willReturn( true );

		// Arrange: Set the expectation for the call to get_price.
		$this->mock_multi_currency
			->expects( $this->never() )
			->method( 'get_price' );

		// Act/Assert: Confirm the result value is not converted.
		$this->assertSame( 10.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Does not convert price due to second check with backtrace and cart item key check returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_during_proration_calculation() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

		// Arrange: Set our switch_cart_item property.
		$this->woocommerce_subscriptions->switch_cart_item = 'abc123';

		// Arrange: Set the expectations and returns for the is_call_in_backtrace calls.
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

		// Arrange: Set the expectation for the call to get_price.
		$this->mock_multi_currency
			->expects( $this->never() )
			->method( 'get_price' );

		// Act/Assert: Confirm the result value is not converted.
		$this->assertSame( 10.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Does not convert due to third check for changes in the meta data returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_when_meta_already_updated() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

		// Arrange: Set our switch_cart_item property.
		$this->woocommerce_subscriptions->switch_cart_item = 'abc123';

		// Arrange: Set the expectation for the call to is_call_in_backtrace and always return false.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->willReturn( false );

		// Arrange: Set expectations and returns for get_meta_data, get_data, and get_changes.
		$this->mock_product
			->expects( $this->once() )
			->method( 'get_meta_data' )
			->willReturn( [ $this->mock_meta_data ] );
		$this->mock_meta_data
			->expects( $this->once() )
			->method( 'get_data' )
			->willReturn( [ 'key' => '_subscription_sign_up_fee' ] );
		$this->mock_meta_data
			->expects( $this->once() )
			->method( 'get_changes' )
			->willReturn( [ 1, 2 ] );

		// Arrange: Set the expectation for the call to get_price.
		$this->mock_multi_currency
			->expects( $this->never() )
			->method( 'get_price' );

		// Act/Assert: Confirm the result value is not converted.
		$this->assertSame( 10.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Converts price due to the switch item does not match the item being checked.
	public function test_get_subscription_product_signup_fee_converts_price_when_cart_item_keys_do_not_match() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

		// Arrange: Set our switch_cart_item property so that it does not match what's in the cart.
		$this->woocommerce_subscriptions->switch_cart_item = 'def456';

		// Arrange: Set the expectation for the call to is_call_in_backtrace and always return false.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->willReturn( false );

		// Arrange: Set expectations for get_meta_data, get_data, and get_changes.
		$this->mock_product
			->expects( $this->never() )
			->method( 'get_meta_data' );
		$this->mock_meta_data
			->expects( $this->never() )
			->method( 'get_data' );
		$this->mock_meta_data
			->expects( $this->never() )
			->method( 'get_changes' );

		// Arrange: Set the expectation and return value for the call to get_price.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( 10.0, 'product' )
			->willReturn( 25.0 );

		// Act/Assert: Confirm the result value is converted.
		$this->assertSame( 25.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Converts due to backtraces are not found and the check for changes in meta data returns false.
	public function test_get_subscription_product_signup_fee_converts_price_when_meta_not_updated() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

		// Arrange: Set our switch_cart_item property.
		$this->woocommerce_subscriptions->switch_cart_item = 'abc123';

		// Arrange: Set the expectation for the call to is_call_in_backtrace and always return false.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->willReturn( false );

		// Arrange: Set expectations and returns for get_meta_data and get_data.
		$this->mock_product
			->expects( $this->once() )
			->method( 'get_meta_data' )
			->willReturn( [ $this->mock_meta_data ] );
		$this->mock_meta_data
			->expects( $this->once() )
			->method( 'get_data' )
			->willReturn( [ 'key' => '_subscription_sign_up_fee' ] );

		// Arrange: Set expectation and return for get_changes so that it is empty.
		$this->mock_meta_data
			->expects( $this->once() )
			->method( 'get_changes' )
			->willReturn( [] );

		// Arrange: Set the expectation and return value for the call to get_price.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( 10.0, 'product' )
			->willReturn( 25.0 );

		// Act/Assert: Confirm the result value is converted.
		$this->assertSame( 25.0, $this->woocommerce_subscriptions->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	public function test_maybe_disable_mixed_cart_return_no() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

		// Act/Assert: 'no' should be returned due to the item in the cart is a switch.
		$this->assertSame( 'no', $this->woocommerce_subscriptions->maybe_disable_mixed_cart( 'yes' ) );
	}

	public function test_maybe_disable_mixed_cart_return_yes() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );

		// Act/Assert: 'yes' should be returned due to the item in the cart is a renewal and not a switch.
		$this->assertSame( 'yes', $this->woocommerce_subscriptions->maybe_disable_mixed_cart( 'yes' ) );
	}

	// Returns currency code due to code was passed.
	public function test_override_selected_currency_return_currency_code_when_code_passed() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items();

		// Arrange: Set the currency for the sub and update the cart items in the session.
		$mock_subscription->set_currency( 'JPY' );
		WC()->session->set( 'cart', $cart_items );

		// Assert: CAD should be returned since it was passed, even though there is an item in the cart.
		$this->assertSame( 'CAD', $this->woocommerce_subscriptions->override_selected_currency( 'CAD' ) );
	}

	// Returns false due we are not adding products to the cart.
	public function test_override_selected_currency_return_false_if_no_cart_items() {
		// Assert: False should be received since there's nothing in the cart.
		$this->assertFalse( $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	/**
	 * Will return the specified codes due to the cart check looks first in the cart object, and then in the session. With the first
	 * check, the cart object is empty, so the session is checked. With the second check, the cart object now has a subscription, so
	 * its code is returned.
	 *
	 * This confirms that the get_subscription_type_from_cart method is working correctly.
	 *
	 * @dataProvider provider_sub_types_renewal_resubscribe_switch
	 */
	public function test_override_selected_currency_return_currency_code_when_sub_type_in_cart( $sub_type ) {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( $sub_type );

		// Arrange: Set the currency for the sub and update the cart items in the session.
		$mock_subscription->set_currency( 'JPY' );
		WC()->session->set( 'cart', $cart_items );

		// Act/Assert: Confirm that the currency is what we set.
		$this->assertSame( 'JPY', $this->woocommerce_subscriptions->override_selected_currency( false ) );

		// Arrange: Change the sub's currency and update the cart contents in the WC object.
		$mock_subscription->set_currency( 'EUR' );
		WC()->cart->set_cart_contents( $cart_items );

		// Act/Assert: Confirm the currency is what we set.
		$this->assertSame( 'EUR', $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	// Test correct currency when shopper clicks upgrade/downgrade button in My Account – "switch".
	public function test_override_selected_currency_return_currency_code_for_switch_request() {
		// Arrange: Create a mock subscription and assign its currency.
		$mock_subscription = $this->create_mock_subscription();
		$mock_subscription->set_currency( 'JPY' );

		// Arrange: Blatantly hack mock request params for the test.
		$_GET['switch-subscription'] = $mock_subscription->get_id();
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );

		// Act/Assert: Confirm that the currency returned is that of the subscription.
		$this->assertSame( 'JPY', $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	// Return false if the current user doesn't match the user of the switching subscription.
	public function test_override_selected_currency_return_false_for_switch_request_when_no_user_match() {
		// Arrange: Create a mock subscription and assign its currency and user.
		$mock_subscription = $this->create_mock_subscription();
		$mock_subscription->set_currency( 'JPY' );
		$mock_subscription->set_customer_id( 42 );

		// Arrange: Blatantly hack mock request params for the test.
		$_GET['switch-subscription'] = $mock_subscription->get_id();
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );

		// Act/Assert: Confirm that false is returned.
		$this->assertFalse( $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	// The default passed into should_convert_product_price is true, this passes false to confirm false is returned.
	public function test_should_convert_product_price_return_false_when_false_passed() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items();

		// Arrange: Set the currency for the sub and update the cart items in the session.
		$mock_subscription->set_currency( 'JPY' );
		WC()->session->set( 'cart', $cart_items );

		// Arrange: Set expecation that is_call_in_backtrace should not be called.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_call_in_backtrace' );

		// Act/Assert: Confirm that false is returned if passed.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_product_price( false, $this->mock_product ) );
	}

	/**
	 * Confirm that false is returned if specific types of subs are in the cart and there are specific calls in the backtrace.
	 *
	 * @dataProvider provider_sub_types_renewal_resubscribe
	 */
	public function test_should_convert_product_price_return_false_when_sub_type_in_cart_and_backtrace_match( $sub_type ) {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( $sub_type );

		// Arrange: Set expectation and return for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->once() )
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

		// Act/Assert: Confirm the result value is false.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	/**
	 * Confirm that true is returned even if there are specific sub types in the cart, but the backtraces are not correct.
	 *
	 * @dataProvider provider_sub_types_renewal_resubscribe
	 */
	public function test_should_convert_product_price_return_true_when_sub_type_in_cart_and_backtraces_do_not_match( $sub_type ) {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( $sub_type );

		// Arrange: Set expectations and returns for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[
					[
						'WC_Cart_Totals->calculate_item_totals',
						'WC_Cart->get_product_subtotal',
						'wc_get_price_excluding_tax',
						'wc_get_price_including_tax',
					],
				],
				[ [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] ]
			)
			->willReturn( false );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	/**
	 * Confirm that true is returned even if there are specific sub types in the cart, but the backtraces are not correct.
	 * This is the same as the above, with the second backtrace check being true, so the third one is now checked.
	 *
	 * @dataProvider provider_sub_types_renewal_resubscribe
	 */
	public function test_should_convert_product_price_return_true_when_sub_type_in_cart_and_backtraces_do_not_match_exactly( $sub_type ) {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( $sub_type );

		// Arrange: Set expectations and returns for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->exactly( 3 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[
					[
						'WC_Cart_Totals->calculate_item_totals',
						'WC_Cart->get_product_subtotal',
						'wc_get_price_excluding_tax',
						'wc_get_price_including_tax',
					],
				],
				[ [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] ],
				[ [ 'WC_Product->get_price' ] ]
			)
			->willReturn( false, true, false );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	// Confirm if there are no sub_types in cart and the first backtrace does not match, true is returned.
	public function test_should_convert_product_price_return_true_with_no_sub_types_in_cart_and_no_backtrace_match() {
		// Arrange: Set expectation and return for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] )
			->willReturn( false );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	// Confirm if there are no sub_types in cart and the second backtrace does not match, true is returned.
	public function test_should_convert_product_price_return_true_with_no_sub_types_in_cart_and_no_second_backtrace_match() {
		// Arrange: Set expectations and returns for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] ],
				[ [ 'WC_Product->get_price' ] ]
			)
			->willReturn( true, false );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	// Test for when WCPay Subs is getting the product's price for the sub creation.
	public function test_should_convert_product_price_return_false_when_get_recurring_item_data_for_subscription() {
		// Arrange: Set expectations and returns for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ] ],
				[ [ 'WC_Product->get_price' ] ]
			)
			->willReturn( true, true );

		// Act/Assert: Confirm the result value is false.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_coupon ) );
	}

	/**
	 * This method should return false if false is passed.
	 * The test does not add a renewal to the cart, which would cause it to return true, but it shouldn't make it there.
	 * The is_call_in_backtrace call should also never be called.
	 */
	public function test_should_convert_coupon_amount_return_false_if_false_passed() {
		// Arrange: Set expectation for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_call_in_backtrace' );

		// Act/Assert: Confirm the result value is false.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_amount( false, $this->mock_coupon ) );
	}

	// Confirm that if there's a subscription percentage coupon type, we don't want to convert its amount.
	public function test_should_convert_coupon_amount_return_false_when_subscription_percent_coupon_type() {
		// Arrange: Set expectation and return for our mock coupon.
		$this->mock_coupon
			->expects( $this->once() )
			->method( 'get_discount_type' )
			->willReturn( 'recurring_percent' );

		// Arrange: Set expectations and returns for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_call_in_backtrace' );

		// Act/Assert: Confirm the result value is false.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	// Confirm true is returned if there is not a renewal in the cart.
	public function test_should_convert_coupon_amount_return_true_with_no_renewal_in_cart() {
		// Arrange: Set expectation and return for our mock coupon.
		$this->mock_coupon
			->expects( $this->once() )
			->method( 'get_discount_type' )
			->willReturn( 'recurring_fee' );

		// Arrange: Set expectations and returns for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_call_in_backtrace' );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	// Confirm true is returned if there's a renewal in the cart, but it's not an early renewal.
	public function test_should_convert_coupon_amount_return_true_with_early_renewal_in_backtrace() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );

		// Arrange: Set expectation and return for our mock coupon.
		$this->mock_coupon
			->expects( $this->once() )
			->method( 'get_discount_type' )
			->willReturn( 'recurring_fee' );

		// Arrange: Set expectation and return for is_call_in_backtrace. This exits our last test and allows the true return.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WCS_Cart_Early_Renewal->setup_cart' ] )
			->willReturn( true );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	// Confirm true is returned if there's a renewal in the cart, if it is an early renewal, but the apply_coupon call is not found in the backtrace.
	public function test_should_convert_coupon_amount_return_true_when_apply_coupon_not_in_backtrace() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );

		// Arrange: Set expectation and return for our mock coupon.
		$this->mock_coupon
			->expects( $this->once() )
			->method( 'get_discount_type' )
			->willReturn( 'recurring_fee' );

		// Arrange: Set expectation and return for is_call_in_backtrace. This exits our last test and allows the true return.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WCS_Cart_Early_Renewal->setup_cart' ] ],
				[ [ 'WC_Discounts->apply_coupon' ] ]
			)
			->willReturn( false, false );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	// Confirm true is returned if there's a renewal in the cart, if it is an early renewal, the coupon is being applied, but it's the wrong coupon type.
	public function test_should_convert_coupon_amount_return_true_when_coupon_type_does_not_match() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );

		// Arrange: Set expectation and return for our mock coupon. The second call exits our last test and allows the true return.
		$this->mock_coupon
			->expects( $this->exactly( 2 ) )
			->method( 'get_discount_type' )
			->willReturn( 'failing_fee' );

		// Arrange: Set expectation and return for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WCS_Cart_Early_Renewal->setup_cart' ] ],
				[ [ 'WC_Discounts->apply_coupon' ] ]
			)
			->willReturn( false, true );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	// Confirm false is returned if there's a renewal in the cart, the backtraces match, and the coupon is the proper type.
	public function test_should_convert_coupon_amount_return_false_when_renewal_in_cart() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );

		// Arrange: Set expectations and returns for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->withConsecutive(
				[ [ 'WCS_Cart_Early_Renewal->setup_cart' ] ],
				[ [ 'WC_Discounts->apply_coupon' ] ]
			)
			->willReturn( false, true );

		// Arrange: Set expectation and return for our mock coupon.
		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( 'recurring_fee' );

		// Act/Assert: Confirm the result value is false.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	// If true is passed to the method, true should be returned immediately.
	public function test_should_disable_currency_switching_return_true_if_true_passed() {
		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_disable_currency_switching( true ) );
	}

	// If false is passed to the method and none of the checks are true, false is returned.
	public function test_should_disable_currency_switching_return_false() {
		// Act/Assert: Confirm the result value is false.
		$this->assertFalse( $this->woocommerce_subscriptions->should_disable_currency_switching( false ) );
	}

	/**
	 * Confirm true is returned when sub types are in cart.
	 *
	 * @dataProvider provider_sub_types_renewal_resubscribe_switch
	 */
	public function test_should_disable_currency_switching_return_true_when_sub_type_in_cart( $sub_type ) {
		// Arrange: Create a subscription and cart_items to be used.
		[ $mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( $sub_type );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_disable_currency_switching( false ) );
	}

	// Should return true if switch found in GET, for when a customer is doing a subscription switch.
	public function test_should_disable_currency_switching_return_true_when_starting_subscription_switch() {
		// Arrange: Create a mock subscription to use.
		$mock_subscription = $this->create_mock_subscription();

		// Arrange: Blatantly hack mock request params for the test.
		$_GET['switch-subscription'] = $mock_subscription->get_id();
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );

		// Act/Assert: Confirm that true is returned.
		$this->assertTrue( $this->woocommerce_subscriptions->should_disable_currency_switching( false ) );
	}

	// Should return false since users will not match.
	public function test_should_disable_currency_switching_return_false_when_starting_subscrition_switch_and_no_user_match() {
		// Arrange: Create a mock subscription and assign its user.
		$mock_subscription = $this->create_mock_subscription();
		$mock_subscription->set_customer_id( 42 );

		// Arrange: Blatantly hack mock request params for the test.
		$_GET['switch-subscription'] = $mock_subscription->get_id();
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );

		// Act/Assert: Confirm that false is returned.
		$this->assertFalse( $this->woocommerce_subscriptions->should_disable_currency_switching( false ) );
	}

	public function provider_sub_types_renewal_resubscribe_switch() {
		return [
			'renewal'     => [ 'renewal' ],
			'resubscribe' => [ 'resubscribe' ],
			'switch'      => [ 'switch' ],
		];
	}

	public function provider_sub_types_renewal_resubscribe() {
		return [
			'renewal'     => [ 'renewal' ],
			'resubscribe' => [ 'resubscribe' ],
		];
	}

	/**
	 * Creates a mock subscription for us to be able to use in our tests.
	 * It also sets up the wcs_get_subscription mock method to return that sub.
	 */
	private function create_mock_subscription() {
		// Create the mock subscription.
		$mock_subscription = new WC_Subscription( 404 );

		// Mock wcs_get_subscription to return our mock subscription.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		return $mock_subscription;
	}

	/**
	 * Creates a mock subsciption, and then adds it to the session's cart array.
	 */
	private function get_mock_subscription_and_session_cart_items( $sub_type = 'renewal' ) {
		// Create the mock subscription.
		$mock_subscription = $this->create_mock_subscription();

		// Create our cart items.
		$cart_items = [
			[
				'subscription_' . $sub_type => [
					'subscription_id' => $mock_subscription->get_id(),
				],
				'product_id'                => $this->mock_product->get_id(),
				'key'                       => 'abc123',
			],
		];

		// Set the cart items in the session.
		WC()->session->set( 'cart', $cart_items );

		return [
			$mock_subscription,
			$cart_items,
		];
	}
}

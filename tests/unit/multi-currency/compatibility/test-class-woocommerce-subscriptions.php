<?php
/**
 * Class WCPay_Multi_Currency_WooCommerceSubscriptions_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Currency_Code;
use WCPay\MultiCurrency\Compatibility;
use WCPay\MultiCurrency\Compatibility\WooCommerceSubscriptions;
use WCPay\MultiCurrency\Currency;
use WCPay\MultiCurrency\FrontendCurrencies;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyAccountInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyApiClientInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyCacheInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyLocalizationInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencySettingsInterface;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * WCPay\MultiCurrency\Compatibility\WooCommerceSubscriptions unit tests.
 */
class WCPay_Multi_Currency_WooCommerceSubscriptions_Tests extends WCPAY_UnitTestCase {

	/**
	 * Frame lists that `should_convert_product_price()` looks for in the backtrace, keyed by the data provider's names.
	 */
	const PRODUCT_PRICE_BACKTRACES = [
		'renewal_cart_setup'  => [ 'WCS_Cart_Renewal->setup_cart' ],
		'cart_totals'         => [
			'WC_Cart_Totals->calculate_item_totals',
			'WC_Cart->get_product_subtotal',
			'wc_get_price_excluding_tax',
			'wc_get_price_including_tax',
		],
		'recurring_item_data' => [ 'WC_Payments_Subscription_Service->get_recurring_item_data_for_subscription' ],
		'product_get_price'   => [ 'WC_Product->get_price' ],
	];

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

		$mock_api_client   = $this->createMock( MultiCurrencyApiClientInterface::class );
		$mock_account      = $this->createMock( MultiCurrencyAccountInterface::class );
		$mock_localization = $this->createMock( MultiCurrencyLocalizationInterface::class );
		$mock_cache        = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_settings     = $this->createMock( MultiCurrencySettingsInterface::class );

		$this->mock_multi_currency = $this->getMockBuilder( MultiCurrency::class )
			->setConstructorArgs( [ $mock_settings, $mock_api_client, $mock_account, $mock_localization, $mock_cache ] )
			->getMock();

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
		WC()->session->set( 'wcs_renewal_coupons', null );
		WC()->session->set( 'wcpay_multi_currency_unconverted_session_coupons', null );
		unset( $_GET['switch-subscription'], $_GET['_wcsnonce'] );

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
			[ 'wcpay_multi_currency_should_convert_coupon_min_max_amount', 'should_convert_coupon_min_max_amount' ],
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
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

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
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

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
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

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
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

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
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

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
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'switch' );

		// Act/Assert: 'no' should be returned due to the item in the cart is a switch.
		$this->assertSame( 'no', $this->woocommerce_subscriptions->maybe_disable_mixed_cart( 'yes' ) );
	}

	public function test_maybe_disable_mixed_cart_return_yes() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );

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

	/**
	 * @dataProvider provider_sub_types_renewal_resubscribe_switch
	 */
	public function test_nested_read_while_loading_a_cart_subscription_does_not_keep_its_decimals( $sub_type ) {
		[ $mock_subscription ]                               = $this->get_mock_subscription_and_session_cart_items( $sub_type );
		[ $woocommerce_subscriptions, $frontend_currencies ] = $this->get_subscriptions_compatibility_reading_decimals_on_load( $mock_subscription );

		$this->assertSame( 'JPY', $woocommerce_subscriptions->override_selected_currency( false ) );
		$this->assertSame( 0, $frontend_currencies->get_price_decimals( 2 ) );
	}

	public function test_nested_read_while_loading_a_switch_subscription_does_not_keep_its_decimals() {
		$mock_subscription                                   = $this->create_mock_subscription();
		[ $woocommerce_subscriptions, $frontend_currencies ] = $this->get_subscriptions_compatibility_reading_decimals_on_load( $mock_subscription );

		$_GET['switch-subscription'] = $mock_subscription->get_id();
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );

		$this->assertSame( 'JPY', $woocommerce_subscriptions->override_selected_currency( false ) );
		$this->assertSame( 0, $frontend_currencies->get_price_decimals( 2 ) );
	}

	// A load that throws and is caught further up, as the Store API does for each request in a batch, must not leave the override off.
	public function test_override_selected_currency_still_applies_after_a_subscription_load_throws() {
		// Arrange: A JPY renewal subscription whose first load throws.
		[ $mock_subscription ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );
		$mock_subscription->set_currency( 'JPY' );

		[ $woocommerce_subscriptions, $frontend_currencies ] = $this->get_subscriptions_compatibility_with_frontend_currencies();

		$loads = 0;
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $_unused_id ) use ( $frontend_currencies, $mock_subscription, &$loads ) {
				$frontend_currencies->get_price_decimals( 2 );
				if ( 1 === ++$loads ) {
					throw new \TypeError( 'The subscription could not be loaded.' );
				}
				return $mock_subscription;
			}
		);

		// Act: Read the currency once while the load throws, then again.
		try {
			$woocommerce_subscriptions->override_selected_currency( false );
			$this->fail( 'The first load should throw.' );
		} catch ( \TypeError $e ) {
			unset( $e );
		}
		$currency = $woocommerce_subscriptions->override_selected_currency( false );

		// Assert: The second read uses the subscription's currency and decimals.
		$this->assertSame( 'JPY', $currency );
		$this->assertSame( 0, $frontend_currencies->get_price_decimals( 2 ) );
	}

	// A cart paying for a signup order again uses the order's currency. Loading the order reads prices while the override is skipped,
	// so what FrontendCurrencies cached during the load is discarded, and the order is only loaded once.
	public function test_override_selected_currency_return_initial_payment_order_currency_and_discard_cache_read_during_load() {
		// Arrange: Put a JPY signup order in the session cart, and make wc_get_order() load it instead of returning a cached copy.
		$order = $this->create_order_in_currency( Currency_Code::JAPANESE_YEN );
		WC()->session->set( 'cart', $this->get_initial_payment_cart_items( $order->get_id() ) );
		wp_cache_flush();

		// Arrange: Read the decimals while the order loads, which caches those of the store currency.
		[ $woocommerce_subscriptions, $frontend_currencies ] = $this->get_subscriptions_compatibility_with_frontend_currencies();

		$loads        = 0;
		$read_on_load = function ( $classname ) use ( $frontend_currencies, &$loads ) {
			++$loads;
			$frontend_currencies->get_price_decimals( 2 );
			return $classname;
		};
		add_filter( 'woocommerce_order_class', $read_on_load );

		// Act: Read the currency twice, and the decimals after the first read.
		$currency        = $woocommerce_subscriptions->override_selected_currency( false );
		$decimals        = $frontend_currencies->get_price_decimals( 2 );
		$second_currency = $woocommerce_subscriptions->override_selected_currency( false );
		remove_filter( 'woocommerce_order_class', $read_on_load );

		// Assert: The order's currency and decimals are used, and the order was loaded once.
		$this->assertSame( 'JPY', $currency );
		$this->assertSame( 0, $decimals );
		$this->assertSame( 'JPY', $second_currency );
		$this->assertSame( 1, $loads );
	}

	// Reads made while the signup order loads skip the override, and later reads use it again.
	public function test_override_selected_currency_skips_reads_while_loading_initial_payment_order() {
		// Arrange: Put a JPY signup order in the session cart, and make wc_get_order() load it instead of returning a cached copy.
		$order = $this->create_order_in_currency( Currency_Code::JAPANESE_YEN );
		WC()->session->set( 'cart', $this->get_initial_payment_cart_items( $order->get_id() ) );
		wp_cache_flush();

		// Arrange: Read the selected currency once while the order loads.
		$nested_read      = false;
		$nested_currency  = null;
		$read_during_load = function ( $classname ) use ( &$nested_read, &$nested_currency ) {
			if ( ! $nested_read ) {
				$nested_read     = true;
				$nested_currency = $this->woocommerce_subscriptions->override_selected_currency( false );
			}
			return $classname;
		};
		add_filter( 'woocommerce_order_class', $read_during_load );

		// Act: Read the currency, which loads the order.
		$currency = $this->woocommerce_subscriptions->override_selected_currency( false );
		remove_filter( 'woocommerce_order_class', $read_during_load );

		// Assert: The nested read was skipped, the outer one got the order's currency, and the next read isn't skipped.
		$this->assertTrue( $nested_read );
		$this->assertFalse( $nested_currency );
		$this->assertSame( 'JPY', $currency );
		$this->assertSame( 'JPY', $this->woocommerce_subscriptions->override_selected_currency( false ) );
	}

	public function test_override_selected_currency_return_false_when_initial_payment_order_missing() {
		// Arrange: Put an item for an order that does not exist in the session cart.
		WC()->session->set( 'cart', $this->get_initial_payment_cart_items( 999999 ) );

		// Act/Assert: Nothing to override with, so the selected currency is kept.
		$this->assertFalse( $this->woocommerce_subscriptions->override_selected_currency( false ) );
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

	/**
	 * This test covers the testing of 3 methods.
	 *
	 * override_selected_currency - Confirms that if the current_my_account_subscription property contains a valid subscription that the
	 * currency from that subscription will be returned.
	 *
	 * maybe_set_current_my_account_subscription - Tested when the woocommerce_subscription_price_string_details filter is applied. The filter
	 * itself should not modify the array passed, however, if the backtrace matches, the subscription passed should be set as the value of
	 * the current_my_account_subscription property.
	 *
	 * maybe_clear_current_my_account_subscription - Tested when the woocommerce_get_formatted_subscription_total filter is applied. The filter
	 * itself should not modify the string passed, however, if the current_my_account_subscription property is set, it should return it's value to null.
	 */
	public function test_override_selected_currency_return_currency_code_when_current_my_account_subscription_set() {
		// Arrange: Create a subscription and set the currency.
		$mock_subscription = $this->create_mock_subscription();
		$mock_subscription->set_currency( 'EUR' );

		// Assert: Confirm that override_selected_currency currently returns false.
		$this->assertFalse( $this->woocommerce_subscriptions->override_selected_currency( false ) );

		// Arrange: Set expectation and return for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with(
				[
					'WCS_Template_Loader::get_my_subscriptions ',
					'WC_Subscription->get_formatted_order_total',
				]
			)
			->willReturn( true );

		// Arrange/Assert: Apply the woocommerce_subscription_price_string_details filter and confirm the filter does not change the passed array.
		$this->assertSame( [ 1, 2, 3 ], apply_filters( 'woocommerce_subscription_price_string_details', [ 1, 2, 3 ], $mock_subscription ) );

		// Act/Assert: Confirm the currency is what we set.
		$this->assertSame( 'EUR', $this->woocommerce_subscriptions->override_selected_currency( false ) );

		// Arrange/Assert: Apply the woocommerce_get_formatted_subscription_total filter and confirm the filter does not change the passed string.
		$this->assertSame( 'expected_string', apply_filters( 'woocommerce_get_formatted_subscription_total', 'expected_string', $mock_subscription ) );

		// Assert: Confirm override_selected_currency returns false again.
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
	 * Confirms which call stacks stop a product price from being converted. The backtrace stub answers by
	 * frame list rather than call order, so reordering equivalent checks in production doesn't break this test.
	 *
	 * @dataProvider provider_should_convert_product_price
	 */
	public function test_should_convert_product_price( $cart_subscription_type, array $matching_backtraces, bool $expected ) {
		if ( $cart_subscription_type ) {
			[ $_unused_mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( $cart_subscription_type );

			// The product being priced is the one held by the subscription cart item.
			$cart_items[0]['data'] = $this->mock_product;
			WC()->cart->set_cart_contents( $cart_items );
		}

		$matching_frames = array_map(
			function ( $backtrace ) {
				return self::PRODUCT_PRICE_BACKTRACES[ $backtrace ];
			},
			$matching_backtraces
		);
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturnCallback(
				function ( array $frames ) use ( $matching_frames ) {
					return in_array( $frames, $matching_frames, true );
				}
			);

		$this->assertSame( $expected, $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	public function provider_should_convert_product_price(): array {
		return [
			'no subscription in cart, no backtrace matches' => [ null, [], true ],
			'no subscription in cart, cart totals in backtrace' => [ null, [ 'cart_totals' ], true ],
			'no subscription in cart, only recurring item data' => [ null, [ 'recurring_item_data' ], true ],
			'no subscription in cart, only product get_price' => [ null, [ 'product_get_price' ], true ],
			'no subscription in cart, recurring item data price' => [ null, [ 'recurring_item_data', 'product_get_price' ], false ],
			'renewal in cart, no backtrace matches'      => [ 'renewal', [], true ],
			'renewal in cart, cart totals in backtrace'  => [ 'renewal', [ 'cart_totals' ], false ],
			'renewal in cart, cart being set up'         => [ 'renewal', [ 'renewal_cart_setup', 'cart_totals' ], true ],
			'renewal in cart, only recurring item data'  => [ 'renewal', [ 'recurring_item_data' ], true ],
			'renewal in cart, recurring item data price' => [ 'renewal', [ 'recurring_item_data', 'product_get_price' ], false ],
			'resubscribe in cart, no backtrace matches'  => [ 'resubscribe', [], true ],
			'resubscribe in cart, cart totals in backtrace' => [ 'resubscribe', [ 'cart_totals' ], false ],
			'resubscribe in cart, renewal cart setup does not apply' => [ 'resubscribe', [ 'renewal_cart_setup', 'cart_totals' ], false ],
			'resubscribe in cart, only recurring item data' => [ 'resubscribe', [ 'recurring_item_data' ], true ],
			'resubscribe in cart, recurring item data price' => [ 'resubscribe', [ 'recurring_item_data', 'product_get_price' ], false ],
		];
	}

	/**
	 * Only the renewal or resubscribe cart item's own product holds a price WCS already set in the subscription
	 * currency, so other products in the cart are converted.
	 *
	 * @dataProvider provider_should_convert_product_price_of_other_products_in_cart
	 */
	public function test_should_convert_product_price_of_other_products_in_cart( $cart_subscription_type, $other_product_id ) {
		// Arrange: Put the subscription cart item (holding the mock product) and a second, regular cart item in the cart.
		[ $_unused_mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( $cart_subscription_type );

		$other_product = $this->createMock( \WC_Product::class );
		$other_product
			->method( 'get_id' )
			->willReturn( $other_product_id );

		$cart_items[0]['data'] = $this->mock_product;
		$cart_items[]          = [
			'product_id' => $other_product_id,
			'key'        => 'def456',
			'data'       => $other_product,
		];
		WC()->cart->set_cart_contents( $cart_items );

		// Arrange: The cart totals are being calculated.
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturnCallback(
				function ( array $frames ) {
					return self::PRODUCT_PRICE_BACKTRACES['cart_totals'] === $frames;
				}
			);

		// Act/Assert: Only the subscription cart item's own product is left unconverted; the other product is converted.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_product_price( true, $other_product ) );
	}

	public function provider_should_convert_product_price_of_other_products_in_cart(): array {
		return [
			'renewal in cart, different product'      => [ 'renewal', 43 ],
			'renewal in cart, same product again'     => [ 'renewal', 42 ],
			'resubscribe in cart, different product'  => [ 'resubscribe', 43 ],
			'resubscribe in cart, same product again' => [ 'resubscribe', 42 ],
		];
	}

	/**
	 * When a product is passed, the session cart is not consulted: WooCommerce loads session items into
	 * WC()->cart->cart_contents before any pricing call, and session items hold no product object to match.
	 * A product priced while the renewal lives only in the session is therefore converted.
	 */
	public function test_should_convert_product_price_converts_when_subscription_only_in_session() {
		// Arrange: Renewal item is in the session cart only; WC()->cart is empty.
		$this->get_mock_subscription_and_session_cart_items( 'renewal' );

		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturnCallback(
				function ( array $frames ) {
					return self::PRODUCT_PRICE_BACKTRACES['cart_totals'] === $frames;
				}
			);

		// Act/Assert: With a product given, the session-only renewal no longer blocks conversion.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_product_price( true, $this->mock_product ) );
	}

	/**
	 * Callers that pass no product (override_selected_currency, coupon checks, etc.) keep the historical
	 * cart-wide answer: a renewal anywhere in the cart blocks conversion.
	 */
	public function test_should_convert_product_price_keeps_cart_wide_answer_without_product() {
		[ $_unused_mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );
		$cart_items[0]['data']                      = $this->mock_product;
		WC()->cart->set_cart_contents( $cart_items );
		WC()->session->set( 'cart', [] );

		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturnCallback(
				function ( array $frames ) {
					return self::PRODUCT_PRICE_BACKTRACES['cart_totals'] === $frames;
				}
			);

		// Act/Assert: Without a product, the cart-wide behaviour is preserved.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_product_price( true, null ) );
	}

	/**
	 * A second subscription product added next to a resubscribe is priced through get_subscription_product_price,
	 * which shares the decision, so it is converted like any other product in the cart.
	 */
	public function test_get_subscription_product_price_converts_other_subscription_product() {
		[ $_unused_mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'resubscribe' );

		$other_product = $this->createMock( \WC_Product::class );
		$other_product
			->method( 'get_id' )
			->willReturn( 43 );

		$cart_items[0]['data'] = $this->mock_product;
		$cart_items[]          = [
			'product_id' => 43,
			'key'        => 'def456',
			'data'       => $other_product,
		];
		WC()->cart->set_cart_contents( $cart_items );

		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturnCallback(
				function ( array $frames ) {
					return self::PRODUCT_PRICE_BACKTRACES['cart_totals'] === $frames;
				}
			);

		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_price' )
			->with( 20, 'product' )
			->willReturn( 3000.0 );

		// Act/Assert: The other subscription product's price is converted.
		$this->assertSame( 3000.0, $this->woocommerce_subscriptions->get_subscription_product_price( 20, $other_product ) );
	}

	/**
	 * The resubscribe item's own product must keep the price WCS set from the subscription, so it is not converted
	 * again.
	 */
	public function test_get_subscription_product_price_does_not_convert_resubscribe_item() {
		[ $_unused_mock_subscription, $cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'resubscribe' );
		$cart_items[0]['data']                      = $this->mock_product;
		WC()->cart->set_cart_contents( $cart_items );

		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturnCallback(
				function ( array $frames ) {
					return self::PRODUCT_PRICE_BACKTRACES['cart_totals'] === $frames;
				}
			);

		$this->mock_multi_currency
			->expects( $this->never() )
			->method( 'get_price' );

		// Act/Assert: The resubscribe item's own price is returned unchanged.
		$this->assertSame( 1500, $this->woocommerce_subscriptions->get_subscription_product_price( 1500, $this->mock_product ) );
	}

	/**
	 * Runs the real backtrace check rather than a stub: real products are priced through wc_get_price_excluding_tax()
	 * while a renewal or resubscribe holding one of them is in the cart. Only that cart item's own product object keeps
	 * its price; another product, and a copy of the same product loaded again with wc_get_product(), are converted.
	 *
	 * @dataProvider provider_subscription_cart_item_types
	 */
	public function test_should_convert_product_price_with_real_backtrace_matches_only_the_cart_item_object( $cart_subscription_type ) {
		// Arrange: The real backtrace helper, and a subscription cart item holding a real product next to a regular one.
		[ $woocommerce_subscriptions, $subscription_product, $other_product ] = $this->set_up_real_products_in_cart( $cart_subscription_type );
		$copy_of_subscription_product = wc_get_product( $subscription_product->get_id() );

		// Act.
		$decisions = $this->get_decisions_while_pricing(
			$woocommerce_subscriptions,
			[ $subscription_product, $other_product, $copy_of_subscription_product ]
		);

		// Assert.
		$this->assertSame( [ false, true, true ], $decisions );
	}

	/**
	 * Pins the limit of matching by object: a clone of the subscription cart item's product is a different object, so
	 * it is converted even though it carries the price WCS set in the subscription currency. No WooCommerce or WCS path
	 * prices such a clone; a plugin that hands cart templates a clone through woocommerce_cart_item_product would see
	 * that item's displayed price converted again, while the cart totals, which read the cart item's own object, stay
	 * right.
	 *
	 * @dataProvider provider_subscription_cart_item_types
	 */
	public function test_should_convert_product_price_with_real_backtrace_converts_a_clone_of_the_cart_item_product( $cart_subscription_type ) {
		// Arrange.
		[ $woocommerce_subscriptions, $subscription_product ] = $this->set_up_real_products_in_cart( $cart_subscription_type );

		// Act.
		$decisions = $this->get_decisions_while_pricing( $woocommerce_subscriptions, [ clone $subscription_product ] );

		// Assert.
		$this->assertSame( [ true ], $decisions );
	}

	public function provider_subscription_cart_item_types(): array {
		return [
			'renewal'     => [ 'renewal' ],
			'resubscribe' => [ 'resubscribe' ],
		];
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

	/**
	 * Confirm that the amount of a pseudo coupon, which is a discount taken from an order or subscription, is not converted.
	 *
	 * @dataProvider pseudo_coupon_type_provider
	 */
	public function test_should_convert_coupon_amount_return_false_when_pseudo_coupon_type( $coupon_type ) {
		// Arrange: Set return for our mock coupon.
		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( $coupon_type );

		// Arrange: Set expectation for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_call_in_backtrace' );

		// Act/Assert: Confirm the result value is false.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	public function pseudo_coupon_type_provider() {
		return [
			'renewal_cart' => [ 'renewal_cart' ],
			'initial_cart' => [ 'initial_cart' ],
		];
	}

	/**
	 * Confirm false is returned while Subscriptions copies the coupon into the session, so the copy keeps the original amount.
	 *
	 * @dataProvider cart_setup_coupon_type_provider
	 */
	public function test_should_convert_coupon_amount_return_false_when_coupon_is_being_stored_in_session( $cart_setup_action, $coupon_type ) {
		// Arrange: Set return for our mock coupon.
		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( $coupon_type );

		// Arrange: Set expectation and return for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WCS_Cart_Renewal->store_coupon' ] )
			->willReturn( true );

		// Act: Check the coupon while the cart is being set up.
		$should_convert = $this->call_during_action(
			$cart_setup_action,
			function () {
				return $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon );
			}
		);

		// Assert: Confirm the result value is false.
		$this->assertFalse( $should_convert );
	}

	/**
	 * Confirm true is returned for other queries while the cart is being set up, such as when the coupon is validated.
	 *
	 * @dataProvider cart_setup_action_provider
	 */
	public function test_should_convert_coupon_amount_return_true_during_cart_setup_when_coupon_is_not_being_stored( $cart_setup_action ) {
		// Arrange: Set return for our mock coupon.
		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( 'fixed_cart' );

		// Arrange: Set expectation and return for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WCS_Cart_Renewal->store_coupon' ] )
			->willReturn( false );

		// Act: Check the coupon while the cart is being set up.
		$should_convert = $this->call_during_action(
			$cart_setup_action,
			function () {
				return $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon );
			}
		);

		// Assert: Confirm the result value is true.
		$this->assertTrue( $should_convert );
	}

	/**
	 * Confirm true is returned, without inspecting the backtrace, once the renewal is in the cart, as the copy in the session holds the original amount.
	 *
	 * @dataProvider fixed_amount_coupon_type_provider
	 */
	public function test_should_convert_coupon_amount_return_true_when_renewal_in_cart( $coupon_type ) {
		// Arrange: Create a subscription and cart_items to be used.
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );

		// Arrange: Set return for our mock coupon.
		$this->mock_coupon
			->method( 'get_discount_type' )
			->willReturn( $coupon_type );

		// Arrange: Set expectation for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_call_in_backtrace' );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	/**
	 * A copy stored in the session by an earlier version already holds converted amounts.
	 *
	 * @dataProvider fixed_amount_coupon_type_provider
	 */
	public function test_should_convert_coupon_amount_return_false_when_session_copy_stored_by_earlier_version( $coupon_type ) {
		// Arrange: A renewal cart with a coupon copy in the session and no unconverted mark.
		$this->get_mock_subscription_and_session_cart_items( 'renewal' );
		$this->arrange_session_copy_of_mock_coupon( $coupon_type );

		// Act/Assert: The copy's amount is not converted again.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	/**
	 * Copies stored while the cart is set up are unconverted, both during the setup and after it.
	 *
	 * @dataProvider cart_setup_action_provider
	 */
	public function test_should_convert_coupon_amount_return_true_when_session_copy_stored_during_cart_setup( $cart_setup_action ) {
		// Arrange: Store a coupon copy during the setup, as Subscriptions does, and read the coupon there too.
		$this->get_mock_subscription_and_session_cart_items( 'renewal' );
		$read_during_setup = null;
		$store_copy        = function () use ( &$read_during_setup ) {
			$this->arrange_session_copy_of_mock_coupon( 'renewal_fee' );
			$read_during_setup = $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon );
		};
		add_action( $cart_setup_action, $store_copy );

		// Act: Set up the cart, which reads the coupon while storing it.
		do_action( $cart_setup_action ); // phpcs:ignore WooCommerce.Commenting.CommentHooks
		remove_action( $cart_setup_action, $store_copy );

		// Assert: The copy's amount is converted when it's read, during and after the setup.
		$this->assertTrue( $read_during_setup );
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	// A copy rewritten after the setup by code that doesn't mark it, e.g. an earlier version after a rollback, is already converted.
	public function test_should_convert_coupon_amount_return_false_when_session_copy_rewritten_after_cart_setup() {
		// Arrange: Store an unconverted copy during the setup.
		$cart_setup_action = 'woocommerce_setup_cart_for_subscription_renewal';
		$this->get_mock_subscription_and_session_cart_items( 'renewal' );
		$store_copy = function () {
			$this->arrange_session_copy_of_mock_coupon( 'renewal_fee' );
		};
		add_action( $cart_setup_action, $store_copy );
		do_action( $cart_setup_action ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		remove_action( $cart_setup_action, $store_copy );

		// Arrange: Rewrite the copy with a converted amount, without marking it.
		$session_coupons                          = WC()->session->get( 'wcs_renewal_coupons' );
		$session_coupons[123]['fixed1']['amount'] = 150;
		WC()->session->set( 'wcs_renewal_coupons', $session_coupons );

		// Act/Assert: The rewritten copy's amount is not converted again.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_amount( true, $this->mock_coupon ) );
	}

	public function test_marking_session_coupons_without_session_does_not_fail() {
		// Arrange: No session, e.g. the cart setup action fired by other code outside a shopper request.
		$session      = WC()->session;
		WC()->session = null;

		try {
			// Act: Run both marker callbacks.
			$this->woocommerce_subscriptions->start_storing_unconverted_session_coupons();
			$this->woocommerce_subscriptions->finish_storing_unconverted_session_coupons();
		} finally {
			WC()->session = $session;
		}

		// Assert: Nothing was written to the session that comes back.
		$this->assertNull( WC()->session->get( 'wcpay_multi_currency_unconverted_session_coupons' ) );
	}

	public function test_should_convert_coupon_min_max_amount_return_false_when_session_copy_stored_by_earlier_version() {
		// Arrange: A renewal cart with a coupon copy in the session and no unconverted mark.
		$this->get_mock_subscription_and_session_cart_items( 'renewal' );
		$this->arrange_session_copy_of_mock_coupon( 'fixed_cart' );

		// Act/Assert: The copy's minimum and maximum amounts are not converted again.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_min_max_amount( true, $this->mock_coupon ) );
	}

	public function fixed_amount_coupon_type_provider() {
		return [
			'fixed_cart'    => [ 'fixed_cart' ],
			'fixed_product' => [ 'fixed_product' ],
			'sign_up_fee'   => [ 'sign_up_fee' ],
			'recurring_fee' => [ 'recurring_fee' ],
			'renewal_fee'   => [ 'renewal_fee' ],
		];
	}

	public function cart_setup_action_provider() {
		return [
			'renewal'         => [ 'woocommerce_setup_cart_for_subscription_renewal' ],
			'initial payment' => [ 'woocommerce_setup_cart_for_subscription_initial_payment' ],
		];
	}

	public function cart_setup_coupon_type_provider() {
		$cases = [];
		foreach ( $this->cart_setup_action_provider() as $action_name => [ $action ] ) {
			foreach ( $this->fixed_amount_coupon_type_provider() as $coupon_type_name => [ $coupon_type ] ) {
				$cases[ "$action_name, $coupon_type_name" ] = [ $action, $coupon_type ];
			}
		}
		return $cases;
	}

	// This method should return false if false is passed, and the is_call_in_backtrace call should never be called.
	public function test_should_convert_coupon_min_max_amount_return_false_if_false_passed() {
		// Arrange: Set expectation for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_call_in_backtrace' );

		// Act/Assert: Confirm the result value is false.
		$this->assertFalse( $this->woocommerce_subscriptions->should_convert_coupon_min_max_amount( false ) );
	}

	/**
	 * Confirm false is returned while Subscriptions copies the coupon into the session, so the copy keeps the original amounts.
	 *
	 * @dataProvider cart_setup_action_provider
	 */
	public function test_should_convert_coupon_min_max_amount_return_false_when_coupon_is_being_stored_in_session( $cart_setup_action ) {
		// Arrange: Set expectation and return for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WCS_Cart_Renewal->store_coupon' ] )
			->willReturn( true );

		// Act: Check the coupon while the cart is being set up.
		$should_convert = $this->call_during_action(
			$cart_setup_action,
			function () {
				return $this->woocommerce_subscriptions->should_convert_coupon_min_max_amount( true );
			}
		);

		// Assert: Confirm the result value is false.
		$this->assertFalse( $should_convert );
	}

	/**
	 * Confirm true is returned for other queries while the cart is being set up, such as when the minimum spend is validated.
	 *
	 * @dataProvider cart_setup_action_provider
	 */
	public function test_should_convert_coupon_min_max_amount_return_true_during_cart_setup_when_coupon_is_not_being_stored( $cart_setup_action ) {
		// Arrange: Set expectation and return for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WCS_Cart_Renewal->store_coupon' ] )
			->willReturn( false );

		// Act: Check the coupon while the cart is being set up.
		$should_convert = $this->call_during_action(
			$cart_setup_action,
			function () {
				return $this->woocommerce_subscriptions->should_convert_coupon_min_max_amount( true );
			}
		);

		// Assert: Confirm the result value is true.
		$this->assertTrue( $should_convert );
	}

	// Confirm true is returned, without inspecting the backtrace, when no cart is being set up.
	public function test_should_convert_coupon_min_max_amount_return_true_outside_cart_setup() {
		// Arrange: Create a subscription and cart_items to be used.
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( 'renewal' );

		// Arrange: Set expectation for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_call_in_backtrace' );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_convert_coupon_min_max_amount( true ) );
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
		[ $_unused_mock_subscription, $_unused_cart_items ] = $this->get_mock_subscription_and_session_cart_items( $sub_type );

		// Act/Assert: Confirm the result value is true.
		$this->assertTrue( $this->woocommerce_subscriptions->should_disable_currency_switching( false ) );
	}

	public function test_should_disable_currency_switching_return_true_when_initial_payment_in_cart() {
		// Arrange: Put a signup order in the session cart.
		$order = $this->create_order_in_currency( Currency_Code::JAPANESE_YEN );
		WC()->session->set( 'cart', $this->get_initial_payment_cart_items( $order->get_id() ) );

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

	public function test_maybe_get_explicit_format_for_subscription_total() {
		// Arrange: Create a subscription and set the currency.
		$mock_subscription = $this->create_mock_subscription();
		$mock_subscription->set_currency( 'EUR' );

		// Arrange: Set the price string to be passed, and also what is expected.
		$price    = '<span class="woocommerce-Price-amount amount"><bdi>14,00&nbsp;<span class="woocommerce-Price-currencySymbol">&euro;</span></bdi></span>';
		$expected = $price . ' EUR';

		// Arrange: Set expectation and return for is_call_in_backtrace.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with(
				[
					'WCS_Template_Loader::get_my_subscriptions ',
					'WC_Subscription->get_formatted_order_total',
				]
			)
			->willReturn( true );

		// Arrange: Set expectation and return for is_initialized and has_additional_currencies_enabled.
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'has_additional_currencies_enabled' )
			->willReturn( true );

		// Arrange/Assert: Apply the woocommerce_subscription_price_string_details filter and confirm the filter does not change the passed array.
		$this->assertSame( [ 1, 2, 3 ], apply_filters( 'woocommerce_subscription_price_string_details', [ 1, 2, 3 ], $mock_subscription ) );

		// Assert: Confirm the price returned is what is expected.
		$this->assertSame( $expected, $this->woocommerce_subscriptions->maybe_get_explicit_format_for_subscription_total( $price, '14.00', [], 14.00, 14.00 ) );
	}

	public function provider_sub_types_renewal_resubscribe_switch() {
		return [
			'renewal'     => [ 'renewal' ],
			'resubscribe' => [ 'resubscribe' ],
			'switch'      => [ 'switch' ],
		];
	}

	/**
	 * Wires a JPY subscription to a real FrontendCurrencies on a USD store. Loading the subscription reads the
	 * decimals, like the real load does, before the override can tell the currency of the subscription.
	 */
	private function get_subscriptions_compatibility_reading_decimals_on_load( $mock_subscription ) {
		$mock_subscription->set_currency( 'JPY' );

		[ $woocommerce_subscriptions, $frontend_currencies ] = $this->get_subscriptions_compatibility_with_frontend_currencies();

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $_unused_id ) use ( $frontend_currencies, $mock_subscription ) {
				$frontend_currencies->get_price_decimals( 2 );
				return $mock_subscription;
			}
		);

		return [ $woocommerce_subscriptions, $frontend_currencies ];
	}

	/**
	 * Builds the compatibility class with a real FrontendCurrencies, whose selected currency goes through the override.
	 * JPY has no decimals, and every other currency has two.
	 *
	 * @return array The compatibility class and FrontendCurrencies.
	 */
	private function get_subscriptions_compatibility_with_frontend_currencies() {
		$mock_localization = $this->createMock( MultiCurrencyLocalizationInterface::class );
		$mock_localization->method( 'get_currency_format' )->willReturnCallback(
			function ( $code ) {
				return [ 'num_decimals' => 'JPY' === $code ? 0 : 2 ];
			}
		);

		$mock_multi_currency = $this->createMock( MultiCurrency::class );
		$frontend_currencies = new FrontendCurrencies( $mock_multi_currency, $mock_localization, $this->mock_utils, $this->createMock( Compatibility::class ) );
		$mock_multi_currency->method( 'get_frontend_currencies' )->willReturn( $frontend_currencies );
		$mock_multi_currency->method( 'get_default_currency' )->willReturn( new Currency( $mock_localization, 'USD' ) );

		$woocommerce_subscriptions = new WooCommerceSubscriptions( $mock_multi_currency, $this->mock_utils );
		$mock_multi_currency->method( 'get_selected_currency' )->willReturnCallback(
			function () use ( &$woocommerce_subscriptions, $mock_localization ) {
				$override_code = $woocommerce_subscriptions->override_selected_currency( false );
				return new Currency( $mock_localization, $override_code ? $override_code : 'USD' );
			}
		);

		return [ $woocommerce_subscriptions, $frontend_currencies ];
	}

	/**
	 * Stores a copy of the mock coupon in the session, as Subscriptions does when it sets up a cart from an order.
	 *
	 * @param string $coupon_type The coupon's discount type.
	 */
	private function arrange_session_copy_of_mock_coupon( string $coupon_type ) {
		$coupon_code = 'fixed1';

		$this->mock_coupon->method( 'get_code' )->willReturn( $coupon_code );
		$this->mock_coupon->method( 'get_discount_type' )->willReturn( $coupon_type );

		WC()->session->set(
			'wcs_renewal_coupons',
			[
				123 => [
					$coupon_code => [
						'discount_type' => $coupon_type,
						'amount'        => 1,
					],
				],
			]
		);
	}

	/**
	 * Builds the class with the real Utils, and puts a subscription cart item holding a real product next to a regular
	 * cart item holding another real product.
	 *
	 * @param string $sub_type The subscription cart item type.
	 *
	 * @return array The class under test, the subscription item's product and the other product.
	 */
	private function set_up_real_products_in_cart( string $sub_type ): array {
		$woocommerce_subscriptions = new WooCommerceSubscriptions( $this->mock_multi_currency, new Utils() );
		$subscription_product      = WC_Helper_Product::create_simple_product();
		$other_product             = WC_Helper_Product::create_simple_product();
		$mock_subscription         = $this->create_mock_subscription();

		WC()->cart->set_cart_contents(
			[
				'abc123' => [
					'subscription_' . $sub_type => [ 'subscription_id' => $mock_subscription->get_id() ],
					'product_id'                => $subscription_product->get_id(),
					'key'                       => 'abc123',
					'data'                      => $subscription_product,
				],
				'def456' => [
					'product_id' => $other_product->get_id(),
					'key'        => 'def456',
					'data'       => $other_product,
				],
			]
		);

		return [ $woocommerce_subscriptions, $subscription_product, $other_product ];
	}

	/**
	 * Prices each product through wc_get_price_excluding_tax(), one of the frames the class looks for, and records the
	 * class's decision for each product object at the moment its price is read.
	 *
	 * @param WooCommerceSubscriptions $woocommerce_subscriptions The class under test.
	 * @param \WC_Product[]            $products                  The products to price.
	 *
	 * @return bool[] One decision per product, in order.
	 */
	private function get_decisions_while_pricing( WooCommerceSubscriptions $woocommerce_subscriptions, array $products ): array {
		$decisions = [];
		$record    = function ( $price, $product ) use ( $woocommerce_subscriptions, &$decisions ) {
			$decisions[ spl_object_id( $product ) ] = $woocommerce_subscriptions->should_convert_product_price( true, $product );
			return $price;
		};

		add_filter( 'woocommerce_product_get_price', $record, 10, 2 );
		foreach ( $products as $product ) {
			wc_get_price_excluding_tax( $product );
		}
		remove_filter( 'woocommerce_product_get_price', $record, 10 );

		return array_map(
			function ( $product ) use ( $decisions ) {
				return $decisions[ spl_object_id( $product ) ] ?? null;
			},
			$products
		);
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
			function ( $_unused_id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		return $mock_subscription;
	}

	/**
	 * Runs a callback while the given action is firing, so doing_action() is true for it.
	 *
	 * @param string   $action   The action name.
	 * @param callable $callback The callback to run.
	 *
	 * @return mixed What the callback returned.
	 */
	private function call_during_action( string $action, callable $callback ) {
		$result   = null;
		$listener = function () use ( $callback, &$result ) {
			$result = $callback();
		};

		add_action( $action, $listener );
		do_action( $action ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		remove_action( $action, $listener );

		return $result;
	}

	/**
	 * Creates and saves an order in the given currency.
	 *
	 * @param string $currency Three letter currency code.
	 *
	 * @return \WC_Order
	 */
	private function create_order_in_currency( string $currency ) {
		$order = \WC_Helper_Order::create_order();
		$order->set_currency( $currency );
		$order->save();

		return $order;
	}

	/**
	 * Builds the cart items Subscriptions uses to pay for a signup order again.
	 *
	 * @param int $order_id The signup order ID.
	 *
	 * @return array
	 */
	private function get_initial_payment_cart_items( int $order_id ): array {
		return [
			[
				'subscription_initial_payment' => [
					'order_id' => $order_id,
				],
				'product_id'                   => $this->mock_product->get_id(),
				'key'                          => 'abc123',
			],
		];
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

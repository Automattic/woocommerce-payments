<?php
/**
 * Class WCPay_Multi_Currency_Compatibility_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Currency;

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

	public function tearDown() {
		// Reset cart checks so future tests can pass.
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_wcs_get_order_type_cart_items( false );

		parent::tearDown();
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
			// Subscriptions filters.
			[ 'option_woocommerce_subscriptions_multiple_purchase', 'maybe_disable_mixed_cart' ],
			[ 'woocommerce_subscriptions_product_price', 'get_subscription_product_price' ],
			[ 'woocommerce_product_get__subscription_sign_up_fee', 'get_subscription_product_signup_fee' ],
			[ 'woocommerce_product_variation_get__subscription_sign_up_fee', 'get_subscription_product_signup_fee' ],

			// Product Add-Ons filters.
			[ 'woocommerce_add_cart_item', 'add_cart_item' ],
			[ 'woocommerce_get_cart_item_from_session', 'get_cart_item_from_session' ],
			[ 'woocommerce_product_addons_option_price_raw', 'get_addons_price' ],
			[ 'woocommerce_product_addons_price_raw', 'get_addons_price' ],
			[ 'woocommerce_get_price_including_tax', 'get_addon_order_display_price' ],
			[ 'woocommerce_get_price_excluding_tax', 'get_addon_order_display_price' ],
			[ 'woocommerce_product_addons_params', 'product_addons_params' ],
			[ 'woocommerce_get_item_data', 'get_item_data' ],
		];
	}

	/**
	 * @dataProvider ajax_filter_provider
	 */
	public function test_registers_ajax_filters_properly( $filter, $function_name ) {
		// Add filter to make it seem like it is an ajax request, then re-init Compatibility.
		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->compatibility = new WCPay\MultiCurrency\Compatibility( $this->mock_multi_currency, $this->mock_utils );

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

		// Remove all ajax filters, and re-init Compatibility again.
		remove_all_filters( 'wp_doing_ajax' );
		$this->compatibility = new WCPay\MultiCurrency\Compatibility( $this->mock_multi_currency, $this->mock_utils );
	}

	public function ajax_filter_provider() {
		return [
			// Product Add-Ons filters.
			[ 'woocommerce_get_price_including_tax', 'get_product_calculation_price' ],
			[ 'woocommerce_get_price_excluding_tax', 'get_product_calculation_price' ],
		];
	}

	public function test_woocommerce_product_addons_adjust_price_filter() {
		$this->assertFalse( apply_filters( 'woocommerce_product_addons_adjust_price', true ) );
	}

	// Test should not convert the product price due to all checks return true.
	public function test_get_subscription_product_price_does_not_convert_price() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( true );
		$this->mock_wcs_cart_contains_resubscribe( true );
		$this->assertSame( 10.0, $this->compatibility->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Test should convert product price due to all checks return false.
	public function test_get_subscription_product_price_converts_price_with_all_checks_false() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->compatibility->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Test should convert product price due to the backtrace check returns true but the cart contains renewal/resubscribe return checks false.
	public function test_get_subscription_product_price_converts_price_if_only_backtrace_found() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->compatibility->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Test should convert product price due to the backtrace check returns false after the cart contains renewal check returns true.
	public function test_get_subscription_product_price_converts_price_if_only_renewal_in_cart() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( true );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->compatibility->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Test should convert product price due to the backtrace check returns false after the cart contains resubscribe check returns true.
	public function test_get_subscription_product_price_converts_price_if_only_resubscribe_in_cart() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( false );
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( true );
		$this->mock_multi_currency->method( 'get_price' )->with( 10.0, 'product' )->willReturn( 25.0 );
		$this->assertSame( 25.0, $this->compatibility->get_subscription_product_price( 10.0, $this->mock_product ) );
	}

	// Does not convert price due to first backtrace check returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_on_first_backtrace_match() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Subscriptions_Cart::set_subscription_prices_for_calculation' ] )
			->willReturn( true );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->assertSame( 10.0, $this->compatibility->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
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
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->compatibility->switch_cart_item = 'abc123';
		$this->assertSame( 10.0, $this->compatibility->get_subscription_product_signup_fee( 10.0, $this->mock_product ) );
	}

	// Does not convert due to third check for changes in the meta data returns true.
	public function test_get_subscription_product_signup_fee_does_not_convert_price_when_meta_already_updated() {
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
	public function test_get_subscription_product_signup_fee_converts_price_when_meta_not_updated() {
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
	public function test_get_subscription_product_signup_fee_converts_price_when_cart_item_keys_do_not_match() {
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

	// Returns false due to all checks return false.
	public function test_override_selected_currency_return_false() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertFalse( $this->compatibility->override_selected_currency() );
	}

	// Returns code due to cart contains a subscription renewal.
	public function test_override_selected_currency_return_currency_code_when_renewal_in_cart() {
		$this->mock_wcs_cart_contains_renewal( true );
		$this->mock_wcs_cart_contains_resubscribe( false );
		update_post_meta( 42, '_order_currency', 'CAD', true );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertSame( 'CAD', $this->compatibility->override_selected_currency() );
	}

	// Returns code due to GET states there is a subscription switch like on the product page after clicking upgrade/downgrade button.
	public function test_override_selected_currency_return_currency_code_when_switch_initiated() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$_GET['switch-subscription'] = 42;
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );
		update_post_meta( 42, '_order_currency', 'CAD', true );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertSame( 'CAD', $this->compatibility->override_selected_currency() );
	}

	// Returns code due to cart contains a subscription switch.
	public function test_override_selected_currency_return_currency_code_when_switch_in_cart() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		update_post_meta( 42, '_order_currency', 'CAD', true );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->assertSame( 'CAD', $this->compatibility->override_selected_currency() );
	}

	// Returns code due to cart contains a subscription resubscribe.
	public function test_override_selected_currency_return_currency_code_when_resubscribe_in_cart() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( true );
		update_post_meta( 42, '_order_currency', 'CAD', true );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertSame( 'CAD', $this->compatibility->override_selected_currency() );
	}

	// Should return false since all checks return false.
	public function test_should_hide_widgets_return_false() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_cart_contains_resubscribe( false );
		$this->mock_wcs_get_order_type_cart_items( false );
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

	// Should return true if switch found in GET, like on product page.
	public function test_should_hide_widgets_return_true_when_starting_subscrition_switch() {
		$this->mock_wcs_cart_contains_renewal( false );
		$_GET['switch-subscription'] = 42;
		$_GET['_wcsnonce']           = wp_create_nonce( 'wcs_switch_request' );
		$this->mock_wcs_get_order_type_cart_items( false );
		$this->assertTrue( $this->compatibility->should_hide_widgets() );
	}

	// Should return true if switch found in cart.
	public function test_should_hide_widgets_return_true_when_switch_found_in_cart() {
		$this->mock_wcs_cart_contains_renewal( false );
		$this->mock_wcs_get_order_type_cart_items( true );
		$this->mock_wcs_cart_contains_resubscribe( false );
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

	public function test_get_product_calculation_price() {
		global $wp_current_filter;
		$wp_current_filter[] = 'woocommerce_get_price_excluding_tax';
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$product = WC_Helper_Product::create_simple_product();
		$this->mock_multi_currency->method( 'get_price' )->willReturn( 12.99 );

		$test_values = [
			[ 12.99, 1 ],
			[ 25.98, 2 ],
			[ 38.97, 3 ],
		];

		foreach ( $test_values as $values ) {
			$this->assertEquals( $values[0], $this->compatibility->get_product_calculation_price( $values[0], $values[1], $product ) );
		}
		array_pop( $wp_current_filter );
	}

	public function test_get_item_data_correctly_modifies_other_data_array_correctly() {
		$other_data = [
			[
				'other' => 'should remain',
			],
			[
				'name'    => 'test name',
				'value'   => 'test value',
				'display' => 'test display',
			],
		];

		$cart_item = [
			'addons' => [
				[
					'name'       => 'checkboxes',
					'value'      => 'test value',
					'price'      => 1.5,
					'price_type' => 'flat_fee',
					'display'    => 'This is a checkbox',
					'field_type' => 'test',
				],
			],
			'data'   => WC_Helper_Product::create_simple_product(),
		];

		$this->mock_multi_currency->method( 'get_price' )->with( 1.5, 'product' )->willReturn( 2.0 );

		$result = $this->compatibility->get_item_data( $other_data, $cart_item );

		// WC 4.4 introduced the <bdi> wrapper, and we still test back to WC 4.0.
		// For some reason version_compare didn't work to fix the issue, so, we update the string instead.
		if ( isset( $result[1]['name'] ) && false === strpos( $result[1]['name'], '<bdi>' ) ) {
			$result[1]['name'] = str_replace( 'amount"><span', 'amount"><bdi><span', $result[1]['name'] );
			$result[1]['name'] = str_replace( '</span>)', '</bdi></span>)', $result[1]['name'] );
		}

		$expected = [
			[
				'other' => 'should remain',
			],
			[
				'name'    => 'checkboxes (<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#36;</span>2.00</bdi></span>)',
				'value'   => 'test value',
				'display' => 'This is a checkbox',
			],
		];

		$this->assertSame( $expected, $result );
	}

	public function test_get_item_data_does_not_add_addon_price_to_name_if_price_zero() {
		$cart_item = [
			'addons' => [
				[
					'name'       => 'checkboxes',
					'value'      => 'test value',
					'price'      => 0,
					'price_type' => 'flat_fee',
					'field_type' => 'test',
				],
			],
			'data'   => WC_Helper_Product::create_simple_product(),
		];

		$expected = [
			[
				'name'    => 'checkboxes',
				'value'   => 'test value',
				'display' => '',
			],
		];

		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $cart_item ) );
	}

	public function test_get_item_data_does_not_add_addon_price_to_name_if_percentage_price_zero() {
		$cart_item = [
			'addons' => [
				[
					'name'       => 'checkboxes',
					'value'      => 'test value',
					'price'      => 0,
					'price_type' => 'percentage_based',
					'field_type' => 'test',
				],
			],
			'data'   => WC_Helper_Product::create_simple_product(),
		];

		$expected = [
			[
				'name'    => 'checkboxes',
				'value'   => 'test value',
				'display' => '',
			],
		];

		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $cart_item ) );
	}

	public function test_get_item_data_adds_custom_price_to_name_properly() {
		$cart_item = [
			'addons' => [
				[
					'name'       => 'custom price',
					'value'      => 'test value',
					'price'      => 42,
					'price_type' => 'flat_fee',
					'field_type' => 'custom_price',
				],
			],
			'data'   => WC_Helper_Product::create_simple_product(),
		];

		$result = $this->compatibility->get_item_data( [], $cart_item );

		// WC 4.4 introduced the <bdi> wrapper, and we still test back to WC 4.0.
		// For some reason version_compare didn't work to fix the issue, so, we update the string instead.
		if ( isset( $result[0]['name'] ) && false === strpos( $result[0]['name'], '<bdi>' ) ) {
			$result[0]['name'] = str_replace( 'amount"><span', 'amount"><bdi><span', $result[0]['name'] );
			$result[0]['name'] = str_replace( '</span>)', '</bdi></span>)', $result[0]['name'] );
		}

		$expected = [
			[
				'name'    => 'custom price (<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#36;</span>42.00</bdi></span>)',
				'value'   => 'test value',
				'display' => '',
			],
		];

		$this->assertSame( $expected, $result );
	}

	public function test_get_item_data_adds_price_to_name_properly() {
		$cart_item = [
			'addons' => [
				[
					'name'       => 'checkboxes',
					'value'      => 'test value',
					'price'      => 1.5,
					'price_type' => 'flat_fee',
					'field_type' => 'test',
				],
			],
			'data'   => WC_Helper_Product::create_simple_product(),
		];

		$this->mock_multi_currency->method( 'get_price' )->with( 1.5, 'product' )->willReturn( 2.0 );

		$result = $this->compatibility->get_item_data( [], $cart_item );

		// WC 4.4 introduced the <bdi> wrapper, and we still test back to WC 4.0.
		// For some reason version_compare didn't work to fix the issue, so, we update the string instead.
		if ( isset( $result[0]['name'] ) && false === strpos( $result[0]['name'], '<bdi>' ) ) {
			$result[0]['name'] = str_replace( 'amount"><span', 'amount"><bdi><span', $result[0]['name'] );
			$result[0]['name'] = str_replace( '</span>)', '</bdi></span>)', $result[0]['name'] );
		}

		$expected = [
			[
				'name'    => 'checkboxes (<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#36;</span>2.00</bdi></span>)',
				'value'   => 'test value',
				'display' => '',
			],
		];

		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $cart_item ) );
	}

	public function test_get_item_data_adds_percentage_price_to_name_properly() {
		$product   = WC_Helper_Product::create_simple_product();
		$cart_item = [
			'addons'                   => [
				[
					'name'       => 'checkboxes',
					'value'      => 'test value',
					'price'      => 50,
					'price_type' => 'percentage_based',
					'field_type' => 'test',
				],
			],
			'data'                     => $product,
			'product_id'               => $product->get_id(),
			'addons_price_before_calc' => 10,
		];

		// WC 4.4 introduced the <bdi> wrapper, and we still test back to WC 4.0.
		// For some reason version_compare didn't work to fix the issue, so, we update the string instead.
		if ( isset( $result[0]['name'] ) && false === strpos( $result[0]['name'], '<bdi>' ) ) {
			$result[0]['name'] = str_replace( 'amount"><span', 'amount"><bdi><span', $result[0]['name'] );
			$result[0]['name'] = str_replace( '</span>)', '</bdi></span>)', $result[0]['name'] );
		}

		$expected = [
			[
				'name'    => 'checkboxes (<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#36;</span>5.00</bdi></span>)',
				'value'   => 'test value',
				'display' => '',
			],
		];

		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $cart_item ) );
	}

	public function test_add_cart_item_returns_proper_addon_data() {
		$product   = WC_Helper_Product::create_simple_product();
		$cart_item = [
			'addons'     => [
				[
					'name'       => 'checkboxes',
					'value'      => 'flat fee',
					'price'      => 50,
					'price_type' => 'flat_fee',
					'field_type' => 'checkbox',
				],
				[
					'name'       => 'checkboxes',
					'value'      => 'percentage based',
					'price'      => 50,
					'price_type' => 'percentage_based',
					'field_type' => 'checkbox',
				],
				[
					'name'       => 'customer defined',
					'value'      => '',
					'price'      => 50,
					'price_type' => 'quantity_based',
					'field_type' => 'custom_price',
				],
			],
			'product_id' => $product->get_id(),
			'data'       => $product,
			'quantity'   => 1,
		];

		$result = $this->compatibility->add_cart_item( $cart_item );

		$this->assertSame( $cart_item['addons'], $result['addons'] );
		$this->assertEquals( 10, $result['addons_price_before_calc'] );
		$this->assertEquals( 10, $result['addons_regular_price_before_calc'] );
		$this->assertEquals( 0, $result['addons_sale_price_before_calc'] );
		$this->assertEquals( 1, $cart_item['data']->get_meta( 'wcpay_mc_addons_converted' ) );
	}

	public function test_add_cart_item_returns_percentage_price_correctly() {
		$product   = WC_Helper_Product::create_simple_product();
		$cart_item = [
			'addons'     => [
				[
					'name'       => 'checkboxes',
					'value'      => 'percentage based',
					'price'      => 50,
					'price_type' => 'percentage_based',
					'field_type' => 'checkbox',
				],
			],
			'product_id' => $product->get_id(),
			'data'       => $product,
			'quantity'   => 1,
		];

		$result = $this->compatibility->add_cart_item( $cart_item );

		$this->assertEquals( floatval( 15 ), floatval( $cart_item['data']->get_price() ) );
	}

	public function test_add_cart_item_returns_flat_fee_price_correctly() {
		$product   = WC_Helper_Product::create_simple_product();
		$cart_item = [
			'addons'     => [
				[
					'name'       => 'checkboxes',
					'value'      => 'flat fee',
					'price'      => 50,
					'price_type' => 'flat_fee',
					'field_type' => 'checkbox',
				],
			],
			'product_id' => $product->get_id(),
			'data'       => $product,
			'quantity'   => 1,
		];

		$this->mock_multi_currency->method( 'get_price' )->with( 50, 'product' )->willReturn( 75 );

		$result = $this->compatibility->add_cart_item( $cart_item );

		$this->assertEquals( floatval( 85 ), floatval( $cart_item['data']->get_price() ) );
	}

	public function test_add_cart_item_returns_custom_price_correctly() {
		$product   = WC_Helper_Product::create_simple_product();
		$cart_item = [
			'addons'     => [
				[
					'name'       => 'customer defined',
					'value'      => '',
					'price'      => 50,
					'price_type' => 'quantity_based',
					'field_type' => 'custom_price',
				],
			],
			'product_id' => $product->get_id(),
			'data'       => $product,
			'quantity'   => 1,
		];

		$result = $this->compatibility->add_cart_item( $cart_item );

		$this->assertEquals( floatval( 60 ), floatval( $cart_item['data']->get_price() ) );
	}

	public function test_get_addon_order_display_price_returns_correct_flat_fee_price_without_tax() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_multi_currency->method( 'get_price' )->with( 10, 'product' )->willReturn( 15 );

		$product = WC_Helper_Product::create_simple_product();

		$this->assertEquals( 15, $this->compatibility->get_addon_order_display_price( 10, 1, $product ) );
	}

	public function test_get_addon_order_display_price_returns_correct_flat_fee_price_with_tax() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );
		$this->mock_multi_currency->method( 'get_price' )->with( 10, 'product' )->willReturn( 15 );

		// We need to make it think it's in checkout with taxes enabled and included in the price.
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		add_filter( 'woocommerce_product_is_taxable', '__return_true' );
		add_filter(
			'woocommerce_matched_rates',
			function( $rates ) {
				$rates[] = [
					'rate'     => 10,
					'label'    => 'test tax',
					'shipping' => 'yes',
					'compound' => 'no',
				];
				return $rates;
			}
		);
		update_option( 'woocommerce_tax_display_cart', 'incl' );

		$product = WC_Helper_Product::create_simple_product();

		$this->assertEquals( 16.50, $this->compatibility->get_addon_order_display_price( 10, 1, $product ) );

		// Be sure to undo all the tax stuff.
		remove_filter( 'woocommerce_is_checkout', 10 );
		remove_filter( 'woocommerce_product_is_taxable', 10 );
		remove_filter( 'woocommerce_matched_rates', 10 );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
	}

	public function test_get_addon_order_display_price_returns_correct_percentage_price_without_tax() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );

		$product               = WC_Helper_Product::create_simple_product();     // Price is 10.
		$addon_price           = 50;                                             // Price is percentage, so 50%.
		$addon_currency_amount = $product->get_price() * ( $addon_price / 100 ); // Amount is 5.

		// Set the cart item data, then add the item to the cart.
		$cart_item_data = [
			'addons' => [
				[
					'name'       => 'checkboxes',
					'value'      => 'percentage based',
					'price'      => $addon_price,
					'price_type' => 'percentage_based',
					'field_type' => 'checkbox',
				],
			],
		];
		WC()->cart->add_to_cart( $product->get_id(), 1, 0, [], $cart_item_data );

		// Go through the one item in the cart and add the percentage currency amount set above to the meta.
		$cart = WC()->cart->get_cart_contents();
		foreach ( $cart as $item ) {
			$item['data']->update_meta_data( 'wcpay_mc_percentage_currency_amount', $addon_currency_amount );
		}

		$price = $this->compatibility->get_addon_order_display_price( $addon_currency_amount, 1, $product );
		$this->assertEquals( $addon_currency_amount, $price );
	}

	public function test_get_addon_order_display_price_returns_correct_percentage_price_with_tax() {
		$this->mock_utils->method( 'is_call_in_backtrace' )->willReturn( true );

		// We need to make it think it's in checkout with taxes enabled and included in the price.
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		add_filter( 'woocommerce_product_is_taxable', '__return_true' );
		add_filter(
			'woocommerce_matched_rates',
			function( $rates ) {
				$rates[] = [
					'rate'     => 10,
					'label'    => 'test tax',
					'shipping' => 'yes',
					'compound' => 'no',
				];
				return $rates;
			}
		);
		update_option( 'woocommerce_tax_display_cart', 'incl' );

		$product               = WC_Helper_Product::create_simple_product();     // Price is 10.
		$addon_price           = 50;                                             // Price is percentage, so 50%.
		$addon_currency_amount = $product->get_price() * ( $addon_price / 100 ); // Amount is 5.
		$expected_price        = $addon_currency_amount * 1.10;                  // Tax is 10% above, so we multiply by 1.1.

		// Set the cart item data, then add the item to the cart.
		$cart_item_data = [
			'addons' => [
				[
					'name'       => 'checkboxes',
					'value'      => 'percentage based',
					'price'      => $addon_price,
					'price_type' => 'percentage_based',
					'field_type' => 'checkbox',
				],
			],
		];
		WC()->cart->add_to_cart( $product->get_id(), 1, 0, [], $cart_item_data );

		// Go through the one item in the cart and add the percentage currency amount set above to the meta.
		$cart = WC()->cart->get_cart_contents();
		foreach ( $cart as $item ) {
			$item['data']->update_meta_data(
				'wcpay_mc_percentage_currency_amount',
				$addon_currency_amount
			);
		}

		// We submit the expected price because that's what PAO does. There's a check in the tested method that confirms the math adds up.
		$price = $this->compatibility->get_addon_order_display_price( $expected_price, 1, $product );
		$this->assertEquals( $expected_price, $price );

		// Be sure to undo all the tax stuff.
		remove_filter( 'woocommerce_is_checkout', 10 );
		remove_filter( 'woocommerce_product_is_taxable', 10 );
		remove_filter( 'woocommerce_matched_rates', 10 );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
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

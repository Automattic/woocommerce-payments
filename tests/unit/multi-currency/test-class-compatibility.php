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

			// Product Add Ons filters.
			[ 'woocommerce_product_addons_option_price_raw', 'get_addons_price' ],
			[ 'woocommerce_product_addons_price_raw', 'get_addons_price' ],
			[ 'woocommerce_product_addons_params', 'product_addons_params' ],
			[ 'woocommerce_product_addons_get_item_data', 'get_item_data' ],
			[ 'woocommerce_product_addons_update_product_price', 'update_product_price' ],
			[ 'woocommerce_product_addons_order_line_item_meta', 'order_line_item_meta' ],
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
			[ 'woocommerce_product_addons_ajax_get_product_price_including_tax', 'get_product_calculation_price' ],
			[ 'woocommerce_product_addons_ajax_get_product_price_excluding_tax', 'get_product_calculation_price' ],
		];
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

	public function test_should_convert_product_price_return_false_when_product_meta_addons_converted_set() {
		$product = WC_Helper_Product::create_simple_product();
		$product->update_meta_data( 'wcpay_mc_addons_converted', 1 );
		$this->assertFalse( $this->compatibility->should_convert_product_price( $product ) );
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

	public function test_get_addons_price_returns_percentage_without_conversion() {
		$this->assertEquals( 50, $this->compatibility->get_addons_price( 50, [ 'price_type' => 'percentage_based' ] ) );
	}

	public function test_get_addons_price_returns_converted_price() {
		$this->mock_multi_currency->method( 'get_price' )->with( 50, 'product' )->willReturn( 75 );
		$this->assertEquals( 75, $this->compatibility->get_addons_price( 50, [ 'price_type' => 'flat_fee' ] ) );
	}

	public function test_get_product_calculation_price_returns_correctly() {
		$price = 42;
		$this->mock_multi_currency->method( 'get_price' )->with( $price, 'product' )->willReturn( $price );
		for ( $i = 1; $i < 5; $i++ ) {
			$expected = $price * $i;
			$this->assertEquals( $expected, $this->compatibility->get_product_calculation_price( $expected, $i, $this->mock_product ) );
		}
	}

	public function test_order_line_item_meta_returns_flat_fee_data_correctly() {
		$price = 42;
		$this->mock_multi_currency->method( 'get_price' )->with( $price, 'product' )->willReturn( $price * 2 );
		$addon = [
			'name'       => 'checkboxes',
			'value'      => 'flat fee',
			'price'      => $price,
			'field_type' => 'checkbox',
			'price_type' => 'flat_fee',
		];

		// Create an Order Item, add a new product to the Order Item.
		$item = new WC_Order_Item_Product();
		$item->set_props( [ 'product' => WC_Helper_Product::create_simple_product() ] );
		$item->save();

		$expected = [
			'key'   => 'checkboxes ($84.00)',
			'value' => 'flat fee',
		];
		$this->assertSame( $expected, $this->compatibility->order_line_item_meta( [], $addon, $item, [ 'data' => '' ] ) );
	}

	public function test_order_line_item_meta_returns_percentage_data_correctly() {
		$price = 50;
		$addon = [
			'name'       => 'checkboxes',
			'value'      => 'percentage based',
			'price'      => $price,
			'field_type' => 'checkbox',
			'price_type' => 'percentage_based',
		];

		// Create an Order Item, add a new product to the Order Item.
		$item = new WC_Order_Item_Product();
		$item->set_props( [ 'product' => WC_Helper_Product::create_simple_product() ] );
		$item->save();

		$expected = [
			'key'   => 'checkboxes ($5.00)',
			'value' => 'percentage based',
		];
		$this->assertSame( $expected, $this->compatibility->order_line_item_meta( [], $addon, $item, [ 'data' => '' ] ) );
	}

	public function test_order_line_item_meta_returns_input_multiplier_data_correctly() {
		$price = 42;
		$value = 2;
		$this->mock_multi_currency->method( 'get_price' )->with( $price / $value, 'product' )->willReturn( $price / $value );
		$addon = [
			'name'       => 'quantity',
			'value'      => $value,
			'price'      => $price,
			'field_type' => 'input_multiplier',
			'price_type' => 'flat_fee',
		];

		// Create an Order Item, add a new product to the Order Item.
		$item = new WC_Order_Item_Product();
		$item->set_props( [ 'product' => WC_Helper_Product::create_simple_product() ] );
		$item->save();

		$expected = [
			'key'   => 'quantity ($42.00)',
			'value' => 2,
		];
		$this->assertSame( $expected, $this->compatibility->order_line_item_meta( [], $addon, $item, [ 'data' => '' ] ) );
	}

	public function test_order_line_item_meta_returns_custom_price_data_correctly() {
		$price = 42;
		$this->mock_multi_currency->method( 'get_price' )->with( $price, 'product' )->willReturn( $price * 2 );
		$addon = [
			'name'       => 'checkboxes',
			'value'      => 'custom price',
			'price'      => $price,
			'field_type' => 'custom_price',
			'price_type' => '',
		];

		// Create an Order Item, add a new product to the Order Item.
		$item = new WC_Order_Item_Product();
		$item->set_props( [ 'product' => WC_Helper_Product::create_simple_product() ] );
		$item->save();

		$expected = [
			'key'   => 'checkboxes ($42.00)',
			'value' => 42,
		];
		$this->assertSame( $expected, $this->compatibility->order_line_item_meta( [], $addon, $item, [ 'data' => '' ] ) );
	}

	public function test_update_product_price_returns_flat_fee_data_correctly() {
		$addon     = [
			'name'       => 'checkboxes',
			'value'      => 'flat fee',
			'price'      => 42,
			'field_type' => 'checkbox',
			'price_type' => 'flat_fee',
		];
		$cart_item = [
			'addons'   => [ $addon ],
			'data'     => WC_Helper_Product::create_simple_product(),
			'quantity' => 1,
		];
		$prices    = [
			'price'         => 10,
			'regular_price' => 10,
			'sale_price'    => 0,
		];
		$expected  = [
			'price'         => 78.0, // (10 * 1.5) + (42 * 1.5)
			'regular_price' => 78.0,
			'sale_price'    => 63.0, // (0 * 1.5) + (42 * 1.5)
		];

		$this->mock_multi_currency
			->expects( $this->exactly( 4 ) )
			->method( 'get_price' )
			->withConsecutive(
				[ 10.0, 'product' ],
				[ 10.0, 'product' ],
				[ 0.0, 'product' ],
				[ 42.0, 'product' ]
			)
			->willReturn( 15.0, 15.0, 0.0, 63.0 );

		$this->assertSame( $expected, $this->compatibility->update_product_price( [], $cart_item, $prices ) );
		$this->assertEquals( 1, $cart_item['data']->get_meta( 'wcpay_mc_addons_converted' ) );
	}

	public function test_update_product_price_returns_percentage_data_correctly() {
		$addon     = [
			'name'       => 'checkboxes',
			'value'      => 'percentage',
			'price'      => 50,
			'field_type' => 'checkbox',
			'price_type' => 'percentage_based',
		];
		$cart_item = [
			'addons'   => [ $addon ],
			'data'     => WC_Helper_Product::create_simple_product(),
			'quantity' => 1,
		];
		$prices    = [
			'price'         => 10,
			'regular_price' => 10,
			'sale_price'    => 0,
		];
		$expected  = [
			'price'         => 22.5, // 10 * 1.5 * 1.5
			'regular_price' => 22.5,
			'sale_price'    => 0.0,
		];

		// Product is created with a price of 10, and update_product_price calls get_price, which is already converted.
		$cart_item['data']->set_price( 15.0 );

		$this->mock_multi_currency
			->expects( $this->exactly( 3 ) )
			->method( 'get_price' )
			->withConsecutive(
				[ 10.0, 'product' ],
				[ 10.0, 'product' ],
				[ 0.0, 'product' ]
			)
			->willReturn( 15.0, 15.0, 0.0 );

		$this->assertSame( $expected, $this->compatibility->update_product_price( [], $cart_item, $prices ) );
	}

	public function test_update_product_price_returns_custom_price_data_correctly() {
		$addon     = [
			'name'       => 'custom price',
			'value'      => 'custom price',
			'price'      => 42,
			'field_type' => 'custom_price',
			'price_type' => 'quantity_based',
		];
		$cart_item = [
			'addons'   => [ $addon ],
			'data'     => WC_Helper_Product::create_simple_product(),
			'quantity' => 1,
		];
		$prices    = [
			'price'         => 10,
			'regular_price' => 10,
			'sale_price'    => 0,
		];
		$expected  = [
			'price'         => 57.0, // (10 * 1.5) + 42
			'regular_price' => 57.0,
			'sale_price'    => 42.0,
		];

		$this->mock_multi_currency
			->expects( $this->exactly( 3 ) )
			->method( 'get_price' )
			->withConsecutive(
				[ 10.0, 'product' ],
				[ 10.0, 'product' ],
				[ 0.0, 'product' ]
			)
			->willReturn( 15.0, 15.0, 0.0 );

		$this->assertSame( $expected, $this->compatibility->update_product_price( [], $cart_item, $prices ) );
	}

	public function test_update_product_price_returns_multiplier_data_correctly() {
		$addon     = [
			'name'       => 'quantity multiplier',
			'value'      => 2,
			'price'      => 84,
			'field_type' => 'input_multiplier',
			'price_type' => 'flat_fee',
		];
		$cart_item = [
			'addons'   => [ $addon ],
			'data'     => WC_Helper_Product::create_simple_product(),
			'quantity' => 1,
		];
		$prices    = [
			'price'         => 10,
			'regular_price' => 10,
			'sale_price'    => 0,
		];
		$expected  = [
			'price'         => 141.0, // (10 * 1.5) + ((42 * 1.5) * 2)
			'regular_price' => 141.0,
			'sale_price'    => 126.0, // (0 * 1.5) + ((42 * 1.5) * 2)
		];

		$this->mock_multi_currency
			->expects( $this->exactly( 4 ) )
			->method( 'get_price' )
			->withConsecutive(
				[ 10.0, 'product' ],
				[ 10.0, 'product' ],
				[ 0.0, 'product' ],
				[ 42.0, 'product' ]
			)
			->willReturn( 15.0, 15.0, 0.0, 63.0 );

		$this->assertSame( $expected, $this->compatibility->update_product_price( [], $cart_item, $prices ) );
		$this->assertEquals( 1, $cart_item['data']->get_meta( 'wcpay_mc_addons_converted' ) );
	}

	public function test_get_item_data_returns_zero_price_data_correctly() {
		$addon     = [
			'name'       => 'checkbox',
			'value'      => 'zero price',
			'price'      => 0.0,
			'field_type' => 'checkbox',
			'price_type' => 'flat_fee',
			'display'    => 'display',
		];
		$cart_item = [
			'addons'   => [ $addon ],
			'data'     => WC_Helper_Product::create_simple_product(),
			'quantity' => 1,
		];
		$expected  = [
			'name'    => 'checkbox',
			'value'   => 'zero price',
			'display' => 'display',
		];

		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $addon, $cart_item ) );
	}

	public function test_get_item_data_returns_zero_percentage_price_data_correctly() {
		$addon     = [
			'name'       => 'checkbox',
			'value'      => 'zero price',
			'price'      => 50,
			'field_type' => 'checkbox',
			'price_type' => 'percentage_based',
		];
		$cart_item = [
			'addons'                   => [ $addon ],
			'data'                     => WC_Helper_Product::create_simple_product(),
			'quantity'                 => 1,
			'addons_price_before_calc' => 0.0,
		];
		$expected  = [
			'name'    => 'checkbox',
			'value'   => 'zero price',
			'display' => '',
		];

		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $addon, $cart_item ) );
	}

	public function test_get_item_data_returns_custom_price_data_correctly() {
		$addon     = [
			'name'       => 'Customer defined price',
			'value'      => '',
			'price'      => 42,
			'field_type' => 'custom_price',
			'price_type' => 'quantity_based',
		];
		$cart_item = [
			'addons'                   => [ $addon ],
			'data'                     => WC_Helper_Product::create_simple_product(),
			'quantity'                 => 1,
			'addons_price_before_calc' => 10,
		];
		$expected  = [
			'name'    => 'Customer defined price (<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#36;</span>42.00</bdi></span>)',
			'value'   => '',
			'display' => '',
		];

		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $addon, $cart_item ) );
	}

	public function test_get_item_data_returns_multiplier_price_data_correctly() {
		$price     = 42;
		$value     = 2;
		$addon     = [
			'name'       => 'Multiplier',
			'value'      => $value,
			'price'      => $price,
			'field_type' => 'input_multiplier',
			'price_type' => 'flat_fee',
		];
		$cart_item = [
			'addons'   => [ $addon ],
			'data'     => WC_Helper_Product::create_simple_product(),
			'quantity' => 1,
		];
		$expected  = [
			'name'    => 'Multiplier (<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#36;</span>42.00</bdi></span>)',
			'value'   => 2,
			'display' => '',
		];

		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_price' )
			->withConsecutive(
				[ $price, 'product' ],
				[ $price / $value, 'product' ]
			)
			->willReturn(
				$price,
				$price / $value
			);

		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $addon, $cart_item ) );
	}

	// Handles flat_fee and quantity_based.
	public function test_get_item_data_returns_price_data_correctly() {
		$price     = 42;
		$addon     = [
			'name'       => 'Checkbox',
			'value'      => 'Flat fee',
			'price'      => $price,
			'field_type' => 'checkbox',
			'price_type' => 'flat_fee',
		];
		$cart_item = [
			'addons'   => [ $addon ],
			'data'     => WC_Helper_Product::create_simple_product(),
			'quantity' => 1,
		];
		$expected  = [
			'name'    => 'Checkbox (<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#36;</span>42.00</bdi></span>)',
			'value'   => 'Flat fee',
			'display' => '',
		];

		$this->mock_multi_currency->method( 'get_price' )->with( $price, 'product' )->willReturn( $price );
		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $addon, $cart_item ) );
	}

	public function test_get_item_data_returns_percentage_price_data_correctly() {
		$addon     = [
			'name'       => 'Checkbox',
			'value'      => 'Percentage',
			'price'      => 50,
			'field_type' => 'checkbox',
			'price_type' => 'percentage_based',
		];
		$product   = WC_Helper_Product::create_simple_product();
		$cart_item = [
			'addons'                   => [ $addon ],
			'data'                     => $product,
			'product_id'               => $product->get_id(),
			'quantity'                 => 1,
			'addons_price_before_calc' => 10,
		];
		$expected  = [
			'name'    => 'Checkbox (<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#36;</span>5.00</bdi></span>)',
			'value'   => 'Percentage',
			'display' => '',
		];

		$this->mock_multi_currency->method( 'get_price' )->with( 10, 'product' )->willReturn( 10 );
		$this->assertSame( $expected, $this->compatibility->get_item_data( [], $addon, $cart_item ) );
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

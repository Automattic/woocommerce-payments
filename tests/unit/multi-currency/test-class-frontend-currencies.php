<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Currencies_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Currency;
use WCPay\MultiCurrency\Compatibility;
use WCPay\MultiCurrency\FrontendCurrencies;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * FrontendCurrencies unit tests.
 *
 * @group frontend-tests
 */
class WCPay_Multi_Currency_Frontend_Currencies_Tests extends WCPAY_UnitTestCase {
	/**
	 * WC_Payments_Localization_Service.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $localization_service;

	/**
	 * Mock Compatibility.
	 *
	 * @var Compatibility|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_compatibility;

	/**
	 * Mock MultiCurrency.
	 *
	 * @var MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * Mock Utils.
	 *
	 * @var Utils|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_utils;

	/**
	 * WC_Order object.
	 *
	 * @var WC_Order
	 */
	private $mock_order;

	/**
	 * FrontendCurrencies instance.
	 *
	 * @var FrontendCurrencies
	 */
	private $frontend_currencies;

	public function set_up() {
		parent::set_up();

		$this->localization_service = new WC_Payments_Localization_Service();
		$this->mock_compatibility   = $this->createMock( Compatibility::class );
		$this->mock_multi_currency  = $this->createMock( MultiCurrency::class );
		$this->mock_utils           = $this->createMock( Utils::class );
		$this->mock_order           = WC_Helper_Order::create_order();

		$this->mock_multi_currency
			->method( 'get_default_currency' )
			->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->frontend_currencies = new FrontendCurrencies( $this->mock_multi_currency, $this->localization_service, $this->mock_utils, $this->mock_compatibility );
		$this->frontend_currencies->init_hooks();
	}

	public function tear_down() {
		remove_all_filters( 'wcpay_multi_currency_currency_settings' );

		parent::tear_down();
	}

	/**
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_registers_woocommerce_filter( $filter, $function_name ) {
		$this->assertGreaterThan(
			500,
			has_filter( $filter, [ $this->frontend_currencies, $function_name ] ),
			"Filter '$filter' was not registered with '$function_name' with a priority higher than the default"
		);
	}

	public function woocommerce_filter_provider() {
		return [
			[ 'woocommerce_currency', 'get_woocommerce_currency' ],
			[ 'wc_get_price_decimals', 'get_price_decimals' ],
			[ 'wc_get_price_decimal_separator', 'get_price_decimal_separator' ],
			[ 'wc_get_price_thousand_separator', 'get_price_thousand_separator' ],
			[ 'woocommerce_price_format', 'get_woocommerce_price_format' ],
			[ 'woocommerce_cart_hash', 'add_currency_to_cart_hash' ],
			[ 'woocommerce_shipping_method_add_rate_args', 'fix_price_decimals_for_shipping_rates' ],
		];
	}

	public function test_get_woocommerce_currency_returns_selected_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency() );
	}

	public function test_get_woocommerce_currency_returns_store_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( true );

		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency() );
	}

	public function test_get_price_decimals_returns_num_decimals() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'BHD' ) );

		// We expect 3 decimal points here because BHD uses 3 decimal points, see
		// i18n/locale-info.php (or plugins/woocommerce/i18n/locale-info.php in WC Core).
		$this->assertEquals( 3, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	public function test_get_price_decimals_returns_num_decimals_for_order_currency() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->willReturn( true );
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_page_with_vars' )
			->willReturn( true );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->mock_order->set_currency( 'BHD' );
		$this->frontend_currencies->init_order_currency( $this->mock_order );

		// We expect 3 decimal points here because BHD uses 3 decimal points, see
		// i18n/locale-info.php (or plugins/woocommerce/i18n/locale-info.php in WC Core).
		$this->assertEquals( 3, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	public function test_get_price_decimals_returns_original_when_the_currency_is_same() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( 2, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	public function test_get_price_decimal_separator_returns_decimal_sep() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );

		// We expect a comma as the decimal separator here because that's the default EUR formatting, see
		// i18n/currency-info.php (or plugins/woocommerce/i18n/currency-info.php in WC Core).
		$this->assertEquals( ',', $this->frontend_currencies->get_price_decimal_separator( '.' ) );
	}

	public function test_get_price_decimal_separator_returns_decimal_sep_for_order_currency() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->willReturn( true );
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_page_with_vars' )
			->willReturn( true );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->mock_order->set_currency( 'EUR' );
		$this->frontend_currencies->init_order_currency( $this->mock_order );

		// We expect a comma as the decimal separator here because that's the default EUR formatting, see
		// i18n/currency-info.php (or plugins/woocommerce/i18n/currency-info.php in WC Core).
		$this->assertEquals( ',', $this->frontend_currencies->get_price_decimal_separator( '.' ) );
	}

	public function test_get_price_decimal_separator_returns_original_decimal_sep_when_the_currency_is_same() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( ',', $this->frontend_currencies->get_price_decimal_separator( ',' ) );
	}

	public function test_get_price_thousand_separator_returns_thousand_sep() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );

		// We expect a period as the thousand separator here because that's the default EUR formatting, see
		// i18n/currency-info.php (or plugins/woocommerce/i18n/currency-info.php in WC Core).
		$this->assertEquals( '.', $this->frontend_currencies->get_price_thousand_separator( ',' ) );
	}

	public function test_get_price_thousand_separator_returns_thousand_sep_for_order_currency() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->willReturn( true );
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_page_with_vars' )
			->willReturn( true );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->mock_order->set_currency( 'EUR' );
		$this->frontend_currencies->init_order_currency( $this->mock_order );

		// We expect a period as the thousand separator here because that's the default EUR formatting, see
		// i18n/currency-info.php (or plugins/woocommerce/i18n/currency-info.php in WC Core).
		$this->assertEquals( '.', $this->frontend_currencies->get_price_thousand_separator( ',' ) );
	}

	public function test_get_price_thousand_separator_returns_original_thousand_sep_when_the_currency_is_same() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( '.', $this->frontend_currencies->get_price_thousand_separator( '.' ) );
	}

	public function test_get_woocommerce_price_format_returns_format_for_currency_pos() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );

		// We expect right_space formatting here because that's the default EUR formatting, see
		// i18n/currency-info.php (or plugins/woocommerce/i18n/currency-info.php in WC Core).
		$this->assertEquals( '%2$s&nbsp;%1$s', $this->frontend_currencies->get_woocommerce_price_format( '%2$s%1$s' ) );
	}

	public function test_get_woocommerce_price_format_returns_format_for_order_currency_pos() {
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->willReturn( true );
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_page_with_vars' )
			->willReturn( true );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->mock_order->set_currency( 'EUR' );
		$this->frontend_currencies->selected_currency_changed();
		$this->frontend_currencies->init_order_currency( $this->mock_order );

		// We expect right_space formatting here because that's the default EUR formatting, see
		// i18n/currency-info.php (or plugins/woocommerce/i18n/currency-info.php in WC Core).
		$this->assertEquals( '%2$s&nbsp;%1$s', $this->frontend_currencies->get_woocommerce_price_format( '%2$s%1$s' ) );
	}

	public function test_get_woocommerce_price_format_returns_original_format_for_currency_pos_when_the_currency_is_same() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( '%2$s%1$s', $this->frontend_currencies->get_woocommerce_price_format( '%2$s%1$s' ) );
	}

	public function test_get_woocommerce_currency_pos_returns_currency_pos_for_selected_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );

		// The Cart and Checkout Blocks read the `woocommerce_currency_pos` option (via the Store API
		// CurrencyFormatter) rather than the `woocommerce_price_format` filter the shortcode uses, so
		// WCPay must also override the option to keep both paths aligned. EUR's default position is
		// right_space (see i18n/currency-info.php), not the store's setting.
		$this->assertEquals( 'right_space', $this->frontend_currencies->get_woocommerce_currency_pos( 'left' ) );
	}

	public function test_get_woocommerce_currency_pos_returns_original_pos_when_the_currency_is_same() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( 'left', $this->frontend_currencies->get_woocommerce_currency_pos( 'left' ) );
	}

	/**
	 * @dataProvider currency_format_provider
	 */
	public function test_get_woocommerce_price_format_outputs_right_format( $currency_pos, $expected_format ) {
		/** @var WC_Payments_Localization_Service $mock_localization_service */
		$mock_localization_service = $this->createMock( WC_Payments_Localization_Service::class );
		$mock_localization_service
			->method( 'get_currency_format' )
			->with( 'EUR' )
			->willReturn(
				[
					'currency_pos' => $currency_pos,
					'num_decimals' => 2,
				]
			);

		// We don't use the main object here because we need this test to use the mocked localization service, whereas
		// other tests can use the real localization service.
		$frontend_currencies = new FrontendCurrencies( $this->mock_multi_currency, $mock_localization_service, $this->mock_utils, $this->mock_compatibility );
		$frontend_currencies->init_hooks();

		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $mock_localization_service, 'EUR' ) );

		$this->assertEquals( $expected_format, $frontend_currencies->get_woocommerce_price_format( $currency_pos ) );
	}

	public function currency_format_provider() {
		return [
			[ '', '%1$s%2$s' ],
			[ 'random_value', '%1$s%2$s' ],
			[ 'left', '%1$s%2$s' ],
			[ 'right', '%2$s%1$s' ],
			[ 'left_space', '%1$s&nbsp;%2$s' ],
			[ 'right_space', '%2$s&nbsp;%1$s' ],
		];
	}

	public function test_add_currency_to_cart_hash_adds_currency_and_rate() {
		$current_currency = new Currency( $this->localization_service, 'GBP', 0.71 );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertSame(
			md5( 'cart_hashGBP0.71' ),
			$this->frontend_currencies->add_currency_to_cart_hash( 'cart_hash' )
		);
	}

	public function test_fix_price_decimals_for_shipping_rates() {
		$this->assertSame(
			[ 'price_decimals' => 2 ],
			$this->frontend_currencies->fix_price_decimals_for_shipping_rates( [ 'price_decimals' => 42 ], null )
		);
	}

	public function test_init_order_currency_returns_order_if_order_currency_not_null() {
		// Set the currency and then init the order_currency.
		$currency = 'EUR';
		$this->mock_order->set_currency( $currency );
		$this->frontend_currencies->init_order_currency( $this->mock_order );

		// Since the order_currency is already set, this should return what's passed, the full order.
		$this->assertSame( $this->mock_order, $this->frontend_currencies->init_order_currency( $this->mock_order ) );
	}

	/**
	 * @dataProvider empty_order_number_provider
	 */
	public function test_init_order_currency_returns_empty_order_numbers( $order_id ) {
		$this->assertSame( $order_id, $this->frontend_currencies->init_order_currency( $order_id ) );
	}

	public function empty_order_number_provider() {
		return [
			[ '' ],
			[ '0' ],
			[ false ],
			[ '2020' ],
		];
	}

	public function test_init_order_currency_returns_order_id() {
		$this->assertSame( $this->mock_order->get_id(), $this->frontend_currencies->init_order_currency( $this->mock_order ) );
	}

	/**
	 * @dataProvider provider_maybe_init_order_currency_from_order_total_prop
	 */
	public function test_maybe_init_order_currency_from_order_total_prop( $vars, $backtrace, $expected ) {
		// Arrange: Set the expected calls and/or returns for is_page_with_vars and is_call_in_backtrace within should_use_order_currency.
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_page_with_vars' )
			->willReturn( $vars );
		if ( $vars ) {
			$this->mock_utils
				->expects( $this->once() )
				->method( 'is_call_in_backtrace' )
				->willReturn( $backtrace );
		} else {
			$this->mock_utils
				->expects( $this->never() )
				->method( 'is_call_in_backtrace' );
		}

		// Arrange: Set the currency for the mock order.
		$this->mock_order->set_currency( 'EUR' );

		// Act: Call our method we're testing.
		$return = $this->frontend_currencies->maybe_init_order_currency_from_order_total_prop( 10.00, $this->mock_order );

		// Assert: Confirm the return value has not changed and that the expected order_currency is set.
		$this->assertEquals( 10.00, $return );
		$this->assertEquals( $expected, $this->frontend_currencies->get_order_currency() );
	}

	public function provider_maybe_init_order_currency_from_order_total_prop() {
		return [
			'return EUR'                        => [ true, true, 'EUR' ],
			'return null fail first backtrace'  => [ false, true, null ],
			'return null fail second backtrace' => [ true, false, null ],
		];
	}

	public function test_maybe_clear_order_currency_after_formatted_order_total_takes_no_action() {
		// Arrange: Set the expected calls and/or returns for is_page_with_vars and is_call_in_backtrace within should_use_order_currency.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_page_with_vars' );
		$this->mock_utils
			->expects( $this->never() )
			->method( 'is_call_in_backtrace' );

		// Act: Call our method we're testing.
		$return = $this->frontend_currencies->maybe_clear_order_currency_after_formatted_order_total( 10.00, $this->mock_order, '', false );

		// Assert: Confirm the return value has not changed and that the expected order_currency is set.
		$this->assertEquals( 10.00, $return );
		$this->assertEquals( null, $this->frontend_currencies->get_order_currency() );
	}

	public function rest_api_ensure_filter_vals_provider() {
		return [
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// [ $request_uri, $store_currency_args, $product_price_args ]
			[ '', [ false ], [ true ] ],
			[ '/wp-json/wc/v2/products/1', [ true ], [ false ] ],
		];
	}

	/**
	 * @dataProvider rest_api_ensure_filter_vals_provider
	 */
	public function test_rest_api_ensure_should_return_store_currency_and_should_convert_product_price_vals( $request_uri, $store_currency_args, $product_price_args ) {
		// Arrange.
		$original_request_uri   = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = $request_uri;

		$mccy = new WCPay\MultiCurrency\MultiCurrency(
			WC_Payments::get_settings_service(),
			WC_Payments::get_payments_api_client(),
			WC_Payments::get_account_service(),
			WC_Payments::get_localization_service(),
			WC_Payments::get_database_cache()
		);
		$mccy->init_hooks();

		$price        = 10.0;
		$mock_product = new WC_Product();
		$mock_product->set_price( $price );
		$mock_product->save();

		$fn_pass_param = function ( $value ) {
			return $value;
		};

		$spy_return_store_currency    = PHPUnit_Utils::function_spy();
		$should_return_store_currency = ( $spy_return_store_currency->computed_fn )( $fn_pass_param );
		add_filter( 'wcpay_multi_currency_should_return_store_currency', $should_return_store_currency, 999 );

		$spy_convert_product_price    = PHPUnit_Utils::function_spy();
		$should_convert_product_price = ( $spy_convert_product_price->computed_fn )( $fn_pass_param );
		add_filter( 'wcpay_multi_currency_should_convert_product_price', $should_convert_product_price, 999 );

		// Act.
		$price_html = $mock_product->get_price_html();

		// Assert.
		$this->assertEquals( $store_currency_args, ( $spy_return_store_currency->received_args )() );
		$this->assertEquals( $product_price_args, ( $spy_convert_product_price->received_args )() );

		// Clean up.
		remove_filter( 'wcpay_multi_currency_should_return_store_currency', $should_return_store_currency, 999 );
		remove_filter( 'wcpay_multi_currency_should_convert_product_price', $should_convert_product_price, 999 );
		$_SERVER['REQUEST_URI'] = $original_request_uri;
	}

	public function test_maybe_clear_order_currrency_after_formatted_order_total() {
		// Arrange: Set the expected calls and/or returns for is_page_with_vars and is_call_in_backtrace within should_use_order_currency.
		// Noting that the count is set to 2 due to maybe_init_order_currency_from_order_total_prop is called to set the order_currency.
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_page_with_vars' )
			->willReturn( true );
		$this->mock_utils
			->expects( $this->exactly( 2 ) )
			->method( 'is_call_in_backtrace' )
			->willReturn( true );

		// Arrange: Set the currency for the mock order.
		$this->mock_order->set_currency( 'EUR' );

		// Arrange: We call this to set order_currency since there is not a setter method for the property.
		$this->frontend_currencies->maybe_init_order_currency_from_order_total_prop( 10.00, $this->mock_order );

		// Assert: We want to make sure the order_currency is EUR before acting again.
		$this->assertEquals( 'EUR', $this->frontend_currencies->get_order_currency() );

		// Act: Call our method we're testing.
		$return = $this->frontend_currencies->maybe_clear_order_currency_after_formatted_order_total( 10.00, $this->mock_order, '', false );

		// Assert: Confirm the return value has not changed and that the order_currency is now null.
		$this->assertEquals( 10.00, $return );
		$this->assertEquals( null, $this->frontend_currencies->get_order_currency() );
	}

	/**
	 * REST clients (e.g. the WC Store API) must not be redirected when the currency
	 * is switched; a 302 would strip filter bounds from the URL and break the response.
	 *
	 * Sets `$_SERVER['REQUEST_URI']` to a Store API path so `WC()->is_rest_api_request()`
	 * detects the REST context the same way it would in production at `init:11`. The
	 * earlier version of this test defined `REST_REQUEST` manually, which simulated a
	 * state that never exists when the bug actually fires (the constant isn't defined
	 * until `parse_request`, several hooks later).
	 */
	public function test_clear_url_price_params_is_noop_on_rest_request() {
		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/products?currency=EUR&min_price=10&max_price=50';
		$_GET['min_price']      = '10';
		$_GET['max_price']      = '50';

		// If the guard fails, `wp_safe_redirect()` runs followed by `exit;`. Hook the
		// `wp_redirect` filter to throw so the redirect attempt surfaces as a test
		// failure instead of terminating the PHPUnit process.
		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new Exception( 'Unexpected redirect during REST request to: ' . $location );
			}
		);

		try {
			$this->frontend_currencies->clear_url_price_params();
			$this->addToAssertionCount( 1 );
		} finally {
			remove_all_filters( 'wp_redirect' );
			unset( $_GET['min_price'], $_GET['max_price'] );
			if ( null === $original_request_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $original_request_uri;
			}
		}
	}

	/**
	 * The `?rest_route=` URL shape is used by WordPress when pretty permalinks are
	 * disabled. `WC()->is_rest_api_request()` only matches `wp-json/` in REQUEST_URI,
	 * so the guard must also recognize `$_GET['rest_route']` to cover this case.
	 */
	public function test_clear_url_price_params_is_noop_on_rest_route_query_request() {
		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? null;
		$original_rest_route    = $_GET['rest_route'] ?? null;
		$_SERVER['REQUEST_URI'] = '/?rest_route=/wc/store/v1/products&currency=EUR&min_price=10&max_price=50';
		$_GET['rest_route']     = '/wc/store/v1/products';
		$_GET['min_price']      = '10';
		$_GET['max_price']      = '50';

		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new Exception( 'Unexpected redirect during ?rest_route= REST request to: ' . $location );
			}
		);

		try {
			$this->frontend_currencies->clear_url_price_params();
			$this->addToAssertionCount( 1 );
		} finally {
			remove_all_filters( 'wp_redirect' );
			unset( $_GET['min_price'], $_GET['max_price'] );
			if ( null === $original_rest_route ) {
				unset( $_GET['rest_route'] );
			} else {
				$_GET['rest_route'] = $original_rest_route;
			}
			if ( null === $original_request_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $original_request_uri;
			}
		}
	}

	/**
	 * Browser navigation (non-REST request) MUST still redirect to strip stale
	 * `min_price`/`max_price` query params after a currency switch. Captures the
	 * intended Location header via the `wp_redirect` filter, then throws to escape
	 * the `exit;` that follows `wp_safe_redirect()` in `clear_url_price_params()`.
	 */
	public function test_clear_url_price_params_redirects_on_browser_request() {
		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/shop/?currency=EUR&min_price=10&max_price=50';
		$_GET['min_price']      = '10';
		$_GET['max_price']      = '50';

		$captured_url = null;

		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$captured_url ) {
				$captured_url = $location;
				throw new Exception( 'redirect captured' );
			}
		);

		try {
			$this->frontend_currencies->clear_url_price_params();
			$this->fail( 'Expected clear_url_price_params() to issue a redirect on a browser request.' );
		} catch ( Exception $e ) {
			$this->assertSame( 'redirect captured', $e->getMessage(), 'Unexpected exception thrown.' );
			$this->assertIsString( $captured_url, 'Expected a redirect URL to be captured.' );
			$this->assertStringNotContainsString( 'min_price', $captured_url, 'min_price should have been stripped.' );
			$this->assertStringNotContainsString( 'max_price', $captured_url, 'max_price should have been stripped.' );
			$this->assertStringContainsString( 'currency=EUR', $captured_url, 'Other query args (currency) should be preserved.' );
		} finally {
			remove_all_filters( 'wp_redirect' );
			unset( $_GET['min_price'], $_GET['max_price'] );
			if ( null === $original_request_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $original_request_uri;
			}
		}
	}
}

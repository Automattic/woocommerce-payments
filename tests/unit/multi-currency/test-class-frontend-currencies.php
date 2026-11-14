<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Currencies_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;
use WCPay\Constants\Currency_Code;
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

	/**
	 * The global query vars before each test, restored in tear_down().
	 *
	 * @var array
	 */
	private $original_query_vars;

	/**
	 * The filter use_hpos_or_skip() adds to turn on HPOS, removed in tear_down().
	 *
	 * @var callable|null
	 */
	private $use_hpos;

	public function set_up() {
		parent::set_up();
		WCPay_Test_Request_Context::set( 'frontend' );

		global $wp;
		$this->original_query_vars = $wp->query_vars;

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
		WCPay_Test_Request_Context::reset();
		\WCPay_Test_Request_Context::set( null );
		remove_all_filters( 'wcpay_multi_currency_currency_settings' );

		if ( null !== $this->use_hpos ) {
			remove_filter( 'pre_option_' . CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION, $this->use_hpos );
			$this->use_hpos = null;
		}

		global $wp;
		$wp->query_vars = $this->original_query_vars;

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

	/**
	 * @dataProvider cart_action_provider
	 */
	public function test_discards_the_cached_currency_on_cart_actions( $action ) {
		$this->assertSame(
			0,
			has_action( $action, [ $this->frontend_currencies, 'selected_currency_changed' ] ),
			"Action '$action' does not discard the cached currency before other callbacks run"
		);
	}

	public function cart_action_provider() {
		return [
			[ 'woocommerce_cart_loaded_from_session' ],
			[ 'woocommerce_cart_updated' ],
		];
	}

	public function test_get_woocommerce_currency_re_resolves_after_the_cart_is_updated() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_explicit_selected_currency' )
			->willReturnOnConsecutiveCalls( new Currency( $this->localization_service, Currency_Code::JAPANESE_YEN ), null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'JPY', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );

		do_action( 'woocommerce_cart_updated' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment

		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );
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
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( new Currency( $this->localization_service, Currency_Code::EURO ) );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency() );
	}

	public function test_get_woocommerce_currency_returns_store_currency() {
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( true );

		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
	}

	public function test_get_woocommerce_currency_passes_through_filtered_currency_when_not_switching() {
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'GBP', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
	}

	public function test_get_woocommerce_currency_returns_store_currency_when_not_switching_and_nothing_was_filtered() {
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency() );
	}

	public function test_get_woocommerce_currency_overrides_filtered_currency_when_switching() {
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( new Currency( $this->localization_service, Currency_Code::EURO ) );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
	}

	public function test_woocommerce_currency_filter_keeps_value_from_earlier_filters_when_not_switching() {
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		// A third-party currency switcher hooked below Multi-Currency's priority.
		add_filter( 'woocommerce_currency', fn() => 'GBP', 5 );

		// Run the filter chain as WooCommerce does, starting from the store currency.
		$this->assertSame( 'GBP', apply_filters( 'woocommerce_currency', 'USD' ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.HookCommentWrongStyle
	}

	public function test_get_woocommerce_currency_passes_through_a_second_filtered_currency_when_idle() {
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_explicit_selected_currency' )
			->willReturn( null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'GBP', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
		$this->assertSame( 'CAD', $this->frontend_currencies->get_woocommerce_currency( 'CAD' ) );
	}

	public function test_get_woocommerce_currency_reuses_an_explicit_selection_on_a_second_call() {
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_explicit_selected_currency' )
			->willReturn( new Currency( $this->localization_service, Currency_Code::EURO ) );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency( 'CAD' ) );
	}

	public function test_get_woocommerce_currency_re_resolves_after_selected_currency_changed() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_explicit_selected_currency' )
			->willReturnOnConsecutiveCalls(
				new Currency( $this->localization_service, Currency_Code::EURO ),
				new Currency( $this->localization_service, Currency_Code::POUND_STERLING )
			);
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );

		$this->frontend_currencies->selected_currency_changed();

		$this->assertSame( 'GBP', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );
	}

	public function test_get_woocommerce_currency_re_resolves_after_the_current_user_is_replaced() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_explicit_selected_currency' )
			->willReturnOnConsecutiveCalls( new Currency( $this->localization_service, Currency_Code::JAPANESE_YEN ), null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertSame( 'JPY', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );

		wp_set_current_user( 0 );
		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );
	}

	public function test_get_woocommerce_currency_re_resolves_after_the_session_is_replaced() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_explicit_selected_currency' )
			->willReturnOnConsecutiveCalls( new Currency( $this->localization_service, Currency_Code::JAPANESE_YEN ), null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'JPY', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );

		$original_session = WC()->session;
		WC()->session     = clone $original_session;
		$currency         = $this->frontend_currencies->get_woocommerce_currency( 'USD' );
		WC()->session     = $original_session;

		$this->assertSame( 'USD', $currency );
	}

	public function test_get_price_decimals_re_resolves_after_the_current_user_is_replaced() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_selected_currency' )
			->willReturnOnConsecutiveCalls(
				new Currency( $this->localization_service, Currency_Code::JAPANESE_YEN ),
				new Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR )
			);

		wp_set_current_user( self::factory()->user->create() );
		$this->assertSame( 0, $this->frontend_currencies->get_price_decimals( 2 ) );

		wp_set_current_user( 0 );
		$this->assertSame( 2, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	public function test_get_price_decimals_returns_num_decimals() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'BHD' ) );

		// We expect 3 decimal points here because BHD uses 3 decimal points, see
		// i18n/locale-info.php (or plugins/woocommerce/i18n/locale-info.php in WC Core).
		$this->assertEquals( 3, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	/**
	 * Replaces the set_up instance's currency hooks with a FrontendCurrencies built on a real Utils, so
	 * is_call_in_backtrace() inspects the genuine call stack rather than a mock. Returns the instance.
	 */
	private function reinit_with_real_utils(): FrontendCurrencies {
		$hooks = [
			'woocommerce_currency',
			'wc_get_price_decimals',
			'wc_get_price_decimal_separator',
			'wc_get_price_thousand_separator',
			'woocommerce_price_format',
			'option_woocommerce_currency_pos',
			'woocommerce_order_get_total',
			'woocommerce_get_formatted_order_total',
			'woocommerce_order_class',
			'woocommerce_order_before_calculate_totals',
			'woocommerce_order_after_calculate_totals',
		];
		foreach ( $hooks as $hook ) {
			remove_all_filters( $hook );
		}

		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn(
				[
					'USD' => new Currency( $this->localization_service, 'USD' ),
					'JPY' => new Currency( $this->localization_service, 'JPY' ),
				]
			);

		$frontend_currencies = new FrontendCurrencies( $this->mock_multi_currency, $this->localization_service, new Utils(), $this->mock_compatibility );
		$frontend_currencies->init_hooks();
		return $frontend_currencies;
	}

	/**
	 * Turns on HPOS for the current test and skips the test when WooCommerce does not use it, so a test that
	 * relies on it does not pass without exercising anything. Older WooCommerce (e.g. 7.6) uses HPOS, and so
	 * the order cache, only once the feature is visible.
	 */
	private function use_hpos_or_skip() {
		$this->use_hpos = function () {
			return 'yes';
		};
		add_filter( 'pre_option_' . CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION, $this->use_hpos );

		if ( ! OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$this->markTestSkipped( 'WooCommerce is not using HPOS.' );
		}
	}

	/**
	 * A single-order query var pointing at a different order's id (order-received=<another order's id>)
	 * must not pull that order's currency into the load, even with the store currency selected.
	 */
	public function test_loading_with_an_order_received_var_for_another_order_keeps_the_order_decimals() {
		// Registers the currency hooks on a real Utils; the selection stays the store currency here, so the
		// returned instance needs no further setup.
		$this->reinit_with_real_utils();

		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$other_order = WC_Helper_Order::create_order();
		$other_order->set_currency( 'JPY' );
		$other_order->save();

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( '10.49' );
		$order->save();
		$order_id = $order->get_id();

		global $wp;
		$wp->query_vars['order-received'] = $other_order->get_id();

		wp_cache_flush();
		$this->assertSame( '10.49', wc_get_order( $order_id )->get_total() );
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

	public function provider_maybe_init_order_currency_from_order_total_prop() {
		return [
			'return EUR'                        => [ true, true, 'EUR' ],
			'return null fail first backtrace'  => [ false, true, null ],
			'return null fail second backtrace' => [ true, false, null ],
		];
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
		$mccy                   = new WCPay\MultiCurrency\MultiCurrency(
			WC_Payments::get_settings_service(),
			WC_Payments::get_payments_api_client(),
			WC_Payments::get_account_service(),
			WC_Payments::get_localization_service(),
			WC_Payments::get_database_cache()
		);
		$mccy->init_hooks();

		// A REST route decides at route time, once matched.
		$rest_request = new WP_REST_Request( 'GET', '/wc/v2/products/1' );
		if ( false !== strpos( $request_uri, '/wp-json/' ) ) {
			$mccy->enter_rest_route( null, [], $rest_request );
		}

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
		$mock_product->get_price_html();

		// Assert.
		$this->assertEquals( $store_currency_args, ( $spy_return_store_currency->received_args )() );
		$this->assertEquals( $product_price_args, ( $spy_convert_product_price->received_args )() );

		// Clean up.
		WCPay\MultiCurrency\Utils::leave_rest_route( $rest_request );
		remove_filter( 'wcpay_multi_currency_should_return_store_currency', $should_return_store_currency, 999 );
		remove_filter( 'wcpay_multi_currency_should_convert_product_price', $should_convert_product_price, 999 );
		$_SERVER['REQUEST_URI'] = $original_request_uri;
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

	/**
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_registers_woocommerce_filter_active_currency( $filter, $function_name ) {
		$this->assertGreaterThan(
			500,
			has_filter( $filter, [ $this->frontend_currencies, $function_name ] ),
			"Filter '$filter' was not registered with '$function_name' with a priority higher than the default"
		);
	}

	/**
	 * @dataProvider cart_action_provider
	 */
	public function test_discards_the_cached_currency_on_cart_actions_active_currency( $action ) {
		$this->assertSame(
			0,
			has_action( $action, [ $this->frontend_currencies, 'selected_currency_changed' ] ),
			"Action '$action' does not discard the cached currency before other callbacks run"
		);
	}

	public function cart_action_provider_active_currency() {
		return [
			[ 'woocommerce_cart_loaded_from_session' ],
			[ 'woocommerce_cart_updated' ],
		];
	}

	public function test_get_woocommerce_currency_re_resolves_after_the_cart_is_updated_active_currency() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_explicit_selected_currency' )
			->willReturnOnConsecutiveCalls( new Currency( $this->localization_service, Currency_Code::JAPANESE_YEN ), null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'JPY', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );

		do_action( 'woocommerce_cart_updated' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment

		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );
	}

	public function woocommerce_filter_provider_active_currency() {
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

	public function test_get_woocommerce_currency_returns_selected_currency_active_currency() {
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( new Currency( $this->localization_service, Currency_Code::EURO ) );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency() );
	}

	public function test_get_woocommerce_currency_returns_store_currency_active_currency() {
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( true );

		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
	}

	public function test_get_woocommerce_currency_passes_through_filtered_currency_when_not_switching_active_currency() {
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'GBP', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
	}

	public function test_get_woocommerce_currency_returns_store_currency_when_not_switching_and_nothing_was_filtered_active_currency() {
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency() );
	}

	public function test_get_woocommerce_currency_overrides_filtered_currency_when_switching_active_currency() {
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( new Currency( $this->localization_service, Currency_Code::EURO ) );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
	}

	public function test_woocommerce_currency_filter_keeps_value_from_earlier_filters_when_not_switching_active_currency() {
		$this->mock_multi_currency->method( 'get_explicit_selected_currency' )->willReturn( null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		// A third-party currency switcher hooked below Multi-Currency's priority.
		add_filter( 'woocommerce_currency', fn() => 'GBP', 5 );

		// Run the filter chain as WooCommerce does, starting from the store currency.
		$this->assertSame( 'GBP', apply_filters( 'woocommerce_currency', 'USD' ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.HookCommentWrongStyle
	}

	public function test_get_woocommerce_currency_passes_through_a_second_filtered_currency_when_idle_active_currency() {
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_explicit_selected_currency' )
			->willReturn( null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'GBP', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
		$this->assertSame( 'CAD', $this->frontend_currencies->get_woocommerce_currency( 'CAD' ) );
	}

	public function test_get_woocommerce_currency_reuses_an_explicit_selection_on_a_second_call_active_currency() {
		$this->mock_multi_currency
			->expects( $this->once() )
			->method( 'get_explicit_selected_currency' )
			->willReturn( new Currency( $this->localization_service, Currency_Code::EURO ) );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency( 'GBP' ) );
		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency( 'CAD' ) );
	}

	public function test_get_woocommerce_currency_re_resolves_after_selected_currency_changed_active_currency() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_explicit_selected_currency' )
			->willReturnOnConsecutiveCalls(
				new Currency( $this->localization_service, Currency_Code::EURO ),
				new Currency( $this->localization_service, Currency_Code::POUND_STERLING )
			);
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'EUR', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );

		$this->frontend_currencies->selected_currency_changed();

		$this->assertSame( 'GBP', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );
	}

	public function test_get_woocommerce_currency_re_resolves_after_the_current_user_is_replaced_active_currency() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_explicit_selected_currency' )
			->willReturnOnConsecutiveCalls( new Currency( $this->localization_service, Currency_Code::JAPANESE_YEN ), null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertSame( 'JPY', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );

		wp_set_current_user( 0 );
		$this->assertSame( 'USD', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );
	}

	public function test_get_woocommerce_currency_re_resolves_after_the_session_is_replaced_active_currency() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_explicit_selected_currency' )
			->willReturnOnConsecutiveCalls( new Currency( $this->localization_service, Currency_Code::JAPANESE_YEN ), null );
		$this->mock_compatibility->method( 'should_return_store_currency' )->willReturn( false );

		$this->assertSame( 'JPY', $this->frontend_currencies->get_woocommerce_currency( 'USD' ) );

		$original_session = WC()->session;
		WC()->session     = clone $original_session;
		$currency         = $this->frontend_currencies->get_woocommerce_currency( 'USD' );
		WC()->session     = $original_session;

		$this->assertSame( 'USD', $currency );
	}

	public function test_get_price_decimals_re_resolves_after_the_current_user_is_replaced_active_currency() {
		$this->mock_multi_currency
			->expects( $this->exactly( 2 ) )
			->method( 'get_selected_currency' )
			->willReturnOnConsecutiveCalls(
				new Currency( $this->localization_service, Currency_Code::JAPANESE_YEN ),
				new Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR )
			);

		wp_set_current_user( self::factory()->user->create() );
		$this->assertSame( 0, $this->frontend_currencies->get_price_decimals( 2 ) );

		wp_set_current_user( 0 );
		$this->assertSame( 2, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	public function test_get_price_decimals_returns_num_decimals_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'BHD' ) );

		// We expect 3 decimal points here because BHD uses 3 decimal points, see
		// i18n/locale-info.php (or plugins/woocommerce/i18n/locale-info.php in WC Core).
		$this->assertEquals( 3, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	/**
	 * Turns on HPOS for the current test and skips the test when WooCommerce does not use it, so a test that
	 * relies on it does not pass without exercising anything. Older WooCommerce (e.g. 7.6) uses HPOS, and so
	 * the order cache, only once the feature is visible.
	 */
	private function use_hpos_or_skip_active_currency() {
		$this->use_hpos = function () {
			return 'yes';
		};
		add_filter( 'pre_option_' . CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION, $this->use_hpos );

		if ( ! OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$this->markTestSkipped( 'WooCommerce is not using HPOS.' );
		}
	}

	public function test_get_price_decimals_uses_the_active_currency_over_the_shopper_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'JPY' ) );

		// BHD has 3 decimals; the active currency passed by WooCommerce wins over the shopper's JPY.
		$this->assertSame( 3, $this->frontend_currencies->get_price_decimals( 2, 'BHD' ) );
	}

	public function test_get_price_decimals_keeps_decimals_when_the_active_currency_is_the_store_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'JPY' ) );

		$this->assertSame( 2, $this->frontend_currencies->get_price_decimals( 2, 'USD' ) );
	}

	public function test_get_price_decimals_uses_the_active_currency_decimals_for_jpy() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertSame( 0, $this->frontend_currencies->get_price_decimals( 2, 'JPY' ) );
		$this->assertSame( 2, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	public function test_get_price_decimal_separator_uses_the_active_currency_when_one_is_passed() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( ',', $this->frontend_currencies->get_price_decimal_separator( '.', 'EUR' ) );
	}

	public function test_get_price_thousand_separator_uses_the_active_currency_when_one_is_passed() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( '.', $this->frontend_currencies->get_price_thousand_separator( ',', 'EUR' ) );
	}

	public function test_get_woocommerce_price_format_uses_the_active_currency_when_one_is_passed() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( '%2$s&nbsp;%1$s', $this->frontend_currencies->get_woocommerce_price_format( '%2$s%1$s', 'left', 'EUR' ) );
	}

	public function test_get_price_decimals_returns_original_when_the_currency_is_same_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( 2, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	public function test_get_price_decimal_separator_returns_decimal_sep_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );

		// We expect a comma as the decimal separator here because that's the default EUR formatting, see
		// i18n/currency-info.php (or plugins/woocommerce/i18n/currency-info.php in WC Core).
		$this->assertEquals( ',', $this->frontend_currencies->get_price_decimal_separator( '.' ) );
	}

	public function test_get_price_decimal_separator_returns_original_decimal_sep_when_the_currency_is_same_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( ',', $this->frontend_currencies->get_price_decimal_separator( ',' ) );
	}

	public function test_get_price_thousand_separator_returns_thousand_sep_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );

		// We expect a period as the thousand separator here because that's the default EUR formatting, see
		// i18n/currency-info.php (or plugins/woocommerce/i18n/currency-info.php in WC Core).
		$this->assertEquals( '.', $this->frontend_currencies->get_price_thousand_separator( ',' ) );
	}

	public function test_get_price_thousand_separator_returns_original_thousand_sep_when_the_currency_is_same_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( '.', $this->frontend_currencies->get_price_thousand_separator( '.' ) );
	}

	public function test_get_woocommerce_price_format_returns_format_for_currency_pos_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );

		// We expect right_space formatting here because that's the default EUR formatting, see
		// i18n/currency-info.php (or plugins/woocommerce/i18n/currency-info.php in WC Core).
		$this->assertEquals( '%2$s&nbsp;%1$s', $this->frontend_currencies->get_woocommerce_price_format( '%2$s%1$s' ) );
	}

	public function test_get_woocommerce_price_format_returns_original_format_for_currency_pos_when_the_currency_is_same_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( '%2$s%1$s', $this->frontend_currencies->get_woocommerce_price_format( '%2$s%1$s' ) );
	}

	public function test_get_woocommerce_currency_pos_returns_currency_pos_for_selected_currency_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'EUR' ) );

		// The Cart and Checkout Blocks read the `woocommerce_currency_pos` option (via the Store API
		// CurrencyFormatter) rather than the `woocommerce_price_format` filter the shortcode uses, so
		// WCPay must also override the option to keep both paths aligned. EUR's default position is
		// right_space (see i18n/currency-info.php), not the store's setting.
		$this->assertEquals( 'right_space', $this->frontend_currencies->get_woocommerce_currency_pos( 'left' ) );
	}

	public function test_get_woocommerce_currency_pos_returns_original_pos_when_the_currency_is_same_active_currency() {
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'USD' ) );

		$this->assertEquals( 'left', $this->frontend_currencies->get_woocommerce_currency_pos( 'left' ) );
	}

	/**
	 * @dataProvider currency_format_provider
	 */
	public function test_get_woocommerce_price_format_outputs_right_format_active_currency( $currency_pos, $expected_format ) {
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

	public function currency_format_provider_active_currency() {
		return [
			[ '', '%1$s%2$s' ],
			[ 'random_value', '%1$s%2$s' ],
			[ 'left', '%1$s%2$s' ],
			[ 'right', '%2$s%1$s' ],
			[ 'left_space', '%1$s&nbsp;%2$s' ],
			[ 'right_space', '%2$s&nbsp;%1$s' ],
		];
	}

	public function test_add_currency_to_cart_hash_adds_currency_and_rate_active_currency() {
		$current_currency = new Currency( $this->localization_service, 'GBP', 0.71 );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertSame(
			md5( 'cart_hashGBP0.71' ),
			$this->frontend_currencies->add_currency_to_cart_hash( 'cart_hash' )
		);
	}

	public function test_fix_price_decimals_for_shipping_rates_active_currency() {
		$this->assertSame(
			[ 'price_decimals' => 2 ],
			$this->frontend_currencies->fix_price_decimals_for_shipping_rates( [ 'price_decimals' => 42 ], null )
		);
	}

	public function rest_api_ensure_filter_vals_provider_active_currency() {
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
	public function test_rest_api_ensure_should_return_store_currency_and_should_convert_product_price_vals_active_currency( $request_uri, $store_currency_args, $product_price_args ) {
		// Arrange.
		$original_request_uri   = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = $request_uri;
		$mccy                   = new WCPay\MultiCurrency\MultiCurrency(
			WC_Payments::get_settings_service(),
			WC_Payments::get_payments_api_client(),
			WC_Payments::get_account_service(),
			WC_Payments::get_localization_service(),
			WC_Payments::get_database_cache()
		);
		$mccy->init_hooks();

		// A REST route decides at route time, once matched.
		$rest_request = new WP_REST_Request( 'GET', '/wc/v2/products/1' );
		if ( false !== strpos( $request_uri, '/wp-json/' ) ) {
			$mccy->enter_rest_route( null, [], $rest_request );
		}

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
		$mock_product->get_price_html();

		// Assert.
		$this->assertEquals( $store_currency_args, ( $spy_return_store_currency->received_args )() );
		$this->assertEquals( $product_price_args, ( $spy_convert_product_price->received_args )() );

		// Clean up.
		WCPay\MultiCurrency\Utils::leave_rest_route( $rest_request );
		remove_filter( 'wcpay_multi_currency_should_return_store_currency', $should_return_store_currency, 999 );
		remove_filter( 'wcpay_multi_currency_should_convert_product_price', $should_convert_product_price, 999 );
		$_SERVER['REQUEST_URI'] = $original_request_uri;
	}

	/**
	 * Browser navigation (non-REST request) MUST still redirect to strip stale
	 * `min_price`/`max_price` query params after a currency switch. Captures the
	 * intended Location header via the `wp_redirect` filter, then throws to escape
	 * the `exit;` that follows `wp_safe_redirect()` in `clear_url_price_params()`.
	 */
	public function test_clear_url_price_params_redirects_on_browser_request_active_currency() {
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

	/**
	 * Both FrontendCurrencies implementations.
	 *
	 * @return array
	 */
	public function frontend_currencies_classes(): array {
		return [
			'frontend currencies' => [ FrontendCurrencies::class ],
		];
	}

	/**
	 * Builds an implementation with a USD store and the given shopper currency.
	 *
	 * @param string $class_name            The FrontendCurrencies class to build.
	 * @param string $shopper_currency_code The shopper's selected currency code.
	 *
	 * @return FrontendCurrencies
	 */
	private function make_frontend_currencies( string $class_name, string $shopper_currency_code ): FrontendCurrencies {
		$localization_service = new WC_Payments_Localization_Service();

		$mock_multi_currency = $this->createMock( MultiCurrency::class );
		$mock_multi_currency->method( 'get_default_currency' )->willReturn( new Currency( $localization_service, 'USD' ) );
		$mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $localization_service, $shopper_currency_code ) );

		return new $class_name( $mock_multi_currency, $localization_service, $this->createMock( Utils::class ), $this->createMock( Compatibility::class ) );
	}

	/**
	 * With the store currency shopping, both implementations return WooCommerce's values untouched.
	 *
	 * @dataProvider frontend_currencies_classes
	 *
	 * @param string $class_name The FrontendCurrencies class under test.
	 */
	public function test_store_currency_keeps_woocommerce_values( string $class_name ) {
		$frontend_currencies = $this->make_frontend_currencies( $class_name, 'USD' );

		$this->assertSame( 2, $frontend_currencies->get_price_decimals( 2 ) );
		$this->assertSame( '.', $frontend_currencies->get_price_decimal_separator( '.' ) );
		$this->assertSame( ',', $frontend_currencies->get_price_thousand_separator( ',' ) );
		$this->assertSame( '%1$s%2$s', $frontend_currencies->get_woocommerce_price_format( '%1$s%2$s' ) );
	}

	/**
	 * With a non-store shopper currency and no active currency, both implementations format with that currency.
	 */
	public function test_shopper_currency_formatting_matches_between_implementations() {
		$legacy = $this->make_frontend_currencies( FrontendCurrencies::class, 'JPY' );
		$active = $this->make_frontend_currencies( FrontendCurrencies::class, 'JPY' );

		$this->assertSame( 0, $active->get_price_decimals( 2 ) );
		$this->assertSame( $legacy->get_price_decimals( 2 ), $active->get_price_decimals( 2 ) );
		$this->assertSame( $legacy->get_price_decimal_separator( '.' ), $active->get_price_decimal_separator( '.' ) );
		$this->assertSame( $legacy->get_price_thousand_separator( ',' ), $active->get_price_thousand_separator( ',' ) );
		$this->assertSame( $legacy->get_woocommerce_price_format( '%1$s%2$s' ), $active->get_woocommerce_price_format( '%1$s%2$s' ) );
		$this->assertSame( $legacy->get_woocommerce_currency_pos( 'left' ), $active->get_woocommerce_currency_pos( 'left' ) );
	}

	/**
	 * An active currency passed by WooCommerce wins over the shopper's currency on the active implementation.
	 */
	public function test_active_currency_argument_overrides_shopper_currency() {
		$active = $this->make_frontend_currencies( FrontendCurrencies::class, 'JPY' );

		$this->assertSame( 2, $active->get_price_decimals( 2, 'USD' ) );
		$this->assertSame( 0, $active->get_price_decimals( 2, 'JPY' ) );
	}
}

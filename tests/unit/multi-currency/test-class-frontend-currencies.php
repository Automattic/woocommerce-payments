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
		$this->assertSame( 'GBP', apply_filters( 'woocommerce_currency', 'USD' ) );
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

	/**
	 * Block-based order confirmation pages render the order total without the shortcode classes, so
	 * the order currency is still used there. Loading the order from the data store is covered
	 * separately (see the load tests below). See WOOPMNT-3978.
	 */
	public function test_single_order_pages_use_order_currency_while_rendering_blocks() {
		global $wp;
		$wp->query_vars['order-received'] = $this->mock_order->get_id();

		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_page_with_vars' )
			->willReturn( true );
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with(
				$this->callback(
					function ( $calls ) {
						return in_array( 'Automattic\WooCommerce\Blocks\BlockTypes\OrderConfirmation\AbstractOrderConfirmationBlock->render', $calls, true )
							&& in_array( 'WC_Shortcode_Checkout::order_received', $calls, true )
							&& ! in_array( 'WC_Order_Factory::get_order', $calls, true );
					}
				)
			)
			->willReturn( true );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'ISK' ) );

		$this->mock_order->set_currency( 'USD' );
		$this->frontend_currencies->init_order_currency( $this->mock_order );

		// USD has 2 decimals; the selected ISK has none.
		$this->assertEquals( 2, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	/**
	 * The WooCommerce methods above are matched by name in the backtrace, so a WooCommerce change to
	 * them would silently stop the match. This fails instead.
	 */
	public function test_matched_woocommerce_methods_keep_their_backtrace_names() {
		// Static, so PHP reports it as WC_Order_Factory::get_order in the backtrace however it's called.
		$this->assertTrue( ( new ReflectionMethod( 'WC_Order_Factory', 'get_order' ) )->isStatic() );
		$this->assertTrue( ( new ReflectionMethod( 'WC_Order_Factory', 'get_orders' ) )->isStatic() );

		// calculate_totals is inherited from WC_Abstract_Order, so PHP reports it as
		// WC_Abstract_Order->calculate_totals in the backtrace for any order type.
		$this->assertSame( 'WC_Abstract_Order', ( new ReflectionMethod( 'WC_Order', 'calculate_totals' ) )->getDeclaringClass()->getName() );

		// The CI matrix still runs WooCommerce 7.6, which predates the order confirmation blocks.
		if ( version_compare( WC_VERSION, '9.0.0', '<' ) ) {
			$this->markTestSkipped( 'The order confirmation blocks are not in this WooCommerce version.' );
		}

		$this->assertTrue( method_exists( 'Automattic\WooCommerce\Blocks\BlockTypes\OrderConfirmation\AbstractOrderConfirmationBlock', 'render' ) );
	}

	/**
	 * Prices on a single-order page that are not the order's, like the mini-cart, keep the
	 * selected currency. See WOOPMNT-3978.
	 */
	public function test_other_prices_on_single_order_pages_keep_the_selected_currency() {
		global $wp;
		$wp->query_vars['view-order'] = $this->mock_order->get_id();

		$this->mock_utils
			->method( 'is_page_with_vars' )
			->willReturn( true );
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturn( false );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'ISK' ) );

		$this->mock_order->set_currency( 'USD' );
		$this->frontend_currencies->init_order_currency( $this->mock_order );

		$this->assertEquals( 0, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	/**
	 * The orders list has no single order, so loading an order there must not switch to an order
	 * currency.
	 */
	public function test_orders_list_page_does_not_use_order_currency_while_loading_orders() {
		global $wp;
		$wp->query_vars['orders'] = 1;

		$this->mock_utils
			->method( 'is_page_with_vars' )
			->willReturn( true );
		$this->mock_utils
			->expects( $this->once() )
			->method( 'is_call_in_backtrace' )
			->with(
				$this->callback(
					function ( $calls ) {
						return ! in_array( 'WC_Order_Factory::get_order', $calls, true );
					}
				)
			)
			->willReturn( false );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'ISK' ) );

		$this->assertEquals( 0, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	/**
	 * While an order is read from the data store its own currency is not available here, so the
	 * decimals must not fall below any enabled currency. A selected zero-decimal currency must not
	 * reduce the precision a USD order needs.
	 */
	public function test_get_price_decimals_uses_max_enabled_decimals_while_loading_an_order() {
		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn(
				[
					'USD' => new Currency( $this->localization_service, 'USD' ),
					'JPY' => new Currency( $this->localization_service, 'JPY' ),
				]
			);
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->with( [ 'WC_Order_Factory::get_order', 'WC_Order_Factory::get_orders' ] )
			->willReturn( true );

		// WC_Order_Factory flags a load right before it reads the order.
		$this->assertSame( 'WC_Order', $this->frontend_currencies->flag_order_loading( 'WC_Order' ) );

		// The selected JPY would give 0; the load keeps the 2 that USD needs.
		$this->assertEquals( 2, $this->frontend_currencies->get_price_decimals( 0 ) );
	}

	/**
	 * A single-order page names the order through the URL, so a historical order in a currency with
	 * more decimals than any enabled currency keeps its own precision while loading instead of being
	 * rounded to the enabled maximum (for example after that currency is disabled).
	 */
	public function test_loading_a_single_order_page_keeps_the_order_currency_precision() {
		global $wp;
		$this->mock_order->set_currency( 'BHD' );
		$this->mock_order->save();
		$wp->query_vars['order-pay'] = $this->mock_order->get_id();

		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn(
				[
					'USD' => new Currency( $this->localization_service, 'USD' ),
					'JPY' => new Currency( $this->localization_service, 'JPY' ),
				]
			);
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturn( true );

		// WC_Order_Factory flags a load right before it reads the order.
		$this->frontend_currencies->flag_order_loading( 'WC_Order' );

		// BHD has 3 decimals; the enabled USD/JPY top out at 2. The order's own currency must win, so a
		// stored 10.499 is not rounded to 10.50 on load.
		$this->assertEquals( 3, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	/**
	 * The load flag is cleared once the factory is no longer loading, so later prices on the same
	 * request use the selected currency again.
	 */
	public function test_is_loading_order_only_applies_while_the_factory_is_loading() {
		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn( [ 'USD' => new Currency( $this->localization_service, 'USD' ) ] );
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturn( new Currency( $this->localization_service, 'JPY' ) );
		$this->mock_utils
			->method( 'is_page_with_vars' )
			->willReturn( false );

		// Drive the backtrace result by state, not by call count, so the test does not depend on how
		// many times get_price_decimals() consults the backtrace.
		$factory_in_backtrace = true;
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturnCallback(
				function () use ( &$factory_in_backtrace ) {
					return $factory_in_backtrace;
				}
			);

		$this->frontend_currencies->flag_order_loading( 'WC_Order' );

		// During the load the USD store keeps 2 even though JPY is selected.
		$this->assertEquals( 2, $this->frontend_currencies->get_price_decimals( 2 ) );

		// Once the factory is no longer loading, the selected JPY applies again, so 0.
		$factory_in_backtrace = false;
		$this->assertEquals( 0, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	/**
	 * While an existing order recalculates its totals, amounts follow the order's own currency, not
	 * the shopper's selected currency.
	 */
	public function test_get_price_decimals_uses_order_currency_during_calculate_totals_and_releases_on_clear() {
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturn( true );
		$this->mock_utils
			->method( 'get_backtrace_call_count' )
			->willReturn( 1 );
		$this->mock_utils
			->method( 'is_page_with_vars' )
			->willReturn( false );
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturn( new Currency( $this->localization_service, 'JPY' ) );

		$this->mock_order->set_currency( 'BHD' );
		$this->frontend_currencies->init_calculated_order_currency( true, $this->mock_order );

		// During calculate_totals the order's BHD (3 decimals) applies, not the selected JPY.
		$this->assertEquals( 3, $this->frontend_currencies->get_price_decimals( 2 ) );

		// The after-calculate hook releases it, so the selected JPY (0 decimals) applies again.
		$this->frontend_currencies->clear_calculated_order_currency();
		$this->assertEquals( 0, $this->frontend_currencies->get_price_decimals( 2 ) );
	}

	/**
	 * If recalculation ends without the after hook running (for example it threw), the recorded order
	 * currency is dropped on the next lookup instead of leaking into later prices on the request.
	 */
	public function test_calculated_order_currency_is_dropped_once_calculate_totals_is_not_running() {
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturn( new Currency( $this->localization_service, 'JPY' ) );
		$this->mock_utils
			->method( 'is_page_with_vars' )
			->willReturn( false );
		$this->mock_utils
			->method( 'is_call_in_backtrace' )
			->willReturn( false );
		$this->mock_utils
			->method( 'get_backtrace_call_count' )
			->willReturn( 0 );

		$this->mock_order->set_currency( 'BHD' );
		$this->frontend_currencies->init_calculated_order_currency( true, $this->mock_order );

		// calculate_totals is no longer on the stack, so the BHD pin is dropped and the selected JPY applies.
		$this->assertEquals( 0, $this->frontend_currencies->get_price_decimals( 2 ) );
		// And it stays dropped.
		$this->assertEquals( 0, $this->frontend_currencies->get_price_decimals( 2 ) );
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

	public function test_loading_a_real_order_keeps_its_currency_decimals() {
		$frontend_currencies = $this->reinit_with_real_utils();

		$selected = 'USD';
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturnCallback(
				function () use ( &$selected ) {
					return new Currency( $this->localization_service, $selected );
				}
			);

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( '10.49' );
		$order->save();
		$order_id = $order->get_id();

		// Switch to a zero-decimal currency the way MultiCurrency does (it clears the cached selection).
		$selected = 'JPY';
		$frontend_currencies->selected_currency_changed();

		// Hydrating the order must keep its own 2 decimals, not round to the selected JPY's 0.
		wp_cache_flush();
		$this->assertSame( '10.49', wc_get_order( $order_id )->get_total() );
	}

	/**
	 * A persistent object cache serializes the order object and re-reads it from the data store when it
	 * is unserialized (WC_Data::__wakeup() calls the constructor, which reads). That re-read does not go
	 * through WC_Order_Factory, so it must still keep the order's own decimals rather than round the
	 * total to the selected zero-decimal currency. Regression test for the cached pay-for-order display.
	 */
	public function test_waking_a_persisted_order_keeps_its_currency_decimals() {
		$frontend_currencies = $this->reinit_with_real_utils();

		$selected = 'USD';
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturnCallback(
				function () use ( &$selected ) {
					return new Currency( $this->localization_service, $selected );
				}
			);

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( '10.49' );
		$order->save();
		$order_id = $order->get_id();

		$selected = 'JPY';
		$frontend_currencies->selected_currency_changed();

		// serialize()/unserialize() reproduces what a persistent object cache does on a hit: WC_Data only
		// stores the id and re-reads on wakeup. The re-read total must keep its 2 decimals, not round to 0.
		wp_cache_flush();
		$woken = unserialize( serialize( wc_get_order( $order_id ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

		$this->assertSame( '10.49', $woken->get_total() );
	}

	public function test_loading_with_an_unresolvable_order_pay_var_keeps_the_order_decimals() {
		$frontend_currencies = $this->reinit_with_real_utils();

		$selected = 'USD';
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturnCallback(
				function () use ( &$selected ) {
					return new Currency( $this->localization_service, $selected );
				}
			);

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( '10.49' );
		$order->save();
		$order_id = $order->get_id();

		$selected = 'JPY';
		$frontend_currencies->selected_currency_changed();

		global $wp;
		$wp->query_vars['order-pay'] = 'x';

		wp_cache_flush();
		$this->assertSame( '10.49', wc_get_order( $order_id )->get_total() );
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

	/**
	 * With HPOS, WC_Order_Factory caches every order it reads. While one order loads, the order named by a
	 * single-order query var is read to learn its currency; that read must keep the named order's precision,
	 * or the cached copy hands a rounded total to every later wc_get_order() call in the request. The store
	 * is set to zero decimals, so a read at the store's decimals would turn 10.49 into 10.
	 */
	public function test_reading_the_query_var_order_while_another_order_loads_caches_it_at_full_precision() {
		$this->use_hpos_or_skip();
		$zero_decimal_store = function () {
			return 0;
		};

		try {
			$this->reinit_with_real_utils();
			$this->mock_multi_currency
				->method( 'get_selected_currency' )
				->willReturn( new Currency( $this->localization_service, Currency_Code::UNITED_STATES_DOLLAR ) );

			// Created at the default 2 decimals, before the store switches to zero decimals below.
			$named_order = WC_Helper_Order::create_order();
			$named_order->set_currency( Currency_Code::UNITED_STATES_DOLLAR );
			$named_order->set_total( '10.49' );
			$named_order->save();
			$loaded_order = WC_Helper_Order::create_order();

			add_filter( 'pre_option_woocommerce_price_num_decimals', $zero_decimal_store );

			global $wp;
			$wp->query_vars['order-received'] = $named_order->get_id();

			wp_cache_flush();
			wc_get_order( $loaded_order->get_id() );

			$this->assertSame( '10.49', wc_get_order( $named_order->get_id() )->get_total() );
		} finally {
			remove_filter( 'pre_option_woocommerce_price_num_decimals', $zero_decimal_store );
		}
	}

	/**
	 * init_order_currency() removes the formatting filters for its order read and must put them back even
	 * when the read throws, or every later price on the page loses the currency's formatting.
	 */
	public function test_init_order_currency_restores_its_filters_when_the_order_read_throws() {
		$throw_on_read = function () {
			throw new Exception( 'Order read failed.' );
		};
		add_filter( 'woocommerce_order_class', $throw_on_read, 1 );

		try {
			$this->frontend_currencies->init_order_currency( $this->mock_order->get_id() );
			$this->fail( 'The order read was expected to throw.' );
		} catch ( Exception $e ) {
			$this->assertSame( 'Order read failed.', $e->getMessage() );
		} finally {
			remove_filter( 'woocommerce_order_class', $throw_on_read, 1 );
		}

		$this->assertSame( 900, has_filter( 'wc_get_price_decimal_separator', [ $this->frontend_currencies, 'get_price_decimal_separator' ] ) );
		$this->assertSame( 900, has_filter( 'wc_get_price_thousand_separator', [ $this->frontend_currencies, 'get_price_thousand_separator' ] ) );
		$this->assertSame( 900, has_filter( 'woocommerce_price_format', [ $this->frontend_currencies, 'get_woocommerce_price_format' ] ) );

		// The nested-read guard is released too, so the next call reads the order and records its currency.
		$this->mock_order->set_currency( Currency_Code::EURO );
		$this->mock_order->save();
		$this->frontend_currencies->init_order_currency( $this->mock_order->get_id() );
		$this->assertSame( 'EUR', $this->frontend_currencies->get_order_currency() );
	}

	public function test_calculate_totals_on_a_real_order_keeps_its_currency_decimals() {
		$frontend_currencies = $this->reinit_with_real_utils();

		$selected = 'USD';
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturnCallback(
				function () use ( &$selected ) {
					return new Currency( $this->localization_service, $selected );
				}
			);

		$order = new WC_Order();
		$order->set_currency( 'USD' );
		foreach ( [ '1.49', '1.49', '1.45' ] as $price ) {
			$product = new WC_Product_Simple();
			$product->set_regular_price( $price );
			$product->set_price( $price );
			$product->save();
			$order->add_product( $product, 1 );
		}
		$order->calculate_totals();
		$order->save();
		$this->assertSame( '4.43', $order->get_total() );

		// Recalculate with a zero-decimal currency selected; the total must stay at the order's precision.
		$selected = 'JPY';
		$frontend_currencies->selected_currency_changed();
		$order->calculate_totals();
		$this->assertSame( '4.43', $order->get_total() );
	}

	/**
	 * A nested calculate_totals() whose inner order throws before its after hook must not leave the inner
	 * currency applied to the outer order's own recalculation. Drives the real before/after hooks and a
	 * genuine inner failure through the live call stack.
	 */
	public function test_nested_calculate_totals_releases_the_inner_currency_after_an_inner_exception() {
		$frontend_currencies = $this->reinit_with_real_utils();

		$selected = 'JPY';
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturnCallback(
				function () use ( &$selected ) {
					return new Currency( $this->localization_service, $selected );
				}
			);
		$frontend_currencies->selected_currency_changed();

		// A zero-decimal inner order whose recalculation throws after our before hook pushed its currency
		// but before its after hook pops it, as an exception inside calculate_totals() would.
		$inner_order = new WC_Order();
		$inner_order->set_currency( 'JPY' );

		// Keep the callback in a variable and remove it in finally, so it does not survive into later tests
		// (tear_down() does not strip arbitrary woocommerce_order_before_calculate_totals callbacks).
		$throw_on_inner = function ( $_and_taxes, $order ) use ( $inner_order ) {
			if ( 'JPY' === $order->get_currency() ) {
				throw new \Exception( 'inner calculation failed' );
			}
			// Outer order: nest the inner recalculation and swallow its failure, as a plugin might.
			try {
				$inner_order->calculate_totals();
			} catch ( \Exception $e ) {
				unset( $e );
			}
		};
		add_action( 'woocommerce_order_before_calculate_totals', $throw_on_inner, 20, 2 );

		try {
			// Outer order in the store currency (2 decimals), three lines summing to 4.43.
			$outer_order = new WC_Order();
			$outer_order->set_currency( 'USD' );
			foreach ( [ '1.49', '1.49', '1.45' ] as $price ) {
				$product = new WC_Product_Simple();
				$product->set_regular_price( $price );
				$product->set_price( $price );
				$product->save();
				$outer_order->add_product( $product, 1 );
			}

			$outer_order->calculate_totals();

			// After the inner JPY failure the outer USD order must keep its own 2 decimals, so its total
			// stays 4.43 rather than being rounded to the stale inner JPY precision.
			$this->assertSame( '4.43', $outer_order->get_total() );
		} finally {
			remove_action( 'woocommerce_order_before_calculate_totals', $throw_on_inner, 20 );
		}
	}

	/**
	 * A calculate_totals() that throws and unwinds leaves its order's currency on the stack. A later,
	 * unrelated recalculation must still use its own order's currency, not the stale one left at the
	 * bottom of the stack. Drives the real before/after hooks and a genuine failure, so the
	 * stale-at-bottom case is caught where the read-time reconciliation (which slices from the front)
	 * would keep the stale entry.
	 */
	public function test_calculate_totals_after_an_unrelated_failed_calculation_uses_the_new_order_currency() {
		$frontend_currencies = $this->reinit_with_real_utils();

		$selected = 'USD';
		$this->mock_multi_currency
			->method( 'get_selected_currency' )
			->willReturnCallback(
				function () use ( &$selected ) {
					return new Currency( $this->localization_service, $selected );
				}
			);

		// Build the USD order (2 decimals, three lines summing to 4.43) while USD is selected, so its line
		// totals are not pre-rounded.
		$order = new WC_Order();
		$order->set_currency( 'USD' );
		foreach ( [ '1.49', '1.49', '1.45' ] as $price ) {
			$product = new WC_Product_Simple();
			$product->set_regular_price( $price );
			$product->set_price( $price );
			$product->save();
			$order->add_product( $product, 1 );
		}

		// Switch to a zero-decimal currency the way MultiCurrency does.
		$selected = 'JPY';
		$frontend_currencies->selected_currency_changed();

		// A recalculation that throws after our before hook pushed its currency (JPY) but before its after
		// hook pops it, leaving a stale JPY entry at the bottom of the stack.
		$fail_on_jpy = function ( $_and_taxes, $failing_order ) {
			if ( 'JPY' === $failing_order->get_currency() ) {
				throw new \Exception( 'calculation failed' );
			}
		};
		add_action( 'woocommerce_order_before_calculate_totals', $fail_on_jpy, 20, 2 );

		try {
			$failed_order = new WC_Order();
			$failed_order->set_currency( 'JPY' );
			try {
				$failed_order->calculate_totals();
			} catch ( \Exception $e ) {
				unset( $e );
			}

			// Recalculated back to back, with nothing reading the decimals in between to clear the stale
			// entry: the USD order's own 2 decimals must keep its total at 4.43, not round to JPY's 0 (4).
			$order->calculate_totals();
			$this->assertSame( '4.43', $order->get_total() );
		} finally {
			remove_action( 'woocommerce_order_before_calculate_totals', $fail_on_jpy, 20 );
		}
	}

	public function test_order_pay_query_var_resolves_the_order_currency() {
		global $wp;
		$this->mock_order->set_currency( 'EUR' );
		$this->mock_order->save();
		$wp->query_vars['order-pay'] = $this->mock_order->get_id() . 'x';
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( new Currency( $this->localization_service, 'JPY' ) );

		$this->frontend_currencies->init_order_currency_from_query_vars();

		$this->assertSame( 'EUR', $this->frontend_currencies->get_order_currency() );
	}

	public function test_registers_order_load_and_calculate_hooks() {
		// The order currency is pushed before calculate_totals() reads the decimals (priority 10) and
		// cleared after everything else has read them (priority 900); the design relies on this ordering.
		$this->assertSame( 900, has_filter( 'woocommerce_order_class', [ $this->frontend_currencies, 'flag_order_loading' ] ) );
		$this->assertSame( 10, has_action( 'woocommerce_order_before_calculate_totals', [ $this->frontend_currencies, 'init_calculated_order_currency' ] ) );
		$this->assertSame( 900, has_action( 'woocommerce_order_after_calculate_totals', [ $this->frontend_currencies, 'clear_calculated_order_currency' ] ) );
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
			// Request URI, store currency arguments, product price arguments.
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
		$mock_product->get_price_html();

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

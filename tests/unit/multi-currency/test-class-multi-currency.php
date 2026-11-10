<?php
/**
 * Class WCPay_Multi_Currency_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Currency_Code;
use WCPay\MultiCurrency\FrontendCurrencies;
use WCPay\MultiCurrency\FrontendPrices;
use WCPay\MultiCurrency\Utils;
use WCPay\MultiCurrency\CachingEnvironment;
use WCPay\MultiCurrency\Exceptions\InvalidCurrencyException;
use WCPay\MultiCurrency\Exceptions\InvalidCurrencyRateException;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyAccountInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyApiClientInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyCacheInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencySettingsInterface;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Settings;
use WCPay\MultiCurrency\SettingsOnboardCta;

/**
 * WCPay\MultiCurrency\MultiCurrency unit tests.
 */
class WCPay_Multi_Currency_Tests extends WCPAY_UnitTestCase {
	const LOGGED_IN_USER_ID             = 1;
	const ENABLED_CURRENCIES_OPTION     = 'wcpay_multi_currency_enabled_currencies';
	const CART_PRODUCT_PRICE            = 100;
	const STORE_CURRENCY_CART_TOTAL     = '100.00';
	const POST_SAVE_TOTAL_ADJUSTMENT    = 1;
	const POST_SAVE_ADJUSTMENT_PRIORITY = 5000;
	const OTHER_CART_SAVE_PRIORITY      = 1500;
	const CACHED_SHIPPING_RATES_KEY     = 'shipping_for_package_0';
	const CACHED_SHIPPING_RATES         = [ 'package_hash' => 'wc_ship_cached' ];
	const REBUILT_SHIPPING_RATES        = [ 'package_hash' => 'wc_ship_rebuilt' ];
	const FAILING_FEE_MESSAGE           = 'Fee service unavailable';
	const STORE_API_CART_ROUTE          = '/wc/store/v1/cart';
	const STOREFRONT_REQUEST_URI        = '/shop/';

	/**
	 * Mock enabled currencies.
	 *
	 * @var array
	 */
	private $mock_enabled_currencies = [ 'USD', 'CAD', 'GBP', 'BIF' ];

	/**
	 * @var int
	 */
	private $timestamp_for_testing;

	/**
	 * Mock available currencies with their rates.
	 *
	 * @var array
	 */
	private $mock_available_currencies = [
		'USD' => 1,
		'CAD' => 1.206823,
		'GBP' => 0.708099,
		'EUR' => 0.826381,
		'CDF' => 2000,
		'BIF' => 1974, // Zero decimal currency.
		'CLP' => 706.8, // Zero decimal currency.
	];

	/**
	 * Mock cached currencies return array
	 *
	 * @var array
	 */
	private $mock_cached_currencies;

	/**
	 * MultiCurrency instance.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * Mock of the API client.
	 *
	 * @var MultiCurrencyApiClientInterface
	 */
	private $mock_api_client;

	/**
	 * Mock of the MultiCurrencyAccountInterface.
	 *
	 * @var MultiCurrencyAccountInterface
	 */
	private $mock_account;

	/**
	 * Mock of the WC_Payments_Localization_Service.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $localization_service;

	/**
	 * Mock of MultiCurrencyCacheInterface.
	 *
	 * @var MultiCurrencyCacheInterface;
	 */
	private $mock_cache;

	/**
	 * Mock of MultiCurrencySettingsInterface.
	 *
	 * @var MultiCurrencySettingsInterface;
	 */
	private $mock_settings;

	/**
	 * Mock of Utils.
	 *
	 * @var Utils;
	 */
	private $mock_utils;

	public function set_up() {
		parent::set_up();

		// Isolate the cache-mode auto-detection state per test. Other suites (e.g. the WC_Payments
		// upgrade test) fire woocommerce_woocommerce_payments_updated, which runs the cache
		// auto-detect migration and can leave wcpay_multi_currency_cache_autodetect_done set.
		delete_option( 'wcpay_multi_currency_rendering_mode' );
		delete_option( 'wcpay_multi_currency_cache_autodetect_done' );
		delete_option( 'wcpay_multi_currency_cache_recommendation_dismissed' );

		$this->localization_service = new WC_Payments_Localization_Service();

		$this->mock_currency_settings(
			'GBP',
			[
				'price_charm'    => '-0.1',
				'price_rounding' => '0.50',
			]
		);

		$this->timestamp_for_testing = strtotime( 'today midnight' );

		$this->mock_cached_currencies = [
			'currencies' => $this->mock_available_currencies,
			'updated'    => $this->timestamp_for_testing,
			'expires'    => $this->timestamp_for_testing + DAY_IN_SECONDS,
		];

		update_option( self::ENABLED_CURRENCIES_OPTION, $this->mock_enabled_currencies );

		$this->init_multi_currency();
	}

	/** A getter before session creation must not prevent later price hooks. */
	public function test_early_getter_before_session_preserves_price_hooks() {
		remove_filter( 'woocommerce_product_get_price', [ $this->multi_currency->get_frontend_prices(), 'get_product_price_string' ], 99 );
		$this->mock_currency_settings(
			'CAD',
			[
				'price_rounding' => '0',
				'price_charm'    => '0',
			]
		);
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( MultiCurrency::RENDERING_MODE_OPTION, 'cache' );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		$this->init_multi_currency( null, true, null, null, null, false );
		$session = WC()->session;
		try {
			WC()->session = null;
			$this->multi_currency->get_default_currency();
			// A direct read before the session exists caches the store currency as unselected.
			$this->assertSame( 'USD', $this->multi_currency->get_frontend_currencies()->get_woocommerce_currency() );
			$this->assertStringContainsString( 'wcpay-async-price', wc_price( 10 ) );
			$this->assertFalse( has_filter( 'woocommerce_product_get_price', [ $this->multi_currency->get_frontend_prices(), 'get_product_price_string' ] ) );
			$active = $this->createMock( WC_Session_Handler::class );
			$active->method( 'has_session' )->willReturn( true );
			$active->method( 'get' )->willReturn( 'CAD' );
			WC()->session = $active;
			$this->multi_currency->init();
			$this->assertStringNotContainsString( 'wcpay-async-price', wc_price( 10 ) );
			$this->assertSame( 99, has_filter( 'woocommerce_product_get_price', [ $this->multi_currency->get_frontend_prices(), 'get_product_price_string' ] ) );
			$product = new WC_Product_Simple();
			$product->set_price( 10 );
			foreach ( [ 1, 2 ] as $repeat ) {
				$this->multi_currency->init();
				$this->assertSame( 'CAD', get_woocommerce_currency(), 'Initialization ' . $repeat );
				$this->assertEquals( 12.07, (float) $product->get_price() );
				$this->assertStringContainsString( '12.07', wc_price( $product->get_price() ) );
				$this->assertStringNotContainsString( 'wcpay-async-price', wc_price( $product->get_price() ) );
			}
		} finally {
			WC()->session = $session;
			$this->remove_currency_settings_mock( 'CAD', [ 'price_rounding', 'price_charm' ] );
		}
	}

	/** Outside cache-optimized mode, a repeated init() must leave the registered hooks alone. */
	public function test_repeated_init_outside_cache_mode_leaves_hooks_alone() {
		$prices = $this->multi_currency->get_frontend_prices();
		remove_filter( 'woocommerce_product_get_price', [ $prices, 'get_product_price_string' ], 99 );
		$this->multi_currency->init();
		$this->assertFalse( has_filter( 'woocommerce_product_get_price', [ $prices, 'get_product_price_string' ] ) );
	}

	/** An early getter followed by scheduled init must not register a second converter. */
	public function test_early_getter_then_init_registers_one_analytics_converter() {
		update_option( '_wcpay_feature_customer_multi_currency', '1' );
		$this->init_multi_currency( null, true, null, null, null, false );
		$count  = static function () {
			global $wp_filter;
			$total = 0;
			foreach ( $wp_filter['woocommerce_analytics_update_order_stats_data']->callbacks ?? [] as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( is_array( $function ) && $function[0] instanceof \WCPay\MultiCurrency\Analytics && 'update_order_stats_data' === $function[1] ) {
						++$total;
					}
				}
			}
			return $total;
		};
		$before = $count();
		$this->multi_currency->get_default_currency();
		$this->assertSame( $before + 1, $count() );
		$this->multi_currency->init();
		$this->assertSame( $before + 1, $count() );
	}

	/** Repeated initialization must preserve the historical amount on import and rebuild. */
	public function test_reinitialized_analytics_import_and_rebuild_convert_once() {
		global $wpdb;
		$currency = get_option( 'woocommerce_currency' );
		$order    = new WC_Order();
		try {
			update_option( 'woocommerce_currency', 'EUR' );
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->get_default_currency();
			$this->multi_currency->init();
			$order->set_currency( 'GBP' );
			$order->set_total( 36 );
			$order->set_status( 'completed' );
			$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'EUR' );
			$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', '0.85' );
			$order->update_meta_data( '_wcpay_multi_currency_stripe_exchange_rate', '1.16388' );
			$order->save();

			foreach ( [ 'initial import', 'historical rebuild' ] as $phase ) {
				$wpdb->delete( $wpdb->prefix . 'wc_order_stats', [ 'order_id' => $order->get_id() ], [ '%d' ] );
				\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::sync_order( $order->get_id() );
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT total_sales, net_total FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d", $order->get_id() ), ARRAY_A );
				$this->assertNotNull( $row, $phase );
				$this->assertEquals( 41.90, (float) $row['total_sales'], $phase );
				$this->assertEquals( 41.90, (float) $row['net_total'], $phase );
				$persisted = wc_get_order( $order->get_id() );
				$this->assertSame( 'GBP', $persisted->get_currency(), $phase );
				$this->assertEquals( 36, (float) $persisted->get_total(), $phase );
			}
		} finally {
			if ( $order->get_id() ) {
				$order->delete( true );
			}
			update_option( 'woocommerce_currency', $currency );
		}
	}


	public function tear_down() {
		WC()->session->__unset( MultiCurrency::CURRENCY_SESSION_KEY );
		WC()->session->__unset( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY );
		WC()->session->__unset( MultiCurrency::CART_TOTALS_HASH_SESSION_KEY );
		WC()->session->__unset( 'cart_totals' );
		remove_all_filters( 'wcpay_multi_currency_apply_charm_only_to_products' );
		remove_all_filters( 'wcpay_multi_currency_available_currencies' );
		remove_all_filters( 'wcpay_multi_currency_override_selected_currency' );
		remove_all_filters( 'woocommerce_currency' );
		remove_all_filters( 'woocommerce_geolocate_ip' );
		remove_all_filters( 'stylesheet' );

		delete_user_meta( self::LOGGED_IN_USER_ID, MultiCurrency::CURRENCY_META_KEY );
		wp_set_current_user( 0 );

		$this->remove_currency_settings_mock( 'GBP', [ 'price_charm', 'price_rounding', 'manual_rate', 'exchange_rate' ] );
		delete_option( self::ENABLED_CURRENCIES_OPTION );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'no' );
		delete_option( '_wcpay_feature_mc_cache_optimized' );
		delete_option( 'wcpay_multi_currency_rendering_mode' );
		delete_option( 'wcpay_multi_currency_cache_autodetect_done' );
		delete_option( 'wcpay_multi_currency_cache_recommendation_dismissed' );
		delete_option( '_wcpay_feature_customer_multi_currency' );
		unset( $_SERVER['HTTP_REFERER'] );
		delete_option( 'wcpay_multi_currency_store_currency' );
		Utils::set_matched_store_api_route( false );

		parent::tear_down();
	}

	public function test_available_currencies_uses_wc_currencies_when_no_stripe_account() {
		$this->mock_account->method( 'get_cached_account_data' )->willReturn( false );

		$this->init_multi_currency();

		$expected_currencies  = array_keys( get_woocommerce_currencies() );
		$available_currencies = array_keys( $this->multi_currency->get_available_currencies() );

		$this->assertEquals( sort( $expected_currencies ), sort( $available_currencies ) );
	}

	public function test_registers_settings_with_account() {
		$this->init_multi_currency( null, true );
		$result = $this->multi_currency->init_settings_pages( [] );

		$this->assertInstanceOf( Settings::class, $result[0] );
	}

	public function test_registers_onboarding_cta_as_settings_when_no_account() {
		$this->init_multi_currency( null, false );
		$result = $this->multi_currency->init_settings_pages( [] );

		$this->assertInstanceOf( SettingsOnboardCta::class, $result[0] );
	}

	public function test_available_currencies_uses_wc_currencies_when_stripe_account_has_no_customer_supported_currencies() {
		$this->mock_account->method( 'get_cached_account_data' )->willReturn( [ 'id' => 'acct' ] );

		$this->init_multi_currency();

		$expected_currencies  = array_keys( get_woocommerce_currencies() );
		$available_currencies = array_keys( $this->multi_currency->get_available_currencies() );

		$this->assertEquals( sort( $expected_currencies ), sort( $available_currencies ) );
	}

	public function test_available_currencies_uses_only_customer_supported_currencies_when_enabled_in_wc() {
		$this->mock_account
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'customer_currencies' => [
						'supported' => [
							'usd',
							'cad',
							'random',
							'brl',
						],
					],
				]
			);

		$this->init_multi_currency();

		$expected_currencies  = [ 'USD', 'CAD', 'BRL' ];
		$available_currencies = array_keys( $this->multi_currency->get_available_currencies() );

		$this->assertEquals( sort( $expected_currencies ), sort( $available_currencies ) );
	}

	public function test_get_available_currencies_adds_store_currency() {
		// Use a real WooCommerce currency that is not in the mock Stripe account currencies.
		update_option( 'woocommerce_currency', 'JPY' );

		$this->init_multi_currency();

		$default_currency = $this->multi_currency->get_available_currencies()['JPY'];

		$this->assertSame( 'JPY', $default_currency->get_code() );
		$this->assertSame( 1.0, $default_currency->get_rate() );
	}

	public function test_get_available_currencies_uses_store_currency_when_woocommerce_currency_is_filtered() {
		update_option( 'woocommerce_currency', 'USD' );
		remove_all_filters( 'woocommerce_currency' );
		add_filter( 'woocommerce_currency', fn() => 'EUR', 999 );

		$mock_account = $this->createMock( MultiCurrencyAccountInterface::class );
		$mock_account
			->method( 'get_cached_account_data' )
			->willReturn( [ 'id' => 'acct' ] );
		$mock_account
			->method( 'get_account_customer_supported_currencies' )
			->willReturn( [ 'usd', 'cad' ] );

		$this->init_multi_currency( null, true, $mock_account );

		$available_currencies = $this->multi_currency->get_available_currencies();

		$this->assertArrayHasKey( 'USD', $available_currencies );
		$this->assertArrayNotHasKey( 'EUR', $available_currencies );
		$this->assertSame( 'USD', $available_currencies['USD']->get_code() );
		$this->assertSame( 1.0, $available_currencies['USD']->get_rate() );
	}

	public function test_get_default_currency_uses_store_currency_when_woocommerce_currency_is_filtered() {
		update_option( 'woocommerce_currency', 'USD' );
		remove_all_filters( 'woocommerce_currency' );
		add_filter( 'woocommerce_currency', fn() => 'EUR', 999 );

		$this->init_multi_currency();

		$this->assertSame( 'USD', $this->multi_currency->get_default_currency()->get_code() );
		$this->assertTrue( $this->multi_currency->get_default_currency()->get_is_default() );
	}

	public function test_available_currencies_can_be_filtered() {
		add_filter(
			'wcpay_multi_currency_available_currencies',
			function ( $available_currencies ) {
				// Remove BRL from the list of currencies.
				return array_filter(
					$available_currencies,
					function ( $currency ) {
						return 'BRL' !== $currency;
					}
				);
			}
		);

		$this->assertArrayHasKey( 'BRL', $this->multi_currency->get_available_currencies() );

		$this->init_multi_currency();

		$this->assertArrayNotHasKey( 'BRL', $this->multi_currency->get_available_currencies() );
	}

	public function test_get_enabled_currencies_returns_correctly() {
		$mock_currencies = [
			'USD' => 1,
			'CAD' => 1.206823,
			'GBP' => 0.708099,
			'BIF' => 1974,
		];

		$expected = [];
		foreach ( $mock_currencies as $code => $rate ) {
			$currency = new WCPay\MultiCurrency\Currency( $this->localization_service, $code, $rate );
			$currency->set_charm( 0.00 );
			$currency->set_rounding( '1.00' );
			$currency->set_last_updated( $this->timestamp_for_testing );
			$expected[ $currency->get_code() ] = $currency;
		}

		$expected['GBP']->set_charm( '-0.1' );
		$expected['GBP']->set_rounding( '0.50' );
		// Zero-decimal currencies should default to rounding = 100.
		$expected['BIF']->set_rounding( '100' );

		$this->assertEquals( $expected, $this->multi_currency->get_enabled_currencies() );
	}

	public function test_get_enabled_currencies_returns_sorted_currencies() {
		$expected = [ 'USD', 'BIF', 'CAD', 'GBP' ];
		$this->assertSame( $expected, array_keys( $this->multi_currency->get_enabled_currencies() ) );
	}

	public function test_set_enabled_currencies_properly_saves_currencies() {
		// Arrange: Set the currencies to change to.
		$currencies = [ 'USD', 'EUR', 'GBP', 'CLP' ];

		// Assert: Confirm the currencies are the default currencies.
		$this->assertSame( $this->mock_enabled_currencies, get_option( self::ENABLED_CURRENCIES_OPTION ) );

		// Act: Set the currencies.
		$this->multi_currency->set_enabled_currencies( $currencies );

		// Assert: Confirm the currencies were updated.
		$this->assertSame( $currencies, get_option( self::ENABLED_CURRENCIES_OPTION ) );
	}

	public function test_set_enabled_currencies_exits_if_currencies_not_array() {
		// Arrange: Set the currencies to change to.
		$currencies = 'banana';

		// Act: Set the currencies.
		$this->multi_currency->set_enabled_currencies( $currencies );

		// Assert: Confirm the currencies have not been updated from the default currencies.
		$this->assertSame( $this->mock_enabled_currencies, get_option( self::ENABLED_CURRENCIES_OPTION ) );
	}

	public function test_set_enabled_currencies_exits_if_currencies_array_empty() {
		// Arrange: Set the currencies to change to.
		$currencies = [];

		// Act: Set the currencies.
		$this->multi_currency->set_enabled_currencies( $currencies );

		// Assert: Confirm the currencies have not been updated from the default currencies.
		$this->assertSame( $this->mock_enabled_currencies, get_option( self::ENABLED_CURRENCIES_OPTION ) );
	}

	public function test_set_enabled_currencies_throws_exception_on_unavailable_currency() {
		// Arrange: Set the currencies to change to.
		$currencies = [ 'USD', 'EUR', 'GBP', 'banana' ];

		try {
			// Act: Set the currencies.
			$this->multi_currency->set_enabled_currencies( $currencies );
			$this->fail( 'Expected an InvalidCurrencyException.' );
		} catch ( InvalidCurrencyException $e ) {
			$this->assertSame( 'Invalid currency passed to set_enabled_currencies: banana', $e->getMessage() );
		}

		// Assert: Confirm the currencies have not been updated from the default currencies.
		$this->assertSame( $this->mock_enabled_currencies, get_option( self::ENABLED_CURRENCIES_OPTION ) );
	}

	public function test_set_enabled_currencies_triggers_removing_currency_settings() {
		update_option( 'wcpay_multi_currency_exchange_rate_bif', 'manual' );
		update_option( 'wcpay_multi_currency_manual_rate_bif', '2' );
		update_option( 'wcpay_multi_currency_price_rounding_bif', '10.00' );
		update_option( 'wcpay_multi_currency_price_charm_bif', '-0.05' );
		$currencies = [ 'USD', 'CAD', 'GBP' ];
		$this->multi_currency->set_enabled_currencies( $currencies );
		$this->assertFalse( get_option( 'wcpay_multi_currency_exchange_rate_bif' ) );
		$this->assertFalse( get_option( 'wcpay_multi_currency_manual_rate_bif' ) );
		$this->assertFalse( get_option( 'wcpay_multi_currency_price_rounding_bif' ) );
		$this->assertFalse( get_option( 'wcpay_multi_currency_price_charm_bif' ) );
	}

	public function test_enabled_but_unavailable_currencies_are_skipped() {
		update_option( self::ENABLED_CURRENCIES_OPTION, [ 'RANDOM_CURRENCY', 'USD' ] );

		$this->init_multi_currency();

		$this->assertSame( [ 'USD' ], array_keys( $this->multi_currency->get_enabled_currencies() ) );
	}

	public function test_get_selected_currency_returns_default_currency_for_empty_session_and_user() {
		$this->assertSame( get_woocommerce_currency(), $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_explicit_selected_currency_returns_null_for_empty_session_and_user() {
		$this->assertNull( $this->multi_currency->get_explicit_selected_currency() );
	}

	public function test_get_explicit_selected_currency_returns_null_for_invalid_session_currency() {
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, 'UNSUPPORTED_CURRENCY' );

		$this->assertNull( $this->multi_currency->get_explicit_selected_currency() );
	}

	public function test_get_explicit_selected_currency_does_not_replace_a_session_of_another_class() {
		$session = WC()->session;
		$session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::POUND_STERLING );

		// The Store API filters the session handler class when a Cart-Token is present.
		$other_session_class = get_class( new class() extends WC_Session_Handler {} );
		$filter              = function () use ( $other_session_class ) {
			return $other_session_class;
		};
		add_filter( 'woocommerce_session_handler', $filter );

		try {
			$currency = $this->multi_currency->get_explicit_selected_currency();

			$this->assertSame( $session, WC()->session );
			$this->assertSame( 'GBP', $currency->get_code() );
		} finally {
			remove_filter( 'woocommerce_session_handler', $filter );
			WC()->session = $session;
		}
	}

	public function test_store_api_batch_labels_the_cart_with_the_currency_of_its_amounts() {
		$session = WC()->session;
		$product = WC_Helper_Product::create_simple_product();
		$session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::BURUNDIAN_FRANC );

		// The Store API filters the session handler class when a Cart-Token is present.
		$other_session_class = get_class( new class() extends WC_Session_Handler {} );
		$filter              = function () use ( $other_session_class ) {
			return $other_session_class;
		};
		add_filter( 'woocommerce_session_handler', $filter );
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wc/store/v1/batch' );
		$request->set_param(
			'requests',
			[
				[
					'path' => '/wc/store/v1/cart/add-item',
					'body' => [
						'id'       => $product->get_id(),
						'quantity' => 1,
					],
				],
			]
		);

		try {
			$response = rest_get_server()->dispatch( $request );
			$totals   = $response->get_data()['responses'][0]['body']['totals'];

			$this->assertSame( 'BIF', $totals->currency_code );
			$this->assertSame( '19800', $totals->total_price );
		} finally {
			remove_filter( 'woocommerce_session_handler', $filter );
			remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
			WC()->session = $session;
			WC()->cart->empty_cart();
		}
	}

	public function test_get_explicit_selected_currency_ignores_stored_code_when_only_store_currency_is_enabled() {
		// Not connected: only the store currency is enabled, so nothing could have been selected.
		$this->init_multi_currency( null, false );
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::UNITED_STATES_DOLLAR );

		$this->assertSame( [ 'USD' ], array_keys( $this->multi_currency->get_enabled_currencies() ) );
		$this->assertNull( $this->multi_currency->get_explicit_selected_currency() );
	}

	public function test_get_explicit_selected_currency_honours_stored_store_currency_with_two_enabled_currencies() {
		update_option( self::ENABLED_CURRENCIES_OPTION, [ Currency_Code::UNITED_STATES_DOLLAR, Currency_Code::POUND_STERLING ] );
		$this->init_multi_currency();
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::UNITED_STATES_DOLLAR );

		$this->assertSame( [ 'USD', 'GBP' ], array_keys( $this->multi_currency->get_enabled_currencies() ) );
		$this->assertSame( 'USD', $this->multi_currency->get_explicit_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_does_not_trigger_null_offset_deprecation_without_stored_currency() {
		// Regression test for WOOPMNT-6238. With no currency stored for the user or
		// session, the resolved currency code is null. Subscripting the enabled
		// currencies array with that null key is deprecated as of PHP 8.5. This suite
		// does not convert deprecations to exceptions, so assert the absence explicitly.
		$null_offset_deprecations = [];
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Scoped handler asserting the absence of a deprecation; restored in finally below.
		set_error_handler(
			function ( $errno, $errstr ) use ( &$null_offset_deprecations ) {
				if ( false !== strpos( $errstr, 'null as an array offset' ) ) {
					$null_offset_deprecations[] = $errstr;
					return true;
				}
				return false;
			},
			E_DEPRECATED
		);

		try {
			$selected_code = $this->multi_currency->get_selected_currency()->get_code();
		} finally {
			restore_error_handler();
		}

		$this->assertSame( get_woocommerce_currency(), $selected_code );
		$this->assertSame(
			[],
			$null_offset_deprecations,
			'get_selected_currency() must not use null as an array offset when no currency is stored.'
		);
	}

	public function test_get_selected_currency_returns_default_currency_for_invalid_session_currency() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'UNSUPPORTED_CURRENCY' );

		$this->assertSame( get_woocommerce_currency(), $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_default_currency_for_invalid_user_currency() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		update_user_meta( self::LOGGED_IN_USER_ID, WCPay\MultiCurrency\MultiCurrency::CURRENCY_META_KEY, 'UNSUPPORTED_CURRENCY' );

		$this->assertSame( get_woocommerce_currency(), $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_currency_from_session() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );

		$this->assertSame( 'GBP', $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_currency_from_user() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		update_user_meta( self::LOGGED_IN_USER_ID, WCPay\MultiCurrency\MultiCurrency::CURRENCY_META_KEY, 'GBP' );

		$this->assertSame( 'GBP', $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_default_currency_with_no_stripe_account() {
		$this->init_multi_currency( null, false );
		$this->assertSame( get_woocommerce_currency(), $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_update_selected_currency_does_not_set_invalid_session_currency() {
		$this->multi_currency->update_selected_currency( 'UNSUPPORTED_CURRENCY' );

		$this->assertNull( WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_does_not_set_invalid_user_currency() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );

		$this->multi_currency->update_selected_currency( 'UNSUPPORTED_CURRENCY' );

		$this->assertEmpty( get_user_meta( self::LOGGED_IN_USER_ID, WCPay\MultiCurrency\MultiCurrency::CURRENCY_META_KEY, true ) );
	}

	public function test_update_selected_currency_sets_session_currency() {
		$this->multi_currency->update_selected_currency( 'GBP' );

		$this->assertSame( 'GBP', WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_sets_user_currency() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );

		$this->multi_currency->update_selected_currency( 'GBP' );

		$this->assertSame( 'GBP', get_user_meta( self::LOGGED_IN_USER_ID, WCPay\MultiCurrency\MultiCurrency::CURRENCY_META_KEY, true ) );
	}

	public function test_update_selected_currency_recalculates_loaded_cart() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();
		$this->assertEquals( 100, (float) WC()->cart->get_totals()['total'] );

		try {
			$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );

			// 100 USD at 0.708099, rounded up to 0.50 and charmed by -0.1.
			$this->assertEqualsWithDelta( 70.90, (float) WC()->cart->get_totals()['total'], 0.001 );
			$this->assertSame( 'GBP', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Run the given callback while pretending the cart has not been loaded from the
	 * session yet, as on Store API requests where the currency is switched on `init`
	 * and the cart is only loaded after `wp_loaded`. Clears `$wp_actions` for both
	 * actions so `did_action()` returns 0. State is restored after the callback returns,
	 * even if it throws.
	 *
	 * @param callable $callback The callback to run.
	 * @return void
	 */
	private function with_cart_not_loaded( callable $callback ) {
		global $wp_actions;

		$actions          = [ 'wp_loaded', 'woocommerce_cart_loaded_from_session' ];
		$original_actions = array_intersect_key( $wp_actions, array_flip( $actions ) );

		foreach ( $actions as $action ) {
			unset( $wp_actions[ $action ] );
		}

		try {
			$callback();
		} finally {
			foreach ( $original_actions as $action => $count ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the $wp_actions snapshot taken above.
				$wp_actions[ $action ] = $count;
			}
		}
	}

	/**
	 * Simulates the cart being loaded from the session on a new request.
	 *
	 * @return void
	 */
	private function reload_cart_from_session() {
		$session_property = new ReflectionProperty( WC_Cart::class, 'session' );
		$session_property->setAccessible( true );
		$session_property->getValue( WC()->cart )->get_cart_from_session();
	}

	/**
	 * Runs the callback as a storefront page view, a REST request or one with `rest_route` in the request.
	 *
	 * @param string   $kind     'page', 'rest_route' or 'rest_filter'.
	 * @param callable $callback The callback to run.
	 * @return mixed The callback's return value.
	 */
	private function as_request( string $kind, callable $callback ) {
		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = self::STOREFRONT_REQUEST_URI;
		if ( 'rest_route' === $kind ) {
			$_REQUEST['rest_route'] = self::STORE_API_CART_ROUTE;
		} elseif ( 'rest_filter' === $kind ) {
			add_filter( 'woocommerce_is_rest_api_request', '__return_true' );
		}

		try {
			return $callback();
		} finally {
			unset( $_REQUEST['rest_route'] );
			remove_filter( 'woocommerce_is_rest_api_request', '__return_true' );
			if ( null === $original_request_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $original_request_uri;
			}
		}
	}

	/**
	 * Forgets that this instance switched the currency, as a later request would.
	 *
	 * @return void
	 */
	private function start_new_request() {
		$property = new ReflectionProperty( MultiCurrency::class, 'currency_changed_before_cart_load' );
		$property->setAccessible( true );
		$property->setValue( $this->multi_currency, false );
	}

	/**
	 * Adds a product priced CART_PRODUCT_PRICE in the store currency to an empty cart.
	 *
	 * @return void
	 */
	private function add_product_to_cart() {
		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( self::CART_PRODUCT_PRICE );
		$product->set_price( self::CART_PRODUCT_PRICE );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );
	}

	/**
	 * Registers the cart totals currency hooks the way init_hooks() does.
	 *
	 * @return void
	 */
	private function register_cart_totals_currency_hooks() {
		add_action( 'woocommerce_before_calculate_totals', [ $this->multi_currency, 'start_cart_totals_calculation' ], PHP_INT_MIN );
		add_action( 'woocommerce_cart_updated', [ $this->multi_currency, 'note_cart_totals_saved' ], PHP_INT_MAX );
		add_action( 'woocommerce_after_calculate_totals', [ $this->multi_currency, 'record_cart_totals_currency' ], PHP_INT_MAX );
		add_action( 'woocommerce_cart_emptied', [ $this->multi_currency, 'clear_cart_totals_currency' ] );
		add_action( 'woocommerce_cart_loaded_from_session', [ $this->multi_currency, 'maybe_recalculate_cart_for_currency' ], PHP_INT_MAX );
	}

	/**
	 * Simulates WooCommerce < 10.1, which hooks WC_Cart_Session::set_session() to
	 * `woocommerce_cart_loaded_from_session` and so persists the stale totals before
	 * the recalculation runs.
	 *
	 * @return void
	 */
	private function persist_cart_totals_when_cart_is_loaded() {
		$session_property = new ReflectionProperty( WC_Cart::class, 'session' );
		$session_property->setAccessible( true );
		add_action( 'woocommerce_cart_loaded_from_session', [ $session_property->getValue( WC()->cart ), 'set_session' ] );
	}

	/**
	 * Counts cart recalculations triggered while the callback runs. Every recalculation goes
	 * through WC_Cart::calculate_totals(), which fires `woocommerce_after_calculate_totals` once,
	 * so the number of times that action fires is the number of recalculations.
	 *
	 * @param callable $callback The callback to run.
	 * @return int The number of recalculations observed.
	 */
	private function count_recalculations_during( callable $callback ): int {
		$recalculations = 0;
		$spy            = function () use ( &$recalculations ) {
			++$recalculations;
		};

		add_action( 'woocommerce_after_calculate_totals', $spy );

		try {
			$callback();
		} finally {
			remove_action( 'woocommerce_after_calculate_totals', $spy );
		}

		return $recalculations;
	}

	public function test_init_hooks_registers_cart_totals_currency_hooks() {
		$this->multi_currency->init_hooks();

		$this->assertSame(
			PHP_INT_MAX,
			has_action( 'woocommerce_after_calculate_totals', [ $this->multi_currency, 'record_cart_totals_currency' ] )
		);
		$this->assertNotFalse( has_action( 'woocommerce_cart_emptied', [ $this->multi_currency, 'clear_cart_totals_currency' ] ) );
		$this->assertSame( PHP_INT_MIN, has_action( 'woocommerce_before_calculate_totals', [ $this->multi_currency, 'start_cart_totals_calculation' ] ) );
		$this->assertSame( PHP_INT_MAX, has_action( 'woocommerce_cart_updated', [ $this->multi_currency, 'note_cart_totals_saved' ] ) );
		$this->assertSame(
			PHP_INT_MAX,
			has_action( 'woocommerce_cart_loaded_from_session', [ $this->multi_currency, 'maybe_recalculate_cart_for_currency' ] )
		);
	}

	/**
	 * In admin, cron and admin-API contexts get_woocommerce_currency() returns the store
	 * currency, so recalculating there would persist store-currency totals under the selected
	 * currency. The recalculation must not be registered outside currency-aware contexts.
	 */
	public function test_init_hooks_skips_stale_totals_recalculation_in_admin() {
		set_current_screen( 'dashboard' );

		try {
			$this->multi_currency->init_hooks();

			$this->assertFalse( has_action( 'woocommerce_cart_loaded_from_session', [ $this->multi_currency, 'maybe_recalculate_cart_for_currency' ] ) );
			// Only the recalculation is gated out of admin; the stamp hook stays registered everywhere.
			$this->assertNotFalse( has_action( 'woocommerce_after_calculate_totals', [ $this->multi_currency, 'record_cart_totals_currency' ] ) );
		} finally {
			set_current_screen( 'front' );
		}
	}

	/**
	 * A currency switch on a request that does not load the cart (e.g. a Store API product query) leaves the
	 * store-currency totals behind; the next REST request that loads the cart recalculates them in the selected
	 * currency, even on WooCommerce < 10.1 where the stale totals are persisted before the recalculation.
	 *
	 * @dataProvider provider_rest_request_kinds
	 */
	public function test_stale_cart_totals_are_recalculated_in_the_selected_currency_on_the_next_rest_load( string $kind ) {
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();
		$this->persist_cart_totals_when_cart_is_loaded();

		$this->assertEquals( 100, (float) WC()->cart->get_totals()['total'] );

		try {
			$this->with_cart_not_loaded(
				function () {
					$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
				}
			);
			$this->start_new_request();

			$this->as_request(
				$kind,
				function () {
					$this->reload_cart_from_session();
				}
			);

			// 100 USD at 0.708099, rounded up to 0.50 and charmed by -0.1.
			$this->assertSame( 'GBP', get_woocommerce_currency() );
			$this->assertEqualsWithDelta( 70.90, (float) WC()->cart->get_totals()['total'], 0.001 );
			$this->assertEqualsWithDelta( 70.90, (float) WC()->session->get( 'cart_totals' )['total'], 0.001 );
			$this->assertSame( 'GBP', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	public function provider_rest_request_kinds(): array {
		return [
			'REST API URL'         => [ 'rest_filter' ],
			'rest_route parameter' => [ 'rest_route' ],
		];
	}

	/**
	 * Storefront pages only display the saved totals, and WooCommerce recalculates them on cart and checkout, so a
	 * page view after a switch made elsewhere leaves them alone, as before the stamp existed. Recalculating here
	 * would turn a failing third-party calculation into an error on every page view.
	 */
	public function test_stale_cart_totals_are_not_recalculated_on_a_page_view_that_did_not_switch_the_currency() {
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();

		try {
			$this->with_cart_not_loaded(
				function () {
					$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
				}
			);
			$this->start_new_request();

			$recalculations = $this->as_request(
				'page',
				function () {
					return $this->count_recalculations_during(
						function () {
							$this->reload_cart_from_session();
						}
					);
				}
			);

			$this->assertSame( 0, $recalculations );
			$this->assertSame( 'USD', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * The switcher selects the currency on `init`, before the cart loads, so the page view that switched recalculates
	 * the cart when it loads, as WooPayments did before the stamp existed.
	 */
	public function test_page_view_that_switched_the_currency_recalculates_the_cart_on_load() {
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();

		try {
			$this->with_cart_not_loaded(
				function () {
					$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
				}
			);

			$this->as_request(
				'page',
				function () {
					$this->reload_cart_from_session();
				}
			);

			$this->assertEqualsWithDelta( 70.90, (float) WC()->cart->get_totals()['total'], 0.001 );
			$this->assertSame( 'GBP', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * A `?currency=` link naming the selected currency corrects stale totals.
	 */
	public function test_currency_url_naming_the_selected_currency_recalculates_stale_totals_on_load() {
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();

		try {
			$this->with_cart_not_loaded(
				function () {
					$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
				}
			);
			$this->start_new_request();

			$recalculations = $this->load_page_with_currency_url( 'gbp' );

			$this->assertSame( 1, $recalculations );
			$this->assertEqualsWithDelta( 70.90, (float) WC()->cart->get_totals()['total'], 0.001 );
			$this->assertSame( 'GBP', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * A `?currency=` link naming a currency that isn't enabled doesn't recalculate stale totals on a page view.
	 */
	public function test_currency_url_naming_a_disabled_currency_does_not_recalculate_stale_totals() {
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();

		try {
			$this->with_cart_not_loaded(
				function () {
					$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
				}
			);
			$this->start_new_request();

			$recalculations = $this->load_page_with_currency_url( 'CLP' );

			$this->assertSame( 0, $recalculations );
			$this->assertSame( 'USD', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Runs a storefront page view with `?currency=`: selects the currency on `init`, before the cart loads, then
	 * loads the cart.
	 *
	 * @param string $currency The `currency` query parameter.
	 * @return int The number of cart calculations during the page view.
	 */
	private function load_page_with_currency_url( string $currency ): int {
		$_GET['currency'] = $currency;

		try {
			return $this->as_request(
				'page',
				function () {
					return $this->count_recalculations_during(
						function () {
							$this->with_cart_not_loaded(
								function () {
									$this->multi_currency->update_selected_currency_by_url();
								}
							);
							$this->reload_cart_from_session();
						}
					);
				}
			);
		} finally {
			unset( $_GET['currency'] );
		}
	}

	/**
	 * When WooCommerce doesn't save the recalculated totals (e.g. `woocommerce_cart_session_initialize`
	 * returning false skips hooking WC_Cart_Session::set_session()), the session keeps the old totals,
	 * so the stamp must not claim the new currency. It is removed, so the next load recalculates.
	 */
	public function test_record_cart_totals_currency_clears_stamp_for_totals_that_were_not_saved() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();
		$this->assertSame( 'USD', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );

		$session_property = new ReflectionProperty( WC_Cart::class, 'session' );
		$session_property->setAccessible( true );
		$cart_session = $session_property->getValue( WC()->cart );
		remove_action( 'woocommerce_after_calculate_totals', [ $cart_session, 'set_session' ], 1000 );

		try {
			$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );

			$this->assertEqualsWithDelta( 70.90, (float) WC()->cart->get_totals()['total'], 0.001 );
			$this->assertEquals( 100, (float) WC()->session->get( 'cart_totals' )['total'] );
			$this->assertNull( WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			add_action( 'woocommerce_after_calculate_totals', [ $cart_session, 'set_session' ], 1000 );
			WC()->cart->empty_cart();
		}
	}

	/**
	 * A plugin that changes the cart's totals after WooCommerce has saved them (e.g. store credit applied at
	 * a priority after 1000) must not make every later load recalculate the cart: the saved totals were
	 * calculated in the selected currency, so they are stamped, and the next load leaves them alone.
	 */
	public function test_totals_changed_after_the_save_do_not_recalculate_the_cart_on_every_load() {
		$this->register_cart_totals_currency_hooks();
		$adjust_total = function ( $cart ) {
			$cart->set_total( (float) $cart->get_total( 'edit' ) - self::POST_SAVE_TOTAL_ADJUSTMENT );
		};
		add_action( 'woocommerce_after_calculate_totals', $adjust_total, self::POST_SAVE_ADJUSTMENT_PRIORITY );

		try {
			$this->add_product_to_cart();

			$this->assertSame( 'USD', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );

			$recalculations = $this->as_request(
				'rest_route',
				function () {
					return $this->count_recalculations_during(
						function () {
							$this->multi_currency->maybe_recalculate_cart_for_currency();
						}
					);
				}
			);

			$this->assertSame( 0, $recalculations );
		} finally {
			remove_action( 'woocommerce_after_calculate_totals', $adjust_total, self::POST_SAVE_ADJUSTMENT_PRIORITY );
			WC()->cart->empty_cart();
		}
	}

	/**
	 * A save outside `woocommerce_after_calculate_totals` (e.g. WooCommerce < 10.1 saving on cart load) doesn't count
	 * as this calculation's save.
	 */
	public function test_record_cart_totals_currency_ignores_saves_outside_the_main_cart_calculation() {
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();

		$this->multi_currency->start_cart_totals_calculation( WC()->cart );
		do_action( 'woocommerce_cart_updated' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		WC()->session->set( 'cart_totals', [ 'total' => self::STORE_CURRENCY_CART_TOTAL ] );

		try {
			$this->multi_currency->record_cart_totals_currency( WC()->cart );

			$this->assertNull( WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * `woocommerce_cart_updated` carries no cart, so another cart's session save during the main cart's calculation
	 * (e.g. a plugin saving a snapshot cart) must not count as the main cart's save. Here the main save is skipped,
	 * as on WooPayments' custom-session order-received requests, so the session keeps totals in the old currency.
	 */
	public function test_record_cart_totals_currency_ignores_another_carts_save_when_the_main_save_is_skipped() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();

		$session_property = new ReflectionProperty( WC_Cart::class, 'session' );
		$session_property->setAccessible( true );
		$cart_session = $session_property->getValue( WC()->cart );
		remove_action( 'woocommerce_after_calculate_totals', [ $cart_session, 'set_session' ], 1000 );
		$save_other_cart = $this->save_another_cart_with_old_totals();
		add_action( 'woocommerce_after_calculate_totals', $save_other_cart, self::OTHER_CART_SAVE_PRIORITY );

		try {
			$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );

			$this->assertEquals( 100, (float) WC()->session->get( 'cart_totals' )['total'] );
			$this->assertNull( WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			remove_action( 'woocommerce_after_calculate_totals', $save_other_cart, self::OTHER_CART_SAVE_PRIORITY );
			add_action( 'woocommerce_after_calculate_totals', [ $cart_session, 'set_session' ], 1000 );
			WC()->cart->empty_cart();
		}
	}

	/**
	 * When another cart's session save overwrites the main cart's saved totals later in the same calculation, the
	 * session no longer holds the totals this currency describes, so they must not be stamped.
	 */
	public function test_record_cart_totals_currency_ignores_totals_overwritten_after_the_main_save() {
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();
		$save_other_cart = $this->save_another_cart_with_old_totals();
		add_action( 'woocommerce_after_calculate_totals', $save_other_cart, self::OTHER_CART_SAVE_PRIORITY );

		try {
			$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );

			$this->assertNull( WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		} finally {
			remove_action( 'woocommerce_after_calculate_totals', $save_other_cart, self::OTHER_CART_SAVE_PRIORITY );
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Returns a callback that saves totals in the store currency to the session the way another cart's
	 * WC_Cart_Session::set_session() would, ending with `woocommerce_cart_updated`.
	 *
	 * @return callable
	 */
	private function save_another_cart_with_old_totals(): callable {
		return function () {
			$totals          = WC()->session->get( 'cart_totals' );
			$totals['total'] = self::STORE_CURRENCY_CART_TOTAL;
			WC()->session->set( 'cart_totals', $totals );
			do_action( 'woocommerce_cart_updated' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		};
	}

	/**
	 * Totals calculated for a cart other than the session's (e.g. one built by an extension) are not
	 * the saved totals, so they must not stamp the session.
	 */
	public function test_record_cart_totals_currency_skips_other_carts() {
		WC()->session->set( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY, Currency_Code::EURO );
		$other_cart = $this->createMock( WC_Cart::class );
		$other_cart->method( 'get_totals' )->willReturn( WC()->session->get( 'cart_totals' ) );

		$this->multi_currency->record_cart_totals_currency( $other_cart );

		$this->assertSame( 'EUR', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
	}

	/**
	 * Called without a cart while no session cart exists, the stamp must be left alone rather than
	 * dereferencing a missing cart.
	 */
	public function test_record_cart_totals_currency_skips_missing_cart() {
		$cart = WC()->cart;
		WC()->session->set( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY, Currency_Code::EURO );
		WC()->cart = null;

		try {
			$this->multi_currency->record_cart_totals_currency();
		} finally {
			WC()->cart = $cart;
		}

		$this->assertSame( 'EUR', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
	}

	/**
	 * Without a session there is nowhere to record the stamp, so the missing session must not be dereferenced.
	 */
	public function test_record_cart_totals_currency_skips_missing_session() {
		$session = WC()->session;
		WC()->session->set( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY, Currency_Code::EURO );
		WC()->session = null;

		try {
			$this->multi_currency->record_cart_totals_currency( WC()->cart );
		} finally {
			WC()->session = $session;
		}

		$this->assertSame( 'EUR', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
	}

	/**
	 * Removing the last item doesn't fire `woocommerce_cart_emptied`, so the next load of the empty cart drops the stamp.
	 */
	public function test_maybe_recalculate_cart_for_currency_clears_stamp_of_empty_cart() {
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();
		$this->assertSame( 'USD', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );

		try {
			WC()->cart->remove_cart_item( array_key_first( WC()->cart->get_cart() ) );
			$this->assertSame( 'USD', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );

			$this->multi_currency->maybe_recalculate_cart_for_currency();

			$this->assertNull( WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
			$this->assertNull( WC()->session->get( MultiCurrency::CART_TOTALS_HASH_SESSION_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * WooCommerce calculates the totals right after `woocommerce_cart_loaded_from_session` when the session
	 * has none (a new session or a restored persistent cart), so recalculating here would calculate twice.
	 */
	public function test_maybe_recalculate_cart_for_currency_skips_cart_without_saved_totals() {
		$this->add_product_to_cart();
		WC()->session->__unset( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY );
		WC()->session->set( 'cart_totals', null );

		try {
			$recalculations = $this->as_request(
				'rest_route',
				function () {
					return $this->count_recalculations_during(
						function () {
							$this->multi_currency->maybe_recalculate_cart_for_currency();
						}
					);
				}
			);

			$this->assertSame( 0, $recalculations );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	public function test_emptying_the_cart_clears_cart_totals_currency() {
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();
		$this->assertSame( 'USD', WC()->session->get( 'wcpay_cart_totals_currency' ) );

		WC()->cart->empty_cart();

		$this->assertNull( WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		$this->assertNull( WC()->session->get( MultiCurrency::CART_TOTALS_HASH_SESSION_KEY ) );
	}

	/**
	 * An empty cart is never stamped, so it must not be recalculated on every load. Counts
	 * `woocommerce_cart_reset`, as calculate_totals() returns before `woocommerce_after_calculate_totals`
	 * for an empty cart.
	 */
	public function test_maybe_recalculate_cart_for_currency_skips_empty_cart() {
		WC()->cart->empty_cart();
		$resets = did_action( 'woocommerce_cart_reset' );

		$this->multi_currency->maybe_recalculate_cart_for_currency();

		$this->assertSame( $resets, did_action( 'woocommerce_cart_reset' ) );
	}

	/**
	 * When the totals were already calculated in the currency now in effect, loading the cart must
	 * not recalculate them again. Guards the stamp-vs-currency comparison in
	 * maybe_recalculate_cart_for_currency(): dropping it would recalculate on every load.
	 */
	public function test_maybe_recalculate_cart_for_currency_leaves_matching_totals_untouched() {
		$this->add_product_to_cart();
		$this->multi_currency->record_cart_totals_currency( WC()->cart );
		$this->assertSame( get_woocommerce_currency(), WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );

		try {
			$recalculations = $this->as_request(
				'rest_route',
				function () {
					return $this->count_recalculations_during(
						function () {
							$this->multi_currency->maybe_recalculate_cart_for_currency();
						}
					);
				}
			);

			$this->assertSame( 0, $recalculations );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Shipping rates cached alongside stale totals can be stale too, and WooCommerce's package hash doesn't always tell:
	 * a rate cached in another currency by code without the currency marker (e.g. an older release the store was rolled
	 * back to) matches an unmarked store-currency package when the item amounts are equal. Storefront pages don't
	 * recalculate on load, so the cached rates are dropped instead, without calculating anything.
	 */
	public function test_maybe_recalculate_cart_for_currency_drops_shipping_rates_cached_with_stale_totals() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$this->register_cart_totals_currency_hooks();
		$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
		$this->add_product_to_cart();
		$totals          = WC()->session->get( 'cart_totals' );
		$totals['total'] = self::STORE_CURRENCY_CART_TOTAL;
		WC()->session->set( 'cart_totals', $totals );
		WC()->session->set( self::CACHED_SHIPPING_RATES_KEY, self::CACHED_SHIPPING_RATES );
		// Rates cached by earlier requests are in the stored session when the cart loads.
		WC()->session->save_data();

		try {
			$recalculations = $this->as_request(
				'storefront',
				function () {
					return $this->count_recalculations_during(
						function () {
							$this->multi_currency->maybe_recalculate_cart_for_currency();
						}
					);
				}
			);

			$this->assertSame( 0, $recalculations );
			$this->assertNull( WC()->session->get( self::CACHED_SHIPPING_RATES_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * A cart whose stamp stays stale (e.g. a plugin keeps it from being recorded) would otherwise lose its cached
	 * rates on every load and make WooCommerce ask live-rate carriers again on every calculation. The rates are
	 * dropped once per stale state, so rates rebuilt for the same stale totals stay cached.
	 */
	public function test_maybe_recalculate_cart_for_currency_drops_shipping_rates_once_per_stale_state() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$this->register_cart_totals_currency_hooks();
		$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
		$this->add_product_to_cart();
		$totals          = WC()->session->get( 'cart_totals' );
		$totals['total'] = self::STORE_CURRENCY_CART_TOTAL;
		WC()->session->set( 'cart_totals', $totals );
		WC()->session->set( self::CACHED_SHIPPING_RATES_KEY, self::CACHED_SHIPPING_RATES );
		WC()->session->save_data();
		$load_storefront = function () {
			$this->as_request(
				'storefront',
				function () {
					$this->multi_currency->maybe_recalculate_cart_for_currency();
				}
			);
		};

		try {
			$load_storefront();
			$this->assertNull( WC()->session->get( self::CACHED_SHIPPING_RATES_KEY ) );

			WC()->session->set( self::CACHED_SHIPPING_RATES_KEY, self::REBUILT_SHIPPING_RATES );
			WC()->session->save_data();
			$load_storefront();

			$this->assertSame( [ 'package_hash' => 'wc_ship_rebuilt' ], WC()->session->get( self::CACHED_SHIPPING_RATES_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Once the cart's totals are stamped again, a later stale state drops the cached rates again.
	 */
	public function test_record_cart_totals_currency_resets_the_dropped_rates_marker() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();
		WC()->session->set( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY, Currency_Code::EURO );
		WC()->session->set( self::CACHED_SHIPPING_RATES_KEY, self::CACHED_SHIPPING_RATES );
		WC()->session->save_data();

		try {
			$this->as_request(
				'storefront',
				function () {
					$this->multi_currency->maybe_recalculate_cart_for_currency();
				}
			);
			WC()->cart->calculate_totals();
			$this->assertSame( 'USD', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );

			WC()->session->set( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY, Currency_Code::EURO );
			WC()->session->set( self::CACHED_SHIPPING_RATES_KEY, self::REBUILT_SHIPPING_RATES );
			WC()->session->save_data();
			$this->as_request(
				'storefront',
				function () {
					$this->multi_currency->maybe_recalculate_cart_for_currency();
				}
			);

			$this->assertNull( WC()->session->get( self::CACHED_SHIPPING_RATES_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Rates cached with totals the stamp still describes stay cached, so loading the cart doesn't make WooCommerce
	 * ask live-rate carriers again.
	 */
	public function test_maybe_recalculate_cart_for_currency_keeps_shipping_rates_cached_with_current_totals() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$this->register_cart_totals_currency_hooks();
		$this->add_product_to_cart();
		WC()->session->set( self::CACHED_SHIPPING_RATES_KEY, self::CACHED_SHIPPING_RATES );
		WC()->session->save_data();

		try {
			$this->as_request(
				'storefront',
				function () {
					$this->multi_currency->maybe_recalculate_cart_for_currency();
				}
			);

			$this->assertSame( [ 'package_hash' => 'wc_ship_cached' ], WC()->session->get( self::CACHED_SHIPPING_RATES_KEY ) );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Requests on which Multi-Currency doesn't run (e.g. while the merchant has it switched off) save the cart
	 * totals in the store currency without touching the stamp. When the module is back on, the stamp still names
	 * the selected currency, so only the totals hash shows the saved totals are not the ones it was recorded for.
	 */
	public function test_maybe_recalculate_cart_for_currency_recalculates_totals_saved_without_multi_currency() {
		$this->register_cart_totals_currency_hooks();
		$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
		$this->add_product_to_cart();
		$this->assertSame( 'GBP', WC()->session->get( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY ) );
		$totals                        = WC()->session->get( 'cart_totals' );
		$totals['subtotal']            = self::STORE_CURRENCY_CART_TOTAL;
		$totals['cart_contents_total'] = self::STORE_CURRENCY_CART_TOTAL;
		$totals['total']               = self::STORE_CURRENCY_CART_TOTAL;
		WC()->session->set( 'cart_totals', $totals );

		try {
			$recalculations = $this->as_request(
				'rest_route',
				function () {
					return $this->count_recalculations_during(
						function () {
							$this->multi_currency->maybe_recalculate_cart_for_currency();
						}
					);
				}
			);

			$this->assertSame( 1, $recalculations );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Totals persisted before this change shipped carry no currency stamp. A non-empty such cart
	 * must still be recalculated into the selected currency on its next load, so the missing stamp
	 * is treated as stale rather than matching.
	 */
	public function test_maybe_recalculate_cart_for_currency_recalculates_unstamped_totals() {
		$this->add_product_to_cart();
		$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
		WC()->session->__unset( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY );

		try {
			$recalculations = $this->as_request(
				'rest_route',
				function () {
					return $this->count_recalculations_during(
						function () {
							$this->multi_currency->maybe_recalculate_cart_for_currency();
						}
					);
				}
			);

			$this->assertSame( 1, $recalculations );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * On REST requests the stale totals could be used without being recalculated, so a failing recalculation must fail
	 * the request rather than let it continue with the stale totals.
	 */
	public function test_failing_recalculation_fails_a_rest_request_with_stale_totals() {
		$this->add_product_to_cart();
		$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
		WC()->session->__unset( MultiCurrency::CART_TOTALS_CURRENCY_SESSION_KEY );
		$failing_fee = function () {
			throw new Exception( self::FAILING_FEE_MESSAGE );
		};
		add_action( 'woocommerce_cart_calculate_fees', $failing_fee );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Fee service unavailable' );

		try {
			$this->as_request(
				'rest_route',
				function () {
					$this->multi_currency->maybe_recalculate_cart_for_currency();
				}
			);
		} finally {
			remove_action( 'woocommerce_cart_calculate_fees', $failing_fee );
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Re-selecting the currency already in effect must not recalculate the loaded cart. Guards the
	 * $currency_changed check in update_selected_currency(): dropping it would recalculate on every
	 * call, even when nothing changed.
	 */
	public function test_update_selected_currency_does_not_recalculate_when_currency_is_unchanged() {
		$this->add_product_to_cart();
		$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
		$this->assertEqualsWithDelta( 70.90, (float) WC()->cart->get_totals()['total'], 0.001 );

		try {
			$recalculations = $this->count_recalculations_during(
				function () {
					$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
				}
			);

			$this->assertSame( 0, $recalculations );
		} finally {
			WC()->cart->empty_cart();
		}
	}

	public function test_update_selected_currency_by_url_does_not_set_session_when_parameter_not_set() {
		$this->multi_currency->update_selected_currency_by_url();

		$this->assertNull( WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_url_does_not_set_session_when_currency_not_enabled() {
		$_GET['currency'] = 'CLP';

		$this->multi_currency->update_selected_currency_by_url();

		$this->assertNull( WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_url_updates_session_when_currency_is_enabled() {
		$_GET['currency'] = 'GBP';

		$this->multi_currency->update_selected_currency_by_url();

		$this->assertSame( 'GBP', WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_geolocation_does_not_set_session_when_currency_not_enabled() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );

		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CL';
			}
		);

		$this->multi_currency->update_selected_currency_by_geolocation();

		$this->assertNull( WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_geolocation_does_not_set_session_cookie() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		$this->mock_utils
			->expects( $this->never() )
			->method( 'set_customer_session_cookie' );

		$this->multi_currency->update_selected_currency_by_geolocation();
	}

	public function test_update_selected_currency_by_geolocation_updates_session_when_currency_is_enabled() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );

		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		$this->multi_currency->update_selected_currency_by_geolocation();

		$this->assertSame( 'CAD', WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_geolocation_displays_notice() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );

		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		$this->multi_currency->update_selected_currency_by_geolocation();

		$this->assertNotFalse( has_filter( 'wp_footer', [ $this->multi_currency, 'display_geolocation_currency_update_notice' ] ) );
	}

	/**
	 * If compatibility->should_disable_currency_switching returns true, then we should not automatically change the customer currency
	 * or add the action that displays the notice that the currency was changed.
	 */
	public function test_update_selected_currency_by_geolocation_does_not_update_if_should_disable_currency_switching() {
		// Arrange: Update the option to enable to auto currency switching.
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );

		// Arrange: Add a filter to return a non US country.
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		// Arrange: Set the expected calls and returns for our mock classes.
		$this->mock_utils
			->expects( $this->never() )
			->method( 'set_customer_session_cookie' );

		// Arrange: Blatantly hack mock request params for the test.
		$_GET['pay_for_order'] = true;

		// Act: Call the tested method.
		$this->multi_currency->update_selected_currency_by_geolocation();

		// Assert: Confirm the session does not have a currency key set, and that the update notice action was not added.
		$this->assertNull( WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
		$this->assertFalse( has_filter( 'wp_footer', [ $this->multi_currency, 'display_geolocation_currency_update_notice' ] ) );
	}

	public function test_update_selected_currency_by_geolocation_skips_persistence_in_cache_mode_without_session() {
		// Enable cache-optimized mode and auto currency switching.
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( 'wcpay_multi_currency_rendering_mode', 'cache' );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		$this->init_multi_currency();

		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		$this->multi_currency->update_selected_currency_by_geolocation();

		// Without an active session, geolocation should be skipped entirely in cache mode.
		$this->assertNull( WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_geolocation_displays_notice_in_cache_mode_without_session() {
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( 'wcpay_multi_currency_rendering_mode', 'cache' );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		$this->init_multi_currency();

		$this->multi_currency->update_selected_currency_by_geolocation();

		$this->assertNotFalse( has_action( 'wp_footer', [ $this->multi_currency, 'display_geolocation_currency_update_notice' ] ) );
	}

	public function test_update_selected_currency_by_geolocation_persists_in_cache_mode_with_active_session() {
		// Enable cache-optimized mode and auto currency switching.
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( 'wcpay_multi_currency_rendering_mode', 'cache' );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		$this->init_multi_currency();

		// Simulate an active session (e.g. after add-to-cart) by setting the session cookie.
		$cookie_name             = apply_filters( 'woocommerce_cookie', 'wp_woocommerce_session_' . COOKIEHASH );
		$_COOKIE[ $cookie_name ] = 'test-session-id';

		try {
			add_filter(
				'woocommerce_geolocate_ip',
				function () {
					return 'CA';
				}
			);

			$this->multi_currency->update_selected_currency_by_geolocation();

			// With an active session, geolocation should persist the currency.
			$this->assertSame( 'CAD', WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
		} finally {
			unset( $_COOKIE[ $cookie_name ] );
		}
	}

	public function test_update_selected_currency_by_geolocation_persists_in_cache_mode_for_store_api_request() {
		// Enable cache-optimized mode and auto currency switching.
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( 'wcpay_multi_currency_rendering_mode', 'cache' );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		$this->init_multi_currency();

		// Save state — WC()->is_store_api_request() may swap the session handler.
		$original_request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$original_session     = WC()->session;

		try {
			// Simulate a Store API request (no session cookie, but Cart-Token based).
			$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/batch';

			add_filter(
				'woocommerce_geolocate_ip',
				function () {
					return 'CA';
				}
			);

			$this->multi_currency->update_selected_currency_by_geolocation();

			// Store API requests should persist geolocation currency even without a cookie session.
			$this->assertSame( 'CAD', WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
		} finally {
			// Restore state to avoid polluting subsequent tests.
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			WC()->session           = $original_session;
		}
	}

	/**
	 * @dataProvider provider_store_api_route_reached_through_another_url
	 */
	public function test_store_api_route_reached_through_another_url_uses_the_selected_currency( $request_uri, $route, $referer, $rendering_mode ) {
		// Clear the hooks of the instance created during the set up, to only see the ones of the instance below.
		remove_all_filters( 'woocommerce_currency' );
		remove_all_filters( 'woocommerce_product_get_price' );
		remove_all_filters( 'woocommerce_created_customer' );
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( 'wcpay_multi_currency_rendering_mode', $rendering_mode );
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::POUND_STERLING );

		$original_request_uri    = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI']  = $request_uri;
		$_SERVER['HTTP_REFERER'] = $referer;

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			// Going by the URL, this is a merchant's request, and other routes leave it at that.
			$this->match_rest_route( '/wc/v3/products' );
			$this->assertSame( 'USD', get_woocommerce_currency() );
			$this->assertSame( 'USD', $this->multi_currency->get_selected_currency()->get_code() );

			$response = $this->match_rest_route( $route );

			$this->assertNull( $response );
			$this->assertSame( 'GBP', get_woocommerce_currency() );
			$this->assertSame( 'GBP', $this->multi_currency->get_selected_currency()->get_code() );
			$this->assertTrue( $this->multi_currency->get_compatibility()->should_convert_product_price() );
			$this->assertSame( 99, has_filter( 'woocommerce_product_get_price', [ $this->multi_currency->get_frontend_prices(), 'get_product_price_string' ] ) );
			$this->assertSame( 10, has_action( 'woocommerce_created_customer', [ $this->multi_currency, 'set_new_customer_currency_meta' ] ) );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
		}
	}

	public function provider_store_api_route_reached_through_another_url() {
		$shop  = 'http://example.org/shop/';
		$admin = 'http://example.org/wp-admin/';

		return [
			'mixed-case route'                   => [ '/wp-json/wc/Store/v1/cart/update-item', '/wc/Store/v1/cart/update-item', $shop, 'speed' ],
			'rest_route parameter'               => [ '/wp-json/', '/wc/store/v1/cart/update-item', $shop, 'speed' ],
			'batched in batch/v1'                => [ '/wp-json/batch/v1', '/wc/store/v1/cart/update-item', $shop, 'speed' ],
			'batched in batch/v1, admin referer' => [ '/wp-json/batch/v1', '/wc/store/v1/cart/update-item', $admin, 'speed' ],
			'batched in batch/v1, cache mode'    => [ '/wp-json/batch/v1', '/wc/store/v1/cart/update-item', $shop, 'cache' ],
		];
	}

	/**
	 * @dataProvider provider_store_api_route_dispatched_through_another_url
	 */
	public function test_dispatched_store_api_route_runs_in_the_selected_currency( $request_uri, $referer, $dispatch ) {
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::POUND_STERLING );

		$original_request_uri    = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI']  = $request_uri;
		$_SERVER['HTTP_REFERER'] = $referer;

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			$seen = $this->dispatch_stub_routes( $dispatch );

			// Stub routes are registered in /wc/store/v1/ and /wc/v3/, the latter is what the merchant's requests use.
			$this->assertSame( 'GBP', $seen['store'] );
			// `merchant_before` is only dispatched for the batch sets (a /wc/v3 sub-request ahead of the store one).
			if ( 'batch' === $dispatch ) {
				$this->assertSame( 'USD', $seen['merchant_before'] );
			}
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			$this->reset_rest_server();
		}
	}

	public function provider_store_api_route_dispatched_through_another_url() {
		$shop  = 'http://example.org/shop/';
		$admin = 'http://example.org/wp-admin/';

		return [
			'mixed-case route'                   => [ '/wp-json/wc/Store/v1/mc-stub', $shop, 'direct' ],
			'mixed-case route, admin referer'    => [ '/wp-json/wc/Store/v1/mc-stub', $admin, 'direct' ],
			'batched in batch/v1'                => [ '/wp-json/batch/v1', $shop, 'batch' ],
			'batched in batch/v1, admin referer' => [ '/wp-json/batch/v1', $admin, 'batch' ],
		];
	}

	public function test_dispatched_store_api_route_applies_the_geolocated_currency_with_an_admin_referer() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'GB';
			}
		);

		$original_request_uri    = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI']  = '/wp-json/batch/v1';
		$_SERVER['HTTP_REFERER'] = 'http://example.org/wp-admin/';

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			// Nothing has selected a currency yet: going by the URL, `init_hooks()` didn't register the callbacks that do.
			$this->assertNull( WC()->session->get( MultiCurrency::CURRENCY_SESSION_KEY ) );

			$seen = $this->dispatch_stub_routes( 'batch' );

			$this->assertSame( 'GBP', WC()->session->get( MultiCurrency::CURRENCY_SESSION_KEY ) );
			$this->assertSame( 'GBP', $seen['store'] );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			$this->reset_rest_server();
		}
	}

	/**
	 * When `rest_route` points at the Store API batch endpoint, `Utils::is_store_batch_request()` is true. The restore
	 * hook must still engage on the batched Store API sub-routes, so the cart runs in the shopper's selected currency
	 * rather than the store default.
	 */
	public function test_dispatched_store_batch_route_via_rest_route_body_runs_in_the_selected_currency() {
		// Clear the hooks of the instance created during set up, so only the instance below drives the currency.
		remove_all_filters( 'woocommerce_currency' );
		remove_all_filters( 'woocommerce_product_get_price' );
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::POUND_STERLING );

		$original_request_uri    = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI']  = '/wp-json/';
		$_SERVER['HTTP_REFERER'] = 'http://example.org/wp-admin/';
		$_REQUEST['rest_route']  = '/wc/store/v1/batch';

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			$seen = $this->dispatch_stub_routes( 'batch' );

			// The restore hook engages on the batched Store API sub-routes even though `rest_route` made the request
			// look like a plain batch call, so the cart runs in the shopper's currency rather than the store default.
			$this->assertSame( 'GBP', $seen['store'] );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			unset( $_REQUEST['rest_route'] );
			Utils::set_matched_store_api_route( false );
			$this->reset_rest_server();
		}
	}

	/**
	 * In the cache-optimized mode a session-less guest reaching a Store API route through `batch/v1` without an admin
	 * referer must still get the geolocated currency, the same as a direct `/wc/store/` request does.
	 */
	public function test_dispatched_store_route_applies_geolocation_without_an_admin_referer_in_cache_mode() {
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( 'wcpay_multi_currency_rendering_mode', 'cache' );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'GB';
			}
		);

		$original_request_uri    = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI']  = '/wp-json/batch/v1';
		$_SERVER['HTTP_REFERER'] = 'http://example.org/shop/';

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			// Going by the URL, `init` chose async rendering, so no currency has been selected yet.
			$this->assertNull( WC()->session->get( MultiCurrency::CURRENCY_SESSION_KEY ) );

			$seen = $this->dispatch_stub_routes( 'batch' );

			$this->assertSame( 'GBP', WC()->session->get( MultiCurrency::CURRENCY_SESSION_KEY ) );
			$this->assertSame( 'GBP', $seen['store'] );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			Utils::set_matched_store_api_route( false );
			$this->reset_rest_server();
		}
	}

	/**
	 * The "matched a Store API route" flag must not outlive the request that set it: an internal `rest_do_request()`
	 * to a Store API route (e.g. WooPay building its session) should not leave the surrounding request reclassified.
	 */
	public function test_matched_store_api_route_flag_does_not_outlive_the_request() {
		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI'] = '/wp-json/'; // Not a Store API URL, so only the flag can make it look like one.

		$seen_during = null;

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			add_action(
				'rest_api_init',
				function () use ( &$seen_during ) {
					register_rest_route(
						'wc/store/v1',
						'/mc-reset-stub',
						[
							'methods'             => 'POST',
							'callback'            => function () use ( &$seen_during ) {
								$seen_during = Utils::is_store_api_request();
								return rest_ensure_response( [ 'ok' => true ] );
							},
							'permission_callback' => '__return_true',
						]
					);
				}
			);

			global $wp_rest_server;
			$wp_rest_server = null;
			rest_get_server();

			$this->assertFalse( Utils::is_store_api_request() );

			rest_do_request( new WP_REST_Request( 'POST', '/wc/store/v1/mc-reset-stub' ) );

			// The matched route itself ran as a Store API request...
			$this->assertTrue( $seen_during );
			// ...but the flag was reset once its callbacks finished, so the surrounding request is not reclassified.
			$this->assertFalse( Utils::is_store_api_request() );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			Utils::set_matched_store_api_route( false );
			$this->reset_rest_server();
		}
	}

	/**
	 * The inverse of {@see test_matched_store_api_route_flag_does_not_outlive_the_request}: when the matched Store API
	 * route is itself the outer request and its handler makes a nested `rest_do_request()`, that nested request
	 * completing first must not clear the flag while the outer route is still running. Only the request that set the
	 * flag clears it, when it finishes.
	 */
	public function test_matched_store_api_route_flag_survives_a_nested_request() {
		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI'] = '/wp-json/'; // Not a Store API URL, so only the flag can make it look like one.

		$seen_before_nested = null;
		$seen_after_nested  = null;

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			add_action(
				'rest_api_init',
				function () use ( &$seen_before_nested, &$seen_after_nested ) {
					register_rest_route(
						'wc/store/v1',
						'/mc-nested-inner',
						[
							'methods'             => 'GET',
							'callback'            => function () {
								return rest_ensure_response( [ 'ok' => true ] );
							},
							'permission_callback' => '__return_true',
						]
					);
					register_rest_route(
						'wc/store/v1',
						'/mc-nested-outer',
						[
							'methods'             => 'POST',
							'callback'            => function () use ( &$seen_before_nested, &$seen_after_nested ) {
								$seen_before_nested = Utils::is_store_api_request();
								// The nested Store API call doesn't re-arm the match (the flag already classifies the
								// request), so its `rest_request_after_callbacks` would reset the outer route's flag
								// unless the reset is scoped to the request that set it.
								rest_do_request( new WP_REST_Request( 'GET', '/wc/store/v1/mc-nested-inner' ) );
								$seen_after_nested = Utils::is_store_api_request();
								return rest_ensure_response( [ 'ok' => true ] );
							},
							'permission_callback' => '__return_true',
						]
					);
				}
			);

			global $wp_rest_server;
			$wp_rest_server = null;
			rest_get_server();

			$this->assertFalse( Utils::is_store_api_request() );

			rest_do_request( new WP_REST_Request( 'POST', '/wc/store/v1/mc-nested-outer' ) );

			// The matched outer route ran as a Store API request...
			$this->assertTrue( $seen_before_nested );
			// ...the nested request completing inside it did not clear the flag...
			$this->assertTrue( $seen_after_nested );
			// ...and the outer route's own completion did.
			$this->assertFalse( Utils::is_store_api_request() );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			Utils::set_matched_store_api_route( false );
			$this->reset_rest_server();
		}
	}

	/**
	 * A Store API route reached through a URL that didn't look like one must select the shopper's currency on the
	 * session that renders the response, which (for a Cart-Token request) loads after the restore handler runs. The
	 * handler arms a deferred selection on `woocommerce_cart_loaded_from_session`; firing that hook selects the
	 * geolocated currency, and `reset_matched_store_api_route()` detaches the callback once the route's callbacks
	 * finish so it does not outlive a route that never loads the cart.
	 */
	public function test_matched_store_api_route_defers_currency_selection_to_cart_load() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'GB';
			}
		);

		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI'] = '/wp-json/'; // Not a Store API URL, so only a matched route triggers the restore.

		$hook         = 'woocommerce_cart_loaded_from_session';
		$method       = 'select_currency_for_matched_store_api_route';
		$seen_armed   = null;
		$seen_at_load = null;

		// Whether any callback for the selection method is attached, regardless of the Multi-Currency instance that
		// armed it (the test's instance and the globally bootstrapped one both hook the request).
		$is_armed = function () use ( $hook, $method ) {
			if ( empty( $GLOBALS['wp_filter'][ $hook ] ) ) {
				return false;
			}
			foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $priority_callbacks ) {
				foreach ( $priority_callbacks as $registered ) {
					if ( is_array( $registered['function'] ) && ( $registered['function'][1] ?? '' ) === $method ) {
						return true;
					}
				}
			}
			return false;
		};

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			// Going by the `/wp-json/` URL, `init_hooks()` didn't register the callbacks that select a currency.
			$this->assertNull( WC()->session->get( MultiCurrency::CURRENCY_SESSION_KEY ) );

			add_action(
				'rest_api_init',
				function () use ( &$seen_armed, &$seen_at_load, $is_armed ) {
					register_rest_route(
						'wc/store/v1',
						'/mc-defer-stub',
						[
							'methods'             => 'GET',
							'callback'            => function () use ( &$seen_armed, &$seen_at_load, $is_armed ) {
								$seen_armed = $is_armed();
								// The restore handler already selected the geolocated currency inline. Clear it so the
								// value captured below can only come from the deferred callback firing on the cart-load
								// hook, as it would on the Cart-Token session that renders the response.
								WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, null );
								do_action( 'woocommerce_cart_loaded_from_session' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
								$seen_at_load = WC()->session->get( MultiCurrency::CURRENCY_SESSION_KEY );
								return rest_ensure_response( [ 'ok' => true ] );
							},
							'permission_callback' => '__return_true',
						]
					);
				}
			);

			global $wp_rest_server;
			$wp_rest_server = null;
			rest_get_server();

			$this->assertFalse( $is_armed() );

			rest_do_request( new WP_REST_Request( 'GET', '/wc/store/v1/mc-defer-stub' ) );

			// The restore handler for the matched route armed the deferred selection while its callbacks ran...
			$this->assertTrue( $seen_armed );
			// ...firing the cart-load hook ran it, selecting the geolocated currency on the loaded session...
			$this->assertSame( 'GBP', $seen_at_load );
			// ...and `reset_matched_store_api_route()` detached it once the callbacks finished.
			$this->assertFalse( $is_armed() );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			Utils::set_matched_store_api_route( false );
			$this->reset_rest_server();
		}
	}

	/**
	 * A Cart-Token session loads inside the route callback, after the restore handler has resolved the currency from
	 * the session before it. When that session holds its own currency, the route must report that one.
	 *
	 * @dataProvider provider_route_loading_a_cart_session_with_its_own_currency
	 */
	public function test_matched_store_api_route_uses_the_currency_of_the_session_loaded_with_the_cart( $route ) {
		$pre_dispatch_currency = Currency_Code::POUND_STERLING;
		$cart_token_currency   = Currency_Code::CANADIAN_DOLLAR;

		remove_all_filters( 'woocommerce_currency' );
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, $pre_dispatch_currency );

		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI'] = '/wp-json/'; // Not a Store API URL, so only a matched route triggers the restore.

		$seen_before_load = null;
		$seen_after_load  = null;

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			add_action(
				'rest_api_init',
				function () use ( &$seen_before_load, &$seen_after_load, $cart_token_currency ) {
					register_rest_route(
						'wc/store/v1',
						'/mc-session-stub',
						[
							'methods'             => 'GET',
							'callback'            => function () use ( &$seen_before_load, &$seen_after_load, $cart_token_currency ) {
								$seen_before_load = get_woocommerce_currency();
								WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, $cart_token_currency );
								do_action( 'woocommerce_cart_loaded_from_session' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
								$seen_after_load = get_woocommerce_currency();
								return rest_ensure_response( [ 'ok' => true ] );
							},
							'permission_callback' => '__return_true',
						]
					);
					register_rest_route(
						'mc-test/v1',
						'/session',
						[
							'methods'             => 'GET',
							'callback'            => function () {
								return rest_do_request( new WP_REST_Request( 'GET', '/wc/store/v1/mc-session-stub' ) );
							},
							'permission_callback' => '__return_true',
						]
					);
				}
			);

			global $wp_rest_server;
			$wp_rest_server = null;
			rest_get_server();

			rest_do_request( new WP_REST_Request( 'GET', $route ) );

			$this->assertSame( 'GBP', $seen_before_load );
			$this->assertSame( 'CAD', $seen_after_load );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			Utils::set_matched_store_api_route( false );
			$this->reset_rest_server();
		}
	}

	/**
	 * `init_hooks()` doesn't register the stale totals check for a Store API route that looks like an admin API
	 * request, so the restore handler has to, or the cart loads its old totals under the new currency.
	 */
	public function test_matched_store_api_route_recalculates_stale_cart_totals_under_an_admin_referer() {
		// The instance loaded with the plugin would otherwise convert the prices and run the check itself.
		remove_all_filters( 'woocommerce_currency' );
		remove_all_filters( 'woocommerce_product_get_price' );
		remove_all_actions( 'woocommerce_cart_loaded_from_session' );

		$original_request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$original_referer     = $_SERVER['HTTP_REFERER'] ?? null;

		$_SERVER['REQUEST_URI']  = '/wp-json/'; // Not a Store API URL, so only a matched route triggers the restore.
		$_SERVER['HTTP_REFERER'] = admin_url();

		$total_after_load = null;

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			$this->assertFalse( has_action( 'woocommerce_cart_loaded_from_session', [ $this->multi_currency, 'maybe_recalculate_cart_for_currency' ] ) );

			$this->add_product_to_cart();
			$this->with_cart_not_loaded(
				function () {
					$this->multi_currency->update_selected_currency( Currency_Code::POUND_STERLING );
				}
			);
			$this->assertEquals( 100, (float) WC()->session->get( 'cart_totals' )['total'] );

			add_action(
				'rest_api_init',
				function () use ( &$total_after_load ) {
					register_rest_route(
						'wc/store/v1',
						'/mc-stale-totals-stub',
						[
							'methods'             => 'GET',
							'callback'            => function () use ( &$total_after_load ) {
								$this->reload_cart_from_session();
								$total_after_load = (float) WC()->cart->get_totals()['total'];
								return rest_ensure_response( [ 'ok' => true ] );
							},
							'permission_callback' => '__return_true',
						]
					);
				}
			);

			global $wp_rest_server;
			$wp_rest_server = null;
			rest_get_server();

			rest_do_request( new WP_REST_Request( 'GET', '/wc/store/v1/mc-stale-totals-stub' ) );

			// 100 USD at 0.708099, rounded up to 0.50 and charmed by -0.1.
			$this->assertEqualsWithDelta( 70.90, $total_after_load, 0.001 );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			if ( null === $original_referer ) {
				unset( $_SERVER['HTTP_REFERER'] );
			} else {
				$_SERVER['HTTP_REFERER'] = $original_referer;
			}
			Utils::set_matched_store_api_route( false );
			$this->reset_rest_server();
			WC()->cart->empty_cart();
		}
	}

	public function provider_route_loading_a_cart_session_with_its_own_currency() {
		return [
			'matched directly'               => [ '/wc/store/v1/mc-session-stub' ],
			'nested under a non-Store route' => [ '/mc-test/v1/session' ],
		];
	}

	/**
	 * @dataProvider provider_store_api_route_dispatched_on_a_non_rest_url
	 */
	public function test_store_api_route_dispatched_on_a_non_rest_url( $rest_route_in_body, $expected_store_api_request, $expected_currency_after_load ) {
		$pre_dispatch_currency = Currency_Code::POUND_STERLING;
		$cart_token_currency   = Currency_Code::CANADIAN_DOLLAR;

		remove_all_filters( 'woocommerce_currency' );
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( 'wcpay_multi_currency_rendering_mode', 'cache' );
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, $pre_dispatch_currency );

		$original_request_uri = $_SERVER['REQUEST_URI'] ?? '';

		$_SERVER['REQUEST_URI'] = '/';
		$_POST['rest_route']    = $rest_route_in_body;

		$seen_store_api_request = null;
		$seen_after_load        = null;

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			add_action(
				'rest_api_init',
				function () use ( &$seen_store_api_request, &$seen_after_load, $cart_token_currency ) {
					register_rest_route(
						'wc/store/v1',
						'/mc-non-rest-url-stub',
						[
							'methods'             => 'POST',
							'callback'            => function () use ( &$seen_store_api_request, &$seen_after_load, $cart_token_currency ) {
								$seen_store_api_request = Utils::is_store_api_request();
								get_woocommerce_currency();
								WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, $cart_token_currency );
								do_action( 'woocommerce_cart_loaded_from_session' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
								$seen_after_load = get_woocommerce_currency();
								return rest_ensure_response( [ 'ok' => true ] );
							},
							'permission_callback' => '__return_true',
						]
					);
				}
			);

			global $wp_rest_server;
			$wp_rest_server = null;
			rest_get_server();

			rest_do_request( new WP_REST_Request( 'POST', '/wc/store/v1/mc-non-rest-url-stub' ) );

			$this->assertSame( $expected_store_api_request, $seen_store_api_request );
			$this->assertSame( $expected_currency_after_load, $seen_after_load );
			$this->assertFalse( Utils::is_store_api_request() );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			unset( $_POST['rest_route'] );
			Utils::set_matched_store_api_route( false );
			$this->reset_rest_server();
		}
	}

	public function provider_store_api_route_dispatched_on_a_non_rest_url() {
		return [
			'rest_route in the request'          => [ '/wc/store/v1/mc-non-rest-url-stub', true, 'CAD' ],
			'internal dispatch on a normal page' => [ '', false, 'USD' ],
		];
	}

	/**
	 * A storefront REST request can carry `?currency=`, which `update_selected_currency_by_url()` persists to the
	 * session at `init:11` before the route is matched. The matched-route restore removes the REST overrides but does
	 * not (and cannot) undo that already-persisted value, so the Store API route runs in the query-param currency
	 * rather than a prior session currency.
	 */
	public function test_store_api_route_reached_through_another_url_keeps_the_query_param_currency() {
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::POUND_STERLING );

		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI'] = '/wp-json/batch/v1?currency=cad';
		$_GET['currency']       = 'cad';

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();

			// A storefront REST request (non-admin Referer) registers the URL currency handler at `init:11`.
			$this->assertSame( 11, has_action( 'init', [ $this->multi_currency, 'update_selected_currency_by_url' ] ) );

			// Run the callbacks in the order the `init` action would: init() at 10, then the URL handler at 11.
			$this->multi_currency->init();
			$this->multi_currency->update_selected_currency_by_url();
			$this->assertSame( 'CAD', $this->multi_currency->get_selected_currency()->get_code() );

			$this->match_rest_route( '/wc/store/v1/cart/update-item' );

			// The store-currency REST override is gone, but `?currency=cad` was already persisted to the session at
			// init:11, so the Store API route runs in CAD; the restore does not revert to the prior GBP session value.
			$this->assertSame( 'CAD', $this->multi_currency->get_selected_currency()->get_code() );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
			unset( $_GET['currency'] );
		}
	}

	public function test_store_api_route_reached_through_another_url_leaves_disabled_module_inert() {
		remove_all_filters( 'woocommerce_currency' );
		update_option( '_wcpay_feature_customer_multi_currency', '0' );

		$original_request_uri   = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI'] = '/wp-json/batch/v1';

		try {
			$this->init_multi_currency( null, true, null, null, null, false );
			$this->multi_currency->init_hooks();
			$this->multi_currency->init();

			$this->match_rest_route( '/wc/store/v1/cart/update-item' );

			$this->assertFalse( has_filter( 'woocommerce_currency' ) );
		} finally {
			$_SERVER['REQUEST_URI'] = $original_request_uri;
		}
	}

	public function test_display_geolocation_currency_update_notice() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'CAD' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		$this->multi_currency->display_geolocation_currency_update_notice();

		$this->expectOutputRegex( '/woocommerce-store-notice.+visiting from Canada.+\?currency=USD.+Use United States \(US\) dollar instead/' );
	}

	public function test_display_geolocation_currency_update_notice_does_not_display_if_using_default_currency() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'US' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'US';
			}
		);

		$this->multi_currency->display_geolocation_currency_update_notice();

		$this->expectOutputString( '' );
	}

	public function test_display_geolocation_currency_update_notice_does_not_display_if_using_other_currency_than_geolocated() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'CAD' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'US';
			}
		);

		$this->multi_currency->display_geolocation_currency_update_notice();

		$this->expectOutputString( '' );
	}

	public function test_display_geolocation_currency_update_notice_prints_a_template_in_cache_mode_without_session() {
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( 'wcpay_multi_currency_rendering_mode', 'cache' );
		$this->init_multi_currency();
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		ob_start();
		$this->multi_currency->display_geolocation_currency_update_notice();
		$template = ob_get_clean();

		$this->assertStringStartsWith( '<template id="wcpay-geolocation-notice"><p class="woocommerce-store-notice', $template );
		$this->assertStringContainsString( 'visiting from <span data-wcpay-notice-country></span>.', $template );
		$this->assertStringContainsString( 'prices to <span data-wcpay-notice-currency></span> for', $template );
		$this->assertStringContainsString( 'href="?currency=USD">Use United States (US) dollar instead.</a>', $template );
		$this->assertStringContainsString( 'data-notice-id=""', $template );
		$this->assertStringNotContainsString( 'Canada', $template );
	}

	public function test_get_public_config_includes_geolocation_notice_with_the_server_rendered_notice_id() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, 'CAD' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		ob_start();
		$this->multi_currency->display_geolocation_currency_update_notice();
		preg_match( '/data-notice-id="([^"]+)"/', ob_get_clean(), $matches );
		$config = $this->multi_currency->get_public_config();

		$this->assertSame( 'CAD', $config['selected_currency'] );
		$this->assertSame(
			[
				'country_name'  => 'Canada',
				'currency_name' => 'Canadian dollar',
				'id'            => $matches[1],
			],
			$config['geolocation_notice']
		);
	}

	public function test_get_public_config_applies_notice_name_filters() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);
		$override_country  = function () {
			return 'Great White North';
		};
		$override_currency = function () {
			return 'loonies';
		};
		add_filter( 'wcpay_multi_currency_override_notice_country', $override_country );
		add_filter( 'wcpay_multi_currency_override_notice_currency_name', $override_currency );

		$config = $this->multi_currency->get_public_config();

		remove_filter( 'wcpay_multi_currency_override_notice_country', $override_country );
		remove_filter( 'wcpay_multi_currency_override_notice_currency_name', $override_currency );

		$this->assertSame( 'Great White North', $config['geolocation_notice']['country_name'] );
		$this->assertSame( 'loonies', $config['geolocation_notice']['currency_name'] );
	}

	public function test_get_public_config_turns_a_null_notice_name_into_an_empty_string() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);
		add_filter( 'wcpay_multi_currency_override_notice_country', '__return_null' );

		try {
			$config = $this->multi_currency->get_public_config();
		} finally {
			remove_filter( 'wcpay_multi_currency_override_notice_country', '__return_null' );
		}

		$this->assertSame( '', $config['geolocation_notice']['country_name'] );
	}

	public function test_get_public_config_decodes_notice_names_without_changing_the_notice_id() {
		update_option( self::ENABLED_CURRENCIES_OPTION, array_merge( $this->mock_enabled_currencies, [ Currency_Code::EURO ] ) );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		$this->init_multi_currency();
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::EURO );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'BL';
			}
		);
		$override_currency = function () {
			return 'Euro &amp; cents';
		};
		add_filter( 'wcpay_multi_currency_override_notice_currency_name', $override_currency );

		try {
			ob_start();
			$this->multi_currency->display_geolocation_currency_update_notice();
			preg_match( '/data-notice-id="([^"]+)"/', ob_get_clean(), $matches );
			$config = $this->multi_currency->get_public_config();
		} finally {
			remove_filter( 'wcpay_multi_currency_override_notice_currency_name', $override_currency );
		}

		$this->assertSame( 'Saint Barthélemy', $config['geolocation_notice']['country_name'] );
		$this->assertSame( 'Euro & cents', $config['geolocation_notice']['currency_name'] );
		$this->assertSame( $matches[1], $config['geolocation_notice']['id'] );
	}

	public function test_get_public_config_has_no_geolocation_notice_when_auto_switching_is_off() {
		// WooCommerce counts a logged-in user as having a session, so the config reads the stored
		// CAD and only the auto-switching check keeps the notice out.
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, 'CAD' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		$config = $this->multi_currency->get_public_config();

		$this->assertNull( $config['geolocation_notice'] );
	}

	public function test_get_public_config_has_no_geolocation_notice_for_the_store_currency() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'US';
			}
		);

		$config = $this->multi_currency->get_public_config();

		$this->assertSame( 'USD', $config['selected_currency'] );
		$this->assertNull( $config['geolocation_notice'] );
	}

	public function test_get_public_config_has_no_geolocation_notice_for_a_currency_other_than_the_geolocated_one() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		// WooCommerce counts a logged-in user as having a session, so the config reads the
		// currency the visitor picked instead of the geolocated one.
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter(
			'woocommerce_geolocate_ip',
			function () {
				return 'CA';
			}
		);

		$config = $this->multi_currency->get_public_config();

		$this->assertSame( 'GBP', $config['selected_currency'] );
		$this->assertNull( $config['geolocation_notice'] );
	}

	public function test_get_price_returns_price_in_default_currency() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, get_woocommerce_currency() );

		$this->assertSame( 5.0, $this->multi_currency->get_price( '5.0', 'product' ) );
	}

	public function test_get_price_returns_price_if_unsupported_type() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, get_woocommerce_currency() );

		$this->assertSame( 5.0, $this->multi_currency->get_price( '5.0', 'unsupported_type' ) );
	}

	public function test_get_price_returns_converted_product_price_with_charm() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_true' );

		// 0.708099 * 10 = 7,08099 -> ceiled to 7.5 -> 7.5 - 0.1 = 7.4
		$this->assertSame( 7.4, $this->multi_currency->get_price( '10.0', 'product' ) );
	}

	public function test_get_price_returns_converted_shipping_price_with_charm() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7,08099 -> ceiled to 7.5 -> 7.5 - 0.1 = 7.4
		$this->assertSame( 7.4, $this->multi_currency->get_price( '10.0', 'shipping' ) );
	}

	public function test_get_price_returns_converted_shipping_price_without_charm() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_true' );

		// 0.708099 * 10 = 7,08099 -> ceiled to 7.5 -> 7.5 + 0.0 = 7.5
		$this->assertSame( 7.5, $this->multi_currency->get_price( '10.0', 'shipping' ) );
	}

	/**
	 * A zero-priced shippable product keeps every cart line total the same in both currencies, so
	 * WooCommerce's shipping-package hash only changes on a switch because of the Multi-Currency marker.
	 */
	public function test_shipping_rate_is_rebuilt_after_currency_switch() {
		$product = WC_Helper_Product::create_simple_product( false );
		$product->set_regular_price( 0 );
		$product->save();

		update_option(
			'woocommerce_flat_rate_settings',
			[
				'enabled'      => 'yes',
				'title'        => 'Flat rate',
				'availability' => 'all',
				'countries'    => '',
				'tax_status'   => 'none',
				'cost'         => '20',
			]
		);
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->shipping()->load_shipping_methods();

		try {
			WC()->cart->add_to_cart( $product->get_id(), 1 );

			// Build and cache the rate while USD is selected.
			WC()->cart->calculate_totals();
			$this->assertSame( 20.0, (float) WC()->cart->get_shipping_total() );

			$this->multi_currency->update_selected_currency( Currency_Code::CANADIAN_DOLLAR );
			WC()->cart->calculate_totals();

			// ceil( 20 * 1.206823 ) = 25.
			$this->assertSame( 25.0, (float) WC()->cart->get_shipping_total() );
		} finally {
			WC()->cart->empty_cart();
			WC()->session->__unset( 'chosen_shipping_methods' );
			// The flat-rate method stays registered on the WC_Shipping singleton, which the DB rollback does not reset.
			WC_Helper_Shipping::delete_simple_flat_rate();
		}
	}

	public function test_get_price_returns_converted_coupon_price_without_adjustments() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7.08099.
		// round( 7.08099, 2 ) = 7.08.
		$this->assertSame( 7.08, $this->multi_currency->get_price( '10.0', 'coupon' ) );
	}

	public function test_get_price_converts_to_the_given_currency_instead_of_the_selected_one() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );

		$this->assertSame( 10.0, $this->multi_currency->get_price( '10.0', 'coupon', $this->multi_currency->get_default_currency() ) );

		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, get_woocommerce_currency() );

		// 0.708099 * 10 = 7.08099.
		// round( 7.08099, 2 ) = 7.08.
		$this->assertSame( 7.08, $this->multi_currency->get_price( '10.0', 'coupon', $this->multi_currency->get_enabled_currencies()['GBP'] ) );
	}

	public function test_get_price_returns_converted_exchange_rate_without_adjustments() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7.08099.
		// round( 7.08099, 2 ) = 7.08.
		$this->assertSame( 7.08, $this->multi_currency->get_price( '10.0', 'exchange_rate' ) );
	}

	public function test_get_price_returns_converted_tax_price() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7.08099.
		// round( 7.08099, 2 ) = 7.08.
		$this->assertSame( 7.08, $this->multi_currency->get_price( '10.0', 'tax' ) );
	}

	/**
	 * @dataProvider get_price_provider
	 */
	public function test_get_price_converts_using_ceil_and_precision( $target_price, $precision, $expected ) {
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_true' );
		$this->mock_currency_settings(
			'GBP',
			[
				'price_rounding' => $precision,
				'exchange_rate'  => 'manual',
				'manual_rate'    => $target_price,
			]
		);

		$this->init_multi_currency();

		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );

		$this->assertSame( $expected, $this->multi_currency->get_price( 1, 'shipping' ) );
	}

	/**
	 * @dataProvider get_raw_conversion_provider
	 */
	public function test_get_raw_conversion( $amount, $to_currency, $from_currency ) {
		// Arrange: Get the expected amount.
		$expected = $amount;
		if ( '' !== $from_currency ) {
			$expected = $expected * ( 1 / $this->mock_available_currencies[ $from_currency ] );
		}
		$expected = $expected * $this->mock_available_currencies[ $to_currency ];

		// Act/Assert: Confirm the expected amount is returned.
		$this->assertSame( $expected, $this->multi_currency->get_raw_conversion( $amount, $to_currency, $from_currency ) );
	}

	public function get_raw_conversion_provider() {
		return [
			'CAD'     => [ 10.00, 'CAD', '' ],
			'GBP CAD' => [ 10.00, 'GBP', 'CAD' ],
			'CAD GBP' => [ 10.00, 'CAD', 'GBP' ],
		];
	}

	/**
	 * @dataProvider get_raw_conversion_exception_provider
	 */
	public function test_get_raw_conversion_throws_exception_on_unavailable_currency( $amount, $to_currency, $from_currency ) {
		// Arrange/Assert: Set expected exception and message.
		$this->expectException( InvalidCurrencyException::class );
		$this->expectExceptionMessage( 'Invalid currency passed to get_raw_conversion: BANANA' );

		// Act: Attempt to get the conversion.
		$this->multi_currency->get_raw_conversion( $amount, $to_currency, $from_currency );
	}

	public function get_raw_conversion_exception_provider() {
		return [
			'CAD banana' => [ 10.00, 'CAD', 'banana' ],
			'banana CAD' => [ 10.00, 'banana', 'CAD' ],
		];
	}

	public function test_get_raw_conversion_throws_exception_on_invalid_from_rate() {
		// Arrange: Update a valid currency to be enabled and have a zero conversion rate.
		$this->mock_enabled_currencies[]        = 'BAM';
		$this->mock_available_currencies['BAM'] = 0;

		// Arrange: Add the new available currencies to the cache.
		$this->mock_cached_currencies = [
			'currencies' => $this->mock_available_currencies,
			'updated'    => $this->timestamp_for_testing,
			'expires'    => $this->timestamp_for_testing + DAY_IN_SECONDS,
		];

		// Arrange: Update the enabled currencies in the db and init MC again.
		update_option( self::ENABLED_CURRENCIES_OPTION, $this->mock_enabled_currencies );
		$this->init_multi_currency();

		// Arrange/Assert: Set expected exception and message.
		$this->expectException( InvalidCurrencyRateException::class );
		$this->expectExceptionMessage( 'Invalid rate for from_currency in get_raw_conversion: 0' );

		// Act: Attempt to get the conversion.
		$this->multi_currency->get_raw_conversion( 10, 'CAD', 'BAM' );
	}

	public function test_get_cached_currencies_with_no_server_connection() {
		// Need to create a new instance of MultiCurrency with a different $mock_api_client
		// Because the mock return value of 'is_server_connected' cannot be overridden.
		$mock_api_client = $this->createMock( MultiCurrencyApiClientInterface::class );

		$mock_api_client->method( 'is_server_connected' )->willReturn( false );

		$this->init_multi_currency( $mock_api_client );

		$this->mock_cache->method( 'get' )->willReturn( $this->mock_cached_currencies );

		$this->assertEquals(
			$this->mock_cached_currencies,
			$this->multi_currency->get_cached_currencies()
		);
	}

	public function test_get_cached_currencies_with_account_rejected() {
		$this->mock_cache
			->expects( $this->once() )
			->method( 'get' )
			->willReturn( null );

		$this->mock_account
			->expects( $this->once() )
			->method( 'is_account_rejected' )
			->willReturn( true );

		$this->mock_cache
			->expects( $this->never() )
			->method( 'get_or_add' );

		$this->assertEquals(
			null,
			$this->multi_currency->get_cached_currencies()
		);
	}

	public function test_get_cached_currencies_fetches_from_server() {
		$get_or_add_call_count = 1;
		$mock_cache            = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache
			->expects( $this->exactly( 2 ) )
			->method( 'get_or_add' )
			->with( MultiCurrencyCacheInterface::CURRENCIES_KEY, $this->anything(), $this->anything() )
			->willReturnCallback(
				function ( $key, $generator, $_unused_validator ) use ( &$get_or_add_call_count ) {
					if ( 1 === $get_or_add_call_count ) {
						// Call that happens inside the init function in MultiCurrency, still use cached data.
						$get_or_add_call_count++;
						return $this->mock_cached_currencies;
					} else {
						// Second call from get_cached_currencies below.
						return $generator();
					}
				}
			);

		$this->init_multi_currency( null, true, null, $mock_cache );

		$currency_from = strtolower( get_woocommerce_currency() );
		$currencies_to = get_woocommerce_currencies();
		unset( $currencies_to[ $currency_from ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_currency_rates' )
			->with( $currency_from )
			->willReturn( $this->mock_available_currencies );

		$result = $this->multi_currency->get_cached_currencies();

		// Assert that the currencies and the time updated were returned.
		$this->assertArrayHasKey( 'currencies', $result );
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertEquals(
			$this->mock_available_currencies,
			$result['currencies']
		);
	}

	public function test_get_cached_currencies_uses_store_currency_when_woocommerce_currency_is_filtered() {
		$get_or_add_call_count = 1;
		$mock_cache            = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache
			->expects( $this->exactly( 2 ) )
			->method( 'get_or_add' )
			->with( MultiCurrencyCacheInterface::CURRENCIES_KEY, $this->anything(), $this->anything() )
			->willReturnCallback(
				function ( $key, $generator, $_unused_validator ) use ( &$get_or_add_call_count ) {
					if ( 1 === $get_or_add_call_count ) {
						// Call that happens inside the init function in MultiCurrency, still use cached data.
						$get_or_add_call_count++;
						return $this->mock_cached_currencies;
					}

					// Second call from get_cached_currencies below.
					return $generator();
				}
			);

		update_option( 'woocommerce_currency', 'USD' );
		update_option( 'wcpay_multi_currency_store_currency', 'USD' );
		remove_all_filters( 'woocommerce_currency' );
		add_filter( 'woocommerce_currency', fn() => 'EUR', 999 );

		$this->init_multi_currency( null, true, null, $mock_cache );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_currency_rates' )
			->with( 'usd' )
			->willReturn( $this->mock_available_currencies );

		$this->multi_currency->get_cached_currencies();
	}

	public function test_storefront_integration_init_with_compatible_themes() {
		// Need to create a new instance of MultiCurrency to re-evaluate new theme.
		$this->mock_theme( 'storefront' );

		$this->init_multi_currency();
		$this->assertNotNull( $this->multi_currency->get_storefront_integration() );
	}

	public function test_storefront_integration_does_not_init_with_incompatible_themes() {
		// Need to create a new instance of MultiCurrency to re-evaluate new theme.
		$this->mock_theme( 'not_storefront' );

		$this->init_multi_currency();
		$this->assertNull( $this->multi_currency->get_storefront_integration() );
	}

	public function test_add_order_meta_on_refund_skips_default_currency() {
		$order = wc_create_order();
		$order->set_currency( 'USD' );

		$refund = wc_create_refund( [ 'order_id' => $order->get_id() ] );
		$refund->set_currency( 'USD' );

		$this->multi_currency->add_order_meta_on_refund( $order->get_id(), $refund->get_id() );

		// Get the order from the database.
		$refund = wc_get_order( $refund->get_id() );

		$this->assertFalse( $refund->meta_exists( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertFalse( $refund->meta_exists( '_wcpay_multi_currency_order_default_currency' ) );
	}

	public function test_add_order_meta_on_refund_with_no_stripe_exchange_rate() {
		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$order->save();

		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', '0.71' );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'USD' );
		$order->save_meta_data();

		$refund = wc_create_refund( [ 'order_id' => $order->get_id() ] );
		$refund->set_currency( 'GBP' );
		$refund->save();

		$this->multi_currency->add_order_meta_on_refund( $order->get_id(), $refund->get_id() );

		// Get the order from the database.
		$refund = wc_get_order( $refund->get_id() );

		$this->assertEquals( '0.71', $refund->get_meta( '_wcpay_multi_currency_order_exchange_rate', true ) );
		$this->assertEquals( 'USD', $refund->get_meta( '_wcpay_multi_currency_order_default_currency', true ) );
		$this->assertFalse( $refund->meta_exists( '_wcpay_multi_currency_stripe_exchange_rate' ) );
	}

	public function test_add_order_meta_on_refund_skips_order_without_multi_currency_rate() {
		// An order Multi-Currency did not convert, such as one placed through a third-party currency
		// switcher, can still carry the Stripe rate from its charge.
		$order = wc_create_order();
		$order->set_currency( 'EUR' );
		$order->save();

		$order->update_meta_data( '_wcpay_multi_currency_stripe_exchange_rate', '1.11718' );
		$order->save_meta_data();

		$refund = wc_create_refund( [ 'order_id' => $order->get_id() ] );
		$refund->set_currency( 'EUR' );
		$refund->save();

		$this->multi_currency->add_order_meta_on_refund( $order->get_id(), $refund->get_id() );

		// Get the order from the database.
		$refund = wc_get_order( $refund->get_id() );

		$this->assertFalse( $refund->meta_exists( '_wcpay_multi_currency_order_exchange_rate' ) );
		$this->assertFalse( $refund->meta_exists( '_wcpay_multi_currency_order_default_currency' ) );
		$this->assertFalse( $refund->meta_exists( '_wcpay_multi_currency_stripe_exchange_rate' ) );
	}

	public function test_add_order_meta_on_refund() {
		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$order->save();

		$order->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', '0.71' );
		$order->update_meta_data( '_wcpay_multi_currency_stripe_exchange_rate', '0.724' );
		$order->update_meta_data( '_wcpay_multi_currency_order_default_currency', 'USD' );
		$order->save_meta_data();

		$refund = wc_create_refund( [ 'order_id' => $order->get_id() ] );
		$refund->set_currency( 'GBP' );
		$refund->save();

		$this->multi_currency->add_order_meta_on_refund( $order->get_id(), $refund->get_id() );

		// Get the order from the database.
		$refund = wc_get_order( $refund->get_id() );

		$this->assertEquals( '0.71', $refund->get_meta( '_wcpay_multi_currency_order_exchange_rate', true ) );
		$this->assertEquals( 'USD', $refund->get_meta( '_wcpay_multi_currency_order_default_currency', true ) );
		$this->assertEquals( '0.724', $refund->get_meta( '_wcpay_multi_currency_stripe_exchange_rate', true ) );
	}

	public function test_init_rest_api_registers_routes_when_admin_screen_is_set() {
		global $wp_rest_server, $current_screen;
		$previous_server = $wp_rest_server;
		$previous_screen = $current_screen;
		$wp_rest_server  = null;

		set_current_screen( 'edit-page' );

		try {
			$routes = rest_get_server()->get_routes( 'wc/v3' );
			$this->assertArrayHasKey( '/wc/v3/payments/multi-currency/currencies', $routes );
		} finally {
			$wp_rest_server = $previous_server;
			$current_screen = $previous_screen;
		}
	}

	public function test_enabled_currencies_option_as_string_does_not_fatal() {
		update_option( 'wcpay_multi_currency_enabled_currencies', '' );
		$this->multi_currency->init();
		$this->assertEquals( '', get_option( 'wcpay_multi_currency_enabled_currencies', false ) );
	}

	public function test_get_cached_currencies_with_no_stripe_connection() {
		$this->init_multi_currency( null, false );
		$this->mock_cache->method( 'get' )->willReturn( $this->mock_cached_currencies );
		$this->assertEquals(
			$this->mock_cached_currencies,
			$this->multi_currency->get_cached_currencies()
		);
	}

	public function test_get_available_currencies_returns_store_currency_with_no_stripe_connection() {
		$expected = [
			'USD' => new WCPay\MultiCurrency\Currency( $this->localization_service, 'USD', 1 ),
		];
		$this->init_multi_currency( null, false );
		$this->assertEquals( $expected, $this->multi_currency->get_available_currencies() );
	}

	public function test_get_switcher_widget_markup() {
		$expected = '<div class="widget ">		<form>
						<select
				name="currency"
				class="js-woopayments-currency-switcher"
				aria-label="Select your currency"
				onchange="this.form.submit()"
			>
				<option value="USD" selected>&#36; USD</option><option value="BIF">Fr BIF</option><option value="CAD">&#36; CAD</option><option value="GBP">&pound; GBP</option>			</select>
		</form>
		</div>';

		$this->assertEquals( $expected, $this->multi_currency->get_switcher_widget_markup() );
	}

	public function test_get_switcher_widget_markup_when_widget_instance_is_null() {
		$mock_multi_currency = $this
			->getMockBuilder( WCPay\MultiCurrency\MultiCurrency::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_currency_switcher_widget' ] )
			->getMock();

		$mock_multi_currency->method( 'get_currency_switcher_widget' )->willReturn( null );

		$this->assertEquals( '', $mock_multi_currency->get_switcher_widget_markup() );
	}

	public function test_validate_currency_code_returns_existing_currency_code() {
		$this->assertEquals( 'CAD', $this->multi_currency->validate_currency_code( 'CAD' ) );
		$this->assertEquals( 'CAD', $this->multi_currency->validate_currency_code( 'cAd' ) );
		$this->assertEquals( 'CAD', $this->multi_currency->validate_currency_code( 'cad' ) );
	}

	public function test_validate_currency_code_returns_false_on_non_matching_currency_code() {
		$this->assertEquals( false, $this->multi_currency->validate_currency_code( 'XXX' ) );
		$this->assertEquals( false, $this->multi_currency->validate_currency_code( 'YYY' ) );
	}

	public function test_is_simulation_enabled() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$this->assertFalse( $this->multi_currency->is_simulation_enabled() );
		$_GET = [
			'is_mc_onboarding_simulation' => true,
			'enable_storefront_switcher'  => true,
		];
		$this->multi_currency->possible_simulation_activation();
		$this->assertTrue( $this->multi_currency->is_simulation_enabled() );
	}

	public function test_get_multi_currency_onboarding_simulation_variables_for_a_shop_manager() {
		$shop_manager_id = self::factory()->user->create( [ 'role' => 'shop_manager' ] );
		wp_set_current_user( $shop_manager_id );
		$_GET = [
			'is_mc_onboarding_simulation' => true,
			'enable_storefront_switcher'  => true,
			'enable_auto_currency'        => true,
		];

		$this->assertSame(
			[
				'enable_storefront_switcher' => true,
				'enable_auto_currency'       => true,
			],
			$this->multi_currency->get_multi_currency_onboarding_simulation_variables()
		);
	}

	public function test_get_multi_currency_onboarding_simulation_variables_is_empty_for_a_guest() {
		wp_set_current_user( 0 );
		$_GET = [
			'is_mc_onboarding_simulation' => true,
			'enable_storefront_switcher'  => true,
			'enable_auto_currency'        => true,
		];

		$this->assertSame( [], $this->multi_currency->get_multi_currency_onboarding_simulation_variables() );
	}

	public function test_get_multi_currency_onboarding_simulation_variables_is_empty_for_a_logged_in_non_merchant() {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		$_GET = [
			'is_mc_onboarding_simulation' => true,
			'enable_storefront_switcher'  => true,
		];

		$this->assertSame( [], $this->multi_currency->get_multi_currency_onboarding_simulation_variables() );
	}

	public function test_get_multi_currency_onboarding_simulation_variables() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$this->assertFalse( $this->multi_currency->is_simulation_enabled() );

		$this->assertEquals( [], $this->multi_currency->get_multi_currency_onboarding_simulation_variables() );

		$_GET = [
			'is_mc_onboarding_simulation' => false,
			'enable_storefront_switcher'  => true,
		];

		$this->assertEquals( [], $this->multi_currency->get_multi_currency_onboarding_simulation_variables() );

		$_GET = [
			'is_mc_onboarding_simulation' => true,
		];

		$this->assertEquals(
			[
				'enable_storefront_switcher' => false,
				'enable_auto_currency'       => false,
			],
			$this->multi_currency->get_multi_currency_onboarding_simulation_variables()
		);

		$_GET = [
			'is_mc_onboarding_simulation' => true,
			'enable_storefront_switcher'  => true,
		];

		$this->assertEquals(
			[
				'enable_storefront_switcher' => true,
				'enable_auto_currency'       => false,
			],
			$this->multi_currency->get_multi_currency_onboarding_simulation_variables()
		);

		$_GET                    = [];
		$_SERVER['HTTP_REFERER'] = '?is_mc_onboarding_simulation=true&enable_auto_currency=true&enable_storefront_switcher=true';

		$this->assertEquals(
			[
				'enable_storefront_switcher' => true,
				'enable_auto_currency'       => true,
			],
			$this->multi_currency->get_multi_currency_onboarding_simulation_variables()
		);
	}

	public function test_set_new_customer_currency_meta_updates_user_meta_from_session() {
		$expected = 'GBP';
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, $expected );

		$this->multi_currency->set_new_customer_currency_meta( self::LOGGED_IN_USER_ID, [], '' );
		$this->assertSame( $expected, get_user_meta( self::LOGGED_IN_USER_ID, MultiCurrency::CURRENCY_META_KEY, true ) );
	}

	public function test_set_new_customer_currency_meta_does_not_update_user_meta_if_no_user_passed() {
		$this->multi_currency->set_new_customer_currency_meta( 0, [], '' );
		$this->assertSame( '', get_user_meta( self::LOGGED_IN_USER_ID, MultiCurrency::CURRENCY_META_KEY, true ) );
	}

	public function test_set_new_customer_currency_meta_does_not_update_user_meta_if_no_session_currency() {
		$this->multi_currency->set_new_customer_currency_meta( self::LOGGED_IN_USER_ID, [], '' );
		$this->assertSame( '', get_user_meta( self::LOGGED_IN_USER_ID, MultiCurrency::CURRENCY_META_KEY, true ) );
	}

	public function get_price_provider() {
		return [
			[ '5.2401', '0.00', 5.24 ], // Even though the precision is 0.00 we make sure the amount is rounded to the currency's number of digits.
			[ '5.2499', '0.00', 5.25 ], // Even though the precision is 0.00 we make sure the amount is rounded to the currency's number of digits.
			[ '5.2499', '0.25', 5.25 ],
			[ '5.2500', '0.25', 5.25 ],
			[ '5.2501', '0.25', 5.50 ],
			[ '5.4999', '0.50', 5.50 ],
			[ '5.5000', '0.50', 5.50 ],
			[ '5.5001', '0.50', 6.00 ],
			[ '4.9999', '1.00', 5.00 ],
			[ '5.0000', '1.00', 5.00 ],
			[ '5.0001', '1.00', 6.00 ],
			[ '4.9999', '5.00', 5.00 ],
			[ '5.0000', '5.00', 5.00 ],
			[ '5.0001', '5.00', 10.00 ],
			[ '9.9999', '10.00', 10.00 ],
			[ '10.000', '10.00', 10.00 ],
			[ '10.0001', '10.00', 20.00 ],
		];
	}

	/**
	 * @dataProvider get_has_additional_currencies_enabled_provider
	 */
	public function test_has_additional_currencies_enabled( $currencies, $expected ) {
		update_option( self::ENABLED_CURRENCIES_OPTION, $currencies );
		$this->init_multi_currency();

		$this->assertSame( $expected, $this->multi_currency->has_additional_currencies_enabled() );
	}

	public function get_has_additional_currencies_enabled_provider() {
		return [
			[ [], false ],
			[ [ 'USD' ], false ],
			[ [ 'USD', 'EUR' ], true ],
		];
	}

	public function test_get_all_customer_currencies() {
		delete_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY );

		$mock_orders = [];

		$mock_orders[] = $this->add_mock_order_with_currency_meta( 'GBP' );
		$mock_orders[] = $this->add_mock_order_with_currency_meta( 'EUR' );
		$mock_orders[] = $this->add_mock_order_with_currency_meta( 'USD' );

		$mock_cache = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->with( MultiCurrencyCacheInterface::CURRENCIES_KEY, $this->anything(), $this->anything() )
			->willReturn( $this->mock_cached_currencies );

		$this->init_multi_currency( null, true, null, $mock_cache );

		$result = $this->multi_currency->get_all_customer_currencies();

		$this->assertEquals( [ 'EUR', 'GBP', 'USD' ], $result );

		foreach ( $mock_orders as $order_id ) {
			wp_delete_post( $order_id, true );
		}
	}

	public function test_get_all_customer_currencies_with_option_data() {
		$mock_option_data = [ 'GBP', 'EUR', 'USD' ];
		update_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY, $mock_option_data );

		$mock_cache = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->with( MultiCurrencyCacheInterface::CURRENCIES_KEY, $this->anything(), $this->anything() )
			->willReturn( $this->mock_cached_currencies );

		$this->init_multi_currency( null, true, null, $mock_cache );

		$result = $this->multi_currency->get_all_customer_currencies();

		$this->assertEquals( [ 'GBP', 'EUR', 'USD' ], $result );

		delete_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY );
	}

	/**
	 * Tests that if the option data is invalid, the currencies are fetched from the database.
	 *
	 * @dataProvider get_all_customer_currencies_with_invalid_option_data_provider
	 */
	public function test_get_all_customer_currencies_with_invalid_option_data( $option_data ) {
		update_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY, $option_data );

		$mock_orders = [];

		$mock_orders[] = $this->add_mock_order_with_currency_meta( 'GBP' );
		$mock_orders[] = $this->add_mock_order_with_currency_meta( 'EUR' );
		$mock_orders[] = $this->add_mock_order_with_currency_meta( 'USD' );

		$mock_cache = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->with( MultiCurrencyCacheInterface::CURRENCIES_KEY, $this->anything(), $this->anything() )
			->willReturn( $this->mock_cached_currencies );

		$this->init_multi_currency( null, true, null, $mock_cache );

		$result = $this->multi_currency->get_all_customer_currencies();

		$this->assertEquals( [ 'EUR', 'GBP', 'USD' ], $result );

		foreach ( $mock_orders as $order_id ) {
			wp_delete_post( $order_id, true );
		}

		delete_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY );
	}

	public function get_all_customer_currencies_with_invalid_option_data_provider() {
		return [
			'Empty string' => [ '' ],
			'Invalid data' => [ 'invalid-data' ],
			'Empty array'  => [ [] ],
		];
	}

	public function test_get_store_currencies_returns_expected() {
		// Arrange: Get the expected values.
		$expected = [
			'available' => $this->multi_currency->get_available_currencies(),
			'enabled'   => $this->multi_currency->get_enabled_currencies(),
			'default'   => $this->multi_currency->get_default_currency(),
		];

		// Act: Get the store's currencies.
		$store_currencies = $this->multi_currency->get_store_currencies();

		// Assert: Confirm expected equals result.
		$this->assertSame( $expected, $store_currencies );
	}

	/**
	 * @dataProvider get_single_currency_settings_provider
	 */
	public function test_get_single_currency_settings_returns_correctly( $exchange_rate_type, $manual_rate, $price_rounding, $price_charm ) {
		// Arrange: Set the currency code and the values in the database for the currency.
		$currency_code = 'eur';
		if ( ! is_null( $exchange_rate_type ) ) {
			update_option( 'wcpay_multi_currency_exchange_rate_' . $currency_code, $exchange_rate_type );
		} else {
			// If this option isn't set, the default is automatic.
			$exchange_rate_type = 'automatic';
		}
		if ( ! is_null( $manual_rate ) ) {
			update_option( 'wcpay_multi_currency_manual_rate_' . $currency_code, $manual_rate );
		}
		if ( ! is_null( $price_rounding ) ) {
			update_option( 'wcpay_multi_currency_price_rounding_' . $currency_code, $price_rounding );
		}
		if ( ! is_null( $price_charm ) ) {
			update_option( 'wcpay_multi_currency_price_charm_' . $currency_code, $price_charm );
		}

		// Arrange: Set the expected outcome.
		$expected = [
			'exchange_rate_type' => $exchange_rate_type,
			'manual_rate'        => $manual_rate,
			'price_rounding'     => $price_rounding,
			'price_charm'        => $price_charm,
		];

		// Act: Get the currency settings.
		$results = $this->multi_currency->get_single_currency_settings( strtoupper( $currency_code ) );

		// Assert: Confirm the results are correct.
		$this->assertSame( $expected, $results );
	}

	public function get_single_currency_settings_provider() {
		return [
			'manual'      => [
				'exchange_rate_type' => 'manual',
				'manual_rate'        => (float) 2,
				'price_rounding'     => (float) 1,
				'price_charm'        => (float) 0,
			],
			'automatic'   => [
				'exchange_rate_type' => 'automatic',
				'manual_rate'        => (float) 5.1,
				'price_rounding'     => (float) 4.2,
				'price_charm'        => (float) 3.3,
			],
			'null values' => [
				'exchange_rate_type' => null,
				'manual_rate'        => null,
				'price_rounding'     => null,
				'price_charm'        => null,
			],
		];
	}

	public function test_get_single_currency_settings_throws_exception_on_unavailable_currency() {
		// Arrange: Set the currencies to change to.
		$currency_code = 'banana';

		// Arrange/Assert: Set expected exception and message.
		$this->expectException( InvalidCurrencyException::class );
		$this->expectExceptionMessage( 'Invalid currency passed to get_single_currency_settings: banana' );

		// Act: Set the currencies.
		$this->multi_currency->get_single_currency_settings( $currency_code );
	}

	/**
	 * @dataProvider update_single_currency_settings_provider
	 */
	public function test_update_single_currency_settings_updates_settings( $exchange_rate_type, $manual_rate, $price_rounding, $price_charm ) {
		// Arrange: Set the currency code.
		$currency_code = 'eur';

		// Act: Update the currency settings.
		$this->multi_currency->update_single_currency_settings( strtoupper( $currency_code ), $exchange_rate_type, $price_rounding, $price_charm, $manual_rate );

		// Arrange: Update tne expected values.
		if ( ! in_array( $exchange_rate_type, [ 'automatic', 'manual' ], true ) ) {
			// Value remains unchanged if it is not in the array.
			$exchange_rate_type = false;
		}
		if ( is_null( $manual_rate ) || 'automatic' === $exchange_rate_type ) {
			// Value remains unchanged if it is null or if the rate type is automatic.
			$manual_rate = false;
		}

		// Assert: Confirm the results are correct.
		$this->assertSame( $exchange_rate_type, get_option( 'wcpay_multi_currency_exchange_rate_' . $currency_code, false ) );
		$this->assertEquals( $manual_rate, get_option( 'wcpay_multi_currency_manual_rate_' . $currency_code, false ) );
		$this->assertEquals( $price_rounding, get_option( 'wcpay_multi_currency_price_rounding_' . $currency_code, false ) );
		$this->assertEquals( $price_charm, get_option( 'wcpay_multi_currency_price_charm_' . $currency_code, false ) );
	}

	public function update_single_currency_settings_provider() {
		return [
			'manual'                     => [
				'exchange_rate_type' => 'manual',
				'manual_rate'        => (float) 2,
				'price_rounding'     => (float) 1,
				'price_charm'        => (float) 0,
			],
			'manual null rate'           => [
				'exchange_rate_type' => 'manual',
				'manual_rate'        => null,
				'price_rounding'     => (float) 1,
				'price_charm'        => (float) 0,
			],
			'automatic'                  => [
				'exchange_rate_type' => 'automatic',
				'manual_rate'        => null,
				'price_rounding'     => (float) 4.2,
				'price_charm'        => (float) 3.3,
			],
			'automatic with manual rate' => [
				'exchange_rate_type' => 'automatic',
				'manual_rate'        => (float) 1.5,
				'price_rounding'     => (float) 2.6,
				'price_charm'        => (float) 3.7,
			],
			'invalid rate type'          => [
				'exchange_rate_type' => 'invalid',
				'manual_rate'        => null,
				'price_rounding'     => (float) 9.2,
				'price_charm'        => (float) 8.3,
			],
		];
	}

	public function test_update_single_currency_settings_throws_exception_on_unavailable_currency() {
		// Arrange: Set the currencies to change to.
		$currency_code = 'banana';

		// Arrange/Assert: Set expected exception and message.
		$this->expectException( InvalidCurrencyException::class );
		$this->expectExceptionMessage( 'Invalid currency passed to update_single_currency_settings: banana' );

		// Act: Set the currencies.
		$this->multi_currency->update_single_currency_settings( $currency_code, 'manual', (float) 1, (float) 1, (float) 1 );

		// Assert: Confirm the currency settings were not updated.
		$this->assertFalse( get_option( 'wcpay_multi_currency_exchange_rate_' . $currency_code, false ) );
		$this->assertFalse( get_option( 'wcpay_multi_currency_manual_rate_' . $currency_code, false ) );
		$this->assertFalse( get_option( 'wcpay_multi_currency_price_rounding_' . $currency_code, false ) );
		$this->assertFalse( get_option( 'wcpay_multi_currency_price_charm_' . $currency_code, false ) );
	}

	/**
	 * @dataProvider update_single_currency_settings_throws_exception_on_invalid_currency_rate_provider
	 */
	public function test_update_single_currency_settings_throws_exception_on_invalid_currency_rate( $manual_rate ) {
		// Arrange: Set the currencto use.
		$currency_code = 'usd';

		// Arrange/Assert: Set expected exception and message.
		$this->expectException( InvalidCurrencyRateException::class );
		$this->expectExceptionMessage( 'Invalid manual currency rate passed to update_single_currency_settings: ' . $manual_rate );

		// Act: Set the currencies.
		$this->multi_currency->update_single_currency_settings( $currency_code, 'manual', (float) 1, (float) 1, $manual_rate );

		// Assert: Confirm the currency settings were not updated.
		$this->assertFalse( get_option( 'wcpay_multi_currency_exchange_rate_' . $currency_code, false ) );
		$this->assertFalse( get_option( 'wcpay_multi_currency_manual_rate_' . $currency_code, false ) );
		$this->assertFalse( get_option( 'wcpay_multi_currency_price_rounding_' . $currency_code, false ) );
		$this->assertFalse( get_option( 'wcpay_multi_currency_price_charm_' . $currency_code, false ) );
	}

	public function update_single_currency_settings_throws_exception_on_invalid_currency_rate_provider() {
		return [
			'invalid'  => [
				'rate' => 'invalid',
			],
			'zero'     => [
				'rate' => 0,
			],
			'negative' => [
				'rate' => -1,
			],
		];
	}

	public function test_maybe_update_customer_currencies_option() {
		$customer_currencies = [ 'EUR', 'USD' ];
		update_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY, $customer_currencies );

		$order = wc_create_order();
		$order->set_currency( 'GBP' );
		$order->save();

		$this->multi_currency->maybe_update_customer_currencies_option( $order->get_id() );

		$expected = array_merge( $customer_currencies, [ 'GBP' ] );

		$this->assertEquals( $expected, get_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY ) );

		delete_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY );
	}

	public function test_maybe_update_customer_currencies_option_currency_is_already_included() {
		$customer_currencies = [ 'EUR', 'USD' ];
		update_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY, $customer_currencies );

		$order = wc_create_order();
		$order->set_currency( 'USD' );
		$order->save();

		$this->multi_currency->maybe_update_customer_currencies_option( $order->get_id() );

		$this->assertEquals( $customer_currencies, get_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY ) );

		delete_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY );
	}

	public function test_maybe_update_customer_currencies_option_invalid() {
		$customer_currencies = 'invalid-data';
		update_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY, $customer_currencies );

		$order = wc_create_order();
		$order->set_currency( 'USD' );
		$order->save();

		$order = wc_create_order();
		$order->set_currency( 'EUR' );
		$order->save();

		$this->multi_currency->maybe_update_customer_currencies_option( $order->get_id() );
		$expected = [ 'EUR', 'USD' ];

		$this->assertEquals( $expected, get_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY ) );

		delete_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY );
	}

	public function test_maybe_update_customer_currencies_option_does_not_exist() {
		delete_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY );

		$order = wc_create_order();
		$order->set_currency( 'BRL' );
		$order->save();

		$order = wc_create_order();
		$order->set_currency( 'EUR' );
		$order->save();

		$this->multi_currency->maybe_update_customer_currencies_option( $order->get_id() );
		$expected = [ 'BRL', 'EUR' ];

		$this->assertEquals( $expected, get_option( MultiCurrency::CUSTOMER_CURRENCIES_KEY ) );
	}

	public function test_get_public_config_uses_wc_store_settings_for_default_currency() {
		// Snapshot original WC options to restore after the test.
		$original_decimal_sep  = get_option( 'woocommerce_price_decimal_sep' );
		$original_thousand_sep = get_option( 'woocommerce_price_thousand_sep' );
		$original_num_decimals = get_option( 'woocommerce_price_num_decimals' );
		$original_currency_pos = get_option( 'woocommerce_currency_pos' );

		// Custom WC settings that differ from the US locale defaults (. and ,).
		$custom_decimal_sep  = ',';
		$custom_thousand_sep = '.';
		$custom_decimals     = '2';
		$custom_currency_pos = 'right_space';

		update_option( 'woocommerce_price_decimal_sep', $custom_decimal_sep );
		update_option( 'woocommerce_price_thousand_sep', $custom_thousand_sep );
		update_option( 'woocommerce_price_num_decimals', $custom_decimals );
		update_option( 'woocommerce_currency_pos', $custom_currency_pos );

		$config   = $this->multi_currency->get_public_config();
		$usd_data = $config['currencies']['USD'];

		// Default currency should use the merchant's WC store settings.
		$this->assertEquals( $custom_decimal_sep, $usd_data['decimal_sep'] );
		$this->assertEquals( $custom_thousand_sep, $usd_data['thousand_sep'] );
		$this->assertEquals( (int) $custom_decimals, $usd_data['decimals'] );
		$this->assertEquals( $custom_currency_pos, $usd_data['symbol_pos'] );

		// Non-default currency should use the localization service format.
		$gbp_data   = $config['currencies']['GBP'];
		$gbp_format = $this->localization_service->get_currency_format( 'GBP' );
		$this->assertEquals( $gbp_format['decimal_sep'], $gbp_data['decimal_sep'] );
		$this->assertEquals( $gbp_format['thousand_sep'], $gbp_data['thousand_sep'] );

		// Restore original WC options.
		update_option( 'woocommerce_price_decimal_sep', $original_decimal_sep );
		update_option( 'woocommerce_price_thousand_sep', $original_thousand_sep );
		update_option( 'woocommerce_price_num_decimals', $original_num_decimals );
		update_option( 'woocommerce_currency_pos', $original_currency_pos );
	}

	public function test_maybe_auto_enable_sets_cache_mode_when_unset_and_caching_detected() {
		delete_option( MultiCurrency::RENDERING_MODE_OPTION );
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );

		$mock_ce = $this->createMock( CachingEnvironment::class );
		$mock_ce->method( 'is_page_caching_active' )->willReturn( true );
		$this->init_multi_currency( null, true, null, null, $mock_ce );

		$this->multi_currency->maybe_auto_enable_cache_rendering_mode();

		$this->assertSame( 'cache', get_option( 'wcpay_multi_currency_rendering_mode' ) );
		$this->assertSame( 'yes', get_option( 'wcpay_multi_currency_cache_autodetect_done' ) );
	}

	public function test_maybe_auto_enable_leaves_mode_unset_when_no_caching_detected() {
		delete_option( MultiCurrency::RENDERING_MODE_OPTION );
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );

		$mock_ce = $this->createMock( CachingEnvironment::class );
		$mock_ce->method( 'is_page_caching_active' )->willReturn( false );
		$this->init_multi_currency( null, true, null, null, $mock_ce );

		$this->multi_currency->maybe_auto_enable_cache_rendering_mode();

		$this->assertFalse( get_option( 'wcpay_multi_currency_rendering_mode' ) );
		$this->assertSame( 'yes', get_option( 'wcpay_multi_currency_cache_autodetect_done' ) );
	}

	public function test_maybe_auto_enable_never_overrides_existing_mode() {
		update_option( MultiCurrency::RENDERING_MODE_OPTION, 'speed' );
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );

		$mock_ce = $this->createMock( CachingEnvironment::class );
		$mock_ce->method( 'is_page_caching_active' )->willReturn( true );
		$this->init_multi_currency( null, true, null, null, $mock_ce );

		$this->multi_currency->maybe_auto_enable_cache_rendering_mode();

		$this->assertSame( 'speed', get_option( 'wcpay_multi_currency_rendering_mode' ) );
		$this->assertSame( 'yes', get_option( 'wcpay_multi_currency_cache_autodetect_done' ) );
	}

	public function test_maybe_auto_enable_is_noop_when_already_run() {
		update_option( MultiCurrency::CACHE_AUTODETECT_DONE_OPTION, 'yes' );
		delete_option( MultiCurrency::RENDERING_MODE_OPTION );
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );

		$mock_ce = $this->createMock( CachingEnvironment::class );
		$mock_ce->expects( $this->never() )->method( 'is_page_caching_active' );
		$this->init_multi_currency( null, true, null, null, $mock_ce );

		$this->multi_currency->maybe_auto_enable_cache_rendering_mode();

		$this->assertFalse( get_option( 'wcpay_multi_currency_rendering_mode' ) );
	}

	public function test_maybe_auto_enable_is_noop_when_feature_disabled() {
		delete_option( MultiCurrency::RENDERING_MODE_OPTION );
		update_option( '_wcpay_feature_mc_cache_optimized', '0' );

		$mock_ce = $this->createMock( CachingEnvironment::class );
		$mock_ce->expects( $this->never() )->method( 'is_page_caching_active' );
		$this->init_multi_currency( null, true, null, null, $mock_ce );

		$this->multi_currency->maybe_auto_enable_cache_rendering_mode();

		$this->assertFalse( get_option( 'wcpay_multi_currency_rendering_mode' ) );
		// Detection is intentionally not marked done so it can run once the feature is enabled later.
		$this->assertFalse( get_option( 'wcpay_multi_currency_cache_autodetect_done' ) );
	}

	public function test_get_settings_recommends_cache_mode_when_on_speed_and_caching_detected() {
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( MultiCurrency::RENDERING_MODE_OPTION, 'speed' );

		$mock_ce = $this->createMock( CachingEnvironment::class );
		$mock_ce->method( 'is_page_caching_active' )->willReturn( true );
		$this->init_multi_currency( null, true, null, null, $mock_ce );

		$settings = $this->multi_currency->get_settings();

		$this->assertTrue( $settings['should_recommend_cache_mode'] );
		$this->assertFalse( $settings['cache_recommendation_dismissed'] );
	}

	public function test_get_settings_does_not_recommend_when_dismissed() {
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( MultiCurrency::RENDERING_MODE_OPTION, 'speed' );
		update_option( MultiCurrency::CACHE_RECOMMENDATION_DISMISSED_OPTION, 'yes' );

		$mock_ce = $this->createMock( CachingEnvironment::class );
		$mock_ce->method( 'is_page_caching_active' )->willReturn( true );
		$this->init_multi_currency( null, true, null, null, $mock_ce );

		$settings = $this->multi_currency->get_settings();

		$this->assertFalse( $settings['should_recommend_cache_mode'] );
		$this->assertTrue( $settings['cache_recommendation_dismissed'] );
	}

	public function test_get_settings_does_not_recommend_when_already_on_cache_mode() {
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( MultiCurrency::RENDERING_MODE_OPTION, 'cache' );

		$mock_ce = $this->createMock( CachingEnvironment::class );
		$mock_ce->method( 'is_page_caching_active' )->willReturn( true );
		$this->init_multi_currency( null, true, null, null, $mock_ce );

		$settings = $this->multi_currency->get_settings();

		$this->assertFalse( $settings['should_recommend_cache_mode'] );
	}

	public function test_get_settings_does_not_recommend_when_no_caching_detected() {
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );
		update_option( MultiCurrency::RENDERING_MODE_OPTION, 'speed' );

		$mock_ce = $this->createMock( CachingEnvironment::class );
		$mock_ce->method( 'is_page_caching_active' )->willReturn( false );
		$this->init_multi_currency( null, true, null, null, $mock_ce );

		$settings = $this->multi_currency->get_settings();

		$this->assertFalse( $settings['should_recommend_cache_mode'] );
	}

	public function test_update_settings_persists_cache_recommendation_dismissed() {
		$this->init_multi_currency();

		$this->multi_currency->update_settings(
			[ MultiCurrency::CACHE_RECOMMENDATION_DISMISSED_OPTION => 'yes' ]
		);

		$this->assertSame( 'yes', get_option( 'wcpay_multi_currency_cache_recommendation_dismissed' ) );
	}

	public function test_update_settings_ignores_invalid_cache_recommendation_dismissed() {
		$this->init_multi_currency();

		$this->multi_currency->update_settings(
			[ MultiCurrency::CACHE_RECOMMENDATION_DISMISSED_OPTION => 'maybe' ]
		);

		$this->assertFalse( get_option( 'wcpay_multi_currency_cache_recommendation_dismissed' ) );
	}

	private function mock_currency_settings( $currency_code, $settings ) {
		foreach ( $settings as $setting => $value ) {
			update_option( 'wcpay_multi_currency_' . $setting . '_' . strtolower( $currency_code ), $value );
		}
	}

	private function remove_currency_settings_mock( $currency_code, $settings ) {
		foreach ( $settings as $setting ) {
			delete_option( 'wcpay_multi_currency_' . $setting . '_' . strtolower( $currency_code ) );
		}
	}

	private function init_multi_currency( $mock_api_client = null, $wcpay_account_connected = true, $mock_account = null, $mock_cache = null, $mock_caching_environment = null, $run_init = true ) {
		$this->mock_api_client = $this->createMock( MultiCurrencyApiClientInterface::class );

		$this->mock_account = $mock_account ?? $this->createMock( MultiCurrencyAccountInterface::class );
		$this->mock_account->method( 'is_provider_connected' )->willReturn( $wcpay_account_connected );

		$this->mock_api_client->method( 'is_server_connected' )->willReturn( true );

		$this->mock_cache = $this->createMock( MultiCurrencyCacheInterface::class );
		$this->mock_cache->method( 'get_or_add' )->willReturn( $this->mock_cached_currencies );

		$this->mock_utils = $this->createMock( Utils::class );

		$this->mock_settings = $this->createMock( MultiCurrencySettingsInterface::class );

		$this->multi_currency = new MultiCurrency(
			$this->mock_settings,
			$mock_api_client ?? $this->mock_api_client,
			$this->mock_account,
			$this->localization_service,
			$mock_cache ?? $this->mock_cache,
			$this->mock_utils,
			$mock_caching_environment
		);
		$this->multi_currency->init_widgets();
		if ( $run_init ) {
			$this->multi_currency->init();
		}
	}

	/**
	 * Dispatches stub routes through a real REST server, recording the currency each of them is handled in.
	 *
	 * @param string $dispatch `direct` calls the Store API stub route, `batch` sends it through `batch/v1`.
	 *
	 * @return array The currencies seen by the `merchant_before` and `store` routes.
	 */
	private function dispatch_stub_routes( string $dispatch ): array {
		global $wp_rest_server;

		$seen     = [];
		$recorder = function ( $key ) use ( &$seen ) {
			return function () use ( $key, &$seen ) {
				$seen[ $key ] = get_woocommerce_currency();
				return rest_ensure_response( [ 'ok' => true ] );
			};
		};

		add_action(
			'rest_api_init',
			function () use ( $recorder ) {
				foreach ( [
					'wc/v3'       => 'merchant_before',
					'wc/store/v1' => 'store',
				] as $namespace => $key ) {
					register_rest_route(
						$namespace,
						'/mc-stub',
						[
							'methods'             => 'POST',
							'callback'            => $recorder( $key ),
							'permission_callback' => '__return_true',
							'allow_batch'         => [ 'v1' => true ],
						]
					);
				}
			}
		);

		// A fresh server registers the default routes (`batch/v1`) and fires `rest_api_init` for the stubs.
		$wp_rest_server = null;
		rest_get_server();

		if ( 'batch' === $dispatch ) {
			$request = new WP_REST_Request( 'POST', '/batch/v1' );
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body(
				wp_json_encode(
					[
						'requests' => [
							[
								'method' => 'POST',
								'path'   => '/wc/v3/mc-stub',
							],
							[
								'method' => 'POST',
								'path'   => '/wc/store/v1/mc-stub',
							],
						],
					]
				)
			);
		} else {
			$request = new WP_REST_Request( 'POST', '/wc/Store/v1/mc-stub' );
		}

		$response = rest_do_request( $request );
		$this->assertContains( $response->get_status(), [ 200, 207 ], wp_json_encode( $response->get_data() ) );
		if ( 'batch' === $dispatch ) {
			$this->assertSame( [ 200, 200 ], wp_list_pluck( $response->get_data()['responses'], 'status' ), wp_json_encode( $response->get_data() ) );
		}

		return $seen;
	}

	private function reset_rest_server() {
		global $wp_rest_server;

		$wp_rest_server = null;
		remove_all_actions( 'rest_api_init' );
		remove_all_filters( 'rest_request_before_callbacks' );
	}

	private function match_rest_route( $route ) {
		return apply_filters( 'rest_request_before_callbacks', null, [], new WP_REST_Request( 'POST', $route ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
	}

	private function add_mock_order_with_currency_meta( $currency ) {
		// Insert a couple of orders into the database to verify the customer currencies get fetched correctly.
		$order_id = wp_insert_post(
			[
				'post_type'   => 'shop_order',
				'post_status' => 'wc-processing',
			]
		);

		update_post_meta( $order_id, '_order_currency', $currency );

		return $order_id;
	}

	public function test_init_invalidates_cache_when_store_currency_changes() {
		// Simulate previously known currency was USD, but store currency is now EUR.
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'wcpay_multi_currency_store_currency', 'USD' );

		// Clear filters from prior init (FrontendCurrencies adds one at priority 900).
		remove_all_filters( 'woocommerce_currency' );

		$mock_cache = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache->method( 'get_or_add' )->willReturn( $this->mock_cached_currencies );
		$mock_cache->expects( $this->once() )
			->method( 'delete' )
			->with( MultiCurrencyCacheInterface::CURRENCIES_KEY );

		$this->init_multi_currency( null, true, null, $mock_cache );

		// Verify the stored currency was updated to the new value.
		$this->assertSame( 'EUR', get_option( 'wcpay_multi_currency_store_currency' ) );
	}

	public function test_init_does_not_invalidate_cache_when_woocommerce_currency_is_filtered() {
		update_option( 'woocommerce_currency', 'USD' );
		update_option( 'wcpay_multi_currency_store_currency', 'USD' );

		// Simulate another plugin returning a visitor-specific currency.
		remove_all_filters( 'woocommerce_currency' );
		add_filter( 'woocommerce_currency', fn() => 'EUR', 999 );

		$mock_cache = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache->method( 'get_or_add' )->willReturn( $this->mock_cached_currencies );
		$mock_cache->expects( $this->never() )
			->method( 'delete' );

		$this->init_multi_currency( null, true, null, $mock_cache );

		$this->assertSame( 'USD', get_option( 'wcpay_multi_currency_store_currency' ) );
	}

	public function test_init_does_not_invalidate_cache_when_store_currency_unchanged() {
		// Store currency matches the current WooCommerce currency (both USD).
		update_option( 'woocommerce_currency', 'USD' );
		update_option( 'wcpay_multi_currency_store_currency', 'USD' );

		// Clear filters from prior init to ensure configured and filtered currency values match.
		remove_all_filters( 'woocommerce_currency' );

		$mock_cache = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache->method( 'get_or_add' )->willReturn( $this->mock_cached_currencies );
		$mock_cache->expects( $this->never() )
			->method( 'delete' );

		$this->init_multi_currency( null, true, null, $mock_cache );
	}

	public function test_init_does_not_invalidate_cache_on_first_install() {
		// No store_currency option exists yet (first-time install).
		update_option( 'woocommerce_currency', 'USD' );
		delete_option( 'wcpay_multi_currency_store_currency' );

		// Clear filters from prior init to ensure configured and filtered currency values match.
		remove_all_filters( 'woocommerce_currency' );

		$mock_cache = $this->createMock( MultiCurrencyCacheInterface::class );
		$mock_cache->method( 'get_or_add' )->willReturn( $this->mock_cached_currencies );
		$mock_cache->expects( $this->never() )
			->method( 'delete' );

		$this->init_multi_currency( null, true, null, $mock_cache );

		// Verify the option was set for the first time (not treated as a change).
		$this->assertSame( 'USD', get_option( 'wcpay_multi_currency_store_currency' ) );
	}

	public function test_init_returns_early_when_store_currency_not_in_available_wc_currencies() {
		// Remove lingering woocommerce_currency filters from previous init (e.g. FrontendCurrencies at priority 900).
		remove_all_filters( 'woocommerce_currency' );

		// Simulate a removed custom currency by setting the store currency to a non-existent code.
		update_option( 'woocommerce_currency', 'CUSTOM' );

		$this->init_multi_currency();

		// After init with an invalid store currency, enabled currencies should be empty
		// because init() returned early before initializing them.
		$this->assertEmpty( $this->multi_currency->get_enabled_currencies() );

		update_option( 'woocommerce_currency', 'USD' );
	}

	public function test_filtered_woocommerce_currency_survives_when_only_store_currency_is_enabled() {
		remove_all_filters( 'woocommerce_currency' );
		// A third-party currency switcher picks the visitor currency at a low priority.
		add_filter( 'woocommerce_currency', fn() => 'GBP', 5 );

		// Not connected: Multi-Currency has only the store currency available, so it has nothing to switch.
		$this->init_multi_currency( null, false );

		$this->assertSame( [ 'USD' ], array_keys( $this->multi_currency->get_enabled_currencies() ) );
		$this->assertSame( 'GBP', get_woocommerce_currency() );
	}

	public function test_explicit_store_currency_selection_overrides_filtered_currency() {
		remove_all_filters( 'woocommerce_currency' );
		add_filter( 'woocommerce_currency', fn() => Currency_Code::POUND_STERLING, 5 );
		$this->init_multi_currency();
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::UNITED_STATES_DOLLAR );

		$this->assertSame( 'USD', get_woocommerce_currency() );
	}

	public function test_stored_store_currency_does_not_override_filtered_currency_when_only_store_currency_is_enabled() {
		remove_all_filters( 'woocommerce_currency' );
		add_filter( 'woocommerce_currency', fn() => Currency_Code::POUND_STERLING, 5 );
		// Not connected: a stored code on a one-currency store is stale state, not a shopper's choice.
		$this->init_multi_currency( null, false );
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, Currency_Code::UNITED_STATES_DOLLAR );

		$this->assertSame( 'GBP', get_woocommerce_currency() );
	}

	public function test_compatibility_store_currency_override_overrides_filtered_currency() {
		remove_all_filters( 'woocommerce_currency' );
		add_filter( 'woocommerce_currency', fn() => Currency_Code::POUND_STERLING, 5 );
		add_filter( 'wcpay_multi_currency_override_selected_currency', fn() => Currency_Code::UNITED_STATES_DOLLAR );
		$this->init_multi_currency();

		$this->assertSame( 'USD', get_woocommerce_currency() );
	}

	public function test_filtered_woocommerce_currency_survives_empty_logged_in_user_selection() {
		remove_all_filters( 'woocommerce_currency' );
		add_filter( 'woocommerce_currency', fn() => Currency_Code::POUND_STERLING, 5 );
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		delete_user_meta( self::LOGGED_IN_USER_ID, MultiCurrency::CURRENCY_META_KEY );
		$this->init_multi_currency();

		$this->assertSame( 'GBP', get_woocommerce_currency() );
	}

	public function test_init_does_not_initialize_when_feature_is_disabled() {
		remove_all_filters( 'woocommerce_currency' );
		update_option( '_wcpay_feature_customer_multi_currency', '0' );

		$this->init_multi_currency();

		$this->assertSame( [], $this->multi_currency->get_available_currencies() );
		$this->assertSame( [], $this->multi_currency->get_enabled_currencies() );
		$this->assertFalse( has_filter( 'woocommerce_currency' ) );
	}

	public function test_uninitialized_module_still_reports_store_currency_as_default_and_selected() {
		update_option( '_wcpay_feature_customer_multi_currency', '0' );

		$this->init_multi_currency();

		$this->assertSame( 'USD', $this->multi_currency->get_default_currency()->get_code() );
		$this->assertSame( 'USD', $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_uninitialized_module_still_exposes_frontend_objects_without_their_hooks() {
		remove_all_filters( 'woocommerce_currency' );
		update_option( '_wcpay_feature_customer_multi_currency', '0' );

		$this->init_multi_currency();

		$this->assertInstanceOf( FrontendCurrencies::class, $this->multi_currency->get_frontend_currencies() );
		$this->assertInstanceOf( FrontendPrices::class, $this->multi_currency->get_frontend_prices() );
		$this->assertFalse( has_filter( 'woocommerce_currency' ) );
	}

	public function test_get_default_currency_on_disabled_module_does_not_rebuild_frontend_objects() {
		update_option( '_wcpay_feature_customer_multi_currency', '0' );

		$this->init_multi_currency();

		$frontend_currencies = $this->multi_currency->get_frontend_currencies();
		$frontend_prices     = $this->multi_currency->get_frontend_prices();

		$this->multi_currency->get_default_currency();
		$this->multi_currency->get_default_currency();

		$this->assertSame( $frontend_currencies, $this->multi_currency->get_frontend_currencies() );
		$this->assertSame( $frontend_prices, $this->multi_currency->get_frontend_prices() );
	}

	public function test_frontend_getters_initialize_a_disabled_module_on_first_use() {
		remove_all_filters( 'woocommerce_currency' );
		update_option( '_wcpay_feature_customer_multi_currency', '0' );

		// Nothing has run init() yet: the getters must not return null into their non-nullable return types.
		$this->init_multi_currency( null, true, null, null, null, false );

		$this->assertInstanceOf( FrontendCurrencies::class, $this->multi_currency->get_frontend_currencies() );
		$this->assertInstanceOf( FrontendPrices::class, $this->multi_currency->get_frontend_prices() );
		$this->assertFalse( has_filter( 'woocommerce_currency' ) );
	}

	public function test_init_initializes_during_setup_for_a_merchant_when_feature_is_disabled() {
		update_option( '_wcpay_feature_customer_multi_currency', '0' );
		$_SERVER['HTTP_REFERER'] = admin_url( 'admin.php?page=wc-admin&path=%2Fpayments%2Fmulti-currency-setup' );
		// A shop_manager has manage_woocommerce but not manage_options, so this fails if the capability is narrowed.
		$shop_manager_id = self::factory()->user->create( [ 'role' => 'shop_manager' ] );
		wp_set_current_user( $shop_manager_id );

		$this->init_multi_currency();

		$this->assertNotEmpty( $this->multi_currency->get_enabled_currencies() );
	}

	public function test_init_does_not_initialize_during_setup_for_a_guest_when_feature_is_disabled() {
		remove_all_filters( 'woocommerce_currency' );
		update_option( '_wcpay_feature_customer_multi_currency', '0' );
		$_SERVER['HTTP_REFERER'] = admin_url( 'admin.php?page=wc-admin&path=%2Fpayments%2Fmulti-currency-setup' );
		wp_set_current_user( 0 );

		$this->init_multi_currency();

		$this->assertSame( [], $this->multi_currency->get_available_currencies() );
		$this->assertSame( [], $this->multi_currency->get_enabled_currencies() );
		$this->assertFalse( has_filter( 'woocommerce_currency' ) );
	}

	public function test_is_enabled_follows_the_customer_multi_currency_feature_flag() {
		update_option( '_wcpay_feature_customer_multi_currency', '0' );
		$this->assertFalse( MultiCurrency::is_enabled() );

		update_option( '_wcpay_feature_customer_multi_currency', '1' );
		$this->assertTrue( MultiCurrency::is_enabled() );
	}

	public function test_is_enabled_is_false_during_setup_for_a_logged_in_non_merchant_when_feature_is_disabled() {
		update_option( '_wcpay_feature_customer_multi_currency', '0' );
		$_SERVER['HTTP_REFERER'] = admin_url( 'admin.php?page=wc-admin&path=%2Fpayments%2Fmulti-currency-setup' );
		$subscriber_id           = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( MultiCurrency::is_enabled() );
	}

	public function test_is_enabled_is_true_during_setup_for_a_shop_manager_when_feature_is_disabled() {
		update_option( '_wcpay_feature_customer_multi_currency', '0' );
		$_SERVER['HTTP_REFERER'] = admin_url( 'admin.php?page=wc-admin&path=%2Fpayments%2Fmulti-currency-setup' );
		$shop_manager_id         = self::factory()->user->create( [ 'role' => 'shop_manager' ] );
		wp_set_current_user( $shop_manager_id );

		$this->assertTrue( MultiCurrency::is_enabled() );
	}

	public function test_register_script_with_dependencies_sets_script_translations() {
		$this->multi_currency->register_script_with_dependencies( 'wcpay-test-multi-currency', 'dist/multi-currency' );

		$this->assertSame( 'woocommerce-payments', wp_scripts()->registered['wcpay-test-multi-currency']->textdomain );

		wp_deregister_script( 'wcpay-test-multi-currency' );
	}

	private function mock_theme( $theme ) {
		add_filter(
			'stylesheet',
			function () use ( $theme ) {
				return $theme;
			}
		);
	}
}

<?php
/**
 * Class WCPay_Multi_Currency_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;
use WCPay\Multi_Currency\Multi_Currency;

/**
 * WCPay\Multi_Currency\Multi_Currency unit tests.
 */
class WCPay_Multi_Currency_Tests extends WP_UnitTestCase {
	const LOGGED_IN_USER_ID         = 1;
	const ENABLED_CURRENCIES_OPTION = 'wcpay_multi_currency_enabled_currencies';
	const CACHED_CURRENCIES_OPTION  = 'wcpay_multi_currency_cached_currencies';

	/**
	 * Mock enabled currencies.
	 *
	 * @var array
	 */
	private $mock_enabled_currencies = [ 'USD', 'CAD', 'GBP', 'BIF' ];

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
	 * WCPay\Multi_Currency\Multi_Currency instance.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency
	 */
	private $multi_currency;

	/**
	 * Mock of the API client.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $mock_api_client;

	public function setUp() {
		parent::setUp();

		$this->mock_currency_settings(
			'GBP',
			[
				'price_charm'    => '-0.1',
				'price_rounding' => '0',
			]
		);

		$this->mock_cached_currencies = [
			'currencies' => $this->mock_available_currencies,
			'updated'    => strtotime( 'today midnight' ),
			'expires'    => strtotime( 'today midnight' ) + DAY_IN_SECONDS,
		];

		update_option( self::CACHED_CURRENCIES_OPTION, $this->mock_cached_currencies );
		update_option( self::ENABLED_CURRENCIES_OPTION, $this->mock_enabled_currencies );

		$this->mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_currency_rates', 'is_server_connected' ] )
			->getMock();

		$this->mock_api_client
			->expects( $this->any() )
			->method( 'is_server_connected' )
			->willReturn( true );

		$this->multi_currency = new Multi_Currency( $this->mock_api_client );
	}

	public function tearDown() {
		WC()->session->__unset( Multi_Currency::CURRENCY_SESSION_KEY );
		remove_all_filters( 'wcpay_multi_currency_apply_charm_only_to_products' );
		remove_all_filters( 'woocommerce_currency' );

		delete_user_meta( self::LOGGED_IN_USER_ID, Multi_Currency::CURRENCY_META_KEY );
		wp_set_current_user( 0 );

		$this->remove_currency_settings_mock( 'GBP', [ 'price_charm', 'price_rounding' ] );
		delete_option( self::CACHED_CURRENCIES_OPTION );
		delete_option( self::ENABLED_CURRENCIES_OPTION );

		parent::tearDown();
	}

	public function test_get_available_currencies_adds_store_currency() {
		add_filter(
			'woocommerce_currency',
			function () {
				return 'DEFAULT';
			},
			100
		);

		// Recreate Multi_Currency instance to use the recently set DEFAULT currency.
		$this->multi_currency = new Multi_Currency( $this->mock_api_client );

		$default_currency = $this->multi_currency->get_available_currencies()['DEFAULT'];

		$this->assertSame( 'DEFAULT', $default_currency->get_code() );
		$this->assertSame( 1.0, $default_currency->get_rate() );
	}

	public function test_get_enabled_currencies_returns_correctly() {
		$mock_currencies = [
			'USD' => 1,
			'CAD' => 1.206823,
			'GBP' => 0.708099,
			'BIF' => 1974,
		];

		foreach ( $mock_currencies as $code => $rate ) {
			$currency = new WCPay\Multi_Currency\Currency( $code, $rate );
			$currency->set_charm( 0.00 );
			$currency->set_rounding( 'none' );
			$expected[ $currency->get_code() ] = $currency;
		}
		$expected['GBP']->set_charm( '-0.1' );
		$expected['GBP']->set_rounding( '0' );

		$this->assertEquals( $expected, $this->multi_currency->get_enabled_currencies() );
	}

	public function test_get_enabled_currencies_returns_sorted_currencies() {
		$expected = [ 'USD', 'BIF', 'CAD', 'GBP' ];
		$this->assertSame( $expected, array_keys( $this->multi_currency->get_enabled_currencies() ) );
	}

	public function test_set_enabled_currencies() {
		$currencies = [ 'USD', 'EUR', 'GBP', 'CLP' ];
		$this->multi_currency->set_enabled_currencies( $currencies );
		$this->assertSame( $currencies, get_option( self::ENABLED_CURRENCIES_OPTION ) );
	}

	public function test_enabled_but_unavailable_currencies_are_skipped() {
		update_option( self::ENABLED_CURRENCIES_OPTION, [ 'RANDOM_CURRENCY', 'USD' ] );

		// Recreate Multi_Currency instance to use the recently set currencies.
		$this->multi_currency = new Multi_Currency( $this->mock_api_client );

		$this->assertSame( [ 'USD' ], array_keys( $this->multi_currency->get_enabled_currencies() ) );
	}

	public function test_get_selected_currency_returns_default_currency_for_empty_session_and_user() {
		$this->assertSame( get_woocommerce_currency(), $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_default_currency_for_invalid_session_currency() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'UNSUPPORTED_CURRENCY' );

		$this->assertSame( get_woocommerce_currency(), $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_default_currency_for_invalid_user_currency() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		update_user_meta( self::LOGGED_IN_USER_ID, WCPay\Multi_Currency\Multi_Currency::CURRENCY_META_KEY, 'UNSUPPORTED_CURRENCY' );

		$this->assertSame( get_woocommerce_currency(), $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_currency_from_session() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );

		$this->assertSame( 'GBP', $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_currency_from_user() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		update_user_meta( self::LOGGED_IN_USER_ID, WCPay\Multi_Currency\Multi_Currency::CURRENCY_META_KEY, 'GBP' );

		$this->assertSame( 'GBP', $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_update_selected_currency_does_not_set_invalid_session_currency() {
		$this->multi_currency->update_selected_currency( 'UNSUPPORTED_CURRENCY' );

		$this->assertNull( WC()->session->get( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_does_not_set_invalid_user_currency() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );

		$this->multi_currency->update_selected_currency( 'UNSUPPORTED_CURRENCY' );

		$this->assertEmpty( get_user_meta( self::LOGGED_IN_USER_ID, WCPay\Multi_Currency\Multi_Currency::CURRENCY_META_KEY, true ) );
	}

	public function test_update_selected_currency_sets_session_currency() {
		$this->multi_currency->update_selected_currency( 'GBP' );

		$this->assertSame( 'GBP', WC()->session->get( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_sets_user_currency() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );

		$this->multi_currency->update_selected_currency( 'GBP' );

		$this->assertSame( 'GBP', get_user_meta( self::LOGGED_IN_USER_ID, WCPay\Multi_Currency\Multi_Currency::CURRENCY_META_KEY, true ) );
	}

	public function test_update_selected_currency_by_url_does_not_set_session_when_parameter_not_set() {
		$this->multi_currency->update_selected_currency_by_url();

		$this->assertNull( WC()->session->get( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_url_does_not_set_session_when_currency_not_enabled() {
		$_GET['currency'] = 'CLP';

		$this->multi_currency->update_selected_currency_by_url();

		$this->assertNull( WC()->session->get( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_url_updates_session_when_currency_is_enabled() {
		$_GET['currency'] = 'GBP';

		$this->multi_currency->update_selected_currency_by_url();

		$this->assertSame( 'GBP', WC()->session->get( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_url_recalculates_cart() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );
		$_GET['currency'] = 'GBP';

		$this->assertContains( '&#36;', WC()->cart->get_total() );

		$this->multi_currency->update_selected_currency_by_url();

		$this->assertContains( '&pound;', WC()->cart->get_total() );
		$this->assertNotContains( '&#36;', WC()->cart->get_total() );

	}

	public function test_get_price_returns_price_in_default_currency() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, get_woocommerce_currency() );

		$this->assertSame( 5.0, $this->multi_currency->get_price( '5.0', 'product' ) );
	}

	public function test_get_price_returns_price_if_unsupported_type() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, get_woocommerce_currency() );

		$this->assertSame( 5.0, $this->multi_currency->get_price( '5.0', 'unsupported_type' ) );
	}

	public function test_get_price_returns_converted_product_price_with_charm() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_true' );

		// 0.708099 * 10 = 7,08099 -> ceiled to 8 -> 8 - 0.1 = 7.9
		$this->assertSame( 7.9, $this->multi_currency->get_price( '10.0', 'product' ) );
	}

	public function test_get_price_returns_converted_shipping_price_with_charm() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7,08099 -> ceiled to 8 -> 8 - 0.1 = 7.9
		$this->assertSame( 7.9, $this->multi_currency->get_price( '10.0', 'shipping' ) );
	}

	public function test_get_price_returns_converted_shipping_price_without_charm() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_true' );

		// 0.708099 * 10 = 7,08099 -> ceiled to 8 -> 8 + 0.0 = 8.0
		$this->assertSame( 8.0, $this->multi_currency->get_price( '10.0', 'shipping' ) );
	}

	public function test_get_price_returns_converted_coupon_price_without_adjustments() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7,08099
		$this->assertSame( 7.08099, $this->multi_currency->get_price( '10.0', 'coupon' ) );
	}

	public function test_get_price_returns_converted_tax_price() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7,08099
		$this->assertSame( 7.08099, $this->multi_currency->get_price( '10.0', 'tax' ) );
	}

	/**
	 * @dataProvider get_price_provider
	 */
	public function test_get_price_converts_using_ceil_and_precision( $price, $precision, $expected ) {
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_true' );
		$this->mock_currency_settings( 'GBP', [ 'price_rounding' => $precision ] );

		// Recreate Multi_Currency instance to use the recently set price_rounding.
		$this->multi_currency = new Multi_Currency( $this->mock_api_client );

		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );

		$this->assertSame( $expected, $this->multi_currency->get_price( $price, 'shipping' ) );
	}

	public function test_get_cached_currencies_with_no_server_connection() {
		// Need to create a new instance of Multi_Currency with a different $mock_api_client
		// Because the mock return value of 'is_server_connected' cannot be overridden.
		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_currency_rates', 'is_server_connected' ] )
			->getMock();

		$mock_api_client
			->expects( $this->any() )
			->method( 'is_server_connected' )
			->willReturn( false );

		$this->multi_currency = new Multi_Currency( $mock_api_client );
		$this->assertNull( $this->multi_currency->get_cached_currencies() );
	}

	public function test_get_cached_currencies_with_server_retrieval_error() {
		$current_time = time();

		$currency_cache = [
			'currencies' => Multi_Currency::CURRENCY_RETRIEVAL_ERROR,
			'updated'    => $current_time,
			'expires'    => $current_time + DAY_IN_SECONDS,
		];

		// Create or update the currency option cache.
		update_option( Multi_Currency::CURRENCY_CACHE_OPTION, $currency_cache, 'no' );

		$this->assertNull( $this->multi_currency->get_cached_currencies() );
	}

	public function test_get_cached_currencies_with_valid_cached_data() {
		update_option( self::CACHED_CURRENCIES_OPTION, $this->mock_cached_currencies );

		$this->assertEquals(
			$this->mock_cached_currencies,
			$this->multi_currency->get_cached_currencies()
		);
	}

	public function test_get_cached_currencies_fetches_from_server() {
		delete_option( self::CACHED_CURRENCIES_OPTION );

		$currency_from = get_woocommerce_currency();
		$currencies_to = get_woocommerce_currencies();
		unset( $currencies_to[ $currency_from ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_currency_rates' )
			->with(
				$currency_from,
				array_keys( $currencies_to )
			)->willReturn(
				$this->mock_available_currencies
			);

		$result = $this->multi_currency->get_cached_currencies();

		// Assert that the currencies and the time updated were returned.
		$this->assertArrayHasKey( 'currencies', $result );
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertEquals(
			$this->mock_available_currencies,
			$result['currencies']
		);

		// Assert that the cache was correctly set.
		$cached_data = get_option( self::CACHED_CURRENCIES_OPTION );
		$this->assertTrue( is_array( $cached_data ) );
		$this->assertArrayHasKey( 'currencies', $cached_data );
		$this->assertArrayHasKey( 'updated', $cached_data );
		$this->assertEquals(
			$this->mock_available_currencies,
			$result['currencies']
		);
	}

	public function test_get_cached_currencies_handles_api_exception() {
		delete_option( self::CACHED_CURRENCIES_OPTION );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_currency_rates' )
			->willThrowException( new API_Exception( 'Error connecting to server', 'API_ERROR', 500 ) );

		$this->assertNull( $this->multi_currency->get_cached_currencies() );

		// Assert that the cache was correctly set with the error string.
		$cached_data = get_option( self::CACHED_CURRENCIES_OPTION );
		$this->assertEquals( Multi_Currency::CURRENCY_RETRIEVAL_ERROR, $cached_data['currencies'] );
	}

	public function get_price_provider() {
		return [
			[ '7.07', '2', 5.01 ], // 5.006 after conversion
			[ '7.06', '2', 5.0 ], // 4.999 after conversion
			[ '7.04', '2', 4.99 ], // 4.985 after conversion
			[ '7.07', '1', 5.1 ], // 5.006 after conversion
			[ '7.06', '1', 5.0 ], // 4.999 after conversion
			[ '6.90', '1', 4.9 ], // 4.885 after conversion
			[ '7.07', '0', 6.0 ], // 5.006 after conversion
			[ '7.06', '0', 5.0 ], // 4.999 after conversion
			[ '5.80', '0', 5.0 ], // 4.106 after conversion
			[ '14.26', '-1', 20.0 ], // 10.097 after conversion
			[ '14.02', '-1', 10.0 ], // 9.927 after conversion
			[ '141.0', '-2', 100.0 ], // 99.841 after conversion
			[ '142.0', '-2', 200.0 ], // 100.550 after conversion
		];
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
}

<?php
/**
 * Class WCPay_Multi_Currency_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Utils;
use WCPay\Database_Cache;
use WCPay\MultiCurrency\Exceptions\InvalidCurrencyException;
use WCPay\MultiCurrency\Exceptions\InvalidCurrencyRateException;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Settings;
use WCPay\MultiCurrency\SettingsOnboardCta;

/**
 * WCPay\MultiCurrency\MultiCurrency unit tests.
 */
class WCPay_Multi_Currency_Tests extends WCPAY_UnitTestCase {
	const LOGGED_IN_USER_ID         = 1;
	const ENABLED_CURRENCIES_OPTION = 'wcpay_multi_currency_enabled_currencies';

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
	 * @var WC_Payments_API_Client
	 */
	private $mock_api_client;

	/**
	 * Mock of the WC_Payments_Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $mock_account;

	/**
	 * Mock of the WC_Payments_Localization_Service.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $mock_localization_service;

	/**
	 * Mock of Database_Cache.
	 *
	 * @var Database_Cache;
	 */
	private $mock_database_cache;

	/**
	 * Mock of Utils.
	 *
	 * @var Utils;
	 */
	private $mock_utils;

	public function set_up() {
		parent::set_up();

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

	public function tear_down() {
		WC()->session->__unset( MultiCurrency::CURRENCY_SESSION_KEY );
		remove_all_filters( 'wcpay_multi_currency_apply_charm_only_to_products' );
		remove_all_filters( 'wcpay_multi_currency_available_currencies' );
		remove_all_filters( 'woocommerce_currency' );
		remove_all_filters( 'stylesheet' );

		delete_user_meta( self::LOGGED_IN_USER_ID, MultiCurrency::CURRENCY_META_KEY );
		wp_set_current_user( 0 );

		$this->remove_currency_settings_mock( 'GBP', [ 'price_charm', 'price_rounding', 'manual_rate', 'exchange_rate' ] );
		delete_option( self::ENABLED_CURRENCIES_OPTION );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'no' );

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
		add_filter(
			'woocommerce_currency',
			function () {
				return 'DEFAULT';
			},
			901
		);

		$this->init_multi_currency();

		$default_currency = $this->multi_currency->get_available_currencies()['DEFAULT'];

		$this->assertSame( 'DEFAULT', $default_currency->get_code() );
		$this->assertSame( 1.0, $default_currency->get_rate() );
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

		foreach ( $mock_currencies as $code => $rate ) {
			$currency = new WCPay\MultiCurrency\Currency( $code, $rate );
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

		// Arrange/Assert: Set expected exception and message.
		$this->expectException( InvalidCurrencyException::class );
		$this->expectExceptionMessage( 'Invalid currency/currencies passed to set_enabled_currencies: banana' );

		// Act: Set the currencies.
		$this->multi_currency->set_enabled_currencies( $currencies );

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

	public function test_update_selected_currency_recalculates_cart() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );

		$this->assertStringContainsString( '&#36;', WC()->cart->get_total() );

		$this->multi_currency->update_selected_currency( 'GBP' );

		$this->assertStringContainsString( '&pound;', WC()->cart->get_total() );
		$this->assertStringNotContainsString( '&#36;', WC()->cart->get_total() );

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
		$this->mock_localization_service->method( 'get_country_locale_data' )->with( 'CL' )->willReturn( [ 'currency_code' => 'CLP' ] );

		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return 'CL';
			}
		);

		$this->multi_currency->update_selected_currency_by_geolocation();

		$this->assertNull( WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_geolocation_does_not_set_session_cookie() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		$this->mock_localization_service->method( 'get_country_locale_data' )->with( 'CA' )->willReturn( [ 'currency_code' => 'CAD' ] );
		add_filter(
			'woocommerce_geolocate_ip',
			function() {
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
			function() {
				return 'CA';
			}
		);

		$this->mock_localization_service->method( 'get_country_locale_data' )->with( 'CA' )->willReturn( [ 'currency_code' => 'CAD' ] );

		$this->multi_currency->update_selected_currency_by_geolocation();

		$this->assertSame( 'CAD', WC()->session->get( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_geolocation_displays_notice() {
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );

		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return 'CA';
			}
		);

		$this->mock_localization_service->method( 'get_country_locale_data' )->with( 'CA' )->willReturn( [ 'currency_code' => 'CAD' ] );

		$this->multi_currency->update_selected_currency_by_geolocation();

		$this->assertNotFalse( has_filter( 'wp_footer', [ $this->multi_currency, 'display_geolocation_currency_update_notice' ] ) );
	}

	public function test_display_geolocation_currency_update_notice() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'CAD' );
		add_filter(
			'woocommerce_geolocate_ip',
			function() {
				return 'CA';
			}
		);

		$this->mock_localization_service->method( 'get_country_locale_data' )->with( 'CA' )->willReturn( [ 'currency_code' => 'CAD' ] );

		$this->multi_currency->display_geolocation_currency_update_notice();

		$this->expectOutputRegex( '/woocommerce-store-notice.+visiting from Canada.+\?currency=USD.+Use United States \(US\) dollar instead/' );
	}

	public function test_display_geolocation_currency_update_notice_does_not_display_if_using_default_currency() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'US' );
		add_filter(
			'woocommerce_geolocate_ip',
			function() {
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
			function() {
				return 'US';
			}
		);

		$this->multi_currency->display_geolocation_currency_update_notice();

		$this->expectOutputString( '' );
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

	public function test_get_price_returns_converted_coupon_price_without_adjustments() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7,08099
		$this->assertSame( 7.08099, $this->multi_currency->get_price( '10.0', 'coupon' ) );
	}

	public function test_get_price_returns_converted_exchange_rate_without_adjustments() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7,08099
		$this->assertSame( 7.08099, $this->multi_currency->get_price( '10.0', 'exchange_rate' ) );
	}

	public function test_get_price_returns_converted_tax_price() {
		WC()->session->set( WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7,08099
		$this->assertSame( 7.08099, $this->multi_currency->get_price( '10.0', 'tax' ) );
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

	public function test_get_cached_currencies_with_no_server_connection() {
		// Need to create a new instance of MultiCurrency with a different $mock_api_client
		// Because the mock return value of 'is_server_connected' cannot be overridden.
		$mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$mock_api_client->method( 'is_server_connected' )->willReturn( false );

		$this->init_multi_currency( $mock_api_client );

		$this->mock_database_cache->method( 'get' )->willReturn( $this->mock_cached_currencies );

		$this->assertEquals(
			$this->mock_cached_currencies,
			$this->multi_currency->get_cached_currencies()
		);
	}

	public function test_get_cached_currencies_with_account_rejected() {
		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get' )
			->willReturn( null );

		$this->mock_account
			->expects( $this->once() )
			->method( 'is_account_rejected' )
			->willReturn( true );

		$this->mock_database_cache
			->expects( $this->never() )
			->method( 'get_or_add' );

		$this->assertEquals(
			null,
			$this->multi_currency->get_cached_currencies()
		);
	}

	public function test_get_cached_currencies_fetches_from_server() {
		$get_or_add_call_count = 1;
		$mock_database_cache   = $this->createMock( Database_Cache::class );
		$mock_database_cache
			->expects( $this->exactly( 2 ) )
			->method( 'get_or_add' )
			->with( Database_Cache::CURRENCIES_KEY, $this->anything(), $this->anything() )
			->willReturnCallback(
				function ( $key, $generator, $validator ) use ( &$get_or_add_call_count ) {
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

		$this->init_multi_currency( null, true, null, $mock_database_cache );

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

	public function test_enabled_currencies_option_as_string_does_not_fatal() {
		update_option( 'wcpay_multi_currency_enabled_currencies', '' );
		$this->multi_currency->init();
		$this->assertEquals( '', get_option( 'wcpay_multi_currency_enabled_currencies', false ) );
	}

	public function test_get_cached_currencies_with_no_stripe_connection() {
		$this->init_multi_currency( null, false );
		$this->mock_database_cache->method( 'get' )->willReturn( $this->mock_cached_currencies );
		$this->assertEquals(
			$this->mock_cached_currencies,
			$this->multi_currency->get_cached_currencies()
		);
	}

	public function test_get_available_currencies_returns_store_currency_with_no_stripe_connection() {
		$expected = [
			'USD' => new WCPay\MultiCurrency\Currency( 'USD', 1 ),
		];
		$this->init_multi_currency( null, false );
		$this->assertEquals( $expected, $this->multi_currency->get_available_currencies() );
	}

	public function test_get_switcher_widget_markup() {
		$expected = '<div class="widget ">		<form>
						<select
				name="currency"
				aria-label=""
				onchange="this.form.submit()"
			>
				<option value="USD" selected>&#36; USD</option><option value="BIF">Fr BIF</option><option value="CAD">&#36; CAD</option><option value="GBP">&pound; GBP</option>			</select>
		</form>
		</div>';

		$this->assertEquals( $expected, $this->multi_currency->get_switcher_widget_markup() );
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
		$this->assertFalse( $this->multi_currency->is_simulation_enabled() );
		$_GET = [
			'is_mc_onboarding_simulation' => true,
			'enable_storefront_switcher'  => true,
		];
		$this->multi_currency->possible_simulation_activation();
		$this->assertTrue( $this->multi_currency->is_simulation_enabled() );
	}

	public function test_get_multi_currency_onboarding_simulation_variables() {
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
			[ '5.2499', '0.00', 5.2499 ],
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
		$mock_orders = [];

		$mock_orders[] = $this->add_mock_order_with_currency_meta( 'GBP' );
		$mock_orders[] = $this->add_mock_order_with_currency_meta( 'EUR' );
		$mock_orders[] = $this->add_mock_order_with_currency_meta( 'USD' );

		$mock_database_cache = $this->createMock( Database_Cache::class );
		$mock_database_cache
			->expects( $this->exactly( 2 ) )
			->method( 'get_or_add' )
			->withConsecutive(
				[ Database_Cache::CURRENCIES_KEY, $this->anything(), $this->anything() ],
				[ Database_Cache::CUSTOMER_CURRENCIES_KEY, $this->anything(), $this->anything() ]
			)->willReturnCallback(
				function( $key, $generator, $validator ) {
					if ( Database_Cache::CURRENCIES_KEY === $key ) {
						return $this->mock_cached_currencies;
					} else {
						// If calling the get all customer currencies function, run the callback function.
						return $generator();
					}
				}
			);

		$this->init_multi_currency( null, true, null, $mock_database_cache );

		$result = $this->multi_currency->get_all_customer_currencies();

		$this->assertEquals( [ 'EUR', 'GBP', 'USD' ], $result );

		foreach ( $mock_orders as $order_id ) {
			wp_delete_post( $order_id, true );
		}
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

	private function init_multi_currency( $mock_api_client = null, $wcpay_account_connected = true, $mock_account = null, $mock_database_cache = null ) {
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->mock_account = $mock_account ?? $this->createMock( WC_Payments_Account::class );
		$this->mock_account->method( 'is_stripe_connected' )->willReturn( $wcpay_account_connected );

		$this->mock_localization_service = $this->createMock( WC_Payments_Localization_Service::class );

		$this->mock_api_client->method( 'is_server_connected' )->willReturn( true );

		$this->mock_localization_service->method( 'get_currency_format' )->willReturn(
			[
				'currency_pos' => 'left',
				'thousand_sep' => ',',
				'decimal_sep'  => '.',
				'num_decimals' => 2,
			]
		);

		$this->mock_database_cache = $this->createMock( Database_Cache::class );
		$this->mock_database_cache->method( 'get_or_add' )->willReturn( $this->mock_cached_currencies );

		$this->mock_utils = $this->createMock( Utils::class );

		$this->multi_currency = new MultiCurrency(
			$mock_api_client ?? $this->mock_api_client,
			$this->mock_account,
			$this->mock_localization_service,
			$mock_database_cache ?? $this->mock_database_cache,
			$this->mock_utils
		);
		$this->multi_currency->init_widgets();
		$this->multi_currency->init();

		// Fix an issue in WPCOM tests.
		WC_Payments_Explicit_Price_Formatter::set_multi_currency_instance( $this->multi_currency );
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

	private function mock_theme( $theme ) {
		add_filter(
			'stylesheet',
			function() use ( $theme ) {
				return $theme;
			}
		);
	}
}

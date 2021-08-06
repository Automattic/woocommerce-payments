<?php
/**
 * Class WC_Payments_Explicit_Price_Formatter_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\MultiCurrency;

/**
 * WC_Payments_Explicit_Price_Formatter unit tests.
 */
class WC_Payments_Explicit_Price_Formatter_Test extends WP_UnitTestCase {

	const LOGGED_IN_USER_ID               = 1;
	const ENABLED_CURRENCIES_OPTION       = 'wcpay_multi_currency_enabled_currencies';
	const CACHED_CURRENCIES_OPTION        = 'wcpay_multi_currency_cached_currencies';
	const CURRENCY_RETRIEVAL_ERROR_OPTION = 'wcpay_multi_currency_retrieval_error';

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

	public function setUp() {
		parent::setUp();

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

		update_option( self::CACHED_CURRENCIES_OPTION, $this->mock_cached_currencies );
		update_option( self::ENABLED_CURRENCIES_OPTION, $this->mock_enabled_currencies );

		$this->init_multi_currency();
	}

	public function tearDown() {
		set_current_screen( 'front' );
		WC()->session->__unset( MultiCurrency::CURRENCY_SESSION_KEY );
		remove_all_filters( 'wcpay_multi_currency_apply_charm_only_to_products' );
		remove_all_filters( 'wcpay_multi_currency_available_currencies' );
		remove_all_filters( 'woocommerce_currency' );
		remove_all_filters( 'stylesheet' );

		delete_user_meta( self::LOGGED_IN_USER_ID, MultiCurrency::CURRENCY_META_KEY );
		wp_set_current_user( 0 );

		$this->remove_currency_settings_mock( 'GBP', [ 'price_charm', 'price_rounding', 'manual_rate', 'exchange_rate' ] );
		delete_option( self::CACHED_CURRENCIES_OPTION );
		delete_option( self::ENABLED_CURRENCIES_OPTION );
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'no' );

		WC_Payments_Explicit_Price_Formatter::set_multi_currency_instance( WC_Payments_Multi_Currency() );

		parent::tearDown();
	}

	public function test_get_explicit_price_with_order_currency_on_backend_with_one_enabled_currency() {
		set_current_screen( 'edit-page' );
		$this->prepare_one_enabled_currency();
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( 'BRL' );

		$this->assertSame( 'R$ 5,90 BRL', WC_Payments_Explicit_Price_Formatter::get_explicit_price( 'R$ 5,90', $order ) );
	}

	public function test_get_explicit_price_with_store_currency_on_backend_with_one_enabled_currency() {
		set_current_screen( 'edit-page' );
		$this->prepare_one_enabled_currency();
		$this->assertSame( '$10.30 USD', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30' ) );
	}

	public function test_get_explicit_price_skips_already_explicit_prices_on_backend_with_one_enabled_currency() {
		set_current_screen( 'edit-page' );
		$this->prepare_one_enabled_currency();
		$this->assertSame( '$10.30 USD', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30 USD' ) );
	}

	public function test_get_explicit_price_with_order_currency_on_backend_with_multiple_enabled_currencies() {
		set_current_screen( 'edit-page' );

		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( 'BRL' );

		$this->assertSame( 'R$ 5,90 BRL', WC_Payments_Explicit_Price_Formatter::get_explicit_price( 'R$ 5,90', $order ) );
	}

	public function test_get_explicit_price_with_store_currency_on_backend_with_multiple_enabled_currencies() {
		set_current_screen( 'edit-page' );
		$this->assertSame( '$10.30 USD', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30' ) );
	}

	public function test_get_explicit_price_skips_already_explicit_prices_on_backend_with_multiple_enabled_currencies() {
		set_current_screen( 'edit-page' );
		$this->assertSame( '$10.30 USD', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30 USD' ) );
	}

	public function test_get_explicit_price_skips_prefixed_prices() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( 'CHF' );

		$this->assertSame( 'CHF 10.30', WC_Payments_Explicit_Price_Formatter::get_explicit_price( 'CHF 10.30', $order ) );
  }
  
	public function test_get_explicit_price_with_order_currency_on_frontend_with_one_enabled_currency() {
		$this->prepare_one_enabled_currency();
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( 'BRL' );

		$this->assertSame( 'R$ 5,90', WC_Payments_Explicit_Price_Formatter::get_explicit_price( 'R$ 5,90', $order ) );
	}

	public function test_get_explicit_price_with_store_currency_on_frontend_with_one_enabled_currency() {
		$this->prepare_one_enabled_currency();
		$this->assertSame( '$10.30', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30' ) );
	}

	public function test_get_explicit_price_skips_already_explicit_prices_on_frontend_with_one_enabled_currency() {
		$this->prepare_one_enabled_currency();
		$this->assertSame( '$10.30 USD', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30 USD' ) );
	}

	public function test_get_explicit_price_with_order_currency_on_frontend_with_multiple_enabled_currencies() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( 'BRL' );

		$this->assertSame( 'R$ 5,90 BRL', WC_Payments_Explicit_Price_Formatter::get_explicit_price( 'R$ 5,90', $order ) );
	}

	public function test_get_explicit_price_with_store_currency_on_frontend_with_multiple_enabled_currencies() {
		$this->assertSame( '$10.30 USD', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30' ) );
	}

	public function test_get_explicit_price_skips_already_explicit_prices_on_frontend_with_multiple_enabled_currencies() {
		$this->assertSame( '$10.30 USD', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30 USD' ) );
	}

	private function prepare_one_enabled_currency() {
		$this->multi_currency->set_enabled_currencies( [ 'USD' ] );
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

	private function init_multi_currency( $mock_api_client = null, $wcpay_account_connected = true ) {
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->mock_account = $this->createMock( WC_Payments_Account::class );
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

		$this->multi_currency = new MultiCurrency( $mock_api_client ?? $this->mock_api_client, $this->mock_account, $this->mock_localization_service );
		$this->multi_currency->init();

		WC_Payments_Explicit_Price_Formatter::set_multi_currency_instance( $this->multi_currency );
	}
}

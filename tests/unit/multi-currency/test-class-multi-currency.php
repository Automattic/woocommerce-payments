<?php
/**
 * Class WCPay_Multi_Currency_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Multi_Currency unit tests.
 */
class WCPay_Multi_Currency_Tests extends WP_UnitTestCase {
	const LOGGED_IN_USER_ID = 1;

	/**
	 * Mock available currencies.
	 *
	 * @var array
	 */
	public $mock_available_currencies = [
		[ 'USD', 1 ],
		[ 'CAD', 1.206823 ],
		[ 'GBP', 0.708099 ],
		[ 'EUR', 0.826381 ],
		[ 'CDF', 2000 ],
		[ 'BIF', 1974 ], // Zero decimal currency.
		[ 'CLP', 706.8 ], // Zero decimal currency.
	];

	/**
	 * Mock enabled currencies.
	 *
	 * @var array
	 */
	public $mock_enabled_currencies = [ 'USD', 'CAD', 'GBP', 'BIF' ];

	/**
	 * WCPay\Multi_Currency\Multi_Currency instance.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency
	 */
	private $multi_currency;

	public function setUp() {
		parent::setUp();

		$this->mock_currency_settings(
			'GBP',
			[
				'price_charm'    => '-0.1',
				'price_rounding' => '1.00',
			]
		);
		update_option( 'wcpay_multi_currency_stored_currencies', $this->mock_available_currencies );
		update_option( 'wcpay_multi_currency_enabled_currencies', $this->mock_enabled_currencies );

		$this->multi_currency = WCPay\Multi_Currency\Multi_Currency::instance();
	}

	public function tearDown() {
		WC()->session->__unset( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY );
		remove_all_filters( 'wcpay_multi_currency_apply_charm_only_to_products' );
		remove_all_filters( 'woocommerce_currency' );
		$this->reset_multi_currency_instance();

		delete_user_meta( self::LOGGED_IN_USER_ID, WCPay\Multi_Currency\Multi_Currency::CURRENCY_META_KEY );
		wp_set_current_user( 0 );

		$this->remove_currency_settings_mock( 'GBP', [ 'price_charm', 'price_rounding', 'manual_rate', 'exchange_rate' ] );
		delete_option( 'wcpay_multi_currency_stored_currencies' );
		delete_option( 'wcpay_multi_currency_enabled_currencies' );

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
		$this->reset_multi_currency_instance();
		$this->multi_currency = WCPay\Multi_Currency\Multi_Currency::instance();

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
		$expected['GBP']->set_rounding( '1.00' );

		$this->assertEquals( $expected, $this->multi_currency->get_enabled_currencies() );
	}

	public function test_get_enabled_currencies_returns_sorted_currencies() {
		$expected = [ 'USD', 'BIF', 'CAD', 'GBP' ];
		$this->assertSame( $expected, array_keys( $this->multi_currency->get_enabled_currencies() ) );
	}

	public function test_set_enabled_currencies() {
		$currencies = [ 'USD', 'EUR', 'GBP', 'CLP' ];
		$this->multi_currency->set_enabled_currencies( $currencies );
		$this->assertSame( $currencies, get_option( 'wcpay_multi_currency_enabled_currencies' ) );
	}

	public function test_enabled_but_unavailable_currencies_are_skipped() {
		update_option( 'wcpay_multi_currency_enabled_currencies', [ 'RANDOM_CURRENCY', 'USD' ] );

		// Recreate Multi_Currency instance to use the recently set currencies.
		$this->reset_multi_currency_instance();
		$this->multi_currency = WCPay\Multi_Currency\Multi_Currency::instance();

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

	public function test_update_selected_currency_recalculates_cart() {
		wp_set_current_user( self::LOGGED_IN_USER_ID );

		$this->assertContains( '&#36;', WC()->cart->get_total() );

		$this->multi_currency->update_selected_currency( 'GBP' );

		$this->assertContains( '&pound;', WC()->cart->get_total() );
		$this->assertNotContains( '&#36;', WC()->cart->get_total() );

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

	public function test_get_price_returns_converted_exchange_rate_without_adjustments() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_false' );

		// 0.708099 * 10 = 7,08099
		$this->assertSame( 7.08099, $this->multi_currency->get_price( '10.0', 'exchange_rate' ) );
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

		// Recreate Multi_Currency instance to use the recently set price_rounding.
		$this->reset_multi_currency_instance();
		$this->multi_currency = WCPay\Multi_Currency\Multi_Currency::instance();

		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );

		$this->assertSame( $expected, $this->multi_currency->get_price( 1, 'shipping' ) );
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

	private function reset_multi_currency_instance() {
		$multi_currency_reflection = new ReflectionClass( $this->multi_currency );
		$instance_property         = $multi_currency_reflection->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, null );
		$instance_property->setAccessible( false );
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

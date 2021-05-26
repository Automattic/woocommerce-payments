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
	/**
	 * WCPay\Multi_Currency\Multi_Currency instance.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency
	 */
	private $multi_currency;

	public function setUp() {
		parent::setUp();

		$this->multi_currency = WCPay\Multi_Currency\Multi_Currency::instance();
	}

	public function tearDown() {
		WC()->session->__unset( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY );
		remove_all_filters( 'wcpay_multi_currency_apply_charm_only_to_products' );
		remove_all_filters( 'wcpay_multi_currency_round_precision' );
		remove_all_filters( 'woocommerce_currency' );
		$this->reset_multi_currency_instance();

		parent::tearDown();
	}

	public function test_multi_currency_stops_loading_when_store_currency_is_invalid() {
		$this->reload_multi_currency_with_invalid_store_currency();

		$this->assertFalse( has_action( 'rest_api_init', [ $this->multi_currency, 'init_rest_api' ] ) );
		$this->assertFalse( has_action( 'widgets_init', [ $this->multi_currency, 'init_widgets' ] ) );
	}

	public function test_multi_currency_adds_admin_note_when_store_currency_is_invalid() {
		$this->reload_multi_currency_with_invalid_store_currency();

		$this->assertNotFalse( has_action( 'admin_notices', [ $this->multi_currency, 'display_invalid_currency_error_notice' ] ) );
	}

	public function test_get_default_currency_throws_exception_when_store_currency_is_invalid() {
		$this->reload_multi_currency_with_invalid_store_currency();

		$this->expectException( WCPay\Multi_Currency\Exceptions\Invalid_Default_Currency_Exception::class );
		$this->expectExceptionMessage( 'The store\'s default currency (INVALID_CURRENCY) cannot be used with WooCommerce Payments Multi-Currency.' );

		$this->multi_currency->get_default_currency();
	}

	public function test_get_selected_currency_returns_default_currency_for_empty_session() {
		$this->assertSame( get_woocommerce_currency(), $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_get_selected_currency_returns_currency_from_session() {
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );

		$this->assertSame( 'GBP', $this->multi_currency->get_selected_currency()->get_code() );
	}

	public function test_update_selected_currency_by_url_does_not_set_session_when_parameter_not_set() {
		$this->multi_currency->update_selected_currency_by_url();

		$this->assertNull( WC()->session->get( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY ) );
	}

	public function test_update_selected_currency_by_url_does_not_set_session_when_currency_not_enabled() {
		$_GET['currency'] = 'BIF';

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
		WC()->session->set( WCPay\Multi_Currency\Multi_Currency::CURRENCY_SESSION_KEY, 'GBP' );
		add_filter( 'wcpay_multi_currency_apply_charm_only_to_products', '__return_true' );
		add_filter(
			'wcpay_multi_currency_round_precision',
			function () use ( $precision ) {
				return $precision;
			}
		);

		$this->assertSame( $expected, $this->multi_currency->get_price( $price, 'shipping' ) );
	}

	public function get_price_provider() {
		return [
			[ '7.07', 2, 5.01 ], // 5.006 after conversion
			[ '7.06', 2, 5.0 ], // 4.999 after conversion
			[ '7.04', 2, 4.99 ], // 4.985 after conversion
			[ '7.07', 1, 5.1 ], // 5.006 after conversion
			[ '7.06', 1, 5.0 ], // 4.999 after conversion
			[ '6.90', 1, 4.9 ], // 4.885 after conversion
			[ '7.07', 0, 6.0 ], // 5.006 after conversion
			[ '7.06', 0, 5.0 ], // 4.999 after conversion
			[ '5.80', 0, 5.0 ], // 4.106 after conversion
			[ '14.26', -1, 20.0 ], // 10.097 after conversion
			[ '14.02', -1, 10.0 ], // 9.927 after conversion
			[ '141.0', -2, 100.0 ], // 99.841 after conversion
			[ '142.0', -2, 200.0 ], // 100.550 after conversion
		];
	}

	private function reset_multi_currency_instance() {
		$multi_currency_reflection = new ReflectionClass( $this->multi_currency );
		$instance_property         = $multi_currency_reflection->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, null );
		$instance_property->setAccessible( false );
	}

	private function reload_multi_currency_with_invalid_store_currency() {
		add_filter(
			'woocommerce_currency',
			function () {
				return 'INVALID_CURRENCY';
			},
			100
		);

		// Recreate Multi_Currency instance to use the recently set INVALID_CURRENCY.
		$this->reset_multi_currency_instance();
		$this->multi_currency = WCPay\Multi_Currency\Multi_Currency::instance();
	}
}

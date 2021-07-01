<?php
/**
 * Class WCPay_Multi_Currency_Settings_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Currency;

/**
 * WCPay\MultiCurrency\Settings unit tests.
 */
class WCPay_Multi_Currency_Settings_Tests extends WP_UnitTestCase {
	/**
	 * Mock WCPay\MultiCurrency\MultiCurrency.
	 *
	 * @var WCPay\MultiCurrency\MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\MultiCurrency\Settings instance.
	 *
	 * @var WCPay\MultiCurrency\Settings
	 */
	private $settings;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );

		// The settings pages file is only included in woocommerce_get_settings_pages, so we need to manually include it here.
		$this->settings = new WCPay\MultiCurrency\Settings( $this->mock_multi_currency );
	}

	/**
	 * @dataProvider woocommerce_action_provider
	 */
	public function test_registers_woocommerce_action( $action, $function_name ) {
		$this->assertNotFalse(
			has_action( $action, [ $this->settings, $function_name ] ),
			"Action '$action' was not registered with '$function_name'"
		);
	}

	public function woocommerce_action_provider() {
		return [
			[ 'woocommerce_settings_wcpay_multi_currency', 'render_single_currency_breadcrumbs' ],
			[ 'woocommerce_admin_field_wcpay_enabled_currencies_list', 'enabled_currencies_list' ],
			[ 'woocommerce_admin_field_wcpay_currencies_settings_section_start', 'currencies_settings_section_start' ],
			[ 'woocommerce_admin_field_wcpay_currencies_settings_section_end', 'currencies_settings_section_end' ],
			[ 'woocommerce_admin_field_wcpay_single_currency_preview_helper', 'single_currency_preview_helper' ],
		];
	}

	public function test_render_single_currency_breadcrumbs_does_not_render_when_blank_section() {
		$GLOBALS['current_section'] = '';

		$this->expectOutputString( '' );

		$this->settings->render_single_currency_breadcrumbs();
	}

	public function test_render_single_currency_breadcrumbs_does_not_render_when_currency_not_enabled() {
		$GLOBALS['current_section'] = 'gbp';
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn( [] );

		$this->expectOutputString( '' );

		$this->settings->render_single_currency_breadcrumbs();
	}

	public function test_render_single_currency_breadcrumbs_renders_breadcrumbs_for_single_currency() {
		$GLOBALS['current_section'] = 'gbp';
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn( [ 'GBP' => new Currency( 'GBP' ) ] );

		$this->expectOutputRegex( '/<a href=".*\/wp-admin\/admin\.php\?page=wc-settings&#038;tab=wcpay_multi_currency">Currencies<\/a> &gt; Pound sterling \(GBP\) 🇬🇧/' );

		$this->settings->render_single_currency_breadcrumbs();
	}
}

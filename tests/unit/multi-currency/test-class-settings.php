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

		/** @var WCPay\MultiCurrency\MultiCurrency|PHPUnit_Framework_MockObject_MockObject */
		$this->mock_multi_currency = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );

		// The settings pages file is only included in woocommerce_get_settings_pages, so we need to manually include it here.
		$this->settings = new WCPay\MultiCurrency\Settings( $this->mock_multi_currency );
	}

	/**
	 * @dataProvider woocommerce_action_provider
	 */
	public function test_registers_internal_actions_with_account( $action, $function_name ) {
		// Init Settings again to get proper registration of hooks/filters.
		$this->settings = new WCPay\MultiCurrency\Settings( $this->mock_multi_currency );

		$this->assertNotFalse(
			has_action( $action, [ $this->settings, $function_name ] ),
			"Action '$action' was not registered with '$function_name'"
		);
	}

	public function woocommerce_action_provider() {
		return [
			[ 'woocommerce_admin_field_wcpay_multi_currency_settings_page', 'wcpay_multi_currency_settings_page' ],
		];
	}

	public function test_registers_external_action_with_account() {

		// Init Settings again to get proper registration of hooks/filters.
		$this->settings = new WCPay\MultiCurrency\Settings( $this->mock_multi_currency );

		$action        = 'admin_print_scripts';
		$function_name = 'print_emoji_detection_script';

		$this->assertNotFalse(
			has_action( $action, $function_name ),
			"Action '$action' was not registered with '$function_name'"
		);
	}
}

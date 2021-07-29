<?php
/**
 * Class WCPay_Multi_Currency_Storefront_Integration_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\StorefrontIntegration;

/**
 * WCPay\MultiCurrency\StorefrontIntegration unit tests.
 */
class WCPay_Multi_Currency_Storefront_Integration_Tests extends WP_UnitTestCase {
	/**
	 * Mock MultiCurrency.
	 *
	 * @var MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency    = $this->createMock( MultiCurrency::class );
		$this->storefront_integration = new StorefrontIntegration( $this->mock_multi_currency );
	}

	public function tearDown() {
		remove_all_filters( 'option_wcpay_multi_currency_enable_storefront_switcher' );

		parent::tearDown();
	}

	public function test_register_settings_on_init() {
		$this->assertSame(
			10,
			has_filter( 'wcpay_multi_currency_enabled_currencies_settings', [ $this->storefront_integration, 'filter_store_settings' ] ),
			"The filter 'wcpay_multi_currency_enabled_currencies_settings' with function 'filter_store_settings' was not registered with a priority of 10."
		);
	}

	/**
	 * @dataProvider switcher_filter_provider
	 */
	public function test_does_not_register_actions_when_switcher_disabled( $filter, $function_name ) {
		update_option( 'wcpay_multi_currency_enable_storefront_switcher', 'no' );
		// Reinit class to re-evaluate conditional hooks.
		$this->storefront_integration = new StorefrontIntegration( $this->mock_multi_currency );

		$this->assertFalse(
			has_filter( $filter, [ $this->storefront_integration, $function_name ] ),
			"The filter '$filter' with function '$function_name' was found."
		);
	}


	/**
	 * @dataProvider switcher_filter_provider
	 */
	public function test_registers_actions_when_switcher_default( $filter, $function_name ) {
		delete_option( 'wcpay_multi_currency_enable_storefront_switcher' );
		// Reinit class to re-evaluate conditional hooks.
		$this->storefront_integration = new StorefrontIntegration( $this->mock_multi_currency );

		$this->assertGreaterThan(
			10,
			has_filter( $filter, [ $this->storefront_integration, $function_name ] ),
			"The filter '$filter' with function '$function_name' was not registered with a priority above 10."
		);
	}

	/**
	 * @dataProvider switcher_filter_provider
	 */
	public function test_registers_actions_when_switcher_enabled( $filter, $function_name ) {
		update_option( 'wcpay_multi_currency_enable_storefront_switcher', 'yes' );
		// Reinit class to re-evaluate conditional hooks.
		$this->storefront_integration = new StorefrontIntegration( $this->mock_multi_currency );

		$this->assertGreaterThan(
			10,
			has_filter( $filter, [ $this->storefront_integration, $function_name ] ),
			"The filter '$filter' with function '$function_name' was not registered with a priority above 10."
		);
	}

	public function switcher_filter_provider() {
		return [
			[ 'woocommerce_breadcrumb_defaults', 'modify_breadcrumb_defaults' ],
			[ 'wp_enqueue_scripts', 'add_inline_css' ],
		];
	}
}

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
	public function set_up() {
		parent::set_up();

		/** @var MultiCurrency|PHPUnit_Framework_MockObject_MockObject */
		$this->mock_multi_currency    = $this->createMock( MultiCurrency::class );
		$this->storefront_integration = new StorefrontIntegration( $this->mock_multi_currency );
	}

	/**
	 * @dataProvider switcher_filter_provider
	 */
	public function test_does_not_register_actions_when_less_than_2_currencies( $filter, $function_name ) {
		// The breadcrumbs widget should only be registered when 2 or more currencies are enabled.
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn( [ [] ] );
		$this->mock_multi_currency->method( 'is_using_storefront_switcher' )->willReturn( true );
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
	public function test_does_not_register_actions_when_switcher_disabled( $filter, $function_name ) {
		// The breadcrumbs widget should only be registered when 2 or more currencies are enabled.
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn( [ [], [] ] );
		$this->mock_multi_currency->method( 'is_using_storefront_switcher' )->willReturn( false );

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
	public function test_registers_actions_when_switcher_enabled( $filter, $function_name ) {
		// The breadcrumbs widget should only be registered when 2 or more currencies are enabled.
		$this->mock_multi_currency->method( 'get_enabled_currencies' )->willReturn( [ [], [] ] );
		$this->mock_multi_currency->method( 'is_using_storefront_switcher' )->willReturn( true );

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

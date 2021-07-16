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

	public function test_registers_settings_filter() {
		$this->assertSame(
			10,
			has_filter(
				'wcpay_multi_currency_enabled_currencies_settings',
				[ $this->storefront_integration, 'filter_store_settings' ]
			)
		);
	}

	public function test_does_not_register_actions_when_switcher_disabled() {
		$this->mock_multi_currency->method( 'get_theme_stylesheet' )->willReturn( 'storefront' );

		// Update the option, and then recreate the instance before testing.
		update_option( 'wcpay_multi_currency_enable_storefront_switcher', 'no' );
		$this->storefront_integration = new StorefrontIntegration( $this->mock_multi_currency );

		$this->assertFalse(
			has_filter( 'woocommerce_breadcrumb_defaults', [ $this->storefront_integration, 'modify_breadcrumb_wrapper' ] ),
			"The filter 'woocommerce_breadcrumb_defaults' with function 'modify_breadcrumb_wrapper' was found."
		);
		$this->assertFalse(
			has_action( 'wp_enqueue_scripts', [ $this->storefront_integration, 'add_inline_css' ] ),
			"The action 'wp_enqueue_scripts' with function 'add_inline_css' was found."
		);
		$this->assertFalse(
			has_action( 'storefront_before_content', [ $this->storefront_integration, 'add_switcher_widget' ] ),
			"The action 'storefront_before_content' with function 'add_switcher_widget' was found."
		);
		$this->assertFalse(
			has_action( 'storefront_before_content', [ $this->storefront_integration, 'close_breadcrumb_wrapper' ] ),
			"The action 'storefront_before_content' with function 'close_breadcrumb_wrapper' was found."
		);
	}

	public function test_registers_default_actions_when_switcher_enabled() {
		$this->mock_multi_currency->method( 'get_theme_stylesheet' )->willReturn( 'storefront' );

		// Due to we are testing conditional actions/filters, we need to reinit the class.
		$this->storefront_integration = new StorefrontIntegration( $this->mock_multi_currency );

		$this->assertGreaterThan(
			10,
			has_filter( 'woocommerce_breadcrumb_defaults', [ $this->storefront_integration, 'modify_breadcrumb_wrapper' ] ),
			"The filter 'woocommerce_breadcrumb_defaults' with function 'modify_breadcrumb_wrapper' was not registered with a priority above 10."
		);
		$this->assertGreaterThan(
			10,
			has_action( 'wp_enqueue_scripts', [ $this->storefront_integration, 'add_inline_css' ] ),
			"The action 'wp_enqueue_scripts' with function 'add_inline_css' was not registered with a priority above 10."
		);
		$this->assertGreaterThan(
			10,
			has_action( 'storefront_before_content', [ $this->storefront_integration, 'add_switcher_widget' ] ),
			"The action 'storefront_before_content' with function 'add_switcher_widget' was not registered with a priority above 10."
		);
		$this->assertGreaterThan(
			10,
			has_action( 'storefront_before_content', [ $this->storefront_integration, 'close_breadcrumb_wrapper' ] ),
			"The action 'storefront_before_content' with function 'close_breadcrumb_wrapper' was not registered with a priority above 10."
		);
	}

	public function test_registers_wrapper_theme_actions_when_switcher_enabled_and_wrapper_theme_found() {
		$this->mock_multi_currency->method( 'get_theme_stylesheet' )->willReturn( 'arcade' );

		// Due to we are testing conditional actions/filters, we need to reinit the class.
		$this->storefront_integration = new StorefrontIntegration( $this->mock_multi_currency );

		$this->assertGreaterThan(
			10,
			has_filter( 'woocommerce_breadcrumb_defaults', [ $this->storefront_integration, 'modify_breadcrumb_wrapper' ] ),
			"The filter 'woocommerce_breadcrumb_defaults' with function 'modify_breadcrumb_wrapper' was not registered with a priority above 10."
		);
		$this->assertGreaterThan(
			10,
			has_action( 'wp_enqueue_scripts', [ $this->storefront_integration, 'add_inline_css' ] ),
			"The action 'wp_enqueue_scripts' with function 'add_inline_css' was not registered with a priority above 10."
		);
		$this->assertGreaterThan(
			10,
			has_action( 'storefront_content_top', [ $this->storefront_integration, 'add_switcher_widget' ] ),
			"The action 'storefront_content_top' with function 'add_switcher_widget' was not registered with a priority above 10."
		);
		$this->assertGreaterThan(
			10,
			has_action( 'storefront_content_top', [ $this->storefront_integration, 'close_breadcrumb_wrapper' ] ),
			"The action 'storefront_content_top' with function 'close_breadcrumb_wrapper' was not registered with a priority above 10."
		);
	}
}

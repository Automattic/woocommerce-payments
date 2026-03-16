<?php
/**
 * Class WCPay_Multi_Currency_Async_Price_Renderer_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\AsyncPriceRenderer;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * AsyncPriceRenderer unit tests.
 */
class WCPay_Multi_Currency_Async_Price_Renderer_Tests extends WCPAY_UnitTestCase {

	/**
	 * Mock MultiCurrency.
	 *
	 * @var MultiCurrency|PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_multi_currency;

	/**
	 * The system under test.
	 *
	 * @var AsyncPriceRenderer
	 */
	private $renderer;

	/**
	 * Pre-test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_multi_currency = $this->createMock( MultiCurrency::class );

		$this->renderer = new AsyncPriceRenderer(
			$this->mock_multi_currency
		);
	}

	/**
	 * Test that wrap_price_with_skeleton produces correct HTML.
	 */
	public function test_wrap_price_with_skeleton_produces_correct_html() {
		$original_price_html = '<span class="woocommerce-Price-amount amount">$10.00</span>';
		$result              = $this->renderer->wrap_price_with_skeleton(
			$original_price_html,
			10.00,
			[],
			10.00,
			10.00
		);

		$this->assertStringContainsString( 'woocommerce-Price-amount', $result );
		$this->assertStringContainsString( 'wcpay-async-price', $result );
		$this->assertStringContainsString( 'data-wcpay-price="10"', $result );
		$this->assertStringContainsString( 'data-wcpay-price-type="product"', $result );
		// Skeleton is a <bdi> to match wc_price() structure.
		$this->assertStringContainsString( '<bdi class="wcpay-price-skeleton"></bdi>', $result );
		// Screen-reader placeholder for crawlers and a11y before JS loads.
		$this->assertStringContainsString( 'wcpay-price-placeholder', $result );
		$this->assertStringContainsString( 'screen-reader-text', $result );
		$this->assertStringContainsString( '$10.00', $result );
	}

	/**
	 * Test that init_hooks registers hooks when cache-optimized mode is active and no session exists.
	 */
	public function test_init_hooks_registers_hooks_when_conditions_met() {
		$this->mock_multi_currency
			->method( 'is_cache_optimized_mode' )
			->willReturn( true );
		$this->mock_multi_currency
			->method( 'has_active_session' )
			->willReturn( false );

		$this->renderer->init_hooks();

		$this->assertSame(
			999,
			has_filter( 'wc_price', [ $this->renderer, 'wrap_price_with_skeleton' ] )
		);
		$this->assertNotFalse(
			has_action( 'wp_enqueue_scripts', [ $this->renderer, 'enqueue_async_renderer' ] )
		);
	}

	/**
	 * Test that init_hooks does not hook when cache-optimized mode is not active.
	 */
	public function test_init_hooks_returns_early_when_not_cache_optimized() {
		$this->mock_multi_currency
			->method( 'is_cache_optimized_mode' )
			->willReturn( false );

		$this->renderer->init_hooks();

		$this->assertFalse(
			has_filter( 'wc_price', [ $this->renderer, 'wrap_price_with_skeleton' ] )
		);
	}

	/**
	 * Test that init_hooks does not hook when there is an active session.
	 */
	public function test_init_hooks_returns_early_with_active_session() {
		$this->mock_multi_currency
			->method( 'is_cache_optimized_mode' )
			->willReturn( true );
		$this->mock_multi_currency
			->method( 'has_active_session' )
			->willReturn( true );

		$this->renderer->init_hooks();

		$this->assertFalse(
			has_filter( 'wc_price', [ $this->renderer, 'wrap_price_with_skeleton' ] )
		);
	}

	/**
	 * Test that the price data attribute uses the raw price value.
	 */
	public function test_wrap_price_with_skeleton_uses_raw_price() {
		$result = $this->renderer->wrap_price_with_skeleton(
			'<span>$25.99</span>',
			25.99,
			[],
			25.99,
			25.99
		);

		$this->assertStringContainsString( 'data-wcpay-price="25.99"', $result );
	}

	/**
	 * Test that enqueue_async_renderer registers the script and localizes the config.
	 */
	public function test_enqueue_async_renderer_localizes_config_data() {
		// Register the script manually since register_script_with_dependencies is mocked.
		wp_register_script( 'wcpay-multi-currency-async-renderer', false, [], '1.0', true );

		$this->mock_multi_currency
			->method( 'register_script_with_dependencies' )
			->willReturn( null );
		$this->mock_multi_currency
			->method( 'get_file_version' )
			->willReturn( '1.0' );

		$this->renderer->enqueue_async_renderer();

		$data = wp_scripts()->get_data( 'wcpay-multi-currency-async-renderer', 'data' );
		$this->assertNotEmpty( $data, 'wp_localize_script should have set data on the script handle.' );
		$this->assertStringContainsString( 'wcpayAsyncPriceConfig', $data );
		$this->assertStringContainsString( 'apiUrl', $data );
		$this->assertStringContainsString( 'defaultCurrency', $data );
		$this->assertTrue( wp_script_is( 'wcpay-multi-currency-async-renderer', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wcpay-multi-currency-async-renderer', 'enqueued' ) );
	}

	/**
	 * Test that enqueue_async_renderer decodes HTML entities in the currency symbol.
	 *
	 * get_woocommerce_currency_symbol() returns HTML entities (e.g. &euro; for EUR).
	 * The localized data must contain the decoded character, not the raw entity.
	 */
	public function test_enqueue_async_renderer_decodes_currency_symbol_entities() {
		// Use a filter to override the currency reliably in the test environment.
		// Priority 901 is needed to override FrontendCurrencies which hooks at 900.
		$force_eur = function () {
			return 'EUR';
		};
		add_filter( 'woocommerce_currency', $force_eur, 901 );

		wp_register_script( 'wcpay-multi-currency-async-renderer', false, [], '1.0', true );

		$this->mock_multi_currency
			->method( 'register_script_with_dependencies' )
			->willReturn( null );
		$this->mock_multi_currency
			->method( 'get_file_version' )
			->willReturn( '1.0' );

		$this->renderer->enqueue_async_renderer();

		$data = wp_scripts()->get_data( 'wcpay-multi-currency-async-renderer', 'data' );

		// Decoded euro sign must be present; raw entity must not.
		$this->assertStringContainsString( json_encode( '€' ), $data );
		$this->assertStringNotContainsString( '&euro;', $data );
		$this->assertStringNotContainsString( '&#', $data );

		remove_filter( 'woocommerce_currency', $force_eur, 901 );
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down() {
		delete_option( '_wcpay_feature_mc_cache_optimized' );
		wp_deregister_script( 'wcpay-multi-currency-async-renderer' );
		wp_deregister_style( 'wcpay-multi-currency-async-renderer' );
		parent::tear_down();
	}
}

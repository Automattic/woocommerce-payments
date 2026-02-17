<?php
/**
 * Class WCPay_Multi_Currency_Async_Price_Renderer_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\AsyncPriceRenderer;
use WCPay\MultiCurrency\Compatibility;
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
	 * Mock Compatibility.
	 *
	 * @var Compatibility|PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_compatibility;

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
		$this->mock_compatibility  = $this->createMock( Compatibility::class );

		$this->renderer = new AsyncPriceRenderer(
			$this->mock_multi_currency,
			$this->mock_compatibility
		);
	}

	/**
	 * Test that wrap_price_with_skeleton produces correct HTML.
	 */
	public function test_wrap_price_with_skeleton_produces_correct_html() {
		$result = $this->renderer->wrap_price_with_skeleton(
			'<span class="woocommerce-Price-amount amount">$10.00</span>',
			10.00,
			[],
			10.00,
			10.00
		);

		$this->assertStringContainsString( 'wcpay-async-price', $result );
		$this->assertStringContainsString( 'data-wcpay-price="10"', $result );
		$this->assertStringContainsString( 'data-wcpay-price-type="product"', $result );
		$this->assertStringContainsString( 'wcpay-price-skeleton', $result );
	}

	/**
	 * Test that wrap_price_with_skeleton returns original value in admin.
	 */
	public function test_wrap_price_with_skeleton_returns_original_in_admin() {
		set_current_screen( 'edit-post' );

		$original = '<span class="woocommerce-Price-amount amount">$10.00</span>';
		$result   = $this->renderer->wrap_price_with_skeleton(
			$original,
			10.00,
			[],
			10.00,
			10.00
		);

		$this->assertEquals( $original, $result );

		set_current_screen( 'front' );
	}

	/**
	 * Test that init_hooks does not hook when feature flag is disabled.
	 */
	public function test_init_hooks_returns_early_when_feature_flag_disabled() {
		update_option( '_wcpay_feature_mc_cache_optimized', '0' );

		$this->mock_multi_currency
			->method( 'is_cache_optimized_mode' )
			->willReturn( true );

		$this->renderer->init_hooks();

		$this->assertFalse(
			has_filter( 'wc_price', [ $this->renderer, 'wrap_price_with_skeleton' ] )
		);
	}

	/**
	 * Test that init_hooks does not hook in speed mode.
	 */
	public function test_init_hooks_returns_early_in_speed_mode() {
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );

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
		update_option( '_wcpay_feature_mc_cache_optimized', '1' );

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
	 * Clean up after tests.
	 */
	public function tear_down() {
		delete_option( '_wcpay_feature_mc_cache_optimized' );
		parent::tear_down();
	}
}

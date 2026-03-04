<?php
/**
 * Class WC_Payments_Styles_Cache_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Styles_Cache unit tests.
 */
class WC_Payments_Styles_Cache_Test extends WCPAY_UnitTestCase {

	public function test_get_styles_cache_version_returns_md5_string() {
		delete_option( 'wcpay_styles_cache_version' );
		$version = WC_Payments_Styles_Cache::get_styles_cache_version();
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $version );
	}

	public function test_get_styles_cache_version_stores_in_option() {
		delete_option( 'wcpay_styles_cache_version' );
		$version = WC_Payments_Styles_Cache::get_styles_cache_version();
		$this->assertEquals( $version, get_option( 'wcpay_styles_cache_version' ) );
	}

	public function test_get_styles_cache_version_reads_from_option() {
		update_option( 'wcpay_styles_cache_version', 'cached_hash_value' );
		$version = WC_Payments_Styles_Cache::get_styles_cache_version();
		$this->assertEquals( 'cached_hash_value', $version );
		delete_option( 'wcpay_styles_cache_version' );
	}

	public function test_invalidate_styles_cache_version_deletes_option() {
		update_option( 'wcpay_styles_cache_version', 'some_hash' );
		WC_Payments_Styles_Cache::invalidate_styles_cache_version();
		$this->assertFalse( get_option( 'wcpay_styles_cache_version' ) );
	}

	public function test_styles_cache_invalidation_hooks_registered() {
		$this->assertNotFalse(
			has_action( 'after_switch_theme', [ 'WC_Payments_Styles_Cache', 'invalidate_styles_cache_version' ] ),
			'after_switch_theme hook not registered.'
		);
		$this->assertNotFalse(
			has_action( 'save_post_wp_global_styles', [ 'WC_Payments_Styles_Cache', 'invalidate_styles_cache_version' ] ),
			'save_post_wp_global_styles hook not registered.'
		);
		$this->assertNotFalse(
			has_action( 'customize_save_after', [ 'WC_Payments_Styles_Cache', 'invalidate_styles_cache_version' ] ),
			'customize_save_after hook not registered.'
		);
		$this->assertNotFalse(
			has_action( 'woocommerce_woocommerce_payments_updated', [ 'WC_Payments_Styles_Cache', 'invalidate_styles_cache_version' ] ),
			'woocommerce_woocommerce_payments_updated hook not registered.'
		);
	}

	public function test_get_styles_cache_version_recomputes_after_invalidation() {
		// Populate the cache.
		delete_option( 'wcpay_styles_cache_version' );
		$first_version = WC_Payments_Styles_Cache::get_styles_cache_version();

		// Invalidate.
		WC_Payments_Styles_Cache::invalidate_styles_cache_version();
		$this->assertFalse( get_option( 'wcpay_styles_cache_version' ) );

		// Recompute — should get a new stored value.
		$second_version = WC_Payments_Styles_Cache::get_styles_cache_version();
		$this->assertNotEmpty( get_option( 'wcpay_styles_cache_version' ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $second_version );
	}
}

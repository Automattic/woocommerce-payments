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

	public function test_handle_theme_change_hooks_registered() {
		$this->assertNotFalse(
			has_action( 'after_switch_theme', [ 'WC_Payments_Styles_Cache', 'handle_theme_change' ] ),
			'after_switch_theme hook not registered.'
		);
		$this->assertNotFalse(
			has_action( 'save_post_wp_global_styles', [ 'WC_Payments_Styles_Cache', 'handle_theme_change' ] ),
			'save_post_wp_global_styles hook not registered.'
		);
		$this->assertNotFalse(
			has_action( 'customize_save_after', [ 'WC_Payments_Styles_Cache', 'handle_theme_change' ] ),
			'customize_save_after hook not registered.'
		);
		$this->assertNotFalse(
			has_action( 'woocommerce_woocommerce_payments_updated', [ 'WC_Payments_Styles_Cache', 'handle_theme_change' ] ),
			'woocommerce_woocommerce_payments_updated hook not registered.'
		);
	}

	public function test_set_and_get_woopay_appearance() {
		delete_option( 'wcpay_woopay_checkout_appearance' );
		delete_option( 'wcpay_styles_cache_version' );

		$appearance = [
			'theme' => 'stripe',
			'rules' => [ '.Input' => [ 'color' => '#333' ] ],
		];

		WC_Payments_Styles_Cache::set_woopay_appearance( $appearance );
		$result = WC_Payments_Styles_Cache::get_woopay_appearance();

		$this->assertEquals( $appearance, $result );
	}

	public function test_get_woopay_appearance_returns_null_on_version_mismatch() {
		// Force classic theme so get_woopay_appearance() does not auto-compute.
		add_filter(
			'stylesheet',
			function () {
				return 'default';
			}
		);

		delete_option( 'wcpay_styles_cache_version' );

		$appearance = [ 'theme' => 'stripe' ];
		WC_Payments_Styles_Cache::set_woopay_appearance( $appearance );

		// Invalidate the styles cache version so a new one is computed.
		WC_Payments_Styles_Cache::invalidate_styles_cache_version();

		// Manually set a different version to simulate a theme change.
		update_option( 'wcpay_styles_cache_version', 'different_version' );

		$result = WC_Payments_Styles_Cache::get_woopay_appearance();
		$this->assertNull( $result );
	}

	public function test_get_woopay_appearance_returns_null_when_empty() {
		delete_option( 'wcpay_woopay_checkout_appearance' );

		$result = WC_Payments_Styles_Cache::get_woopay_appearance();
		$this->assertNull( $result );
	}

	public function test_invalidate_woopay_appearance_deletes_option() {
		WC_Payments_Styles_Cache::set_woopay_appearance( [ 'theme' => 'stripe' ] );
		WC_Payments_Styles_Cache::invalidate_woopay_appearance();

		$this->assertFalse( get_option( 'wcpay_woopay_checkout_appearance' ) );
	}

	public function test_maybe_set_woopay_appearance_stores_when_empty() {
		delete_option( 'wcpay_woopay_checkout_appearance' );
		delete_option( 'wcpay_styles_cache_version' );

		$appearance = [
			'theme' => 'stripe',
			'rules' => [ '.Input' => [ 'color' => '#333' ] ],
		];

		$result = WC_Payments_Styles_Cache::maybe_set_woopay_appearance( $appearance );
		$this->assertTrue( $result );
		$this->assertEquals( $appearance, WC_Payments_Styles_Cache::get_woopay_appearance() );
	}

	public function test_maybe_set_woopay_appearance_rejects_when_slot_filled() {
		delete_option( 'wcpay_woopay_checkout_appearance' );
		delete_option( 'wcpay_styles_cache_version' );

		$first  = [
			'theme' => 'stripe',
			'rules' => [],
		];
		$second = [
			'theme' => 'night',
			'rules' => [],
		];

		WC_Payments_Styles_Cache::set_woopay_appearance( $first );
		$result = WC_Payments_Styles_Cache::maybe_set_woopay_appearance( $second );

		$this->assertFalse( $result );
		$this->assertEquals( $first, WC_Payments_Styles_Cache::get_woopay_appearance() );
	}

	public function test_validate_appearance_schema_accepts_valid_appearance() {
		$appearance = [
			'theme'     => 'stripe',
			'labels'    => 'floating',
			'variables' => [
				'colorBackground' => '#ffffff',
				'colorText'       => '#333333',
				'fontFamily'      => 'Arial, sans-serif',
				'fontSizeBase'    => '16px',
			],
			'rules'     => [
				'.Input' => [
					'color'      => '#333',
					'fontFamily' => 'Arial',
				],
				'.Label' => [
					'color' => '#666',
				],
			],
		];

		$this->assertTrue( WC_Payments_Styles_Cache::validate_appearance_schema( $appearance ) );
	}

	public function test_validate_appearance_schema_accepts_footer_link_rule() {
		$appearance = [
			'rules' => [
				'.Footer-link' => [
					'color' => '#333',
				],
			],
		];

		$this->assertTrue( WC_Payments_Styles_Cache::validate_appearance_schema( $appearance ) );
	}

	public function test_validate_appearance_schema_rejects_invalid_theme() {
		$appearance = [ 'theme' => 'invalid_theme' ];
		$this->assertFalse( WC_Payments_Styles_Cache::validate_appearance_schema( $appearance ) );
	}

	public function test_validate_appearance_schema_rejects_unknown_top_key() {
		$appearance = [
			'theme'     => 'stripe',
			'malicious' => 'data',
		];
		$this->assertFalse( WC_Payments_Styles_Cache::validate_appearance_schema( $appearance ) );
	}

	public function test_validate_appearance_schema_rejects_unknown_rule_key() {
		$appearance = [
			'rules' => [
				'.UnknownElement' => [ 'color' => '#333' ],
			],
		];
		$this->assertFalse( WC_Payments_Styles_Cache::validate_appearance_schema( $appearance ) );
	}

	public function test_validate_appearance_schema_rejects_long_values() {
		$appearance = [
			'variables' => [
				'colorBackground' => str_repeat( 'a', 201 ),
			],
		];
		$this->assertFalse( WC_Payments_Styles_Cache::validate_appearance_schema( $appearance ) );
	}

	public function test_validate_appearance_schema_rejects_non_string_values() {
		$appearance = [
			'variables' => [
				'colorBackground' => 12345,
			],
		];
		$this->assertFalse( WC_Payments_Styles_Cache::validate_appearance_schema( $appearance ) );
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

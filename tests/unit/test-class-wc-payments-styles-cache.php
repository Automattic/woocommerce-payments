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
		// Force a non-block theme so get_woopay_appearance() does not auto-compute.
		$stylesheet_filter = function () {
			return 'default';
		};
		add_filter( 'stylesheet', $stylesheet_filter );

		try {
			delete_option( 'wcpay_styles_cache_version' );

			$appearance = [ 'theme' => 'stripe' ];
			WC_Payments_Styles_Cache::set_woopay_appearance( $appearance );

			// Invalidate the styles cache version so a new one is computed.
			WC_Payments_Styles_Cache::invalidate_styles_cache_version();

			// Manually set a different version to simulate a theme change.
			update_option( 'wcpay_styles_cache_version', 'different_version' );

			$result = WC_Payments_Styles_Cache::get_woopay_appearance();
			$this->assertNull( $result );
		} finally {
			remove_filter( 'stylesheet', $stylesheet_filter );
		}
	}

	public function test_get_woopay_appearance_returns_null_when_empty() {
		// Force a non-block theme so get_woopay_appearance() does not auto-compute.
		$stylesheet_filter = function () {
			return 'default';
		};
		add_filter( 'stylesheet', $stylesheet_filter );

		try {
			delete_option( 'wcpay_woopay_checkout_appearance' );

			$result = WC_Payments_Styles_Cache::get_woopay_appearance();
			$this->assertNull( $result );
		} finally {
			remove_filter( 'stylesheet', $stylesheet_filter );
		}
	}

	public function test_invalidate_woopay_appearance_deletes_option() {
		WC_Payments_Styles_Cache::set_woopay_appearance( [ 'theme' => 'stripe' ] );
		WC_Payments_Styles_Cache::invalidate_woopay_appearance();

		$this->assertFalse( get_option( 'wcpay_woopay_checkout_appearance' ) );
	}

	public function test_maybe_set_woopay_appearance_stores_when_empty() {
		// Force a non-block theme so get_woopay_appearance() does not auto-compute.
		$stylesheet_filter = function () {
			return 'default';
		};
		add_filter( 'stylesheet', $stylesheet_filter );

		try {
			delete_option( 'wcpay_woopay_checkout_appearance' );
			delete_option( 'wcpay_styles_cache_version' );

			$appearance = [
				'theme' => 'stripe',
				'rules' => [ '.Input' => [ 'color' => '#333' ] ],
			];

			$result = WC_Payments_Styles_Cache::maybe_set_woopay_appearance( $appearance );
			$this->assertTrue( $result );
			$this->assertEquals( $appearance, WC_Payments_Styles_Cache::get_woopay_appearance() );
		} finally {
			remove_filter( 'stylesheet', $stylesheet_filter );
		}
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

	public function test_set_woopay_appearance_stores_font_rules() {
		delete_option( 'wcpay_woopay_checkout_appearance' );
		delete_option( 'wcpay_styles_cache_version' );

		$appearance = [
			'theme' => 'stripe',
			'rules' => [ '.Input' => [ 'color' => '#333' ] ],
		];
		$font_rules = [
			[ 'cssSrc' => 'https://fonts.googleapis.com/css?family=Roboto' ],
			[ 'cssSrc' => 'https://fonts.bunny.net/css?family=Inter' ],
		];

		WC_Payments_Styles_Cache::set_woopay_appearance( $appearance, $font_rules );

		$this->assertEquals( $appearance, WC_Payments_Styles_Cache::get_woopay_appearance() );
		$this->assertEquals( $font_rules, WC_Payments_Styles_Cache::get_woopay_font_rules() );
	}

	public function test_get_woopay_font_rules_returns_empty_when_not_set() {
		// Force a non-block theme so get_woopay_appearance() does not auto-compute.
		$stylesheet_filter = function () {
			return 'default';
		};
		add_filter( 'stylesheet', $stylesheet_filter );

		try {
			delete_option( 'wcpay_woopay_checkout_appearance' );

			$result = WC_Payments_Styles_Cache::get_woopay_font_rules();
			$this->assertEmpty( $result );
		} finally {
			remove_filter( 'stylesheet', $stylesheet_filter );
		}
	}

	public function test_get_woopay_font_rules_returns_empty_on_version_mismatch() {
		// Force a non-block theme so get_woopay_appearance() does not auto-compute.
		$stylesheet_filter = function () {
			return 'default';
		};
		add_filter( 'stylesheet', $stylesheet_filter );

		try {
			delete_option( 'wcpay_styles_cache_version' );

			$appearance = [ 'theme' => 'stripe' ];
			$font_rules = [
				[ 'cssSrc' => 'https://fonts.googleapis.com/css?family=Roboto' ],
			];
			WC_Payments_Styles_Cache::set_woopay_appearance( $appearance, $font_rules );

			// Invalidate the styles cache version so a new one is computed.
			WC_Payments_Styles_Cache::invalidate_styles_cache_version();

			// Manually set a different version to simulate a theme change.
			update_option( 'wcpay_styles_cache_version', 'different_version' );

			$result = WC_Payments_Styles_Cache::get_woopay_font_rules();
			$this->assertEmpty( $result );
		} finally {
			remove_filter( 'stylesheet', $stylesheet_filter );
		}
	}

	public function test_get_font_rules_from_registered_styles_extracts_cdn_urls() {
		// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Test fixtures for CDN font stylesheets.
		wp_register_style( 'test-google-font', 'https://fonts.googleapis.com/css?family=Roboto', [], null );
		wp_register_style( 'test-bunny-font', 'https://fonts.bunny.net/css?family=Inter', [], null );
		// phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion

		try {
			$result = WC_Payments_Styles_Cache::get_font_rules_from_registered_styles();

			$sources = array_column( $result, 'cssSrc' );
			$this->assertContains( 'https://fonts.googleapis.com/css?family=Roboto', $sources );
			$this->assertContains( 'https://fonts.bunny.net/css?family=Inter', $sources );
		} finally {
			wp_deregister_style( 'test-google-font' );
			wp_deregister_style( 'test-bunny-font' );
		}
	}

	public function test_get_font_rules_from_registered_styles_ignores_non_cdn_urls() {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Test fixture.
		wp_register_style( 'test-non-cdn', 'https://example.com/styles.css', [], null );

		try {
			$result  = WC_Payments_Styles_Cache::get_font_rules_from_registered_styles();
			$sources = array_column( $result, 'cssSrc' );
			$this->assertNotContains( 'https://example.com/styles.css', $sources );
		} finally {
			wp_deregister_style( 'test-non-cdn' );
		}
	}

	public function test_get_font_rules_from_registered_styles_caps_at_10() {
		$handles = [];
		// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Test fixtures.
		for ( $i = 0; $i < 12; $i++ ) {
			$handle    = 'test-font-cap-' . $i;
			$handles[] = $handle;
			wp_register_style( $handle, 'https://fonts.googleapis.com/css?family=Font' . $i, [], null );
		}
		// phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion

		try {
			$result = WC_Payments_Styles_Cache::get_font_rules_from_registered_styles();
			$this->assertCount( 10, $result );
		} finally {
			foreach ( $handles as $handle ) {
				wp_deregister_style( $handle );
			}
		}
	}

	public function test_maybe_set_woopay_appearance_stores_font_rules() {
		// Force a non-block theme so get_woopay_appearance() does not auto-compute.
		$stylesheet_filter = function () {
			return 'default';
		};
		add_filter( 'stylesheet', $stylesheet_filter );

		try {
			delete_option( 'wcpay_woopay_checkout_appearance' );
			delete_option( 'wcpay_styles_cache_version' );

			$appearance = [
				'theme' => 'stripe',
				'rules' => [ '.Input' => [ 'color' => '#333' ] ],
			];
			$font_rules = [
				[ 'cssSrc' => 'https://fonts.googleapis.com/css?family=Roboto' ],
			];

			$result = WC_Payments_Styles_Cache::maybe_set_woopay_appearance( $appearance, $font_rules );
			$this->assertTrue( $result );
			$this->assertEquals( $font_rules, WC_Payments_Styles_Cache::get_woopay_font_rules() );
		} finally {
			remove_filter( 'stylesheet', $stylesheet_filter );
		}
	}

	public function test_resolve_style_value_returns_string_as_is() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_style_value' );
		$method->setAccessible( true );

		$result = $method->invoke( null, '#ffffff', '#000000' );
		$this->assertSame( '#ffffff', $result );
	}

	public function test_resolve_style_value_returns_default_for_non_string_array() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_style_value' );
		$method->setAccessible( true );

		$result = $method->invoke( null, [ 'unexpected' => 'array' ], '#000000' );
		$this->assertSame( '#000000', $result );
	}

	public function test_resolve_style_value_returns_default_for_non_string_non_array() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_style_value' );
		$method->setAccessible( true );

		$result = $method->invoke( null, 12345, 'fallback' );
		$this->assertSame( 'fallback', $result );
	}

	public function test_resolve_style_value_resolves_ref_object() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_style_value' );
		$method->setAccessible( true );

		$styles_context = [
			'typography' => [
				'fontFamily' => 'TestFont, sans-serif',
			],
		];

		$ref_value = [ 'ref' => 'styles.typography.fontFamily' ];
		$result    = $method->invoke( null, $ref_value, 'inherit', $styles_context );

		$this->assertSame( 'TestFont, sans-serif', $result );
	}

	public function test_resolve_style_value_returns_default_for_null() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_style_value' );
		$method->setAccessible( true );

		$result = $method->invoke( null, null, 'default_val' );
		$this->assertSame( 'default_val', $result );
	}

	public function test_resolve_style_value_returns_default_for_non_existent_ref_path() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_style_value' );
		$method->setAccessible( true );

		$styles_context = [
			'typography' => [
				'fontFamily' => 'TestFont, sans-serif',
			],
		];

		$ref_value = [ 'ref' => 'styles.nonexistent.key' ];
		$result    = $method->invoke( null, $ref_value, 'fallback', $styles_context );
		$this->assertSame( 'fallback', $result );
	}

	public function test_resolve_css_var_returns_empty_string_for_non_string_input() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_css_var' );
		$method->setAccessible( true );

		$this->assertSame( '', $method->invoke( null, 12345 ) );
		$this->assertSame( '', $method->invoke( null, null ) );
		$this->assertSame( '', $method->invoke( null, [ 'ref' => 'something' ] ) );
	}

	public function test_compute_woopay_appearance_does_not_fatal_with_ref_objects() {
		// Simulate a theme where button fontFamily is a ref object pointing
		// to the root typography fontFamily (a concrete string value).
		// Note: whether wp_get_global_styles() surfaces filtered data depends
		// on the active theme (block vs classic) — CI may not have a block theme.
		// The ref-resolution logic itself is covered by the resolve_style_value tests.
		add_filter(
			'wp_theme_json_data_default',
			function ( $theme_json ) {
				$data                                       = $theme_json->get_data();
				$data['styles']['typography']['fontFamily'] = 'TestFont, sans-serif';
				$data['styles']['elements']['button']['typography']['fontFamily'] = [
					'ref' => 'styles.typography.fontFamily',
				];
				return $theme_json->update_with( $data );
			}
		);

		try {
			$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'compute_woopay_appearance_from_theme' );
			$method->setAccessible( true );

			// This should NOT throw a TypeError.
			$result = $method->invoke( null );

			$this->assertIsArray( $result );
			$this->assertArrayHasKey( 'variables', $result );
			$this->assertArrayHasKey( 'rules', $result );
			// fontFamily must be a string (resolved or fallback), never an array.
			$this->assertIsString( $result['variables']['fontFamily'] );
			$this->assertIsString( $result['rules']['.Button']['fontFamily'] );
		} finally {
			remove_all_filters( 'wp_theme_json_data_default' );
		}
	}
}

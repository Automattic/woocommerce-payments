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
		wp_register_style( 'test-wp-font', 'https://fonts.wp.com/css?family=Open+Sans', [], null );
		// phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion

		try {
			$result = WC_Payments_Styles_Cache::get_font_rules_from_registered_styles();

			$sources = array_column( $result, 'cssSrc' );
			$this->assertContains( 'https://fonts.googleapis.com/css?family=Roboto', $sources );
			$this->assertContains( 'https://fonts.bunny.net/css?family=Inter', $sources );
			$this->assertContains( 'https://fonts.wp.com/css?family=Open+Sans', $sources );
		} finally {
			wp_deregister_style( 'test-google-font' );
			wp_deregister_style( 'test-bunny-font' );
			wp_deregister_style( 'test-wp-font' );
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

	public function test_resolve_pattern_blocks_replaces_pattern_with_content() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_pattern_blocks' );
		$method->setAccessible( true );

		$pattern_content = '<!-- wp:group {"backgroundColor":"vivid-red"} --><div class="wp-block-group has-vivid-red-background-color has-background"></div><!-- /wp:group -->';

		$registry = WP_Block_Patterns_Registry::get_instance();
		$registry->register(
			'test/resolve-pattern',
			[
				'title'   => 'Test Pattern',
				'content' => $pattern_content,
			]
		);

		try {
			$blocks = [
				[
					'blockName'    => 'core/pattern',
					'attrs'        => [ 'slug' => 'test/resolve-pattern' ],
					'innerBlocks'  => [],
					'innerHTML'    => '',
					'innerContent' => [],
				],
			];

			$resolved = $method->invoke( null, $blocks );

			$this->assertNotEmpty( $resolved );
			$this->assertSame( 'core/group', $resolved[0]['blockName'] );
			$this->assertSame( 'vivid-red', $resolved[0]['attrs']['backgroundColor'] );
		} finally {
			$registry->unregister( 'test/resolve-pattern' );
		}
	}

	public function test_resolve_pattern_blocks_passes_through_non_pattern_blocks() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_pattern_blocks' );
		$method->setAccessible( true );

		$blocks = [
			[
				'blockName'    => 'core/group',
				'attrs'        => [ 'backgroundColor' => 'vivid-red' ],
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			],
		];

		$resolved = $method->invoke( null, $blocks );

		$this->assertCount( 1, $resolved );
		$this->assertSame( 'core/group', $resolved[0]['blockName'] );
		$this->assertSame( 'vivid-red', $resolved[0]['attrs']['backgroundColor'] );
	}

	public function test_resolve_pattern_blocks_keeps_unregistered_pattern() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_pattern_blocks' );
		$method->setAccessible( true );

		$blocks = [
			[
				'blockName'    => 'core/pattern',
				'attrs'        => [ 'slug' => 'nonexistent/pattern' ],
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			],
		];

		$resolved = $method->invoke( null, $blocks );

		$this->assertCount( 1, $resolved );
		$this->assertSame( 'core/pattern', $resolved[0]['blockName'] );
	}

	public function test_extract_block_colors_from_style_variation() {
		// Block style variations require WP 6.6+ (wp_get_block_style_variation_name_from_class)
		// and theme.json v3 schema support (WP 6.6+). Older WP/Gutenberg combinations may
		// have the function polyfilled but lack proper theme.json v3 handling, causing
		// variation data to be silently stripped during schema validation.
		if ( ! function_exists( 'wp_get_block_style_variation_name_from_class' )
			|| version_compare( get_bloginfo( 'version' ), '6.6', '<' ) ) {
			$this->markTestSkipped( 'Block style variations require WordPress 6.6+.' );
		}

		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'extract_block_colors' );
		$method->setAccessible( true );

		// Ensure core/group is a registered block type so theme.json schema
		// validation accepts block-level style data for it.
		$registered_group = WP_Block_Type_Registry::get_instance()->is_registered( 'core/group' );
		if ( ! $registered_group ) {
			register_block_type( 'core/group', [] );
		}

		// Register the block style variation so WP_Theme_JSON::sanitize()
		// doesn't strip it during schema validation.
		register_block_style(
			'core/group',
			[
				'name'  => 'test-variation',
				'label' => 'Test',
			]
		);

		// Clear any cached theme.json data before injecting our variation.
		WP_Theme_JSON_Resolver::clean_cached_data();

		// Inject a style variation via the wp_theme_json_data_default filter.
		$filter = function ( $theme_json ) {
			return $theme_json->update_with(
				[
					'version' => 3,
					'styles'  => [
						'blocks' => [
							'core/group' => [
								'variations' => [
									'test-variation' => [
										'color' => [
											'background' => '#112233',
											'text'       => '#aabbcc',
										],
									],
								],
							],
						],
					],
				]
			);
		};
		add_filter( 'wp_theme_json_data_default', $filter );

		try {
			$block = [
				'blockName'    => 'core/group',
				'attrs'        => [ 'className' => 'is-style-test-variation' ],
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			];

			$colors = $method->invoke( null, $block );

			$this->assertSame( '#112233', $colors['background'] );
			$this->assertSame( '#aabbcc', $colors['text'] );
		} finally {
			remove_filter( 'wp_theme_json_data_default', $filter );
			unregister_block_style( 'core/group', 'test-variation' );
			WP_Theme_JSON_Resolver::clean_cached_data();
			if ( ! $registered_group ) {
				unregister_block_type( 'core/group' );
			}
		}
	}

	public function test_extract_block_colors_inline_attrs_take_precedence_over_variation() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'extract_block_colors' );
		$method->setAccessible( true );

		$block = [
			'blockName'    => 'core/group',
			'attrs'        => [
				'className' => 'is-style-some-variation',
				'style'     => [
					'color' => [
						'background' => '#inline-bg',
						'text'       => '#inline-text',
					],
				],
			],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];

		$colors = $method->invoke( null, $block );

		// Inline attributes should win — style variation lookup only runs
		// when no inline colors were found (empty($colors) guard).
		$this->assertSame( '#inline-bg', $colors['background'] );
		$this->assertSame( '#inline-text', $colors['text'] );
	}

	public function test_get_style_variation_colors_returns_empty_without_classname() {
		if ( ! function_exists( 'wp_get_block_style_variation_name_from_class' ) ) {
			$this->markTestSkipped( 'Block style variations require WordPress 6.6+.' );
		}

		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'get_style_variation_colors' );
		$method->setAccessible( true );

		$result = $method->invoke( null, 'core/group', '' );
		$this->assertEmpty( $result );
	}

	public function test_get_style_variation_colors_returns_empty_for_default_style() {
		if ( ! function_exists( 'wp_get_block_style_variation_name_from_class' ) ) {
			$this->markTestSkipped( 'Block style variations require WordPress 6.6+.' );
		}

		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'get_style_variation_colors' );
		$method->setAccessible( true );

		// "is-style-default" should be excluded per WP core behavior.
		$result = $method->invoke( null, 'core/group', 'is-style-default' );
		$this->assertEmpty( $result );
	}

	public function test_classify_block_area_detects_template_part_area() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'classify_block_area' );
		$method->setAccessible( true );

		$block = [
			'blockName'    => 'core/template-part',
			'attrs'        => [
				'slug' => 'footer-dark',
				'area' => 'footer',
			],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];

		$this->assertSame( 'footer', $method->invoke( null, $block ) );
	}

	public function test_classify_block_area_detects_metadata_categories() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'classify_block_area' );
		$method->setAccessible( true );

		$block = [
			'blockName'    => 'core/group',
			'attrs'        => [
				'metadata'  => [
					'categories' => [ 'footer' ],
				],
				'className' => 'is-style-section-1',
			],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];

		$this->assertSame( 'footer', $method->invoke( null, $block ) );
	}

	public function test_classify_block_area_detects_tag_name() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'classify_block_area' );
		$method->setAccessible( true );

		$block = [
			'blockName'    => 'core/group',
			'attrs'        => [ 'tagName' => 'header' ],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];

		$this->assertSame( 'header', $method->invoke( null, $block ) );
	}

	public function test_classify_block_area_returns_null_for_content_blocks() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'classify_block_area' );
		$method->setAccessible( true );

		$block = [
			'blockName'    => 'core/group',
			'attrs'        => [ 'tagName' => 'main' ],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];

		$this->assertNull( $method->invoke( null, $block ) );
	}

	public function test_flatten_blocks_finds_nested_blocks() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'flatten_blocks' );
		$method->setAccessible( true );

		$blocks = [
			[
				'blockName'    => 'core/group',
				'attrs'        => [],
				'innerBlocks'  => [
					[
						'blockName'    => 'core/template-part',
						'attrs'        => [
							'slug' => 'header',
							'area' => 'header',
						],
						'innerBlocks'  => [],
						'innerHTML'    => '',
						'innerContent' => [],
					],
				],
				'innerHTML'    => '',
				'innerContent' => [],
			],
			[
				'blockName'    => 'core/template-part',
				'attrs'        => [
					'slug' => 'footer',
					'area' => 'footer',
				],
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			],
		];

		$flat = $method->invoke( null, $blocks );

		$this->assertCount( 3, $flat );
		// Parent appears before child.
		$this->assertSame( 'core/group', $flat[0]['blockName'] );
		$this->assertSame( 'core/template-part', $flat[1]['blockName'] );
		$this->assertSame( 'header', $flat[1]['attrs']['slug'] );
		$this->assertSame( 'core/template-part', $flat[2]['blockName'] );
		$this->assertSame( 'footer', $flat[2]['attrs']['slug'] );
	}

	public function test_resolve_pattern_blocks_recurses_into_inner_blocks() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'resolve_pattern_blocks' );
		$method->setAccessible( true );

		$pattern_slug    = 'test-theme/nested-pattern';
		$pattern_content = '<!-- wp:template-part {"slug":"header","area":"header"} /-->';

		register_block_pattern(
			$pattern_slug,
			[
				'title'   => 'Test nested pattern',
				'content' => $pattern_content,
			]
		);

		try {
			$blocks = [
				[
					'blockName'    => 'core/group',
					'attrs'        => [],
					'innerBlocks'  => [
						[
							'blockName'    => 'core/pattern',
							'attrs'        => [ 'slug' => $pattern_slug ],
							'innerBlocks'  => [],
							'innerHTML'    => '',
							'innerContent' => [],
						],
					],
					'innerHTML'    => '',
					'innerContent' => [],
				],
			];

			$resolved = $method->invoke( null, $blocks );

			$this->assertCount( 1, $resolved );
			$inner = $resolved[0]['innerBlocks'];
			$this->assertSame( 'core/template-part', $inner[0]['blockName'] );
			$this->assertSame( 'header', $inner[0]['attrs']['slug'] );
		} finally {
			unregister_block_pattern( $pattern_slug );
		}
	}

	public function test_classify_block_area_falls_back_to_theme_attr() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'classify_block_area' );
		$method->setAccessible( true );

		// Template part with no area attr, slug not in active theme,
		// but theme attr points to a theme that has it registered.
		// Since we can't easily register a cross-theme template part in
		// unit tests, we test the branch by providing a theme attr with
		// a non-existent theme — the lookup returns null, so area stays null.
		// The important thing is the code doesn't fatal and returns null.
		$block = [
			'blockName'    => 'core/template-part',
			'attrs'        => [
				'slug'  => 'nonexistent-part',
				'theme' => 'fake-theme/fake-theme',
			],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];

		$this->assertNull( $method->invoke( null, $block ) );
	}

	public function test_classify_block_area_uses_area_attr_before_theme_lookup() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'classify_block_area' );
		$method->setAccessible( true );

		// When area attr is present, the theme lookup is skipped entirely.
		$block = [
			'blockName'    => 'core/template-part',
			'attrs'        => [
				'slug'  => 'checkout-header',
				'area'  => 'header',
				'theme' => 'woocommerce/woocommerce',
			],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];

		$this->assertSame( 'header', $method->invoke( null, $block ) );
	}

	public function test_get_template_part_colors_falls_back_to_theme_attr() {
		$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'get_template_part_colors' );
		$method->setAccessible( true );

		// With a slug not in the active theme and no valid theme fallback,
		// the method should return an empty array without errors.
		$result = $method->invoke( null, 'nonexistent-checkout-header', 'fake-theme/fake-theme' );
		$this->assertSame( [], $result );

		// With no theme arg, same behavior.
		$result = $method->invoke( null, 'nonexistent-checkout-header' );
		$this->assertSame( [], $result );
	}

	public function test_compute_woopay_appearance_maps_input_element_styles() {
		// WooPay requires WP 6.5+. The textInput element key and proper
		// elements.link resolution require WP 6.1+. Skip on older versions
		// where wp_get_global_styles() strips unrecognized element keys.
		if ( version_compare( $GLOBALS['wp_version'], '6.5', '<' ) ) {
			$this->markTestSkipped( 'WooPay appearance extraction requires WP 6.5+.' );
		}

		$filter = function ( $theme_json ) {
			return $theme_json->update_with(
				[
					'version' => 3,
					'styles'  => [
						'color'    => [
							'background' => '#ffffff',
							'text'       => '#000000',
						],
						'elements' => [
							'textInput' => [
								'color' => [
									'background' => '#f0f0f0',
									'text'       => '#333333',
								],
							],
						],
					],
				]
			);
		};
		add_filter( 'wp_theme_json_data_default', $filter );

		try {
			$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'compute_woopay_appearance_from_theme' );
			$method->setAccessible( true );

			WP_Theme_JSON_Resolver::clean_cached_data();
			$result = $method->invoke( null );

			$this->assertNotNull( $result );
			$this->assertArrayHasKey( 'rules', $result );
			$this->assertSame( '#f0f0f0', $result['rules']['.Input']['backgroundColor'] );
			$this->assertSame( '#333333', $result['rules']['.Input']['color'] );
		} finally {
			remove_filter( 'wp_theme_json_data_default', $filter );
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
	}

	public function test_footer_link_falls_back_to_link_color_when_no_footer_part() {
		// WooPay requires WP 6.5+. The elements.link resolution used by
		// this test requires WP 6.1+. Skip on older versions.
		if ( version_compare( $GLOBALS['wp_version'], '6.5', '<' ) ) {
			$this->markTestSkipped( 'WooPay appearance extraction requires WP 6.5+.' );
		}

		// When no footer template part is in the checkout template,
		// $footer_colors is empty, so .Footer-link should fall back to $link_color.
		$filter = function ( $theme_json ) {
			return $theme_json->update_with(
				[
					'version' => 3,
					'styles'  => [
						'color'    => [
							'background' => '#ffffff',
							'text'       => '#000000',
						],
						'elements' => [
							'link' => [
								'color' => [
									'text' => '#0066cc',
								],
							],
						],
					],
				]
			);
		};
		add_filter( 'wp_theme_json_data_default', $filter );

		try {
			$method = new ReflectionMethod( WC_Payments_Styles_Cache::class, 'compute_woopay_appearance_from_theme' );
			$method->setAccessible( true );

			WP_Theme_JSON_Resolver::clean_cached_data();
			$result = $method->invoke( null );

			$this->assertNotNull( $result );
			// Footer-link should use the global link color as fallback.
			$this->assertSame( '#0066cc', $result['rules']['.Footer-link']['color'] );
		} finally {
			remove_filter( 'wp_theme_json_data_default', $filter );
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
	}
}

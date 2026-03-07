<?php
/**
 * Class WC_Payments_Styles_Cache
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages the Stripe Elements appearance styles cache version.
 *
 * The cache version is an MD5 hash derived from the plugin version, active theme,
 * global styles, and theme mods. It is stored as a WP option and used by the
 * frontend to invalidate localStorage appearance caches when the site's visual
 * configuration changes.
 */
class WC_Payments_Styles_Cache {

	/**
	 * Returns the styles cache version string used to invalidate localStorage
	 * appearance caches. Reads from a stored WP option; if missing, computes
	 * and stores it.
	 *
	 * @return string MD5 hash representing the current styles version.
	 */
	public static function get_styles_cache_version(): string {
		$version = get_option( 'wcpay_styles_cache_version' );
		if ( ! empty( $version ) ) {
			return $version;
		}

		$version = self::compute_styles_cache_version();
		update_option( 'wcpay_styles_cache_version', $version, true );
		return $version;
	}

	/**
	 * Deletes the stored cache version so it recomputes on the next page load.
	 * Hooked to after_switch_theme, save_post_wp_global_styles, and customize_save_after.
	 */
	public static function invalidate_styles_cache_version(): void {
		delete_option( 'wcpay_styles_cache_version' );
	}

	/**
	 * Returns the stored WooPay checkout appearance, or null if not set or version mismatch.
	 *
	 * @return array|null The appearance object, or null.
	 */
	public static function get_woopay_appearance(): ?array {
		$stored = get_option( 'wcpay_woopay_checkout_appearance' );
		if ( ! empty( $stored ) && is_array( $stored ) ) {
			if ( ( $stored['version'] ?? '' ) === self::get_styles_cache_version() ) {
				return $stored['appearance'] ?? null;
			}
		}

		// Auto-compute for block themes when no valid stored appearance exists.
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			$appearance = self::compute_woopay_appearance_from_theme();
			if ( null !== $appearance ) {
				self::set_woopay_appearance( $appearance );
				return $appearance;
			}
		}

		return null;
	}

	/**
	 * Stores the WooPay checkout appearance alongside the current cache version.
	 *
	 * @param array $appearance The appearance object to store.
	 */
	public static function set_woopay_appearance( array $appearance ): void {
		update_option(
			'wcpay_woopay_checkout_appearance',
			[
				'appearance' => $appearance,
				'version'    => self::get_styles_cache_version(),
			],
			true
		);
	}

	/**
	 * Deletes the stored WooPay checkout appearance so it recomputes on next precomputation.
	 */
	public static function invalidate_woopay_appearance(): void {
		delete_option( 'wcpay_woopay_checkout_appearance' );
	}

	/**
	 * Called on theme/style change hooks. For block themes, recomputes the
	 * appearance from theme.json and stores it. For classic themes, clears
	 * the stored appearance so it can be repopulated by admin or shopper.
	 */
	public static function handle_theme_change(): void {
		self::invalidate_styles_cache_version();
		self::invalidate_woopay_appearance();

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			$appearance = self::compute_woopay_appearance_from_theme();
			if ( null !== $appearance ) {
				self::set_woopay_appearance( $appearance );
			}
		}
	}

	/**
	 * Computes WooPay appearance from theme.json global styles (block themes only).
	 *
	 * @return array|null The appearance object, or null if data is insufficient.
	 */
	public static function compute_woopay_appearance_from_theme(): ?array {
		if ( ! function_exists( 'wp_get_global_styles' ) ) {
			return null;
		}

		$styles = wp_get_global_styles();

		// Template part styles (used by header/footer in block themes).
		$tp_styles = wp_get_global_styles( [], [ 'block_name' => 'core/template-part' ] );

		// Extract colors and resolve any CSS custom property references
		// like var(--wp--preset--color--base) to concrete values.
		$bg_color   = self::resolve_css_var( $styles['color']['background'] ?? '#ffffff' );
		$text_color = self::resolve_css_var( $styles['color']['text'] ?? '#000000' );
		$link_color = self::resolve_css_var(
			$styles['elements']['link']['color']['text']
				?? $styles['elements']['a']['color']['text']
				?? $text_color
		);

		// Extract typography.
		$font_family = self::resolve_css_var( $styles['typography']['fontFamily'] ?? 'inherit' );
		$font_size   = self::resolve_css_var( $styles['typography']['fontSize'] ?? '16px' );

		// Extract heading styles.
		$heading_color       = self::resolve_css_var( $styles['elements']['heading']['color']['text'] ?? $text_color );
		$heading_font_family = self::resolve_css_var( $styles['elements']['heading']['typography']['fontFamily'] ?? $font_family );

		// Extract button styles.
		$button_bg_color   = self::resolve_css_var( $styles['elements']['button']['color']['background'] ?? $bg_color );
		$button_text_color = self::resolve_css_var( $styles['elements']['button']['color']['text'] ?? $text_color );
		$button_font_size  = self::resolve_css_var( $styles['elements']['button']['typography']['fontSize'] ?? $font_size );

		// Extract input styles if available.
		$input_border_color  = self::resolve_css_var( $styles['elements']['input']['border']['color'] ?? $text_color );
		$input_border_radius = self::resolve_css_var( $styles['elements']['input']['border']['radius'] ?? '0px' );

		// Extract button font family.
		$button_font_family = self::resolve_css_var( $styles['elements']['button']['typography']['fontFamily'] ?? $font_family );

		// Extract header/footer colors. First try the actual header template
		// part block attributes, then fall back to template part default styles.
		$header_colors     = self::get_template_part_colors( 'header' );
		$footer_colors     = self::get_template_part_colors( 'footer' );
		$header_bg_color   = $header_colors['background'] ?? self::resolve_css_var( $tp_styles['color']['background'] ?? $bg_color );
		$header_text_color = $header_colors['text'] ?? self::resolve_css_var( $tp_styles['color']['text'] ?? $text_color );

		// Determine theme (light vs dark) from background color.
		$theme = self::is_color_light( $bg_color ) ? 'stripe' : 'night';

		// Error color for invalid inputs.
		$error_color = '#df1b41';

		return [
			'variables' => [
				'colorBackground' => $bg_color,
				'colorText'       => $text_color,
				'fontFamily'      => $font_family,
				'fontSizeBase'    => $font_size,
			],
			'theme'     => $theme,
			'labels'    => 'floating',
			'rules'     => [
				'.Input'          => [
					'color'             => $text_color,
					'fontFamily'        => $font_family,
					'fontSize'          => $font_size,
					'borderColor'       => $input_border_color,
					'borderBottomColor' => $input_border_color,
					'borderRadius'      => $input_border_radius,
					'backgroundColor'   => $bg_color,
				],
				'.Input--invalid' => [
					'borderBottomColor' => $error_color,
				],
				'.Label'          => [
					'color'      => $text_color,
					'fontFamily' => $font_family,
					'fontSize'   => $font_size,
				],
				'.Text'           => [
					'color'      => $text_color,
					'fontFamily' => $font_family,
					'fontSize'   => $font_size,
				],
				'.Heading'        => [
					'color'      => $heading_color,
					'fontFamily' => $heading_font_family,
				],
				'.Header'         => [
					'backgroundColor' => $header_bg_color,
					'color'           => $header_text_color,
				],
				'.Footer'         => [
					'backgroundColor' => $footer_colors['background'] ?? $bg_color,
					'color'           => $footer_colors['text'] ?? $text_color,
				],
				'.Footer-link'    => [
					'color' => self::resolve_css_var( $tp_styles['elements']['link']['color']['text'] ?? $link_color ),
				],
				'.Button'         => [
					'color'           => $button_text_color,
					'backgroundColor' => $button_bg_color,
					'fontFamily'      => $button_font_family,
					'fontSize'        => $button_font_size,
				],
				'.Link'           => [
					'color'      => $link_color,
					'fontFamily' => $font_family,
				],
				'.Tab'            => [
					'color'           => $text_color,
					'backgroundColor' => $bg_color,
					'fontFamily'      => $font_family,
				],
				'.Block'          => [
					'backgroundColor' => $bg_color,
				],
			],
		];
	}

	/**
	 * Stores the WooPay appearance if no valid appearance exists for the current version.
	 * Used by the shopper conditional write path.
	 *
	 * @param array $appearance The appearance object to store.
	 * @return bool True if stored, false if slot was already filled.
	 */
	public static function maybe_set_woopay_appearance( array $appearance ): bool {
		$existing = self::get_woopay_appearance();
		if ( null !== $existing ) {
			return false;
		}

		self::set_woopay_appearance( $appearance );
		return true;
	}

	/**
	 * Validates a WooPay appearance payload against the known schema.
	 *
	 * @param array $appearance The appearance array to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_appearance_schema( array $appearance ): bool {
		// Validate top-level keys.
		$allowed_top_keys = [ 'variables', 'theme', 'labels', 'rules' ];
		foreach ( array_keys( $appearance ) as $key ) {
			if ( ! in_array( $key, $allowed_top_keys, true ) ) {
				return false;
			}
		}

		// Validate theme.
		if ( isset( $appearance['theme'] ) && ! in_array( $appearance['theme'], [ 'stripe', 'night' ], true ) ) {
			return false;
		}

		// Validate labels.
		if ( isset( $appearance['labels'] ) && ! in_array( $appearance['labels'], [ 'floating', 'above' ], true ) ) {
			return false;
		}

		// Validate variables.
		if ( isset( $appearance['variables'] ) ) {
			if ( ! is_array( $appearance['variables'] ) ) {
				return false;
			}
			$allowed_vars = [ 'colorBackground', 'colorText', 'fontFamily', 'fontSizeBase' ];
			foreach ( array_keys( $appearance['variables'] ) as $key ) {
				if ( ! in_array( $key, $allowed_vars, true ) ) {
					return false;
				}
			}
			if ( ! self::validate_string_values( $appearance['variables'] ) ) {
				return false;
			}
		}

		// Validate rules.
		if ( isset( $appearance['rules'] ) ) {
			if ( ! is_array( $appearance['rules'] ) ) {
				return false;
			}
			$allowed_rules = [
				'.Input',
				'.Input--invalid',
				'.Label',
				'.Label--resting',
				'.Label--floating',
				'.Text',
				'.Text--redirect',
				'.Block',
				'.Tab',
				'.Tab:hover',
				'.Tab--selected',
				'.TabIcon',
				'.TabIcon:hover',
				'.TabIcon--selected',
				'.TabLabel',
				'.Heading',
				'.Header',
				'.Footer',
				'.Footer-link',
				'.Footer--link',
				'.Button',
				'.Link',
				'.Container',
			];
			foreach ( $appearance['rules'] as $rule_key => $rule_value ) {
				if ( ! in_array( $rule_key, $allowed_rules, true ) ) {
					return false;
				}
				if ( ! is_array( $rule_value ) ) {
					return false;
				}
				if ( ! self::validate_string_values( $rule_value ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Validates that all values in an array are strings under 200 characters.
	 *
	 * @param array $values The array to validate.
	 * @return bool True if all values are valid strings.
	 */
	private static function validate_string_values( array $values ): bool {
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 200 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Determines if a hex color is light (brightness > 125).
	 *
	 * @param string $color Hex color (e.g. '#ffffff' or 'ffffff').
	 * @return bool True if light, false if dark.
	 */
	private static function is_color_light( string $color ): bool {
		$color = ltrim( $color, '#' );
		if ( 3 === strlen( $color ) ) {
			$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
		}
		if ( 6 !== strlen( $color ) ) {
			return true; // Default to light for unparseable colors.
		}
		$r = hexdec( substr( $color, 0, 2 ) );
		$g = hexdec( substr( $color, 2, 2 ) );
		$b = hexdec( substr( $color, 4, 2 ) );

		// Same formula as tinycolor: (r * 299 + g * 587 + b * 114) / 1000.
		$brightness = ( $r * 299 + $g * 587 + $b * 114 ) / 1000;
		return $brightness > 125;
	}

	/**
	 * Attempts to resolve a CSS var() reference to a concrete value using
	 * the global styles presets. Returns the original string if unresolvable.
	 *
	 * @param string $value The CSS value, possibly a var() reference.
	 * @return string The resolved value or the original.
	 */
	private static function resolve_css_var( string $value ): string {
		if ( 0 !== strpos( $value, 'var(' ) ) {
			return $value;
		}

		// Extract the custom property name: var(--wp--preset--font-family--system-font).
		if ( ! preg_match( '/var\(\s*(--[^,)]+)/', $value, $matches ) ) {
			return $value;
		}

		$property = $matches[1];

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return $value;
		}

		// Map known preset patterns to their settings paths.
		$preset_map = [
			'--wp--preset--font-family--' => 'typography.fontFamilies',
			'--wp--preset--font-size--'   => 'typography.fontSizes',
			'--wp--preset--color--'       => 'color.palette',
		];

		foreach ( $preset_map as $prefix => $settings_path ) {
			if ( 0 === strpos( $property, $prefix ) ) {
				$slug     = substr( $property, strlen( $prefix ) );
				$path     = explode( '.', $settings_path );
				$settings = wp_get_global_settings( $path );

				if ( is_array( $settings ) ) {
					$resolved = self::find_preset_value( $settings, $slug );
					if ( null !== $resolved ) {
						return $resolved;
					}
				}
			}
		}

		return $value;
	}

	/**
	 * Searches a preset array for a matching slug and returns its value.
	 * Handles both flat arrays and origin-keyed arrays (default/theme/custom)
	 * returned by wp_get_global_settings().
	 *
	 * @param array  $settings The presets array.
	 * @param string $slug     The preset slug to find.
	 * @return string|null The resolved value, or null if not found.
	 */
	private static function find_preset_value( array $settings, string $slug ): ?string {
		foreach ( $settings as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			// Flat preset entry with a slug key.
			if ( isset( $entry['slug'] ) && $entry['slug'] === $slug ) {
				return $entry['fontFamily'] ?? $entry['size'] ?? $entry['color'] ?? null;
			}

			// Origin-keyed sub-array (default, theme, custom) — recurse.
			if ( ! isset( $entry['slug'] ) ) {
				$nested = self::find_preset_value( $entry, $slug );
				if ( null !== $nested ) {
					return $nested;
				}
			}
		}

		return null;
	}

	/**
	 * Extracts background and text colors from a template part (header/footer)
	 * by parsing its outermost block attributes.
	 *
	 * @param string $slug The template part slug (e.g. 'header', 'footer').
	 * @return array Associative array with 'background' and/or 'text' keys, or empty.
	 */
	private static function get_template_part_colors( string $slug ): array {
		if ( ! function_exists( 'get_block_template' ) ) {
			return [];
		}

		$template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
		if ( ! $template || empty( $template->content ) ) {
			return [];
		}

		$blocks = parse_blocks( $template->content );
		$target = self::find_primary_block( $blocks );

		if ( null === $target ) {
			return [];
		}

		return self::extract_block_colors( $target );
	}

	/**
	 * Finds the primary block in a template part (e.g. the main header, not a minibar).
	 *
	 * Looks for the top-level block containing navigation-related inner blocks
	 * (core/navigation, core/site-title, core/site-logo). Falls back to the last
	 * top-level core/group block, then the first non-empty block.
	 *
	 * @param array $blocks Parsed blocks from a template part.
	 * @return array|null The primary block, or null if none found.
	 */
	private static function find_primary_block( array $blocks ): ?array {
		$nav_markers = [ 'core/navigation', 'core/site-title', 'core/site-logo' ];
		$last_group  = null;
		$first_block = null;

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( null === $first_block ) {
				$first_block = $block;
			}

			if ( 'core/group' === $block['blockName'] ) {
				$last_group = $block;
			}

			if ( self::block_contains_any( $block, $nav_markers ) ) {
				return $block;
			}
		}

		return $last_group ?? $first_block;
	}

	/**
	 * Recursively checks if a block or any of its inner blocks matches one of the given block names.
	 *
	 * @param array    $block       A parsed block.
	 * @param string[] $block_names Block names to search for.
	 * @return bool True if any matching block is found.
	 */
	private static function block_contains_any( array $block, array $block_names ): bool {
		if ( in_array( $block['blockName'], $block_names, true ) ) {
			return true;
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner ) {
				if ( self::block_contains_any( $inner, $block_names ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Extracts background and text colors from a block's attributes.
	 *
	 * @param array $block A parsed block.
	 * @return array Colors array with optional 'background' and 'text' keys.
	 */
	private static function extract_block_colors( array $block ): array {
		$colors = [];

		// Check preset background (e.g. "backgroundColor": "background").
		if ( ! empty( $block['attrs']['backgroundColor'] ) ) {
			$resolved = self::resolve_css_var( 'var(--wp--preset--color--' . $block['attrs']['backgroundColor'] . ')' );
			if ( 'var(--wp--preset--color--' . $block['attrs']['backgroundColor'] . ')' !== $resolved ) {
				$colors['background'] = $resolved;
			}
		}

		// Check inline background (e.g. "style.color.background": "#fff").
		if ( ! empty( $block['attrs']['style']['color']['background'] ) ) {
			$colors['background'] = self::resolve_css_var( $block['attrs']['style']['color']['background'] );
		}

		// Check preset text color.
		if ( ! empty( $block['attrs']['textColor'] ) ) {
			$resolved = self::resolve_css_var( 'var(--wp--preset--color--' . $block['attrs']['textColor'] . ')' );
			if ( 'var(--wp--preset--color--' . $block['attrs']['textColor'] . ')' !== $resolved ) {
				$colors['text'] = $resolved;
			}
		}

		// Check inline text color.
		if ( ! empty( $block['attrs']['style']['color']['text'] ) ) {
			$colors['text'] = self::resolve_css_var( $block['attrs']['style']['color']['text'] );
		}

		return $colors;
	}

	/**
	 * Computes a fresh styles cache version hash from plugin version,
	 * theme stylesheet, and global styles (color palettes, style variations).
	 *
	 * @return string MD5 hash.
	 */
	private static function compute_styles_cache_version(): string {
		$parts = WCPAY_VERSION_NUMBER . wp_get_theme()->get_stylesheet();

		if ( function_exists( 'wp_get_global_styles' ) ) {
			$parts .= wp_json_encode( wp_get_global_styles() );
		}

		// Theme mods capture Customizer changes (classic themes).
		$parts .= wp_json_encode( get_theme_mods() );

		return md5( $parts );
	}
}

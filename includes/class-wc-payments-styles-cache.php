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
	 * Font CDN domains allowed for WooPay appearance font rules.
	 *
	 * Shared between server-side extraction (get_font_rules_from_registered_styles)
	 * and client-submitted validation (WooPay_Session::sanitize_font_rules).
	 *
	 * @var string[]
	 */
	const ALLOWED_FONT_DOMAINS = [
		'fonts.googleapis.com',
		'fonts.gstatic.com',
		'use.typekit.net',
		'fonts.bunny.net',
		'fonts.wp.com',
	];

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
		if ( wp_is_block_theme() ) {
			$appearance = self::compute_woopay_appearance_from_theme();
			if ( null !== $appearance ) {
				$font_rules = self::get_font_rules_from_registered_styles();
				self::set_woopay_appearance( $appearance, $font_rules );
				return $appearance;
			}
		}

		return null;
	}

	/**
	 * Returns the stored WooPay font rules, or an empty array if not set or version mismatch.
	 *
	 * @return array The font rules array.
	 */
	public static function get_woopay_font_rules(): array {
		$stored = get_option( 'wcpay_woopay_checkout_appearance' );
		if ( ! empty( $stored ) && is_array( $stored ) ) {
			if ( ( $stored['version'] ?? '' ) === self::get_styles_cache_version() ) {
				return $stored['font_rules'] ?? [];
			}
		}

		return [];
	}

	/**
	 * Stores the WooPay checkout appearance and font rules alongside the current cache version.
	 *
	 * The stored option (`wcpay_woopay_checkout_appearance`) has the structure:
	 *   [
	 *       'appearance' => [ 'variables' => [...], 'rules' => [...] ],
	 *       'font_rules' => [ [ 'cssSrc' => 'https://fonts.googleapis.com/...' ], ... ],
	 *       'version'    => '<cache_version_hash>',
	 *   ]
	 *
	 * @param array $appearance The appearance object to store.
	 * @param array $font_rules Font CDN stylesheet URLs, each as [ 'cssSrc' => string ].
	 */
	public static function set_woopay_appearance( array $appearance, array $font_rules = [] ): void {
		update_option(
			'wcpay_woopay_checkout_appearance',
			[
				'appearance' => $appearance,
				'font_rules' => $font_rules,
				'version'    => self::get_styles_cache_version(),
			],
			false
		);
	}

	/**
	 * Deletes the stored WooPay checkout appearance so it recomputes on next precomputation.
	 */
	public static function invalidate_woopay_appearance(): void {
		delete_option( 'wcpay_woopay_checkout_appearance' );
	}

	/**
	 * Called on theme/style change hooks. Invalidates the styles cache version
	 * and any stored WooPay appearance so it can be recomputed lazily when
	 * needed (e.g. via get_woopay_appearance()).
	 */
	public static function handle_theme_change(): void {
		self::invalidate_styles_cache_version();
		self::invalidate_woopay_appearance();
	}

	/**
	 * Computes WooPay appearance from theme.json global styles (block themes).
	 *
	 * @return array|null The appearance object, or null if data is insufficient.
	 */
	public static function compute_woopay_appearance_from_theme(): ?array {
		$styles = wp_get_global_styles( [], [ 'transforms' => [ 'resolve-variables' ] ] );

		// Template part styles (used by header/footer in block themes).
		$tp_styles = wp_get_global_styles(
			[],
			[
				'block_name' => 'core/template-part',
				'transforms' => [ 'resolve-variables' ],
			]
		);

		// Extract colors. CSS custom property references are already resolved
		// by the 'resolve-variables' transform. resolve_style_value() handles
		// any remaining ref objects from theme.json.
		$bg_color   = self::resolve_style_value( $styles['color']['background'] ?? '#ffffff', '#ffffff', $styles );
		$text_color = self::resolve_style_value( $styles['color']['text'] ?? '#000000', '#000000', $styles );
		$link_color = self::resolve_style_value(
			$styles['elements']['link']['color']['text']
				?? $styles['elements']['a']['color']['text']
				?? $text_color,
			$text_color,
			$styles
		);

		// Extract typography.
		$font_family = self::resolve_style_value( $styles['typography']['fontFamily'] ?? 'inherit', 'inherit', $styles );
		$font_size   = self::resolve_style_value( $styles['typography']['fontSize'] ?? '16px', '16px', $styles );

		// Extract heading styles.
		$heading_color       = self::resolve_style_value( $styles['elements']['heading']['color']['text'] ?? $text_color, $text_color, $styles );
		$heading_font_family = self::resolve_style_value( $styles['elements']['heading']['typography']['fontFamily'] ?? $font_family, $font_family, $styles );

		// Extract button styles.
		$button_bg_color   = self::resolve_style_value( $styles['elements']['button']['color']['background'] ?? $bg_color, $bg_color, $styles );
		$button_text_color = self::resolve_style_value( $styles['elements']['button']['color']['text'] ?? $text_color, $text_color, $styles );
		$button_font_size  = self::resolve_style_value( $styles['elements']['button']['typography']['fontSize'] ?? $font_size, $font_size, $styles );

		// Extract input styles. WordPress theme.json uses 'textInput' as the
		// element name (maps to textarea + text-like input types).
		$input_el            = $styles['elements']['textInput'] ?? $styles['elements']['input'] ?? [];
		$input_bg_color      = self::resolve_style_value( $input_el['color']['background'] ?? $bg_color, $bg_color, $styles );
		$input_text_color    = self::resolve_style_value( $input_el['color']['text'] ?? $text_color, $text_color, $styles );
		$input_border_color  = self::resolve_style_value( $input_el['border']['color'] ?? $text_color, $text_color, $styles );
		$input_border_radius = self::resolve_style_value( $input_el['border']['radius'] ?? '0px', '0px', $styles );

		// Extract button font family.
		$button_font_family = self::resolve_style_value( $styles['elements']['button']['typography']['fontFamily'] ?? $font_family, $font_family, $styles );

		// Extract header/footer colors from the checkout template. Handles
		// both core/template-part references and inline blocks with category
		// metadata (e.g. Assembler inlines footer as a styled core/group).
		$checkout_colors   = self::get_checkout_section_colors();
		$header_colors     = $checkout_colors['header'] ?? [];
		$footer_colors     = $checkout_colors['footer'] ?? [];
		$header_bg_color   = $header_colors['background'] ?? self::resolve_style_value( $tp_styles['color']['background'] ?? $bg_color, $bg_color, $tp_styles );
		$header_text_color = $header_colors['text'] ?? self::resolve_style_value( $tp_styles['color']['text'] ?? $text_color, $text_color, $tp_styles );

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
					'color'             => $input_text_color,
					'fontFamily'        => $font_family,
					'fontSize'          => $font_size,
					'borderColor'       => $input_border_color,
					'borderBottomColor' => $input_border_color,
					'borderRadius'      => $input_border_radius,
					'backgroundColor'   => $input_bg_color,
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
					'color' => $footer_colors['text'] ?? $link_color,
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
	 * Extracts font CDN stylesheet URLs from the WordPress registered styles queue.
	 *
	 * Scans wp_styles() for registered stylesheets from allowed font CDN domains.
	 * Used as a server-side fallback for block themes where DOM extraction isn't available.
	 *
	 * @return array Array of font rules, each with a 'cssSrc' key. Capped at 10 entries.
	 */
	public static function get_font_rules_from_registered_styles(): array {
		$wp_styles = wp_styles();

		$font_rules = [];
		foreach ( $wp_styles->registered as $style ) {
			if ( empty( $style->src ) || ! is_string( $style->src ) ) {
				continue;
			}
			$url  = esc_url_raw( $style->src, [ 'https' ] );
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host && in_array( $host, self::ALLOWED_FONT_DOMAINS, true ) ) {
				$font_rules[] = [ 'cssSrc' => $url ];
			}
		}
		return array_slice( $font_rules, 0, 10 );
	}

	/**
	 * Stores the WooPay appearance if no valid appearance exists for the current version.
	 * Used by the shopper conditional write path.
	 *
	 * @param array $appearance The appearance object to store.
	 * @param array $font_rules Font CDN stylesheet URLs.
	 * @return bool True if stored, false if slot was already filled.
	 */
	public static function maybe_set_woopay_appearance( array $appearance, array $font_rules = [] ): bool {
		$existing = self::get_woopay_appearance();
		if ( null !== $existing ) {
			return false;
		}

		self::set_woopay_appearance( $appearance, $font_rules );
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
			// Stripe Appearance API CSS properties used by DOM extraction and server-side compute.
			$allowed_properties = [
				'color',
				'backgroundColor',
				'fontFamily',
				'fontSize',
				'fontWeight',
				'fontVariation',
				'lineHeight',
				'letterSpacing',
				'padding',
				'paddingTop',
				'paddingRight',
				'paddingBottom',
				'paddingLeft',
				'border',
				'borderTop',
				'borderRight',
				'borderBottom',
				'borderLeft',
				'borderColor',
				'borderStyle',
				'borderWidth',
				'borderTopColor',
				'borderTopStyle',
				'borderTopWidth',
				'borderRightColor',
				'borderRightStyle',
				'borderRightWidth',
				'borderBottomColor',
				'borderBottomStyle',
				'borderBottomWidth',
				'borderLeftColor',
				'borderLeftStyle',
				'borderLeftWidth',
				'borderRadius',
				'borderTopLeftRadius',
				'borderTopRightRadius',
				'borderBottomRightRadius',
				'borderBottomLeftRadius',
				'outline',
				'outlineColor',
				'outlineWidth',
				'outlineStyle',
				'outlineOffset',
				'boxShadow',
				'textDecoration',
				'textShadow',
				'textTransform',
				'transition',
				'transform',
				'-webkit-font-smoothing',
				'-moz-osx-font-smoothing',
			];
			foreach ( $appearance['rules'] as $rule_key => $rule_value ) {
				if ( ! in_array( $rule_key, $allowed_rules, true ) ) {
					return false;
				}
				if ( ! is_array( $rule_value ) ) {
					return false;
				}
				foreach ( array_keys( $rule_value ) as $prop ) {
					if ( ! in_array( $prop, $allowed_properties, true ) ) {
						return false;
					}
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
	 * Ensures a theme style value is a string. Handles ref objects
	 * (e.g. {"ref": "styles.typography.fontFamily"}) by resolving them
	 * against the provided styles context array. Returns the default for
	 * any non-string value that cannot be resolved.
	 *
	 * @param mixed  $value          The style value — string, ref object array, or other.
	 * @param string $default        Fallback value when resolution fails.
	 * @param array  $styles_context The already-resolved styles array to look up refs in.
	 * @return string The resolved string value or the default.
	 */
	private static function resolve_style_value( $value, string $default, array $styles_context = [] ): string {
		// Handle ref objects: {"ref": "styles.typography.fontFamily"}.
		if ( is_array( $value ) && isset( $value['ref'] ) ) {
			$path = explode( '.', $value['ref'] );
			// Strip the leading 'styles' segment since $styles_context
			// is already scoped to the styles subtree.
			if ( ! empty( $path ) && 'styles' === $path[0] ) {
				array_shift( $path );
			}
			$value = _wp_array_get( $styles_context, $path );
		}

		if ( ! is_string( $value ) ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Attempts to resolve a CSS var() reference to a concrete value using
	 * the global styles presets. Returns the original string if unresolvable.
	 *
	 * @param string $value The CSS value, possibly a var() reference.
	 * @return string The resolved value or the original.
	 */
	private static function resolve_css_var( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		if ( 0 !== strpos( $value, 'var(' ) ) {
			return $value;
		}

		// Extract the custom property name: var(--wp--preset--font-family--system-font).
		if ( ! preg_match( '/var\(\s*(--[^,)]+)/', $value, $matches ) ) {
			return $value;
		}

		$property = $matches[1];

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
	 * Extracts header and footer colors from the checkout page template.
	 *
	 * Walks the checkout template blocks looking for header/footer sections.
	 * These can appear as:
	 * - `core/template-part` blocks with area "header"/"footer" (standard pattern)
	 * - Inline blocks with `metadata.categories` containing "header"/"footer"
	 *   (e.g. Assembler inlines the footer as a core/group with is-style-section-1)
	 * - Blocks with `tagName` "header"/"footer"
	 *
	 * @return array<string, array> Map of area ('header'|'footer') to color arrays
	 *                              with optional 'background' and 'text' keys.
	 */
	private static function get_checkout_section_colors(): array {
		// Theme override takes priority, then WooCommerce's registered template.
		$template = get_block_template( get_stylesheet() . '//page-checkout' )
			?? get_block_template( 'woocommerce//page-checkout' );

		if ( ! $template || empty( $template->content ) ) {
			return [];
		}

		$blocks   = parse_blocks( $template->content );
		$blocks   = self::resolve_pattern_blocks( $blocks );
		$blocks   = self::flatten_blocks( $blocks );
		$sections = [];

		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? '';
			if ( empty( $block_name ) ) {
				continue;
			}

			$area = self::classify_block_area( $block );
			if ( ! $area || isset( $sections[ $area ] ) ) {
				continue;
			}

			if ( 'core/template-part' === $block_name && ! empty( $block['attrs']['slug'] ) ) {
				$colors = self::get_template_part_colors( $block['attrs']['slug'], $block['attrs']['theme'] ?? '' );
			} else {
				$colors = self::extract_block_colors( $block );
			}

			if ( ! empty( $colors ) ) {
				$sections[ $area ] = $colors;
			}
		}

		return $sections;
	}

	/**
	 * Determines if a block serves as a header or footer section.
	 *
	 * Checks (in priority order):
	 * 1. Template part area attribute or registered entity area
	 * 2. Block metadata categories containing "header" or "footer"
	 * 3. Block tagName attribute ("header" or "footer")
	 *
	 * @param array $block A parsed block.
	 * @return string|null 'header', 'footer', or null if not a section block.
	 */
	private static function classify_block_area( array $block ): ?string {
		$block_name = $block['blockName'] ?? '';

		// Template parts: check area from attrs or registered entity.
		if ( 'core/template-part' === $block_name && ! empty( $block['attrs']['slug'] ) ) {
			$area = $block['attrs']['area'] ?? null;
			if ( ! $area ) {
				$part = get_block_template( get_stylesheet() . '//' . $block['attrs']['slug'], 'wp_template_part' );
				if ( ! $part && ! empty( $block['attrs']['theme'] ) ) {
					$part = get_block_template( $block['attrs']['theme'] . '//' . $block['attrs']['slug'], 'wp_template_part' );
				}
				$area = $part ? $part->area : null;
			}
			if ( in_array( $area, [ 'header', 'footer' ], true ) ) {
				return $area;
			}
			return null;
		}

		// Inline blocks: check metadata categories.
		$categories = $block['attrs']['metadata']['categories'] ?? [];
		if ( in_array( 'footer', $categories, true ) ) {
			return 'footer';
		}
		if ( in_array( 'header', $categories, true ) ) {
			return 'header';
		}

		// Fallback: check tagName.
		$tag = $block['attrs']['tagName'] ?? '';
		if ( 'footer' === $tag ) {
			return 'footer';
		}
		if ( 'header' === $tag ) {
			return 'header';
		}

		return null;
	}

	/**
	 * Extracts background and text colors from a template part (header/footer)
	 * by parsing its outermost block attributes.
	 *
	 * @param string $slug  The template part slug (e.g. 'header', 'checkout-header').
	 * @param string $theme The theme attribute from the template-part block (e.g. 'woocommerce/woocommerce').
	 * @return array Associative array with 'background' and/or 'text' keys, or empty.
	 */
	private static function get_template_part_colors( string $slug, string $theme = '' ): array {
		$template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );

		if ( ( ! $template || empty( $template->content ) ) && '' !== $theme ) {
			$template = get_block_template( $theme . '//' . $slug, 'wp_template_part' );
		}

		if ( ! $template || empty( $template->content ) ) {
			return [];
		}

		$blocks = parse_blocks( $template->content );

		// Resolve core/pattern references — template parts commonly contain
		// a single pattern reference instead of inline blocks.
		$blocks = self::resolve_pattern_blocks( $blocks );

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
	 * Resolves core/pattern block references to their actual block content.
	 *
	 * Template parts commonly contain a single `<!-- wp:pattern {"slug":"theme/footer"} /-->`
	 * instead of inline blocks. `parse_blocks()` returns the raw pattern reference with no
	 * inner blocks, so we resolve it via the pattern registry.
	 *
	 * @param array $blocks Parsed blocks that may contain core/pattern references.
	 * @return array Blocks with pattern references replaced by their content.
	 */
	private static function resolve_pattern_blocks( array $blocks ): array {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$resolved = [];

		foreach ( $blocks as $block ) {
			if ( 'core/pattern' !== $block['blockName'] || empty( $block['attrs']['slug'] ) ) {
				if ( ! empty( $block['innerBlocks'] ) ) {
					$block['innerBlocks'] = self::resolve_pattern_blocks( $block['innerBlocks'] );
				}
				$resolved[] = $block;
				continue;
			}

			$slug = $block['attrs']['slug'];
			if ( ! $registry->is_registered( $slug ) ) {
				$resolved[] = $block;
				continue;
			}

			$pattern = $registry->get_registered( $slug );
			if ( ! empty( $pattern['content'] ) ) {
				$pattern_blocks = parse_blocks( $pattern['content'] );
				foreach ( $pattern_blocks as $pattern_block ) {
					$resolved[] = $pattern_block;
				}
			}
		}

		return $resolved;
	}

	/**
	 * Recursively flattens a block tree into a single-level array.
	 *
	 * Parent blocks appear before their children, so the first classified
	 * block for a given area is always the outermost one.
	 *
	 * @param array $blocks Parsed blocks (possibly nested via innerBlocks).
	 * @return array Flat array of all blocks at every depth.
	 */
	private static function flatten_blocks( array $blocks ): array {
		$flat = [];
		foreach ( $blocks as $block ) {
			$flat[] = $block;
			if ( ! empty( $block['innerBlocks'] ) ) {
				$flat = array_merge( $flat, self::flatten_blocks( $block['innerBlocks'] ) );
			}
		}
		return $flat;
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

		// Fill in missing colors from block style variations (e.g.
		// "is-style-section-1"). Inline attributes take precedence per-key,
		// but the variation provides defaults for keys not set inline.
		// Example: user overrides text color in Site Editor but background
		// still comes from the variation.
		if ( ! empty( $block['attrs']['className'] ) && ! empty( $block['blockName'] ) ) {
			$variation_colors = self::get_style_variation_colors( $block['blockName'], $block['attrs']['className'] );
			$colors           = array_merge( $variation_colors, $colors );
		}

		return $colors;
	}

	/**
	 * Extracts colors from a block style variation by looking up the variation
	 * in the merged theme.json data.
	 *
	 * Modern block themes use CSS class-based color schemes (e.g. "is-style-section-1")
	 * instead of inline color attributes. The variation definitions are stored in
	 * theme.json partial files (e.g. styles/block/section-1.json).
	 *
	 * @param string $block_name Block name (e.g. 'core/group').
	 * @param string $class_name The block's className attribute.
	 * @return array Colors array with optional 'background' and 'text' keys.
	 */
	private static function get_style_variation_colors( string $block_name, string $class_name ): array {
		if ( ! function_exists( 'wp_get_block_style_variation_name_from_class' ) ) {
			return [];
		}

		$variation_names = wp_get_block_style_variation_name_from_class( $class_name );
		if ( empty( $variation_names ) ) {
			return [];
		}

		// Only the first variation with data is used (same as WP core).
		foreach ( $variation_names as $variation ) {
			$variation_color = wp_get_global_styles(
				[ 'variations', $variation, 'color' ],
				[ 'block_name' => $block_name ]
			);

			if ( ! is_array( $variation_color ) ) {
				continue;
			}

			$colors = [];
			if ( ! empty( $variation_color['background'] ) ) {
				$colors['background'] = self::resolve_css_var( $variation_color['background'] );
			}
			if ( ! empty( $variation_color['text'] ) ) {
				$colors['text'] = self::resolve_css_var( $variation_color['text'] );
			}

			if ( ! empty( $colors ) ) {
				return $colors;
			}
		}

		return [];
	}

	/**
	 * Computes a fresh styles cache version hash from plugin version,
	 * theme stylesheet, and global styles (color palettes, style variations).
	 *
	 * @return string MD5 hash.
	 */
	private static function compute_styles_cache_version(): string {
		$parts = WCPAY_VERSION_NUMBER . wp_get_theme()->get_stylesheet();

		$parts .= wp_json_encode( wp_get_global_styles() );

		// Theme mods capture Customizer changes (classic themes).
		$parts .= wp_json_encode( get_theme_mods() );

		// just making sure that it gets updated each time this method is called.
		$parts .= time();

		return md5( $parts );
	}
}

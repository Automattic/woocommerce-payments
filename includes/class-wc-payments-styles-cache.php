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

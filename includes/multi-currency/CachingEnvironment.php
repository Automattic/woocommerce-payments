<?php
/**
 * Class CachingEnvironment
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Detects whether the site runs a high-confidence full-page caching environment.
 *
 * Used to decide whether Multi-Currency's cache-optimized rendering mode should be
 * auto-enabled (on never-configured sites) or recommended to the merchant. Only strong
 * full-page-cache signals count; weaker hints such as a persistent object cache alone are
 * intentionally ignored to avoid false positives, since cache-optimized rendering is only
 * a net win when the site actually serves cached full-page HTML.
 */
class CachingEnvironment {

	/**
	 * Known page-cache plugin signals, keyed by a stable provider slug.
	 *
	 * Each entry lists the constants and/or classes that, when present, indicate the plugin
	 * is active and providing full-page caching.
	 *
	 * @var array
	 */
	const CACHE_PLUGIN_SIGNALS = [
		'wp_rocket'        => [ 'constants' => [ 'WP_ROCKET_VERSION' ] ],
		'w3_total_cache'   => [ 'constants' => [ 'W3TC' ] ],
		'wp_super_cache'   => [ 'constants' => [ 'WPCACHEHOME' ] ],
		'litespeed_cache'  => [ 'constants' => [ 'LSCWP_V' ] ],
		'wp_fastest_cache' => [
			'constants' => [ 'WPFC_MAIN_PATH' ],
			'classes'   => [ 'WpFastestCache' ],
		],
		'cache_enabler'    => [ 'classes' => [ 'Cache_Enabler' ] ],
		'comet_cache'      => [ 'classes' => [ 'comet_cache' ] ],
		'hummingbird'      => [ 'classes' => [ 'Hummingbird\WP_Hummingbird' ] ],
	];

	/**
	 * Managed-host signals, keyed by a stable provider slug.
	 *
	 * Intentionally limited to hosts whose platform full-page caching has been verified in practice
	 * and whose detection constants are reliable. Other managed or edge hosts cache at the server or
	 * edge level and expose no dependable PHP signal; they are expected to declare themselves through
	 * the wcpay_multi_currency_page_caching_active filter instead of being guessed at here.
	 *
	 * @var array
	 */
	const MANAGED_HOST_SIGNALS = [
		'wpcom_atomic' => [ 'IS_ATOMIC', 'ATOMIC_SITE_ID' ],
		'pressable'    => [ 'IS_PRESSABLE' ],
	];

	/**
	 * Whether a high-confidence full-page caching environment is active.
	 *
	 * The result can be overridden via the `wcpay_multi_currency_page_caching_active` filter,
	 * letting hosts declare their caching capability explicitly.
	 *
	 * @return bool
	 */
	public function is_page_caching_active(): bool {
		$is_active = null !== $this->get_detected_provider();

		/**
		 * Filters whether the site is considered to run full-page caching.
		 *
		 * @since 10.9.0
		 *
		 * @param bool $is_active Whether full-page caching was detected.
		 */
		return (bool) apply_filters( 'wcpay_multi_currency_page_caching_active', $is_active );
	}

	/**
	 * Returns a slug identifying the detected caching provider, or null when none is detected.
	 *
	 * @return string|null
	 */
	public function get_detected_provider(): ?string {
		if ( $this->has_page_cache_dropin() ) {
			return 'advanced_cache_dropin';
		}

		$plugin = $this->get_active_cache_plugin();
		if ( null !== $plugin ) {
			return $plugin;
		}

		return $this->get_managed_host();
	}

	/**
	 * Whether a page-cache drop-in (advanced-cache.php) is installed and enabled.
	 *
	 * This is the broadest high-confidence signal: most page-cache plugins (WP Super Cache,
	 * W3 Total Cache, WP Rocket, Cache Enabler, Comet Cache, Batcache, etc.) set WP_CACHE and
	 * install this drop-in.
	 *
	 * @return bool
	 */
	protected function has_page_cache_dropin(): bool {
		return defined( 'WP_CACHE' ) && WP_CACHE && file_exists( WP_CONTENT_DIR . '/advanced-cache.php' );
	}

	/**
	 * Returns the slug of an active known page-cache plugin, or null when none is active.
	 *
	 * @return string|null
	 */
	protected function get_active_cache_plugin(): ?string {
		foreach ( self::CACHE_PLUGIN_SIGNALS as $slug => $signals ) {
			foreach ( $signals['constants'] ?? [] as $constant ) {
				if ( defined( $constant ) ) {
					return $slug;
				}
			}

			foreach ( $signals['classes'] ?? [] as $class ) {
				if ( class_exists( $class ) ) {
					return $slug;
				}
			}
		}

		return null;
	}

	/**
	 * Returns the slug of a managed/edge host that provides full-page caching, or null.
	 *
	 * @return string|null
	 */
	protected function get_managed_host(): ?string {
		foreach ( self::MANAGED_HOST_SIGNALS as $slug => $constants ) {
			foreach ( $constants as $constant ) {
				if ( defined( $constant ) ) {
					return $slug;
				}
			}
		}

		return null;
	}
}

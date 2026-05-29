<?php
/**
 * Class WSN_Hub
 *
 * Bootstrap for the Woo Shopping Network Hub.
 *
 * Wires up:
 *
 * - The admin menu entry under WooCommerce (alongside Orders / Products / Customers,
 *   NOT under the WooPayments sub-menu).
 * - The render callback that outputs the React mount container.
 * - The asset enqueue for the WSN React bundle, scoped to this admin page only.
 * - The REST surface the React app consumes.
 *
 * Uses `add_submenu_page()` rather than `wc_admin_register_page()` (matches the
 * AI Storefront plugin's pattern). The reason: WC Admin's layout chrome injects its
 * own page title + Activity panel above the page content, which collides visually
 * with our branded `<PageHeader>`. A vanilla submenu page gives us a blank slate
 * where the branded header IS the chrome — there is no second title to fight with.
 *
 * This class is only instantiated when WC_Payments_Features::is_wsn_hub_enabled()
 * returns true — see class-wc-payments.php::init().
 *
 * @package WooCommerce\Payments\WSN
 */

defined( 'ABSPATH' ) || exit;

/**
 * WSN_Hub — bootstrap for Shopping Network Hub feature.
 */
class WSN_Hub {

	/**
	 * The submenu page slug. Public so tests / external callers can build the URL
	 * without hardcoding the string.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'wcpay-shopping-network';

	/**
	 * The script handle for the WSN admin bundle.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'wcpay-wsn-hub';

	/**
	 * Wires up WordPress hooks. Called from WC_Payments::init() when the feature flag is on.
	 */
	public function init_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_controllers' ] );

		// `in_admin_header` fires immediately before `admin_notices` + `all_admin_notices`,
		// which is the last safe moment to strip third-party callbacks before they render.
		add_action( 'in_admin_header', [ $this, 'suppress_third_party_admin_notices' ], 0 );
	}

	/**
	 * Registers the "Shopping Network" entry under the WooCommerce admin menu.
	 *
	 * Top-level WooCommerce sub-item, alongside Home / Orders / Customers / Reports /
	 * Settings — NOT under the WooPayments sub-menu. Uses `add_submenu_page()` rather
	 * than `wc_admin_register_page()` so we get a vanilla WP admin page with no WC
	 * Admin layout chrome (see class docblock for rationale).
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			// Browser tab / page title — branded prefix for support-ticket clarity.
			__( 'Woo Shopping Network', 'woocommerce-payments' ),
			// Sidebar menu title — shorter form, since "WooCommerce" already provides
			// the surrounding context in the admin nav.
			__( 'Shopping Network', 'woocommerce-payments' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			[ $this, 'render_admin_page' ],
			// Position 15: between Home (0) and Customers (typically 20+).
			15
		);
	}

	/**
	 * Renders the admin page container.
	 *
	 * Outputs only the React mount point plus a screen-reader-only <h1>. WP core's
	 * admin-notice JS uses a stable <h1> inside .wrap to position notices on initial
	 * page load before React mounts — keeping it as `screen-reader-text` prevents
	 * duplication with the branded <PageHeader> the React app renders.
	 */
	public function render_admin_page(): void {
		echo '<div class="wrap">';
		echo '<h1 class="screen-reader-text">' . esc_html__( 'Woo Shopping Network', 'woocommerce-payments' ) . '</h1>';
		echo '<div id="wcpay-wsn-hub-container"></div>';
		echo '</div>';
	}

	/**
	 * Enqueues the React bundle on the WSN admin page only.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// add_submenu_page() under 'woocommerce' generates this hook suffix.
		if ( 'woocommerce_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = WCPAY_ABSPATH . 'dist/wsn-hub.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [
				'dependencies' => [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ],
				'version'      => WCPAY_VERSION_NUMBER,
			];

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'dist/wsn-hub.js', WCPAY_PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Webpack emits the SCSS as wsn-hub.css alongside the JS.
		$css_path = WCPAY_ABSPATH . 'dist/wsn-hub.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				self::SCRIPT_HANDLE,
				plugins_url( 'dist/wsn-hub.css', WCPAY_PLUGIN_FILE ),
				[ 'wp-components' ],
				filemtime( $css_path )
			);
		}

		// Paint the WP admin content shell white so the branded white surface
		// extends edge-to-edge against the sidebar and to the viewport bottom.
		// `body.woocommerce_page_<menu-slug>` is the body class auto-generated
		// by add_submenu_page(); scoping there means other admin pages keep
		// the default gray. Mirrors the AI Storefront plugin's pattern at
		// woocommerce-ai-storefront/includes/class-wc-ai-storefront.php:473.
		wp_add_inline_style(
			'wp-components',
			'body.woocommerce_page_' . self::MENU_SLUG . ',
			 body.woocommerce_page_' . self::MENU_SLUG . ' #wpcontent,
			 body.woocommerce_page_' . self::MENU_SLUG . ' #wpbody-content { background: #fff; }'
		);

		// WSN_Settings is loaded once in class-wc-payments.php::init() so all WSN
		// callbacks (admin_enqueue_scripts here, rest_api_init in
		// register_rest_controllers(), in_admin_header in
		// suppress_third_party_admin_notices()) can safely call its statics
		// regardless of which hook fires first for a given request type.

		// Expose the feature flag value + the enable-state under the existing
		// wcpaySettings global. The React app gates pre-enable vs. post-enable
		// rendering on `wcpaySettings.wsn.enabled` so it can paint the right
		// view immediately on mount without waiting for an API round-trip.
		$bootstrap = [
			'featureFlags' => [ 'wsnHub' => true ],
			'wsn'          => [ 'enabled' => WSN_Settings::is_enabled() ],
		];
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.wcpaySettings = Object.assign( window.wcpaySettings || {}, ' . wp_json_encode( $bootstrap ) . ' );',
			'before'
		);
	}

	/**
	 * Suppresses third-party admin notices on the WSN admin page only.
	 *
	 * Walks $wp_filter['admin_notices'] + $wp_filter['all_admin_notices'] and removes
	 * any callback whose source file lives outside WordPress core, WooCommerce core, or
	 * WooPayments itself. The merchant still sees WP / WC / WCPay-originated notices —
	 * they're typically actionable (security warnings, plugin updates) and shouldn't be
	 * hidden. Third-party plugin marketing notices (TrackShip "Connect Store", etc.) get
	 * removed so the branded Hub UI isn't pushed below the fold by unrelated chrome.
	 *
	 * Other admin pages are unaffected — this only runs when the current screen is the
	 * WSN Hub admin page.
	 */
	public function suppress_third_party_admin_notices(): void {
		global $wp_filter, $hook_suffix;

		if ( 'woocommerce_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		// Hoist the path-root allowlist out of the per-callback loop. A typical WP
		// admin page has 20–30 admin_notices callbacks; without this hoist we'd
		// rebuild the same normalized-path array (and run wp_normalize_path() 4–5
		// times) on every iteration.
		$path_roots = $this->first_party_path_roots();

		foreach ( [ 'admin_notices', 'all_admin_notices', 'user_admin_notices', 'network_admin_notices' ] as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $id => $callback_data ) {
					if ( ! $this->is_first_party_notice_callback( $callback_data['function'] ?? null, $path_roots ) ) {
						unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] );
					}
				}
			}
		}
	}

	/**
	 * Determines whether a notice callback originates from WP core, WC core, or WCPay.
	 *
	 * Uses Reflection to resolve the callback's defining file, then checks that path
	 * against an allowlist of known-first-party plugin/core directories. Any Reflection
	 * failure errs on the side of KEEPING the callback (better to show a noisy notice
	 * than hide one that might be important).
	 *
	 * @param mixed         $callback   The hook callback (string function, [class, method], or Closure).
	 * @param string[]|null $path_roots Pre-computed allowlist of normalized path-root prefixes.
	 *                                  Pass from the caller to avoid rebuilding per callback.
	 *                                  Defaults to first_party_path_roots() for backwards-compatible direct calls.
	 * @return bool True if the callback should be kept, false to remove it.
	 */
	private function is_first_party_notice_callback( $callback, ?array $path_roots = null ): bool {
		if ( null === $callback ) {
			return true;
		}

		if ( null === $path_roots ) {
			$path_roots = $this->first_party_path_roots();
		}

		// Per-request memoization keyed on the callback's stable identity. Reflection
		// is ~0.1-0.2ms per instantiation on a warm OPcache; a typical admin install
		// has 20-40 admin_notices callbacks, so even though this runs only on the WSN
		// Hub admin page (not other admin pages), caching means subsequent same-page
		// renders within a PHP-FPM worker get the classification for free.
		static $classification_cache = [];

		$cache_key = $this->callback_cache_key( $callback );
		if ( null !== $cache_key && array_key_exists( $cache_key, $classification_cache ) ) {
			return $classification_cache[ $cache_key ];
		}

		try {
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( $callback instanceof Closure || ( is_string( $callback ) && function_exists( $callback ) ) ) {
				$reflection = new ReflectionFunction( $callback );
			} else {
				// Unknown shape — keep it.
				return $this->cache_classification( $classification_cache, $cache_key, true );
			}

			$file = $reflection->getFileName();
			if ( ! $file ) {
				// Internal PHP function or otherwise un-resolvable — keep it.
				return $this->cache_classification( $classification_cache, $cache_key, true );
			}

			$normalized = wp_normalize_path( $file );

			foreach ( $path_roots as $root ) {
				if ( '' !== $root && 0 === strpos( $normalized, $root ) ) {
					return $this->cache_classification( $classification_cache, $cache_key, true );
				}
			}

			return $this->cache_classification( $classification_cache, $cache_key, false );
		} catch ( \Throwable $e ) {
			// Reflection failed (e.g., closure rebound to a missing class). Keep the callback.
			// Surface the failure in WP_DEBUG so a developer chasing missing notices
			// has a breadcrumb; in production we silently keep the callback.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WSN_Hub: notice callback reflection failed — keeping callback. ' . $e->getMessage() );
			}
			return $this->cache_classification( $classification_cache, $cache_key, true );
		}
	}

	/**
	 * Produce a stable cache key for a hook callback. Returns null when the
	 * callback shape can't be uniquely identified (in which case the caller
	 * skips caching and re-classifies on every call — correct fallback).
	 *
	 * @param mixed $callback The hook callback.
	 * @return string|null
	 */
	private function callback_cache_key( $callback ): ?string {
		if ( is_string( $callback ) ) {
			return 'fn:' . $callback;
		}
		if ( $callback instanceof Closure ) {
			return 'closure:' . spl_object_hash( $callback );
		}
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$class_or_instance = $callback[0];
			$method            = (string) $callback[1];
			if ( is_object( $class_or_instance ) ) {
				return 'method:' . spl_object_hash( $class_or_instance ) . '::' . $method;
			}
			if ( is_string( $class_or_instance ) ) {
				return 'static:' . $class_or_instance . '::' . $method;
			}
		}
		return null;
	}

	/**
	 * Helper that writes into the static classification cache and returns the
	 * passed-in classification. Skips writing when $cache_key is null.
	 *
	 * @param array       $cache         Reference to the per-request cache.
	 * @param string|null $cache_key     Stable key for this callback, or null.
	 * @param bool        $classification The classification to store + return.
	 * @return bool
	 */
	private function cache_classification( array &$cache, ?string $cache_key, bool $classification ): bool {
		if ( null !== $cache_key ) {
			$cache[ $cache_key ] = $classification;
		}
		return $classification;
	}

	/**
	 * Allowlist of filesystem path roots that count as "first-party" sources.
	 *
	 * Anything matching one of these prefixes is allowed to render notices on the WSN
	 * Hub admin page. The list is intentionally conservative — WP core, WC core, and
	 * WooPayments. Other Automattic plugins (Jetpack, WooCommerce.com helpers) are
	 * excluded; if their notices ever need to surface here we can extend this.
	 *
	 * @return string[]
	 */
	private function first_party_path_roots(): array {
		// String literal 'wp-includes' (rather than the WPINC constant) keeps PHPStan
		// happy — WPINC is a WordPress runtime constant, not visible to static analysis.
		// The constant is hard-coded to 'wp-includes' in WP core and has been since 2.6.
		$roots = [
			wp_normalize_path( ABSPATH . 'wp-admin' ),
			wp_normalize_path( ABSPATH . 'wp-includes' ),
			wp_normalize_path( WP_PLUGIN_DIR . '/woocommerce/' ),
			wp_normalize_path( dirname( WCPAY_PLUGIN_FILE ) ),
		];

		// MU plugins also count as "core-equivalent" — site operators trust them.
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$roots[] = wp_normalize_path( WPMU_PLUGIN_DIR );
		}

		return array_filter( $roots );
	}

	/**
	 * Registers all REST controllers under the WSN namespace.
	 *
	 * Controllers extend `WC_Payments_REST_Controller`, which requires the API client
	 * to be injected at construction. The WSN controllers don't actually call the API
	 * (they're pure local wp_options + wc_get_orders queries), but extending the
	 * shared base class gets us inherited `check_permission()` + `$namespace`
	 * defaults plus any future cross-cutting behavior the base accrues — at the cost
	 * of carrying an unused dependency.
	 */
	public function register_rest_controllers(): void {
		require_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-wsn-settings-controller.php';
		require_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-wsn-orders-controller.php';

		$api_client = WC_Payments::get_payments_api_client();

		$settings_controller = new WC_REST_Payments_WSN_Settings_Controller( $api_client );
		$settings_controller->register_routes();

		$orders_controller = new WC_REST_Payments_WSN_Orders_Controller( $api_client );
		$orders_controller->register_routes();
	}
}

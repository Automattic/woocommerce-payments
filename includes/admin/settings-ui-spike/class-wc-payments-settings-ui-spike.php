<?php
/**
 * Class WC_Payments_Settings_UI_Spike
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * WOOPMNT-6211 spike: renders the WooPayments gateway settings section through
 * the WooCommerce Settings UI SDK (WC 10.9+, behind the `settings-ui` feature flag).
 *
 * The SDK's documented opt-in boundary is `WC_Settings_Page::get_settings_ui_page()`,
 * but the WooPayments settings live in a *gateway section* of Core's
 * `WC_Settings_Payment_Gateways` page, which WooPayments does not control. This
 * spike bridges that gap without modifying Core:
 *
 * 1. A minimal stub `WC_Settings_Page` (id `checkout`) is prepended to the
 *    settings pages array so Core's tab-level adapter lookups
 *    (`Internal\Admin\Settings::add_settings_ui_schema()` for the schema payload and
 *    `WCAdminAssets::get_settings_ui_script_dependencies()` for script ordering)
 *    resolve the WooPayments adapter. The stub registers no hooks and no tab.
 * 2. The gateway's `admin_options()` prints the SDK mount div
 *    (`data-wc-settings-ui="1"`) instead of the legacy React container, and
 *    Core's `settings-embed` script mounts the SDK page into it.
 *
 * With the feature flag off, or on WC < 10.9, everything no-ops and the
 * existing WooPayments settings app renders unchanged.
 */
class WC_Payments_Settings_UI_Spike {

	/**
	 * Page id used for scoping the settings UI schema and JS registrations.
	 *
	 * @var string
	 */
	const PAGE_ID = 'woocommerce_payments';

	/**
	 * Script handle for the spike JS bundle.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'wcpay-settings-ui-spike';

	/**
	 * Name of the JS-registered custom save handler.
	 *
	 * @var string
	 */
	const SAVE_HANDLER = 'wcpay-rest-settings';

	/**
	 * Initialize the spike integration. No-ops when the SDK is unavailable.
	 *
	 * @return void
	 */
	public static function init() {
		// WC < 10.9: the SDK contract does not exist — keep the classic settings.
		if ( ! interface_exists( \Automattic\WooCommerce\Admin\Settings\SettingsUIPageInterface::class ) ) {
			return;
		}

		add_filter( 'woocommerce_get_settings_pages', [ __CLASS__, 'register_settings_ui_provider' ] );
		add_filter( 'admin_body_class', [ __CLASS__, 'add_settings_ui_body_class' ], 40 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'register_scripts' ], 5 );
		// The i1 design is a full page without admin notices. `in_admin_header`
		// is the last action before `admin_notices` fires; Core uses the same
		// approach for reactified payment sections.
		add_action( 'in_admin_header', [ __CLASS__, 'suppress_admin_notices' ], PHP_INT_MAX );
	}

	/**
	 * Whether the current request should render the WooPayments section via the SDK.
	 *
	 * True only on the main WooPayments gateway section screen (not express
	 * checkout method subpages) with the `settings-ui` feature flag enabled.
	 *
	 * @return bool
	 */
	public static function is_sdk_rendering(): bool {
		if ( ! interface_exists( \Automattic\WooCommerce\Admin\Settings\SettingsUIPageInterface::class ) ) {
			return false;
		}

		if (
			! class_exists( \Automattic\WooCommerce\Admin\Features\Features::class )
			|| ! \Automattic\WooCommerce\Admin\Features\Features::is_enabled( 'settings-ui' )
		) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab     = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		$method  = isset( $_GET['method'] ) ? sanitize_text_field( wp_unslash( $_GET['method'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'wc-settings' === $page
			&& 'checkout' === $tab
			&& self::PAGE_ID === $section
			&& '' === $method;
	}

	/**
	 * Prepend the stub settings page that exposes the WooPayments settings UI adapter.
	 *
	 * Prepending matters: Core's adapter lookups return the first page whose id
	 * matches the current tab, and Core's own `WC_Settings_Payment_Gateways`
	 * (same id) provides no adapter.
	 *
	 * @param array $pages Settings pages.
	 * @return array
	 */
	public static function register_settings_ui_provider( $pages ) {
		if ( ! is_array( $pages ) || ! class_exists( 'WC_Settings_Page' ) ) {
			return $pages;
		}

		// Limit the stub to the one screen that needs it, so it can never leak
		// into other contexts that iterate settings pages (e.g. the wc/v3
		// settings REST groups).
		if ( ! self::is_sdk_rendering() ) {
			return $pages;
		}

		require_once __DIR__ . '/class-wc-payments-settings-ui-stub-page.php';

		array_unshift( $pages, new WC_Payments_Settings_UI_Stub_Page() );

		return $pages;
	}

	/**
	 * Add the SDK body class so the Settings UI canvas styles apply.
	 *
	 * Core adds this from `WC_Settings_Page` for opted-in pages; our stub page
	 * registers no hooks, so we add it here for the WooPayments section.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function add_settings_ui_body_class( $classes ) {
		if ( ! is_string( $classes ) || ! self::is_sdk_rendering() ) {
			return $classes;
		}

		if ( ! str_contains( $classes, 'woocommerce-settings-ui-page' ) ) {
			$classes .= ' woocommerce-settings-ui-page';
		}

		// Scopes the spike CSS that hides the legacy settings chrome
		// (Settings header bar, WC settings tab rows) per the i1 design.
		if ( ! str_contains( $classes, 'wcpay-settings-ui-spike-page' ) ) {
			$classes .= ' wcpay-settings-ui-spike-page';
		}

		return $classes;
	}

	/**
	 * Suppress admin notices on the SDK-rendered WooPayments settings screen.
	 *
	 * The i1 design renders a clean full page. Mirrors (simplified) what Core's
	 * `WC_Settings_Payment_Gateways::suppress_admin_notices()` does for
	 * reactified payment sections, which never include the gateway section.
	 *
	 * @return void
	 */
	public static function suppress_admin_notices() {
		if ( ! self::is_sdk_rendering() ) {
			return;
		}

		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'admin_notices' );
	}

	/**
	 * Register the spike JS bundle early so Core's `settings-embed` dependency
	 * resolution (which runs on `admin_enqueue_scripts`) can pick the handle up.
	 *
	 * @return void
	 */
	public static function register_scripts() {
		if ( ! self::is_sdk_rendering() ) {
			return;
		}

		WC_Payments::register_script_with_dependencies(
			self::SCRIPT_HANDLE,
			'dist/settings-ui-spike',
			[ 'wc-settings-ui-sdk' ]
		);

		wp_register_style(
			self::SCRIPT_HANDLE,
			plugins_url( 'dist/settings-ui-spike.css', WCPAY_PLUGIN_FILE ),
			[ 'wp-components' ],
			WC_Payments::get_file_version( 'dist/settings-ui-spike.css' )
		);
		wp_enqueue_style( self::SCRIPT_HANDLE );
	}

	/**
	 * Render the SDK mount div in place of the legacy settings container.
	 *
	 * Called from `WC_Payment_Gateway_WCPay::admin_options()`. Returns false when
	 * the SDK path is inactive so the caller falls through to the legacy screen.
	 *
	 * @return bool Whether the SDK mount was rendered.
	 */
	public static function maybe_render_sdk_mount(): bool {
		if ( ! self::is_sdk_rendering() ) {
			return false;
		}

		// The SDK page renders its own save button.
		$GLOBALS['hide_save_button'] = true;

		// WC core's legacy settings script manipulates `.woocommerce-save-button`
		// directly (jQuery change tracking force-enables the React-rendered
		// button, fighting the SDK's own dirty-state handling) — same
		// interference the classic WooPayments screen avoids in
		// output_payments_settings_screen().
		wp_dequeue_script( 'woocommerce_settings' );

		printf(
			'<div id="%1$s" data-wc-settings-ui="1" data-wc-settings-page="%2$s" data-wc-settings-section="%3$s"></div>',
			esc_attr( 'wc_settings_ui_checkout_' . self::PAGE_ID ),
			esc_attr( self::PAGE_ID ),
			esc_attr( self::PAGE_ID )
		);

		return true;
	}
}

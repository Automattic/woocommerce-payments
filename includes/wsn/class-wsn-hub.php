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

		// Expose the feature flag value (and any other settings the React app needs)
		// via the existing wcpaySettings global. The Hub reads wcpaySettings.featureFlags
		// at render time to gate rendering — defensive even though the menu is gated.
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.wcpaySettings = window.wcpaySettings || { featureFlags: { wsnHub: true } };',
			'before'
		);
	}

	/**
	 * Registers all REST controllers under the WSN namespace.
	 *
	 * Currently only the settings controller. Sibling controllers (taxonomy, pages,
	 * products-search, orders) ship with their per-tab issues (RSM-2480/2481/2493).
	 *
	 * WSN_Settings is required here (not lazily by the controller itself) because the
	 * controller's route-registration code path consumes `WSN_Settings::valid_visibility_modes()`
	 * for the schema enum — by the time register_routes() runs, the class must already exist.
	 */
	public function register_rest_controllers(): void {
		require_once WCPAY_ABSPATH . 'includes/wsn/class-wsn-settings.php';
		require_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-wsn-settings-controller.php';

		$settings_controller = new WC_REST_Payments_WSN_Settings_Controller();
		$settings_controller->register_routes();
	}
}

<?php
/**
 * Set up top-level menus for WCPay.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Admin Class.
 */
class WC_Payments_Admin {
	/**
	 * Hook in admin menu items.
	 */
	public function __construct() {
		// Add menu items.
		add_action( 'admin_menu', array( $this, 'add_payments_menu' ), 9 );
		add_action( 'init', array( $this, 'register_payments_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_payments_scripts' ) );
	}

	/**
	 * Add payments menu items.
	 */
	public function add_payments_menu() {
		add_menu_page(
			__( 'WooCommerce Payments', 'woocommerce-payments' ),
			__( 'Payments', 'woocommerce-payments' ),
			'manage_woocommerce',
			'wc-payments',
			array( $this, 'wc_payments_page' ),
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( WCPAY_ABSPATH . 'assets/img/admin-icon.svg' ) ),
			56 // After WooCommerce & Product menu items.
		);
	}

	/**
	 * Set up a div for the app to render into.
	 */
	public function wc_payments_page() {
		?>
		<div class="wrap">
			<div id="woocommerce-payments__root"></div>
		</div>
		<?php
	}

	/**
	 * Register the CSS and JS scripts
	 */
	public function register_payments_scripts() {
		wp_register_script(
			'WCPAY_DASH_APP',
			plugins_url( 'dist/index.js', WCPAY_PLUGIN_FILE ),
			array( 'wp-element' ),
			filemtime( WCPAY_ABSPATH . 'dist/index.js' ),
			true
		);
		wp_register_style(
			'WCPAY_DASH_APP',
			plugins_url( 'dist/index.css', WCPAY_PLUGIN_FILE ),
			array(),
			filemtime( WCPAY_ABSPATH . 'dist/index.css' )
		);
	}

	/**
	 * Load the assets
	 *
	 * @param string $hook fdfd.
	 */
	public function enqueue_payments_scripts( $hook ) {
		if ( 'toplevel_page_wc-payments' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'WCPAY_DASH_APP' );
		wp_enqueue_style( 'WCPAY_DASH_APP' );
	}
}

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
			array( $this, 'wc_payments_page' ), // TODO: Use a real payments icon.
			'dashicons-palmtree',
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
}

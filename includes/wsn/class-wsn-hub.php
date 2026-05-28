<?php
/**
 * Class WSN_Hub
 *
 * Bootstrap for the Woo Shopping Network Hub.
 *
 * Wires up the admin menu entry (under WooCommerce, alongside Orders / Products /
 * Customers — NOT under the WooPayments sub-menu) and the REST surface that the
 * admin React app at /shopping-network consumes. The React app itself is registered
 * by client/index.js via the woocommerce_admin_pages_list filter.
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
	 * Wires up WordPress hooks. Called from WC_Payments::init() when the feature flag is on.
	 */
	public function init_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_controllers' ] );
	}

	/**
	 * Registers the "Shopping Network" entry under the WooCommerce admin menu.
	 *
	 * Placement: top-level WooCommerce sub-item, alongside Home / Orders / Customers /
	 * Reports / Settings — NOT under the WooPayments sub-menu. Path `/shopping-network`
	 * is a wc-admin path (`wc-admin&path=/shopping-network`), so the React app
	 * registered by client/index.js handles rendering.
	 */
	public function register_admin_menu(): void {
		if ( ! function_exists( 'wc_admin_register_page' ) ) {
			return;
		}

		wc_admin_register_page(
			[
				'id'       => 'wc-shopping-network',
				'title'    => __( 'Shopping Network', 'woocommerce-payments' ),
				'parent'   => 'woocommerce',
				'path'     => '/shopping-network',
				'nav_args' => [
					'parent' => 'woocommerce',
					'order'  => 15,
				],
			]
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

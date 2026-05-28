<?php
/**
 * Class WSN_Hub
 *
 * Bootstrap for the Woo Shopping Network Hub.
 *
 * Wires up the REST surface that the admin React app at /payments/shopping-network
 * consumes. The admin menu entry itself lives in WC_Payments_Admin::add_payments_menu()
 * (centralized menu registration is the existing WCPay convention) and the React app
 * is registered by client/index.js via the woocommerce_admin_pages_list filter, so
 * there is no per-page render callback or asset enqueue here.
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
		add_action( 'rest_api_init', [ $this, 'register_rest_controllers' ] );
	}

	/**
	 * Registers all REST controllers under the WSN namespace.
	 *
	 * Currently only the settings controller. Sibling controllers (taxonomy, pages,
	 * products-search, orders) ship with their per-tab issues (RSM-2480/2481/2493).
	 */
	public function register_rest_controllers(): void {
		require_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-wsn-settings-controller.php';

		$settings_controller = new WC_REST_Payments_WSN_Settings_Controller();
		$settings_controller->register_routes();
	}
}

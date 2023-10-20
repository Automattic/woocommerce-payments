<?php
/**
 * Class WC_Payments_Subscriptions_Plugin_Notice_Manager
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * A class to handle the displaying of a warning notice when admin deactivate the WC Subscriptions extension.
 */
class WC_Payments_Subscriptions_Plugin_Notice_Manager {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * Initialize the class and attach callbacks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts_and_styles' ], 100 );
		add_action( 'admin_footer', [ $this, 'output_notice_template' ] );
	}

	/**
	 * Determines if the current screen is the admin plugins screen.
	 *
	 * @return bool Whether the current request is for the admin plugins screen.
	 */
	private function is_admin_plugins_screen() {
		if ( ! is_admin() ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && 'plugins' === $screen->id;
	}

	/**
	 * Enqueues the admin scripts needed on the plugins screen.
	 */
	public function enqueue_scripts_and_styles() {
		if ( ! $this->is_admin_plugins_screen() || ! WC_Payments_Subscription_Service::store_has_active_wcpay_subscriptions() ) {
			return;
		}

		// The backbone modal requires the WC admin styles to be loaded.
		wp_enqueue_style( 'woocommerce_admin_styles' );

		wp_register_script(
			'wcpay-subscriptions-plugin',
			plugins_url( 'includes/subscriptions/assets/js/plugin-page.js', WCPAY_PLUGIN_FILE ),
			[ 'jquery', 'wc-backbone-modal' ],
			WCPAY_VERSION_NUMBER,
			true
		);

		wp_enqueue_script( 'wcpay-subscriptions-plugin' );

		WC_Payments_Utils::enqueue_style(
			'wcpay-subscriptions-plugin-styles',
			plugins_url( 'includes/subscriptions/assets/css/plugin-page.css', WCPAY_PLUGIN_FILE ),
			[],
			WCPAY_VERSION_NUMBER,
			'all'
		);
	}

	/**
	 * Enqueues templates for plugin deactivation warnings on the admin plugin screen.
	 */
	public function output_notice_template() {
		if ( ! $this->is_admin_plugins_screen() ) {
			return;
		}

		wc_get_template( 'html-subscriptions-plugin-notice.php', [], '', dirname( __DIR__ ) . '/subscriptions/templates/' );

		// Load a slightly different notice for folks still using the legacy WCPay Subscriptions functionality.
		if ( WC_Payments::get_gateway()->is_subscriptions_plugin_active() ) {
			wc_get_template( 'html-woo-payments-deactivate-warning.php', [], '', dirname( __DIR__ ) . '/subscriptions/templates/' );
		} else {
			wc_get_template( 'html-wcpay-deactivate-warning.php', [], '', dirname( __DIR__ ) . '/subscriptions/templates/' );
		}
	}
}

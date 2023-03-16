<?php
/**
 * Class WC_Payments_Admin_Settings
 *
 * @package WooCommerce\Payments\Admin
 */

/**
 * WC_Payments_Admin_Settings class.
 */
class WC_Payments_Admin_Settings {

	/**
	 * WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Set of parameters to build the URL to the gateway's settings page.
	 *
	 * @var string[]
	 */
	private static $settings_url_params = [
		'page'    => 'wc-settings',
		'tab'     => 'checkout',
		'section' => WC_Payment_Gateway_WCPay::GATEWAY_ID,
	];

	/**
	 * Initialize class actions.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway Payment Gateway.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;

		add_action( 'woocommerce_woocommerce_payments_admin_notices', [ $this, 'display_test_mode_notice' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( WCPAY_PLUGIN_FILE ), [ $this, 'add_plugin_links' ] );
	}

	/**
	 * Add notice explaining test mode when it's enabled.
	 */
	public function display_test_mode_notice() {
		if ( WC_Payments::mode()->is_test() ) {
			?>
			<div id="wcpay-test-mode-notice" class="notice notice-warning">
				<p>
					<b><?php esc_html_e( 'Test mode active: ', 'woocommerce-payments' ); ?></b>
					<?php esc_html_e( "All transactions are simulated. Customers can't make real purchases through WooCommerce Payments.", 'woocommerce-payments' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Adds links to the plugin's row in the "Plugins" Wp-Admin page.
	 *
	 * @see https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
	 * @param array $links The existing list of links that will be rendered.
	 * @return array The list of links that will be rendered, after adding some links specific to this plugin.
	 */
	public function add_plugin_links( $links ) {
		$plugin_links = [
			'<a href="' . esc_attr( self::get_settings_url() ) . '">' . esc_html__( 'Settings', 'woocommerce-payments' ) . '</a>',
		];

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Whether the current page is the WooCommerce Payments settings page.
	 *
	 * @return bool
	 */
	public static function is_current_page_settings() {
		return count( self::$settings_url_params ) === count( array_intersect_assoc( $_GET, self::$settings_url_params ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Returns the URL of the configuration screen for this gateway, for use in internal links.
	 *
	 * @return string URL of the configuration screen for this gateway
	 */
	public static function get_settings_url() {
		return admin_url( add_query_arg( self::$settings_url_params, 'admin.php' ) );
	}
}

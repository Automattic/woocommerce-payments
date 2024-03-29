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
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'woocommerce_woocommerce_payments_admin_notices', [ $this, 'display_test_mode_notice' ] );
		add_action( 'woocommerce_woocommerce_payments_admin_notices', [ $this, 'display_duplicates_warning' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( WCPAY_PLUGIN_FILE ), [ $this, 'add_plugin_links' ] );
	}

	/**
	 * Add notice explaining test mode when it's enabled.
	 */
	public function display_duplicates_warning() {
		$enabled_gateways  = WC()->payment_gateways()->payment_gateways;
		$gateways_to_check = [];
		foreach ( $enabled_gateways as $gateway ) {
			if ( 'yes' === $gateway->enabled ) {
				$gateways_to_check[ $gateway->id ] = $gateway;
			}
		}
		$duplicates = $this->find_duplicates( $enabled_gateways );

		if ( ! empty( $duplicates ) ) {
			?>
			<div id="wcpay-test-mode-notice" class="notice notice-warning">
				<p>
					<b><?php esc_html_e( 'Duplicated payment methods: ', 'woocommerce-payments' ); ?></b>
					<?php
						echo sprintf(
							/* translators: %s: WooPayments */
							esc_html__( 'There are duplicated payment methods enabled', 'woocommerce-payments' ),
							'WooPayments'
						);
					?>
				</p>
			</div>
			<?php
		}
	}

		/**
		 * Find duplicates.
		 *
		 * @param array $gateways All enabled gateways.
		 * @return array Duplicated gateways.
		 */
	private function find_duplicates( $gateways ) {
		// Use associative array for counting occurrences.
		$counter                    = [];
		$duplicated_payment_methods = [];

		$gateway_ids = [
			'apple_pay',
			'applepay',
			'google_pay',
			'googlepay',
			'affirm',
			'afterpay',
			'clearpay',
			'klarna',
			'credit_card',
			'credicard',
			'cc',
			'bancontact',
			'ideal',
		];

		// Only loop through gateways once.
		foreach ( $gateways as $gateway ) {
			foreach ( $gateway_ids as $keyword ) {
				if ( strpos( $gateway->id, $keyword ) !== false ) {
					// Increment counter or initialize if not exists.
					if ( isset( $counter[ $keyword ] ) ) {
						$counter[ $keyword ]++;
					} else {
						$counter[ $keyword ] = 1;
					}

					// If more than one occurrence, add to duplicates.
					if ( $counter[ $keyword ] > 1 && method_exists( $gateway, 'get_stripe_id' ) ) {
						$duplicated_payment_methods[ $gateway->get_stripe_id() ] = $gateway; // Use keys to prevent duplicates.
					}
					break; // Stop searching once a match is found for this gateway.
				}
			}
		}

		// Return duplicated gateway titles.
		return array_keys( $duplicated_payment_methods );
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
					<?php
						echo sprintf(
							/* translators: %s: WooPayments */
							esc_html__( "All transactions are simulated. Customers can't make real purchases through %s.", 'woocommerce-payments' ),
							'WooPayments'
						);
					?>
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
	 * Whether the current page is the WooPayments settings page.
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
		return admin_url( add_query_arg( self::$settings_url_params, 'admin.php' ) ); // nosemgrep: audit.php.wp.security.xss.query-arg -- constant string is passed in.
	}
}

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
	 * Instance of WC_Payments_Account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

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
	 * @param WC_Payments_Account      $account The account service.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway, WC_Payments_Account $account ) {
		$this->gateway = $gateway;
		$this->account = $account;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'woocommerce_woocommerce_payments_admin_notices', [ $this, 'maybe_show_test_mode_notice' ] );
		add_action( 'woocommerce_woocommerce_payments_admin_notices', [ $this, 'maybe_show_test_account_notice' ] );
		add_action( 'woocommerce_woocommerce_payments_admin_notices', [ $this, 'maybe_show_sandbox_account_notice' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( WCPAY_PLUGIN_FILE ), [ $this, 'add_plugin_links' ] );
	}

	/**
	 * Add notice about payments being in test mode when using a live account.
	 *
	 * This notice is mutually exclusive with the test account and sandbox account notices.
	 *
	 * @see self::maybe_show_test_account_notice()
	 * @see self::maybe_show_sandbox_account_notice()
	 */
	public function maybe_show_test_mode_notice() {
		// If there is no valid account connected, bail.
		if ( ! $this->gateway->is_connected() || ! $this->account->is_stripe_account_valid() ) {
			return;
		}

		// If this is not a live account, bail since we will inform the user about the test account instead.
		if ( ! $this->account->get_is_live() ) {
			return;
		}

		// If the test mode is not enabled, bail.
		if ( ! WC_Payments::mode()->is_test() ) {
			return;
		}

		// Output the notice.
		?>
		<div id="wcpay-test-mode-notice" class="notice notice-warning">
			<p>
				<b>
					<?php
						printf(
							/* translators: %s: WooPayments */
							esc_html__( '%s is in test mode — all transactions are simulated!', 'woocommerce-payments' ) . ' ',
							'WooPayments'
						);
					?>
				</b>
				<?php
					printf(
						/* translators: 1: Anchor opening tag; 2: Anchor closing tag */
						esc_html__( 'You can use %1$stest card numbers%2$s to simulate various types of transactions.', 'woocommerce-payments' ),
						'<a href="' . esc_url( 'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#test-cards' ) . '" target="_blank" rel="noreferrer noopener">',
						'</a>'
					);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add notice to activate payments when a test account is in use.
	 *
	 * This notice is mutually exclusive with the test mode and sandbox account notices.
	 *
	 * @see self::maybe_show_test_mode_notice()
	 * @see self::maybe_show_sandbox_account_notice()
	 */
	public function maybe_show_test_account_notice() {
		// If there is no valid account connected, bail.
		if ( ! $this->gateway->is_connected() || ! $this->account->is_stripe_account_valid() ) {
			return;
		}

		// If this is a live account, bail.
		if ( $this->account->get_is_live() ) {
			return;
		}

		// If this is NOT a test [drive] account, bail.
		$account_status = $this->account->get_account_status_data();
		if ( empty( $account_status['testDrive'] ) ) {
			return;
		}

		// Output the notice.
		?>
		<div id="wcpay-test-account-notice" class="notice notice-warning">
			<p>
				<b><?php echo esc_html__( 'You are using a test account.', 'woocommerce-payments' ) . ' '; ?></b>
				<?php
				if ( ! WC_Payments::mode()->is_dev() ) {
					printf(
						/* translators: %s: URL to learn more */
						esc_html__( 'Provide additional details about your business so you can begin accepting real payments. %1$sLearn more%2$s', 'woocommerce-payments' ),
						'<a href="' . esc_url( 'https://woocommerce.com/document/woopayments/startup-guide/#sign-up-process' ) . '" target="_blank" rel="noreferrer noopener">',
						'</a>'
					);
				} else {
					esc_html_e( '⚠️ Development mode is enabled for the store! There can be no live onboarding process while using development, testing, or staging WordPress environments!', 'woocommerce-payments' );
					echo '</br>';
					printf(
					/* translators: 1: Anchor opening tag; 2: Anchor closing tag; 3: Anchor opening tag; 4: Anchor closing tag */
						esc_html__( 'To begin accepting real payments, please go to the live store or change your %1$sWordPress environment%2$s to a production one. %3$sLearn more%4$s', 'woocommerce-payments' ),
						'<a href="' . esc_url( 'https://make.wordpress.org/core/2020/08/27/wordpress-environment-types/' ) . '" target="_blank" rel="noreferrer noopener">',
						'</a>',
						'<a href="' . esc_url( 'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/#developer-notes' ) . '" target="_blank" rel="noreferrer noopener">',
						'</a>'
					);
				}
				?>
			</p>
			<?php if ( ! WC_Payments::mode()->is_dev() ) { ?>
				<p>
					<a id="wcpay-activate-payments-button" href="#" class="button button-secondary">
						<?php esc_html_e( 'Activate payments', 'woocommerce-payments' ); ?>
					</a>
				</p>
			<?php } ?>
		</div>
		<?php if ( ! WC_Payments::mode()->is_dev() ) { ?>
			<script type="text/javascript">
				// We dispatch an event to trigger the modal.
				// The listener is in the general-settings/index.js file.
				document.addEventListener( 'DOMContentLoaded', function () {
					var activateButton = document.getElementById( 'wcpay-activate-payments-button' );
					if ( !activateButton ) {
						return;
					}
					activateButton.addEventListener( 'click', function ( e ) {
						e.preventDefault();
						document.dispatchEvent( new CustomEvent( 'wcpay:activate_payments' ) );
					} );
				} );
			</script>
			<?php
		}
	}

	/**
	 * Add notice to inform that a sandbox account is in use.
	 *
	 * This notice is mutually exclusive with the test mode and test account notices.
	 *
	 * @see self::maybe_show_test_mode_notice()
	 * @see self::maybe_show_test_account_notice()
	 */
	public function maybe_show_sandbox_account_notice() {
		// If there is no valid account connected, bail.
		if ( ! $this->gateway->is_connected() || ! $this->account->is_stripe_account_valid() ) {
			return;
		}

		// If this is a live account, bail.
		if ( $this->account->get_is_live() ) {
			return;
		}

		// If this is a test [drive] account, bail.
		$account_status = $this->account->get_account_status_data();
		if ( ! empty( $account_status['testDrive'] ) ) {
			return;
		}

		// Output the notice.
		?>
		<div id="wcpay-test-account-notice" class="notice notice-warning">
			<p>
				<b><?php echo esc_html__( 'You are using a sandbox test account.', 'woocommerce-payments' ) . ' '; ?></b>
				<?php
				if ( ! WC_Payments::mode()->is_dev() ) {
					printf(
					/* translators: 1: Anchor opening tag; 2: Anchor closing tag; 3: Anchor opening tag; 4: Anchor closing tag */
						esc_html__( 'To begin accepting real payments you will need to first %1$sreset your account%2$s and, then, provide additional details about your business. %3$sLearn more%4$s', 'woocommerce-payments' ),
						'<a href="' . esc_url( 'https://woocommerce.com/document/woopayments/startup-guide/#resetting' ) . '" target="_blank" rel="noreferrer noopener">',
						'</a>',
						'<a href="' . esc_url( 'https://woocommerce.com/document/woopayments/startup-guide/#sign-up-process' ) . '" target="_blank" rel="noreferrer noopener">',
						'</a>',
					);
				} else {
					esc_html_e( '⚠️ Development mode is enabled for the store! There can be no live onboarding process while using development, testing, or staging WordPress environments!', 'woocommerce-payments' );
					echo '</br>';
					printf(
					/* translators: 1: Anchor opening tag; 2: Anchor closing tag; 3: Anchor opening tag; 4: Anchor closing tag */
						esc_html__( 'To begin accepting real payments, please go to the live store or change your %1$sWordPress environment%2$s to a production one. %3$sLearn more%4$s', 'woocommerce-payments' ),
						'<a href="' . esc_url( 'https://make.wordpress.org/core/2020/08/27/wordpress-environment-types/' ) . '" target="_blank" rel="noreferrer noopener">',
						'</a>',
						'<a href="' . esc_url( 'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/#developer-notes' ) . '" target="_blank" rel="noreferrer noopener">',
						'</a>'
					);
				}
				?>
			</p>
		</div>
		<?php
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
	 * @param array $query_args Optional additional query args to append to the URL.
	 *
	 * @return string URL of the configuration screen for this gateway
	 */
	public static function get_settings_url( $query_args = [] ) {
		return admin_url( add_query_arg( array_merge( self::$settings_url_params, $query_args ), 'admin.php' ) ); // nosemgrep: audit.php.wp.security.xss.query-arg -- constant string is passed in.
	}
}

<?php
/**
 * Class WC_Payments
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main class for the WooCommerce Payments extension. Its responsibility is to initialize the extension.
 */
class WC_Payments {

	/**
	 * Instance of WC_Payment_Gateway_WCPay, created in init function.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private static $gateway;

	/**
	 * Instance of WC_Payments_API_Client, created in init function.
	 *
	 * @var WC_Payments_API_Client
	 */
	private static $api_client;

	/**
	 * Cache for plugin headers to avoid multiple calls to get_file_data
	 *
	 * @var array
	 */
	private static $plugin_headers = null;

	/**
	 * Entry point to the initialization logic.
	 */
	public static function init() {
		define( 'WCPAY_VERSION_NUMBER', self::get_plugin_headers()['Version'] );

		if ( ! self::check_plugin_dependencies( true ) ) {
			add_filter( 'admin_notices', array( __CLASS__, 'check_plugin_dependencies' ) );
			return;
		}

		if ( ! self::check_multi_currency_disabled() ) {
			return;
		};

		add_filter( 'plugin_action_links_' . plugin_basename( WCPAY_PLUGIN_FILE ), array( __CLASS__, 'add_plugin_links' ) );

		self::$api_client = self::create_api_client();

		include_once dirname( __FILE__ ) . '/class-wc-payment-gateway-wcpay.php';
		self::$gateway = new WC_Payment_Gateway_WCPay( self::$api_client );

		// Add account ID to the payments.
		self::$api_client->set_account_id( self::$gateway->get_option( 'stripe_account_id' ) );

		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register_gateway' ) );

		// Add admin screens.
		if ( is_admin() ) {
			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-payments-admin.php';
			new WC_Payments_Admin();

			// Only check stripe account requirements if user is in an admin page
			// as regular users would not be able to take any action.
			self::check_stripe_account_status();
		}

		add_action( 'rest_api_init', array( __CLASS__, 'init_rest_api' ) );
	}

	/**
	 * Prints the given message in an "admin notice" wrapper with "error" class.
	 *
	 * @param string $message Message to print. Can contain HTML.
	 */
	private static function display_admin_error( $message ) {
		self::display_admin_notice( $message, 'notice-error' );
	}

	/**
	 * Prints the given message in an "admin notice" wrapper with provided classes.
	 *
	 * @param string $message Message to print. Can contain HTML.
	 * @param string $classes Space separated list of classes to be applied to notice element.
	 */
	private static function display_admin_notice( $message, $classes ) {
		?>
		<div class="notice <?php echo esc_attr( $classes ); ?>">
			<p><b><?php echo esc_html( __( 'WooCommerce Payments', 'woocommerce-payments' ) ); ?></b></p>
			<p><?php echo $message; // PHPCS:Ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		</div>
		<?php
	}

	/**
	 * Prints a dismissible admin notice when Woo Multi-Currency is enabled.
	 */
	public static function multi_currency_enabled__error() {
		$class   = 'notice notice-error is-dismissible';
		$message = sprintf(
			/* translators: %1: WooCommerce Payments version */
			__( 'WooCommerce Payments %1$s does not support WooCommerce Multi-Currency and has not been loaded.', 'woocommerce-payments' ),
			WCPAY_VERSION_NUMBER
		);
		return printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Get plugin headers and cache the result to avoid reopening the file.
	 * First call should execute get_file_data and fetch headers from plugin details comment.
	 * Subsequent calls return the value stored in the variable $plugin_headers.
	 *
	 * @return array Array with plugin headers
	 */
	public static function get_plugin_headers() {
		if ( null === self::$plugin_headers ) {
			self::$plugin_headers = get_file_data(
				WCPAY_PLUGIN_FILE,
				array(
					// Mirrors the functionality on WooCommerce core: https://github.com/woocommerce/woocommerce/blob/ff2eadeccec64aa76abd02c931bf607dd819bbf0/includes/wc-core-functions.php#L1916 .
					'WCRequires' => 'WC requires at least',
					// The "Requires WP" plugin header is proposed and being implemented here: https://core.trac.wordpress.org/ticket/43992
					// TODO: Check before release if the "Requires WP" header name has been accepted, or we should use a header on the readme.txt file instead.
					'RequiresWP' => 'Requires WP',
					'Version'    => 'Version',
				)
			);
		}
		return self::$plugin_headers;
	}

	/**
	 * Checks if all the dependencies needed to run this plugin are present
	 * TODO: Before public launch, revisit these dependencies. We may need to bump the WC dependency so we require one where WC-Admin is already in Core.
	 *
	 * @param bool $silent True if the function should just return true/false, False if this function should display notice messages for failed dependencies.
	 * @return bool True if all dependencies are met, false otherwise
	 */
	public static function check_plugin_dependencies( $silent ) {
		$plugin_headers = self::get_plugin_headers();

		$wc_version = $plugin_headers['WCRequires'];
		$wp_version = $plugin_headers['RequiresWP'];

		// Check if WooCommerce is installed and active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			if ( ! $silent ) {
				$message = sprintf(
					/* translators: %1: WooCommerce plugin URL */
					__( 'WooCommerce Payments requires <a href="%1$s">WooCommerce</a> to be installed and active.', 'woocommerce-payments' ),
					'https://wordpress.org/plugins/woocommerce/'
				);

				if ( current_user_can( 'install_plugins' ) ) {
					$wc_plugin_name = 'woocommerce/woocommerce.php';
					$wc_plugin_slug = 'woocommerce';
					if ( validate_plugin( $wc_plugin_name ) ) {
						// The plugin is installed, so it just needs to be enabled.
						$activate_url = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=' . $wc_plugin_slug ), 'install-plugin_' . $wc_plugin_slug );
						$message     .= ' <a href="' . $activate_url . '">' . __( 'Install WooCommerce', 'woocommerce-payments' ) . '</a>';
					} else {
						// The plugin is not installed.
						$activate_url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $wc_plugin_name ), 'activate-plugin_' . $wc_plugin_name );
						$message     .= ' <a href="' . $activate_url . '">' . __( 'Activate WooCommerce', 'woocommerce-payments' ) . '</a>';
					}
				}

				self::display_admin_error( $message );
			}
			return false;
		}

		// Check if the version of WooCommerce is compatible with WooCommerce Payments.
		if ( version_compare( WC_VERSION, $wc_version, '<' ) ) {
			if ( ! $silent ) {
				$message = sprintf(
					/* translators: %1: required WC version number, %2: currently installed WC version number */
					__( 'WooCommerce Payments requires <strong>WooCommerce %1$s</strong> or greater to be installed (you are using %2$s).', 'woocommerce-payments' ),
					$wc_version,
					WC_VERSION
				);
				if ( current_user_can( 'update_plugins' ) ) {
					// Take the user to the "plugins" screen instead of trying to update WooCommerce inline. WooCommerce adds important information
					// on its plugin row regarding the currently installed extensions and their compatibility with the latest WC version.
					$message .= ' <a href="' . admin_url( 'plugins.php' ) . '">' . __( 'Update WooCommerce', 'woocommerce-payments' ) . '</a>';
				}
				self::display_admin_error( $message );
			}
			return false;
		}

		// Check if the version of WordPress is compatible with WooCommerce Payments.
		if ( version_compare( get_bloginfo( 'version' ), $wp_version, '<' ) ) {
			if ( ! $silent ) {
				$message = sprintf(
					/* translators: %1: required WP version number, %2: currently installed WP version number */
					__( 'WooCommerce Payments requires <strong>WordPress %1$s</strong> or greater (you are using %2$s).', 'woocommerce-payments' ),
					$wp_version,
					get_bloginfo( 'version' )
				);
				if ( current_user_can( 'update_core' ) ) {
					$message .= ' <a href="' . admin_url( 'update-core.php' ) . '">' . __( 'Update WordPress', 'woocommerce-payments' ) . '</a>';
				}
				self::display_admin_error( $message );
			}
			return false;
		}

		return true;
	}

	/**
	 * Checks if Stripe account is connected and displays admin notices if it is not.
	 *
	 * @return boolean True if the account is registered and has no pending requirements.
	 * @throws Exception - If self::$gateway is not defined.
	 */
	public static function check_stripe_account_status() {
		if ( ! isset( self::$gateway ) ) {
			throw new Exception( __( 'Payment Gateway class was not initialized.', 'woocommerce-payments' ) );
		}

		if ( ! isset( self::$api_client ) ) {
			throw new Exception( __( 'API Client class was not initialized.', 'woocommerce-payments' ) );
		}

		if ( ! self::$gateway->is_stripe_connected() ) {
			add_filter(
				'admin_notices',
				function () {
					self::display_admin_notice(
						self::$gateway->get_connect_message(),
						'notice-success'
					);
				}
			);
			return false;
		}

		$account = self::$api_client->get_account_data();

		if ( is_wp_error( $account ) ) {
			$message = sprintf(
				/* translators: %1: error message */
				__( 'Could not fetch data for your account: "%1$s"', 'woocommerce-payments' ),
				$account->get_error_message()
			);
			add_filter(
				'admin_notices',
				function () use ( $message ) {
					self::display_admin_error( $message );
				}
			);
			return false;
		}

		if ( self::$gateway->account_has_pending_requirements( $account ) ) {
			add_filter(
				'admin_notices',
				function () {
					self::display_admin_error(
						self::$gateway->get_verify_requirements_message( $account['requirements']['current_deadline'] )
					);
				}
			);
			return false;
		}

		return true;
	}

	/**
	 * Checks whether Woo Multi-Currency is disabled and displays admin error message if enabled.
	 * TODO: Once Multi-Currency support is implemented, remove this check.
	 *
	 * @return bool True if Woo Multi-Currency is not enabled, false otherwise.
	 */
	public static function check_multi_currency_disabled() {
		if ( class_exists( 'WOOMC\MultiCurrency\App' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'multi_currency_enabled__error' ) );
			return false;
		}

		return true;
	}

	/**
	 * Adds links to the plugin's row in the "Plugins" Wp-Admin page.
	 *
	 * @see https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
	 * @param array $links The existing list of links that will be rendered.
	 * @return array The list of links that will be rendered, after adding some links specific to this plugin.
	 */
	public static function add_plugin_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_attr( WC_Payment_Gateway_WCPay::get_settings_url() ) . '">' . esc_html__( 'Settings', 'woocommerce-payments' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Adds the WooCommerce Payments' gateway class to the list of installed payment gateways.
	 *
	 * @param array $gateways Existing list of gateway classes that will be available for the merchant to configure.
	 * @return array The list of payment gateways that will be available, including WooCommerce Payments' Gateway class.
	 */
	public static function register_gateway( $gateways ) {
		$gateways[] = self::$gateway;

		return $gateways;
	}

	/**
	 * Create the API client.
	 *
	 * @return WC_Payments_API_Client
	 */
	public static function create_api_client() {
		require_once dirname( __FILE__ ) . '/wc-payment-api/models/class-wc-payments-api-charge.php';
		require_once dirname( __FILE__ ) . '/wc-payment-api/models/class-wc-payments-api-intention.php';
		require_once dirname( __FILE__ ) . '/wc-payment-api/class-wc-payments-api-client.php';
		require_once dirname( __FILE__ ) . '/wc-payment-api/class-wc-payments-http.php';

		// TODO: Don't hard code user agent string.
		$payments_api_client = new WC_Payments_API_Client(
			'WooCommerce Payments/0.1.0',
			new WC_Payments_Http()
		);

		return $payments_api_client;
	}

	/**
	 * Initialize the REST API controllers.
	 */
	public static function init_rest_api() {
		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-transactions-controller.php';
		$transactions_controller = new WC_REST_Payments_Transactions_Controller( self::$api_client );
		$transactions_controller->register_routes();
	}

	/**
	 * Gets the file modified time as a cache buster if we're in dev mode, or the plugin version otherwise.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	public static function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$file = trim( $file, '/' );
			return filemtime( WCPAY_ABSPATH . $file );
		}
		return WCPAY_VERSION_NUMBER;
	}
}

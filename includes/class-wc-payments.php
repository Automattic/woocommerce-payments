<?php
/**
 * Class WC_Payments
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Logger;

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
	 * Instance of WC_Payments_DB.
	 *
	 * @var WC_Payments_DB
	 */
	private static $db_helper;

	/**
	 * Instance of WC_Payments_Account, created in init function.
	 *
	 * @var WC_Payments_Account
	 */
	private static $account;

	/**
	 * Instance of WC_Payments_Customer_Service, created in init function.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private static $customer_service;

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

		include_once dirname( __FILE__ ) . '/class-wc-payments-utils.php';

		if ( ! self::check_plugin_dependencies( true ) ) {
			add_filter( 'admin_notices', [ __CLASS__, 'check_plugin_dependencies' ] );
			return;
		}

		if ( ! self::check_multi_currency_disabled() ) {
			return;
		};

		add_filter( 'plugin_action_links_' . plugin_basename( WCPAY_PLUGIN_FILE ), [ __CLASS__, 'add_plugin_links' ] );

		include_once dirname( __FILE__ ) . '/class-wc-payments-db.php';
		self::$db_helper = new WC_Payments_DB();

		self::$api_client = self::create_api_client();

		include_once dirname( __FILE__ ) . '/class-wc-payments-account.php';
		include_once dirname( __FILE__ ) . '/class-wc-payments-customer-service.php';
		include_once dirname( __FILE__ ) . '/class-logger.php';
		include_once dirname( __FILE__ ) . '/class-wc-payment-gateway-wcpay.php';
		self::$account          = new WC_Payments_Account( self::$api_client );
		self::$customer_service = new WC_Payments_Customer_Service( self::$api_client );

		self::$gateway = new WC_Payment_Gateway_WCPay( self::$api_client, self::$account, self::$customer_service );

		add_filter( 'woocommerce_payment_gateways', [ __CLASS__, 'register_gateway' ] );
		add_filter( 'option_woocommerce_gateway_order', [ __CLASS__, 'set_gateway_top_of_list' ], 2 );
		add_filter( 'default_option_woocommerce_gateway_order', [ __CLASS__, 'set_gateway_top_of_list' ], 3 );

		// Add admin screens.
		if ( is_admin() ) {
			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-payments-admin.php';
			new WC_Payments_Admin( self::$gateway, self::$account );
		}

		add_action( 'rest_api_init', [ __CLASS__, 'init_rest_api' ] );
	}

	/**
	 * Prints the given message in an "admin notice" wrapper with "error" class.
	 *
	 * @param string $message Message to print. Can contain HTML.
	 */
	public static function display_admin_error( $message ) {
		self::display_admin_notice( $message, 'notice-error' );
	}

	/**
	 * Prints the given message in an "admin notice" wrapper with provided classes.
	 *
	 * @param string $message Message to print. Can contain HTML.
	 * @param string $classes Space separated list of classes to be applied to notice element.
	 */
	public static function display_admin_notice( $message, $classes ) {
		?>
		<div class="notice wcpay-notice <?php echo esc_attr( $classes ); ?>">
			<p><b><?php echo esc_html( __( 'WooCommerce Payments', 'woocommerce-payments' ) ); ?></b></p>
			<p><?php echo $message; // PHPCS:Ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		</div>
		<?php
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
				[
					// Mirrors the functionality on WooCommerce core: https://github.com/woocommerce/woocommerce/blob/ff2eadeccec64aa76abd02c931bf607dd819bbf0/includes/wc-core-functions.php#L1916 .
					'WCRequires' => 'WC requires at least',
					// The "Requires WP" plugin header is proposed and being implemented here: https://core.trac.wordpress.org/ticket/43992
					// TODO: Check before release if the "Requires WP" header name has been accepted, or we should use a header on the readme.txt file instead.
					'RequiresWP' => 'Requires WP',
					'Version'    => 'Version',
				]
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
		if ( defined( 'WCPAY_TEST_ENV' ) && WCPAY_TEST_ENV ) {
			return true;
		}

		$plugin_headers = self::get_plugin_headers();

		// Do not show alerts while installing plugins.
		if ( ! $silent && self::is_at_plugin_install_page() ) {
			return true;
		}

		$wc_version = $plugin_headers['WCRequires'];
		$wp_version = $plugin_headers['RequiresWP'];

		$plugin_dependencies = [
			[
				'name'  => 'WooCommerce',
				'class' => 'WooCommerce',
				'slug'  => 'woocommerce',
				'file'  => 'woocommerce/woocommerce.php',
			],
			[
				'name'  => 'WooCommerce Admin',
				'class' => '\Automattic\WooCommerce\Admin\FeaturePlugin',
				'slug'  => 'woocommerce-admin',
				'file'  => 'woocommerce-admin/woocommerce-admin.php',
			],
		];

		// Check if WooCommerce and other dependencies are  installed and active.
		foreach ( $plugin_dependencies as $plugin_data ) {
			if ( ! class_exists( $plugin_data['class'] ) ) {
				if ( ! $silent ) {
					$message = WC_Payments_Utils::esc_interpolated_html(
						sprintf(
							/* translators: %1: plugin name */
							__( 'WooCommerce Payments requires <a>%1$s</a> to be installed and active.', 'woocommerce-payments' ),
							$plugin_data['name']
						),
						[ 'a' => '<a href="https://wordpress.org/plugins/' . trailingslashit( $plugin_data['slug'] ) . '">' ]
					);

					if ( current_user_can( 'install_plugins' ) ) {
						if ( is_wp_error( validate_plugin( $plugin_data['file'] ) ) ) {
							// The plugin is not installed.
							$activate_url = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=' . $plugin_data['slug'] ), 'install-plugin_' . $plugin_data['slug'] );
							/* translators: %1: plugin name */
							$activate_text = sprintf( __( 'Install %1$s', 'woocommerce-payments' ), $plugin_data['name'] );
						} else {
							// The plugin is installed, so it just needs to be enabled.
							$activate_url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $plugin_data['file'] ), 'activate-plugin_' . $plugin_data['file'] );
							/* translators: %1: plugin name */
							$activate_text = sprintf( __( 'Activate %1$s', 'woocommerce-payments' ), $plugin_data['name'] );
						}
						$message .= ' <a href="' . $activate_url . '">' . $activate_text . '</a>';
					}

					self::display_admin_error( $message );
				}
				return false;
			}
		}

		// Check if the current WooCommerce version has WooCommerce Admin bundled (WC 4.0+) but it's disabled using a filter.
		if ( ! defined( 'WC_ADMIN_VERSION_NUMBER' ) ) {
			if ( ! $silent ) {
				self::display_admin_error(
					WC_Payments_Utils::esc_interpolated_html(
						__( 'WooCommerce Payments requires WooCommerce Admin to be enabled. Please remove the <code>woocommerce_admin_disabled</code> filter to use WooCommerce Payments.', 'woocommerce-payments' ),
						[ 'code' => '<code>' ]
					)
				);
			}
			return false;
		}

		// Check if the version of WooCommerce Admin is compatible with WooCommerce Payments.
		if ( version_compare( WC_ADMIN_VERSION_NUMBER, WCPAY_MIN_WC_ADMIN_VERSION, '<' ) ) {
			if ( ! $silent ) {
				$message = WC_Payments_Utils::esc_interpolated_html(
					sprintf(
						/* translators: %1: required WC-Admin version number, %2: currently installed WC-Admin version number */
						__( 'WooCommerce Payments requires <strong>WooCommerce Admin %1$s</strong> or greater to be installed (you are using %2$s).', 'woocommerce-payments' ),
						WCPAY_MIN_WC_ADMIN_VERSION,
						WC_ADMIN_VERSION_NUMBER
					),
					[ 'strong' => '<strong>' ]
				);

				if ( defined( 'WC_ADMIN_PACKAGE_EXISTS' ) ) { // Let's assume for now that any WC-Admin version bundled with WooCommerce will meet our minimum requirements.
					$message .= ' ' . __( 'There is a newer version of WooCommerce Admin bundled with WooCommerce.', 'woocommerce-payments' );
					if ( current_user_can( 'deactivate_plugins' ) ) {
						$deactivate_url = wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=woocommerce-admin/woocommerce-admin.php' ), 'deactivate-plugin_woocommerce-admin/woocommerce-admin.php' );
						$message       .= ' <a href="' . $deactivate_url . '">' . __( 'Use the bundled version of WooCommerce Admin', 'woocommerce-payments' ) . '</a>';
					}
				} else {
					if ( current_user_can( 'update_plugins' ) ) {
						$update_url = wp_nonce_url( admin_url( 'update.php?action=upgrade-plugin&plugin=woocommerce-admin/woocommerce-admin.php' ), 'upgrade-plugin_woocommerce-admin/woocommerce-admin.php' );
						$message   .= ' <a href="' . $update_url . '">' . __( 'Update WooCommerce Admin', 'woocommerce-payments' ) . '</a>';
					}
				}
				self::display_admin_error( $message );
			}
			return false;
		}

		// Check if the version of WooCommerce is compatible with WooCommerce Payments.
		if ( version_compare( WC_VERSION, $wc_version, '<' ) ) {
			if ( ! $silent ) {
				$message = WC_Payments_Utils::esc_interpolated_html(
					sprintf(
						/* translators: %1: required WC version number, %2: currently installed WC version number */
						__( 'WooCommerce Payments requires <strong>WooCommerce %1$s</strong> or greater to be installed (you are using %2$s).', 'woocommerce-payments' ),
						$wc_version,
						WC_VERSION
					),
					[ 'strong' => '<strong>' ]
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
				$message = WC_Payments_Utils::esc_interpolated_html(
					sprintf(
						/* translators: %1: required WP version number, %2: currently installed WP version number */
						__( 'WooCommerce Payments requires <strong>WordPress %1$s</strong> or greater (you are using %2$s).', 'woocommerce-payments' ),
						$wp_version,
						get_bloginfo( 'version' )
					),
					[ 'strong' => '<strong>' ]
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
	 * Checks if current page is plugin installation process page.
	 *
	 * @return bool True when installing plugin.
	 */
	private static function is_at_plugin_install_page() {
		$cur_screen = get_current_screen();
		return 'update' === $cur_screen->id && 'plugins' === $cur_screen->parent_base;
	}

	/**
	 * Checks whether Woo Multi-Currency is disabled and displays admin error message if enabled.
	 * TODO: Once Multi-Currency support is implemented, remove this check.
	 *
	 * @return bool True if Woo Multi-Currency is not enabled, false otherwise.
	 */
	public static function check_multi_currency_disabled() {
		if ( class_exists( 'WOOMC\MultiCurrency\App' ) ) {
			$message = sprintf(
				/* translators: %1: WooCommerce Payments version */
				__( 'WooCommerce Payments %1$s does not support WooCommerce Multi-Currency and has not been loaded.', 'woocommerce-payments' ),
				WCPAY_VERSION_NUMBER
			);

			add_filter(
				'admin_notices',
				function () use ( $message ) {
					self::display_admin_error( $message );
				}
			);
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
		$plugin_links = [
			'<a href="' . esc_attr( WC_Payment_Gateway_WCPay::get_settings_url() ) . '">' . esc_html__( 'Settings', 'woocommerce-payments' ) . '</a>',
		];

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
	 * By default, new payment gateways are put at the bottom of the list on the admin "Payments" settings screen.
	 * For visibility, we want WooCommerce Payments to be at the top of the list.
	 *
	 * @param array $ordering Existing ordering of the payment gateways.
	 *
	 * @return array Modified ordering.
	 */
	public static function set_gateway_top_of_list( $ordering ) {
		$ordering = (array) $ordering;
		$id       = self::$gateway->id;
		// Only tweak the ordering if the list hasn't been reordered with WooCommerce Payments in it already.
		if ( ! isset( $ordering[ $id ] ) || ! is_numeric( $ordering[ $id ] ) ) {
			$ordering[ $id ] = empty( $ordering ) ? 0 : ( min( $ordering ) - 1 );
		}
		return $ordering;
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
		require_once dirname( __FILE__ ) . '/wc-payment-api/class-wc-payments-api-exception.php';
		require_once dirname( __FILE__ ) . '/wc-payment-api/class-wc-payments-http.php';

		$payments_api_client = new WC_Payments_API_Client(
			'WooCommerce Payments/' . WCPAY_VERSION_NUMBER,
			new WC_Payments_Http( new Automattic\Jetpack\Connection\Manager( 'woocommerce-payments' ) ),
			self::$db_helper
		);

		return $payments_api_client;
	}

	/**
	 * Initialize the REST API controllers.
	 */
	public static function init_rest_api() {
		include_once WCPAY_ABSPATH . 'includes/exceptions/class-wc-payments-rest-request-exception.php';
		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-payments-rest-controller.php';

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-deposits-controller.php';
		$deposits_controller = new WC_REST_Payments_Deposits_Controller( self::$api_client );
		$deposits_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-transactions-controller.php';
		$transactions_controller = new WC_REST_Payments_Transactions_Controller( self::$api_client );
		$transactions_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-disputes-controller.php';
		$disputes_controller = new WC_REST_Payments_Disputes_Controller( self::$api_client );
		$disputes_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-charges-controller.php';
		$charges_controller = new WC_REST_Payments_Charges_Controller( self::$api_client );
		$charges_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-timeline-controller.php';
		$timeline_controller = new WC_REST_Payments_Timeline_Controller( self::$api_client );
		$timeline_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-webhook-controller.php';
		$webhook_controller = new WC_REST_Payments_Webhook_Controller( self::$api_client, self::$db_helper, self::$account );
		$webhook_controller->register_routes();
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

	/**
	 * Returns the WC_Payment_Gateway_WCPay instance
	 *
	 * @return WC_Payment_Gateway_WCPay gateway instance
	 */
	public static function get_gateway() {
		return self::$gateway;
	}
}

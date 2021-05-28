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
use WCPay\Payment_Methods\CC_Payment_Gateway;
use WCPay\Payment_Methods\Giropay_Payment_Gateway;
use WCPay\Payment_Methods\Sepa_Payment_Gateway;
use WCPay\Payment_Methods\Sofort_Payment_Gateway;
use WCPay\Payment_Methods\Digital_Wallets_Payment_Gateway;
use WCPay\Payment_Methods\UPE_Payment_Gateway;

/**
 * Main class for the WooCommerce Payments extension. Its responsibility is to initialize the extension.
 */
class WC_Payments {

	/**
	 * Instance of WC_Payment_Gateway_WCPay, created in init function.
	 *
	 * @var CC_Payment_Gateway
	 */
	private static $card_gateway;

	/**
	 * Instance of Giropay gateway, created in init function.
	 *
	 * @var Giropay_Payment_Gateway
	 */
	private static $giropay_gateway;

	/**
	 * Instance of SEPA gateway, created in init function.
	 *
	 * @var Sepa_Payment_Gateway
	 */
	private static $sepa_gateway;

	/**
	 * Instance of Sofort gateway, created in init function.
	 *
	 * @var Sofort_Payment_Gateway
	 */
	private static $sofort_gateway;

	/**
	 * Instance of Digital Wallets payment gateway, created in init function.
	 *
	 * @var Digital_Wallets_Payment_Gateway
	 */
	private static $digital_wallets_gateway;

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
	 * Instance of WC_Payments_Token_Service, created in init function.
	 *
	 * @var WC_Payments_Token_Service
	 */
	private static $token_service;

	/**
	 * Instance of WC_Payments_Remote_Note_Service, created in init function.
	 *
	 * @var WC_Payments_Remote_Note_Service
	 */
	private static $remote_note_service;

	/**
	 * Instance of WC_Payments_Action_Scheduler_Service, created in init function
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private static $action_scheduler_service;

	/**
	 * Instance of WC_Payments_Fraud_Service, created in init function
	 *
	 * @var WC_Payments_Fraud_Service
	 */
	private static $fraud_service;

	/**
	 * Instance of WC_Payments_Payment_Request_Button_Handler, created in init function
	 *
	 * @var WC_Payments_Payment_Request_Button_Handler
	 */
	private static $payment_request_button_handler;

	/**
	 * Instance of WC_Payments_Apple_Pay_Registration, created in init function
	 *
	 * @var WC_Payments_Apple_Pay_Registration
	 */
	private static $apple_pay_registration;

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

		include_once __DIR__ . '/class-wc-payments-features.php';
		include_once __DIR__ . '/class-wc-payments-utils.php';

		if ( ! self::check_plugin_dependencies( true ) ) {
			add_filter( 'admin_notices', [ __CLASS__, 'check_plugin_dependencies' ] );
			return;
		}

		add_action( 'admin_init', [ __CLASS__, 'add_woo_admin_notes' ] );
		add_action( 'admin_init', [ __CLASS__, 'install_actions' ] );

		add_filter( 'plugin_action_links_' . plugin_basename( WCPAY_PLUGIN_FILE ), [ __CLASS__, 'add_plugin_links' ] );
		add_action( 'woocommerce_blocks_payment_method_type_registration', [ __CLASS__, 'register_checkout_gateway' ] );

		include_once __DIR__ . '/class-wc-payments-db.php';
		self::$db_helper = new WC_Payments_DB();

		include_once __DIR__ . '/exceptions/class-base-exception.php';
		include_once __DIR__ . '/exceptions/class-api-exception.php';
		include_once __DIR__ . '/exceptions/class-connection-exception.php';

		self::$api_client = self::create_api_client();

		include_once __DIR__ . '/class-wc-payments-account.php';
		include_once __DIR__ . '/class-wc-payments-customer-service.php';
		include_once __DIR__ . '/class-logger.php';
		include_once __DIR__ . '/class-wc-payment-gateway-wcpay.php';
		include_once __DIR__ . '/payment-methods/class-cc-payment-gateway.php';
		include_once __DIR__ . '/payment-methods/class-giropay-payment-gateway.php';
		include_once __DIR__ . '/payment-methods/class-sepa-payment-gateway.php';
		include_once __DIR__ . '/payment-methods/class-sofort-payment-gateway.php';
		include_once __DIR__ . '/payment-methods/class-digital-wallets-payment-gateway.php';
		include_once __DIR__ . '/payment-methods/class-upe-payment-gateway.php';
		include_once __DIR__ . '/class-wc-payment-token-wcpay-sepa.php';
		include_once __DIR__ . '/class-wc-payments-token-service.php';
		include_once __DIR__ . '/class-wc-payments-payment-request-button-handler.php';
		include_once __DIR__ . '/class-wc-payments-apple-pay-registration.php';
		include_once __DIR__ . '/exceptions/class-add-payment-method-exception.php';
		include_once __DIR__ . '/exceptions/class-intent-authentication-exception.php';
		include_once __DIR__ . '/exceptions/class-invalid-payment-method-exception.php';
		include_once __DIR__ . '/exceptions/class-process-payment-exception.php';
		include_once __DIR__ . '/compat/class-wc-payment-woo-compat-utils.php';
		include_once __DIR__ . '/constants/class-payment-type.php';
		include_once __DIR__ . '/constants/class-payment-initiated-by.php';
		include_once __DIR__ . '/constants/class-payment-capture-type.php';
		include_once __DIR__ . '/constants/class-payment-method.php';
		include_once __DIR__ . '/constants/class-digital-wallets-locations.php';
		include_once __DIR__ . '/class-payment-information.php';
		require_once __DIR__ . '/notes/class-wc-payments-remote-note-service.php';
		include_once __DIR__ . '/class-wc-payments-action-scheduler-service.php';
		include_once __DIR__ . '/class-wc-payments-fraud-service.php';

		// Always load tracker to avoid class not found errors.
		include_once WCPAY_ABSPATH . 'includes/admin/tracks/class-tracker.php';

		self::$account                  = new WC_Payments_Account( self::$api_client );
		self::$customer_service         = new WC_Payments_Customer_Service( self::$api_client, self::$account );
		self::$token_service            = new WC_Payments_Token_Service( self::$api_client, self::$customer_service );
		self::$remote_note_service      = new WC_Payments_Remote_Note_Service( WC_Data_Store::load( 'admin-note' ) );
		self::$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( self::$api_client );
		self::$fraud_service            = new WC_Payments_Fraud_Service( self::$api_client, self::$customer_service, self::$account );

		$gateway_class         = CC_Payment_Gateway::class;
		$giropay_class         = Giropay_Payment_Gateway::class;
		$sepa_class            = Sepa_Payment_Gateway::class;
		$sofort_class          = Sofort_Payment_Gateway::class;
		$digital_wallets_class = Digital_Wallets_Payment_Gateway::class;

		// TODO: Remove admin payment method JS hack for Subscriptions <= 3.0.7 when we drop support for those versions.
		if ( class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' ) ) {
			include_once __DIR__ . '/compat/subscriptions/class-wc-payment-gateway-wcpay-subscriptions-compat.php';
			$gateway_class = 'WC_Payment_Gateway_WCPay_Subscriptions_Compat';
		} elseif ( WC_Payments_Features::is_upe_enabled() ) {
			$gateway_class = UPE_Payment_Gateway::class;
		}

		self::$card_gateway = new $gateway_class( self::$api_client, self::$account, self::$customer_service, self::$token_service, self::$action_scheduler_service );
		if ( WC_Payments_Features::is_giropay_enabled() ) {
			self::$giropay_gateway = new $giropay_class( self::$api_client, self::$account, self::$customer_service, self::$token_service, self::$action_scheduler_service );
		}
		if ( WC_Payments_Features::is_sepa_enabled() ) {
			self::$sepa_gateway = new $sepa_class( self::$api_client, self::$account, self::$customer_service, self::$token_service, self::$action_scheduler_service );
		}
		if ( WC_Payments_Features::is_sofort_enabled() ) {
			self::$sofort_gateway = new $sofort_class( self::$api_client, self::$account, self::$customer_service, self::$token_service, self::$action_scheduler_service );
		}
		if ( WC_Payments_Features::is_grouped_settings_enabled() || ( defined( 'WCPAY_TEST_ENV' ) && WCPAY_TEST_ENV ) ) {
			self::$digital_wallets_gateway = new $digital_wallets_class( self::$api_client, self::$account, self::$customer_service, self::$token_service, self::$action_scheduler_service );
		}

		// Payment Request and Apple Pay.
		self::$payment_request_button_handler = new WC_Payments_Payment_Request_Button_Handler( self::$account );
		self::$apple_pay_registration         = new WC_Payments_Apple_Pay_Registration( self::$api_client, self::$account );

		add_filter( 'woocommerce_payment_gateways', [ __CLASS__, 'register_gateway' ] );
		add_filter( 'option_woocommerce_gateway_order', [ __CLASS__, 'set_gateway_top_of_list' ], 2 );
		add_filter( 'default_option_woocommerce_gateway_order', [ __CLASS__, 'set_gateway_top_of_list' ], 3 );
		add_filter( 'default_option_woocommerce_gateway_order', [ __CLASS__, 'replace_wcpay_gateway_with_payment_methods' ], 4 );

		// Priority 5 so we can manipulate the registered gateways before they are shown.
		add_action( 'woocommerce_admin_field_payment_gateways', [ __CLASS__, 'hide_gateways_on_settings_page' ], 5 );

		include_once WCPAY_ABSPATH . '/includes/class-wc-payments-translations-loader.php';
		WC_Payments_Translations_Loader::init();

		// Add admin screens.
		if ( is_admin() ) {
			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-payments-admin.php';
			new WC_Payments_Admin( self::$api_client, self::$card_gateway, self::$account );

			// Use tracks loader only in admin screens because it relies on WC_Tracks loaded by WC_Admin.
			include_once WCPAY_ABSPATH . 'includes/admin/tracks/tracks-loader.php';

			if ( WC_Payments_Features::is_grouped_settings_enabled() ) {
				include_once __DIR__ . '/admin/class-wc-payments-admin-sections-overwrite.php';
				new WC_Payments_Admin_Sections_Overwrite();

				include_once __DIR__ . '/admin/class-wc-payments-admin-additional-methods-setup.php';
				new WC_Payments_Admin_Additional_Methods_Setup( self::$card_gateway );
			}
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

		// Check if WooCommerce is installed and active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			if ( ! $silent ) {
				$message = WC_Payments_Utils::esc_interpolated_html(
					__( 'WooCommerce Payments requires <a>WooCommerce</a> to be installed and active.', 'woocommerce-payments' ),
					[ 'a' => '<a href="https://wordpress.org/plugins/woocommerce">' ]
				);

				if ( current_user_can( 'install_plugins' ) ) {
					if ( is_wp_error( validate_plugin( 'woocommerce/woocommerce.php' ) ) ) {
						// WooCommerce is not installed.
						$activate_url  = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' );
						$activate_text = __( 'Install WooCommerce', 'woocommerce-payments' );
					} else {
						// WooCommerce is installed, so it just needs to be enabled.
						$activate_url  = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ), 'activate-plugin_woocommerce/woocommerce.php' );
						$activate_text = __( 'Activate WooCommerce', 'woocommerce-payments' );
					}
					$message .= ' <a href="' . $activate_url . '">' . $activate_text . '</a>';
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

				// Let's assume for now that any WC-Admin version bundled with WooCommerce will meet our minimum requirements.
				$message .= ' ' . __( 'There is a newer version of WooCommerce Admin bundled with WooCommerce.', 'woocommerce-payments' );
				if ( current_user_can( 'deactivate_plugins' ) ) {
					$deactivate_url = wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=woocommerce-admin/woocommerce-admin.php' ), 'deactivate-plugin_woocommerce-admin/woocommerce-admin.php' );
					$message       .= ' <a href="' . $deactivate_url . '">' . __( 'Use the bundled version of WooCommerce Admin', 'woocommerce-payments' ) . '</a>';
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
		$gateways[] = self::$card_gateway;

		if ( WC_Payments_Features::is_giropay_enabled() ) {
			$gateways[] = self::$giropay_gateway;
		}
		if ( WC_Payments_Features::is_sepa_enabled() ) {
			$gateways[] = self::$sepa_gateway;
		}
		if ( WC_Payments_Features::is_sofort_enabled() ) {
			$gateways[] = self::$sofort_gateway;
		}
		if ( WC_Payments_Features::is_grouped_settings_enabled() ) {
			$gateways[] = self::$digital_wallets_gateway;
		}

		return $gateways;
	}

	/**
	 * Called on Payments setting page.
	 *
	 * Remove all WCPay gateways except CC one. Comparison is done against
	 * $self::card_gateway because it should be the same instance as
	 * registered with WooCommerce and class can change depending on
	 * environment (see `init` method where $card_gateway is set).
	 */
	public static function hide_gateways_on_settings_page() {
		foreach ( WC()->payment_gateways->payment_gateways as $index => $payment_gateway ) {
			if ( $payment_gateway instanceof WC_Payment_Gateway_WCPay && $payment_gateway !== self::$card_gateway ) {
				unset( WC()->payment_gateways->payment_gateways[ $index ] );
			}
		}
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
		$id       = self::$card_gateway->id;
		// Only tweak the ordering if the list hasn't been reordered with WooCommerce Payments in it already.
		if ( ! isset( $ordering[ $id ] ) || ! is_numeric( $ordering[ $id ] ) ) {
			$ordering[ $id ] = empty( $ordering ) ? 0 : ( min( $ordering ) - 1 );
		}
		return $ordering;
	}

	/**
	 * Replace the main WCPay gateway with all WCPay payment methods
	 * when retrieving the "woocommerce_gateway_order" option.
	 *
	 * @param array $ordering Gateway order.
	 *
	 * @return array
	 */
	public static function replace_wcpay_gateway_with_payment_methods( $ordering ) {
		$ordering    = (array) $ordering;
		$wcpay_index = array_search(
			self::get_gateway()->id,
			array_keys( $ordering ),
			true
		);

		if ( false === $wcpay_index ) {
			// The main WCPay gateway isn't on the list.
			return $ordering;
		}

		$method_order = self::get_gateway()->get_option( 'payment_method_order', [] );

		if ( empty( $method_order ) ) {
			return $ordering;
		}

		$ordering = array_keys( $ordering );

		array_splice( $ordering, $wcpay_index, 1, $method_order );
		return array_flip( $ordering );
	}

	/**
	 * Create the API client.
	 *
	 * @return WC_Payments_API_Client
	 */
	public static function create_api_client() {
		require_once __DIR__ . '/wc-payment-api/models/class-wc-payments-api-charge.php';
		require_once __DIR__ . '/wc-payment-api/models/class-wc-payments-api-intention.php';
		require_once __DIR__ . '/wc-payment-api/class-wc-payments-api-client.php';

		$http_class = self::get_wc_payments_http();

		$api_client_class = apply_filters( 'wc_payments_api_client', 'WC_Payments_API_Client' );

		if ( ! is_subclass_of( $api_client_class, 'WC_Payments_API_Client' ) ) {
			$api_client_class = 'WC_Payments_API_Client';
		}

		return new $api_client_class(
			'WooCommerce Payments/' . WCPAY_VERSION_NUMBER,
			$http_class,
			self::$db_helper
		);
	}

	/**
	 * Create the HTTP instantiation.
	 *
	 * @return WC_Payments_Http_Interface
	 */
	private static function get_wc_payments_http() {
		require_once __DIR__ . '/wc-payment-api/class-wc-payments-http-interface.php';
		require_once __DIR__ . '/wc-payment-api/class-wc-payments-http.php';

		$http_class = apply_filters( 'wc_payments_http', null );

		if ( ! $http_class instanceof WC_Payments_Http_Interface ) {
			$http_class = new WC_Payments_Http( new Automattic\Jetpack\Connection\Manager( 'woocommerce-payments' ) );
		}

		return $http_class;
	}

	/**
	 * Initialize the REST API controllers.
	 */
	public static function init_rest_api() {
		include_once WCPAY_ABSPATH . 'includes/exceptions/class-rest-request-exception.php';
		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-payments-rest-controller.php';

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-accounts-controller.php';
		$accounts_controller = new WC_REST_Payments_Accounts_Controller( self::$api_client );
		$accounts_controller->register_routes();

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

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-connection-tokens-controller.php';
		$conn_tokens_controller = new WC_REST_Payments_Connection_Tokens_Controller( self::$api_client, self::$card_gateway, self::$account );
		$conn_tokens_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-orders-controller.php';
		$orders_controller = new WC_REST_Payments_Orders_Controller( self::$api_client, self::$card_gateway );
		$orders_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-timeline-controller.php';
		$timeline_controller = new WC_REST_Payments_Timeline_Controller( self::$api_client );
		$timeline_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-webhook-controller.php';
		$webhook_controller = new WC_REST_Payments_Webhook_Controller( self::$api_client, self::$db_helper, self::$account, self::$remote_note_service );
		$webhook_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-tos-controller.php';
		$tos_controller = new WC_REST_Payments_Tos_Controller( self::$api_client, self::$card_gateway, self::$account );
		$tos_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-settings-controller.php';
		$settings_controller = new WC_REST_Payments_Settings_Controller( self::$api_client, self::$card_gateway, self::$digital_wallets_gateway );
		$settings_controller->register_routes();
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
		return self::$card_gateway;
	}

	/**
	 * Returns the WC_Payments_Account instance
	 *
	 * @return WC_Payments_Account account service instance
	 */
	public static function get_account_service() {
		return self::$account;
	}

	/**
	 * Registers the payment method with the blocks registry.
	 *
	 * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry The registry.
	 */
	public static function register_checkout_gateway( $payment_method_registry ) {
		require_once __DIR__ . '/class-wc-payments-blocks-payment-method.php';

		$payment_method_registry->register( new WC_Payments_Blocks_Payment_Method() );
	}

	/**
	 * Handles upgrade routines.
	 */
	public static function install_actions() {
		if ( version_compare( WCPAY_VERSION_NUMBER, get_option( 'woocommerce_woocommerce_payments_version' ), '>' ) ) {
			do_action( 'woocommerce_woocommerce_payments_updated' );
			self::update_plugin_version();
		}
	}

	/**
	 * Updates the plugin version in db.
	 */
	public static function update_plugin_version() {
		update_option( 'woocommerce_woocommerce_payments_version', WCPAY_VERSION_NUMBER );
	}

	/**
	 * Adds WCPay notes to the WC-Admin inbox.
	 */
	public static function add_woo_admin_notes() {
		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-up-refund-policy.php';
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-qualitative-feedback.php';
			WC_Payments_Notes_Qualitative_Feedback::possibly_add_note();
			WC_Payments_Notes_Set_Up_Refund_Policy::possibly_add_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-https-for-checkout.php';
			WC_Payments_Notes_Set_Https_For_Checkout::possibly_add_note();
		}
	}

	/**
	 * Removes WCPay notes from the WC-Admin inbox.
	 */
	public static function remove_woo_admin_notes() {
		self::$remote_note_service->delete_notes();

		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-up-refund-policy.php';
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-qualitative-feedback.php';
			WC_Payments_Notes_Qualitative_Feedback::possibly_delete_note();
			WC_Payments_Notes_Set_Up_Refund_Policy::possibly_delete_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-https-for-checkout.php';
			WC_Payments_Notes_Set_Https_For_Checkout::possibly_delete_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-instant-deposits-eligible.php';
			WC_Payments_Notes_Instant_Deposits_Eligible::possibly_delete_note();
		}
	}

	/**
	 * Filter to check if WCPay should operate as usual (the customer can save payment methods at checkout and those payment methods
	 * will only be used on this site), or if saved cards should be available for all the sites on the multisite network.
	 *
	 * NOTE: DON'T USE THIS FILTER. Everything will break. At this moment, it's only intended to be used internally by Automattic.
	 *
	 * @return bool Normal WCPay behavior (false, default) or TRUE if the site should only use network-wide saved payment methods.
	 */
	public static function is_network_saved_cards_enabled() {
		return apply_filters( 'wcpay_force_network_saved_cards', false );
	}

}

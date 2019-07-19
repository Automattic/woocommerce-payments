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
	 * Entry point to the initialization logic.
	 */
	public static function init() {
		include_once dirname( __FILE__ ) . '/class-wc-payments-dependencies-validator.php';
		$dependencies_validator = new WC_Payments_Dependencies_Validator();
		if ( ! $dependencies_validator->check_plugin_dependencies( true ) ) {
			add_filter( 'admin_notices', array( $dependencies_validator, 'check_plugin_dependencies' ) );
			return;
		}

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
		}

		add_action( 'rest_api_init', array( __CLASS__, 'init_rest_api' ) );
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
}

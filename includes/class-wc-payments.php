<?php
/**
 * Class WC_Payments
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\StoreApi\RoutesController;
use WCPay\Logger;
use WCPay\Migrations\Allowed_Payment_Request_Button_Types_Update;
use WCPay\Payment_Methods\CC_Payment_Gateway;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Bancontact_Payment_Method;
use WCPay\Payment_Methods\Becs_Payment_Method;
use WCPay\Payment_Methods\Giropay_Payment_Method;
use WCPay\Payment_Methods\P24_Payment_Method;
use WCPay\Payment_Methods\Sepa_Payment_Method;
use WCPay\Payment_Methods\Sofort_Payment_Method;
use WCPay\Payment_Methods\UPE_Payment_Gateway;
use WCPay\Payment_Methods\Ideal_Payment_Method;
use WCPay\Payment_Methods\Eps_Payment_Method;
use WCPay\Platform_Checkout_Tracker;
use WCPay\Platform_Checkout\Platform_Checkout_Utilities;
use WCPay\Platform_Checkout\Platform_Checkout_Order_Status_Sync;
use WCPay\Payment_Methods\Link_Payment_Method;
use WCPay\Session_Rate_Limiter;
use WCPay\Database_Cache;

/**
 * Main class for the WooCommerce Payments extension. Its responsibility is to initialize the extension.
 */
class WC_Payments {
	/**
	 * Instance of WC_Payment_Gateway_WCPay, created in init function.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private static $card_gateway;

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
	 * Instance of WC_Payments_Localization_Service, created in init function
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private static $localization_service;

	/**
	 * Instance of WC_Payments_Dependency_Service, created in init function
	 *
	 * @var WC_Payments_Dependency_Service
	 */
	private static $dependency_service;

	/**
	 * Instance of WC_Payments_Fraud_Service, created in init function
	 *
	 * @var WC_Payments_Fraud_Service
	 */
	private static $fraud_service;

	/**
	 * Instance of WC_Payments_In_Person_Payments_Receipts_Service, created in init function
	 *
	 * @var WC_Payments_In_Person_Payments_Receipts_Service
	 */
	private static $in_person_payments_receipts_service;

	/**
	 * Instance of WC_Payments_Order_Service, created in init function
	 *
	 * @var WC_Payments_Order_Service
	 */
	private static $order_service;

	/**
	 * Instance of WC_Payments_Order_Success_Page, created in init function
	 *
	 * @var WC_Payments_Order_Success_Page
	 */
	private static $order_success_page;

	/**
	 * Instance of WC_Payments_Onboarding_Service, created in init function
	 *
	 * @var WC_Payments_Onboarding_Service
	 */
	private static $onboarding_service;

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
	 * Instance of Session_Rate_Limiter to limit failed transactions
	 *
	 * @var Session_Rate_Limiter
	 */
	private static $failed_transaction_rate_limiter;

	/**
	 * Instance of Database_Cache utils
	 *
	 * @var Database_Cache
	 */
	private static $database_cache;

	/**
	 * Cache for plugin headers to avoid multiple calls to get_file_data
	 *
	 * @var array
	 */
	private static $plugin_headers = null;

	/**
	 * Instance of WC_Payments_Webhook_Processing_Service to process webhook data.
	 *
	 * @var WC_Payments_Webhook_Processing_Service
	 */
	private static $webhook_processing_service;

	/**
	 * Instance of WC_Payments_Webhook_Reliability_Service, created in init function
	 *
	 * @var WC_Payments_Webhook_Reliability_Service
	 */
	private static $webhook_reliability_service;

	/**
	 * Entry point to the initialization logic.
	 */
	public static function init() {
		define( 'WCPAY_VERSION_NUMBER', self::get_plugin_headers()['Version'] );

		include_once __DIR__ . '/class-wc-payments-utils.php';

		include_once __DIR__ . '/class-database-cache.php';
		self::$database_cache = new Database_Cache();

		include_once __DIR__ . '/class-wc-payments-dependency-service.php';

		self::$dependency_service = new WC_Payments_Dependency_Service();

		if ( false === self::$dependency_service->has_valid_dependencies() ) {
			return;
		}

		add_action( 'admin_init', [ __CLASS__, 'add_woo_admin_notes' ] );
		add_action( 'init', [ __CLASS__, 'install_actions' ] );

		add_filter( 'plugin_action_links_' . plugin_basename( WCPAY_PLUGIN_FILE ), [ __CLASS__, 'add_plugin_links' ] );
		add_action( 'woocommerce_blocks_payment_method_type_registration', [ __CLASS__, 'register_checkout_gateway' ] );

		include_once __DIR__ . '/class-wc-payments-db.php';
		self::$db_helper = new WC_Payments_DB();

		include_once __DIR__ . '/exceptions/class-base-exception.php';
		include_once __DIR__ . '/exceptions/class-api-exception.php';
		include_once __DIR__ . '/exceptions/class-connection-exception.php';

		self::$api_client = self::create_api_client();

		include_once __DIR__ . '/compat/subscriptions/trait-wc-payments-subscriptions-utilities.php';
		include_once __DIR__ . '/compat/subscriptions/trait-wc-payment-gateway-wcpay-subscriptions.php';
		include_once __DIR__ . '/class-wc-payments-account.php';
		include_once __DIR__ . '/class-wc-payments-customer-service.php';
		include_once __DIR__ . '/class-logger.php';
		include_once __DIR__ . '/class-session-rate-limiter.php';
		include_once __DIR__ . '/class-wc-payment-gateway-wcpay.php';
		include_once __DIR__ . '/payment-methods/class-cc-payment-gateway.php';
		include_once __DIR__ . '/payment-methods/class-upe-payment-gateway.php';
		include_once __DIR__ . '/payment-methods/class-upe-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-cc-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-bancontact-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-sepa-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-giropay-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-p24-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-sofort-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-ideal-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-becs-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-eps-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-link-payment-method.php';
		include_once __DIR__ . '/class-wc-payment-token-wcpay-sepa.php';
		include_once __DIR__ . '/class-wc-payments-status.php';
		include_once __DIR__ . '/class-wc-payments-token-service.php';
		include_once __DIR__ . '/class-wc-payments-payment-request-button-handler.php';
		include_once __DIR__ . '/class-wc-payments-apple-pay-registration.php';
		include_once __DIR__ . '/exceptions/class-add-payment-method-exception.php';
		include_once __DIR__ . '/exceptions/class-amount-too-small-exception.php';
		include_once __DIR__ . '/exceptions/class-intent-authentication-exception.php';
		include_once __DIR__ . '/exceptions/class-invalid-payment-method-exception.php';
		include_once __DIR__ . '/exceptions/class-process-payment-exception.php';
		include_once __DIR__ . '/exceptions/class-invalid-webhook-data-exception.php';
		include_once __DIR__ . '/compat/class-wc-payment-woo-compat-utils.php';
		include_once __DIR__ . '/constants/class-payment-type.php';
		include_once __DIR__ . '/constants/class-payment-initiated-by.php';
		include_once __DIR__ . '/constants/class-payment-capture-type.php';
		include_once __DIR__ . '/constants/class-payment-method.php';
		include_once __DIR__ . '/class-payment-information.php';
		require_once __DIR__ . '/notes/class-wc-payments-remote-note-service.php';
		include_once __DIR__ . '/class-wc-payments-action-scheduler-service.php';
		include_once __DIR__ . '/class-wc-payments-fraud-service.php';
		include_once __DIR__ . '/class-wc-payments-onboarding-service.php';
		include_once __DIR__ . '/class-experimental-abtest.php';
		include_once __DIR__ . '/class-wc-payments-localization-service.php';
		include_once __DIR__ . '/in-person-payments/class-wc-payments-in-person-payments-receipts-service.php';
		include_once __DIR__ . '/class-wc-payments-order-service.php';
		include_once __DIR__ . '/class-wc-payments-order-success-page.php';
		include_once __DIR__ . '/class-wc-payments-file-service.php';
		include_once __DIR__ . '/class-wc-payments-webhook-processing-service.php';
		include_once __DIR__ . '/class-wc-payments-webhook-reliability-service.php';
		include_once __DIR__ . '/fraud-prevention/class-fraud-prevention-service.php';
		include_once __DIR__ . '/fraud-prevention/class-buyer-fingerprinting-service.php';
		include_once __DIR__ . '/platform-checkout/class-platform-checkout-utilities.php';
		include_once __DIR__ . '/platform-checkout/class-platform-checkout-order-status-sync.php';

		// Load customer multi-currency if feature is enabled.
		if ( WC_Payments_Features::is_customer_multi_currency_enabled() ) {
			include_once __DIR__ . '/multi-currency/wc-payments-multi-currency.php';
		}

		// // Load platform checkout save user section if feature is enabled.
		add_action( 'woocommerce_cart_loaded_from_session', [ __CLASS__, 'init_platform_checkout' ] );

		// Init the email template for In Person payment receipt email. We need to do it before passing the mailer to the service.
		add_filter( 'woocommerce_email_classes', [ __CLASS__, 'add_ipp_emails' ], 10 );

		// Always load tracker to avoid class not found errors.
		include_once WCPAY_ABSPATH . 'includes/admin/tracks/class-tracker.php';

		self::$action_scheduler_service            = new WC_Payments_Action_Scheduler_Service( self::$api_client );
		self::$account                             = new WC_Payments_Account( self::$api_client, self::$database_cache, self::$action_scheduler_service );
		self::$customer_service                    = new WC_Payments_Customer_Service( self::$api_client, self::$account, self::$database_cache );
		self::$token_service                       = new WC_Payments_Token_Service( self::$api_client, self::$customer_service );
		self::$remote_note_service                 = new WC_Payments_Remote_Note_Service( WC_Data_Store::load( 'admin-note' ) );
		self::$fraud_service                       = new WC_Payments_Fraud_Service( self::$api_client, self::$customer_service, self::$account );
		self::$in_person_payments_receipts_service = new WC_Payments_In_Person_Payments_Receipts_Service();
		self::$localization_service                = new WC_Payments_Localization_Service();
		self::$failed_transaction_rate_limiter     = new Session_Rate_Limiter( Session_Rate_Limiter::SESSION_KEY_DECLINED_CARD_REGISTRY, 5, 10 * MINUTE_IN_SECONDS );
		self::$order_service                       = new WC_Payments_Order_Service( self::$api_client );
		self::$order_success_page                  = new WC_Payments_Order_Success_Page();
		self::$onboarding_service                  = new WC_Payments_Onboarding_Service( self::$api_client, self::$database_cache );

		$card_class = CC_Payment_Gateway::class;
		$upe_class  = UPE_Payment_Gateway::class;

		if ( WC_Payments_Features::is_upe_enabled() ) {
			$payment_methods        = [];
			$payment_method_classes = [
				CC_Payment_Method::class,
				Bancontact_Payment_Method::class,
				Sepa_Payment_Method::class,
				Giropay_Payment_Method::class,
				Sofort_Payment_Method::class,
				P24_Payment_Method::class,
				Ideal_Payment_Method::class,
				Becs_Payment_Method::class,
				Eps_Payment_Method::class,
				Link_Payment_Method::class,
			];
			foreach ( $payment_method_classes as $payment_method_class ) {
				$payment_method                               = new $payment_method_class( self::$token_service );
				$payment_methods[ $payment_method->get_id() ] = $payment_method;
			}
			self::$card_gateway = new $upe_class( self::$api_client, self::$account, self::$customer_service, self::$token_service, self::$action_scheduler_service, $payment_methods, self::$failed_transaction_rate_limiter, self::$order_service );
		} else {
			self::$card_gateway = new $card_class( self::$api_client, self::$account, self::$customer_service, self::$token_service, self::$action_scheduler_service, self::$failed_transaction_rate_limiter, self::$order_service );
		}

		self::$webhook_processing_service  = new WC_Payments_Webhook_Processing_Service( self::$api_client, self::$db_helper, self::$account, self::$remote_note_service, self::$order_service, self::$in_person_payments_receipts_service, self::$card_gateway, self::$customer_service, self::$database_cache );
		self::$webhook_reliability_service = new WC_Payments_Webhook_Reliability_Service( self::$api_client, self::$action_scheduler_service, self::$webhook_processing_service );

		self::maybe_register_platform_checkout_hooks();

		// Payment Request and Apple Pay.
		self::$payment_request_button_handler = new WC_Payments_Payment_Request_Button_Handler( self::$account, self::$card_gateway );
		self::$apple_pay_registration         = new WC_Payments_Apple_Pay_Registration( self::$api_client, self::$account, self::get_gateway() );

		add_filter( 'woocommerce_payment_gateways', [ __CLASS__, 'register_gateway' ] );
		add_filter( 'option_woocommerce_gateway_order', [ __CLASS__, 'set_gateway_top_of_list' ], 2 );
		add_filter( 'default_option_woocommerce_gateway_order', [ __CLASS__, 'set_gateway_top_of_list' ], 3 );
		add_filter( 'default_option_woocommerce_gateway_order', [ __CLASS__, 'replace_wcpay_gateway_with_payment_methods' ], 4 );
		add_filter( 'woocommerce_admin_get_user_data_fields', [ __CLASS__, 'add_user_data_fields' ] );

		// Add note query support for source.
		add_filter( 'woocommerce_rest_notes_object_query', [ __CLASS__, 'possibly_add_source_to_notes_query' ], 10, 2 );
		add_filter( 'woocommerce_note_where_clauses', [ __CLASS__, 'possibly_add_note_source_where_clause' ], 10, 2 );

		// Priority 5 so we can manipulate the registered gateways before they are shown.
		add_action( 'woocommerce_admin_field_payment_gateways', [ __CLASS__, 'hide_gateways_on_settings_page' ], 5 );

		require_once __DIR__ . '/migrations/class-allowed-payment-request-button-types-update.php';
		require_once __DIR__ . '/migrations/class-update-service-data-from-server.php';
		require_once __DIR__ . '/migrations/class-track-upe-status.php';
		add_action( 'woocommerce_woocommerce_payments_updated', [ new Allowed_Payment_Request_Button_Types_Update( self::get_gateway() ), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Update_Service_Data_From_Server( self::get_account_service() ), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ '\WCPay\Migrations\Track_Upe_Status', 'maybe_track' ] );

		include_once WCPAY_ABSPATH . '/includes/class-wc-payments-explicit-price-formatter.php';
		WC_Payments_Explicit_Price_Formatter::init();

		include_once WCPAY_ABSPATH . '/includes/class-wc-payments-captured-event-note.php';

		// Add admin screens.
		if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-payments-admin.php';
			new WC_Payments_Admin( self::$api_client, self::$card_gateway, self::$account, self::$database_cache );

			// Use tracks loader only in admin screens because it relies on WC_Tracks loaded by WC_Admin.
			include_once WCPAY_ABSPATH . 'includes/admin/tracks/tracks-loader.php';

			include_once __DIR__ . '/admin/class-wc-payments-admin-sections-overwrite.php';
			new WC_Payments_Admin_Sections_Overwrite( self::get_account_service() );

			new WC_Payments_Status( self::get_wc_payments_http(), self::get_account_service() );
		}

		// Load WCPay Subscriptions.
		if ( WC_Payments_Features::is_wcpay_subscriptions_enabled() ) {
			include_once WCPAY_ABSPATH . '/includes/subscriptions/class-wc-payments-subscriptions.php';
			WC_Payments_Subscriptions::init( self::$api_client, self::$customer_service, self::$card_gateway, self::$account );
		}

		add_action( 'rest_api_init', [ __CLASS__, 'init_rest_api' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ __CLASS__, 'set_plugin_activation_timestamp' ] );
	}

	/**
	 * Adds IPP Email template to WooCommerce emails.
	 *
	 * @param array $email_classes the email classes.
	 * @return array
	 */
	public static function add_ipp_emails( array $email_classes ): array {
		$email_classes['WC_Payments_Email_IPP_Receipt'] = include __DIR__ . '/emails/class-wc-payments-email-ipp-receipt.php';
		return $email_classes;
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

					'RequiresWP' => 'Requires at least',
					'Version'    => 'Version',
				]
			);
		}
		return self::$plugin_headers;
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
	 * Adds fields so that we can store inbox notifications last read and open times.
	 *
	 * @param array $user_data_fields User data fields.
	 * @return array
	 */
	public static function add_user_data_fields( $user_data_fields ) {
		return array_merge(
			$user_data_fields,
			[ 'wc_payments_overview_inbox_last_read' ]
		);
	}

	/**
	 * By default, new payment gateways are put at the bottom of the list on the admin "Payments" settings screen.
	 * For visibility, we want WooCommerce Payments to be at the top of the list.
	 * NOTE: this can be removed after WC version 5.6, when the api supports the use of source.
	 * https://github.com/woocommerce/woocommerce-admin/pull/6979
	 *
	 * @param array           $args Existing ordering of the payment gateways.
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return array Modified ordering.
	 */
	public static function possibly_add_source_to_notes_query( $args, $request ) {
		if ( isset( $request['source'] ) && ! isset( $args['source'] ) ) {
			return array_merge(
				$args,
				[
					'source' => wp_parse_list( $request['source'] ),
				]
			);
		}
		return $args;
	}

	/**
	 * Adds source where clause to note query.
	 * NOTE: this can be removed after WC version 5.6, when the api supports the use of source.
	 * https://github.com/woocommerce/woocommerce-admin/pull/6979
	 *
	 * @param string $where_clauses Existing ordering of the payment gateways.
	 * @param array  $args Full details about the request.
	 *
	 * @return string Modified where clause.
	 */
	public static function possibly_add_note_source_where_clause( $where_clauses, $args ) {
		if ( ! empty( $args['source'] ) && false === strpos( $where_clauses, 'AND source IN' ) ) {
			$where_source_array = [];
			foreach ( $args['source'] as $args_type ) {
				$args_type            = trim( $args_type );
				$where_source_array[] = "'" . esc_sql( $args_type ) . "'";
			}
			$escaped_where_source = implode( ',', $where_source_array );
			$where_clauses       .= " AND source IN ($escaped_where_source)";
		}
		return $where_clauses;
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

		$api_client_class = apply_filters( 'wc_payments_api_client', WC_Payments_API_Client::class );
		if ( ! class_exists( $api_client_class ) || ! is_subclass_of( $api_client_class, 'WC_Payments_API_Client' ) ) {
			$api_client_class = WC_Payments_API_Client::class;
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
		$orders_controller = new WC_REST_Payments_Orders_Controller( self::$api_client, self::$card_gateway, self::$customer_service, self::$order_service );
		$orders_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-timeline-controller.php';
		$timeline_controller = new WC_REST_Payments_Timeline_Controller( self::$api_client );
		$timeline_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-webhook-controller.php';
		$webhook_controller = new WC_REST_Payments_Webhook_Controller( self::$api_client, self::$webhook_processing_service );
		$webhook_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-tos-controller.php';
		$tos_controller = new WC_REST_Payments_Tos_Controller( self::$api_client, self::$card_gateway, self::$account );
		$tos_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-terminal-locations-controller.php';
		$accounts_controller = new WC_REST_Payments_Terminal_Locations_Controller( self::$api_client );
		$accounts_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-settings-controller.php';
		$settings_controller = new WC_REST_Payments_Settings_Controller( self::$api_client, self::$card_gateway );
		$settings_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-reader-controller.php';
		$charges_controller = new WC_REST_Payments_Reader_Controller( self::$api_client, self::$card_gateway, self::$in_person_payments_receipts_service );
		$charges_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-files-controller.php';
		$files_controller = new WC_REST_Payments_Files_Controller( self::$api_client );
		$files_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-capital-controller.php';
		$capital_controller = new WC_REST_Payments_Capital_Controller( self::$api_client );
		$capital_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-onboarding-controller.php';
		$onboarding_controller = new WC_REST_Payments_Onboarding_Controller( self::$api_client, self::$onboarding_service );
		$onboarding_controller->register_routes();

		if ( WC_Payments_Features::is_upe_settings_preview_enabled() ) {
			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-upe-flag-toggle-controller.php';
			$upe_flag_toggle_controller = new WC_REST_UPE_Flag_Toggle_Controller( self::get_gateway() );
			$upe_flag_toggle_controller->register_routes();

			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-survey-controller.php';
			$survey_controller = new WC_REST_Payments_Survey_Controller( self::get_wc_payments_http() );
			$survey_controller->register_routes();
		}

		if ( WC_Payments_Features::is_documents_section_enabled() ) {
			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-documents-controller.php';
			$documents_controller = new WC_REST_Payments_Documents_Controller( self::$api_client );
			$documents_controller->register_routes();

			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-vat-controller.php';
			$vat_controller = new WC_REST_Payments_VAT_Controller( self::$api_client );
			$vat_controller->register_routes();
		}
	}

	/**
	 * Gets the file modified time as a cache buster if we're in dev mode, or the plugin version otherwise.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	public static function get_file_version( $file ): string {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( WCPAY_ABSPATH . $file ) ) {
			return (string) filemtime( WCPAY_ABSPATH . trim( $file, '/' ) );
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
	 * Returns the Database_Cache instance.
	 *
	 * @return Database_Cache Database_Cache instance.
	 */
	public static function get_database_cache(): Database_Cache {
		return self::$database_cache;
	}

	/**
	 * Sets the Database_Cache instance.
	 *
	 * @param Database_Cache $database_cache The cache instance.
	 */
	public static function set_database_cache( Database_Cache $database_cache ) {
		self::$database_cache = $database_cache;
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
	 * Sets the account service instance.
	 *
	 * @param WC_Payments_Account $account The account instance.
	 */
	public static function set_account_service( WC_Payments_Account $account ) {
		self::$account = $account;
	}

	/**
	 * Returns the WC_Payments_API_Client
	 *
	 * @return WC_Payments_API_Client API Client instance
	 */
	public static function get_payments_api_client() {
		return self::$api_client;
	}

	/**
	 * Returns the WC_Payments_Localization_Service
	 *
	 * @return WC_Payments_Localization_Service Localization Service instance
	 */
	public static function get_localization_service() {
		return self::$localization_service;
	}

	/**
	 * Returns the WC_Payments_Fraud_Service instance
	 *
	 * @return WC_Payments_Fraud_Service Fraud Service instance
	 */
	public static function get_fraud_service() {
		return self::$fraud_service;
	}

	/**
	 * Returns the WC_Payments_Customer_Service instance
	 *
	 * @return WC_Payments_Customer_Service  The Customer Service instance.
	 */
	public static function get_customer_service(): WC_Payments_Customer_Service {
		return self::$customer_service;
	}

	/**
	 * Sets the customer service instance. This is needed only for tests.
	 *
	 * @param WC_Payments_Customer_Service $customer_service_class Instance of WC_Payments_Customer_Service.
	 *
	 * @return void
	 */
	public static function set_customer_service( WC_Payments_Customer_Service $customer_service_class ) {
		self::$customer_service = $customer_service_class;
	}

	/**
	 * Registers the payment method with the blocks registry.
	 *
	 * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry The registry.
	 */
	public static function register_checkout_gateway( $payment_method_registry ) {
		require_once __DIR__ . '/class-wc-payments-blocks-payment-method.php';
		if ( WC_Payments_Features::is_upe_enabled() ) {
			require_once __DIR__ . '/class-wc-payments-upe-blocks-payment-method.php';
			$payment_method_registry->register( new WC_Payments_UPE_Blocks_Payment_Method() );
		} else {
			$payment_method_registry->register( new WC_Payments_Blocks_Payment_Method() );
		}

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
	 * Sets the plugin activation timestamp.
	 *
	 * Use add_option so that we don't overwrite the value.
	 */
	public static function set_plugin_activation_timestamp() {
		add_option( 'wcpay_activation_timestamp', time() );
	}

	/**
	 * Adds WCPay notes to the WC-Admin inbox.
	 */
	public static function add_woo_admin_notes() {
		// Do not try to add notes on ajax requests to improve their performance.
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-up-refund-policy.php';
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-qualitative-feedback.php';
			WC_Payments_Notes_Qualitative_Feedback::possibly_add_note();
			WC_Payments_Notes_Set_Up_Refund_Policy::possibly_add_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-https-for-checkout.php';
			WC_Payments_Notes_Set_Https_For_Checkout::possibly_add_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-additional-payment-methods.php';
			WC_Payments_Notes_Additional_Payment_Methods::set_account( self::get_account_service() );
			WC_Payments_Notes_Additional_Payment_Methods::possibly_add_note();
			WC_Payments_Notes_Additional_Payment_Methods::maybe_enable_upe_feature_flag();
		}
	}

	/**
	 * Removes WCPay notes from the WC-Admin inbox.
	 */
	public static function remove_woo_admin_notes() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			self::$remote_note_service->delete_notes();
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-up-refund-policy.php';
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-qualitative-feedback.php';
			WC_Payments_Notes_Qualitative_Feedback::possibly_delete_note();
			WC_Payments_Notes_Set_Up_Refund_Policy::possibly_delete_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-https-for-checkout.php';
			WC_Payments_Notes_Set_Https_For_Checkout::possibly_delete_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-instant-deposits-eligible.php';
			WC_Payments_Notes_Instant_Deposits_Eligible::possibly_delete_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-additional-payment-methods.php';
			WC_Payments_Notes_Additional_Payment_Methods::possibly_delete_note();
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

	/**
	 * Registers platform checkout hooks if the platform checkout feature flag is enabled.
	 *
	 * @return void
	 */
	public static function maybe_register_platform_checkout_hooks() {
		$is_platform_checkout_eligible = WC_Payments_Features::is_platform_checkout_eligible(); // Feature flag.
		$is_platform_checkout_enabled  = 'yes' === self::get_gateway()->get_option( 'platform_checkout', 'no' );

		if ( $is_platform_checkout_eligible && $is_platform_checkout_enabled ) {
			add_action( 'wc_ajax_wcpay_init_platform_checkout', [ __CLASS__, 'ajax_init_platform_checkout' ] );
			add_filter( 'determine_current_user', [ __CLASS__, 'determine_current_user_for_platform_checkout' ] );
			add_filter( 'woocommerce_cookie', [ __CLASS__, 'determine_session_cookie_for_platform_checkout' ] );

			// This injects the payments API and draft orders into core, so the WooCommerce Blocks plugin is not necessary.
			// We should remove this once both features are available by default in the WC minimum supported version.
			// - The payments API is currently only available in feature builds (with flag `WC_BLOCKS_IS_FEATURE_PLUGIN`).
			// - The Draft order status is available after WC blocks 7.5.0.
			if (
				! defined( 'WC_BLOCKS_IS_FEATURE_PLUGIN' ) &&
				class_exists( 'Automattic\WooCommerce\Blocks\Package' ) &&
				class_exists( 'Automattic\WooCommerce\Blocks\Payments\Api' )
			) {
				// Register payments API.
				$blocks_package_container = Automattic\WooCommerce\Blocks\Package::container();
				$blocks_package_container->register(
					Automattic\WooCommerce\Blocks\Payments\Api::class,
					function ( $container ) {
						$payment_method_registry = $container->get( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class );
						$asset_data_registry     = $container->get( Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class );
						return new Automattic\WooCommerce\Blocks\Payments\Api( $payment_method_registry, $asset_data_registry );
					}
				);
				$blocks_package_container->get( Automattic\WooCommerce\Blocks\Payments\Api::class );

				// Register draft orders.
				$draft_orders = $blocks_package_container->get( Automattic\WooCommerce\Blocks\Domain\Services\DraftOrders::class );

				add_filter( 'wc_order_statuses', [ $draft_orders, 'register_draft_order_status' ] );
				add_filter( 'woocommerce_register_shop_order_post_statuses', [ $draft_orders, 'register_draft_order_post_status' ] );
				add_filter( 'woocommerce_analytics_excluded_order_statuses', [ $draft_orders, 'append_draft_order_post_status' ] );
				add_filter( 'woocommerce_valid_order_statuses_for_payment', [ $draft_orders, 'append_draft_order_post_status' ] );
				add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', [ $draft_orders, 'append_draft_order_post_status' ] );
				// Hook into the query to retrieve My Account orders so draft status is excluded.
				add_action( 'woocommerce_my_account_my_orders_query', [ $draft_orders, 'delete_draft_order_post_status_from_args' ] );
				add_action( 'woocommerce_cleanup_draft_orders', [ $draft_orders, 'delete_expired_draft_orders' ] );
				add_action( 'admin_init', [ $draft_orders, 'install' ] );
			}

			new Platform_Checkout_Order_Status_Sync( self::$api_client );
		}
	}

	/**
	 * Used to initialize platform checkout session.
	 *
	 * @return void
	 */
	public static function ajax_init_platform_checkout() {
		$is_nonce_valid = check_ajax_referer( 'wcpay_init_platform_checkout_nonce', false, false );

		if ( ! $is_nonce_valid ) {
			wp_send_json_error(
				__( 'You aren’t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		$session_cookie_name = apply_filters( 'woocommerce_cookie', 'wp_woocommerce_session_' . COOKIEHASH );

		$email       = ! empty( $_POST['email'] ) ? wc_clean( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$user        = wp_get_current_user();
		$customer_id = self::$customer_service->get_customer_id_by_user_id( $user->ID );
		if ( null === $customer_id ) {
			// create customer.
			$customer_data = WC_Payments_Customer_Service::map_customer_data( null, new WC_Customer( $user->ID ) );
			$customer_id   = self::$customer_service->create_customer_for_user( $user, $customer_data );
		}

		$account_id = self::get_account_service()->get_stripe_account_id();

		$platform_checkout_host = defined( 'PLATFORM_CHECKOUT_HOST' ) ? PLATFORM_CHECKOUT_HOST : 'https://pay.woo.com';
		$url                    = $platform_checkout_host . '/wp-json/platform-checkout/v1/init';

		$store_logo = self::get_gateway()->get_option( 'platform_checkout_store_logo' );

		$body = [
			'wcpay_version'        => WCPAY_VERSION_NUMBER,
			'user_id'              => $user->ID,
			'customer_id'          => $customer_id,
			'session_nonce'        => wp_create_nonce( 'wc_store_api' ),
			'email'                => $email,
			'session_cookie_name'  => $session_cookie_name,
			'session_cookie_value' => wp_unslash( $_COOKIE[ $session_cookie_name ] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'store_data'           => [
				'store_name'        => get_bloginfo( 'name' ),
				'store_logo'        => ! empty( $store_logo ) ? add_query_arg( 'as_account', '0', get_rest_url( null, 'wc/v3/payments/file/' . $store_logo ) ) : '',
				'custom_message'    => self::get_gateway()->get_option( 'platform_checkout_custom_message' ),
				'blog_id'           => Jetpack_Options::get_option( 'id' ),
				'blog_url'          => get_site_url(),
				'blog_checkout_url' => wc_get_checkout_url(),
				'blog_shop_url'     => get_permalink( wc_get_page_id( 'shop' ) ),
				'store_api_url'     => self::get_store_api_url(),
				'account_id'        => $account_id,
				'test_mode'         => self::get_gateway()->is_in_test_mode(),
				'capture_method'    => empty( self::get_gateway()->get_option( 'manual_capture' ) ) || 'no' === self::get_gateway()->get_option( 'manual_capture' ) ? 'automatic' : 'manual',
			],
			'user_session'         => isset( $_REQUEST['user_session'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['user_session'] ) ) : null,
		];
		$args = [
			'url'     => $url,
			'method'  => 'POST',
			'timeout' => 30,
			'body'    => wp_json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		];

		/**
		 * Suppress psalm error from Jetpack Connection namespacing WP_Error.
		 *
		 * @psalm-suppress UndefinedDocblockClass
		 */
		$response = Automattic\Jetpack\Connection\Client::remote_request( $args, wp_json_encode( $body ) );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			Logger::error( 'HTTP_REQUEST_ERROR ' . var_export( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			// phpcs:ignore
			/**
			 * @psalm-suppress UndefinedDocblockClass
			 */
			$message = sprintf(
				// translators: %1: original error message.
				__( 'Http request failed. Reason: %1$s', 'woocommerce-payments' ),
				$response->get_error_message()
			);
			// Respond with same message platform would respond with on failure.
			$response_body_json = wp_json_encode( [ 'result' => 'failure' ] );
		} else {
			$response_body_json = wp_remote_retrieve_body( $response );
		}

		Logger::log( $response_body_json );
		wp_send_json( json_decode( $response_body_json ) );
	}

	/**
	 * Retrieves the Store API URL.
	 *
	 * @return string
	 */
	public static function get_store_api_url() {
		if ( class_exists( Package::class ) && class_exists( RoutesController::class ) ) {
			try {
				$cart          = Package::container()->get( RoutesController::class )->get( 'cart' );
				$store_api_url = method_exists( $cart, 'get_namespace' ) ? $cart->get_namespace() : 'wc/store';
			} catch ( Exception $e ) {
				$store_api_url = 'wc/store';
			}
		}

		return get_rest_url( null, $store_api_url ?? 'wc/store' );
	}

	/**
	 * Tells WC to use platform checkout session cookie if the header is present.
	 *
	 * @param string $cookie_hash Default cookie hash.
	 *
	 * @return string
	 */
	public static function determine_session_cookie_for_platform_checkout( $cookie_hash ) {
		if ( isset( $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] ) && 0 === (int) $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] ) {
			return 'platform_checkout_session';
		}
		return $cookie_hash;
	}

	/**
	 * Determine the current user
	 *
	 * @param WP_User|int $user The user to determine.
	 */
	public static function determine_current_user_for_platform_checkout( $user ) {
		if ( $user ) {
			return $user;
		}

		if ( ! isset( $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] ) || ! is_numeric( $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] ) ) {
			return $user;
		}

		return (int) $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'];
	}

	/**
	 * Adds custom email field.
	 */
	public static function platform_checkout_fields_before_billing_details() {
		$checkout = WC()->checkout;

		echo '<div class="woocommerce-billing-fields" id="contact_details">';

		echo '<h3>' . esc_html( __( 'Contact information', 'woocommerce-payments' ) ) . '</h3>';

		echo '<div class="woocommerce-billing-fields__field-wrapper">';
		woocommerce_form_field(
			'billing_email',
			[
				'type'        => 'email',
				'label'       => __( 'Email address', 'woocommerce-payments' ),
				'class'       => [ 'form-row-wide platform-checkout-billing-email' ],
				'input_class' => [ 'platform-checkout-billing-email-input' ],
				'validate'    => [ 'email' ],
				'required'    => true,
			],
			$checkout->get_value( 'billing_email' )
		);

		echo '</div>';
		echo '</div>';

		// Ensure WC Blocks styles are enqueued so the spinner will show.
		// This style is not enqueued be default when using a block theme and classic checkout.
		wp_enqueue_style( 'wc-blocks-style' );
	}

	/**
	 * Hide the core email field
	 *
	 * @param string $field The checkout field being filtered.
	 * @param string $key The field key.
	 * @param mixed  $args Field arguments.
	 * @param string $value Field value.
	 * @return string
	 */
	public static function filter_woocommerce_form_field_platform_checkout_email( $field, $key, $args, $value ) {
		$class = $args['class'][0];
		if ( false === strpos( $class, 'platform-checkout-billing-email' ) && is_checkout() && ! is_checkout_pay_page() ) {
			$field = '';
		}
		return $field;
	}

	/**
	 * Register platform checkout hooks and scripts if feature is available.
	 *
	 * @return void
	 */
	public static function init_platform_checkout() {
		// Load platform checkout save user section if feature is enabled.
		$platform_checkout_util = new Platform_Checkout_Utilities();
		if ( $platform_checkout_util->should_enable_platform_checkout( self::get_gateway() ) ) {
			// Update email field location.
			add_action( 'woocommerce_checkout_billing', [ __CLASS__, 'platform_checkout_fields_before_billing_details' ], -50 );
			add_filter( 'woocommerce_form_field_email', [ __CLASS__, 'filter_woocommerce_form_field_platform_checkout_email' ], 20, 4 );

			include_once __DIR__ . '/platform-checkout-user/class-platform-checkout-save-user.php';
			// Load platform checkout tracking.
			include_once WCPAY_ABSPATH . 'includes/class-platform-checkout-tracker.php';

			new Platform_Checkout_Save_User();
			new Platform_Checkout_Tracker( self::get_wc_payments_http() );
		}
	}
}

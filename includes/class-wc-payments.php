<?php
/**
 * Class WC_Payments
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Core\Mode;
use WCPay\Core\Server\Request;
use WCPay\Migrations\Allowed_Payment_Request_Button_Types_Update;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Bancontact_Payment_Method;
use WCPay\Payment_Methods\Becs_Payment_Method;
use WCPay\Payment_Methods\Giropay_Payment_Method;
use WCPay\Payment_Methods\Klarna_Payment_Method;
use WCPay\Payment_Methods\P24_Payment_Method;
use WCPay\Payment_Methods\Sepa_Payment_Method;
use WCPay\Payment_Methods\Sofort_Payment_Method;
use WCPay\Payment_Methods\Ideal_Payment_Method;
use WCPay\Payment_Methods\Eps_Payment_Method;
use WCPay\Payment_Methods\UPE_Payment_Method;
use WCPay\WooPay_Tracker;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\WooPay\WooPay_Order_Status_Sync;
use WCPay\Payment_Methods\Link_Payment_Method;
use WCPay\Payment_Methods\Affirm_Payment_Method;
use WCPay\Payment_Methods\Afterpay_Payment_Method;
use WCPay\Session_Rate_Limiter;
use WCPay\Database_Cache;
use WCPay\WC_Payments_Checkout;
use WCPay\WooPay\Service\Checkout_Service;
use WCPay\Core\WC_Payments_Customer_Service_API;
use WCPay\Constants\Payment_Method;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;
use WCPay\WooPay\WooPay_Scheduler;
use WCPay\WooPay\WooPay_Session;
use WCPay\Compatibility_Service;
use WCPay\Duplicates_Detection_Service;
use WCPay\WC_Payments_Currency_Manager;

/**
 * Main class for the WooPayments extension. Its responsibility is to initialize the extension.
 */
class WC_Payments {
	/**
	 * Main payment gateway controller instance, created in init function.
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
	 * Instance of WC_Payments_Session_Service, created in init function.
	 *
	 * @var WC_Payments_Session_Service
	 */
	private static $session_service;

	/**
	 * Instance of WC_Payments_Redirect_Service, created in init function.
	 *
	 * @var WC_Payments_Redirect_Service
	 */
	private static $redirect_service;

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
	 * Instance of WC_Payments_Settings_Service, created in init function
	 *
	 * @var WC_Payments_Settings_Service
	 */
	private static $settings_service;

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
	 * Maps all availabled Stripe payment method IDs to Payment Method instances.
	 *
	 * @var array
	 */
	private static $payment_method_map = [];

	/**
	 * Maps all availabled Stripe payment method IDs to Payment Gateway instances.
	 *
	 * @var array
	 */
	private static $payment_gateway_map = [];

	/**
	 * Instance of WC_Payments_Webhook_Reliability_Service, created in init function
	 *
	 * @var WC_Payments_Webhook_Reliability_Service
	 */
	private static $webhook_reliability_service;

	/**
	 * Holds WCPay's working mode.
	 *
	 * @var Mode
	 */
	private static $mode;

	/**
	 * WooPay Utilities.
	 *
	 * @var WooPay_Utilities
	 */
	private static $woopay_util;

	/**
	 * WooPay Tracker.
	 *
	 * @var WooPay_Tracker
	 */
	private static $woopay_tracker;

	/**
	 * WC Payments Checkout
	 *
	 * @var WC_Payments_Checkout
	 */
	private static $wc_payments_checkout;

	/**
	 * WooPay Checkout service
	 *
	 * @var Checkout_Service
	 */
	private static $woopay_checkout_service;

	/**
	 * WC Payments Customer Service API
	 *
	 * @var WC_Payments_Customer_Service_API
	 */
	private static $customer_service_api;

	/**
	 * Duplicate payment prevention service.
	 *
	 * @var Duplicate_Payment_Prevention_Service
	 */
	private static $duplicate_payment_prevention_service;

	/**
	 * Instance of WC_Payments_Incentives_Service, created in init function.
	 *
	 * @var WC_Payments_Incentives_Service
	 */
	private static $incentives_service;

	/**
	 * Instance of WC_Payments_Express_Checkout_Button_Helper, created in init function.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private static $express_checkout_helper;

	/**
	 * Instance of Compatibility_Service, created in init function
	 *
	 * @var Compatibility_Service
	 */
	private static $compatibility_service;

	/**
	 * Instance of Duplicates_Detection_Service, created in init function
	 *
	 * @var Duplicates_Detection_Service
	 */
	private static $duplicates_detection_service;

	/**
	 * Instance of WC_Payments_Currency_Manager, created in init function
	 *
	 * @var WC_Payments_Currency_Manager
	 */
	private static $currency_manager;

	/**
	 * Entry point to the initialization logic.
	 */
	public static function init() {
		// in TeamCity tests, there might be multiple instances of WC_Payments initialized.
		// This prevents the hooks from being registered multiple times, which can cause issues.
		if ( defined( 'WCPAY_VERSION_NUMBER' ) ) {
			return;
		}

		define( 'WCPAY_VERSION_NUMBER', self::get_plugin_headers()['Version'] );

		include_once __DIR__ . '/class-wc-payments-utils.php';
		include_once __DIR__ . '/core/class-mode.php';

		self::$mode = new Mode();

		include_once __DIR__ . '/class-database-cache.php';
		self::$database_cache = new Database_Cache();
		self::$database_cache->init_hooks();

		include_once __DIR__ . '/class-wc-payments-dependency-service.php';

		self::$dependency_service = new WC_Payments_Dependency_Service();
		self::$dependency_service->init_hooks();

		if ( false === self::$dependency_service->has_valid_dependencies() ) {
			return;
		}

		add_action( 'admin_init', [ __CLASS__, 'add_woo_admin_notes' ] );
		add_action( 'init', [ __CLASS__, 'install_actions' ] );

		add_action( 'woocommerce_blocks_payment_method_type_registration', [ __CLASS__, 'register_checkout_gateway' ] );

		include_once __DIR__ . '/class-wc-payments-db.php';
		self::$db_helper = new WC_Payments_DB();

		include_once __DIR__ . '/exceptions/class-base-exception.php';
		include_once __DIR__ . '/exceptions/class-api-exception.php';
		include_once __DIR__ . '/exceptions/class-api-merchant-exception.php';
		include_once __DIR__ . '/exceptions/class-connection-exception.php';
		include_once __DIR__ . '/core/class-mode.php';

		// Include core exceptions.
		include_once __DIR__ . '/core/exceptions/server/request/class-server-request-exception.php';
		include_once __DIR__ . '/core/exceptions/server/request/class-invalid-request-parameter-exception.php';
		include_once __DIR__ . '/core/exceptions/server/request/class-immutable-parameter-exception.php';
		include_once __DIR__ . '/core/exceptions/server/request/class-extend-request-exception.php';
		include_once __DIR__ . '/core/exceptions/server/response/class-server-response-exception.php';

		// Include core requests.
		include_once __DIR__ . '/core/server/class-request.php';
		include_once __DIR__ . '/core/server/class-response.php';
		include_once __DIR__ . '/core/server/request/trait-intention.php';
		include_once __DIR__ . '/core/server/request/trait-level3.php';
		include_once __DIR__ . '/core/server/request/trait-order-info.php';
		include_once __DIR__ . '/core/server/request/trait-date-parameters.php';
		include_once __DIR__ . '/core/server/request/trait-use-test-mode-only-when-test-mode-onboarding.php';
		include_once __DIR__ . '/core/server/request/class-generic.php';
		include_once __DIR__ . '/core/server/request/class-get-intention.php';
		include_once __DIR__ . '/core/server/request/class-get-reporting-payment-activity.php';
		include_once __DIR__ . '/core/server/request/class-get-payment-process-factors.php';
		include_once __DIR__ . '/core/server/request/class-create-intention.php';
		include_once __DIR__ . '/core/server/request/class-update-intention.php';
		include_once __DIR__ . '/core/server/request/class-capture-intention.php';
		include_once __DIR__ . '/core/server/request/class-cancel-intention.php';
		include_once __DIR__ . '/core/server/request/class-create-setup-intention.php';
		include_once __DIR__ . '/core/server/request/class-create-and-confirm-setup-intention.php';
		include_once __DIR__ . '/core/server/request/class-get-setup-intention.php';
		include_once __DIR__ . '/core/server/request/class-get-account.php';
		include_once __DIR__ . '/core/server/request/class-get-account-login-data.php';
		include_once __DIR__ . '/core/server/request/class-get-account-capital-link.php';
		include_once __DIR__ . '/core/server/request/class-add-account-tos-agreement.php';
		include_once __DIR__ . '/core/server/request/class-update-account.php';
		include_once __DIR__ . '/core/server/request/class-get-charge.php';
		include_once __DIR__ . '/core/server/request/class-woopay-create-intent.php';
		include_once __DIR__ . '/core/server/request/class-create-and-confirm-intention.php';
		include_once __DIR__ . '/core/server/request/class-woopay-create-and-confirm-intention.php';
		include_once __DIR__ . '/core/server/request/class-woopay-create-and-confirm-setup-intention.php';
		include_once __DIR__ . '/core/server/request/class-paginated.php';
		include_once __DIR__ . '/core/server/request/class-list-transactions.php';
		include_once __DIR__ . '/core/server/request/class-list-fraud-outcome-transactions.php';
		include_once __DIR__ . '/core/server/request/class-list-disputes.php';
		include_once __DIR__ . '/core/server/request/class-list-deposits.php';
		include_once __DIR__ . '/core/server/request/class-list-documents.php';
		include_once __DIR__ . '/core/server/request/class-list-authorizations.php';
		include_once __DIR__ . '/core/server/request/class-woopay-create-and-confirm-setup-intention.php';
		include_once __DIR__ . '/core/server/request/class-refund-charge.php';
		include_once __DIR__ . '/core/server/request/class-list-charge-refunds.php';
		include_once __DIR__ . '/core/server/request/class-get-request.php';
		include_once __DIR__ . '/core/server/request/class-request-utils.php';

		include_once __DIR__ . '/woopay/services/class-checkout-service.php';

		self::$api_client = self::create_api_client();

		include_once __DIR__ . '/compat/subscriptions/trait-wc-payments-subscriptions-utilities.php';
		include_once __DIR__ . '/compat/subscriptions/trait-wc-payment-gateway-wcpay-subscriptions.php';
		include_once __DIR__ . '/class-wc-payments-session-service.php';
		include_once __DIR__ . '/class-wc-payments-redirect-service.php';
		include_once __DIR__ . '/class-wc-payments-account.php';
		include_once __DIR__ . '/class-wc-payments-customer-service.php';
		include_once __DIR__ . '/class-logger.php';
		include_once __DIR__ . '/class-logger-context.php';
		include_once __DIR__ . '/class-session-rate-limiter.php';
		include_once __DIR__ . '/class-wc-payment-gateway-wcpay.php';
		include_once __DIR__ . '/class-wc-payments-checkout.php';
		include_once __DIR__ . '/payment-methods/class-cc-payment-gateway.php';
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
		include_once __DIR__ . '/payment-methods/class-affirm-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-afterpay-payment-method.php';
		include_once __DIR__ . '/payment-methods/class-klarna-payment-method.php';
		include_once __DIR__ . '/express-checkout/class-wc-payments-express-checkout-button-helper.php';
		include_once __DIR__ . '/class-wc-payment-token-wcpay-sepa.php';
		include_once __DIR__ . '/class-wc-payments-status.php';
		include_once __DIR__ . '/class-wc-payments-token-service.php';
		include_once __DIR__ . '/express-checkout/class-wc-payments-express-checkout-ajax-handler.php';
		include_once __DIR__ . '/express-checkout/class-wc-payments-express-checkout-button-display-handler.php';
		include_once __DIR__ . '/express-checkout/class-wc-payments-express-checkout-button-handler.php';
		include_once __DIR__ . '/class-wc-payments-woopay-button-handler.php';
		include_once __DIR__ . '/class-wc-payments-woopay-direct-checkout.php';
		include_once __DIR__ . '/class-wc-payments-apple-pay-registration.php';
		include_once __DIR__ . '/exceptions/class-add-payment-method-exception.php';
		include_once __DIR__ . '/exceptions/class-amount-too-large-exception.php';
		include_once __DIR__ . '/exceptions/class-amount-too-small-exception.php';
		include_once __DIR__ . '/exceptions/class-cannot-combine-currencies-exception.php';
		include_once __DIR__ . '/exceptions/class-intent-authentication-exception.php';
		include_once __DIR__ . '/exceptions/class-invalid-payment-method-exception.php';
		include_once __DIR__ . '/exceptions/class-process-payment-exception.php';
		include_once __DIR__ . '/exceptions/class-invalid-phone-number-exception.php';
		include_once __DIR__ . '/exceptions/class-invalid-webhook-data-exception.php';
		include_once __DIR__ . '/exceptions/class-invalid-price-exception.php';
		include_once __DIR__ . '/exceptions/class-fraud-ruleset-exception.php';
		include_once __DIR__ . '/exceptions/class-fraud-prevention-enabled-exception.php';
		include_once __DIR__ . '/exceptions/class-new-process-payment-exception.php';
		include_once __DIR__ . '/exceptions/class-order-not-found-exception.php';
		include_once __DIR__ . '/exceptions/class-order-id-mismatch-exception.php';
		include_once __DIR__ . '/exceptions/class-rate-limiter-enabled-exception.php';
		include_once __DIR__ . '/exceptions/class-invalid-address-exception.php';
		include_once __DIR__ . '/constants/class-base-constant.php';
		include_once __DIR__ . '/constants/class-country-code.php';
		include_once __DIR__ . '/constants/class-country-test-cards.php';
		include_once __DIR__ . '/constants/class-currency-code.php';
		include_once __DIR__ . '/constants/class-fraud-meta-box-type.php';
		include_once __DIR__ . '/constants/class-order-mode.php';
		include_once __DIR__ . '/constants/class-order-status.php';
		include_once __DIR__ . '/constants/class-payment-type.php';
		include_once __DIR__ . '/constants/class-payment-initiated-by.php';
		include_once __DIR__ . '/constants/class-intent-status.php';
		include_once __DIR__ . '/constants/class-payment-intent-status.php';
		include_once __DIR__ . '/constants/class-payment-capture-type.php';
		include_once __DIR__ . '/constants/class-payment-method.php';
		include_once __DIR__ . '/constants/class-track-events.php';
		include_once __DIR__ . '/class-payment-information.php';
		require_once __DIR__ . '/notes/class-wc-payments-remote-note-service.php';
		include_once __DIR__ . '/class-wc-payments-action-scheduler-service.php';
		include_once __DIR__ . '/class-wc-payments-fraud-service.php';
		include_once __DIR__ . '/class-wc-payments-onboarding-service.php';
		include_once __DIR__ . '/class-experimental-abtest.php';
		include_once __DIR__ . '/class-wc-payments-localization-service.php';
		include_once __DIR__ . '/class-wc-payments-settings-service.php';
		include_once __DIR__ . '/in-person-payments/class-wc-payments-in-person-payments-receipts-service.php';
		include_once __DIR__ . '/class-wc-payments-order-service.php';
		include_once __DIR__ . '/class-wc-payments-order-success-page.php';
		include_once __DIR__ . '/class-wc-payments-file-service.php';
		include_once __DIR__ . '/class-wc-payments-webhook-processing-service.php';
		include_once __DIR__ . '/class-wc-payments-webhook-reliability-service.php';
		include_once __DIR__ . '/fraud-prevention/class-fraud-prevention-service.php';
		include_once __DIR__ . '/fraud-prevention/class-buyer-fingerprinting-service.php';
		include_once __DIR__ . '/fraud-prevention/class-fraud-risk-tools.php';
		include_once __DIR__ . '/fraud-prevention/wc-payments-fraud-risk-tools.php';
		include_once __DIR__ . '/woopay/class-woopay-store-api-token.php';
		include_once __DIR__ . '/woopay/class-woopay-utilities.php';
		include_once __DIR__ . '/woopay/class-woopay-order-status-sync.php';
		include_once __DIR__ . '/woopay/class-woopay-store-api-session-handler.php';
		include_once __DIR__ . '/woopay/class-woopay-scheduler.php';
		include_once __DIR__ . '/woopay/class-woopay-adapted-extensions.php';
		include_once __DIR__ . '/class-wc-payment-token-wcpay-link.php';
		include_once __DIR__ . '/core/service/class-wc-payments-customer-service-api.php';
		include_once __DIR__ . '/class-duplicate-payment-prevention-service.php';
		include_once __DIR__ . '/class-wc-payments-incentives-service.php';
		include_once __DIR__ . '/class-compatibility-service.php';
		include_once __DIR__ . '/compat/multi-currency/wc-payments-multi-currency.php';
		include_once __DIR__ . '/compat/multi-currency/class-wc-payments-currency-manager.php';
		include_once __DIR__ . '/class-duplicates-detection-service.php';

		wcpay_get_container()->get( \WCPay\Internal\LoggerContext::class )->init_hooks();

		self::$woopay_checkout_service = new Checkout_Service();
		self::$woopay_checkout_service->init();

		// // Load woopay save user section if feature is enabled.
		add_action( 'woocommerce_cart_loaded_from_session', [ __CLASS__, 'init_woopay' ] );

		// Init the email template for In Person payment receipt email. We need to do it before passing the mailer to the service.
		add_filter( 'woocommerce_email_classes', [ __CLASS__, 'add_ipp_emails' ], 10 );

		// Always load tracker to avoid class not found errors.
		include_once WCPAY_ABSPATH . 'includes/admin/tracks/class-tracker.php';

		// Load woopay tracking.
		include_once WCPAY_ABSPATH . 'includes/class-woopay-tracker.php';

		self::$order_service                        = new WC_Payments_Order_Service( self::$api_client );
		self::$compatibility_service                = new Compatibility_Service( self::$api_client );
		self::$action_scheduler_service             = new WC_Payments_Action_Scheduler_Service( self::$api_client, self::$order_service, self::$compatibility_service );
		self::$session_service                      = new WC_Payments_Session_Service( self::$api_client );
		self::$redirect_service                     = new WC_Payments_Redirect_Service( self::$api_client );
		self::$onboarding_service                   = new WC_Payments_Onboarding_Service( self::$api_client, self::$database_cache, self::$session_service );
		self::$account                              = new WC_Payments_Account( self::$api_client, self::$database_cache, self::$action_scheduler_service, self::$onboarding_service, self::$redirect_service );
		self::$customer_service                     = new WC_Payments_Customer_Service( self::$api_client, self::$account, self::$database_cache, self::$session_service, self::$order_service );
		self::$token_service                        = new WC_Payments_Token_Service( self::$api_client, self::$customer_service );
		self::$remote_note_service                  = new WC_Payments_Remote_Note_Service( WC_Data_Store::load( 'admin-note' ) );
		self::$fraud_service                        = new WC_Payments_Fraud_Service( self::$api_client, self::$customer_service, self::$account, self::$session_service, self::$database_cache );
		self::$in_person_payments_receipts_service  = new WC_Payments_In_Person_Payments_Receipts_Service();
		self::$localization_service                 = new WC_Payments_Localization_Service();
		self::$settings_service                     = new WC_Payments_Settings_Service();
		self::$failed_transaction_rate_limiter      = new Session_Rate_Limiter( Session_Rate_Limiter::SESSION_KEY_DECLINED_CARD_REGISTRY, 5, 10 * MINUTE_IN_SECONDS );
		self::$order_success_page                   = new WC_Payments_Order_Success_Page();
		self::$woopay_util                          = new WooPay_Utilities();
		self::$woopay_tracker                       = new WooPay_Tracker( self::get_wc_payments_http() );
		self::$incentives_service                   = new WC_Payments_Incentives_Service( self::$database_cache );
		self::$duplicate_payment_prevention_service = new Duplicate_Payment_Prevention_Service();
		self::$duplicates_detection_service         = new Duplicates_Detection_Service();

		( new WooPay_Scheduler( self::$api_client ) )->init();

		// Initialise hooks.
		self::$account->init_hooks();
		self::$fraud_service->init_hooks();
		self::$onboarding_service->init_hooks();
		self::$incentives_service->init_hooks();
		self::$compatibility_service->init_hooks();
		self::$customer_service->init_hooks();
		self::$token_service->init_hooks();

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
			Affirm_Payment_Method::class,
			Afterpay_Payment_Method::class,
			Klarna_Payment_Method::class,
		];

		$payment_methods = [];
		foreach ( $payment_method_classes as $payment_method_class ) {
			$payment_method                               = new $payment_method_class( self::$token_service );
			$payment_methods[ $payment_method->get_id() ] = $payment_method;
		}
		foreach ( $payment_methods as $payment_method ) {
			self::$payment_method_map[ $payment_method->get_id() ] = $payment_method;

			$split_gateway = new WC_Payment_Gateway_WCPay( self::$api_client, self::$account, self::$customer_service, self::$token_service, self::$action_scheduler_service, $payment_method, $payment_methods, self::$order_service, self::$duplicate_payment_prevention_service, self::$localization_service, self::$fraud_service, self::$duplicates_detection_service, self::$failed_transaction_rate_limiter );

			// Card gateway hooks are registered once below.
			if ( 'card' !== $payment_method->get_id() ) {
				$split_gateway->init_hooks();
			}

			self::$payment_gateway_map[ $payment_method->get_id() ] = $split_gateway;
		}

		self::$card_gateway         = self::get_payment_gateway_by_id( 'card' );
		self::$wc_payments_checkout = new WC_Payments_Checkout( self::get_gateway(), self::$woopay_util, self::$account, self::$customer_service, self::$fraud_service );

		self::$card_gateway->init_hooks();
		self::$wc_payments_checkout->init_hooks();

		self::$webhook_processing_service  = new WC_Payments_Webhook_Processing_Service( self::$api_client, self::$db_helper, self::$account, self::$remote_note_service, self::$order_service, self::$in_person_payments_receipts_service, self::get_gateway(), self::$customer_service, self::$database_cache );
		self::$webhook_reliability_service = new WC_Payments_Webhook_Reliability_Service( self::$api_client, self::$action_scheduler_service, self::$webhook_processing_service );

		self::$customer_service_api = new WC_Payments_Customer_Service_API( self::$customer_service );

		self::$currency_manager = new WC_Payments_Currency_Manager( self::get_gateway() );
		self::$currency_manager->init_hooks();

		// Only register hooks of the new `src` service with the same feature of Duplicate_Payment_Prevention_Service.
		// To avoid register the same hooks twice.
		wcpay_get_container()->get( \WCPay\Internal\Service\DuplicatePaymentPreventionService::class )->init_hooks();

		self::$apple_pay_registration = new WC_Payments_Apple_Pay_Registration( self::$api_client, self::$account, self::get_gateway() );
		self::$apple_pay_registration->init_hooks();

		$express_checkout_helper = new WC_Payments_Express_Checkout_Button_Helper( self::get_gateway(), self::$account );
		self::set_express_checkout_helper( $express_checkout_helper );

		// Delay registering hooks that could end up in a fatal error due to expired account cache.
		// The `woocommerce_payments_account_refreshed` action will result in a fatal error if it's fired before the `$wp_rewrite` is defined.
		// See #8942 for more details.
		add_action(
			'setup_theme',
			function () {
				add_action( 'woocommerce_payments_account_refreshed', [ WooPay_Order_Status_Sync::class, 'remove_webhook' ] );

				self::maybe_register_woopay_hooks();
				self::maybe_display_express_checkout_buttons();
				self::maybe_init_woopay_direct_checkout();
				self::maybe_enqueue_woopay_common_config_script( WC_Payments_Features::is_woopay_direct_checkout_enabled() );
			}
		);

		if ( self::get_gateway()->is_enabled() ) {
			// Insert the Stripe Payment Messaging Element only if there is at least one BNPL method enabled.
			$enabled_bnpl_payment_methods = array_intersect(
				Payment_Method::BNPL_PAYMENT_METHODS,
				self::get_gateway()->get_upe_enabled_payment_method_ids()
			);
			if ( [] !== $enabled_bnpl_payment_methods ) {
				add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'load_stripe_bnpl_site_messaging' ], 10 );
				add_action( 'woocommerce_proceed_to_checkout', [ __CLASS__, 'load_stripe_bnpl_site_messaging' ], 5 );
				add_action( 'woocommerce_blocks_enqueue_cart_block_scripts_after', [ __CLASS__, 'load_stripe_bnpl_site_messaging' ] );
				add_action( 'wc_ajax_wcpay_get_cart_total', [ __CLASS__, 'ajax_get_cart_total' ] );
				add_action( 'wc_ajax_wcpay_check_bnpl_availability', [ __CLASS__, 'ajax_check_bnpl_availability' ] );
			}
		}

		add_filter( 'woocommerce_payment_gateways', [ __CLASS__, 'register_gateway' ] );
		add_filter( 'option_woocommerce_gateway_order', [ __CLASS__, 'set_gateway_top_of_list' ], 2 );
		add_filter( 'default_option_woocommerce_gateway_order', [ __CLASS__, 'set_gateway_top_of_list' ], 3 );
		add_filter( 'default_option_woocommerce_gateway_order', [ __CLASS__, 'replace_wcpay_gateway_with_payment_methods' ], 4 );
		add_filter( 'woocommerce_rest_api_option_permissions', [ __CLASS__, 'add_wcpay_options_to_woocommerce_permissions_list' ], 5 );
		add_filter( 'woocommerce_admin_get_user_data_fields', [ __CLASS__, 'add_user_data_fields' ] );

		// Add note query support for source.
		add_filter( 'woocommerce_rest_notes_object_query', [ __CLASS__, 'possibly_add_source_to_notes_query' ], 10, 2 );
		add_filter( 'woocommerce_note_where_clauses', [ __CLASS__, 'possibly_add_note_source_where_clause' ], 10, 2 );

		// Priority 5 so we can manipulate the registered gateways before they are shown.
		add_action( 'woocommerce_admin_field_payment_gateways', [ __CLASS__, 'hide_gateways_on_settings_page' ], 5 );

		require_once __DIR__ . '/migrations/class-allowed-payment-request-button-types-update.php';
		require_once __DIR__ . '/migrations/class-allowed-payment-request-button-sizes-update.php';
		require_once __DIR__ . '/migrations/class-update-service-data-from-server.php';
		require_once __DIR__ . '/migrations/class-additional-payment-methods-admin-notes-removal.php';
		require_once __DIR__ . '/migrations/class-link-woopay-mutual-exclusion-handler.php';
		require_once __DIR__ . '/migrations/class-gateway-settings-sync.php';
		require_once __DIR__ . '/migrations/class-delete-active-woopay-webhook.php';
		require_once __DIR__ . '/migrations/class-payment-method-deprecation-settings-update.php';
		require_once __DIR__ . '/migrations/class-erase-bnpl-announcement-meta.php';
		require_once __DIR__ . '/migrations/class-erase-deprecated-flags-and-options.php';
		require_once __DIR__ . '/migrations/class-manual-capture-payment-method-settings-update.php';
		add_action( 'woocommerce_woocommerce_payments_updated', [ new Allowed_Payment_Request_Button_Types_Update( self::get_gateway() ), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Allowed_Payment_Request_Button_Sizes_Update( self::get_gateway() ), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Update_Service_Data_From_Server( self::get_account_service() ), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Additional_Payment_Methods_Admin_Notes_Removal(), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Link_WooPay_Mutual_Exclusion_Handler( self::get_gateway() ), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Gateway_Settings_Sync( self::get_gateway(), self::get_payment_gateway_map() ), 'maybe_sync' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ '\WCPay\Migrations\Delete_Active_WooPay_Webhook', 'maybe_delete' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Payment_Method_Deprecation_Settings_Update( self::get_gateway(), self::get_payment_gateway_map(), Giropay_Payment_Method::PAYMENT_METHOD_STRIPE_ID, '7.9.0' ), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Payment_Method_Deprecation_Settings_Update( self::get_gateway(), self::get_payment_gateway_map(), Sofort_Payment_Method::PAYMENT_METHOD_STRIPE_ID, '8.9.0' ), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Erase_Bnpl_Announcement_Meta(), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Erase_Deprecated_Flags_And_Options(), 'maybe_migrate' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ new \WCPay\Migrations\Manual_Capture_Payment_Method_Settings_Update( self::get_gateway(), self::get_payment_gateway_map() ), 'maybe_migrate' ] );

		include_once WCPAY_ABSPATH . '/includes/class-wc-payments-explicit-price-formatter.php';
		WC_Payments_Explicit_Price_Formatter::init();

		include_once WCPAY_ABSPATH . 'includes/class-wc-payments-captured-event-note.php';
		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-payments-admin-settings.php';
		include_once WCPAY_ABSPATH . 'includes/fraud-prevention/class-order-fraud-and-risk-meta-box.php';

		// Add admin screens.
		if ( is_admin() ) {
			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-payments-admin.php';
		}

		if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			$admin = new WC_Payments_Admin(
				self::$api_client,
				self::get_gateway(),
				self::$account,
				self::$onboarding_service,
				self::$order_service,
				self::$incentives_service,
				self::$fraud_service,
				self::$database_cache
			);
			$admin->init_hooks();

			$admin_settings = new WC_Payments_Admin_Settings( self::get_gateway() );
			$admin_settings->init_hooks();

			// Use tracks loader only in admin screens because it relies on WC_Tracks loaded by WC_Admin.
			include_once WCPAY_ABSPATH . 'includes/admin/tracks/tracks-loader.php';

			include_once __DIR__ . '/admin/class-wc-payments-admin-sections-overwrite.php';
			$admin_sections_overwrite = new WC_Payments_Admin_Sections_Overwrite( self::get_account_service() );
			$admin_sections_overwrite->init_hooks();

			$wcpay_status = new WC_Payments_Status( self::get_gateway(), self::get_wc_payments_http(), self::get_account_service() );
			$wcpay_status->init_hooks();

			$wcpay_order_frt_meta_box = new WCPay\Fraud_Prevention\Order_Fraud_And_Risk_Meta_Box( self::$order_service );
			$wcpay_order_frt_meta_box->init_hooks();
		}

		// Load Stripe Billing subscription integration.
		if ( self::should_load_stripe_billing_integration() ) {
			include_once WCPAY_ABSPATH . '/includes/subscriptions/class-wc-payments-subscriptions.php';
			WC_Payments_Subscriptions::init( self::$api_client, self::$customer_service, self::$order_service, self::$account, self::$token_service );
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.9.0', '<' ) ) {
			add_action( 'woocommerce_onboarding_profile_data_updated', 'WC_Payments_Features::maybe_enable_wcpay_subscriptions_after_onboarding', 10, 2 );
		}

		add_action( 'woocommerce_woocommerce_payments_updated', [ __CLASS__, 'maybe_disable_wcpay_subscriptions_on_update' ] );

		add_action( 'rest_api_init', [ __CLASS__, 'init_rest_api' ] );
		add_action( 'woocommerce_woocommerce_payments_updated', [ __CLASS__, 'set_plugin_activation_timestamp' ] );

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_dev_runtime_scripts' ] );

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets_script' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets_script' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_cart_scripts' ] );

		self::$duplicate_payment_prevention_service->init( self::$card_gateway, self::$order_service );

		wcpay_get_container()->get( \WCPay\Internal\PluginManagement\TranslationsLoader::class )->init_hooks();
	}

	/**
	 * Returns the gateway's working mode.
	 *
	 * @return Mode
	 */
	public static function mode() {
		return self::$mode;
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
			<p><b>WooPayments</b></p>
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
	 * Adds the WooPayments' gateway class to the list of installed payment gateways.
	 *
	 * @param array $gateways Existing list of gateway classes that will be available for the merchant to configure.
	 * @return array The list of payment gateways that will be available, including WooPayments' Gateway class.
	 */
	public static function register_gateway( $gateways ) {
		$payment_methods = array_keys( self::get_payment_method_map() );

		$gateways[] = self::$card_gateway;
		foreach ( $payment_methods as $payment_method_id ) {
			if ( 'card' === $payment_method_id || 'link' === $payment_method_id ) {
				continue;
			}

			$gateways[] = self::get_payment_gateway_by_id( $payment_method_id );
		}

		return $gateways;
	}

	/**
	 * Called on Payments setting page.
	 *
	 * Remove all WCPay gateways except CC one.
	 */
	public static function hide_gateways_on_settings_page() {
		$default_gateway = self::get_gateway();
		foreach ( WC()->payment_gateways->payment_gateways as $index => $payment_gateway ) {
			if ( $payment_gateway instanceof WC_Payment_Gateway_WCPay && $payment_gateway !== $default_gateway ) {
				unset( WC()->payment_gateways->payment_gateways[ $index ] );
			}
		}
	}

	/**
	 * By default, new payment gateways are put at the bottom of the list on the admin "Payments" settings screen.
	 * For visibility, we want WooPayments to be at the top of the list.
	 *
	 * @param array $ordering Existing ordering of the payment gateways.
	 *
	 * @return array Modified ordering.
	 */
	public static function set_gateway_top_of_list( $ordering ) {
		$ordering = (array) $ordering;
		$id       = self::get_gateway()->id;
		// Only tweak the ordering if the list hasn't been reordered with WooPayments in it already.
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
	 * Define fields for storing user preferences in the wp_usermeta table.
	 *
	 * Includes fields for inbox notifications and table column visibility.
	 *
	 * @param array $user_data_fields User data fields.
	 * @return array
	 */
	public static function add_user_data_fields( $user_data_fields ) {
		return array_merge(
			$user_data_fields,
			[
				// Inbox notifications.
				'wc_payments_overview_inbox_last_read',

				// Column visibility preferences.
				'wc_payments_transactions_hidden_columns',
				'wc_payments_transactions_blocked_hidden_columns',
				'wc_payments_transactions_risk_review_hidden_columns',
				'wc_payments_transactions_uncaptured_hidden_columns',
				'wc_payments_payouts_hidden_columns',
				'wc_payments_disputes_hidden_columns',
				'wc_payments_documents_hidden_columns',
			]
		);
	}

	/**
	 * By default, new payment gateways are put at the bottom of the list on the admin "Payments" settings screen.
	 * For visibility, we want WooPayments to be at the top of the list.
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
		require_once __DIR__ . '/wc-payment-api/models/class-wc-payments-api-abstract-intention.php';
		require_once __DIR__ . '/wc-payment-api/models/class-wc-payments-api-payment-intention.php';
		require_once __DIR__ . '/wc-payment-api/models/class-wc-payments-api-setup-intention.php';
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
			$http_class->init_hooks();
		}

		return $http_class;
	}

	/**
	 * Initialize the REST API controllers.
	 */
	public static function init_rest_api() {
		// Ensures we are not initializing our REST during `rest_preload_api_request`.
		// When constructors signature changes, in manual update scenarios we were run into fatals.
		// Those fatals are not critical, but it causes hickups in release process as catches unnecessary attention.
		if ( function_exists( 'get_current_screen' ) && get_current_screen() ) {
			return;
		}

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
		$conn_tokens_controller = new WC_REST_Payments_Connection_Tokens_Controller( self::$api_client );
		$conn_tokens_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-orders-controller.php';
		$orders_controller = new WC_REST_Payments_Orders_Controller( self::$api_client, self::get_gateway(), self::$customer_service, self::$order_service );
		$orders_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-fraud-outcomes-controller.php';
		$fraud_outcomes_controller = new WC_REST_Payments_Fraud_Outcomes_Controller( self::$api_client );
		$fraud_outcomes_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-timeline-controller.php';
		$timeline_controller = new WC_REST_Payments_Timeline_Controller( self::$api_client );
		$timeline_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-webhook-controller.php';
		$webhook_controller = new WC_REST_Payments_Webhook_Controller( self::$api_client, self::$webhook_processing_service );
		$webhook_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-tos-controller.php';
		$tos_controller = new WC_REST_Payments_Tos_Controller( self::$api_client, self::get_gateway(), self::$account );
		$tos_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-terminal-locations-controller.php';
		$accounts_controller = new WC_REST_Payments_Terminal_Locations_Controller( self::$api_client );
		$accounts_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-reporting-controller.php';
		$reporting_controller = new WC_REST_Payments_Reporting_Controller( self::$api_client );
		$reporting_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-settings-controller.php';
		$settings_controller = new WC_REST_Payments_Settings_Controller( self::$api_client, self::get_gateway(), self::$account );
		$settings_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-reader-controller.php';
		$charges_controller = new WC_REST_Payments_Reader_Controller( self::$api_client, self::get_gateway(), self::$in_person_payments_receipts_service );
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

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-customer-controller.php';
		$customer_controller = new WC_REST_Payments_Customer_Controller( self::$api_client, self::$customer_service );
		$customer_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-refunds-controller.php';
		$refunds_controller = new WC_REST_Payments_Refunds_Controller( self::$api_client );
		$refunds_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-survey-controller.php';
		$survey_controller = new WC_REST_Payments_Survey_Controller( self::get_wc_payments_http() );
		$survey_controller->register_routes();

		if ( WC_Payments_Features::is_documents_section_enabled() ) {
			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-documents-controller.php';
			$documents_controller = new WC_REST_Payments_Documents_Controller( self::$api_client );
			$documents_controller->register_routes();

			include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-vat-controller.php';
			$vat_controller = new WC_REST_Payments_VAT_Controller( self::$api_client );
			$vat_controller->register_routes();
		}

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-payment-intents-controller.php';
		$payment_intents_controller = new WC_REST_Payments_Payment_Intents_Controller( self::$api_client );
		$payment_intents_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-payment-intents-create-controller.php';
		$payment_intents_create_controller = new WC_REST_Payments_Payment_Intents_Create_Controller(
			self::$api_client,
			self::get_gateway(),
			wcpay_get_container()->get( OrderService::class ),
			wcpay_get_container()->get( Level3Service::class )
		);
		$payment_intents_create_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-authorizations-controller.php';
		$authorizations_controller = new WC_REST_Payments_Authorizations_Controller( self::$api_client );
		$authorizations_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/reports/class-wc-rest-payments-reports-transactions-controller.php';
		$reports_transactions_controller = new WC_REST_Payments_Reports_Transactions_Controller( self::$api_client );
		$reports_transactions_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/reports/class-wc-rest-payments-reports-authorizations-controller.php';
		$reports_authorizations_controller = new WC_REST_Payments_Reports_Authorizations_Controller( self::$api_client );
		$reports_authorizations_controller->register_routes();

		include_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-woopay-session-controller.php';
		$woopay_session_controller = new WC_REST_WooPay_Session_Controller();
		$woopay_session_controller->register_routes();
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
	 * Returns the WooPay_Tracker instance
	 *
	 * @return WooPay_Tracker instance
	 */
	public static function woopay_tracker(): WooPay_Tracker {
		return self::$woopay_tracker;
	}

	/**
	 * Load script with all required dependencies.
	 *
	 * @param string $handler Script handler.
	 * @param string $script Script name.
	 * @param array  $dependencies Additional dependencies.
	 *
	 * @return void
	 */
	public static function register_script_with_dependencies( string $handler, string $script, array $dependencies = [] ) {
		$script_file                  = $script . '.js';
		$script_src_url               = plugins_url( $script_file, WCPAY_PLUGIN_FILE );
		$script_asset_path            = WCPAY_ABSPATH . $script . '.asset.php';
		$script_asset                 = file_exists( $script_asset_path ) ? require $script_asset_path : [ 'dependencies' => [] ]; // nosemgrep: audit.php.lang.security.file.inclusion-arg -- server generated path is used.
		$script_asset['dependencies'] = array_merge( $script_asset['dependencies'], $dependencies );
		wp_register_script(
			$handler,
			$script_src_url,
			$script_asset['dependencies'],
			self::get_file_version( $script_file ),
			true
		);
	}

	/**
	 * Returns payment method instance by Stripe ID.
	 *
	 * @param string $payment_method_id Stripe payment method type ID.
	 * @return false|UPE_Payment_Method Matching Payment Method instance.
	 */
	public static function get_payment_method_by_id( $payment_method_id ) {
		if ( ! isset( self::$payment_method_map[ $payment_method_id ] ) ) {
			return false;
		}
		return self::$payment_method_map[ $payment_method_id ];
	}

	/**
	 * Returns payment gateway instance by Stripe ID.
	 *
	 * @param string $payment_method_id Stripe payment method type ID.
	 * @return false|WC_Payment_Gateway_WCPay Matching Payment Gateway instance.
	 */
	public static function get_payment_gateway_by_id( $payment_method_id ) {
		if ( ! isset( self::$payment_gateway_map[ $payment_method_id ] ) ) {
			return false;
		}
		return self::$payment_gateway_map[ $payment_method_id ];
	}

	/**
	 * Returns Payment Method map.
	 *
	 * @return array
	 */
	public static function get_payment_method_map() {
		return self::$payment_method_map;
	}

	/**
	 * Returns Payment Gateway map.
	 *
	 * @return array
	 */
	public static function get_payment_gateway_map() {
		return self::$payment_gateway_map;
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
	 * Returns the WC_Payments_Checkout instance
	 *
	 * @return WC_Payments_Checkout gateway instance
	 */
	public static function get_wc_payments_checkout() {
		return self::$wc_payments_checkout;
	}

	/**
	 * Returns the WC_Payments_Express_Checkout_Button_Helper instance.
	 *
	 * @return WC_Payments_Express_Checkout_Button_Helper instance.
	 */
	public static function get_express_checkout_helper() {
		return self::$express_checkout_helper;
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
	 * Sets the card gateway instance.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway The card gateway instance..
	 */
	public static function set_gateway( $gateway ) {
		self::$card_gateway = $gateway;
	}

	/**
	 * Sets the express checkout helper instance.
	 *
	 * @param WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper The express checkout helper instance.
	 */
	public static function set_express_checkout_helper( $express_checkout_helper ) {
		self::$express_checkout_helper = $express_checkout_helper;
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
	 * Returns the WC_Payments_Settings_Service
	 *
	 * @return WC_Payments_Settings_Service Localization Service instance
	 */
	public static function get_settings_service() {
		return self::$settings_service;
	}

	/**
	 * Returns the WC_Payments_Action_Scheduler_Service
	 *
	 * @return WC_Payments_Action_Scheduler_Service Action Scheduler Service instance
	 */
	public static function get_action_scheduler_service() {
		return self::$action_scheduler_service;
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
	 * Returns the WC_Payments_Customer_Service_API instance
	 *
	 * @return WC_Payments_Customer_Service_API  The Customer Service instance.
	 */
	public static function get_customer_service_api(): WC_Payments_Customer_Service_API {
		return self::$customer_service_api;
	}

	/**
	 * Returns the order service instance.
	 *
	 * @return WC_Payments_Order_Service
	 */
	public static function get_order_service(): WC_Payments_Order_Service {
		return self::$order_service;
	}

	/**
	 * Returns the token service instance.
	 *
	 * @return WC_Payments_Token_Service
	 */
	public static function get_token_service(): WC_Payments_Token_Service {
		return self::$token_service;
	}

	/**
	 * Sets the token service instance. This is needed only for tests.
	 *
	 * @param WC_Payments_Token_Service $token_service Instance of WC_Payments_Token_Service.
	 *
	 * @return void
	 */
	public static function set_token_service( WC_Payments_Token_Service $token_service ) {
		self::$token_service = $token_service;
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
	 * Returns the WC_Payments_Session_Service instance
	 *
	 * @return WC_Payments_Session_Service Session Service instance
	 */
	public static function get_session_service() {
		return self::$session_service;
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
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-qualitative-feedback.php';
			WC_Payments_Notes_Qualitative_Feedback::possibly_add_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-https-for-checkout.php';
			WC_Payments_Notes_Set_Https_For_Checkout::possibly_add_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-up-stripelink.php';
			WC_Payments_Notes_Set_Up_StripeLink::set_gateway( self::get_gateway() );
			WC_Payments_Notes_Set_Up_StripeLink::possibly_add_note();
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.5', '<' ) && get_woocommerce_currency() === 'NOK' ) {
			add_filter( 'admin_notices', [ __CLASS__, 'wcpay_show_old_woocommerce_for_norway_notice' ] );
		}

		add_filter( 'admin_notices', [ __CLASS__, 'wcpay_show_old_woocommerce_for_hungary_sweden_and_czech_republic' ] );
	}

	/**
	 * Shows an alert notice for Norwegian merchants on WooCommerce 7.4 and below
	 */
	public static function wcpay_show_old_woocommerce_for_norway_notice() {
		?>
		<div class="notice wcpay-notice notice-error">
			<p>
			<?php
			echo WC_Payments_Utils::esc_interpolated_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				sprintf(
					/* translators: %1$s: WooCommerce, %2$s: WooPayments, a1: documentation URL */
					__( 'The %1$s version you have installed is not compatible with %2$s for a Norwegian business. Please update %1$s to version 7.5 or above. You can do that via the <a1>the plugins page.</a1>', 'woocommerce-payments' ),
					'WooCommerce',
					'WooPayments'
				),
				[
					'a1' => '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">',
				]
			)
			?>
			</p>
		</div>
		<?php
	}

	/**
	 * Removes WCPay notes from the WC-Admin inbox.
	 */
	public static function remove_woo_admin_notes() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			self::$remote_note_service->delete_notes();
			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-qualitative-feedback.php';
			WC_Payments_Notes_Qualitative_Feedback::possibly_delete_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-https-for-checkout.php';
			WC_Payments_Notes_Set_Https_For_Checkout::possibly_delete_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-instant-deposits-eligible.php';
			WC_Payments_Notes_Instant_Deposits_Eligible::possibly_delete_note();

			require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-set-up-stripelink.php';
			WC_Payments_Notes_Set_Up_StripeLink::possibly_delete_note();
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
	 * Registers woopay hooks if the woopay feature flag is enabled.
	 * Removes WooPay webhooks if the merchant is not eligible.
	 *
	 * @return void
	 */
	public static function maybe_register_woopay_hooks() {
		$is_woopay_eligible = WC_Payments_Features::is_woopay_eligible(); // Feature flag.
		$is_woopay_enabled  = 'yes' === self::get_gateway()->get_option( 'platform_checkout', 'no' );

		if ( $is_woopay_eligible && $is_woopay_enabled ) {
			add_action( 'wc_ajax_wcpay_init_woopay', [ WooPay_Session::class, 'ajax_init_woopay' ] );
			add_action( 'wc_ajax_wcpay_get_woopay_session', [ WooPay_Session::class, 'ajax_get_woopay_session' ] );
			add_action( 'wc_ajax_wcpay_set_woopay_phone_number', [ WooPay_Session::class, 'ajax_set_woopay_phone_number' ] );
			add_action( 'wc_ajax_wcpay_get_woopay_signature', [ __CLASS__, 'ajax_get_woopay_signature' ] );
			add_action( 'wc_ajax_wcpay_get_woopay_minimum_session_data', [ WooPay_Session::class, 'ajax_get_woopay_minimum_session_data' ] );

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

			new WooPay_Order_Status_Sync( self::$api_client, self::$account );
		}
	}

	/**
	 * Initializes woopay direct checkout if the woopay feature flag is enabled.
	 *
	 * @return void
	 */
	public static function maybe_init_woopay_direct_checkout() {
		if ( ! WC_Payments_Features::is_woopay_direct_checkout_enabled() ) {
			return;
		}

		$woopay_direct_checkout = new WC_Payments_WooPay_Direct_Checkout( self::$woopay_util );
		$woopay_direct_checkout->init();
	}

	/**
	 * Validates whether the common config script should be enqueued and enqueues it.
	 *
	 * If the express checkout button is disabled on the cart page, the common config
	 * script needs to be enqueued to ensure `wcpayConfig` is available on the cart page.
	 *
	 * @return void
	 */
	public static function validate_and_enqueue_woopay_common_config_script() {
		$is_express_button_disabled_on_cart = self::get_express_checkout_helper()->is_cart()
			&& ! self::get_express_checkout_helper()->is_available_at( 'cart', WC_Payments_WooPay_Button_Handler::BUTTON_LOCATIONS );

		if ( $is_express_button_disabled_on_cart ) {
			self::enqueue_woopay_common_config_script();
		}
	}

	/**
	 * Enqueues the common config script.
	 *
	 * @return void
	 */
	public static function enqueue_woopay_common_config_script() {
		try {
			// is_test() throws if the class 'Mode' has not been initialized.
			$is_test_mode = self::mode()->is_test();
		} catch ( Exception $e ) {
			// Default to false if the class 'Mode' has not been initialized.
			$is_test_mode = false;
		}

		wp_register_script( 'WCPAY_WOOPAY_COMMON_CONFIG', '', [], WCPAY_VERSION_NUMBER, false );
		wp_localize_script(
			'WCPAY_WOOPAY_COMMON_CONFIG',
			'wcpayConfig',
			[
				'woopayHost'                    => WooPay_Utilities::get_woopay_url(),
				'testMode'                      => $is_test_mode,
				'wcAjaxUrl'                     => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'woopaySessionNonce'            => wp_create_nonce( 'woopay_session_nonce' ),
				'woopayMerchantId'              => Jetpack_Options::get_option( 'id' ),
				'isWooPayDirectCheckoutEnabled' => WC_Payments_Features::is_woopay_direct_checkout_enabled(),
				'platformTrackerNonce'          => wp_create_nonce( 'platform_tracks_nonce' ),
				'ajaxUrl'                       => admin_url( 'admin-ajax.php' ),
				'woopayMinimumSessionData'      => WooPay_Session::get_woopay_minimum_session_data(),
			]
		);
		wp_enqueue_script( 'WCPAY_WOOPAY_COMMON_CONFIG' );
	}

	/**
	 * Enqueues the common config script if the WooPay Direct Checkout flow is
	 * enabled and the express checkout button is disabled on the cart page.
	 *
	 * @param bool $should_enqueue Whether the script should be enqueued.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_woopay_common_config_script( $should_enqueue ) {
		if ( ! $should_enqueue ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'validate_and_enqueue_woopay_common_config_script' ] );
	}

	/**
	 * Initializes express checkout buttons if payments are enabled
	 *
	 * @return void
	 */
	public static function maybe_display_express_checkout_buttons() {
		if ( WC_Payments_Features::are_payments_enabled() ) {
			$woopay_button_handler = new WC_Payments_WooPay_Button_Handler( self::$account, self::get_gateway(), self::$woopay_util, self::get_express_checkout_helper() );

			$express_checkout_ajax_handler           = new WC_Payments_Express_Checkout_Ajax_Handler( self::get_express_checkout_helper() );
			$express_checkout_element_button_handler = new WC_Payments_Express_Checkout_Button_Handler( self::$account, self::get_gateway(), self::get_express_checkout_helper(), $express_checkout_ajax_handler );
			$express_checkout_button_display_handler = new WC_Payments_Express_Checkout_Button_Display_Handler( self::get_gateway(), $woopay_button_handler, $express_checkout_element_button_handler, $express_checkout_ajax_handler, self::get_express_checkout_helper() );
			$express_checkout_button_display_handler->init();
		}
	}

	/**
	 * Retrieve a woopay request signature.
	 *
	 * @return void
	 */
	public static function ajax_get_woopay_signature() {
		$is_nonce_valid = check_ajax_referer( 'woopay_signature_nonce', false, false );

		if ( ! $is_nonce_valid ) {
			wp_send_json_error(
				__( 'You aren’t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		$woopay_util = new WooPay_Utilities();

		$signature = $woopay_util->get_woopay_request_signature();

		wp_send_json_success(
			[
				'signature' => $signature,
			],
			200
		);
	}

	/**
	 * Get cart total.
	 */
	public static function ajax_get_cart_total() {
		check_ajax_referer( 'wcpay-get-cart-total', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		WC()->cart->calculate_totals();

		$cart_total    = WC()->cart->total;
		$currency_code = get_woocommerce_currency();

		wp_send_json( [ 'total' => WC_Payments_Utils::prepare_amount( $cart_total, $currency_code ) ] );
	}

	/**
	 * Check if BNPL is available for the given price, currency, and country.
	 */
	public static function ajax_check_bnpl_availability() {
		check_ajax_referer( 'wcpay-is-bnpl-available', 'security' );

		$price    = floatval( $_POST['price'] );
		$currency = sanitize_text_field( wp_unslash( $_POST['currency'] ) );
		$country  = sanitize_text_field( wp_unslash( $_POST['country'] ) );

		$enabled_bnpl_payment_methods = array_intersect(
			Payment_Method::BNPL_PAYMENT_METHODS,
			self::get_gateway()->get_upe_enabled_payment_method_ids()
		);

		$is_available = WC_Payments_Utils::is_any_bnpl_method_available( $enabled_bnpl_payment_methods, $country, $currency, $price );

		wp_send_json_success( [ 'is_available' => $is_available ] );
	}

	/**
	 * Adds custom email field.
	 */
	public static function woopay_fields_before_billing_details() {
		$checkout = WC()->checkout;

		echo '<div class="woocommerce-billing-fields" id="contact_details">';

		echo '<h3>' . esc_html( __( 'Contact information', 'woocommerce-payments' ) ) . '</h3>';

		echo '<div class="woocommerce-billing-fields__field-wrapper">';
		woocommerce_form_field(
			'billing_email',
			[
				'type'        => 'email',
				'label'       => __( 'Email address', 'woocommerce-payments' ),
				'class'       => [ 'form-row-wide woopay-billing-email' ],
				'input_class' => [ 'woopay-billing-email-input' ],
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
	public static function filter_woocommerce_form_field_woopay_email( $field, $key, $args, $value ) {
		$class = $args['class'][0];
		if ( false === strpos( $class, 'woopay-billing-email' ) && is_checkout() && ! is_checkout_pay_page() ) {
			$field = '';
		}
		return $field;
	}

	/**
	 * Enqueue cart page scripts.
	 *
	 * @return void
	 */
	public static function enqueue_cart_scripts() {
		if ( ! WC_Payments_Utils::is_cart_page() || ! self::get_gateway()->is_enabled() ) {
			return;
		}

		self::register_script_with_dependencies( 'WCPAY_CART', 'dist/cart' );
		wp_enqueue_script( 'WCPAY_CART' );

		if ( WC_Payments_Utils::is_cart_block() ) {
			self::register_script_with_dependencies( 'WCPAY_CART_BLOCK', 'dist/cart-block', [ 'wc-cart-block-frontend' ] );
			wp_enqueue_script( 'WCPAY_CART_BLOCK' );
		}
	}

	/**
	 * Register woopay hooks and scripts if feature is available.
	 *
	 * @return void
	 */
	public static function init_woopay() {
		// Load woopay save user section if feature is enabled.
		if ( self::$woopay_util->should_enable_woopay( self::get_gateway() ) ) {
			// Update email field location.
			add_action( 'woocommerce_checkout_billing', [ __CLASS__, 'woopay_fields_before_billing_details' ], -50 );
			add_filter( 'woocommerce_form_field_email', [ __CLASS__, 'filter_woocommerce_form_field_woopay_email' ], 20, 4 );
			add_action( 'woocommerce_checkout_process', [ __CLASS__, 'maybe_show_woopay_phone_number_error' ] );

			include_once __DIR__ . '/woopay-user/class-woopay-save-user.php';

			new WooPay_Save_User();
		}
	}

	/**
	 * Load stripe site messaging script.
	 *
	 * @return void
	 */
	public static function load_stripe_bnpl_site_messaging() {
		// The messaging element shall not be shown for subscription products.
		// As we are not too deep into subscriptions API, we follow simplistic approach for now.
		$is_subscription            = false;
		$cart_contains_subscription = false;
		$are_subscriptions_enabled  = class_exists( 'WC_Subscriptions' ) || class_exists( 'WC_Subscriptions_Core_Plugin' );
		if ( $are_subscriptions_enabled ) {
			global $product;
			$is_subscription            = $product && WC_Subscriptions_Product::is_subscription( $product );
			$cart_contains_subscription = is_cart() && WC_Subscriptions_Cart::cart_contains_subscription();
		}

		if ( ! $is_subscription && ! $cart_contains_subscription ) {
			require_once __DIR__ . '/class-wc-payments-payment-method-messaging-element.php';
			$stripe_site_messaging = new WC_Payments_Payment_Method_Messaging_Element( self::$account, self::$card_gateway );
			echo wp_kses( $stripe_site_messaging->init() ?? '', 'post' );
		}
	}

	/**
	 * Load webpack runtime script, only if SCRIPT_DEBUG is enabled and the script exists.
	 * Required for webpack server with HMR.
	 *
	 * @return void
	 */
	public static function enqueue_dev_runtime_scripts() {
		if ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && file_exists( WCPAY_ABSPATH . 'dist/runtime.js' ) ) {
			wp_enqueue_script( 'WCPAY_RUNTIME', plugins_url( 'dist/runtime.js', WCPAY_PLUGIN_FILE ), [], self::get_file_version( 'dist/runtime.js' ), true );
		}
	}

	/**
	 * Adds WCPay options to Woo Core option allow list.
	 *
	 * @param   array $permissions Array containing the permissions.
	 *
	 * @return  array              An array containing the modified permissions.
	 */
	public static function add_wcpay_options_to_woocommerce_permissions_list( $permissions ) {
		$wcpay_permissions_list = array_fill_keys(
			[
				'wcpay_frt_discover_banner_settings',
				'wcpay_multi_currency_setup_completed',
				'woocommerce_dismissed_todo_tasks',
				'woocommerce_remind_me_later_todo_tasks',
				'woocommerce_deleted_todo_tasks',
				'wcpay_fraud_protection_welcome_tour_dismissed',
				'wcpay_capability_request_dismissed_notices',
				'wcpay_onboarding_eligibility_modal_dismissed',
				'wcpay_connection_success_modal_dismissed',
				'wcpay_next_deposit_notice_dismissed',
				'wcpay_duplicate_payment_method_notices_dismissed',
				'wcpay_exit_survey_dismissed',
				'wcpay_instant_deposit_notice_dismissed',
				'wcpay_date_format_notice_dismissed',
			],
			true
		);

		if ( is_array( $permissions ) ) {
			return array_merge(
				$permissions,
				$wcpay_permissions_list
			);
		}

		return $wcpay_permissions_list;
	}


	/**
	 * Creates a new request object for a server call.
	 *
	 * @param  string $class_name The name of the request class. Must extend WCPay\Core\Server\Request.
	 * @param  mixed  $id         The item ID, if the request needs it (Optional).
	 * @return Request
	 * @throws Exception          If the request class is not really a request.
	 */
	public static function create_request( $class_name, $id = null ) {
		/**
		 * Used for unit tests only, as requests have dependencies, which are not publicly available in live mode.
		 *
		 * @param Request $request    Null, but if the filter returns a request, it will be used.
		 * @param string  $class_name The name of the request class.
		 */
		$request = apply_filters( 'wcpay_create_request', null, $class_name, $id );
		if ( $request instanceof Request ) {
			return $request;
		}

		if ( ! is_subclass_of( $class_name, Request::class ) ) {
			throw new Exception(
				sprintf(
					'WC_Payments::create_request() requires a class, which extends %s, %s provided instead',
					Request::class,
					$class_name
				)
			);
		}

		return new $class_name( self::get_payments_api_client(), self::get_wc_payments_http(), $id );
	}

	/**
	 * Inject an inline script with WCPay assets properties.
	 * window.wcpayAssets.url – Dist URL, required to properly load chunks on sites with JS concatenation enabled.
	 *
	 * @return void
	 */
	public static function enqueue_assets_script() {
		wp_register_script( 'WCPAY_ASSETS', '', [], WCPAY_VERSION_NUMBER, false );
		wp_enqueue_script( 'WCPAY_ASSETS' );
		wp_localize_script(
			'WCPAY_ASSETS',
			'wcpayAssets',
			[
				'url' => plugins_url( '/dist/', WCPAY_PLUGIN_FILE ),
			]
		);
	}

	/**
	 * Shows an alert notice for Hungarian, Sweden, and Czech Republic merchants on WooCommerce 7.4 and below
	 */
	public static function wcpay_show_old_woocommerce_for_hungary_sweden_and_czech_republic() {
		$currencies        = [ 'HUF', 'SEK', 'CZK' ];
		$store_currency    = get_woocommerce_currency();
		$should_show_error = in_array( $store_currency, $currencies, true );

		if ( ! defined( 'WC_VERSION' ) || ! version_compare( WC_VERSION, '7.8', '<' ) || ! $should_show_error ) {
			return;
		}

		$notice = '';

		switch ( $store_currency ) {
			case 'HUF':
				/* translators: %1$s: WooCommerce , %2$s: WooPayments, %3$s: The current WooCommerce version used by the store */
				$notice = __( 'The %1$s version you have installed is not compatible with %2$s for a Hungarian business. Please update %1$s to version 7.8 or above (you are using %3$s). You can do that via the <a1>the plugins page.</a1>', 'woocommerce-payments' );
				break;
			case 'SEK':
				/* translators: %1$s: WooCommerce , %2$s: WooPayments, %3$s: The current WooCommerce version used by the store */
				$notice = __( 'The %1$s version you have installed is not compatible with %2$s for a Swedish business. Please update %1$s to version 7.8 or above (you are using %3$s). You can do that via the <a1>the plugins page.</a1>', 'woocommerce-payments' );
				break;
			case 'CZK':
				/* translators: %1$s: WooCommerce , %2$s: WooPayments, %3$s: The current WooCommerce version used by the store */
				$notice = __( 'The %1$s version you have installed is not compatible with %2$s for a Czech Republic business. Please update %1$s to version 7.8 or above (you are using %3$s). You can do that via the <a1>the plugins page.</a1>', 'woocommerce-payments' );
				break;
		}

		?>
		<div class="notice wcpay-notice notice-error">
			<p>
			<?php
			echo WC_Payments_Utils::esc_interpolated_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				sprintf(
					$notice,
					'WooCommerce',
					'WooPayments',
					esc_html( WC_VERSION )
				),
				[
					'a1' => '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">',
				]
			)
			?>
			</p>
		</div>
		<?php
	}

	/**
	 * Determines whether we should load Stripe Billing integration classes.
	 *
	 * Return true when:
	 *  - the WCPay Subscriptions feature is enabled & the Woo Subscriptions plugin isn't active, or
	 *  - Woo Subscriptions plugin is active and Stripe Billing is enabled or there are Stripe Billing Subscriptions.
	 *
	 * @see WC_Payments_Features::should_use_stripe_billing()
	 *
	 * @return bool
	 */
	private static function should_load_stripe_billing_integration() {
		if ( WC_Payments_Features::should_use_stripe_billing() ) {
			return true;
		}

		if ( ! function_exists( 'wcs_get_orders_with_meta_query' ) ) {
			return false;
		}

		// If there are any Stripe Billing Subscriptions, we should load the Stripe Billing integration classes. eg while a migration is in progress, or to support legacy subscriptions.
		$result = wcs_get_orders_with_meta_query(
			[
				'status'     => 'any',
				'return'     => 'ids',
				'type'       => 'shop_subscription',
				'limit'      => 1, // We only need to know if there are any - at least 1.
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => '_wcpay_subscription_id',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		return (bool) ( is_countable( $result ) ? count( $result ) : 0 );
	}

	/**
	 * Disable the WCPay Subscriptions feature on WooPayments plugin update if it's enabled and the store is no longer eligible.
	 *
	 * @see WC_Payments_Features::is_wcpay_subscriptions_eligible() for eligibility criteria.
	 */
	public static function maybe_disable_wcpay_subscriptions_on_update() {
		if ( WC_Payments_Features::is_wcpay_subscriptions_enabled() && ( class_exists( 'WC_Subscriptions' ) || ! WC_Payments_Features::is_wcpay_subscriptions_eligible() ) ) {
			update_option( WC_Payments_Features::WCPAY_SUBSCRIPTIONS_FLAG_NAME, '0' );
		}
	}

	/**
	 * Show error when WooPay opt-in is checked but no phone number was typed.
	 */
	public static function maybe_show_woopay_phone_number_error() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['save_user_in_woopay'] ) && 'true' === $_POST['save_user_in_woopay'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! isset( $_POST['woopay_user_phone_field'] ) || ! isset( $_POST['woopay_user_phone_field']['no-country-code'] ) || empty( $_POST['woopay_user_phone_field']['no-country-code'] ) ) {
				wc_add_notice( '<strong>' . __( 'Mobile Number', 'woocommerce-payments' ) . '</strong> ' . __( 'is required to create an WooPay account.', 'woocommerce-payments' ), 'error' );
			}
		}
	}
}

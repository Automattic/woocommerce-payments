<?php
/**
 * Class WC_Payment_Gateway_WCPay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Constants\Fraud_Meta_Box_Type;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Capture_Type;
use WCPay\Constants\Payment_Initiated_By;
use WCPay\Constants\Intent_Status;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Payment_Method;
use WCPay\Exceptions\{ Add_Payment_Method_Exception, Amount_Too_Small_Exception, Process_Payment_Exception, Intent_Authentication_Exception, API_Exception };
use WCPay\Core\Server\Request\Cancel_Intention;
use WCPay\Core\Server\Request\Capture_Intention;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Core\Server\Request\Create_And_Confirm_Setup_Intention;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Core\Server\Request\Get_Charge;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Core\Server\Request\Get_Setup_Intention;
use WCPay\Core\Server\Request\List_Charge_Refunds;
use WCPay\Core\Server\Request\Refund_Charge;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Fraud_Prevention\Fraud_Risk_Tools;
use WCPay\Internal\Payment\State\AuthenticationRequiredState;
use WCPay\Internal\Payment\State\DuplicateOrderDetectedState;
use WCPay\Internal\Service\DuplicatePaymentPreventionService;
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\Payment_Methods\UPE_Payment_Gateway;
use WCPay\Payment_Methods\UPE_Split_Payment_Gateway;
use WCPay\Payment_Methods\Link_Payment_Method;
use WCPay\WooPay\WooPay_Order_Status_Sync;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\Session_Rate_Limiter;
use WCPay\Tracker;
use WCPay\Internal\Service\PaymentProcessingService;
use WCPay\Internal\Payment\Factor;
use WCPay\Internal\Payment\Router;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;

/**
 * Gateway class for WooPayments
 */
class WC_Payment_Gateway_WCPay extends WC_Payment_Gateway_CC {

	use WC_Payment_Gateway_WCPay_Subscriptions_Trait;

	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woocommerce_payments';

	const METHOD_ENABLED_KEY = 'enabled';

	/**
	 * Mapping between the client and server accepted params:
	 * - Keys are WCPay client accepted params (in WC_REST_Payments_Settings_Controller).
	 * - Values are WCPay Server accepted params.
	 *
	 * @type array
	 */
	const ACCOUNT_SETTINGS_MAPPING = [
		'account_statement_descriptor'       => 'statement_descriptor',
		'account_statement_descriptor_kanji' => 'statement_descriptor_kanji',
		'account_statement_descriptor_kana'  => 'statement_descriptor_kana',
		'account_business_name'              => 'business_name',
		'account_business_url'               => 'business_url',
		'account_business_support_address'   => 'business_support_address',
		'account_business_support_email'     => 'business_support_email',
		'account_business_support_phone'     => 'business_support_phone',
		'account_branding_logo'              => 'branding_logo',
		'account_branding_icon'              => 'branding_icon',
		'account_branding_primary_color'     => 'branding_primary_color',
		'account_branding_secondary_color'   => 'branding_secondary_color',

		'deposit_schedule_interval'          => 'deposit_schedule_interval',
		'deposit_schedule_weekly_anchor'     => 'deposit_schedule_weekly_anchor',
		'deposit_schedule_monthly_anchor'    => 'deposit_schedule_monthly_anchor',
	];

	const UPDATE_SAVED_PAYMENT_METHOD = 'wcpay_update_saved_payment_method';

	/**
	 * Set a large limit argument for retrieving user tokens.
	 *
	 * @type int
	 */

	const USER_FORMATTED_TOKENS_LIMIT = 100;

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	protected $account;

	/**
	 * WC_Payments_Customer instance for working with customer information
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * WC_Payments_Token instance for working with customer tokens
	 *
	 * @var WC_Payments_Token_Service
	 */
	protected $token_service;

	/**
	 * WC_Payments_Order_Service instance
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * WC_Payments_Action_Scheduler_Service instance for scheduling ActionScheduler jobs.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private $action_scheduler_service;

	/**
	 * Session_Rate_Limiter instance for limiting failed transactions.
	 *
	 * @var Session_Rate_Limiter
	 */
	protected $failed_transaction_rate_limiter;

	/**
	 * Mapping between capability keys and payment type keys
	 *
	 * @var array
	 */
	protected $payment_method_capability_key_map;

	/**
	 * WooPay utilities.
	 *
	 * @var WooPay_Utilities
	 */
	protected $woopay_util;

	/**
	 * Duplicate payment prevention service.
	 *
	 * @var Duplicate_Payment_Prevention_Service
	 */
	protected $duplicate_payment_prevention_service;

	/**
	 * WC_Payments_Localization_Service instance.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	protected $localization_service;

	/**
	 * WC_Payments_Fraud_Service instance to get information about fraud services.
	 *
	 * @var WC_Payments_Fraud_Service
	 */
	protected $fraud_service;

	/**
	 * WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client                  - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                              - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service                     - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service                        - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service             - Action Scheduler service instance.
	 * @param Session_Rate_Limiter|null            $failed_transaction_rate_limiter      - Rate Limiter for failed transactions.
	 * @param WC_Payments_Order_Service            $order_service                        - Order class instance.
	 * @param Duplicate_Payment_Prevention_Service $duplicate_payment_prevention_service - Service for preventing duplicate payments.
	 * @param WC_Payments_Localization_Service     $localization_service                 - Localization service instance.
	 * @param WC_Payments_Fraud_Service            $fraud_service                        - Fraud service instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Token_Service $token_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		Session_Rate_Limiter $failed_transaction_rate_limiter = null,
		WC_Payments_Order_Service $order_service,
		Duplicate_Payment_Prevention_Service $duplicate_payment_prevention_service,
		WC_Payments_Localization_Service $localization_service,
		WC_Payments_Fraud_Service $fraud_service
	) {
		$this->payments_api_client                  = $payments_api_client;
		$this->account                              = $account;
		$this->customer_service                     = $customer_service;
		$this->token_service                        = $token_service;
		$this->action_scheduler_service             = $action_scheduler_service;
		$this->failed_transaction_rate_limiter      = $failed_transaction_rate_limiter;
		$this->order_service                        = $order_service;
		$this->duplicate_payment_prevention_service = $duplicate_payment_prevention_service;
		$this->localization_service                 = $localization_service;
		$this->fraud_service                        = $fraud_service;

		$this->id                 = static::GATEWAY_ID;
		$this->icon               = plugins_url( 'assets/images/payment-methods/cc.svg', WCPAY_PLUGIN_FILE );
		$this->has_fields         = true;
		$this->method_title       = 'WooPayments';
		$this->method_description = $this->get_method_description();

		$this->title       = __( 'Credit card / debit card', 'woocommerce-payments' );
		$this->description = __( 'Enter your card details', 'woocommerce-payments' );
		$this->supports    = [
			'products',
			'refunds',
		];

		// Define setting fields.
		$this->form_fields = [
			'enabled'                            => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => sprintf(
					/* translators: %s: WooPayments */
					__( 'Enable %s', 'woocommerce-payments' ),
					'WooPayments'
				),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'account_statement_descriptor'       => [
				'type'        => 'account_statement_descriptor',
				'title'       => __( 'Customer bank statement', 'woocommerce-payments' ),
				'description' => WC_Payments_Utils::esc_interpolated_html(
					__( 'Edit the way your store name appears on your customers’ bank statements (read more about requirements <a>here</a>).', 'woocommerce-payments' ),
					[ 'a' => '<a href="https://woocommerce.com/document/woopayments/customization-and-translation/bank-statement-descriptor/" target="_blank" rel="noopener noreferrer">' ]
				),
			],
			'manual_capture'                     => [
				'title'       => __( 'Manual capture', 'woocommerce-payments' ),
				'label'       => __( 'Issue an authorization on checkout, and capture later.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Charge must be captured within 7 days of authorization, otherwise the authorization and order will be canceled.', 'woocommerce-payments' ),
				'default'     => 'no',
			],
			'saved_cards'                        => [
				'title'       => __( 'Saved cards', 'woocommerce-payments' ),
				'label'       => __( 'Enable payment via saved cards', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on our platform, not on your store.', 'woocommerce-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'test_mode'                          => [
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Simulate transactions using test card numbers.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'enable_logging'                     => [
				'title'       => __( 'Debug log', 'woocommerce-payments' ),
				'label'       => __( 'When enabled debug notes will be added to the log.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'payment_request_details'            => [
				'title'       => __( 'Payment request buttons', 'woocommerce-payments' ),
				'type'        => 'title',
				'description' => '',
			],
			'payment_request'                    => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => sprintf(
					/* translators: 1) br tag 2) Stripe anchor tag 3) Apple anchor tag */
					__( 'Enable payment request buttons (Apple Pay, Google Pay, and more). %1$sBy using Apple Pay, you agree to %2$s and %3$s\'s Terms of Service.', 'woocommerce-payments' ),
					'<br />',
					'<a href="https://stripe.com/apple-pay/legal" target="_blank">Stripe</a>',
					'<a href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/" target="_blank">Apple</a>'
				),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay using Apple Pay, Google Pay or the Payment Request API if supported by the browser.', 'woocommerce-payments' ),
				'default'     => empty( get_option( 'woocommerce_woocommerce_payments_settings' ) ) ? 'yes' : 'no', // Enable by default for new installations only.
				'desc_tip'    => true,
			],
			'payment_request_button_type'        => [
				'title'       => __( 'Button type', 'woocommerce-payments' ),
				'type'        => 'select',
				'description' => __( 'Select the button type you would like to show.', 'woocommerce-payments' ),
				'default'     => 'buy',
				'desc_tip'    => true,
				'options'     => [
					'default' => __( 'Only icon', 'woocommerce-payments' ),
					'buy'     => __( 'Buy', 'woocommerce-payments' ),
					'donate'  => __( 'Donate', 'woocommerce-payments' ),
					'book'    => __( 'Book', 'woocommerce-payments' ),
				],
			],
			'payment_request_button_theme'       => [
				'title'       => __( 'Button theme', 'woocommerce-payments' ),
				'type'        => 'select',
				'description' => __( 'Select the button theme you would like to show.', 'woocommerce-payments' ),
				'default'     => 'dark',
				'desc_tip'    => true,
				'options'     => [
					'dark'          => __( 'Dark', 'woocommerce-payments' ),
					'light'         => __( 'Light', 'woocommerce-payments' ),
					'light-outline' => __( 'Light-Outline', 'woocommerce-payments' ),
				],
			],
			'payment_request_button_height'      => [
				'title'       => __( 'Button height', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Enter the height you would like the button to be in pixels. Width will always be 100%.', 'woocommerce-payments' ),
				'default'     => '44',
				'desc_tip'    => true,
			],
			'payment_request_button_label'       => [
				'title'       => __( 'Custom button label', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Enter the custom text you would like the button to have.', 'woocommerce-payments' ),
				'default'     => __( 'Buy now', 'woocommerce-payments' ),
				'desc_tip'    => true,
			],
			'payment_request_button_locations'   => [
				'title'             => __( 'Button locations', 'woocommerce-payments' ),
				'type'              => 'multiselect',
				'description'       => __( 'Select where you would like to display the button.', 'woocommerce-payments' ),
				'default'           => [
					'product',
					'cart',
					'checkout',
				],
				'class'             => 'wc-enhanced-select',
				'desc_tip'          => true,
				'options'           => [
					'product'  => __( 'Product', 'woocommerce-payments' ),
					'cart'     => __( 'Cart', 'woocommerce-payments' ),
					'checkout' => __( 'Checkout', 'woocommerce-payments' ),
				],
				'custom_attributes' => [
					'data-placeholder' => __( 'Select pages', 'woocommerce-payments' ),
				],
			],
			'upe_enabled_payment_method_ids'     => [
				'title'   => __( 'Payments accepted on checkout', 'woocommerce-payments' ),
				'type'    => 'multiselect',
				'default' => [ 'card' ],
				'options' => [],
			],
			'payment_request_button_size'        => [
				'title'       => __( 'Size of the button displayed for Express Checkouts', 'woocommerce-payments' ),
				'type'        => 'select',
				'description' => __( 'Select the size of the button.', 'woocommerce-payments' ),
				'default'     => 'default',
				'desc_tip'    => true,
				'options'     => [
					'default' => __( 'Default', 'woocommerce-payments' ),
					'medium'  => __( 'Medium', 'woocommerce-payments' ),
					'large'   => __( 'Large', 'woocommerce-payments' ),
				],
			],
			'platform_checkout_button_locations' => [
				'title'             => __( 'WooPay button locations', 'woocommerce-payments' ),
				'type'              => 'multiselect',
				'description'       => __( 'Select where you would like to display the button.', 'woocommerce-payments' ),
				'default'           => [
					'product',
					'cart',
					'checkout',
				],
				'class'             => 'wc-enhanced-select',
				'desc_tip'          => true,
				'options'           => [
					'product'  => __( 'Product', 'woocommerce-payments' ),
					'cart'     => __( 'Cart', 'woocommerce-payments' ),
					'checkout' => __( 'Checkout', 'woocommerce-payments' ),
				],
				'custom_attributes' => [
					'data-placeholder' => __( 'Select pages', 'woocommerce-payments' ),
				],
			],
			'platform_checkout_custom_message'   => [ 'default' => __( 'By placing this order, you agree to our [terms_of_service_link] and understand our [privacy_policy_link].', 'woocommerce-payments' ) ],
		];

		// Capabilities have different keys than the payment method ID's,
		// so instead of appending '_payments' to the end of the ID, it'll be better
		// to have a map for it instead, just in case the pattern changes.
		$this->payment_method_capability_key_map = [
			'sofort'            => 'sofort_payments',
			'giropay'           => 'giropay_payments',
			'bancontact'        => 'bancontact_payments',
			'eps'               => 'eps_payments',
			'ideal'             => 'ideal_payments',
			'p24'               => 'p24_payments',
			'card'              => 'card_payments',
			'sepa_debit'        => 'sepa_debit_payments',
			'au_becs_debit'     => 'au_becs_debit_payments',
			'link'              => 'link_payments',
			'affirm'            => 'affirm_payments',
			'afterpay_clearpay' => 'afterpay_clearpay_payments',
			'klarna'            => 'klarna_payments',
			'jcb'               => 'jcb_payments',
		];

		// WooPay utilities.
		$this->woopay_util = new WooPay_Utilities();

		// Load the settings.
		$this->init_settings();

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// If the setting to enable saved cards is enabled, then we should support tokenization and adding payment methods.
		if ( $this->is_saved_cards_enabled() ) {
			array_push( $this->supports, 'tokenization', 'add_payment_method' );
		}
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Only add certain actions/filter if this is the main gateway (i.e. not split UPE).
		if ( self::GATEWAY_ID === $this->id ) {
			add_action( 'woocommerce_order_actions', [ $this, 'add_order_actions' ] );
			add_action( 'woocommerce_order_action_capture_charge', [ $this, 'capture_charge' ] );
			add_action( 'woocommerce_order_action_cancel_authorization', [ $this, 'cancel_authorization' ] );

			add_action( 'wp_ajax_update_order_status', [ $this, 'update_order_status' ] );
			add_action( 'wp_ajax_nopriv_update_order_status', [ $this, 'update_order_status' ] );

			add_action( 'wp_ajax_create_setup_intent', [ $this, 'create_setup_intent_ajax' ] );
			add_action( 'wp_ajax_nopriv_create_setup_intent', [ $this, 'create_setup_intent_ajax' ] );

			// Update the current request logged_in cookie after a guest user is created to avoid nonce inconsistencies.
			add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );

			add_action( self::UPDATE_SAVED_PAYMENT_METHOD, [ $this, 'update_saved_payment_method' ], 10, 3 );

			// Update the email field position.
			add_filter( 'woocommerce_billing_fields', [ $this, 'checkout_update_email_field_priority' ], 50 );

			add_action( 'woocommerce_update_order', [ $this, 'schedule_order_tracking' ], 10, 2 );

			add_filter( 'rest_request_before_callbacks', [ $this, 'remove_all_actions_on_preflight_check' ], 10, 3 );
		}

		$this->maybe_init_subscriptions_hooks();
	}

	/**
	 * If we're in a WooPay preflight check, remove all the checkout order processed
	 * actions to prevent reduce available resources quantity.
	 *
	 * @param mixed           $response The response object.
	 * @param mixed           $handler The handler used for the response.
	 * @param WP_REST_Request $request The request used to generate the response.
	 *
	 * @return mixed
	 */
	public function remove_all_actions_on_preflight_check( $response, $handler, $request ) {
		$payment_data = $this->get_request_payment_data( $request );
		if ( ! empty( $payment_data['is-woopay-preflight-check'] ) ) {
			remove_all_actions( 'woocommerce_store_api_checkout_update_order_meta' );
			remove_all_actions( 'woocommerce_store_api_checkout_order_processed' );
		}

		return $response;
	}

	/**
	 * Gets and formats payment request data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return array
	 */
	private function get_request_payment_data( \WP_REST_Request $request ) {
		static $payment_data = [];
		if ( ! empty( $payment_data ) ) {
			return $payment_data;
		}
		if ( ! empty( $request['payment_data'] ) ) {
			foreach ( $request['payment_data'] as $data ) {
				$payment_data[ sanitize_key( $data['key'] ) ] = wc_clean( $data['value'] );
			}
		}

		return $payment_data;
	}

	/**
	 * Proceed with current request using new login session (to ensure consistent nonce).
	 *
	 * @param string $cookie New cookie value.
	 */
	public function set_cookie_on_current_request( $cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	}

	/**
	 * Check if the payment gateway is connected. This method is also used by
	 * external plugins to check if a connection has been established.
	 */
	public function is_connected() {
		return $this->account->is_stripe_connected();
	}

	/**
	 * Checks if the account has not completed onboarding due to users abandoning the process half way.
	 * Also used by WC Core to complete the task "Set up WooPayments".
	 *
	 * @return bool
	 */
	public function is_account_partially_onboarded(): bool {
		return $this->account->is_account_partially_onboarded();
	}

	/**
	 * Returns true if the gateway needs additional configuration, false if it's ready to use.
	 *
	 * @see WC_Payment_Gateway::needs_setup
	 * @return bool
	 */
	public function needs_setup() {
		if ( ! $this->is_connected() ) {
			return true;
		}

		$account_status = $this->account->get_account_status_data();
		return parent::needs_setup() || ! empty( $account_status['error'] ) || ! $account_status['paymentsEnabled'];
	}

	/**
	 * Returns whether a store that is not in test mode needs to set https
	 * in the checkout
	 *
	 * @return boolean True if needs to set up forced ssl in checkout or https
	 */
	public function needs_https_setup() {
		return ! WC_Payments::mode()->is_test() && ! wc_checkout_is_https();
	}

	/**
	 * Checks if the gateway is enabled, and also if it's configured enough to accept payments from customers.
	 *
	 * Use parent method value alongside other business rules to make the decision.
	 *
	 * @return bool Whether the gateway is enabled and ready to accept payments.
	 */
	public function is_available() {
		// Disable the gateway if using live mode without HTTPS set up or the currency is not
		// available in the country of the account.
		if ( $this->needs_https_setup() || ! $this->is_available_for_current_currency() ) {
			return false;
		}

		return parent::is_available() && ! $this->needs_setup();
	}

	/**
	 * Overrides the parent method by adding an additional check to see if the tokens list is empty.
	 * If it is, the method avoids displaying the HTML element with an empty line to maintain a clean user interface and remove unnecessary space.
	 *
	 * @return void
	 */
	public function saved_payment_methods() {
		if ( empty( $this->get_tokens() ) ) {
			return;
		}

		parent::saved_payment_methods();
	}

	/**
	 * Checks if the setting to allow the user to save cards is enabled.
	 *
	 * @return bool Whether the setting to allow saved cards is enabled or not.
	 */
	public function is_saved_cards_enabled() {
		return 'yes' === $this->get_option( 'saved_cards' );
	}

	/**
	 * Check if account is eligible for card present.
	 *
	 * @return bool
	 */
	public function is_card_present_eligible(): bool {
		try {
			return $this->account->is_card_present_eligible();
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account card present eligible. ' . $e );
			return false;
		}
	}

	/**
	 * Check if account is eligible for card testing protection.
	 *
	 * @return bool
	 */
	public function is_card_testing_protection_eligible(): bool {
		try {
			return $this->account->is_card_testing_protection_eligible();
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account card testing protection eligible. ' . $e );
			return false;
		}
	}

	/**
	 * Checks if the account country is compatible with the current currency.
	 *
	 * @return bool Whether the currency is supported in the country set in the account.
	 */
	public function is_available_for_current_currency() {
		$supported_currencies = $this->account->get_account_customer_supported_currencies();
		$current_currency     = strtolower( get_woocommerce_currency() );

		if ( count( $supported_currencies ) === 0 ) {
			// If we don't have info related to the supported currencies
			// of the country, we won't disable the gateway.
			return true;
		}

		return in_array( $current_currency, $supported_currencies, true );
	}

	/**
	 * Admin Panel Options.
	 */
	public function admin_options() {
		// Add notices to the WooPayments settings page.
		do_action( 'woocommerce_woocommerce_payments_admin_notices' );

		$this->output_payments_settings_screen();
	}

	/**
	 * Generates markup for the settings screen.
	 */
	public function output_payments_settings_screen() {
		// hiding the save button because the react container has its own.
		global $hide_save_button;
		$hide_save_button = true;

		if ( ! empty( $_GET['method'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div
				id="wcpay-express-checkout-settings-container"
				data-method-id="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"
			></div>
<?php else : ?>
			<div id="wcpay-account-settings-container"></div>
			<?php
		endif;
	}

	/**
	 * Displays the save to account checkbox.
	 *
	 * @param bool $force_checked True if the checkbox must be forced to "checked" state (and invisible).
	 */
	public function save_payment_method_checkbox( $force_checked = false ) {
		$id          = 'wc-' . $this->id . '-new-payment-method';
		$should_hide = $force_checked || $this->should_use_stripe_platform_on_checkout_page();
		?>
		<div <?php echo $should_hide ? 'style="display:none;"' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>>
			<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" type="checkbox" value="true" style="width:auto; vertical-align: middle; position: relative; bottom: 1px;" <?php echo $force_checked ? 'checked' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?> />
				<label for="<?php echo esc_attr( $id ); ?>" style="display:inline;">
					<?php echo esc_html( apply_filters( 'wc_payments_save_to_account_text', __( 'Save payment information to my account for future purchases.', 'woocommerce-payments' ) ) ); ?>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Whether we should use the platform account to initialize Stripe on the checkout page.
	 *
	 * @return bool
	 */
	public function should_use_stripe_platform_on_checkout_page() {
		if (
			WC_Payments_Features::is_woopay_eligible() &&
			'yes' === $this->get_option( 'platform_checkout', 'no' ) &&
			! $this->is_upe_incompatible_with_woopay() &&
			( is_checkout() || has_block( 'woocommerce/checkout' ) ) &&
			! is_wc_endpoint_url( 'order-pay' ) &&
			WC()->cart instanceof WC_Cart &&
			! WC()->cart->is_empty() &&
			WC()->cart->needs_payment()
		) {
			return true;
		}

		return false;
	}

	/**
	 * The legacy UPE is incompatible with WooPay, whereas split UPE and deferred intent UPE are compatible.
	 * This method checks if there's incompatibility between WooPay and currently enabled UPE settings, applying the rule above.
	 *
	 * $return bool - true if UPE is incompatible with WooPay, false otherwise.
	 */
	private function is_upe_incompatible_with_woopay() {
		return WC_Payments_Features::is_upe_legacy_enabled() && ! WC_Payments_Features::is_upe_deferred_intent_enabled();
	}

	/**
	 * Renders the credit card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		do_action( 'wc_payments_add_payment_fields' );
	}

	/**
	 * Checks whether the new payment process should be used to pay for a given order.
	 *
	 * @param WC_Order $order Order that's being paid.
	 * @return bool
	 */
	public function should_use_new_process( WC_Order $order ) {
		$order_id = $order->get_id();

		// The new process us under active development, and not ready for production yet.
		if ( ! WC_Payments::mode()->is_dev() ) {
			return false;
		}

		// This array will contain all factors, present during checkout.
		$factors = [
			/**
			 * The new payment process is a factor itself.
			 * Even if no other factors are present, this will make entering
			 * the new payment process possible only if this factor is allowed.
			 */
			Factor::NEW_PAYMENT_PROCESS(),
		];

		// If there is a token in the request, we're using a saved PM.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$using_saved_payment_method = ! empty( Payment_Information::get_token_from_request( $_POST ) );
		if ( $using_saved_payment_method ) {
			$factors[] = Factor::USE_SAVED_PM();
		}

		// The PM should be saved when chosen, or when it's a recurrent payment, but not if already saved.
		$save_payment_method = ! $using_saved_payment_method && (
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			! empty( $_POST[ 'wc-' . static::GATEWAY_ID . '-new-payment-method' ] )
			|| $this->is_payment_recurring( $order_id )
		);
		if ( $save_payment_method ) {
			$factors[] = Factor::SAVE_PM();
		}

		// In case amount is 0 and we're not saving the payment method, we won't be using intents and can confirm the order payment.
		if (
			apply_filters(
				'wcpay_confirm_without_payment_intent',
				$order->get_total() <= 0 && ! $save_payment_method
			)
		) {
			$factors[] = Factor::NO_PAYMENT();
		}

		// Subscription (both WCPay and WCSubs) if when the order contains one.
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$factors[] = Factor::SUBSCRIPTION_SIGNUP();
		}

		// WooPay might change how payment fields were loaded.
		if (
			$this->woopay_util->should_enable_woopay( $this )
			&& $this->woopay_util->should_enable_woopay_on_cart_or_checkout()
		) {
			$factors[] = Factor::WOOPAY_ENABLED();
		}

		// WooPay payments are indicated by the platform checkout intent.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['platform-checkout-intent'] ) ) {
			$factors[] = Factor::WOOPAY_PAYMENT();
		}

		// Check whether the customer is signining up for a WCPay subscription.
		if (
			function_exists( 'wcs_order_contains_subscription' )
			&& wcs_order_contains_subscription( $order_id )
			&& WC_Payments_Features::should_use_stripe_billing()
		) {
			$factors[] = Factor::WCPAY_SUBSCRIPTION_SIGNUP();
		}

		if ( $this instanceof UPE_Split_Payment_Gateway ) {
			$factors[] = Factor::DEFERRED_INTENT_SPLIT_UPE();
		}

		if ( defined( 'WCPAY_PAYMENT_REQUEST_CHECKOUT' ) && WCPAY_PAYMENT_REQUEST_CHECKOUT ) {
			$factors[] = Factor::PAYMENT_REQUEST();
		}

		$router = wcpay_get_container()->get( Router::class );
		return $router->should_use_new_payment_process( $factors );
	}

	/**
	 * Checks whether the new payment process should be entered,
	 * and if the answer is yes, uses it and returns the result.
	 *
	 * @param WC_Order $order Order that needs payment.
	 * @return array|null     Array if processed, null if the new process is not supported.
	 * @throws Exception      If the payment process could not be completed.
	 */
	public function new_process_payment( WC_Order $order ) {
		$manual_capture = $this->get_capture_type() === Payment_Capture_Type::MANUAL();

		// Important: No factors are provided here, they were meant just for `Feature`.
		$service = wcpay_get_container()->get( PaymentProcessingService::class );
		$state   = $service->process_payment( $order->get_id(), $manual_capture );

		if ( $state instanceof DuplicateOrderDetectedState ) {
			$duplicate_order_return_url = add_query_arg(
				DuplicatePaymentPreventionService::FLAG_PREVIOUS_ORDER_PAID,
				'yes',
				$this->get_return_url( wc_get_order( $state->get_context()->get_duplicate_order_id() ) )
			);

			return [ // nosemgrep: audit.php.wp.security.xss.query-arg -- https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html#method_get_return_url is passed in.
				'result'   => 'success',
				'redirect' => $duplicate_order_return_url,
			];
		}

		if ( $state instanceof CompletedState ) {
			$return_url = $this->get_return_url( $order );
			if ( $state->get_context()->is_detected_authorized_intent() ) {
				$return_url = add_query_arg(
					DuplicatePaymentPreventionService::FLAG_PREVIOUS_SUCCESSFUL_INTENT,
					'yes',
					$return_url
				);
			}

			return [ // nosemgrep: audit.php.wp.security.xss.query-arg -- https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html#method_get_return_url is passed in.
				'result'   => 'success',
				'redirect' => $return_url,
			];
		}

		if ( $state instanceof AuthenticationRequiredState ) {
			$context = $state->get_context();
			return [
				'result'   => 'success',
				'redirect' => $service->get_authentication_redirect_url( $context->get_intent(), $context->get_order_id() ),
			];
		}

		throw new Exception( __( 'The payment process could not be completed.', 'woocommerce-payments' ) );
	}

	/**
	 * Process the payment for a given order.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 * @throws Process_Payment_Exception Error processing the payment.
	 * @throws Exception Error processing the payment.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Use the new payment process if allowed.
		if ( $this->should_use_new_process( $order ) ) {
			return $this->new_process_payment( $order );
		}

		try {
			if ( 20 < strlen( $order->get_billing_phone() ) ) {
				throw new Process_Payment_Exception(
					__( 'Invalid phone number.', 'woocommerce-payments' ),
					'invalid_phone_number'
				);
			}
			// Check if session exists before instantiating Fraud_Prevention_Service.
			if ( WC()->session ) {
				$fraud_prevention_service = Fraud_Prevention_Service::get_instance();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( $fraud_prevention_service->is_enabled() && ! $fraud_prevention_service->verify_token( $_POST['wcpay-fraud-prevention-token'] ?? null ) ) {
					throw new Process_Payment_Exception(
						__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
						'fraud_prevention_enabled'
					);
				}
			}

			if ( $this->failed_transaction_rate_limiter->is_limited() ) {
				throw new Process_Payment_Exception(
					__( 'Your payment was not processed.', 'woocommerce-payments' ),
					'rate_limiter_enabled'
				);
			}

			// The request is a preflight check from WooPay.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! empty( $_POST['is-woopay-preflight-check'] ) ) {
				// Set the order status to "pending payment".
				$order->update_status( 'pending' );

				// Bail out with success so we don't process the payment now,
				// but still let WooPay continue with the payment processing.
				return [
					'result'   => 'success',
					'redirect' => '',
				];
			}

			if ( WC_Payments_Features::is_upe_legacy_enabled() ) {
				UPE_Payment_Gateway::remove_upe_payment_intent_from_session();
			}

			$check_session_order = $this->duplicate_payment_prevention_service->check_against_session_processing_order( $order );
			if ( is_array( $check_session_order ) ) {
				return $check_session_order;
			}
			$this->duplicate_payment_prevention_service->maybe_update_session_processing_order( $order_id );

			$check_existing_intention = $this->duplicate_payment_prevention_service->check_payment_intent_attached_to_order_succeeded( $order );
			if ( is_array( $check_existing_intention ) ) {
				return $check_existing_intention;
			}

			$payment_information = $this->prepare_payment_information( $order );
			return $this->process_payment_for_order( WC()->cart, $payment_information );
		} catch ( Exception $e ) {
			// We set this variable to be used in following checks.
			$blocked_due_to_fraud_rules = $e instanceof API_Exception && 'wcpay_blocked_by_fraud_rule' === $e->get_error_code();

			do_action( 'woocommerce_payments_order_failed', $order, $e );

			/**
			 * TODO: Determine how to do this update with Order_Service.
			 * It seems that the status only needs to change in certain instances, and within those instances the intent
			 * information is not added to the order, as shown by tests.
			 */
			if ( ! $blocked_due_to_fraud_rules && ( empty( $payment_information ) || ! $payment_information->is_changing_payment_method_for_subscription() ) ) {
				$order->update_status( Order_Status::FAILED );
			}

			if ( $e instanceof API_Exception && $this->should_bump_rate_limiter( $e->get_error_code() ) ) {
				$this->failed_transaction_rate_limiter->bump();
			}

			if ( $blocked_due_to_fraud_rules ) {
				$this->order_service->mark_order_blocked_for_fraud( $order, '', Intent_Status::CANCELED );
			} elseif ( ! empty( $payment_information ) ) {
				/**
				 * TODO: Move the contents of this else into the Order_Service.
				 */
				/* translators: %1: the failed payment amount, %2: error message  */
				$error_message = __(
					'A payment of %1$s <strong>failed</strong> to complete with the following message: <code>%2$s</code>.',
					'woocommerce-payments'
				);

				$error_details = esc_html( rtrim( $e->getMessage(), '.' ) );

				if ( $e instanceof API_Exception && 'card_error' === $e->get_error_type() ) {
					// If the payment failed with a 'card_error' API exception, initialize the fraud meta box
					// type with ALLOW, because fraud checks are passed, and the payment returned a "card error".
					$this->order_service->set_fraud_meta_box_type_for_order( $order, Fraud_Meta_Box_Type::ALLOW );

					if ( 'incorrect_zip' === $e->get_error_code() ) {
						/* translators: %1: the failed payment amount, %2: error message  */
						$error_message = __(
							'A payment of %1$s <strong>failed</strong>. %2$s',
							'woocommerce-payments'
						);

						$error_details = __(
							'We couldn’t verify the postal code in the billing address. If the issue persists, suggest the customer to reach out to the card issuing bank.',
							'woocommerce-payments'
						);
					}
				}

				$note = sprintf(
					WC_Payments_Utils::esc_interpolated_html(
						$error_message,
						[
							'strong' => '<strong>',
							'code'   => '<code>',
						]
					),
					WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ), $order ),
					$error_details
				);

				$order->add_order_note( $note );
			}

			if ( $e instanceof Process_Payment_Exception && 'rate_limiter_enabled' === $e->get_error_code() ) {
				/**
				 * TODO: Move the contents of this into the Order_Service.
				 */
				$note = sprintf(
					WC_Payments_Utils::esc_interpolated_html(
						/* translators: %1: the failed payment amount */
						__(
							'A payment of %1$s <strong>failed</strong> to complete because of too many failed transactions. A rate limiter was enabled for the user to prevent more attempts temporarily.',
							'woocommerce-payments'
						),
						[
							'strong' => '<strong>',
						]
					),
					WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ), $order )
				);
				$order->add_order_note( $note );
			}

			if ( WC_Payments_Features::is_upe_legacy_enabled() ) {
				UPE_Payment_Gateway::remove_upe_payment_intent_from_session();
			}

			// Re-throw the exception after setting everything up.
			// This makes the error notice show up both in the regular and block checkout.
			throw new Exception( WC_Payments_Utils::get_filtered_error_message( $e ) );
		}
	}

	/**
	 * Prepares the payment information object.
	 *
	 * @param WC_Order $order The order whose payment will be processed.
	 * @return Payment_Information An object, which describes the payment.
	 */
	protected function prepare_payment_information( $order ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_information = Payment_Information::from_payment_request( $_POST, $order, Payment_Type::SINGLE(), Payment_Initiated_By::CUSTOMER(), $this->get_capture_type(), $this->get_payment_method_to_use_for_intent() );
		$payment_information = $this->maybe_prepare_subscription_payment_information( $payment_information, $order->get_id() );

		if ( ! empty( $_POST[ 'wc-' . static::GATEWAY_ID . '-new-payment-method' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// During normal orders the payment method is saved when the customer enters a new one and chooses to save it.
			$payment_information->must_save_payment_method_to_store();
		}

		if ( $this->woopay_util->should_save_platform_customer() ) {
			do_action( 'woocommerce_payments_save_user_in_woopay' );
			$payment_information->must_save_payment_method_to_platform();
		}

		return $payment_information;
	}

	/**
	 * Update the customer details with the incoming order data, in a CRON job.
	 *
	 * @param \WC_Order $order        WC order id.
	 * @param string    $customer_id  The customer id to update details for.
	 * @param bool      $is_test_mode Whether to run the CRON job in test mode.
	 * @param bool      $is_woopay    Whether CRON job was queued from WooPay.
	 */
	public function update_customer_with_order_data( $order, $customer_id, $is_test_mode = false, $is_woopay = false ) {
		// Since this CRON job may have been created in test_mode, when the CRON job runs, it
		// may lose the test_mode context. So, instead, we pass that context when creating
		// the CRON job and apply the context here.
		$apply_test_mode_context = function () use ( $is_test_mode ) {
			return $is_test_mode;
		};
		add_filter( 'wcpay_test_mode', $apply_test_mode_context );

		$user = $order->get_user();
		if ( false === $user ) {
			$user = wp_get_current_user();
		}

		// Since this function will run in a CRON job, "wp_get_current_user()" will default
		// to user with ID of 0. So, instead, we replace it with the user from the $order,
		// when updating a WooPay user.
		$apply_order_user_email = function ( $params ) use ( $user, $is_woopay ) {
			if ( $is_woopay ) {
				$params['email'] = $user->user_email;
			}

			return $params;
		};
		add_filter( 'wcpay_api_request_params', $apply_order_user_email, 20, 1 );

		// Update the existing customer with the current order details.
		$customer_data = WC_Payments_Customer_Service::map_customer_data( $order, new WC_Customer( $user->ID ) );
		$this->customer_service->update_customer_for_user( $customer_id, $user, $customer_data );
	}

	/**
	 * Manages customer details held on WCPay server for WordPress user associated with an order.
	 *
	 * @param WC_Order $order   WC Order object.
	 * @param array    $options Additional options to apply.
	 *
	 * @return array First element is the new or updated WordPress user, the second element is the WCPay customer ID.
	 */
	protected function manage_customer_details_for_order( $order, $options = [] ) {
		$user = $order->get_user();
		if ( false === $user ) {
			$user = wp_get_current_user();
		}

		// Determine the customer making the payment, create one if we don't have one already.
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );

		if ( null === $customer_id ) {
			$customer_data = WC_Payments_Customer_Service::map_customer_data( $order, new WC_Customer( $user->ID ) );
			// Create a new customer.
			$customer_id = $this->customer_service->create_customer_for_user( $user, $customer_data );
		} else {
			// Update the customer with order data async.
			$this->update_customer_with_order_data( $order, $customer_id, WC_Payments::mode()->is_test(), $options['is_woopay'] ?? false );
		}

		return [ $user, $customer_id ];
	}

	/**
	 * Update the saved payment method information with checkout values, in a CRON job.
	 *
	 * @param string $payment_method The payment method to update.
	 * @param int    $order_id       WC order id.
	 * @param bool   $is_test_mode   Whether to run the CRON job in test mode.
	 */
	public function update_saved_payment_method( $payment_method, $order_id, $is_test_mode = false ) {
		// Since this CRON job may have been created in test_mode, when the CRON job runs, it
		// may lose the test_mode context. So, instead, we pass that context when creating
		// the CRON job and apply the context here.
		$apply_test_mode_context = function () use ( $is_test_mode ) {
			return $is_test_mode;
		};
		add_filter( 'wcpay_test_mode', $apply_test_mode_context );

		$order = wc_get_order( $order_id );

		try {
			$this->customer_service->update_payment_method_with_billing_details_from_order( $payment_method, $order );
		} catch ( Exception $e ) {
			// If updating the payment method fails, log the error message.
			Logger::log( 'Error when updating saved payment method: ' . $e->getMessage() );
		}
	}

	/**
	 * Process the payment for a given order.
	 *
	 * @param WC_Cart|null              $cart Cart.
	 * @param WCPay\Payment_Information $payment_information Payment info.
	 * @param bool                      $scheduled_subscription_payment Used to determinate is scheduled subscription payment to add more fields into API request.
	 *
	 * @return array|null                      An array with result of payment and redirect URL, or nothing.
	 * @throws API_Exception
	 * @throws Intent_Authentication_Exception When the payment intent could not be authenticated.
	 * @throws \WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception When request class filter filed to extend request class because of incompatibility.
	 * @throws \WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception When immutable parameter gets changed in request class.
	 * @throws \WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception When you send incorrect request value via setters.
	 */
	public function process_payment_for_order( $cart, $payment_information, $scheduled_subscription_payment = false ) {
		$order                                       = $payment_information->get_order();
		$save_payment_method_to_store                = $payment_information->should_save_payment_method_to_store();
		$is_changing_payment_method_for_subscription = $payment_information->is_changing_payment_method_for_subscription();

		$order_id = $order->get_id();
		$amount   = $order->get_total();
		$metadata = $this->get_metadata_from_order( $order, $payment_information->get_payment_type() );

		$customer_details_options   = [
			'is_woopay' => filter_var( $metadata['paid_on_woopay'] ?? false, FILTER_VALIDATE_BOOLEAN ),
		];
		list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order, $customer_details_options );

		// Update saved payment method async to include billing details, if missing.
		if ( $payment_information->is_using_saved_payment_method() ) {
			$this->action_scheduler_service->schedule_job(
				time(),
				self::UPDATE_SAVED_PAYMENT_METHOD,
				[
					'payment_method' => $payment_information->get_payment_method(),
					'order_id'       => $order->get_id(),
					'is_test_mode'   => WC_Payments::mode()->is_test(),
				]
			);
		}

		$intent_failed  = false;
		$payment_needed = $amount > 0;

		// Make sure that we attach the payment method and the customer ID to the order meta data.
		$payment_method = $payment_information->get_payment_method();
		$this->order_service->set_payment_method_id_for_order( $order, $payment_method );
		$this->order_service->set_customer_id_for_order( $order, $customer_id );
		$order->update_meta_data( '_wcpay_mode', WC_Payments::mode()->is_test() ? 'test' : 'prod' );

		// In case amount is 0 and we're not saving the payment method, we won't be using intents and can confirm the order payment.
		if ( apply_filters( 'wcpay_confirm_without_payment_intent', ! $payment_needed && ! $save_payment_method_to_store ) ) {
			$order->payment_complete();

			if ( $payment_information->is_using_saved_payment_method() ) {
				// We need to make sure the saved payment method is saved to the order so we can
				// charge the payment method for a future payment.
				$this->add_token_to_order( $order, $payment_information->get_payment_token() );
			}

			if ( $is_changing_payment_method_for_subscription && $payment_information->is_using_saved_payment_method() ) {
				$payment_token = $payment_information->get_payment_token();
				$note          = sprintf(
					WC_Payments_Utils::esc_interpolated_html(
						/* translators: %1: the last 4 digit of the credit card */
						__( 'Payment method is changed to: <strong>Credit card ending in %1$s</strong>.', 'woocommerce-payments' ),
						[
							'strong' => '<strong>',
						]
					),
					$payment_token instanceof WC_Payment_Token_CC ? $payment_token->get_last4() : '----'
				);
				$order->add_order_note( $note );

				do_action( 'woocommerce_payments_changed_subscription_payment_method', $order, $payment_token );
			}

			$order->set_payment_method_title( __( 'Credit / Debit Card', 'woocommerce-payments' ) );
			$order->save();

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];
		}

		if ( $payment_needed ) {
			$converted_amount = WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() );
			$currency         = strtolower( $order->get_currency() );

			// Try catching the error without reaching the API.
			$minimum_amount = WC_Payments_Utils::get_cached_minimum_amount( $currency );
			if ( $minimum_amount > $converted_amount ) {
				$e = new Amount_Too_Small_Exception( 'Amount too small', $minimum_amount, $currency, 400 );
				throw new Exception( WC_Payments_Utils::get_filtered_error_message( $e ) );
			}

			$payment_methods = $this->get_payment_method_types( $payment_information );
			// The sanitize_user call here is deliberate: it seems the most appropriate sanitization function
			// for a string that will only contain latin alphanumeric characters and underscores.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$woopay_intent_id = sanitize_user( wp_unslash( $_POST['platform-checkout-intent'] ?? '' ), true );

			// Initializing the intent variable here to ensure we don't try to use an undeclared
			// variable later.
			$intent = null;
			if ( ! empty( $woopay_intent_id ) ) {
				// If the intent is included in the request use that intent.
				$request = Get_Intention::create( $woopay_intent_id );
				$request->set_hook_args( $order );
				$intent = $request->send();

				$intent_meta_order_id_raw = $intent->get_metadata()['order_id'] ?? '';
				$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;
				if ( $intent_meta_order_id !== $order_id ) {
					throw new Intent_Authentication_Exception(
						__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
						'order_id_mismatch'
					);
				}
			}

			if ( empty( $intent ) ) {
				$request = Create_And_Confirm_Intention::create();
				$request->set_amount( $converted_amount );
				$request->set_currency_code( $currency );
				$request->set_payment_method( $payment_information->get_payment_method() );
				$request->set_customer( $customer_id );
				$request->set_capture_method( $payment_information->is_using_manual_capture() );
				$request->set_metadata( $metadata );
				$request->set_level3( $this->get_level3_data_from_order( $order ) );
				$request->set_off_session( $payment_information->is_merchant_initiated() );
				$request->set_payment_methods( $payment_methods );
				$request->set_cvc_confirmation( $payment_information->get_cvc_confirmation() );
				$request->set_hook_args( $payment_information );
				// Add specific payment method parameters to the request.
				$this->modify_create_intent_parameters_when_processing_payment( $request, $payment_information, $order );

				// The below if-statement ensures the support for UPE payment methods.
				if ( $this->upe_needs_redirection( $payment_methods ) ) {
					$request->set_return_url(
						wp_sanitize_redirect(
							esc_url_raw(
								add_query_arg(
									[
										'wc_payment_method' => self::GATEWAY_ID,
										'_wpnonce' => wp_create_nonce( 'wcpay_process_redirect_order_nonce' ),
									],
									$this->get_return_url( $order )
								)
							)
						)
					);
				}

				// Make sure that setting fingerprint is performed after setting metadata because metadata will override any values you set before for metadata param.
				$request->set_fingerprint( $payment_information->get_fingerprint() );
				if ( $save_payment_method_to_store ) {
					$request->setup_future_usage();
				}
				if ( $scheduled_subscription_payment ) {
					$mandate = $this->get_mandate_param_for_renewal_order( $order );
					if ( $mandate ) {
						$request->set_mandate( $mandate );
					}
				}

				// For Stripe Link & SEPA with deferred intent UPE, we must create mandate to acknowledge that terms have been shown to customer.
				if ( WC_Payments_Features::is_upe_deferred_intent_enabled() && $this->is_mandate_data_required() ) {
					$request->set_mandate_data( $this->get_mandate_data() );
				}

				/** @var WC_Payments_API_Payment_Intention $intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent = $request->send();
			}

			$intent_id     = $intent->get_id();
			$status        = $intent->get_status();
			$charge        = $intent->get_charge();
			$charge_id     = $charge ? $charge->get_id() : null;
			$client_secret = $intent->get_client_secret();
			$currency      = $intent->get_currency();
			$next_action   = $intent->get_next_action();
			$processing    = $intent->get_processing();
			// We update the payment method ID server side when it's necessary to clone payment methods,
			// for example when saving a payment method to a platform customer account. When this happens
			// we need to make sure the payment method on the order matches the one on the merchant account
			// not the one on the platform account. The payment method ID is updated on the order further
			// down.
			$payment_method = $intent->get_payment_method_id() ?? $payment_method;

			if ( Intent_Status::REQUIRES_ACTION === $status && $payment_information->is_merchant_initiated() ) {
				// Allow 3rd-party to trigger some action if needed.
				do_action( 'woocommerce_woocommerce_payments_payment_requires_action', $order, $intent_id, $payment_method, $customer_id, $charge_id, $currency );
				$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id );
			}
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$woopay_intent_id = sanitize_user( wp_unslash( $_POST['platform-checkout-intent'] ?? '' ), true );

			if ( ! empty( $woopay_intent_id ) ) {
				// If the setup intent is included in the request use that intent.
				$setup_intent_request = Get_Setup_Intention::create( $woopay_intent_id );
				/** @var WC_Payments_API_Setup_Intention $setup_intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent = $setup_intent_request->send();

				$intent_metadata          = $intent->get_metadata();
				$intent_meta_order_id_raw = $intent_metadata['order_id'] ?? '';
				$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;

				if ( $intent_meta_order_id !== $order_id ) {
					throw new Intent_Authentication_Exception(
						__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
						'order_id_mismatch'
					);
				}
			} else {
				$save_user_in_woopay = false;

				if ( $this->woopay_util->should_save_platform_customer() ) {
					$save_user_in_woopay = true;
					$metadata_from_order = apply_filters(
						'wcpay_metadata_from_order',
						[
							'customer_email' => $order->get_billing_email(),
						],
						$order
					);
					$metadata            = array_merge( (array) $metadata_from_order, (array) $metadata ); // prioritize metadata from mobile app.

					do_action( 'woocommerce_payments_save_user_in_woopay' );
				}

				// For $0 orders, we need to save the payment method using a setup intent.
				$request = Create_And_Confirm_Setup_Intention::create();
				$request->set_customer( $customer_id );
				$request->set_payment_method( $payment_information->get_payment_method() );
				$request->set_metadata( $metadata );
				$request->assign_hook( 'wcpay_create_and_confirm_setup_intention_request' );
				$request->set_hook_args( $payment_information, false, $save_user_in_woopay );

				if (
					WC_Payments_Features::is_upe_deferred_intent_enabled() &&
					Payment_Method::CARD === $this->get_selected_stripe_payment_type_id() &&
					in_array( Payment_Method::LINK, $this->get_upe_enabled_payment_method_ids(), true )
					) {
					$request->set_payment_method_types( $this->get_payment_method_types( $payment_information ) );
					$request->set_mandate_data( $this->get_mandate_data() );
				}

				/** @var WC_Payments_API_Setup_Intention $intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent = $request->send();
			}

			$intent_id     = $intent->get_id();
			$status        = $intent->get_status();
			$charge_id     = '';
			$charge        = null;
			$client_secret = $intent->get_client_secret();
			$currency      = $order->get_currency();
			$next_action   = $intent->get_next_action();
			$processing    = [];
		}

		if ( ! empty( $intent ) ) {
			if ( ! $intent->is_authorized() ) {
				$intent_failed = true;
			}

			if ( $save_payment_method_to_store && ! $intent_failed ) {
				try {
					$token = null;

					// For WooPay checkouts, we may provide a platform payment method from `$payment_information`, but we need
					// to return a connected payment method. So we should always retrieve the payment method from the intent.
					$payment_method_id = $intent->get_payment_method_id();

					// Handle orders that are paid via WooPay and contain subscriptions.
					if ( $order->get_meta( 'is_woopay' ) && function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order ) ) {

						$customer_tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_user_id(), self::GATEWAY_ID );

						// Use the existing token if we already have one for the incoming payment method.
						foreach ( $customer_tokens as $saved_token ) {
							if ( $saved_token->get_token() === $payment_method_id ) {
								$token = $saved_token;
								break;
							}
						}
					}

					// Store a new token if we're not paying for a subscription in WooPay,
					// or if we are, but we didn't find a stored token for the selected payment method.
					if ( empty( $token ) ) {
						$token = $this->token_service->add_payment_method_to_user( $payment_method_id, $user );
					}
					$payment_information->set_token( $token );
				} catch ( Exception $e ) {
					// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
					Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
				}
			}

			if ( $payment_information->is_using_saved_payment_method() ) {
				$token = $payment_information->get_payment_token();
				$this->add_token_to_order( $order, $token );

				if ( $order->get_meta( '_woopay_has_subscription' ) ) {
					$token->update_meta_data( 'is_attached_to_subscription', '1' );
					$token->save_meta_data();
				}
			}

			if ( Intent_Status::REQUIRES_ACTION === $status ) {
				if ( isset( $next_action['type'] ) && 'redirect_to_url' === $next_action['type'] && ! empty( $next_action['redirect_to_url']['url'] ) ) {
					$response = [
						'result'   => 'success',
						'redirect' => $next_action['redirect_to_url']['url'],
					];
				} else {
					$response = [
						'result'         => 'success',
						// Include a new nonce for update_order_status to ensure the update order
						// status call works when a guest user creates an account during checkout.
						'redirect'       => sprintf(
							'#wcpay-confirm-%s:%s:%s:%s',
							$payment_needed ? 'pi' : 'si',
							$order_id,
							WC_Payments_Utils::encrypt_client_secret( $this->account->get_stripe_account_id(), $client_secret ),
							wp_create_nonce( 'wcpay_update_order_status_nonce' )
						),
						// Include the payment method ID so the Blocks integration can save cards.
						'payment_method' => $payment_information->get_payment_method(),
					];
				}
			}
		}

		$this->order_service->attach_intent_info_to_order( $order, $intent_id, $status, $payment_method, $customer_id, $charge_id, $currency );
		$this->attach_exchange_info_to_order( $order, $charge_id );
		if ( Intent_Status::SUCCEEDED === $status ) {
			$this->duplicate_payment_prevention_service->remove_session_processing_order( $order->get_id() );
		}
		$this->order_service->update_order_status_from_intent( $order, $intent );
		$this->order_service->attach_transaction_fee_to_order( $order, $charge );

		$this->maybe_add_customer_notification_note( $order, $processing );

		if ( isset( $response ) ) {
			return $response;
		}

		wc_reduce_stock_levels( $order_id );
		if ( isset( $cart ) ) {
			$cart->empty_cart();
		}

		if ( $payment_needed ) {
			$charge                 = $intent ? $intent->get_charge() : null;
			$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
			$payment_method_type    = $payment_method_details ? $payment_method_details['type'] : null;

			if ( $order->get_meta( 'is_woopay' ) && 'card' === $payment_method_type && isset( $payment_method_details['card']['last4'] ) ) {
				$order->add_meta_data( 'last4', $payment_method_details['card']['last4'], true );
				$order->save_meta_data();
			}
		} else {
			$payment_method_details = false;
			$payment_method_type    = $intent->get_payment_method_type();
		}

		if ( empty( $_POST['payment_request_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details );
		}

		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * The parent method which allows to modify the child class implementation, while supporting the current design where the parent process_payment method is called from the child class.
	 * Mandate must be shown and acknowledged under certain conditions for Stripe Link and SEPA.
	 * Since WC_Payment_Gateway_WCPay represents card payment, which does not require mandate, we return false.
	 *
	 * @return boolean False since card payment does not require mandate.
	 */
	protected function is_mandate_data_required() {
		return false;
	}

	/**
	 * Get the payment method chosen by the customer for the payment processing.
	 * This payment method is needed in case of the deferred intent creation flow only, because this is the only time when the current gateway might process payments other than of the card type.
	 * Payment method is only one in case of the deferred intent creation flow, hence the first element of the array is returned.
	 *
	 * @return string|null Payment method to use for the intent.
	 */
	public function get_payment_method_to_use_for_intent() {
		if ( WC_Payments_Features::is_upe_deferred_intent_enabled() ) {
			$requested_payment_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			return $this->get_payment_methods_from_gateway_id( $requested_payment_method )[0];
		}
	}

	/**
	 * Get payment method types to attach to intention request.
	 *
	 * @param Payment_Information $payment_information Payment information object for transaction.
	 * @return array List of payment methods.
	 */
	public function get_payment_method_types( $payment_information ) : array {
		$requested_payment_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$token                    = $payment_information->get_payment_token();

		if ( ! empty( $requested_payment_method ) ) {
			// All checkout requests should contain $_POST context, so we check this first.
			$payment_methods = $this->get_payment_methods_from_gateway_id( $requested_payment_method );
		} elseif ( ! is_null( $token ) ) {
			// If $_POST is empty, this may be a subscription renewal, where saved payment token will be present instead.
			$order           = $payment_information->get_order();
			$order_id        = $order instanceof WC_Order ? $order->get_id() : null;
			$payment_methods = $this->get_payment_methods_from_gateway_id( $token->get_gateway_id(), $order_id );
		} else {
			// Final fallback case, if all else fails.
			$payment_methods = WC_Payments::get_gateway()->get_payment_method_ids_enabled_at_checkout( null, true );
		}

		return $payment_methods;
	}

	/**
	 * Get the payment methods used in the request.
	 *
	 * @param string $gateway_id ID of processing payment gateway.
	 * @param int    $order_id ID of related order, if applicable.
	 * @return array List of payment methods.
	 */
	public function get_payment_methods_from_gateway_id( $gateway_id, $order_id = null ) {
		$split_upe_gateway_prefix = self::GATEWAY_ID . '_';
		// If $gateway_id begins with `woocommerce_payments_` payment method is a split UPE LPM.
		// Otherwise, $gateway_id must be `woocommerce_payments`.
		if ( substr( $gateway_id, 0, strlen( $split_upe_gateway_prefix ) ) === $split_upe_gateway_prefix ) {
			return [ str_replace( $split_upe_gateway_prefix, '', $gateway_id ) ];
		}

		$eligible_payment_methods = WC_Payments::get_gateway()->get_payment_method_ids_enabled_at_checkout( $order_id, true );

		if ( WC_Payments_Features::is_upe_deferred_intent_enabled() ) {
			// If split or deferred intent UPE is enabled and $gateway_id is `woocommerce_payments`, this must be the CC gateway.
			// We only need to return single `card` payment method, adding `link` since deferred intent UPE gateway is compatible with Link.
			$payment_methods = [ Payment_Method::CARD ];
			if ( in_array( Payment_Method::LINK, $eligible_payment_methods, true ) ) {
				$payment_methods[] = Payment_Method::LINK;
			}

			return $payment_methods;
		}

		// $gateway_id must be `woocommerce_payments` and gateway is either legacy UPE or legacy card.
		// Find the relevant gateway and return all available payment methods.
		return $eligible_payment_methods;
	}

	/**
	 * Get values for Stripe mandate_data parameter
	 *
	 * @return array mandate_data values to use in request.
	 */
	private function get_mandate_data() {
		return [
			'customer_acceptance' => [
				'type'   => 'online',
				'online' => [
					'ip_address' => WC_Geolocation::get_ip_address(),
					'user_agent' => 'WooCommerce Payments/' . WCPAY_VERSION_NUMBER . '; ' . get_bloginfo( 'url' ),
				],
			],
		];
	}

	/**
	 * By default this function does not do anything. But it can be overriden by child classes.
	 * It is used to set a formatted readable payment method title for order,
	 * using payment method details from accompanying charge.
	 *
	 * @param WC_Order   $order WC Order being processed.
	 * @param string     $payment_method_type Stripe payment method key.
	 * @param array|bool $payment_method_details Array of payment method details from charge or false.
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details ) {
	}

	/**
	 * Prepares Stripe metadata for a given order. The metadata later injected into intents, and
	 * used in transactions listing/details. If merchant connects an account to new store, listing/details
	 * keeps working even if orders are not available anymore - the metadata provides needed details.
	 *
	 * @param WC_Order     $order        Order being processed.
	 * @param Payment_Type $payment_type Enum stating whether payment is single or recurring.
	 *
	 * @return array Array of keyed metadata values.
	 */
	protected function get_metadata_from_order( $order, $payment_type ) {
		$service  = wcpay_get_container()->get( OrderService::class );
		$metadata = $service->get_payment_metadata( $order->get_id(), $payment_type );

		if ( $this instanceof UPE_Split_Payment_Gateway ) {
			$gateway_type = 'split_upe_with_deferred_intent_creation';
		} elseif ( $this instanceof UPE_Payment_Gateway ) {
			$gateway_type = 'legacy_upe';
		} else {
			$gateway_type = 'legacy_card';
		}
		$metadata['gateway_type'] = $gateway_type;

		return $metadata;
	}

	/**
	 * Given the charge data, checks if there was an exchange and adds it to the given order as metadata
	 *
	 * @param WC_Order $order The order to update.
	 * @param string   $charge_id ID of the charge to attach data from.
	 */
	public function attach_exchange_info_to_order( $order, $charge_id ) {
		if ( empty( $charge_id ) ) {
			return;
		}

		$currency_store   = strtolower( get_option( 'woocommerce_currency' ) );
		$currency_order   = strtolower( $order->get_currency() );
		$currency_account = strtolower( $this->account->get_account_default_currency() );

		// If the default currency for the store is different from the currency for the merchant's Stripe account,
		// the conversion rate provided by Stripe won't make sense, so we should not attach it to the order meta data
		// and instead we'll rely on the _wcpay_multi_currency_order_exchange_rate meta key for analytics.
		if ( $currency_store !== $currency_account ) {
			return;
		}

		if ( $currency_order !== $currency_account ) {
			// We check that the currency used in the order is different than the one set in the WC Payments account
			// to avoid requesting the charge if not needed.
			$request = Get_Charge::create( $charge_id );
			$request->set_hook_args( $charge_id );
			$charge = $request->send();

			$exchange_rate = $charge['balance_transaction']['exchange_rate'] ?? null;
			if ( isset( $exchange_rate ) ) {
				$exchange_rate = WC_Payments_Utils::interpret_string_exchange_rate( $exchange_rate, $currency_order, $currency_account );
				$order->update_meta_data( '_wcpay_multi_currency_stripe_exchange_rate', $exchange_rate );
				$order->save_meta_data();
			}
		}
	}

	/**
	 * Saves the payment token to the order.
	 *
	 * @param WC_Order         $order The order.
	 * @param WC_Payment_Token $token The token to save.
	 */
	public function add_token_to_order( $order, $token ) {
		$payment_token = $this->get_payment_token( $order );

		// This could lead to tokens being saved twice in an order's payment tokens, but it is needed so that shoppers
		// may re-use a previous card for the same subscription, as we consider the last token to be the active one.
		// We can't remove the previous entry for the token because WC_Order does not support removal of tokens [1] and
		// we can't delete the token as it might be used somewhere else.
		// [1] https://github.com/woocommerce/woocommerce/issues/11857.
		if ( is_null( $payment_token ) || $token->get_id() !== $payment_token->get_id() ) {
			$order->add_payment_token( $token );
		}

		$this->maybe_add_token_to_subscription_order( $order, $token );
	}

	/**
	 * Retrieve payment token from a subscription or order.
	 *
	 * @param WC_Order $order Order or subscription object.
	 *
	 * @return null|WC_Payment_Token Last token associated with order or subscription.
	 */
	protected function get_payment_token( $order ) {
		$order_tokens = $order->get_payment_tokens();
		$token_id     = end( $order_tokens );
		return ! $token_id ? null : WC_Payment_Tokens::get( $token_id );
	}

	/**
	 * Can the order be refunded?
	 *
	 * @param  WC_Order $order Order object.
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $this->order_service->get_charge_id_for_order( $order );
	}

	/**
	 * Refund a charge.
	 *
	 * @param  int    $order_id - the Order ID to process the refund for.
	 * @param  float  $amount   - the amount to refund.
	 * @param  string $reason   - the reason for refunding.
	 *
	 * @return bool|WP_Error - Whether the refund went through. Returns a WP_Error if an Exception occurs during execution.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		// If this order is not captured yet, don't try and refund it. Instead, return an appropriate error message.
		if ( Intent_Status::REQUIRES_CAPTURE === $this->order_service->get_intention_status_for_order( $order ) ) {
			return new WP_Error(
				'uncaptured-payment',
				/* translators: an error message which will appear if a user tries to refund an order which is has been authorized but not yet charged. */
				__( "This payment is not captured yet. To cancel this order, please go to 'Order Actions' > 'Cancel authorization'. To proceed with a refund, please go to 'Order Actions' > 'Capture charge' to charge the payment card, and then trigger a refund via the 'Refund' button.", 'woocommerce-payments' )
			);
		}

		// If the entered amount is not valid stop without making a request.
		if ( $amount <= 0 || $amount > $order->get_total() ) {
			return new WP_Error(
				'invalid-amount',
				__( 'The refund amount is not valid.', 'woocommerce-payments' )
			);
		}

		$charge_id = $this->order_service->get_charge_id_for_order( $order );
		$currency  = $this->order_service->get_wcpay_intent_currency_for_order( $order );

		try {
			// If the payment method is Interac, the refund already exists (refunded via Mobile app).
			$is_refunded_off_session = Payment_Method::INTERAC_PRESENT === $this->get_payment_method_type_for_order( $order );
			if ( $is_refunded_off_session ) {
				$refund_amount              = WC_Payments_Utils::prepare_amount( $amount ?? $order->get_total(), $order->get_currency() );
				$list_charge_refund_request = List_Charge_Refunds::create();
				$list_charge_refund_request->set_charge( $charge_id );

				$list_charge_refund_response = $list_charge_refund_request->send();

				$refunds = array_filter(
					$list_charge_refund_response['data'] ?? [],
					static function ( $refund ) use ( $refund_amount ) {
							return 'succeeded' === $refund['status'] && $refund_amount === $refund['amount'];
					}
				);

				if ( [] === $refunds ) {

					return new WP_Error(
						'wcpay_edit_order_refund_not_possible',
						__( 'You shall refund this payment in the same application where the payment was made.', 'woocommerce-payments' )
					);
				}

				$refund = array_pop( $refunds );
			} else {
				$refund_request = Refund_Charge::create();
				$refund_request->set_charge( $charge_id );
				if ( null !== $amount ) {
					$refund_request->set_amount( WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ) );
				}
				$refund = $refund_request->send();
			}
			$currency = strtoupper( $refund['currency'] );
			Tracker::track_admin( 'wcpay_edit_order_refund_success' );
		} catch ( Exception $e ) {

			$note = sprintf(
				/* translators: %1: the successfully charged amount, %2: error message */
				__( 'A refund of %1$s failed to complete: %2$s', 'woocommerce-payments' ),
				WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $amount, [ 'currency' => $currency ] ), $order ),
				$e->getMessage()
			);

			Logger::log( $note );
			$order->add_order_note( $note );
			$this->order_service->set_wcpay_refund_status_for_order( $order, 'failed' );
			$order->save();

			Tracker::track_admin( 'wcpay_edit_order_refund_failure', [ 'reason' => $note ] );
			return new WP_Error( 'wcpay_edit_order_refund_failure', $e->getMessage() );
		}

		if ( empty( $reason ) ) {
			$note = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
					/* translators: %1: the successfully charged amount, %2: WooPayments, %3: refund id */
					__( 'A refund of %1$s was successfully processed using %2$s (<code>%3$s</code>).', 'woocommerce-payments' ),
					[
						'code' => '<code>',
					]
				),
				WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $amount, [ 'currency' => $currency ] ), $order ),
				'WooPayments',
				$refund['id']
			);
		} else {
			$note = sprintf(
				WC_Payments_Utils::esc_interpolated_html(
					/* translators: %1: the successfully charged amount, %2: WooPayments, %3: reason, %4: refund id */
					__( 'A refund of %1$s was successfully processed using %2$s. Reason: %3$s. (<code>%4$s</code>)', 'woocommerce-payments' ),
					[
						'code' => '<code>',
					]
				),
				WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $amount, [ 'currency' => $currency ] ), $order ),
				'WooPayments',
				$reason,
				$refund['id']
			);
		}

		// Get the last created WC refund from order and save WCPay refund id as meta.
		$wc_last_refund = WC_Payments_Utils::get_last_refund_from_order_id( $order_id );
		if ( $wc_last_refund ) {
			$this->order_service->set_wcpay_refund_id_for_order( $wc_last_refund, $refund['id'] );
			$wc_last_refund->save_meta_data();
		}

		$order->add_order_note( $note );
		$this->order_service->set_wcpay_refund_status_for_order( $order, 'successful' );
		$order->save();

		return true;
	}

	/**
	 * Checks whether a refund through the gateway has already failed.
	 *
	 * @param WC_Order $order The order to check.
	 * @return boolean
	 */
	public function has_refund_failed( $order ) {
		return 'failed' === $this->order_service->get_wcpay_refund_status_for_order( $order );
	}

	/**
	 * Gets the payment method type used for an order, if any
	 *
	 * @param WC_Order $order The order to get the payment method type for.
	 *
	 * @return string
	 */
	private function get_payment_method_type_for_order( $order ): string {
		$payment_method_details = [];
		if ( $this->order_service->get_payment_method_id_for_order( $order ) ) {
			$payment_method_id      = $this->order_service->get_payment_method_id_for_order( $order );
			$payment_method_details = $this->payments_api_client->get_payment_method( $payment_method_id );
		} elseif ( $this->order_service->get_intent_id_for_order( $order ) ) {
			$payment_intent_id = $this->order_service->get_intent_id_for_order( $order );

			$request = Get_Intention::create( $payment_intent_id );
			$request->set_hook_args( $order );

			$payment_intent = $request->send();

			$charge                 = $payment_intent ? $payment_intent->get_charge() : null;
			$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
		}

		return $payment_method_details['type'] ?? 'unknown';
	}

	/**
	 * Overrides the original method in woo's WC_Settings_API in order to conditionally render the enabled checkbox.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 *
	 * @return string Checkbox markup or empty string.
	 */
	public function generate_checkbox_html( $key, $data ) {
		if ( 'enabled' === $key && ! $this->is_connected() ) {
			return '';
		}

		$in_dev_mode = WC_Payments::mode()->is_dev();

		if ( 'test_mode' === $key && $in_dev_mode ) {
			$data['custom_attributes']['disabled'] = 'disabled';
			$data['label']                         = __( 'Dev mode is active so all transactions will be in test mode. This setting is only available to live accounts.', 'woocommerce-payments' );
		}

		if ( 'enable_logging' === $key && $in_dev_mode ) {
			$data['custom_attributes']['disabled'] = 'disabled';
			$data['label']                         = __( 'Dev mode is active so logging is on by default.', 'woocommerce-payments' );
		}

		return parent::generate_checkbox_html( $key, $data );
	}

	/**
	 * Generates markup for account statement descriptor field.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 *
	 * @return string
	 */
	public function generate_account_statement_descriptor_html( $key, $data ) {
		if ( ! $this->is_connected() ) {
			return '';
		}

		return parent::generate_text_html( $key, $data );
	}

	/**
	 * Get option from DB or connected account.
	 *
	 * Overrides parent method to retrieve some options from connected account.
	 *
	 * @param  string $key           Option key.
	 * @param  mixed  $empty_value   Value when empty.
	 * @return string|array|int|bool The value specified for the option or a default value for the option.
	 */
	public function get_option( $key, $empty_value = null ) {
		switch ( $key ) {
			case 'enabled':
				return parent::get_option( static::METHOD_ENABLED_KEY, $empty_value );
			case 'account_country':
				return $this->get_account_country();
			case 'account_statement_descriptor':
				return $this->get_account_statement_descriptor();
			case 'account_statement_descriptor_kanji':
				return $this->get_account_statement_descriptor_kanji();
			case 'account_statement_descriptor_kana':
				return $this->get_account_statement_descriptor_kana();
			case 'account_business_name':
				return $this->get_account_business_name();
			case 'account_business_url':
				return $this->get_account_business_url();
			case 'account_business_support_address':
				return $this->get_account_business_support_address();
			case 'account_business_support_email':
				return $this->get_account_business_support_email();
			case 'account_business_support_phone':
				return $this->get_account_business_support_phone();
			case 'account_branding_logo':
				return $this->get_account_branding_logo();
			case 'account_branding_icon':
				return $this->get_account_branding_icon();
			case 'account_branding_primary_color':
				return $this->get_account_branding_primary_color();
			case 'account_branding_secondary_color':
				return $this->get_account_branding_secondary_color();
			case 'account_domestic_currency':
				return $this->get_account_domestic_currency();
			case 'deposit_schedule_interval':
				return $this->get_deposit_schedule_interval();
			case 'deposit_schedule_weekly_anchor':
				return $this->get_deposit_schedule_weekly_anchor();
			case 'deposit_schedule_monthly_anchor':
				return $this->get_deposit_schedule_monthly_anchor();
			case 'deposit_delay_days':
				return $this->get_deposit_delay_days();
			case 'deposit_status':
				return $this->get_deposit_status();
			case 'deposit_restrictions':
				return $this->get_deposit_restrictions();
			case 'deposit_completed_waiting_period':
				return $this->get_deposit_completed_waiting_period();
			case 'current_protection_level':
				return $this->get_current_protection_level();
			case 'advanced_fraud_protection_settings':
				return $this->get_advanced_fraud_protection_settings();

			default:
				return parent::get_option( $key, $empty_value );
		}
	}

	/**
	 * Return the name of the option in the WP DB.
	 * Overrides parent method so the option key is the same as the parent class.
	 */
	public function get_option_key() {
		// Intentionally using self instead of static so options are loaded from main gateway settings.
		return $this->plugin_id . self::GATEWAY_ID . '_settings';
	}


	/**
	 * Update a single option.
	 * Overrides parent method to use different key for `enabled`.
	 *
	 * @param string $key Option key.
	 * @param mixed  $value Value to set.
	 * @return bool was anything saved?
	 */
	public function update_option( $key, $value = '' ) {
		if ( 'enabled' === $key ) {
			$key = static::METHOD_ENABLED_KEY;
		}
		return parent::update_option( $key, $value );
	}

	/**
	 * Updates whether woopay is enabled or disabled.
	 *
	 * @param bool $is_woopay_enabled Whether woopay should be enabled.
	 */
	public function update_is_woopay_enabled( $is_woopay_enabled ) {
		$current_is_woopay_enabled = 'yes' === $this->get_option( 'platform_checkout', 'no' );

		if ( $is_woopay_enabled !== $current_is_woopay_enabled ) {
			WC_Payments::woopay_tracker()->maybe_record_admin_event(
				$is_woopay_enabled ? 'woopay_enabled' : 'woopay_disabled',
				[ 'test_mode' => WC_Payments::mode()->is_test() ? 1 : 0 ]
			);

			$this->update_option( 'platform_checkout', $is_woopay_enabled ? 'yes' : 'no' );

			if ( ! $is_woopay_enabled ) {
				$this->update_option( 'platform_checkout_last_disable_date', gmdate( 'Y-m-d' ) );
			}

			if ( ! $is_woopay_enabled ) {
				WooPay_Order_Status_Sync::remove_webhook();
			} elseif ( WC_Payments_Features::is_upe_legacy_enabled() ) {
				update_option( WC_Payments_Features::UPE_FLAG_NAME, '0' );
				update_option( WC_Payments_Features::UPE_DEFERRED_INTENT_FLAG_NAME, '1' );

				if ( function_exists( 'wc_admin_record_tracks_event' ) ) {
					wc_admin_record_tracks_event( 'wcpay_deferred_intent_upe_enabled' );
				}
			}
		}
	}

	/**
	 * Init settings for gateways.
	 */
	public function init_settings() {
		parent::init_settings();
		$this->enabled = ! empty( $this->settings[ static::METHOD_ENABLED_KEY ] ) && 'yes' === $this->settings[ static::METHOD_ENABLED_KEY ] ? 'yes' : 'no';
	}

	/**
	 * Get payment capture type from WCPay settings.
	 *
	 * @return Payment_Capture_Type MANUAL or AUTOMATIC depending on the settings.
	 */
	protected function get_capture_type() {
		return 'yes' === $this->get_option( 'manual_capture' ) ? Payment_Capture_Type::MANUAL() : Payment_Capture_Type::AUTOMATIC();
	}

	/**
	 * Map fields that need to be updated and update the fields server side.
	 *
	 * @param array $settings Plugin settings.
	 *
	 * @return array|WP_Error Updated fields.
	 */
	public function update_account_settings( array $settings ) {
		$account_settings = [];
		foreach ( static::ACCOUNT_SETTINGS_MAPPING as $name => $account_key ) {
			if ( isset( $settings[ $name ] ) ) {
				$account_settings[ $account_key ] = $settings[ $name ];
			}
		}

		$result = $this->update_account( $account_settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $account_settings;
	}

	/**
	 * Gets connected account statement descriptor.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch account descriptor.
	 *
	 * @return string Statement descriptor of default value.
	 */
	public function get_account_statement_descriptor( string $empty_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_statement_descriptor();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account statement descriptor.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets connected account statement descriptor.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch account descriptor.
	 *
	 * @return string Statement descriptor of default value.
	 */
	public function get_account_statement_descriptor_kanji( string $empty_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_statement_descriptor_kanji();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account statement descriptor.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets connected account statement descriptor.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch account descriptor.
	 *
	 * @return string Statement descriptor of default value.
	 */
	public function get_account_statement_descriptor_kana( string $empty_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_statement_descriptor_kana();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account statement descriptor.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets account default currency.
	 *
	 * @return string Currency code.
	 */
	public function get_account_default_currency(): string {
		return $this->account->get_account_default_currency();
	}

	/**
	 * Gets connected account business name.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch business name.
	 *
	 * @return string Business name or default value.
	 */
	protected function get_account_business_name( $default_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_business_name();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account business url.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch business url.
	 *
	 * @return string Business url or default value.
	 */
	protected function get_account_business_url( $default_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_business_url();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account business address.
	 *
	 * @param array $default_value Value to return when not connected or failed to fetch business address.
	 *
	 * @return array Business address or default value.
	 */
	protected function get_account_business_support_address( $default_value = [] ): array {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_business_support_address();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account business support email.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch business support email.
	 *
	 * @return string Business support email or default value.
	 */
	protected function get_account_business_support_email( $default_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_business_support_email();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account business support phone.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch business support phone.
	 *
	 * @return string Business support phone or default value.
	 */
	protected function get_account_business_support_phone( $default_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_business_support_phone();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account branding logo.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch branding logo.
	 *
	 * @return string Business support branding logo or default value.
	 */
	protected function get_account_branding_logo( $default_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_branding_logo();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account branding icon.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch branding icon.
	 *
	 * @return string Business support branding icon or default value.
	 */
	protected function get_account_branding_icon( $default_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_branding_icon();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account branding primary color.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch branding primary color.
	 *
	 * @return string Business support branding primary color or default value.
	 */
	protected function get_account_branding_primary_color( $default_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_branding_primary_color();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Gets connected account branding secondary color.
	 *
	 * @param string $default_value Value to return when not connected or failed to fetch branding secondary color.
	 *
	 * @return string Business support branding secondary color or default value.
	 */
	protected function get_account_branding_secondary_color( $default_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_branding_secondary_color();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account business name.' . $e );
		}

		return $default_value;
	}

	/**
	 * Retrieves the domestic currency of the current account based on its country.
	 * It will fallback to the store's currency if the account's country is not supported.
	 *
	 * @return string The domestic currency code.
	 */
	protected function get_account_domestic_currency(): string {
		$merchant_country    = strtoupper( $this->account->get_account_country() );
		$country_locale_data = $this->localization_service->get_country_locale_data( $merchant_country );

		// Check for missing country locale data.
		if ( ! isset( $country_locale_data['currency_code'] ) ) {
			Logger::error(
				sprintf(
					'Could not find locale data for merchant country: %s. Falling back to the merchant\'s default currency.',
					$merchant_country
				)
			);
			return $this->get_account_default_currency();
		}

		return $country_locale_data['currency_code'];
	}

	/**
	 * Gets connected account deposit schedule interval.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch deposit schedule.
	 *
	 * @return string Interval or default value.
	 */
	protected function get_deposit_schedule_interval( string $empty_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_deposit_schedule_interval();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get deposit schedule interval.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets connected account deposit schedule weekly anchor.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch deposit schedule weekly anchor.
	 *
	 * @return string Weekly anchor or default value.
	 */
	protected function get_deposit_schedule_weekly_anchor( string $empty_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_deposit_schedule_weekly_anchor();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get deposit schedule weekly anchor.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets connected account deposit schedule monthly anchor.
	 *
	 * @param int|null $empty_value Empty value to return when not connected or fails to fetch deposit schedule monthly anchor.
	 *
	 * @return int|null Monthly anchor or default value.
	 */
	protected function get_deposit_schedule_monthly_anchor( $empty_value = null ) {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_deposit_schedule_monthly_anchor();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get deposit schedule monthly anchor.' . $e );
		}
		return null === $empty_value ? null : (int) $empty_value;
	}

	/**
	 * Gets connected account deposit delay days.
	 *
	 * @param int $default_value Value to return when not connected or fails to fetch deposit delay days. Default is 7 days.
	 *
	 * @return int number of days.
	 */
	protected function get_deposit_delay_days( int $default_value = 7 ): int {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_deposit_delay_days() ?? $default_value;
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get deposit delay days.' . $e );
		}
		return $default_value;
	}

	/**
	 * Gets connected account country.
	 *
	 * @param string $default_value Value to return when not connected or fails to fetch account details. Default is US.
	 *
	 * @return string code of the country.
	 */
	protected function get_account_country( string $default_value = 'US' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_account_country() ?? $default_value;
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account country.' . $e );
		}
		return $default_value;
	}

	/**
	 * Gets connected account deposit status.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch deposit status.
	 *
	 * @return string deposit status or default value.
	 */
	protected function get_deposit_status( string $empty_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_deposit_status();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get deposit status.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets connected account deposit restrictions.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch deposit restrictions.
	 *
	 * @return string deposit restrictions or default value.
	 */
	protected function get_deposit_restrictions( string $empty_value = '' ): string {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_deposit_restrictions();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get deposit restrictions.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets the completed deposit waiting period value.
	 *
	 * @param bool $empty_value Empty value to return when not connected or fails to fetch the completed deposit waiting period value.
	 *
	 * @return bool The completed deposit waiting period value or default value.
	 */
	protected function get_deposit_completed_waiting_period( bool $empty_value = false ): bool {
		try {
			if ( $this->is_connected() ) {
				return $this->account->get_deposit_completed_waiting_period();
			}
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get the deposit waiting period value.' . $e );
		}
		return $empty_value;
	}

	/**
	 * Gets the current fraud protection level value.
	 *
	 * @return  string The current fraud protection level.
	 */
	protected function get_current_protection_level() {
		$this->maybe_refresh_fraud_protection_settings();
		return get_option( 'current_protection_level', 'basic' );
	}

	/**
	 * Gets the advanced fraud protection level settings value.
	 *
	 * @return  array|string The advanced level fraud settings for the store, if not saved, the default ones.
	 *                       If there's a fetch error, it returns "error".
	 */
	protected function get_advanced_fraud_protection_settings() {
		// Check if Stripe is connected.
		if ( ! $this->is_connected() ) {
			return [];
		}

		$this->maybe_refresh_fraud_protection_settings();
		$transient_value = get_transient( 'wcpay_fraud_protection_settings' );
		return false === $transient_value ? 'error' : $transient_value;
	}

	/**
	 * Checks the synchronicity of fraud protection settings with the server, and updates the local cache when needed.
	 *
	 * @return  void
	 */
	protected function maybe_refresh_fraud_protection_settings() {
		// It'll be good to run this only once per call, because if it succeeds, the latter won't require
		// to run again, and if it fails, it will fail on other calls too.
		static $runonce = false;

		// If already ran this before on this call, return.
		if ( $runonce ) {
			return;
		}

		// Check if we have local cache available before pulling it from the server.
		// If the transient exists, do nothing.
		$cached_server_settings = get_transient( 'wcpay_fraud_protection_settings' );

		if ( ! $cached_server_settings ) {
			// When both local and server values don't exist, we need to reset the protection level on both to "Basic".
			$needs_reset = false;

			try {
				// There's no cached ruleset, or the cache has expired. Try to fetch it from the server.
				$latest_server_ruleset = $this->payments_api_client->get_latest_fraud_ruleset();
				if ( isset( $latest_server_ruleset['ruleset_config'] ) ) {
					// Update the local cache from the server.
					set_transient( 'wcpay_fraud_protection_settings', $latest_server_ruleset['ruleset_config'], DAY_IN_SECONDS );
					// Get the matching level for the ruleset, and set the option.
					update_option( 'current_protection_level', Fraud_Risk_Tools::get_matching_protection_level( $latest_server_ruleset['ruleset_config'] ) );
					return;
				}
				// If the response doesn't contain a ruleset, probably there's an error. Grey out the form.
			} catch ( API_Exception $ex ) {
				if ( 'wcpay_fraud_ruleset_not_found' === $ex->get_error_code() ) {
					// If fetching returned a 'wcpay_fraud_ruleset_not_found' exception, save the basic protection as the server ruleset,
					// and update the client with the same config.
					$needs_reset = true;
				}
				// If the exception isn't what we want, probably there's an error. Grey out the form.
			}

			if ( $needs_reset ) {
				// Set the Basic protection level as the default on both client and server.
				$basic_protection_settings = Fraud_Risk_Tools::get_basic_protection_settings();
				$this->payments_api_client->save_fraud_ruleset( $basic_protection_settings );
				set_transient( 'wcpay_fraud_protection_settings', $basic_protection_settings, DAY_IN_SECONDS );
				update_option( 'current_protection_level', 'basic' );
			}

			// Set the static flag to prevent duplicate calls to this method.
			$runonce = true;
		}
	}

	/**
	 * The get_icon() method from the WC_Payment_Gateway class wraps the icon URL into a prepared HTML element, but there are situations when this
	 * element needs to be rendered differently on the UI (e.g. additional styles or `display` property).
	 *
	 * This is why we need a usual getter like this to provide a raw icon URL to the UI, which will render it according to particular requirements.
	 *
	 * @return string Returns the payment method icon URL.
	 */
	public function get_icon_url() {
		return $this->icon;
	}

	/**
	 * Handles connected account update when plugin settings saved.
	 *
	 * Adds error message to display in admin notices in case of failure.
	 *
	 * @param array $account_settings Stripe account settings.
	 * Supported: statement_descriptor, business_name, business_url, business_support_address,
	 * business_support_email, business_support_phone, branding_logo, branding_icon,
	 * branding_primary_color, branding_secondary_color.
	 *
	 * $return array | WP_Error Update account result.
	 *
	 * @throws Exception
	 */
	public function update_account( $account_settings ) {
		if ( empty( $account_settings ) ) {
			return;
		}

		$stripe_account_update_response = $this->account->update_stripe_account( $account_settings );

		if ( is_wp_error( $stripe_account_update_response ) ) {
			$msg = __( 'Failed to update Stripe account. ', 'woocommerce-payments' ) . $stripe_account_update_response->get_error_message();
			$this->add_error( $msg );
		}

		return $stripe_account_update_response;
	}

	/**
	 * Validates statement descriptor value
	 *
	 * @param  string $key Field key.
	 * @param  string $value Posted Value.
	 *
	 * @return string                   Sanitized statement descriptor.
	 * @throws InvalidArgumentException When statement descriptor is invalid.
	 */
	public function validate_account_statement_descriptor_field( $key, $value ) {
		// Since the value is escaped, and we are saving in a place that does not require escaping, apply stripslashes.
		$value = trim( stripslashes( $value ) );

		// Validation can be done with a single regex but splitting into multiple for better readability.
		$valid_length   = '/^.{5,22}$/';
		$has_one_letter = '/^.*[a-zA-Z]+/';
		$no_specials    = '/^[^*"\'<>]*$/';

		if (
			! preg_match( $valid_length, $value ) ||
			! preg_match( $has_one_letter, $value ) ||
			! preg_match( $no_specials, $value )
		) {
			throw new InvalidArgumentException( __( 'Customer bank statement is invalid. Statement should be between 5 and 22 characters long, contain at least single Latin character and does not contain special characters: \' " * &lt; &gt;', 'woocommerce-payments' ) );
		}

		return $value;
	}

	/**
	 * Add capture and cancel actions for orders with an authorized charge.
	 *
	 * @param array $actions - Actions to make available in order actions metabox.
	 */
	public function add_order_actions( $actions ) {
		global $theorder;

		if ( ! is_object( $theorder ) ) {
			return $actions;
		}

		if ( $this->id !== $theorder->get_payment_method() ) {
			return $actions;
		}

		if ( Intent_Status::REQUIRES_CAPTURE !== $this->order_service->get_intention_status_for_order( $theorder ) ) {
			return $actions;
		}

		// if order is already completed, we shouldn't capture the charge anymore.
		if ( in_array( $theorder->get_status(), wc_get_is_paid_statuses(), true ) ) {
			return $actions;
		}

		$new_actions = [
			'capture_charge'       => __( 'Capture charge', 'woocommerce-payments' ),
			'cancel_authorization' => __( 'Cancel authorization', 'woocommerce-payments' ),
		];

		return array_merge( $new_actions, $actions );
	}

	/**
	 * Capture previously authorized charge.
	 *
	 * @param WC_Order $order - Order to capture charge on.
	 * @param bool     $include_level3 - Whether to include level 3 data in payment intent.
	 * @param array    $intent_metadata - Intent metadata retrieved earlier in the calling method.
	 *
	 * @return array An array containing the status (succeeded/failed), id (intent ID), message (error message if any), and http code
	 */
	public function capture_charge( $order, $include_level3 = true, $intent_metadata = [] ) {
		$intent_id                = null;
		$amount                   = $order->get_total();
		$is_authorization_expired = false;
		$intent                   = null;
		$status                   = null;
		$error_message            = null;
		$http_code                = null;

		try {
			$intent_id           = $order->get_transaction_id();
			$payment_type        = $this->is_payment_recurring( $order->get_id() ) ? Payment_Type::RECURRING() : Payment_Type::SINGLE();
			$metadata_from_order = $this->get_metadata_from_order( $order, $payment_type );
			$merged_metadata     = array_merge( (array) $metadata_from_order, (array) $intent_metadata ); // prioritize metadata from mobile app.

			$capture_intention_request = Capture_Intention::create( $intent_id );
			$capture_intention_request->set_amount_to_capture( WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ) );
			$capture_intention_request->set_metadata( $merged_metadata );
			$capture_intention_request->set_hook_args( $order );
			if ( $include_level3 ) {
				$capture_intention_request->set_level3( $this->get_level3_data_from_order( $order ) );
			}
			$intent = $capture_intention_request->send();

			$status    = $intent->get_status();
			$http_code = 200;
		} catch ( API_Exception $e ) {
			try {
				$error_message = $e->getMessage();
				$http_code     = $e->get_http_code();

				$request = Get_Intention::create( $intent_id );
				$request->set_hook_args( $order );
				// Fetch the Intent to check if it's already expired and the site missed the "charge.expired" webhook.
				$intent = $request->send();

				if ( Intent_Status::CANCELED === $intent->get_status() ) {
					$is_authorization_expired = true;
				}
			} catch ( API_Exception $ge ) {
				// Ignore any errors during the intent retrieval, and add the failed capture note below with the
				// original error message.
				$status        = null;
				$error_message = $e->getMessage();
				$http_code     = $e->get_http_code();
			}
		}

		Tracker::track_admin( 'wcpay_merchant_captured_auth' );

		// There is a possibility of the intent being null, so we need to get the charge_id safely.
		$charge    = ! empty( $intent ) ? $intent->get_charge() : null;
		$charge_id = ! empty( $charge ) ? $charge->get_id() : $this->order_service->get_charge_id_for_order( $order );

		$this->attach_exchange_info_to_order( $order, $charge_id );

		if ( Intent_Status::SUCCEEDED === $status ) {
			$this->order_service->update_order_status_from_intent( $order, $intent );
		} elseif ( $is_authorization_expired ) {
			$this->order_service->mark_payment_capture_expired( $order, $intent_id, Intent_Status::CANCELED, $charge_id );
		} else {
			if ( ! empty( $error_message ) ) {
				$error_message = esc_html( $error_message );
			} else {
				$http_code = 502;
			}

			$this->order_service->mark_payment_capture_failed( $order, $intent_id, Intent_Status::REQUIRES_CAPTURE, $charge_id, $error_message );
		}

		return [
			'status'    => $status ?? 'failed',
			'id'        => ! empty( $intent ) ? $intent->get_id() : null,
			'message'   => $error_message,
			'http_code' => $http_code,
		];
	}

	/**
	 * Cancel previously authorized charge.
	 *
	 * @param WC_Order $order - Order to cancel authorization on.
	 */
	public function cancel_authorization( $order ) {
		$intent        = null;
		$status        = null;
		$error_message = null;
		$http_code     = null;

		try {
			$request = Cancel_Intention::create( $order->get_transaction_id() );
			$request->set_hook_args( $order );
			$intent    = $request->send();
			$status    = $intent->get_status();
			$http_code = 200;
		} catch ( API_Exception $e ) {
			try {
				// Fetch the Intent to check if it's already expired and the site missed the "charge.expired" webhook.
				$request = Get_Intention::create( $order->get_transaction_id() );
				$request->set_hook_args( $order );
				$intent = $request->send();

				$status = $intent->get_status();
				if ( Intent_Status::CANCELED !== $status ) {
					$error_message = $e->getMessage();
				}
			} catch ( API_Exception $ge ) {
				// Ignore any errors during the intent retrieval, and add the failed cancellation note below with the
				// original error message.
				$status        = null;
				$error_message = $e->getMessage();
				$http_code     = $e->get_http_code();
			}
		}

		if ( Intent_Status::CANCELED === $status ) {
			$this->order_service->update_order_status_from_intent( $order, $intent );
		} else {
			if ( ! empty( $error_message ) ) {
				$note = sprintf(
					WC_Payments_Utils::esc_interpolated_html(
						/* translators: %1: error message  */
						__(
							'Canceling authorization <strong>failed</strong> to complete with the following message: <code>%1$s</code>.',
							'woocommerce-payments'
						),
						[
							'strong' => '<strong>',
							'code'   => '<code>',
						]
					),
					esc_html( $error_message )
				);
				$order->add_order_note( $note );
			} else {
				$order->add_order_note(
					WC_Payments_Utils::esc_interpolated_html(
						__( 'Canceling authorization <strong>failed</strong> to complete.', 'woocommerce-payments' ),
						[ 'strong' => '<strong>' ]
					)
				);
			}

			$this->order_service->set_intention_status_for_order( $order, $status );
			$order->save();
			$http_code = 502;
		}

		return [
			'status'    => $status ?? 'failed',
			'id'        => ! empty( $intent ) ? $intent->get_id() : null,
			'message'   => $error_message,
			'http_code' => $http_code,
		];
	}

	/**
	 * Create the level 3 data array to send to Stripe when making a purchase.
	 *
	 * @param WC_Order $order The order that is being paid for.
	 * @return array          The level 3 data to send to Stripe.
	 */
	public function get_level3_data_from_order( WC_Order $order ): array {
		return wcpay_get_container()->get( Level3Service::class )->get_data_from_order( $order->get_id() );
	}

	/**
	 * Handle AJAX request after authenticating payment at checkout.
	 *
	 * This function is used to update the order status after the user has
	 * been asked to authenticate their payment.
	 *
	 * This function is used for both:
	 * - regular checkout
	 * - Pay for Order page
	 *
	 * @throws Exception - If nonce is invalid.
	 */
	public function update_order_status() {
		$intent_id_received = null;
		$order              = null;
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_update_order_status_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
					'invalid_referrer'
				);
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : false;
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'order_not_found'
				);
			}

			$intent_id          = $this->order_service->get_intent_id_for_order( $order );
			$intent_id_received = isset( $_POST['intent_id'] )
			? sanitize_text_field( wp_unslash( $_POST['intent_id'] ) )
			/* translators: This will be used to indicate an unknown value for an ID. */
			: __( 'unknown', 'woocommerce-payments' );

			if ( empty( $intent_id ) ) {
				throw new Intent_Authentication_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'empty_intent_id'
				);
			}

			$payment_method_id = isset( $_POST['payment_method_id'] ) ? wc_clean( wp_unslash( $_POST['payment_method_id'] ) ) : '';
			if ( 'null' === $payment_method_id ) {
				$payment_method_id = '';
			}

			// Check that the intent saved in the order matches the intent used as part of the
			// authentication process. The ID of the intent used is sent with
			// the AJAX request. We are about to use the status of the intent saved in
			// the order, so we need to make sure the intent that was used for authentication
			// is the same as the one we're using to update the status.
			if ( $intent_id !== $intent_id_received ) {
				throw new Intent_Authentication_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'intent_id_mismatch'
				);
			}

			$amount = $order->get_total();

			if ( $amount > 0 ) {
				// An exception is thrown if an intent can't be found for the given intent ID.
				$request = Get_Intention::create( $intent_id );
				$request->set_hook_args( $order );
				$intent = $request->send();

				$status    = $intent->get_status();
				$charge    = $intent->get_charge();
				$charge_id = ! empty( $charge ) ? $charge->get_id() : null;

				$this->attach_exchange_info_to_order( $order, $charge_id );
				$this->order_service->attach_intent_info_to_order( $order, $intent_id, $status, $intent->get_payment_method_id(), $intent->get_customer_id(), $charge_id, $intent->get_currency() );
				$this->order_service->attach_transaction_fee_to_order( $order, $charge );
			} else {
				// For $0 orders, fetch the Setup Intent instead.
				$setup_intent_request = Get_Setup_Intention::create( $intent_id );
				/** @var WC_Payments_API_Setup_Intention $setup_intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent    = $setup_intent_request->send();
				$status    = $intent->get_status();
				$charge_id = '';
			}

			if ( Intent_Status::SUCCEEDED === $status ) {
				$this->duplicate_payment_prevention_service->remove_session_processing_order( $order->get_id() );
			}
			$this->order_service->update_order_status_from_intent( $order, $intent );

			if ( $intent->is_authorized() ) {
				wc_reduce_stock_levels( $order_id );
				WC()->cart->empty_cart();

				if ( ! empty( $payment_method_id ) ) {
					try {
						$token = $this->token_service->add_payment_method_to_user( $payment_method_id, wp_get_current_user() );
						$this->add_token_to_order( $order, $token );
					} catch ( Exception $e ) {
						// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
						Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
					}
				}

				// Send back redirect URL in the successful case.
				echo wp_json_encode(
					[
						'return_url' => $this->get_return_url( $order ),
					]
				);
				wp_die();
			}
		} catch ( Intent_Authentication_Exception $e ) {
			$error_code = $e->get_error_code();

			switch ( $error_code ) {
				case 'intent_id_mismatch':
				case 'empty_intent_id': // The empty_intent_id case needs the same handling.
					$note = sprintf(
						WC_Payments_Utils::esc_interpolated_html(
							/* translators: %1: transaction ID of the payment or a translated string indicating an unknown ID. */
							__( 'A payment with ID <code>%1$s</code> was used in an attempt to pay for this order. This payment intent ID does not match any payments for this order, so it was ignored and the order was not updated.', 'woocommerce-payments' ),
							[
								'code' => '<code>',
							]
						),
						$intent_id_received
					);
					$order->add_order_note( $note );
					break;
			}

			// Send back error so it can be displayed to the customer.
			echo wp_json_encode(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
			wp_die();
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			echo wp_json_encode(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
			wp_die();
		}
	}

	/**
	 * Add payment method via account screen.
	 *
	 * @throws Add_Payment_Method_Exception If payment method is missing.
	 */
	public function add_payment_method() {
		try {

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! isset( $_POST['wcpay-setup-intent'] ) ) {
				throw new Add_Payment_Method_Exception(
					sprintf(
						/* translators: %s: WooPayments */
						__( 'A %s payment method was not provided', 'woocommerce-payments' ),
						'WooPayments'
					),
					'payment_method_intent_not_provided'
				);
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$setup_intent_id = ! empty( $_POST['wcpay-setup-intent'] ) ? wc_clean( $_POST['wcpay-setup-intent'] ) : false;

			$customer_id = $this->customer_service->get_customer_id_by_user_id( get_current_user_id() );

			if ( ! $setup_intent_id || null === $customer_id ) {
				throw new Add_Payment_Method_Exception(
					__( "We're not able to add this payment method. Please try again later", 'woocommerce-payments' ),
					'invalid_setup_intent_id'
				);
			}

			$setup_intent_request = Get_Setup_Intention::create( $setup_intent_id );
			/** @var WC_Payments_API_Setup_Intention $setup_intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$setup_intent = $setup_intent_request->send();

			if ( Intent_Status::SUCCEEDED !== $setup_intent->get_status() ) {
				throw new Add_Payment_Method_Exception(
					__( 'Failed to add the provided payment method. Please try again later', 'woocommerce-payments' ),
					'invalid_response_status'
				);
			}

			$payment_method = $setup_intent->get_payment_method_id();
			$this->token_service->add_payment_method_to_user( $payment_method, wp_get_current_user() );

			return [
				'result'   => 'success',
				'redirect' => apply_filters( 'wcpay_get_add_payment_method_redirect_url', wc_get_endpoint_url( 'payment-methods' ) ),
			];
		} catch ( Exception $e ) {
			wc_add_notice( WC_Payments_Utils::get_filtered_error_message( $e ), 'error', [ 'icon' => 'error' ] );
			Logger::log( 'Error when adding payment method: ' . $e->getMessage() );
			return [
				'result' => 'error',
			];
		}
	}

	/**
	 * When an order is created/updated, we want to add an ActionScheduler job to send this data to
	 * the payment server.
	 *
	 * @param int           $order_id  The ID of the order that has been created.
	 * @param WC_Order|null $order     The order that has been created.
	 */
	public function schedule_order_tracking( $order_id, $order = null ) {
		$this->maybe_schedule_subscription_order_tracking( $order_id, $order );

		// If Sift is not enabled, exit out and don't do the tracking here.
		if ( ! isset( $this->fraud_service->get_fraud_services_config()['sift'] ) ) {
			return;
		}

		// Sometimes the woocommerce_update_order hook might be called with just the order ID parameter,
		// so we need to fetch the order here.
		if ( is_null( $order ) ) {
			$order = wc_get_order( $order_id );
		}

		// We only want to track orders created by our payment gateway, and orders with a payment method set.
		if ( $order->get_payment_method() !== self::GATEWAY_ID || empty( $this->order_service->get_payment_method_id_for_order( $order ) ) ) {
			return;
		}

		// Check whether this is an order we haven't previously tracked a creation event for.
		if ( $order->get_meta( '_new_order_tracking_complete' ) !== 'yes' ) {
			// Schedule the action to send this information to the payment server.
			$this->action_scheduler_service->schedule_job(
				strtotime( '+5 seconds' ),
				'wcpay_track_new_order',
				[ 'order_id' => $order_id ]
			);
		} else {
			// Schedule an update action to send this information to the payment server.
			$this->action_scheduler_service->schedule_job(
				strtotime( '+5 seconds' ),
				'wcpay_track_update_order',
				[ 'order_id' => $order_id ]
			);
		}
	}

	/**
	 * Create a payment intent without confirming the intent.
	 *
	 * @param WC_Order    $order                        - Order based on which to create intent.
	 * @param array       $payment_methods - A list of allowed payment methods. Eg. card, card_present.
	 * @param string      $capture_method               - Controls when the funds will be captured from the customer's account ("automatic" or "manual").
	 *  It must be "manual" for in-person (terminal) payments.
	 *
	 * @param array       $metadata                     - A list of intent metadata.
	 * @param string|null $customer_id                  - Customer id for intent.
	 *
	 * @return array|WP_Error On success, an array containing info about the newly created intent. On failure, WP_Error object.
	 *
	 * @throws Exception - When an error occurs in intent creation.
	 */
	public function create_intent( WC_Order $order, array $payment_methods, string $capture_method = 'automatic', array $metadata = [], string $customer_id = null ) {
		$currency         = strtolower( $order->get_currency() );
		$converted_amount = WC_Payments_Utils::prepare_amount( $order->get_total(), $currency );
		$order_number     = $order->get_order_number();
		if ( $order_number ) {
			$metadata['order_number'] = $order_number;
		}

		try {
			$request = Create_Intention::create();
			$request->set_amount( $converted_amount );
			$request->set_customer( $customer_id );
			$request->set_currency_code( $currency );
			$request->set_metadata( $metadata );
			$request->set_payment_method_types( $payment_methods );
			$request->set_capture_method( $capture_method );
			$request->set_hook_args( $order );
			$intent = $request->send();

			return [
				'id' => ! empty( $intent ) ? $intent->get_id() : null,
			];
		} catch ( API_Exception $e ) {
			return new WP_Error(
				'wcpay_intent_creation_error',
				sprintf(
					// translators: %s: the error message.
					__( 'Intent creation failed with the following message: %s', 'woocommerce-payments' ),
					$e->getMessage() ?? __( 'Unknown error', 'woocommerce-payments' )
				),
				[ 'status' => $e->get_http_code() ]
			);
		}
	}

	/**
	 * Create a setup intent when adding cards using the my account page.
	 *
	 * @return WC_Payments_API_Setup_Intention
	 *
	 * @throws API_Exception
	 * @throws \WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception
	 * @throws \WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception
	 * @throws \WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception
	 */
	public function create_and_confirm_setup_intent() {
		$payment_information             = Payment_Information::from_payment_request( $_POST, null, null, null, null, $this->get_payment_method_to_use_for_intent() ); // phpcs:ignore WordPress.Security.NonceVerification
		$should_save_in_platform_account = false;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['save_payment_method_in_platform_account'] ) && filter_var( wp_unslash( $_POST['save_payment_method_in_platform_account'] ), FILTER_VALIDATE_BOOLEAN ) ) {
			$should_save_in_platform_account = true;
		}

		// Determine the customer adding the payment method, create one if we don't have one already.
		$user        = wp_get_current_user();
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );
		if ( null === $customer_id ) {
			$customer_data = WC_Payments_Customer_Service::map_customer_data( null, new WC_Customer( $user->ID ) );
			$customer_id   = $this->customer_service->create_customer_for_user( $user, $customer_data );
		}

		$request = Create_And_Confirm_Setup_Intention::create();
		$request->set_customer( $customer_id );
		$request->set_payment_method( $payment_information->get_payment_method() );
		$request->assign_hook( 'wcpay_create_and_confirm_setup_intention_request' );
		$request->set_hook_args( $payment_information, $should_save_in_platform_account, false );
		return $request->send();
	}

	/**
	 * Handle AJAX request for creating a setup intent when adding cards using the my account page.
	 *
	 * @throws Add_Payment_Method_Exception - If nonce or setup intent is invalid.
	 */
	public function create_setup_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_create_setup_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Add_Payment_Method_Exception(
					__( "We're not able to add this payment method. Please refresh the page and try again.", 'woocommerce-payments' ),
					'invalid_referrer'
				);
			}

			$setup_intent        = $this->create_and_confirm_setup_intent();
			$setup_intent_output = [
				'id'            => $setup_intent->get_id(),
				'status'        => $setup_intent->get_status(),
				'client_secret' => WC_Payments_Utils::encrypt_client_secret(
					$this->account->get_stripe_account_id(),
					$setup_intent->get_client_secret()
				),
			];

			wp_send_json_success( $setup_intent_output, 200 );
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => WC_Payments_Utils::get_filtered_error_message( $e ),
					],
				],
				WC_Payments_Utils::get_filtered_error_status_code( $e ),
			);
		}
	}

	/**
	 * Add a url to the admin order page that links directly to the transactions detail view.
	 *
	 * @since 1.4.0
	 *
	 * @param WC_Order $order The context passed into this function when the user view the order details page in WordPress admin.
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		$intent_id = $this->order_service->get_intent_id_for_order( $order );
		$charge_id = $this->order_service->get_charge_id_for_order( $order );

		return WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
	}

	/**
	 * Returns a formatted token list for a user.
	 *
	 * @param int $user_id The user ID.
	 */
	protected function get_user_formatted_tokens_array( $user_id ) {
		$tokens = WC_Payment_Tokens::get_tokens(
			[
				'user_id'    => $user_id,
				'gateway_id' => self::GATEWAY_ID,
				'limit'      => self::USER_FORMATTED_TOKENS_LIMIT,
			]
		);

		return array_map(
			static function ( WC_Payment_Token $token ): array {
				return [
					'tokenId'         => $token->get_id(),
					'paymentMethodId' => $token->get_token(),
					'isDefault'       => $token->get_is_default(),
					'displayName'     => $token->get_display_name(),
				];
			},
			array_values( $tokens )
		);
	}

	/**
	 * Checks whether the gateway is enabled.
	 *
	 * @return bool The result.
	 */
	public function is_enabled() {
		return 'yes' === $this->get_option( 'enabled' );
	}

	/**
	 * Disables gateway.
	 */
	public function disable() {
		$this->update_option( 'enabled', 'no' );
	}

	/**
	 * Enables gateway.
	 */
	public function enable() {
		$this->update_option( 'enabled', 'yes' );
	}

	/**
	 * Returns the list of enabled payment method types for UPE.
	 *
	 * @return string[]
	 */
	public function get_upe_enabled_payment_method_ids() {
		return $this->get_option(
			'upe_enabled_payment_method_ids',
			[
				'card',
			]
		);
	}

	/**
	 * Returns the list of statuses and capabilities available for UPE payment methods in the cached account.
	 *
	 * @return mixed[] The payment method statuses.
	 */
	public function get_upe_enabled_payment_method_statuses() {
		$account_data = $this->account->get_cached_account_data();
		$capabilities = $account_data['capabilities'] ?? [];
		$requirements = $account_data['capability_requirements'] ?? [];
		$statuses     = [];

		if ( $capabilities ) {
			foreach ( $capabilities as $capability_id => $status ) {
				$statuses[ $capability_id ] = [
					'status'       => $status,
					'requirements' => $requirements[ $capability_id ] ?? [],
				];
			}
		}

		return 0 === count( $statuses ) ? [
			'card_payments' => [
				'status'       => 'active',
				'requirements' => [],
			],
		] : $statuses;
	}

	/**
	 * Returns the mapping list between capability keys and payment type keys
	 *
	 * @return string[]
	 */
	public function get_payment_method_capability_key_map(): array {
		return $this->payment_method_capability_key_map;
	}

	/**
	 * Updates the account cache with the new payment method status, until it gets fetched again from the server.
	 *
	 * @return  void
	 */
	public function refresh_cached_account_data() {
		$this->account->refresh_account_data();
	}

	/**
	 * Updates the cached account data.
	 *
	 * @param string $property Property to update.
	 * @param mixed  $data     Data to update.
	 */
	public function update_cached_account_data( $property, $data ) {
		$this->account->update_account_data( $property, $data );
	}

	/**
	 * Returns the Stripe payment type of the selected payment method.
	 *
	 * @return string[]
	 */
	public function get_selected_stripe_payment_type_id() {
		return [
			'card',
		];
	}

	/**
	 * Returns the list of enabled payment method types that will function with the current checkout.
	 *
	 * @param string $order_id optional Order ID.
	 * @param bool   $force_currency_check optional Whether the currency check is required even if is_admin().
	 * @return string[]
	 */
	public function get_payment_method_ids_enabled_at_checkout( $order_id = null, $force_currency_check = false ) {
		return [
			'card',
		];
	}

	/**
	 * Returns the list of enabled payment method types that will function with the current checkout filtered by fees.
	 *
	 * @param string $order_id optional Order ID.
	 * @param bool   $force_currency_check optional Whether the currency check is required even if is_admin().
	 * @return string[]
	 */
	public function get_payment_method_ids_enabled_at_checkout_filtered_by_fees( $order_id = null, $force_currency_check = false ) {
		return $this->get_payment_method_ids_enabled_at_checkout( $order_id, $force_currency_check );
	}

	/**
	 * Returns the list of available payment method types for UPE.
	 * See https://stripe.com/docs/stripe-js/payment-element#web-create-payment-intent for a complete list.
	 *
	 * @return string[]
	 */
	public function get_upe_available_payment_methods() {
		return [
			'card',
		];
	}

	/**
	 * Text provided to users during onboarding setup.
	 *
	 * @return string
	 */
	public function get_setup_help_text() {
		return __( 'Next we’ll ask you to share a few details about your business to create your account.', 'woocommerce-payments' );
	}

	/**
	 * Get the connection URL.
	 *
	 * @return string Connection URL.
	 */
	public function get_connection_url() {
		$account_data = $this->account->get_cached_account_data();

		// The onboarding is finished if account_id is set. `Set up` will be shown instead of `Connect`.
		if ( isset( $account_data['account_id'] ) ) {
			return '';
		}
		return html_entity_decode( WC_Payments_Account::get_connect_url( 'WCADMIN_PAYMENT_TASK' ) );
	}

	/**
	 * Returns true if the code returned from the API represents an error that should be rate-limited.
	 *
	 * @param string $error_code The error code returned from the API.
	 *
	 * @return bool Whether the rate limiter should be bumped.
	 */
	protected function should_bump_rate_limiter( string $error_code ): bool {
		return in_array( $error_code, [ 'card_declined', 'incorrect_number', 'incorrect_cvc' ], true );
	}

	/**
	 * Move the email field to the top of the Checkout page.
	 *
	 * @param array $fields WooCommerce checkout fields.
	 *
	 * @return array WooCommerce checkout fields.
	 */
	public function checkout_update_email_field_priority( $fields ) {
		$is_link_enabled = in_array(
			Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
			\WC_Payments::get_gateway()->get_payment_method_ids_enabled_at_checkout_filtered_by_fees( null, true ),
			true
		);

		if ( $is_link_enabled ) {
			// Update the field priority.
			$fields['billing_email']['priority'] = 1;

			// Add extra `wcpay-checkout-email-field` class.
			$fields['billing_email']['class'][] = 'wcpay-checkout-email-field';

			add_filter( 'woocommerce_form_field_email', [ $this, 'append_stripelink_button' ], 10, 4 );
		}

		return $fields;
	}

	/**
	 * Append StripeLink button within email field for logged in users.
	 *
	 * @param string $field    - HTML content within email field.
	 * @param string $key      - Key.
	 * @param array  $args     - Arguments.
	 * @param string $value    - Default value.
	 *
	 * @return string $field    - Updated email field content with the button appended.
	 */
	public function append_stripelink_button( $field, $key, $args, $value ) {
		if ( 'billing_email' === $key ) {
			$field = str_replace( '</span>', '<button class="wcpay-stripelink-modal-trigger"></button></span>', $field );
		}
		return $field;
	}

	/**
	 * Returns the URL of the configuration screen for this gateway, for use in internal links.
	 *
	 * @return string URL of the configuration screen for this gateway
	 */
	public static function get_settings_url() {
		return WC_Payments_Admin_Settings::get_settings_url();
	}

	/**
	 * Get the right method description if WooPay is eligible.
	 *
	 * @return string
	 */
	public function get_method_description() {
		$description_links = [
			'br'                   => '<br/>',
			'tosLink'              => '<a href="https://wordpress.com/tos/" target="_blank" rel="noopener noreferrer">',
			'privacyLink'          => '<a href="https://automattic.com/privacy/" target="_blank" rel="noopener noreferrer">',
			'woopayMechantTosLink' => '<a href="https://wordpress.com/tos/#more-woopay-specifically" target="_blank" rel="noopener noreferrer">',
		];

		$description = WC_Payments_Utils::esc_interpolated_html(
			sprintf(
				/* translators: %1$s: WooPayments, tosLink: Link to terms of service page, privacyLink: Link to privacy policy page */
				__(
					'%1$s gives your store flexibility to accept credit cards, debit cards, and Apple Pay. Enable popular local payment methods and other digital wallets like Google Pay to give customers even more choice.<br/><br/>
			By using %1$s you agree to be bound by our <tosLink>Terms of Service</tosLink>  and acknowledge that you have read our <privacyLink>Privacy Policy</privacyLink>',
					'woocommerce-payments'
				),
				'WooPayments'
			),
			$description_links
		);

		if ( WooPay_Utilities::is_store_country_available() ) {
			$description = WC_Payments_Utils::esc_interpolated_html(
				sprintf(
					/* translators: %1$s: WooPayments, tosLink: Link to terms of service page, woopayMechantTosLink: Link to WooPay merchant terms, privacyLink: Link to privacy policy page */
					__(
						'Payments made simple — including WooPay, a new express checkout feature.<br/><br/>
				By using %1$s you agree to be bound by our <tosLink>Terms of Service</tosLink> (including WooPay <woopayMechantTosLink>merchant terms</woopayMechantTosLink>) and acknowledge that you have read our <privacyLink>Privacy Policy</privacyLink>',
						'woocommerce-payments'
					),
					'WooPayments'
				),
				$description_links
			);
		}

		return $description;
	}

	// Start: Deprecated functions.

	/**
	 * Check the defined constant to determine the current plugin mode.
	 *
	 * @deprecated 5.6.0
	 *
	 * @return bool
	 */
	public function is_in_dev_mode() {
		wc_deprecated_function( __FUNCTION__, '5.6.0', 'WC_Payments::mode()->is_dev()' );
		return WC_Payments::mode()->is_dev();
	}

	/**
	 * Returns whether test_mode or dev_mode is active for the gateway
	 *
	 * @deprecated 5.6.0
	 *
	 * @return boolean Test mode enabled if true, disabled if false
	 */
	public function is_in_test_mode() {
		wc_deprecated_function( __FUNCTION__, '5.6.0', 'WC_Payments::mode()->is_test()' );
		return WC_Payments::mode()->is_test();
	}

	/**
	 * Whether the current page is the WooPayments settings page.
	 *
	 * @deprecated 5.0.0
	 *
	 * @return bool
	 */
	public static function is_current_page_settings() {
		wc_deprecated_function( __FUNCTION__, '5.0.0', 'WC_Payments_Admin_Settings::is_current_page_settings' );
		return WC_Payments_Admin_Settings::is_current_page_settings();
	}

	/**
	 * Generates the configuration values, needed for payment fields.
	 *
	 * Isolated as a separate method in order to be available both
	 * during the classic checkout, as well as the checkout block.
	 *
	 * @deprecated use WC_Payments_Checkout::get_payment_fields_js_config instead.
	 *
	 * @return array
	 */
	public function get_payment_fields_js_config() {
		wc_deprecated_function( __FUNCTION__, '5.0.0', 'WC_Payments_Checkout::get_payment_fields_js_config' );
		return WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config();
	}

	/**
	 * Prepares customer data to be used on 'Pay for Order' or 'Add Payment Method' pages.
	 * Customer data is retrieved from order when on Pay for Order.
	 * Customer data is retrieved from customer when on 'Add Payment Method'.
	 *
	 * @deprecated use WC_Payments_Customer_Service::get_prepared_customer_data() instead.
	 *
	 * @return array|null An array with customer data or nothing.
	 */
	public function get_prepared_customer_data() {
		wc_deprecated_function( __FUNCTION__, '5.0.0', 'WC_Payments_Customer_Service::get_prepared_customer_data' );
		return WC_Payments::get_customer_service()->get_prepared_customer_data();
	}

	// End: Deprecated functions.

	/**
	 * Determine if current payment method is a platform payment method.
	 *
	 * @param boolean $is_using_saved_payment_method If it is using saved payment method.
	 *
	 * @return boolean True if it is a platform payment method.
	 */
	private function is_platform_payment_method( bool $is_using_saved_payment_method ) {
		// Return false for express checkout method.
		if ( isset( $_POST['payment_request_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		// Make sure the payment method being charged was created in the platform.
		if (
			! $is_using_saved_payment_method &&
			$this->should_use_stripe_platform_on_checkout_page()
		) {
			// This payment method was created under the platform account.
			return true;
		}

		return false;
	}

	/**
	 * Determine whether redirection is needed for the non-card UPE payment method.
	 *
	 * @param array $payment_methods The list of payment methods used for the order processing, usually consists of one method only.
	 * @return boolean True if the arrray consist of only one payment method which is not a card. False otherwise.
	 */
	private function upe_needs_redirection( $payment_methods ) {
		return 1 === count( $payment_methods ) && 'card' !== $payment_methods[0];
	}

	/**
	 * Modifies the create intent parameters when processing a payment.
	 *
	 * Currently used by child UPE_Split_Payment_Gateway to add required shipping information for Afterpay.
	 *
	 * @param Create_And_Confirm_Intention $request               The request object for creating and confirming intention.
	 * @param Payment_Information          $payment_information   The payment information object.
	 * @param WC_Order                     $order                 The order object.
	 *
	 * @return void
	 */
	protected function modify_create_intent_parameters_when_processing_payment( Create_And_Confirm_Intention $request, Payment_Information $payment_information, WC_Order $order ) {
		// Do nothing.
	}
}

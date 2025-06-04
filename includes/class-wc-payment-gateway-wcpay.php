<?php
/**
 * Class WC_Payment_Gateway_WCPay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Fraud_Meta_Box_Type;
use WCPay\Constants\Order_Mode;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Capture_Type;
use WCPay\Constants\Payment_Initiated_By;
use WCPay\Constants\Intent_Status;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Payment_Method;
use WCPay\Constants\Refund_Status;
use WCPay\Exceptions\{Add_Payment_Method_Exception,
	Amount_Too_Small_Exception,
	API_Merchant_Exception,
	Process_Payment_Exception,
	Intent_Authentication_Exception,
	API_Exception,
	Invalid_Address_Exception,
	Fraud_Prevention_Enabled_Exception,
	Invalid_Phone_Number_Exception,
	Rate_Limiter_Enabled_Exception,
	Order_ID_Mismatch_Exception,
	Order_Not_Found_Exception};
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
use WCPay\Duplicates_Detection_Service;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Fraud_Prevention\Fraud_Risk_Tools;
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\Payment_Methods\Link_Payment_Method;
use WCPay\WooPay\WooPay_Order_Status_Sync;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\Session_Rate_Limiter;
use WCPay\Tracker;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;
use WCPay\Payment_Methods\Affirm_Payment_Method;
use WCPay\Payment_Methods\Afterpay_Payment_Method;
use WCPay\Payment_Methods\Bancontact_Payment_Method;
use WCPay\Payment_Methods\Becs_Payment_Method;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Eps_Payment_Method;
use WCPay\Payment_Methods\Ideal_Payment_Method;
use WCPay\Payment_Methods\Klarna_Payment_Method;
use WCPay\Payment_Methods\P24_Payment_Method;
use WCPay\Payment_Methods\Sepa_Payment_Method;
use WCPay\Payment_Methods\UPE_Payment_Method;
use WCPay\Payment_Methods\Multibanco_Payment_Method;
use WCPay\Payment_Methods\Grabpay_Payment_Method;
use WCPay\PaymentMethods\Configs\Registry\PaymentMethodDefinitionRegistry;

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

	const PROCESS_REDIRECT_ORDER_MISMATCH_ERROR_CODE        = 'upe_process_redirect_order_id_mismatched';
	const UPE_APPEARANCE_TRANSIENT                          = 'wcpay_upe_appearance';
	const UPE_ADD_PAYMENT_METHOD_APPEARANCE_TRANSIENT       = 'wcpay_upe_add_payment_method_appearance';
	const WC_BLOCKS_UPE_APPEARANCE_TRANSIENT                = 'wcpay_wc_blocks_upe_appearance';
	const UPE_BNPL_PRODUCT_PAGE_APPEARANCE_TRANSIENT        = 'wcpay_upe_bnpl_product_page_appearance';
	const UPE_BNPL_CLASSIC_CART_APPEARANCE_TRANSIENT        = 'wcpay_upe_bnpl_classic_cart_appearance';
	const UPE_BNPL_CART_BLOCK_APPEARANCE_TRANSIENT          = 'wcpay_upe_bnpl_cart_block_appearance';
	const UPE_APPEARANCE_THEME_TRANSIENT                    = 'wcpay_upe_appearance_theme';
	const UPE_ADD_PAYMENT_METHOD_APPEARANCE_THEME_TRANSIENT = 'wcpay_upe_add_payment_method_appearance_theme';
	const WC_BLOCKS_UPE_APPEARANCE_THEME_TRANSIENT          = 'wcpay_wc_blocks_upe_appearance_theme';
	const UPE_BNPL_PRODUCT_PAGE_APPEARANCE_THEME_TRANSIENT  = 'wcpay_upe_bnpl_product_page_appearance_theme';
	const UPE_BNPL_CLASSIC_CART_APPEARANCE_THEME_TRANSIENT  = 'wcpay_upe_bnpl_classic_cart_appearance_theme';
	const UPE_BNPL_CART_BLOCK_APPEARANCE_THEME_TRANSIENT    = 'wcpay_upe_bnpl_cart_block_appearance_theme';

	/**
	 * The locations of appearance transients.
	 */
	const APPEARANCE_THEME_TRANSIENTS = [
		'checkout'     => [
			'blocks'  => self::WC_BLOCKS_UPE_APPEARANCE_THEME_TRANSIENT,
			'classic' => self::UPE_APPEARANCE_THEME_TRANSIENT,
		],
		'product_page' => [
			'blocks'  => self::UPE_BNPL_PRODUCT_PAGE_APPEARANCE_THEME_TRANSIENT,
			'classic' => self::UPE_BNPL_PRODUCT_PAGE_APPEARANCE_THEME_TRANSIENT,
		],
		'cart'         => [
			'blocks'  => self::UPE_BNPL_CART_BLOCK_APPEARANCE_THEME_TRANSIENT,
			'classic' => self::UPE_BNPL_CLASSIC_CART_APPEARANCE_THEME_TRANSIENT,
		],
	];

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
	 * Duplicate payment methods detection service
	 *
	 * @var Duplicates_Detection_Service
	 */
	protected $duplicate_payment_methods_detection_service;

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
	 * UPE Payment Method for gateway.
	 *
	 * @var UPE_Payment_Method
	 */
	protected $payment_method;

	/**
	 * Array mapping payment method string IDs to classes
	 *
	 * @var UPE_Payment_Method[]
	 */
	protected $payment_methods = [];

	/**
	 * Stripe payment method type ID.
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client                  - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                              - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service                     - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service                        - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service             - Action Scheduler service instance.
	 * @param UPE_Payment_Method                   $payment_method                       - Specific UPE_Payment_Method instance for gateway.
	 * @param array                                $payment_methods                      - Array of UPE payment methods.
	 * @param WC_Payments_Order_Service            $order_service                        - Order class instance.
	 * @param Duplicate_Payment_Prevention_Service $duplicate_payment_prevention_service - Service for preventing duplicate payments.
	 * @param WC_Payments_Localization_Service     $localization_service                 - Localization service instance.
	 * @param WC_Payments_Fraud_Service            $fraud_service                        - Fraud service instance.
	 * @param Duplicates_Detection_Service         $duplicate_payment_methods_detection_service - Service for finding duplicate enabled payment methods.
	 * @param Session_Rate_Limiter|null            $failed_transaction_rate_limiter      - Rate Limiter for failed transactions.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Token_Service $token_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		UPE_Payment_Method $payment_method,
		array $payment_methods,
		WC_Payments_Order_Service $order_service,
		Duplicate_Payment_Prevention_Service $duplicate_payment_prevention_service,
		WC_Payments_Localization_Service $localization_service,
		WC_Payments_Fraud_Service $fraud_service,
		Duplicates_Detection_Service $duplicate_payment_methods_detection_service,
		?Session_Rate_Limiter $failed_transaction_rate_limiter = null
	) {
		$this->payment_methods = $payment_methods;
		$this->payment_method  = $payment_method;
		$this->stripe_id       = $payment_method->get_id();

		$this->payments_api_client                         = $payments_api_client;
		$this->account                                     = $account;
		$this->customer_service                            = $customer_service;
		$this->token_service                               = $token_service;
		$this->action_scheduler_service                    = $action_scheduler_service;
		$this->failed_transaction_rate_limiter             = $failed_transaction_rate_limiter;
		$this->order_service                               = $order_service;
		$this->duplicate_payment_prevention_service        = $duplicate_payment_prevention_service;
		$this->localization_service                        = $localization_service;
		$this->fraud_service                               = $fraud_service;
		$this->duplicate_payment_methods_detection_service = $duplicate_payment_methods_detection_service;

		$this->id           = static::GATEWAY_ID;
		$this->icon         = $this->get_theme_icon();
		$this->has_fields   = true;
		$this->method_title = 'WooPayments';

		$this->description = '';
		$this->supports    = [
			'products',
			'refunds',
		];

		if ( 'card' !== $this->stripe_id ) {
			$this->id = self::GATEWAY_ID . '_' . $this->stripe_id;
		}

		/**
		 * FLAG: PAYMENT_METHODS_LIST
		 * Once all payment methods are converted to use definitions, they will all
		 * have a get_stripe_id() method that can be used instead of this map.
		 */

		// Capabilities have different keys than the payment method ID's,
		// so instead of appending '_payments' to the end of the ID, it'll be better
		// to have a map for it instead, just in case the pattern changes.
		$this->payment_method_capability_key_map = [
			'alipay'            => 'alipay_payments',
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
			'grabpay'           => 'grabpay_payments',
			'jcb'               => 'jcb_payments',
			'multibanco'        => 'multibanco_payments',
			'wechat_pay'        => 'wechat_pay_payments',
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
	 * Return the gateway's title.
	 *
	 * @return string
	 */
	public function get_title() {
		if ( ! $this->title ) {
			$this->title        = $this->payment_method->get_title();
			$this->method_title = "WooPayments ($this->title)";
		}
		return parent::get_title();
	}

	/**
	 * Get the form fields after they are initialized.
	 *
	 * @return array of options
	 */
	public function get_form_fields() {
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
				'default'     => 'default',
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
				'default'     => 'medium',
				'desc_tip'    => true,
				'options'     => [
					'small'  => __( 'Small', 'woocommerce-payments' ),
					'medium' => __( 'Medium', 'woocommerce-payments' ),
					'large'  => __( 'Large', 'woocommerce-payments' ),
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
			'platform_checkout_custom_message'   => [ 'default' => __( 'By placing this order, you agree to our [terms] and understand our [privacy_policy].', 'woocommerce-payments' ) ],
		];

		return parent::get_form_fields();
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'init', [ $this, 'maybe_update_properties_with_country' ] );
		// Only add certain actions/filter if this is the main gateway (i.e. not split UPE).
		if ( self::GATEWAY_ID === $this->id ) {
			add_action( 'woocommerce_order_actions', [ $this, 'add_order_actions' ] );
			add_action( 'woocommerce_order_action_capture_charge', [ $this, 'capture_charge' ] );
			add_action( 'woocommerce_order_action_cancel_authorization', [ $this, 'cancel_authorization' ] );
			add_action( 'woocommerce_order_status_cancelled', [ $this->order_service, 'cancel_authorizations_on_order_status_change' ] );
			add_action( 'woocommerce_order_status_completed', [ $this->order_service, 'capture_authorization_on_order_status_change' ], 10, 3 );

			add_action( 'wp_ajax_update_order_status', [ $this, 'update_order_status' ] );
			add_action( 'wp_ajax_nopriv_update_order_status', [ $this, 'update_order_status' ] );

			add_action( 'wp_ajax_create_setup_intent', [ $this, 'create_setup_intent_ajax' ] );

			// Update the current request logged_in cookie after a guest user is created to avoid nonce inconsistencies.
			add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );

			add_action( self::UPDATE_SAVED_PAYMENT_METHOD, [ $this, 'update_saved_payment_method' ], 10, 3 );

			// Update the email field position.
			add_filter( 'woocommerce_billing_fields', [ $this, 'checkout_update_email_field_priority' ], 50 );

			add_action( 'woocommerce_update_order', [ $this, 'schedule_order_tracking' ], 10, 2 );
			add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'setup_payment_error_handler' ], 10, 2 );

			add_filter( 'rest_request_before_callbacks', [ $this, 'remove_all_actions_on_preflight_check' ], 10, 3 );

			add_action( 'woocommerce_settings_save_general', [ $this, 'update_fraud_rules_based_on_general_options' ], 20 );
		}

		$this->maybe_init_subscriptions_hooks();
	}

	/**
	 * Updates icon and title using the account country.
	 * This method runs on init is not in the controller because get_account_country might
	 * make a request to the API if the account data is not cached.
	 *
	 * @return void
	 */
	public function maybe_update_properties_with_country(): void {
		if ( Afterpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID !== $this->stripe_id ) {
			return;
		}
		$account_country = $this->get_account_country();
		$this->icon      = $this->payment_method->get_icon( $account_country );
		$this->title     = $this->payment_method->get_title( $account_country );
	}

	/**
	 * Displays HTML tags for WC payment gateway radio button content.
	 */
	public function display_gateway_html() {
		?>
			<div class="wcpay-upe-element" data-payment-method-type="<?php echo esc_attr( $this->stripe_id ); ?>"></div>
		<?php
	}

	/**
	 * Renders the credit card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		do_action( 'wc_payments_set_gateway', $this->get_selected_stripe_payment_type_id() );
		do_action( 'wc_payments_add_upe_payment_fields' );
	}

	/**
	 * Adds a token to current user from a setup intent id.
	 *
	 * @param string  $setup_intent_id ID of the setup intent.
	 * @param WP_User $user            User to add token to.
	 *
	 * @return WC_Payment_Token_CC|WC_Payment_Token_WCPay_SEPA|null The added token.
	 */
	public function create_token_from_setup_intent( $setup_intent_id, $user ) {
		try {
			$setup_intent_request = Get_Setup_Intention::create( $setup_intent_id );
			/** @var WC_Payments_API_Setup_Intention $setup_intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$setup_intent = $setup_intent_request->send();

			$payment_method_id = $setup_intent->get_payment_method_id();
			// TODO: When adding SEPA and Sofort, we will need a new API call to get the payment method and from there get the type.
			// Leaving 'card' as a hardcoded value for now to avoid the extra API call.
			// $payment_method = $this->payment_methods['card'];// Maybe this should be enforced.
			$payment_method = $this->payment_method;

			return $payment_method->get_payment_token_for_user( $user, $payment_method_id );
		} catch ( Exception $e ) {
			wc_add_notice( WC_Payments_Utils::get_filtered_error_message( $e ), 'error', [ 'icon' => 'error' ] );
			Logger::log( 'Error when adding payment method: ' . $e->getMessage() );
		}
	}

	/**
	 * Validate order_id received from the request vs value saved in the intent metadata.
	 * Throw an exception if they're not matched.
	 *
	 * @param  WC_Order $order The received order to process.
	 * @param  array    $intent_metadata The metadata of attached intent to the order.
	 *
	 * @return void
	 * @throws Process_Payment_Exception
	 */
	private function validate_order_id_received_vs_intent_meta_order_id( WC_Order $order, array $intent_metadata ): void {
		$intent_meta_order_id_raw = $intent_metadata['order_id'] ?? '';
		$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;

		if ( $order->get_id() !== $intent_meta_order_id ) {
			Logger::error(
				sprintf(
					'UPE Process Redirect Payment - Order ID mismatched. Received: %1$d. Intent Metadata Value: %2$d',
					$order->get_id(),
					$intent_meta_order_id
				)
			);

			throw new Process_Payment_Exception(
				__( "We're not able to process this payment due to the order ID mismatch. Please try again later.", 'woocommerce-payments' ),
				self::PROCESS_REDIRECT_ORDER_MISMATCH_ERROR_CODE
			);
		}
	}

	/**
	 * If we're in a WooPay preflight check, remove all the checkout order processed
	 * actions to prevent a quantity reduction of the available resources.
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
			// Avoid increasing coupon usage count during preflight check.
			remove_all_actions( 'woocommerce_order_status_pending' );

			// Avoid creating new accounts during preflight check.
			remove_all_filters( 'woocommerce_checkout_registration_required' );
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
	 * Only apply during the checkout process with the account creation.
	 *
	 * @param string $cookie New cookie value.
	 */
	public function set_cookie_on_current_request( $cookie ) {
		if ( defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT && did_action( 'woocommerce_created_customer' ) > 0 ) {
			$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
		}
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
	 * Called directly by WooCommerce Core.
	 *
	 * @return bool
	 */
	public function is_account_partially_onboarded(): bool {
		return $this->account->is_stripe_connected() && ! $this->account->is_details_submitted();
	}

	/**
	 * Returns the URL of the configuration screen for this gateway, for use in internal links.
	 * Called directly by WooCommerce Core.
	 *
	 * @return string URL of the configuration screen for this gateway
	 */
	public static function get_settings_url() {
		return WC_Payments_Admin_Settings::get_settings_url();
	}

	/**
	 * Text provided to users during onboarding setup.
	 * Called directly by WooCommerce Core.
	 *
	 * @return string
	 */
	public function get_setup_help_text() {
		return __( 'Next we’ll ask you to share a few details about your business to create your account.', 'woocommerce-payments' );
	}

	/**
	 * Get the connection URL.
	 * Called directly by WooCommerce Core.
	 *
	 * @return string Connection URL.
	 */
	public function get_connection_url() {
		// If we have an account, `Set up` will be shown instead of `Connect`.
		if ( $this->is_connected() ) {
			return '';
		}

		// Note: Payments Task is not a very accurate from value, but it is the best we can do, for now.
		return html_entity_decode( WC_Payments_Account::get_connect_url( WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_TASK ) );
	}

	/**
	 * Add a url to the admin order page that links directly to the transactions detail view for authorized intent statuses.
	 *
	 * Called directly by WooCommerce Core.
	 *
	 * @since 1.4.0
	 *
	 * @param WC_Order $order The context passed into this function when the user view the order details page in WordPress admin.
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		$intent_status = $this->order_service->get_intention_status_for_order( $order );
		if ( ! in_array( $intent_status, Intent_Status::AUTHORIZED_STATUSES, true ) ) {
			return '';
		}

		$intent_id = $this->order_service->get_intent_id_for_order( $order );
		$charge_id = $this->order_service->get_charge_id_for_order( $order );

		return WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
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
		if ( ! WC_Payments::get_gateway()->is_enabled() ) {
			return false;
		}

		$payment_method_id         = $this->payment_method->get_id();
		$processing_payment_method = $this->payment_methods[ $payment_method_id ];
		if ( ! $processing_payment_method->is_enabled_at_checkout( $this->get_account_country() ) ) {
			return false;
		}

		$payment_method_statuses  = $this->get_upe_enabled_payment_method_statuses();
		$stripe_key               = $this->get_payment_method_capability_key_map()[ $payment_method_id ] ?? null;
		$is_payment_method_active = array_key_exists( $stripe_key, $payment_method_statuses ) && 'active' === $payment_method_statuses[ $stripe_key ]['status'];
		if ( false === $is_payment_method_active ) {
			return false;
		}

		if ( ! $this->payment_method->is_reusable() && is_add_payment_method_page() ) {
			return false;
		}

		// Disable the gateway if using live mode without HTTPS set up or the currency is not
		// available in the country of the account.
		if ( $this->needs_https_setup() || ! $this->is_available_for_current_currency() ) {
			return false;
		}

		// Disable the gateway if it should not be displayed on the checkout page.
		$is_gateway_enabled = in_array( $this->stripe_id, $this->get_payment_method_ids_enabled_at_checkout(), true ) ? true : false;
		if ( ! $is_gateway_enabled ) {
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
	 * Checks if the setting to show the payment request buttons is enabled.
	 *
	 * @return bool Whether the setting to show the payment request buttons is enabled or not.
	 */
	public function is_payment_request_enabled() {
		return 'yes' === $this->get_option( 'payment_request' );
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

		// to ensure the WC Core script that prompts the "unsaved changes" dialog appears.
		// that script interferes with merchant actions.
		wp_dequeue_script( 'woocommerce_settings' );

		$method_title = $this->get_method_title();
		$return_url   = 'admin.php?page=wc-settings&tab=checkout';
		if ( ! empty( $_GET['method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Override the title and return url for method-specific pages in WooPayments settings.
			$method       = sanitize_text_field( wp_unslash( $_GET['method'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$method_title = 'payment_request' === $method ? 'Apple Pay / Google Pay' : ( 'woopay' === $method ? 'WooPay' : $this->get_method_title() );
			$return_url   = 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments';
		}

		if ( function_exists( 'wc_back_header' ) ) {
			wc_back_header( $method_title, __( 'Return to payments', 'woocommerce-payments' ), $return_url );
		} else {
			// Until the wc_back_header function is available (WC Core 9.9) use the current available version.
			echo '<h2>';
			echo esc_html( $method_title );
			wc_back_link( __( 'Return to payments', 'woocommerce-payments' ), $return_url );
			echo '</h2>';
		}

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
		if ( 'card' !== $this->stripe_id ) {
			return false;
		}

		if (
			WC_Payments_Features::is_woopay_eligible() &&
			'yes' === $this->get_option( 'platform_checkout', 'no' ) &&
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
	 * Process the payment for a given order.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 * @throws Exception Error processing the payment.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		try {
			if ( 20 < strlen( $order->get_billing_phone() ) ) {
				throw new Invalid_Phone_Number_Exception(
					__( 'Invalid phone number.', 'woocommerce-payments' ),
					'invalid_phone_number'
				);
			}
			// Check if session exists and we're currently not processing a WooPay request before instantiating `Fraud_Prevention_Service`.
			if ( WC()->session && ! apply_filters( 'wcpay_is_woopay_store_api_request', false ) ) {
				$fraud_prevention_service = Fraud_Prevention_Service::get_instance();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( $fraud_prevention_service->is_enabled() && ! $fraud_prevention_service->verify_token( $_POST['wcpay-fraud-prevention-token'] ?? null ) ) {
					throw new Fraud_Prevention_Enabled_Exception(
						__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
						'fraud_prevention_enabled'
					);
				}
			}

			if ( $this->failed_transaction_rate_limiter->is_limited() ) {
				throw new Rate_Limiter_Enabled_Exception(
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
			$blocked_by_fraud_rules = $this->is_blocked_by_fraud_rules( $e );

			do_action( 'woocommerce_payments_order_failed', $order, $e );

			/**
			 * TODO: Determine how to do this update with Order_Service.
			 * It seems that the status only needs to change in certain instances, and within those instances the intent
			 * information is not added to the order, as shown by tests.
			 */
			if ( ! $blocked_by_fraud_rules && ( empty( $payment_information ) || ! $payment_information->is_changing_payment_method_for_subscription() ) ) {
				$order->update_status( Order_Status::FAILED );
			}

			if ( $e instanceof API_Exception && $this->should_bump_rate_limiter( $e->get_error_code() ) ) {
				$this->failed_transaction_rate_limiter->bump();
			}

			if ( $blocked_by_fraud_rules ) {
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
				if ( $e instanceof API_Merchant_Exception ) {
					$error_details = $error_details . '. ' . esc_html( rtrim( $e->get_merchant_message(), '.' ) );
				}

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

			// This allows WC to check if WP_DEBUG mode is enabled before returning previous Exception and expose Exception class name to frontend.
			add_filter( 'woocommerce_return_previous_exceptions', '__return_true' );
			wc_add_notice( wp_strip_all_tags( WC_Payments_Utils::get_filtered_error_message( $e, $blocked_by_fraud_rules ) ), 'error' );
			do_action( 'update_payment_result_on_error', $e, $order );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
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
	 * Sets up a handler to add error details to the payment result.
	 * Registers an action to handle 'update_payment_result_on_error',
	 * using the payment result object from 'woocommerce_rest_checkout_process_payment_with_context'.
	 *
	 * @param PaymentContext $context The payment context.
	 * @param PaymentResult  $result  The payment result, passed by reference.
	 */
	public function setup_payment_error_handler( PaymentContext $context, PaymentResult &$result ) {
		add_action(
			'update_payment_result_on_error',
			function ( $error ) use ( &$result ) {
				$result->set_payment_details(
					array_merge(
						$result->payment_details,
						[ 'errorMessage' => wp_strip_all_tags( $error->getMessage() ) ]
					)
				);
			}
		);
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
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 * @throws API_Exception
	 * @throws Exception When amount too small.
	 * @throws Invalid_Address_Exception
	 * @throws Order_Not_Found_Exception
	 * @throws Order_ID_Mismatch_Exception When the payment intent could not be authenticated.
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

		$customer_details_options = [
			'is_woopay' => filter_var( $metadata['paid_on_woopay'] ?? false, FILTER_VALIDATE_BOOLEAN ),
		];

		if ( $payment_information->get_customer_id() ) {
			$user        = $order->get_user();
			$customer_id = $payment_information->get_customer_id();
		} else {
			list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order, $customer_details_options );
		}

		$intent_failed  = false;
		$payment_needed = $amount > 0;

		// Make sure that we attach the payment method and the customer ID to the order meta data.
		$payment_method = $payment_information->get_payment_method();
		$this->order_service->set_payment_method_id_for_order( $order, $payment_method );
		$this->order_service->set_customer_id_for_order( $order, $customer_id );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, WC_Payments::mode()->is_test() ? Order_Mode::TEST : Order_Mode::PRODUCTION );

		// If an error happened during the payment setup in the client it will be saved in the payment information so we can throw
		// the error here and follow the standard failed order flow.
		$error = $payment_information->get_error();
		if ( ! is_null( $error ) ) {
			throw new \Exception( $error->get_error_message() );
		}

		// In case amount is 0 and we're not saving the payment method, we won't be using intents and can confirm the order payment.
		if ( apply_filters( 'wcpay_confirm_without_payment_intent', ! $payment_needed && ! $save_payment_method_to_store ) ) {
			$order->payment_complete();

			if ( $payment_information->is_using_saved_payment_method() ) {
				// We need to make sure the saved payment method is saved to the order so we can
				// charge the payment method for a future payment.
				$this->add_token_to_order( $order, $payment_information->get_payment_token() );
				// If we are not hitting the API for the intent, we need to update the saved payment method ourselves.
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

			$order->set_payment_method_title( __( 'Credit / Debit Cards', 'woocommerce-payments' ) );
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

			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$woopay_intent_id = WooPay_Utilities::sanitize_intent_id( wp_unslash( $_POST['platform-checkout-intent'] ?? '' ) );

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
					throw new Order_ID_Mismatch_Exception(
						sprintf(
							/* translators: %s: metadata. We do not need to translate WooPayMeta */
							esc_html( __( 'We\'re not able to process this payment. Please try again later. WooPayMeta: intent_meta_order_id: %1$s, order_id: %2$s', 'woocommerce-payments' ) ),
							esc_attr( $intent_meta_order_id ),
							esc_attr( $order_id )
						),
						'order_id_mismatch'
					);
				}
			}

			if ( empty( $intent ) ) {
				$payment_methods = $this->get_payment_method_types( $payment_information );

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
				if ( $payment_information->is_using_saved_payment_method() ) {
					$billing_details = $this->order_service->get_billing_data_from_order( $order );

					$is_legacy_card_object = (bool) preg_match( '/^(card_|src_)/', $payment_information->get_payment_method() );

					// Not updating billing details for legacy card objects because they have a different structure and are no longer supported.
					if ( ! empty( $billing_details ) && ! $is_legacy_card_object ) {
						$request->set_payment_method_update_data( [ 'billing_details' => $billing_details ] );
					}
				}
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

				// For Stripe Link & SEPA, we must create mandate to acknowledge that terms have been shown to customer.
				if ( $this->is_mandate_data_required() ) {
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
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$woopay_intent_id = WooPay_Utilities::sanitize_intent_id( wp_unslash( $_POST['platform-checkout-intent'] ?? '' ) );

			if ( ! empty( $woopay_intent_id ) ) {
				// If the setup intent is included in the request use that intent.
				$setup_intent_request = Get_Setup_Intention::create( $woopay_intent_id );
				/** @var WC_Payments_API_Setup_Intention $setup_intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent = $setup_intent_request->send();

				$intent_metadata          = $intent->get_metadata();
				$intent_meta_order_id_raw = $intent_metadata['order_id'] ?? '';
				$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;

				if ( $intent_meta_order_id !== $order_id ) {
					throw new Order_ID_Mismatch_Exception(
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

		$is_offline_payment_method = $payment_information->is_offline_payment_method();
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
				$next_action_type = $next_action['type'] ?? null;
				if ( 'redirect_to_url' === $next_action_type && ! empty( $next_action[ $next_action_type ]['url'] ) ) {
					$response = [
						'result'   => 'success',
						'redirect' => $next_action[ $next_action_type ]['url'],
					];
				} elseif ( 'multibanco_display_details' === $next_action_type ) {
					$this->order_service->attach_multibanco_info_to_order(
						$order,
						$next_action[ $next_action_type ]['reference'],
						$next_action[ $next_action_type ]['entity'],
						$next_action[ $next_action_type ]['hosted_voucher_url'],
						$next_action[ $next_action_type ]['expires_at']
					);
				} else {
					$response = [
						'result'         => 'success',
						// Include a new nonce for update_order_status to ensure the update order
						// status call works when a guest user creates an account during checkout.
						'redirect'       => sprintf(
							'#wcpay-confirm-%s:%s:%s:%s',
							$payment_needed ? 'pi' : 'si',
							$order_id,
							$client_secret,
							wp_create_nonce( 'wcpay_update_order_status_nonce' ),
						),
						// Include the payment method ID so the Blocks integration can save cards.
						'payment_method' => $payment_information->get_payment_method(),
					];
				}
			} elseif ( $this->is_changing_payment_method_for_subscription() ) {
				// Only attempt to use WC_Subscriptions_Change_Payment_Gateway if it exists.
				if ( class_exists( 'WC_Subscriptions_Change_Payment_Gateway' ) ) {
					// Update the payment method for subscription if the payment intent is not requiring action.
					WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $order, $payment_information->get_payment_method() );
				}

				// Because this new payment does not require action/confirmation, remove this filter so that WC_Subscriptions_Change_Payment_Gateway proceeds to update all subscriptions if flagged.
				remove_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', [ $this, 'update_payment_method_for_subscriptions' ], 10 );
			}
		}

		$allow_update_on_success = $this->is_changing_payment_method_for_subscription() || $this->is_subscription_item_in_cart();
		$this->order_service->attach_intent_info_to_order( $order, $intent, $allow_update_on_success );
		$this->attach_exchange_info_to_order( $order, $charge_id );
		if ( Intent_Status::SUCCEEDED === $status || ( Intent_Status::REQUIRES_ACTION === $status && $is_offline_payment_method ) ) {
			$this->duplicate_payment_prevention_service->remove_session_processing_order( $order->get_id() );
		}
		$this->order_service->update_order_status_from_intent( $order, $intent );
		$this->order_service->attach_transaction_fee_to_order( $order, $charge );

		$this->maybe_add_customer_notification_note( $order, $processing );

		// ensuring the payment method title is set before any early return paths to avoid incomplete order data.
		if ( empty( $_POST['payment_request_type'] ) && empty( $_POST['express_payment_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			// Extract payment method details for setting the payment method title.
			if ( $payment_needed ) {
				$charge                 = $intent ? $intent->get_charge() : null;
				$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
				// For payment intents, get payment method type from the intent itself, fallback to charge details.
				$payment_method_type = $intent ? $intent->get_payment_method_type() : null;
				if ( ! $payment_method_type && $payment_method_details ) {
					$payment_method_type = $payment_method_details['type'] ?? null;
				}

				if ( 'card' === $payment_method_type && isset( $payment_method_details['card']['last4'] ) ) {
					$order->add_meta_data( 'last4', $payment_method_details['card']['last4'], true );
					if ( isset( $payment_method_details['card']['brand'] ) ) {
						$order->add_meta_data( '_card_brand', $payment_method_details['card']['brand'], true );
					}
					$order->save_meta_data();
				}
			} else {
				$payment_method_details = false;
				$token                  = $payment_information->is_using_saved_payment_method() ? $payment_information->get_payment_token() : null;
				$payment_method_type    = $token ? $this->get_payment_method_type_for_setup_intent( $intent, $token ) : null;
			}

			$this->set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details );
		}

		if ( isset( $status ) && Intent_Status::REQUIRES_ACTION === $status && $this->is_changing_payment_method_for_subscription() ) {
			// Because we're filtering woocommerce_subscriptions_update_payment_via_pay_shortcode, we need to manually set this delayed update all flag here.
			if ( isset( $_POST['update_all_subscriptions_payment_method'] ) && wc_clean( wp_unslash( $_POST['update_all_subscriptions_payment_method'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$order->update_meta_data( '_delayed_update_payment_method_all', wc_clean( wp_unslash( $_POST['payment_method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$order->save();
			}

			wp_safe_redirect( $response['redirect'] );
			exit;
		}

		if ( isset( $response ) && ! $is_offline_payment_method ) {
			return $response;
		}

		wc_maybe_reduce_stock_levels( $order_id );
		if ( isset( $cart ) ) {
			$cart->empty_cart();
		}

		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

		/**
		 * Check for a redirect payment method on order received page or setup intent on payment methods page.
		 */
	public function maybe_process_upe_redirect() {
		if ( $this->is_payment_methods_page() ) {
			// If a payment method was added using UPE, we need to clear the cache and notify the user.
			if ( $this->is_setup_intent_success_creation_redirection() ) {
					wc_add_notice( __( 'Payment method successfully added.', 'woocommerce-payments' ) );
					$user = wp_get_current_user();
					$this->customer_service->clear_cached_payment_methods_for_user( $user->ID );
			}
			return;
		}

		if ( ! is_order_received_page() ) {
			return;
		}

		$payment_method = isset( $_GET['wc_payment_method'] ) ? wc_clean( wp_unslash( $_GET['wc_payment_method'] ) ) : '';
		if ( self::GATEWAY_ID !== $payment_method ) {
			return;
		}

		$is_nonce_valid = check_admin_referer( 'wcpay_process_redirect_order_nonce' );
		if ( ! $is_nonce_valid || empty( $_GET['wc_payment_method'] ) ) {
			return;
		}

		if ( ! empty( $_GET['payment_intent_client_secret'] ) ) {
			$intent_id_from_request = isset( $_GET['payment_intent'] ) ? wc_clean( wp_unslash( $_GET['payment_intent'] ) ) : '';
		} elseif ( ! empty( $_GET['setup_intent_client_secret'] ) ) {
			$intent_id_from_request = isset( $_GET['setup_intent'] ) ? wc_clean( wp_unslash( $_GET['setup_intent'] ) ) : '';
		} else {
			return;
		}

		$order_id               = absint( get_query_var( 'order-received' ) );
		$order_key_from_request = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		$save_payment_method    = isset( $_GET['save_payment_method'] ) ? 'yes' === wc_clean( wp_unslash( $_GET['save_payment_method'] ) ) : false;

		if ( empty( $intent_id_from_request ) || empty( $order_id ) || empty( $order_key_from_request ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			// the ID of non-existing order was passed in.
			return;
		}

		if ( $order->get_order_key() !== $order_key_from_request ) {
			// Valid return url should have matching order key.
			return;
		}

		// Perform additional checks for non-zero-amount. For zero-amount orders, we can't compare intents because they are not attached to the order at this stage.
		// Once https://github.com/Automattic/woocommerce-payments/issues/6575 is closed, this check can be applied for zero-amount orders as well.
		if ( $order->get_total() > 0 && ! $this->is_proper_intent_used_with_order( $order, $intent_id_from_request ) ) {
			return;
		}

		$this->process_redirect_payment( $order, $intent_id_from_request, $save_payment_method );
	}

	/**
	 * Processes redirect payments.
	 *
	 * @param WC_Order $order The order being processed.
	 * @param string   $intent_id The Stripe setup/payment intent ID for the order payment.
	 * @param bool     $save_payment_method Boolean representing whether payment method for order should be saved.
	 *
	 * @throws Process_Payment_Exception When the payment intent has an error.
	 */
	public function process_redirect_payment( $order, $intent_id, $save_payment_method ) {
		try {
			$order_id = $order->get_id();
			if ( $order->has_status(
				[
					Order_Status::PROCESSING,
					Order_Status::COMPLETED,
					Order_Status::ON_HOLD,
				]
			) ) {
				return;
			}

			Logger::log( "Begin processing UPE redirect payment for order {$order_id} for the amount of {$order->get_total()}" );

			// Get user/customer for order.
			list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order );

			$payment_needed = 0 < $order->get_total();

			// Get payment intent to confirm status.
			if ( $payment_needed ) {
				$request = Get_Intention::create( $intent_id );
				$request->set_hook_args( $order );
				/** @var WC_Payments_API_Payment_Intention $intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent                 = $request->send();
				$client_secret          = $intent->get_client_secret();
				$status                 = $intent->get_status();
				$charge                 = $intent->get_charge();
				$charge_id              = $charge ? $charge->get_id() : null;
				$currency               = $intent->get_currency();
				$payment_method_id      = $intent->get_payment_method_id();
				$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
				$payment_method_type    = $this->get_payment_method_type_from_payment_details( $payment_method_details );
				$error                  = $intent->get_last_payment_error();

				// This check applies to payment intents only due to two reasons:
				// (1) metadata is missed for setup intents. See https://github.com/Automattic/woocommerce-payments/issues/6575.
				// (2) most issues so far affect only payment intents.
				$intent_metadata = is_array( $intent->get_metadata() ) ? $intent->get_metadata() : [];
				$this->validate_order_id_received_vs_intent_meta_order_id( $order, $intent_metadata );
			} else {
				$request = Get_Setup_Intention::create( $intent_id );
				/** @var WC_Payments_API_Setup_Intention $intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent                 = $request->send();
				$client_secret          = $intent->get_client_secret();
				$status                 = $intent->get_status();
				$charge_id              = '';
				$charge                 = null;
				$currency               = $order->get_currency();
				$payment_method_id      = $intent->get_payment_method_id();
				$payment_method_details = false;
				$payment_method_type    = $intent->get_payment_method_type();
				$error                  = $intent->get_last_setup_error();
			}

			if ( ! empty( $error ) ) {
				Logger::log( 'Error when processing payment: ' . $error['message'] );
				throw new Process_Payment_Exception(
					__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
					'upe_payment_intent_error'
				);
			} else {
				$payment_method = $this->get_selected_payment_method( $payment_method_type );
				if ( ! $payment_method ) {
					return;
				}

				if ( $save_payment_method && $payment_method->is_reusable() ) {
					try {
						$token = $payment_method->get_payment_token_for_user( $user, $payment_method_id );
						$this->add_token_to_order( $order, $token );
					} catch ( Exception $e ) {
						// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
						Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
					}
				}

				$this->order_service->attach_intent_info_to_order( $order, $intent );
				$this->attach_exchange_info_to_order( $order, $charge_id );
				if ( Intent_Status::SUCCEEDED === $status ) {
					$this->duplicate_payment_prevention_service->remove_session_processing_order( $order->get_id() );
				}
				$this->set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details );
				$this->order_service->update_order_status_from_intent( $order, $intent );
				$this->order_service->attach_transaction_fee_to_order( $order, $charge );

				if ( Intent_Status::REQUIRES_ACTION === $status ) {
					// I don't think this case should be possible, but just in case...
					$next_action = $intent->get_next_action();
					if ( isset( $next_action['type'] ) && 'redirect_to_url' === $next_action['type'] && ! empty( $next_action['redirect_to_url']['url'] ) ) {
						wp_safe_redirect( $next_action['redirect_to_url']['url'] );
						exit;
					} else {
						$redirect_url = sprintf(
							'#wcpay-confirm-%s:%s:%s:%s',
							$payment_needed ? 'pi' : 'si',
							$order_id,
							$client_secret,
							wp_create_nonce( 'wcpay_update_order_status_nonce' )
						);
						wp_safe_redirect( $redirect_url );
						exit;
					}
				}
			}
		} catch ( Exception $e ) {
			Logger::log( 'Error: ' . $e->getMessage() );

			$is_order_id_mismatched_exception =
				$e instanceof Process_Payment_Exception
				&& self::PROCESS_REDIRECT_ORDER_MISMATCH_ERROR_CODE === $e->get_error_code();

			// If the order ID mismatched exception is thrown, do not mark the order as failed.
			// Because the outcome of the payment intent is for another order, not for the order processed here.
			if ( ! $is_order_id_mismatched_exception ) {
				// Confirm our needed variables are set before using them due to there could be a server issue during the get_intent process.
				$status    = $status ?? null;
				$charge_id = $charge_id ?? null;

				/* translators: localized exception message */
				$message = sprintf( __( 'UPE payment failed: %s', 'woocommerce-payments' ), $e->getMessage() );
				$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id, $message );
			}

			wc_add_notice( WC_Payments_Utils::get_filtered_error_message( $e ), 'error' );

			$redirect_url = wc_get_checkout_url();
			if ( $is_order_id_mismatched_exception ) {
				$redirect_url = add_query_arg( self::PROCESS_REDIRECT_ORDER_MISMATCH_ERROR_CODE, 'yes', $redirect_url );
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Mandate must be shown and acknowledged by customer before deferred intent UPE payment can be processed.
	 * This applies to SEPA and Link payment methods.
	 * https://stripe.com/docs/payments/finalize-payments-on-the-server
	 *
	 * @return boolean True if mandate must be shown and acknowledged by customer before deferred intent UPE payment can be processed, false otherwise.
	 */
	public function is_mandate_data_required() {
		$is_stripe_link_enabled = Payment_Method::CARD === $this->get_selected_stripe_payment_type_id() && in_array( Payment_Method::LINK, $this->get_upe_enabled_payment_method_ids(), true );
		$is_sepa_debit_payment  = Payment_Method::SEPA === $this->get_selected_stripe_payment_type_id();

		return $is_stripe_link_enabled || $is_sepa_debit_payment;
	}

	/**
	 * Get the payment method chosen by the customer for the payment processing.
	 * This payment method is needed in case of the deferred intent creation flow only, because this is the only time when the current gateway might process payments other than of the card type.
	 * Payment method is only one in case of the deferred intent creation flow, hence the first element of the array is returned.
	 *
	 * @return string|null Payment method to use for the intent.
	 */
	public function get_payment_method_to_use_for_intent() {
		$requested_payment_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return $this->get_payment_methods_from_gateway_id( $requested_payment_method )[0];
	}

	/**
	 * Get payment method types to attach to intention request.
	 *
	 * @param Payment_Information $payment_information Payment information object for transaction.
	 * @return array List of payment methods.
	 */
	public function get_payment_method_types( $payment_information ): array {
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

		// If $gateway_id is `woocommerce_payments`, this must be the CC gateway.
		// We only need to return single `card` payment method, adding `link` since Stripe Link is also supported.
		$payment_methods = [ Payment_Method::CARD ];
		if ( in_array( Payment_Method::LINK, $eligible_payment_methods, true ) ) {
			$payment_methods[] = Payment_Method::LINK;
		}

		return $payment_methods;
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
	 * Set formatted readable payment method title for order,
	 * using payment method details from accompanying charge.
	 *
	 * @param \WC_Order  $order WC Order being processed.
	 * @param string     $payment_method_type Stripe payment method key.
	 * @param array|bool $payment_method_details Array of payment method details from charge or false.
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details ) {
		$payment_method = $this->get_selected_payment_method( $payment_method_type );
		if ( ! $payment_method ) {
			return;
		}

		$payment_method_title = $payment_method->get_title( $this->get_account_country(), $payment_method_details );

		$payment_gateway = in_array( $payment_method->get_id(), [ Payment_Method::CARD, Payment_Method::LINK ], true ) ? self::GATEWAY_ID : self::GATEWAY_ID . '_' . $payment_method_type;

		$order->set_payment_method( $payment_gateway );
		$order->set_payment_method_title( $payment_method_title );
		$order->save();
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
		$service = wcpay_get_container()->get( OrderService::class );
		return $service->get_payment_metadata( $order->get_id(), $payment_type );
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

		// Refund without an amount is a no-op, but required to succeed in
		// case merchant needs it to re-stock order items.
		if ( '0.00' === sprintf( '%0.2f', $amount ?? 0 ) ) {
			return true;
		}

		// If the entered amount is not valid stop without making a request.
		if ( $amount < 0 || $amount > $order->get_total() ) {
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
				// These are reasons supported by Stripe https://stripe.com/docs/api/refunds/create#create_refund-reason.
				if ( in_array( $reason, [ 'duplicate', 'fraudulent', 'requested_by_customer' ], true ) ) {
					$refund_request->set_reason( $reason );
				}
				$refund = $refund_request->send();
			}
			$currency = strtoupper( $refund['currency'] );
			Tracker::track_admin( 'wcpay_edit_order_refund_success' );
		} catch ( Exception $e ) {
			if ( $e instanceof API_Exception && 'insufficient_balance_for_refund' === $e->get_error_code() ) {
				// Handle insufficient_balance_for_refund error.
				$this->order_service->handle_insufficient_balance_for_refund( $order, WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ) );
			} else {
				$note = sprintf(
					/* translators: %1: the successfully charged amount, %2: error message */
					__( 'A refund of %1$s failed to complete: %2$s', 'woocommerce-payments' ),
					WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $amount, [ 'currency' => $currency ] ), $order ),
					$e->getMessage()
				);

				Logger::log( $note );
				$order->add_order_note( $note );
			}

			$this->order_service->set_wcpay_refund_status_for_order( $order, 'failed' );
			Tracker::track_admin( 'wcpay_edit_order_refund_failure', [ 'reason' => $note ] );
			return new WP_Error( 'wcpay_edit_order_refund_failure', $e->getMessage() );
		}

		$wc_refund = WC_Payments_Utils::get_last_refund_from_order_id( $order->get_id() );
		if ( ! $wc_refund ) {
			// translators: %1$: order id.
			return new WP_Error( 'wcpay_edit_order_refund_not_found', sprintf( __( 'A refund cannot be found for order: %1$s', 'woocommerce-payments' ), $order->get_id() ) );
		}
		// There is no error. Refund status can be either pending or succeeded, add a note to the order and update the refund status.
		$this->order_service->add_note_and_metadata_for_created_refund( $order, $wc_refund, $refund['id'], $refund['balance_transaction'] ?? null, Refund_Status::PENDING === $refund['status'] );

		return true;
	}

	/**
	 * Checks whether a refund through the gateway has already failed.
	 *
	 * @param WC_Order $order The order to check.
	 * @return boolean
	 */
	public function has_refund_failed( $order ) {
		return Refund_Status::FAILED === $this->order_service->get_wcpay_refund_status_for_order( $order );
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
		return $this->plugin_id . $this->id . '_settings';
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
			}
		}
	}

	/**
	 * Updates whether woopay global theme support is enabled or disabled.
	 *
	 * @param bool $value Whether woopay global theme support should be enabled.
	 */
	public function update_is_woopay_global_theme_support_enabled( $value ) {
		$current_value = 'yes' === $this->get_option( 'is_woopay_global_theme_support_enabled', 'no' );

		if ( $value !== $current_value ) {
			WC_Payments::woopay_tracker()->maybe_record_admin_event(
				$value ? 'woopay_global_theme_support_enabled' : 'woopay_global_theme_support_disabled',
				[ 'test_mode' => WC_Payments::mode()->is_test() ? 1 : 0 ]
			);

			$this->update_option( 'is_woopay_global_theme_support_enabled', $value ? 'yes' : 'no' );
		}
	}


	/**
	 * Checks whether the WooPay global theme support is enabled.
	 *
	 * @return bool The result.
	 */
	public function is_woopay_global_theme_support_enabled() {
		return WC_Payments_Features::is_woopay_global_theme_support_eligible() && 'yes' === $this->get_option( 'is_woopay_global_theme_support_enabled' );
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
			Logger::error( 'Failed to get account business URL.' . $e );
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
			Logger::error( 'Failed to get account business support address.' . $e );
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
			Logger::error( 'Failed to get business support email.' . $e );
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
			Logger::error( 'Failed to get account business support phone.' . $e );
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
			Logger::error( 'Failed to get account branding logo.' . $e );
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
			Logger::error( 'Failed to get account\'s branding icon.' . $e );
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
			Logger::error( 'Failed to get account\'s branding primary color.' . $e );
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
			Logger::error( 'Failed to get account\'s branding secondary color.' . $e );
		}

		return $default_value;
	}

	/**
	 * Retrieves the domestic currency of the current account based on its country.
	 * It will fallback to the store's currency if the account's country is not supported.
	 *
	 * @return string The domestic currency code.
	 */
	public function get_account_domestic_currency(): string {
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
			return $this->account->get_account_default_currency();
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
	public function get_account_country( string $default_value = Country_Code::UNITED_STATES ): string {
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
	 * Checks if a fraud protection rule is enabled.
	 *
	 * @param string $rule The rule to check.
	 *
	 * @return bool True if the rule is enabled, false otherwise.
	 */
	protected function is_fraud_rule_enabled( string $rule ): bool {
		$settings = $this->get_advanced_fraud_protection_settings();

		if ( ! is_array( $settings ) ) {
			return false;
		}

		foreach ( $settings as $setting ) {
			if ( $rule === $setting['key'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the transaction was blocked by AVS verification fraud rule.
	 *
	 * @param string|null $error_code The error code to check.
	 * @param string|null $error_type The error type to check.
	 *
	 * @return bool True if the transaction was blocked by the AVS verification fraud rule, false otherwise.
	 */
	private function is_blocked_by_avs_verification_fraud_rule( ?string $error_code, ?string $error_type ): bool {
		$is_avs_verification_rule_enabled = $this->is_fraud_rule_enabled( 'avs_verification' );
		$is_incorrect_zip_error           = 'card_error' === $error_type && 'incorrect_zip' === $error_code;

		return $is_avs_verification_rule_enabled && $is_incorrect_zip_error;
	}

	/**
	 * Checks if the transaction was blocked by fraud rules.
	 *
	 * @param Exception $e The exception to check.
	 *
	 * @return bool True if the transaction was blocked by fraud rules, false otherwise.
	 */
	protected function is_blocked_by_fraud_rules( Exception $e ): bool {
		if ( ! ( $e instanceof API_Exception ) ) {
			return false;
		}

		$error_code = $e->get_error_code() ?? null;
		$error_type = $e->get_error_type() ?? null;

		$blocked_by_fraud_rule = 'wcpay_blocked_by_fraud_rule' === $error_code;

		// Since the AVS mismatch is part of the advanced fraud prevention, we need to consider that as a blocked order.
		$blocked_by_avs_mismatch = $this->is_blocked_by_avs_verification_fraud_rule( $error_code, $error_type );

		return $blocked_by_fraud_rule || $blocked_by_avs_mismatch;
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

		if ( false === $cached_server_settings ) {
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
	 * Updates the fraud rules depending on some settings when those settings have changed.
	 *
	 * @return  void           This is a readonly action.
	 */
	public function update_fraud_rules_based_on_general_options() {
		// If the protection level is not "advanced", no need to run this, because it won't contain the IP country filter.
		if ( 'advanced' !== $this->get_current_protection_level() ) {
			return;
		}

		// If the ruleset can't be parsed, skip updating.
		$ruleset = $this->get_advanced_fraud_protection_settings();
		if (
			'error' === $ruleset
			|| ! is_array( $ruleset )
			|| ! Fraud_Risk_Tools::is_valid_ruleset_array( $ruleset )
		) {
			return;
		}

		$needs_update = false;
		foreach ( $ruleset as &$rule_array ) {
			if ( isset( $rule_array['key'] ) && Fraud_Risk_Tools::RULE_INTERNATIONAL_IP_ADDRESS === $rule_array['key'] ) {
				$new_rule_array = Fraud_Risk_Tools::get_international_ip_address_rule()->to_array();
				if ( isset( $rule_array['check'] )
					&& isset( $new_rule_array['check'] )
					&& wp_json_encode( $rule_array['check'] ) !== wp_json_encode( $new_rule_array['check'] )
				) {
					$rule_array   = $new_rule_array;
					$needs_update = true;
				}
			}
		}

		// Update the possibly changed values on the server, and the transient.
		if ( $needs_update ) {
			$this->payments_api_client->save_fraud_ruleset( $ruleset );
			set_transient( 'wcpay_fraud_protection_settings', $ruleset, DAY_IN_SECONDS );
		}
	}

	/**
	 * The URL for the current payment method's icon.
	 *
	 * @return string The payment method icon URL.
	 */
	public function get_icon_url() {
		return $this->payment_method->get_icon();
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
		$error_code               = null;

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
				$error_code    = $e->get_error_code();
				$extra_details = [];

				if ( $e instanceof Amount_Too_Small_Exception ) {
					$extra_details          = [
						'minimum_amount'          => $e->get_minimum_amount(),
						'minimum_amount_currency' => strtoupper( $e->get_currency() ),
					];
					$minimum_amount_details = sprintf(
						/* translators: %s: formatted minimum amount with currency */
						__( 'The minimum amount to capture is %s.', 'woocommerce-payments' ),
						WC_Payments_Utils::format_explicit_currency(
							WC_Payments_Utils::interpret_stripe_amount( $e->get_minimum_amount(), $e->get_currency() ),
							$e->get_currency()
						)
					);
					$error_message = $error_message . ' ' . $minimum_amount_details;
				}

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
				$error_code    = $e->get_error_code();
			}
		}

		Tracker::track_admin( 'wcpay_merchant_captured_auth' );

		// There is a possibility of the intent being null, so we need to get the charge_id safely.
		$charge    = ! empty( $intent ) ? $intent->get_charge() : null;
		$charge_id = ! empty( $charge ) ? $charge->get_id() : $this->order_service->get_charge_id_for_order( $order );

		$this->attach_exchange_info_to_order( $order, $charge_id );

		if ( Intent_Status::SUCCEEDED === $status ) {
			$this->order_service->process_captured_payment( $order, $intent );
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
			'status'        => $status ?? 'failed',
			'id'            => ! empty( $intent ) ? $intent->get_id() : null,
			'message'       => $error_message,
			'http_code'     => $http_code,
			'error_code'    => $error_code,
			'extra_details' => $extra_details ?? [],
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

			$amount                 = $order->get_total();
			$payment_method_details = false;
			$is_changing_payment    = isset( $_POST['is_changing_payment'] ) && filter_var( wp_unslash( $_POST['is_changing_payment'] ), FILTER_VALIDATE_BOOLEAN );

			if ( $amount > 0 && ! $is_changing_payment ) {
				// An exception is thrown if an intent can't be found for the given intent ID.
				$request = Get_Intention::create( $intent_id );
				$request->set_hook_args( $order );
				$intent = $request->send();

				$status    = $intent->get_status();
				$charge    = $intent->get_charge();
				$charge_id = ! empty( $charge ) ? $charge->get_id() : null;

				$this->attach_exchange_info_to_order( $order, $charge_id );
				$this->order_service->attach_intent_info_to_order( $order, $intent );
				$this->order_service->attach_transaction_fee_to_order( $order, $charge );
			} else {
				// For $0 orders, fetch the Setup Intent instead.
				$setup_intent_request = Get_Setup_Intention::create( $intent_id );
				/** @var WC_Payments_API_Setup_Intention $setup_intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
				$intent    = $setup_intent_request->send();
				$status    = $intent->get_status();
				$charge_id = '';
			}

			$payment_method_id = $intent->get_payment_method_id();

			if ( Intent_Status::SUCCEEDED === $status ) {
				$this->duplicate_payment_prevention_service->remove_session_processing_order( $order->get_id() );
			}
			$this->order_service->update_order_status_from_intent( $order, $intent );

			if ( $intent->is_authorized() ) {
				wc_reduce_stock_levels( $order_id );
				WC()->cart->empty_cart();

				$is_subscription            = function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order );
				$should_save_payment_method = $is_subscription || ( isset( $_POST['should_save_payment_method'] ) && 'true' === $_POST['should_save_payment_method'] );
				if ( $should_save_payment_method && ! empty( $payment_method_id ) ) {
					try {
						$token = $this->token_service->add_payment_method_to_user( $payment_method_id, wp_get_current_user() );
						$this->add_token_to_order( $order, $token );

						if ( ! empty( $token ) ) {
							$payment_method_type = $this->get_payment_method_type_for_setup_intent( $intent, $token );
							$this->set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details );
						}
					} catch ( Exception $e ) {
						// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
						Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
					}
				}

				$return_url = $this->get_return_url( $order );

				if ( $is_changing_payment ) {
					$payment_token = $this->get_payment_token( $order );
					if ( class_exists( 'WC_Subscriptions_Change_Payment_Gateway' ) ) {
						WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $order, $payment_token->get_gateway_id() );
						$notice = __( 'Payment method updated.', 'woocommerce-payments' );

						if ( WC_Subscriptions_Change_Payment_Gateway::will_subscription_update_all_payment_methods( $order ) && WC_Subscriptions_Change_Payment_Gateway::update_all_payment_methods_from_subscription( $order, $token->get_gateway_id() ) ) {
							$notice = __( 'Payment method updated for all your current subscriptions.', 'woocommerce-payments' );
						}

						wc_add_notice( $notice );
					}
					$return_url = method_exists( $order, 'get_view_order_url' ) ? $order->get_view_order_url() : $this->get_return_url( $order );
				}

				// Send back redirect URL in the successful case.
				echo wp_json_encode(
					[
						'return_url' => $return_url,
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
	public function create_intent( WC_Order $order, array $payment_methods, string $capture_method = 'automatic', array $metadata = [], ?string $customer_id = null ) {
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
			if ( ! check_ajax_referer( 'wcpay_create_setup_intent_nonce', false, false ) ) {
				throw new Add_Payment_Method_Exception(
					__( "We're not able to add this payment method. Please refresh the page and try again.", 'woocommerce-payments' ),
					'invalid_referrer'
				);
			}

			if ( WC_Rate_Limiter::retried_too_soon( 'add_payment_method_' . get_current_user_id() ) ) {
				throw new Add_Payment_Method_Exception(
					__( 'You cannot add a new payment method so soon after the previous one. Please try again later.', 'woocommerce-payments' ),
					'retried_too_soon'
				);
			}

			$setup_intent        = $this->create_and_confirm_setup_intent();
			$setup_intent_output = [
				'id'            => $setup_intent->get_id(),
				'status'        => $setup_intent->get_status(),
				'client_secret' => $setup_intent->get_client_secret(),
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
				WC_Payments_Utils::get_filtered_error_status_code( $e )
			);
		}
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
	 * @return string
	 */
	public function get_selected_stripe_payment_type_id() {
		return $this->stripe_id;
	}

	/**
	 * Returns the list of enabled payment method types for UPE that are available based on the manual capture setting.
	 *
	 * @return string[]
	 */
	public function get_upe_enabled_payment_method_ids_based_on_manual_capture() {
		$automatic_capture = empty( $this->get_option( 'manual_capture' ) ) || $this->get_option( 'manual_capture' ) === 'no';
		if ( $automatic_capture ) {
			return $this->get_upe_enabled_payment_method_ids();
		}

		return array_intersect( $this->get_upe_enabled_payment_method_ids(), [ Payment_Method::CARD, Payment_Method::LINK ] );
	}

	/**
	 * Returns the list of enabled payment method types that will function with the current checkout.
	 *
	 * @param string $order_id optional Order ID.
	 * @param bool   $force_currency_check optional Whether the currency check is required even if is_admin().
	 *
	 * @return string[]
	 */
	public function get_payment_method_ids_enabled_at_checkout( $order_id = null, $force_currency_check = false ) {
		$upe_enabled_payment_methods = $this->get_upe_enabled_payment_method_ids_based_on_manual_capture();
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$force_currency_check = true;
		}

		$enabled_payment_methods = [];
		$active_payment_methods  = $this->get_upe_enabled_payment_method_statuses();

		foreach ( $upe_enabled_payment_methods as $payment_method_id ) {
			$payment_method_capability_key = $this->payment_method_capability_key_map[ $payment_method_id ] ?? 'undefined_capability_key';
			if ( isset( $this->payment_methods[ $payment_method_id ] ) ) {
				// When creating a payment intent, we need to ensure the currency is matching
				// with the payment methods which are sent with the payment intent request, otherwise
				// Stripe returns an error.

				// In order to allow payment methods to be displayed in admin pages (e.g. blocks editor),
				// we need to skip the currency check (unless force_currency_check is true).
				// force_currency_check = 0 is_admin = 0 -> skip_currency_check = 0.
				// force_currency_check = 0 is_admin = 1 -> skip_currency_check = 1.
				// force_currency_check = 1 is_admin = 0 -> skip_currency_check = 0.
				// force_currency_check = 1 is_admin = 1 -> skip_currency_check = 0.

				$skip_currency_check       = ! $force_currency_check && is_admin();
				$processing_payment_method = $this->payment_methods[ $payment_method_id ];
				if ( $processing_payment_method->is_enabled_at_checkout( $this->get_account_country(), $skip_currency_check ) && ( $skip_currency_check || $processing_payment_method->is_currency_valid( $this->get_account_domestic_currency(), $order_id ) ) ) {
					$status = $active_payment_methods[ $payment_method_capability_key ]['status'] ?? null;
					if ( 'active' === $status ) {
						$enabled_payment_methods[] = $payment_method_id;
					}
				}
			}
		}

		// if credit card payment method is not enabled, we don't use stripe link.
		if (
			! in_array( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID, $enabled_payment_methods, true ) &&
			in_array( Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID, $enabled_payment_methods, true ) ) {
			$enabled_payment_methods = array_filter(
				$enabled_payment_methods,
				static function ( $method ) {
					return Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID !== $method;
				}
			);
		}

		return $enabled_payment_methods;
	}

	/**
	 * Returns the list of enabled payment method types that will function with the current checkout filtered by fees.
	 *
	 * @param string $order_id optional Order ID.
	 * @param bool   $force_currency_check optional Whether the currency check is required even if is_admin().
	 * @return string[]
	 */
	public function get_payment_method_ids_enabled_at_checkout_filtered_by_fees( $order_id = null, $force_currency_check = false ) {
		$enabled_payment_methods = $this->get_payment_method_ids_enabled_at_checkout( $order_id, $force_currency_check );
		$methods_with_fees       = array_keys( $this->account->get_fees() );

		return array_values( array_intersect( $enabled_payment_methods, $methods_with_fees ) );
	}

	/**
	 * Returns the list of available payment method types for UPE.
	 * See https://stripe.com/docs/stripe-js/payment-element#web-create-payment-intent for a complete list.
	 *
	 * @return string[]
	 */
	public function get_upe_available_payment_methods() {
		$available_methods = [ 'card' ];

		/**
		 * FLAG: PAYMENT_METHODS_LIST
		 * As payment methods are converted to use definitions, they need to be removed from the list below.
		 */
		$available_methods[] = Becs_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Bancontact_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Eps_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Ideal_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Sepa_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = P24_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Affirm_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Afterpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Klarna_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Multibanco_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$available_methods[] = Grabpay_Payment_Method::PAYMENT_METHOD_STRIPE_ID;

		// This gets all the registered payment method definitions. As new payment methods are converted from the legacy style, they need to be removed from the list above.
		$payment_method_definitions = PaymentMethodDefinitionRegistry::instance()->get_all_payment_method_definitions();

		foreach ( $payment_method_definitions as $definition_class ) {
			$available_methods[] = $definition_class::get_id();
		}

		$available_methods = array_values(
			apply_filters(
				'wcpay_upe_available_payment_methods',
				$available_methods
			)
		);

		$methods_with_fees = array_keys( $this->account->get_fees() );

		return array_values( array_intersect( $available_methods, $methods_with_fees ) );
	}

	/**
	 * Handle AJAX request for saving UPE appearance value to transient.
	 *
	 * @throws Exception - If nonce or setup intent is invalid.
	 */
	public function save_upe_appearance_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wcpay_save_upe_appearance_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception(
					__( 'Unable to update UPE appearance values at this time.', 'woocommerce-payments' )
				);
			}

			$elements_location = isset( $_POST['elements_location'] ) ? wc_clean( wp_unslash( $_POST['elements_location'] ) ) : null;
			$appearance        = isset( $_POST['appearance'] ) ? json_decode( wc_clean( wp_unslash( $_POST['appearance'] ) ) ) : null;

			$valid_locations = [ 'blocks_checkout', 'shortcode_checkout', 'bnpl_product_page', 'bnpl_classic_cart', 'bnpl_cart_block', 'add_payment_method' ];
			if ( ! $elements_location || ! in_array( $elements_location, $valid_locations, true ) ) {
				throw new Exception(
					__( 'Unable to update UPE appearance values at this time.', 'woocommerce-payments' )
				);
			}

			if ( in_array( $elements_location, [ 'blocks_checkout', 'shortcode_checkout' ], true ) ) {
				$is_blocks_checkout = 'blocks_checkout' === $elements_location;
				/**
				 * This filter is only called on "save" of the appearance, to avoid calling it on every page load.
				 * If you apply changes through this filter, you'll need to clear the transient data to see them at checkout.
				 *
				 * @deprecated 7.4.0 Use {@see 'wcpay_elements_appearance'} instead.
				 * @since 7.3.0
				 */
				$appearance = apply_filters_deprecated( 'wcpay_upe_appearance', [ $appearance, $is_blocks_checkout ], '7.4.0', 'wcpay_elements_appearance' );
			}

			/**
			 * This filter is only called on "save" of the appearance, to avoid calling it on every page load.
			 * If you apply changes through this filter, you'll need to clear the transient data to see them at checkout.
			 * $elements_location can be 'blocks_checkout', 'shortcode_checkout', 'bnpl_product_page', 'bnpl_classic_cart', 'bnpl_cart_block', 'add_payment_method'.
			 *
			 * @since 7.4.0
			 */
			$appearance = apply_filters( 'wcpay_elements_appearance', $appearance, $elements_location );

			$appearance_transient       = [
				'shortcode_checkout' => self::UPE_APPEARANCE_TRANSIENT,
				'add_payment_method' => self::UPE_ADD_PAYMENT_METHOD_APPEARANCE_TRANSIENT,
				'blocks_checkout'    => self::WC_BLOCKS_UPE_APPEARANCE_TRANSIENT,
				'bnpl_product_page'  => self::UPE_BNPL_PRODUCT_PAGE_APPEARANCE_TRANSIENT,
				'bnpl_classic_cart'  => self::UPE_BNPL_CLASSIC_CART_APPEARANCE_TRANSIENT,
				'bnpl_cart_block'    => self::UPE_BNPL_CART_BLOCK_APPEARANCE_TRANSIENT,
			][ $elements_location ];
			$appearance_theme_transient = [
				'shortcode_checkout' => self::UPE_APPEARANCE_THEME_TRANSIENT,
				'add_payment_method' => self::UPE_ADD_PAYMENT_METHOD_APPEARANCE_THEME_TRANSIENT,
				'blocks_checkout'    => self::WC_BLOCKS_UPE_APPEARANCE_THEME_TRANSIENT,
				'bnpl_product_page'  => self::UPE_BNPL_PRODUCT_PAGE_APPEARANCE_THEME_TRANSIENT,
				'bnpl_classic_cart'  => self::UPE_BNPL_CLASSIC_CART_APPEARANCE_THEME_TRANSIENT,
				'bnpl_cart_block'    => self::UPE_BNPL_CART_BLOCK_APPEARANCE_THEME_TRANSIENT,
			][ $elements_location ];

			if ( null !== $appearance ) {
				set_transient( $appearance_transient, $appearance, DAY_IN_SECONDS );
				set_transient( $appearance_theme_transient, $appearance->theme, DAY_IN_SECONDS );
			}

			wp_send_json_success( $appearance, 200 );
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => WC_Payments_Utils::get_filtered_error_message( $e ),
					],
				],
				WC_Payments_Utils::get_filtered_error_status_code( $e )
			);
		}
	}

	/**
	 * Clear the saved UPE appearance transient value.
	 */
	public function clear_upe_appearance_transient() {
		delete_transient( self::UPE_APPEARANCE_TRANSIENT );
		delete_transient( self::WC_BLOCKS_UPE_APPEARANCE_TRANSIENT );
		delete_transient( self::UPE_BNPL_PRODUCT_PAGE_APPEARANCE_TRANSIENT );
		delete_transient( self::UPE_BNPL_CLASSIC_CART_APPEARANCE_TRANSIENT );
		delete_transient( self::UPE_BNPL_CART_BLOCK_APPEARANCE_TRANSIENT );
		delete_transient( self::UPE_APPEARANCE_THEME_TRANSIENT );
		delete_transient( self::WC_BLOCKS_UPE_APPEARANCE_THEME_TRANSIENT );
		delete_transient( self::UPE_BNPL_PRODUCT_PAGE_APPEARANCE_THEME_TRANSIENT );
		delete_transient( self::UPE_BNPL_CLASSIC_CART_APPEARANCE_THEME_TRANSIENT );
		delete_transient( self::UPE_BNPL_CART_BLOCK_APPEARANCE_THEME_TRANSIENT );
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
	 * Returns boolean for whether payment gateway supports saved payments.
	 *
	 * @return bool True, if gateway supports saved payments. False, otherwise.
	 */
	public function should_support_saved_payments() {
		return $this->is_enabled_for_saved_payments( $this->stripe_id );
	}

	/**
	 * Returns true when viewing payment methods page.
	 *
	 * @return bool
	 */
	private function is_payment_methods_page() {
		global $wp;

		$page_id = wc_get_page_id( 'myaccount' );

		return ( $page_id && is_page( $page_id ) && ( isset( $wp->query_vars['payment-methods'] ) ) );
	}

	/**
	 * Verifies that the proper intent is used to process the order.
	 *
	 * @param WC_Order $order The order object based on the order_id received from the request.
	 * @param string   $intent_id_from_request The intent ID received from the request.
	 *
	 * @return bool True if the proper intent is used to process the order, false otherwise.
	 */
	public function is_proper_intent_used_with_order( $order, $intent_id_from_request ) {
		$intent_id_attached_to_order = $this->order_service->get_intent_id_for_order( $order );
		if ( ! hash_equals( $intent_id_attached_to_order, $intent_id_from_request ) ) {
			Logger::error(
				sprintf(
					'Intent ID mismatch. Received in request: %1$s. Attached to order: %2$s. Order ID: %3$d',
					$intent_id_from_request,
					$intent_id_attached_to_order,
					$order->get_id()
				)
			);
			return false;
		}
		return true;
	}

	/**
	 * True if the request contains the values that indicates a redirection after a successful setup intent creation.
	 *
	 * @return bool
	 */
	public function is_setup_intent_success_creation_redirection() {
		return ! empty( $_GET['setup_intent_client_secret'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! empty( $_GET['setup_intent'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! empty( $_GET['redirect_status'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'succeeded' === $_GET['redirect_status']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Function to be used with array_filter
	 * to filter UPE payment methods that support saved payments
	 *
	 * @param string $payment_method_id Stripe payment method.
	 *
	 * @return bool
	 */
	public function is_enabled_for_saved_payments( $payment_method_id ) {
		$payment_method = $this->get_selected_payment_method( $payment_method_id );
		if ( ! $payment_method ) {
			return false;
		}
		return $payment_method->is_reusable()
			&& ( is_admin() || $payment_method->is_currency_valid( $this->get_account_domestic_currency() ) );
	}

	/**
	 * Move the email field to the top of the Checkout page.
	 *
	 * @param array $fields WooCommerce checkout fields.
	 *
	 * @return array WooCommerce checkout fields.
	 */
	public function checkout_update_email_field_priority( $fields ) {
		if ( is_checkout() || has_block( 'woocommerce/checkout' ) ) {
			$is_link_enabled = in_array(
				Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
				\WC_Payments::get_gateway()->get_payment_method_ids_enabled_at_checkout_filtered_by_fees( null, true ),
				true
			);

			if ( $is_link_enabled && isset( $fields['billing_email'] ) ) {
				// Update the field priority.
				$fields['billing_email']['priority'] = 1;

				// Add extra `wcpay-checkout-email-field` class.
				$fields['billing_email']['class'][] = 'wcpay-checkout-email-field';

				add_filter( 'woocommerce_form_field_email', [ $this, 'append_stripelink_button' ], 10, 4 );
			}
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
	 * Get selected UPE payment methods.
	 *
	 * @param string $selected_upe_payment_type Selected payment methods.
	 * @param array  $enabled_payment_methods Enabled payment methods.
	 *
	 * @return array
	 */
	protected function get_selected_upe_payment_methods( string $selected_upe_payment_type, array $enabled_payment_methods ) {
		$payment_methods = [];
		if ( '' !== $selected_upe_payment_type ) {
			// Only update the payment_method_types if we have a reference to the payment type the customer selected.
			$payment_methods[] = $selected_upe_payment_type;

			if ( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID === $selected_upe_payment_type ) {
				$is_link_enabled = in_array(
					Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
					$enabled_payment_methods,
					true
				);
				if ( $is_link_enabled ) {
					$payment_methods[] = Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
				}
			}
		}
		return $payment_methods;
	}

	/**
	 * Gets UPE_Payment_Method instance from ID.
	 *
	 * @param string $payment_method_type Stripe payment method type ID.
	 * @return UPE_Payment_Method|false UPE payment method instance.
	 */
	public function get_selected_payment_method( $payment_method_type ) {
		return WC_Payments::get_payment_method_by_id( $payment_method_type );
	}

	/**
	 * Return the payment method type from the payment method details.
	 *
	 * @param array $payment_method_details Payment method details.
	 * @return string|null Payment method type or nothing.
	 */
	private function get_payment_method_type_from_payment_details( $payment_method_details ) {
		return $payment_method_details['type'] ?? null;
	}

	/**
	 * Get the payment method used with a setup intent.
	 *
	 * @param WC_Payments_API_Setup_Intention $intent The PaymentIntent object.
	 * @param WC_Payment_Token                $token The payment token.
	 * @return string|null The payment method type.
	 */
	private function get_payment_method_type_for_setup_intent( $intent, $token ) {
		return 'wcpay_link' !== $token->get_type() ? $intent->get_payment_method_type() : Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
	}

	/**
	 * This function wraps WC_Payments::get_payment_method_map, useful for unit testing.
	 *
	 * @return array Array of UPE_Payment_Method instances.
	 */
	public function wc_payments_get_payment_method_map() {
		return WC_Payments::get_payment_method_map();
	}

	/**
	 * Returns the payment methods for this gateway.
	 *
	 * @return array|UPE_Payment_Method[]
	 */
	public function get_payment_methods() {
		return $this->payment_methods;
	}

	/**
	 * Returns the UPE payment method for the gateway.
	 *
	 * @return UPE_Payment_Method
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * Returns Stripe payment method type ID.
	 *
	 * @return string
	 */
	public function get_stripe_id() {
		return $this->stripe_id;
	}

	/**
	 * This function wraps WC_Payments::get_payment_gateway_by_id, useful for unit testing.
	 *
	 * @param string $payment_method_id Stripe payment method type ID.
	 * @return false|WC_Payment_Gateway_WCPay Matching UPE Payment Gateway instance.
	 */
	public function wc_payments_get_payment_gateway_by_id( $payment_method_id ) {
		return WC_Payments::get_payment_gateway_by_id( $payment_method_id );
	}

	/**
	 * This function wraps WC_Payments::get_payment_method_by_id, useful for unit testing.
	 *
	 * @param string $payment_method_id Stripe payment method type ID.
	 * @return false|UPE_Payment_Method Matching UPE Payment Method instance.
	 */
	public function wc_payments_get_payment_method_by_id( $payment_method_id ) {
		return WC_Payments::get_payment_method_by_id( $payment_method_id );
	}

	/**
	 * Checks if UPE appearance theme is set and returns appropriate icon URL.
	 *
	 * @return string
	 */
	public function get_theme_icon() {
		$upe_appearance_theme = get_transient( self::UPE_APPEARANCE_THEME_TRANSIENT );
		if ( $upe_appearance_theme ) {
			return 'night' === $upe_appearance_theme ? $this->payment_method->get_dark_icon() : $this->payment_method->get_icon();
		}
		return $this->payment_method->get_icon();
	}

	/**
	 * Get the right method description if WooPay is eligible.
	 *
	 * @return string
	 */
	public function get_method_description() {
		$description = sprintf(
			/* translators: %1$s: WooPayments */
			__(
				'%1$s gives your store flexibility to accept credit cards, debit cards, and Apple Pay. Enable popular local payment methods and other digital wallets like Google Pay to give customers even more choice.',
				'woocommerce-payments'
			),
			'WooPayments'
		);

		if ( WooPay_Utilities::is_store_country_available() ) {
			$description = sprintf(
				/* translators: %s: WooPay,  */
				__(
					'Payments made simple — including %s, a new express checkout feature.',
					'woocommerce-payments'
				),
				'WooPay'
			);
		}

		return $description;
	}

	/**
	 * Calls duplicate payment methods detection service to find duplicates.
	 * This method acts as a wrapper. The approach should be reverted once
	 * https://github.com/Automattic/woocommerce-payments/issues/7464 is resolved.
	 */
	public function find_duplicates() {
		return $this->duplicate_payment_methods_detection_service->find_duplicates();
	}

	/**
	 * Get the recommended payment methods list.
	 *
	 * @param string $country_code Optional. The business location country code. Provide a 2-letter ISO country code.
	 *                             If not provided, the account country will be used if the account is connected.
	 *                             Otherwise, the store's base country will be used.
	 *
	 * @return array List of recommended payment methods for the given country.
	 *               Empty array if there are no recommendations available.
	 *               Each item in the array should be an associative array with at least the following entries:
	 *               - @string id: The payment method ID.
	 *               - @string title: The payment method title/name.
	 *               - @bool enabled: Whether the payment method is enabled.
	 *               - @int order/priority: The order/priority of the payment method.
	 */
	public function get_recommended_payment_methods( string $country_code = '' ): array {
		if ( empty( $country_code ) ) {
			// If the account is connected, use the account country.
			if ( $this->account->is_provider_connected() ) {
				$country_code = $this->get_account_country();
			} else {
				// If the account is not connected, use the store's base country.
				$country_code = WC()->countries->get_base_country();
			}
		}

		return $this->account->get_recommended_payment_methods( $country_code );
	}

	/**
	 * Determine whether redirection is needed for the non-card UPE payment method.
	 *
	 * @param array $payment_methods The list of payment methods used for the order processing, usually consists of one method only.
	 * @return boolean True if the array consist of only one payment method which is not a card. False otherwise.
	 */
	private function upe_needs_redirection( $payment_methods ) {
		return 1 === count( $payment_methods ) && 'card' !== $payment_methods[0];
	}

	/**
	 * Handles the shipping requirement for Afterpay payments.
	 *
	 * This method extracts the shipping and billing data from the order and sets the appropriate
	 * shipping data for the Afterpay payment request. If neither shipping nor billing data is valid
	 * for shipping, an exception is thrown.
	 *
	 * @param WC_Order                     $order    The order object containing shipping and billing information.
	 * @param Create_And_Confirm_Intention $request The Afterpay payment request object to set shipping data on.
	 *
	 * @throws Invalid_Address_Exception If neither shipping nor billing address is valid for Afterpay payments.
	 * @return void
	 */
	private function handle_afterpay_shipping_requirement( WC_Order $order, Create_And_Confirm_Intention $request ): void {
		$wc_locale_data = WC()->countries->get_country_locale();

		$check_if_usable = function ( array $address ) use ( $wc_locale_data ): bool {
			if ( $address['country'] && isset( $wc_locale_data[ $address['country'] ] ) ) {
				$country_locale_data = $wc_locale_data[ $address['country'] ];
				$fields_to_check     = [
					'state'     => 'state',
					'city'      => 'city',
					'postcode'  => 'postal_code',
					'address_1' => 'line1',
				];

				foreach ( $fields_to_check as $locale_field => $address_field ) {
					$is_field_required = (
						! isset( $country_locale_data[ $locale_field ] ) ||
						! isset( $country_locale_data[ $locale_field ]['required'] ) ||
						$country_locale_data[ $locale_field ]['required']
					);

					if ( $is_field_required && ! $address[ $address_field ] ) {
						return false;
					}
				}

				return true;
			}

			return $address['country'] && $address['state'] && $address['city'] && $address['postal_code'] && $address['line1'];
		};

		$shipping_data = $this->order_service->get_shipping_data_from_order( $order );
		if ( $check_if_usable( $shipping_data['address'] ) ) {
			$request->set_shipping( $shipping_data );
			return;
		}

		$billing_data = $this->order_service->get_billing_data_from_order( $order );
		// Afterpay fails if we send more parameters than expected in the shipping address.
		// This ensures that we only send the name and address fields, as in get_shipping_data_from_order.
		$shipping_data = [
			'name'    => $billing_data['name'] ?? '',
			'address' => $billing_data['address'] ?? [],
		];
		if ( $check_if_usable( $shipping_data['address'] ) ) {
			$request->set_shipping( $shipping_data );
			return;
		}

		throw new Invalid_Address_Exception( __( 'A valid shipping address is required for Afterpay payments.', 'woocommerce-payments' ) );
	}


	/**
	 * Modifies the create intent parameters when processing a payment.
	 *
	 * If the selected Stripe payment type is AFTERPAY, it updates the shipping data in the request.
	 *
	 * @param Create_And_Confirm_Intention $request               The request object for creating and confirming intention.
	 * @param Payment_Information          $payment_information   The payment information object.
	 * @param WC_Order                     $order                 The order object.
	 * @throws Invalid_Address_Exception
	 *
	 * @return void
	 */
	protected function modify_create_intent_parameters_when_processing_payment( Create_And_Confirm_Intention $request, Payment_Information $payment_information, WC_Order $order ): void {
		if ( Payment_Method::AFTERPAY === $this->get_selected_stripe_payment_type_id() ) {
			$this->handle_afterpay_shipping_requirement( $order, $request );
		}
	}
}

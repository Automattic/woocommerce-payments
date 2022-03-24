<?php
/**
 * Class WC_Payment_Gateway_WCPay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Exceptions\{ Add_Payment_Method_Exception, Amount_Too_Small_Exception, Process_Payment_Exception, Intent_Authentication_Exception, API_Exception };
use WCPay\Logger;
use WCPay\Payment_Information;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Payment_Initiated_By;
use WCPay\Constants\Payment_Capture_Type;
use WCPay\Tracker;
use WCPay\Payment_Methods\UPE_Payment_Gateway;

/**
 * Gateway class for WooCommerce Payments
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

	const ACCOUNT_SETTINGS_MAPPING = [
		'account_statement_descriptor'     => 'statement_descriptor',
		'account_business_name'            => 'business_name',
		'account_business_url'             => 'business_url',
		'account_business_support_address' => 'business_support_address',
		'account_business_support_email'   => 'business_support_email',
		'account_business_support_phone'   => 'business_support_phone',
		'account_branding_logo'            => 'branding_logo',
		'account_branding_icon'            => 'branding_icon',
		'account_branding_primary_color'   => 'branding_primary_color',
		'account_branding_secondary_color' => 'branding_secondary_color',
	];

	/**
	 * Stripe intents that are treated as successfully created.
	 *
	 * @type array
	 */
	const SUCCESSFUL_INTENT_STATUS = [ 'succeeded', 'requires_capture', 'processing' ];

	/**
	 * Set of parameters to build the URL to the gateway's settings page.
	 *
	 * @var string[]
	 */
	private static $settings_url_params = [
		'page'    => 'wc-settings',
		'tab'     => 'checkout',
		'section' => self::GATEWAY_ID,
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
	 * WC_Payment_Gateway_WCPay constructor.
	 *
	 * @param WC_Payments_API_Client               $payments_api_client             - WooCommerce Payments API client.
	 * @param WC_Payments_Account                  $account                         - Account class instance.
	 * @param WC_Payments_Customer_Service         $customer_service                - Customer class instance.
	 * @param WC_Payments_Token_Service            $token_service                   - Token class instance.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service        - Action Scheduler service instance.
	 * @param Session_Rate_Limiter                 $failed_transaction_rate_limiter - Rate Limiter for failed transactions.
	 * @param WC_Payments_Order_Service            $order_service                   - Order class instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Account $account,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Token_Service $token_service,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		Session_Rate_Limiter $failed_transaction_rate_limiter = null,
		WC_Payments_Order_Service $order_service
	) {
		$this->payments_api_client             = $payments_api_client;
		$this->account                         = $account;
		$this->customer_service                = $customer_service;
		$this->token_service                   = $token_service;
		$this->action_scheduler_service        = $action_scheduler_service;
		$this->failed_transaction_rate_limiter = $failed_transaction_rate_limiter;
		$this->order_service                   = $order_service;

		$this->id                 = static::GATEWAY_ID;
		$this->icon               = ''; // TODO: icon.
		$this->has_fields         = true;
		$this->method_title       = __( 'WooCommerce Payments', 'woocommerce-payments' );
		$this->method_description = __( 'Accept payments via credit card.', 'woocommerce-payments' );
		$this->title              = __( 'Credit card / debit card', 'woocommerce-payments' );
		$this->description        = __( 'Enter your card details', 'woocommerce-payments' );
		$this->supports           = [
			'products',
			'refunds',
		];

		// Define setting fields.
		$this->form_fields = [
			'enabled'                          => [
				'title'       => __( 'Enable/disable', 'woocommerce-payments' ),
				'label'       => __( 'Enable WooCommerce Payments', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'account_statement_descriptor'     => [
				'type'        => 'account_statement_descriptor',
				'title'       => __( 'Customer bank statement', 'woocommerce-payments' ),
				'description' => WC_Payments_Utils::esc_interpolated_html(
					__( 'Edit the way your store name appears on your customers’ bank statements (read more about requirements <a>here</a>).', 'woocommerce-payments' ),
					[ 'a' => '<a href="https://woocommerce.com/document/payments/bank-statement-descriptor/" target="_blank" rel="noopener noreferrer">' ]
				),
			],
			'manual_capture'                   => [
				'title'       => __( 'Manual capture', 'woocommerce-payments' ),
				'label'       => __( 'Issue an authorization on checkout, and capture later.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Charge must be captured within 7 days of authorization, otherwise the authorization and order will be canceled.', 'woocommerce-payments' ),
				'default'     => 'no',
			],
			'saved_cards'                      => [
				'title'       => __( 'Saved cards', 'woocommerce-payments' ),
				'label'       => __( 'Enable payment via saved cards', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on our platform, not on your store.', 'woocommerce-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'test_mode'                        => [
				'title'       => __( 'Test mode', 'woocommerce-payments' ),
				'label'       => __( 'Enable test mode', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Simulate transactions using test card numbers.', 'woocommerce-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'enable_logging'                   => [
				'title'       => __( 'Debug log', 'woocommerce-payments' ),
				'label'       => __( 'When enabled debug notes will be added to the log.', 'woocommerce-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'payment_request_details'          => [
				'title'       => __( 'Payment request buttons', 'woocommerce-payments' ),
				'type'        => 'title',
				'description' => '',
			],
			'payment_request'                  => [
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
			'payment_request_button_type'      => [
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
			'payment_request_button_theme'     => [
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
			'payment_request_button_height'    => [
				'title'       => __( 'Button height', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Enter the height you would like the button to be in pixels. Width will always be 100%.', 'woocommerce-payments' ),
				'default'     => '44',
				'desc_tip'    => true,
			],
			'payment_request_button_label'     => [
				'title'       => __( 'Custom button label', 'woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Enter the custom text you would like the button to have.', 'woocommerce-payments' ),
				'default'     => __( 'Buy now', 'woocommerce-payments' ),
				'desc_tip'    => true,
			],
			'payment_request_button_locations' => [
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
			'upe_enabled_payment_method_ids'   => [
				'title'   => __( 'Payments accepted on checkout', 'woocommerce-payments' ),
				'type'    => 'multiselect',
				'default' => [ 'card' ],
				'options' => [],
			],
			'payment_request_button_size'      => [
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
		];

		// Capabilities have different keys than the payment method ID's,
		// so instead of appending '_payments' to the end of the ID, it'll be better
		// to have a map for it instead, just in case the pattern changes.
		$this->payment_method_capability_key_map = [
			'sofort'        => 'sofort_payments',
			'giropay'       => 'giropay_payments',
			'bancontact'    => 'bancontact_payments',
			'eps'           => 'eps_payments',
			'ideal'         => 'ideal_payments',
			'p24'           => 'p24_payments',
			'card'          => 'card_payments',
			'sepa_debit'    => 'sepa_debit_payments',
			'au_becs_debit' => 'au_becs_debit_payments',
		];

		// Load the settings.
		$this->init_settings();

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// If the setting to enable saved cards is enabled, then we should support tokenization and adding payment methods.
		if ( $this->is_saved_cards_enabled() ) {
			array_push( $this->supports, 'tokenization', 'add_payment_method' );
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'admin_notices', [ $this, 'display_errors' ], 9999 );
		add_action( 'woocommerce_woocommerce_payments_admin_notices', [ $this, 'display_test_mode_notice' ] );
		add_action( 'admin_notices', [ $this, 'display_not_supported_currency_notice' ], 9999 );
		add_action( 'woocommerce_order_actions', [ $this, 'add_order_actions' ] );
		add_action( 'woocommerce_order_action_capture_charge', [ $this, 'capture_charge' ] );
		add_action( 'woocommerce_order_action_cancel_authorization', [ $this, 'cancel_authorization' ] );

		add_action( 'wp_ajax_update_order_status', [ $this, 'update_order_status' ] );
		add_action( 'wp_ajax_nopriv_update_order_status', [ $this, 'update_order_status' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_action( 'wp_ajax_create_setup_intent', [ $this, 'create_setup_intent_ajax' ] );
		add_action( 'wp_ajax_nopriv_create_setup_intent', [ $this, 'create_setup_intent_ajax' ] );

		add_action( 'woocommerce_update_order', [ $this, 'schedule_order_tracking' ], 10, 2 );

		// Update the current request logged_in cookie after a guest user is created to avoid nonce inconsistencies.
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );
	}

	/**
	 * Add a new logo column on the right of "method" in the payment methods table.
	 *
	 * @param array $columns the columns in the "all payment methods" page.
	 * @return array
	 */
	public function add_all_payment_methods_logos_column( $columns ) {
		$logos  = [ 'logos' => '' ]; // Setting an ID for the column, but not a label.
		$offset = array_search( 'name', array_keys( $columns ), true ) + 1;

		return array_merge( array_slice( $columns, 0, $offset ), $logos, array_slice( $columns, $offset ) );
	}

	/**
	 * Add a list of payment method logos to WooCommerce Payment in the logo column.
	 *
	 * @param WC_Payment_Gateway $gateway the current gateway iterated over to be displayed in the "all payment methods" page.
	 */
	public function add_all_payment_methods_icon_logos( $gateway ) {
		if ( 'woocommerce_payments' !== $gateway->id ) {
			echo '<td class="logo"></td>';

			return;
		}

		$icons = [
			'visa',
			'mastercard',
			'amex',
			'apple-pay',
			'google-pay',
		];

		echo '<td class="logo">';
		?>
		<div>
			<?php foreach ( $icons as $icon ) : ?>
				<span class="payment-method__icon payment-method__brand payment-method__brand--<?php echo esc_attr( $icon ); ?>"/></span>
			<?php endforeach; ?>
		</div>
		<?php
		echo '</td>';
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
		return $this->account->is_stripe_connected( false );
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
	 * Whether the current page is the WooCommerce Payments settings page.
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
		return admin_url( add_query_arg( self::$settings_url_params, 'admin.php' ) );
	}

	/**
	 * Check the defined constant to determine the current plugin mode.
	 *
	 * @return bool
	 */
	public function is_in_dev_mode() {
		$is_extension_dev_mode        = defined( 'WCPAY_DEV_MODE' ) && WCPAY_DEV_MODE;
		$is_wordpress_dev_environment = function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), [ 'development', 'staging' ], true );
		return apply_filters( 'wcpay_dev_mode', $is_extension_dev_mode || $is_wordpress_dev_environment );
	}

	/**
	 * Returns whether test_mode or dev_mode is active for the gateway
	 *
	 * @return boolean Test mode enabled if true, disabled if false
	 */
	public function is_in_test_mode() {
		return $this->is_in_dev_mode() || 'yes' === $this->get_option( 'test_mode' );
	}


	/**
	 * Returns whether a store that is not in test mode needs to set https
	 * in the checkout
	 *
	 * @return boolean True if needs to set up forced ssl in checkout or https
	 */
	public function needs_https_setup() {
		return ! $this->is_in_test_mode() && ! wc_checkout_is_https();
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
	 * @param false $empty_value - Default return value.
	 * @return bool
	 */
	public function is_card_present_eligible( $empty_value = false ) {
		try {
			return $this->account->is_card_present_eligible();
		} catch ( Exception $e ) {
			Logger::error( 'Failed to get account card present eligible .' . $e );
			return $empty_value;
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
	 * Add notice explaining test mode when it's enabled.
	 */
	public function display_test_mode_notice() {
		if ( $this->is_in_test_mode() ) {
			?>
			<div id="wcpay-test-mode-notice" class="notice notice-warning">
				<p>
					<b><?php esc_html_e( 'Test mode active: ', 'woocommerce-payments' ); ?></b>
					<?php esc_html_e( "All transactions are simulated. Customers can't make real purchases through WooCommerce Payments.", 'woocommerce-payments' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Add notice explaining that the selected currency is not available.
	 */
	public function display_not_supported_currency_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! $this->is_available_for_current_currency() ) {
			?>
			<div id="wcpay-unsupported-currency-notice" class="notice notice-warning">
				<p>
					<b>
						<?php esc_html_e( 'Unsupported currency:', 'woocommerce-payments' ); ?>
						<?php esc_html( ' ' . get_woocommerce_currency() ); ?>
					</b>
					<?php esc_html_e( 'The selected currency is not available for the country set in your WooCommerce Payments account.', 'woocommerce-payments' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Admin Panel Options.
	 */
	public function admin_options() {
		// Add notices to the WooCommerce Payments settings page.
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
	 * Generates the configuration values, needed for payment fields.
	 *
	 * Isolated as a separate method in order to be available both
	 * during the classic checkout, as well as the checkout block.
	 *
	 * @return array
	 */
	public function get_payment_fields_js_config() {
		return [
			'publishableKey'                 => $this->account->get_publishable_key( $this->is_in_test_mode() ),
			'accountId'                      => $this->account->get_stripe_account_id(),
			'ajaxUrl'                        => admin_url( 'admin-ajax.php' ),
			'wcAjaxUrl'                      => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'createSetupIntentNonce'         => wp_create_nonce( 'wcpay_create_setup_intent_nonce' ),
			'createPaymentIntentNonce'       => wp_create_nonce( 'wcpay_create_payment_intent_nonce' ),
			'updatePaymentIntentNonce'       => wp_create_nonce( 'wcpay_update_payment_intent_nonce' ),
			'initPlatformCheckoutNonce'      => wp_create_nonce( 'wcpay_init_platform_checkout_nonce' ),
			'genericErrorMessage'            => __( 'There was a problem processing the payment. Please check your email inbox and refresh the page to try again.', 'woocommerce-payments' ),
			'fraudServices'                  => $this->account->get_fraud_services_config(),
			'features'                       => $this->supports,
			'forceNetworkSavedCards'         => WC_Payments::is_network_saved_cards_enabled() || $this->should_use_stripe_platform_on_checkout_page(),
			'locale'                         => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
			'isUPEEnabled'                   => WC_Payments_Features::is_upe_enabled(),
			'isSavedCardsEnabled'            => $this->is_saved_cards_enabled(),
			'isPlatformCheckoutEnabled'      => WC_Payments_Features::is_platform_checkout_enabled() && 'yes' === $this->get_option( 'platform_checkout', 'no' ),
			'platformCheckoutHost'           => defined( 'PLATFORM_CHECKOUT_FRONTEND_HOST' ) ? PLATFORM_CHECKOUT_FRONTEND_HOST : 'http://localhost:8090',
			'platformTrackerNonce'           => wp_create_nonce( 'platform_tracks_nonce' ),
			'accountIdForIntentConfirmation' => apply_filters( 'wc_payments_account_id_for_intent_confirmation', '' ),
		];
	}

	/**
	 * Registers all scripts, necessary for the gateway.
	 */
	public function register_scripts() {
		// Register Stripe's JavaScript using the same ID as the Stripe Gateway plugin. This prevents this JS being
		// loaded twice in the event a site has both plugins enabled. We still run the risk of different plugins
		// loading different versions however. If Stripe release a v4 of their JavaScript, we could consider
		// changing the ID to stripe_v4. This would allow older plugins to keep using v3 while we used any new
		// feature in v4. Stripe have allowed loading of 2 different versions of stripe.js in the past (
		// https://stripe.com/docs/stripe-js/elements/migrating).
		wp_register_script(
			'stripe',
			'https://js.stripe.com/v3/',
			[],
			'3.0',
			true
		);

		$script_dependencies = [ 'stripe', 'wc-checkout' ];

		if ( $this->supports( 'tokenization' ) ) {
			$script_dependencies[] = 'woocommerce-tokenization-form';
		}

		wp_register_script(
			'WCPAY_CHECKOUT',
			plugins_url( 'dist/checkout.js', WCPAY_PLUGIN_FILE ),
			$script_dependencies,
			WC_Payments::get_file_version( 'dist/checkout.js' ),
			true
		);

		wp_set_script_translations( 'WCPAY_CHECKOUT', 'woocommerce-payments' );
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
				<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" type="checkbox" value="true" style="width:auto;" <?php echo $force_checked ? 'checked' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?> />
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
		// TODO: Add support for blocks checkout.
		if (
			WC_Payments_Features::is_platform_checkout_enabled() &&
			'yes' === $this->get_option( 'platform_checkout', 'no' ) &&
			! WC_Payments_Features::is_upe_enabled() &&
			is_checkout() &&
			! has_block( 'woocommerce/checkout' ) &&
			! is_wc_endpoint_url( 'order-pay' ) &&
			! WC()->cart->is_empty()
		) {
			$cart_total = WC_Payments_Utils::prepare_amount( WC()->cart->get_total( '' ), get_woocommerce_currency() );
			// We currently can't support setup intents, so free trial subscriptions
			// or pre-orders with charge upon release are not supported.
			return $cart_total > 0;
		}

		return false;
	}

	/**
	 * Prepares customer data to be used on 'Pay for Order' or 'Add Payment Method' pages.
	 * Customer data is retrieved from order when on Pay for Order.
	 * Customer data is retrieved from customer when on 'Add Payment Method'.
	 *
	 * @return array|null An array with customer data or nothing.
	 */
	public function get_prepared_customer_data() {
		if ( ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		global $wp;
		$user_email = '';
		$firstname  = '';
		$lastname   = '';

		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_id = absint( $wp->query_vars['order-pay'] );
			$order    = wc_get_order( $order_id );

			if ( is_a( $order, 'WC_Order' ) ) {
				$firstname  = $order->get_billing_first_name();
				$lastname   = $order->get_billing_last_name();
				$name       = $firstname . ' ' . $lastname;
				$user_email = $order->get_billing_email();
			}
		}

		if ( is_add_payment_method_page() ) {
			$user = wp_get_current_user();

			if ( $user->ID ) {
				$firstname  = $user->user_firstname;
				$lastname   = $user->user_lastname;
				$user_email = get_user_meta( $user->ID, 'billing_email', true );
				$user_email = $user_email ? $user_email : $user->user_email;
			}
		}
		$prepared_customer_data = [
			'name'  => $firstname . ' ' . $lastname,
			'email' => $user_email,
		];

		return $prepared_customer_data;
	}
	/**
	 * Renders the credit card input fields needed to get the user's payment information on the checkout page.
	 *
	 * We also add the JavaScript which drives the UI.
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->supports( 'tokenization' ) && ( is_checkout() || is_add_payment_method_page() );

			add_action( 'wp_footer', [ $this, 'enqueue_payment_scripts' ] );

			$prepared_customer_data = $this->get_prepared_customer_data();
			if ( ! empty( $prepared_customer_data ) ) {
				wp_localize_script( 'WCPAY_CHECKOUT', 'wcpayCustomerData', $prepared_customer_data );
			}

			wp_enqueue_style(
				'WCPAY_CHECKOUT',
				plugins_url( 'dist/checkout.css', WCPAY_PLUGIN_FILE ),
				[],
				WC_Payments::get_file_version( 'dist/checkout.css' )
			);

			// Output the form HTML.
			?>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>

			<?php if ( $this->is_in_test_mode() ) : ?>
				<p class="testmode-info">
				<?php
					echo WC_Payments_Utils::esc_interpolated_html(
						/* translators: link to Stripe testing page */
						__( '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC, or any test card numbers listed <a>here</a>.', 'woocommerce-payments' ),
						[
							'strong' => '<strong>',
							'a'      => '<a href="https://woocommerce.com/document/payments/testing/#test-cards" target="_blank">',
						]
					);
				?>
				</p>
			<?php endif; ?>

			<?php

			if ( $display_tokenization ) {
				$this->tokenization_script();
				echo $this->saved_payment_methods(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

			<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form">
				<div id="wcpay-card-element"></div>
				<div id="wcpay-errors" role="alert"></div>
				<input id="wcpay-payment-method" type="hidden" name="wcpay-payment-method" />
				<input type="hidden" name="wcpay-is-platform-payment-method" value="<?php echo esc_attr( $this->should_use_stripe_platform_on_checkout_page() ); ?>" />
			<?php
			if ( $this->is_saved_cards_enabled() ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_payments_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
				$this->save_payment_method_checkbox( $force_save_payment );
			}
			?>

			</fieldset>
			<?php

			do_action( 'wcpay_payment_fields_wcpay', $this->id );

		} catch ( Exception $e ) {
			// Output the error message.
			?>
			<div>
				<?php
				echo esc_html__( 'An error was encountered when preparing the payment form. Please try again later.', 'woocommerce-payments' );
				?>
			</div>
			<?php
		}
	}

	/**
	 * Enqueues and localizes WCPay's checkout scripts.
	 */
	public function enqueue_payment_scripts() {
		wp_localize_script( 'WCPAY_CHECKOUT', 'wcpay_config', $this->get_payment_fields_js_config() );
		wp_enqueue_script( 'WCPAY_CHECKOUT' );
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

		try {
			if ( $this->failed_transaction_rate_limiter->is_limited() ) {
				throw new Process_Payment_Exception(
					__( 'Your payment was not processed.', 'woocommerce-payments' ),
					'rate_limiter_enabled'
				);
			}

			UPE_Payment_Gateway::remove_upe_payment_intent_from_session();

			$payment_information = $this->prepare_payment_information( $order );
			return $this->process_payment_for_order( WC()->cart, $payment_information );
		} catch ( Exception $e ) {
			/**
			 * TODO: Determine how to do this update with Order_Service.
			 * It seems that the status only needs to change in certain instances, and within those instances the intent
			 * information is not added to the order, as shown by tests.
			 */
			if ( empty( $payment_information ) || ! $payment_information->is_changing_payment_method_for_subscription() ) {
				$order->update_status( 'failed' );
			}

			if ( $e instanceof API_Exception && $this->should_bump_rate_limiter( $e->get_error_code() ) ) {
				$this->failed_transaction_rate_limiter->bump();
			}

			if ( ! empty( $payment_information ) ) {
				/* translators: %1: the failed payment amount, %2: error message  */
				$error_message = __(
					'A payment of %1$s <strong>failed</strong> to complete with the following message: <code>%2$s</code>.',
					'woocommerce-payments'
				);

				$error_details = esc_html( rtrim( $e->getMessage(), '.' ) );

				if ( $e instanceof API_Exception && 'card_error' === $e->get_error_type() && 'incorrect_zip' === $e->get_error_code() ) {
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

			UPE_Payment_Gateway::remove_upe_payment_intent_from_session();

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
		$payment_information = Payment_Information::from_payment_request( $_POST, $order, Payment_Type::SINGLE(), Payment_Initiated_By::CUSTOMER(), $this->get_capture_type() );
		$payment_information = $this->maybe_prepare_subscription_payment_information( $payment_information, $order->get_id() );

		if ( ! empty( $_POST[ 'wc-' . static::GATEWAY_ID . '-new-payment-method' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// During normal orders the payment method is saved when the customer enters a new one and chooses to save it.
			$payment_information->must_save_payment_method_to_store();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! empty( $_POST['save_user_in_platform_checkout'] ) && filter_var( $_POST['save_user_in_platform_checkout'], FILTER_VALIDATE_BOOLEAN ) ) {
			$payment_information->must_save_payment_method_to_platform();
		}

		return $payment_information;
	}

	/**
	 * Manages customer details held on WCPay server for WordPress user associated with an order.
	 *
	 * @param WC_Order $order WC Order object.
	 *
	 * @return array First element is the new or updated WordPress user, the second element is the WCPay customer ID.
	 */
	protected function manage_customer_details_for_order( $order ) {
		$user = $order->get_user();
		if ( false === $user ) {
			$user = wp_get_current_user();
		}

		// Determine the customer making the payment, create one if we don't have one already.
		$customer_id   = $this->customer_service->get_customer_id_by_user_id( $user->ID );
		$customer_data = WC_Payments_Customer_Service::map_customer_data( $order, new WC_Customer( $user->ID ) );

		if ( null === $customer_id ) {
			// Create a new customer.
			$customer_id = $this->customer_service->create_customer_for_user( $user, $customer_data );
		} else {
			// Update the existing customer with the current details. In the event the old customer can't be
			// found a new one is created, so we update the customer ID here as well.
			$customer_id = $this->customer_service->update_customer_for_user( $customer_id, $user, $customer_data );
		}

		return [ $user, $customer_id ];
	}

	/**
	 * Process the payment for a given order.
	 *
	 * @param WC_Cart|null              $cart Cart.
	 * @param WCPay\Payment_Information $payment_information Payment info.
	 * @param array                     $additional_api_parameters Any additional fields required for payment method to pass to API.
	 *
	 * @return array|null                   An array with result of payment and redirect URL, or nothing.
	 * @throws API_Exception                Error processing the payment.
	 * @throws Add_Payment_Method_Exception When $0 order processing failed.
	 */
	public function process_payment_for_order( $cart, $payment_information, $additional_api_parameters = [] ) {
		$order                                       = $payment_information->get_order();
		$save_payment_method_to_store                = $payment_information->should_save_payment_method_to_store();
		$is_changing_payment_method_for_subscription = $payment_information->is_changing_payment_method_for_subscription();

		$order_id = $order->get_id();
		$amount   = $order->get_total();
		$metadata = $this->get_metadata_from_order( $order, $payment_information->get_payment_type() );

		list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order );

		// Update saved payment method information with checkout values, as some saved methods might not have billing details.
		if ( $payment_information->is_using_saved_payment_method() ) {
			try {
				$this->customer_service->update_payment_method_with_billing_details_from_order( $payment_information->get_payment_method(), $order );
			} catch ( Exception $e ) {
				// If updating the payment method fails, log the error message but catch the error to avoid crashing the checkout flow.
				Logger::log( 'Error when updating saved payment method: ' . $e->getMessage() );
			}
		}

		$intent_failed  = false;
		$payment_needed = $amount > 0;

		// Make sure that we attach the payment method and the customer ID to the order meta data.
		$payment_method = $payment_information->get_payment_method();
		$order->update_meta_data( '_payment_method_id', $payment_method );
		$order->update_meta_data( '_stripe_customer_id', $customer_id );

		// In case amount is 0 and we're not saving the payment method, we won't be using intents and can confirm the order payment.
		if ( ! $payment_needed && ! $save_payment_method_to_store ) {
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

			$payment_methods = WC_Payments::get_gateway()->get_payment_method_ids_enabled_at_checkout( null, true );

			// Make sure the payment method being charged was created in the platform.
			if (
				! $payment_information->is_using_saved_payment_method() &&
				$this->should_use_stripe_platform_on_checkout_page() &&
				// This flag is useful to differentiate between PRB, blocks and shortcode checkout, since this endpoint is being used for all of them.
				! empty( $_POST['wcpay-is-platform-payment-method'] ) && // phpcs:ignore WordPress.Security.NonceVerification
				filter_var( $_POST['wcpay-is-platform-payment-method'], FILTER_VALIDATE_BOOLEAN ) // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			) {
				// This payment method was created under the platform account.
				$additional_api_parameters['is_platform_payment_method'] = 'true';
			}

			// The sanitize_user call here is deliberate: it seems the most appropriate sanitization function
			// for a string that will only contain latin alphanumeric characters and underscores.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$platform_checkout_intent_id = sanitize_user( wp_unslash( $_POST['platform-checkout-intent'] ?? '' ), true );

			if ( ! empty( $platform_checkout_intent_id ) ) {
				// If the intent is included in the request use that intent.
				$intent = $this->payments_api_client->get_intent( $platform_checkout_intent_id );
			} else {
				// Create intention, try to confirm it & capture the charge (if 3DS is not required).
				$intent = $this->payments_api_client->create_and_confirm_intention(
					$converted_amount,
					$currency,
					$payment_information->get_payment_method(),
					$customer_id,
					$payment_information->is_using_manual_capture(),
					$save_payment_method_to_store,
					$payment_information->should_save_payment_method_to_platform(),
					$metadata,
					$this->get_level3_data_from_order( $order ),
					$payment_information->is_merchant_initiated(),
					$additional_api_parameters,
					$payment_methods
				);
			}

			$intent_id     = $intent->get_id();
			$status        = $intent->get_status();
			$charge_id     = $intent->get_charge_id();
			$client_secret = $intent->get_client_secret();
			$currency      = $intent->get_currency();
			$next_action   = $intent->get_next_action();

			if ( 'requires_action' === $status && $payment_information->is_merchant_initiated() ) {
				// Allow 3rd-party to trigger some action if needed.
				do_action( 'woocommerce_woocommerce_payments_payment_requires_action', $order, $intent_id, $payment_method, $customer_id, $charge_id, $currency );
				$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id );
			}
		} else {
			// For $0 orders, we need to save the payment method using a setup intent.
			$intent = $this->payments_api_client->create_and_confirm_setup_intent(
				$payment_information->get_payment_method(),
				$customer_id
			);

			$intent_id     = $intent['id'];
			$status        = $intent['status'];
			$charge_id     = '';
			$client_secret = $intent['client_secret'];
			$currency      = $order->get_currency();
			$next_action   = $intent['next_action'];
		}

		if ( ! empty( $intent ) ) {
			if ( ! in_array( $status, self::SUCCESSFUL_INTENT_STATUS, true ) ) {
				$intent_failed = true;
			}

			if ( $save_payment_method_to_store && ! $intent_failed ) {
				try {
					// Setup intents are currently not deserialized as payment intents are, so check if it's an array first.
					// For payment intents, we may provide a platform payment method from `$payment_information`, but we need
					// to return a connected payment method. So we should always retrieve the payment method from the intent.
					$payment_method_id = is_array( $intent ) ? $payment_information->get_payment_method() : $intent->get_payment_method_id();

					$token = $this->token_service->add_payment_method_to_user( $payment_method_id, $user );
					$payment_information->set_token( $token );
				} catch ( Exception $e ) {
					// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
					Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
				}
			}

			if ( $payment_information->is_using_saved_payment_method() ) {
				$token = $payment_information->get_payment_token();
				$this->add_token_to_order( $order, $token );
			}

			if ( 'requires_action' === $status ) {
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
							$client_secret,
							wp_create_nonce( 'wcpay_update_order_status_nonce' )
						),
						// Include the payment method ID so the Blocks integration can save cards.
						'payment_method' => $payment_information->get_payment_method(),
					];
				}
			}
		}

		$this->attach_intent_info_to_order( $order, $intent_id, $status, $payment_method, $customer_id, $charge_id, $currency );
		$this->attach_exchange_info_to_order( $order, $charge_id );
		$this->update_order_status_from_intent( $order, $intent_id, $status, $charge_id );

		if ( isset( $response ) ) {
			return $response;
		}

		wc_reduce_stock_levels( $order_id );
		if ( isset( $cart ) ) {
			$cart->empty_cart();
		}

		if ( $payment_needed ) {
			$payment_method_details = $intent->get_payment_method_details();
			$payment_method_type    = $payment_method_details ? $payment_method_details['type'] : null;
		} else {
			$payment_method_details = false;
			$payment_method_options = isset( $intent['payment_method_options'] ) ? array_keys( $intent['payment_method_options'] ) : null;
			$payment_method_type    = $payment_method_options ? $payment_method_options[0] : null;
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
		$name     = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
		$email    = sanitize_email( $order->get_billing_email() );
		$metadata = [
			'customer_name'  => $name,
			'customer_email' => $email,
			'site_url'       => esc_url( get_site_url() ),
			'order_id'       => $order->get_id(),
			'order_key'      => $order->get_order_key(),
			'payment_type'   => $payment_type,
		];

		// If the order belongs to a WCPay Subscription, set the payment context to 'wcpay_subscription' (this helps with associating which fees belong to orders).
		if ( 'recurring' === (string) $payment_type && ! $this->is_subscriptions_plugin_active() ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'any' ] );

			foreach ( $subscriptions as $subscription ) {
				if ( WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) ) {
					$metadata['payment_context'] = 'wcpay_subscription';
					break;
				}
			}
		}

		return apply_filters( 'wcpay_metadata_from_order', $metadata, $order, $payment_type );
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
			$charge        = $this->payments_api_client->get_charge( $charge_id );
			$exchange_rate = $charge['balance_transaction']['exchange_rate'] ?? null;
			if ( isset( $exchange_rate ) ) {
				$exchange_rate = WC_Payments_Utils::interpret_string_exchange_rate( $exchange_rate, $currency_order, $currency_account );
				$order->update_meta_data( '_wcpay_multi_currency_stripe_exchange_rate', $exchange_rate );
				$order->save_meta_data();
			}
		}
	}

	/**
	 * Given the payment intent data, adds it to the given order as metadata and parses any notes that need to be added
	 *
	 * @param WC_Order $order The order to update.
	 * @param string   $intent_id The intent ID.
	 * @param string   $intent_status Intent status.
	 * @param string   $payment_method Payment method ID.
	 * @param string   $customer_id Customer ID.
	 * @param string   $charge_id Charge ID.
	 * @param string   $currency Currency code.
	 */
	public function attach_intent_info_to_order( $order, $intent_id, $intent_status, $payment_method, $customer_id, $charge_id, $currency ) {
		// first, let's save all the metadata that needed for refunds, required for status change etc.
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', $intent_status );
		$order->update_meta_data( '_payment_method_id', $payment_method );
		$order->update_meta_data( '_stripe_customer_id', $customer_id );
		WC_Payments_Utils::set_order_intent_currency( $order, $currency );
		$order->save();
	}

	/**
	 * Parse the payment intent data and add any necessary notes to the order and update the order status accordingly.
	 *
	 * @param WC_Order $order The order to update.
	 * @param string   $intent_id The intent ID.
	 * @param string   $intent_status Intent status.
	 * @param string   $charge_id Charge ID.
	 */
	public function update_order_status_from_intent( $order, $intent_id, $intent_status, $charge_id ) {
		switch ( $intent_status ) {
			case 'succeeded':
				$this->order_service->mark_payment_completed( $order, $intent_id, $intent_status, $charge_id );
				break;
			case 'processing':
			case 'requires_capture':
				$this->order_service->mark_payment_authorized( $order, $intent_id, $intent_status, $charge_id );
				break;
			case 'requires_action':
			case 'requires_payment_method':
				$this->order_service->mark_payment_started( $order, $intent_id, $intent_status, $charge_id );
				break;
			default:
				Logger::error( 'Uncaught payment intent status of ' . $intent_status . ' passed for order id: ' . $order->get_id() );
				break;
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
		return $order && $order->get_meta( '_charge_id', true );
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
		$order    = wc_get_order( $order_id );
		$currency = WC_Payments_Utils::get_order_intent_currency( $order );

		if ( ! $order ) {
			return false;
		}

		// If this order is not captured yet, don't try and refund it. Instead, return an appropriate error message.
		if ( 'requires_capture' === $order->get_meta( '_intention_status', true ) ) {
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

		$charge_id = $order->get_meta( '_charge_id', true );

		try {
			if ( is_null( $amount ) ) {
				// If amount is null, the default is the entire charge.
				$refund = $this->payments_api_client->refund_charge( $charge_id );
			} else {
				$refund = $this->payments_api_client->refund_charge( $charge_id, WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ) );
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
			$order->update_meta_data( '_wcpay_refund_status', 'failed' );
			$order->save();

			Tracker::track_admin( 'wcpay_edit_order_refund_failure', [ 'reason' => $note ] );
			return new WP_Error( 'wcpay_edit_order_refund_failure', $e->getMessage() );
		}

		if ( empty( $reason ) ) {
			$note = sprintf(
				/* translators: %1: the successfully charged amount */
				__( 'A refund of %1$s was successfully processed using WooCommerce Payments.', 'woocommerce-payments' ),
				WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $amount, [ 'currency' => $currency ] ), $order )
			);
		} else {
			$note = sprintf(
				/* translators: %1: the successfully charged amount, %2: reason */
				__( 'A refund of %1$s was successfully processed using WooCommerce Payments. Reason: %2$s', 'woocommerce-payments' ),
				WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $amount, [ 'currency' => $currency ] ), $order ),
				$reason
			);
		}

		$order->add_order_note( $note );
		$order->update_meta_data( '_wcpay_refund_status', 'successful' );
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
		return 'failed' === $order->get_meta( '_wcpay_refund_status', true );
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

		$in_dev_mode = $this->is_in_dev_mode();

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
	 * @param  string $key         Option key.
	 * @param  mixed  $empty_value Value when empty.
	 * @return string|array        The value specified for the option or a default value for the option.
	 */
	public function get_option( $key, $empty_value = null ) {
		switch ( $key ) {
			case 'enabled':
				return parent::get_option( static::METHOD_ENABLED_KEY, $empty_value );
			case 'account_statement_descriptor':
				return $this->get_account_statement_descriptor();
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
	 * Updates whether platform checkout is enabled or disabled.
	 *
	 * @param bool $is_platform_checkout_enabled Whether platform checkout should be enabled.
	 */
	public function update_is_platform_checkout_enabled( $is_platform_checkout_enabled ) {
		$current_is_platform_checkout_enabled = 'yes' === $this->get_option( 'platform_checkout', 'no' );
		if ( $is_platform_checkout_enabled !== $current_is_platform_checkout_enabled ) {
			wc_admin_record_tracks_event( $is_platform_checkout_enabled ? 'platform_checkout_enabled' : 'platform_checkout_disabled' );
			$this->update_option( 'platform_checkout', $is_platform_checkout_enabled ? 'yes' : 'no' );
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
	 * @return array Updated fields.
	 */
	public function update_account_settings( array $settings ) : array {
		$account_settings = [];
		foreach ( static::ACCOUNT_SETTINGS_MAPPING as $name => $account_key ) {
			if ( isset( $settings[ $name ] ) ) {
				$account_settings[ $account_key ] = $settings[ $name ];
			}
		}
		$this->update_account( $account_settings );

		return $account_settings;
	}

	/**
	 * Gets connected account statement descriptor.
	 *
	 * @param string $empty_value Empty value to return when not connected or fails to fetch account descriptor.
	 *
	 * @return string Statement descriptor of default value.
	 */
	protected function get_account_statement_descriptor( string $empty_value = '' ): string {
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
	 * Handles connected account update when plugin settings saved.
	 *
	 * Adds error message to display in admin notices in case of failure.
	 *
	 * @param array $account_settings Stripe account settings.
	 * Supported: statement_descriptor, business_name, business_url, business_support_address,
	 * business_support_email, business_support_phone, branding_logo, branding_icon,
	 * branding_primary_color, branding_secondary_color.
	 */
	public function update_account( $account_settings ) {
		if ( empty( $account_settings ) ) {
			return;
		}

		$error_message = $this->account->update_stripe_account( $account_settings );

		if ( is_string( $error_message ) ) {
			$msg = __( 'Failed to update Stripe account. ', 'woocommerce-payments' ) . $error_message;
			$this->add_error( $msg );
		}
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

		if ( 'requires_capture' !== $theorder->get_meta( '_intention_status', true ) ) {
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
	 *
	 * @return array An array containing the status (succeeded/failed), id (intent ID), message (error message if any), and http code
	 */
	public function capture_charge( $order ) {
		$amount                   = $order->get_total();
		$is_authorization_expired = false;
		$intent                   = null;
		$status                   = null;
		$error_message            = null;
		$http_code                = null;
		$currency                 = WC_Payments_Utils::get_order_intent_currency( $order );

		try {
			$intent_id    = $order->get_transaction_id();
			$intent       = $this->payments_api_client->get_intent( $intent_id );
			$payment_type = $this->is_payment_recurring( $order->get_id() ) ? Payment_Type::RECURRING() : Payment_Type::SINGLE();

			$metadata_from_intent = $intent->get_metadata(); // mobile app may have set metadata.
			$metadata_from_order  = $this->get_metadata_from_order( $order, $payment_type );
			$merged_metadata      = array_merge( (array) $metadata_from_order, (array) $metadata_from_intent ); // prioritize metadata from mobile app.

			$this->payments_api_client->update_intention_metadata(
				$intent_id,
				$merged_metadata
			);

			$intent = $this->payments_api_client->capture_intention(
				$intent_id,
				WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ),
				$this->get_level3_data_from_order( $order )
			);

			$status    = $intent->get_status();
			$currency  = $intent->get_currency();
			$http_code = 200;
		} catch ( API_Exception $e ) {
			try {
				$error_message = $e->getMessage();
				$http_code     = $e->get_http_code();

				// Fetch the Intent to check if it's already expired and the site missed the "charge.expired" webhook.
				$intent = $this->payments_api_client->get_intent( $order->get_transaction_id() );
				if ( 'canceled' === $intent->get_status() ) {
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
		$charge_id = ! empty( $intent ) ? $intent->get_charge_id() : $order->get_meta( '_charge_id' );

		$this->attach_exchange_info_to_order( $order, $charge_id );

		if ( 'succeeded' === $status ) {
			$this->order_service->mark_payment_capture_completed( $order, $intent_id, $status, $charge_id );
		} elseif ( $is_authorization_expired ) {
			$this->order_service->mark_payment_capture_expired( $order, $intent_id, 'canceled', $charge_id );
		} else {
			if ( ! empty( $error_message ) ) {
				$error_message = esc_html( $error_message );
			} else {
				$http_code = 502;
			}

			$this->order_service->mark_payment_capture_failed( $order, $intent_id, 'requires_capture', $charge_id, $error_message );
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
		$status        = null;
		$error_message = null;

		try {
			$intent = $this->payments_api_client->cancel_intention( $order->get_transaction_id() );
			$status = $intent->get_status();
		} catch ( API_Exception $e ) {
			try {
				// Fetch the Intent to check if it's already expired and the site missed the "charge.expired" webhook.
				$intent = $this->payments_api_client->get_intent( $order->get_transaction_id() );
				$status = $intent->get_status();
				if ( 'canceled' !== $status ) {
					$error_message = $e->getMessage();
				}
			} catch ( API_Exception $ge ) {
				// Ignore any errors during the intent retrieval, and add the failed cancellation note below with the
				// original error message.
				$status        = null;
				$error_message = $e->getMessage();
			}
		}

		if ( 'canceled' === $status ) {
			$this->order_service->mark_payment_capture_cancelled( $order, $intent->get_id(), $status, $intent->get_charge_id() );
			return;
		} elseif ( ! empty( $error_message ) ) {
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

		$order->update_meta_data( '_intention_status', $status );
		$order->save();
	}

	/**
	 * Create the level 3 data array to send to Stripe when making a purchase.
	 *
	 * @param WC_Order $order The order that is being paid for.
	 * @return array          The level 3 data to send to Stripe.
	 */
	public function get_level3_data_from_order( WC_Order $order ): array {
		$merchant_country = $this->account->get_account_country();
		// We do not need to send level3 data if merchant account country is non-US.
		if ( 'US' !== $merchant_country ) {
			return [];
		}

		// Get the order items. Don't need their keys, only their values.
		// Order item IDs are used as keys in the original order items array.
		$order_items = array_values( $order->get_items( [ 'line_item', 'fee' ] ) );
		$currency    = $order->get_currency();

		$process_item  = static function( $item ) use ( $currency ) {
			// Check to see if it is a WC_Order_Item_Product or a WC_Order_Item_Fee.
			if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
				$subtotal   = $item->get_subtotal();
				$product_id = $item->get_variation_id()
					? $item->get_variation_id()
					: $item->get_product_id();
			} else {
				$subtotal   = $item->get_total();
				$product_id = substr( sanitize_title( $item->get_name() ), 0, 12 );
			}

			$description     = substr( $item->get_name(), 0, 26 );
			$quantity        = ceil( $item->get_quantity() );
			$unit_cost       = WC_Payments_Utils::prepare_amount( $subtotal / $quantity, $currency );
			$tax_amount      = WC_Payments_Utils::prepare_amount( $item->get_total_tax(), $currency );
			$discount_amount = WC_Payments_Utils::prepare_amount( $subtotal - $item->get_total(), $currency );

			return (object) [
				'product_code'        => (string) $product_id, // Up to 12 characters that uniquely identify the product.
				'product_description' => $description, // Up to 26 characters long describing the product.
				'unit_cost'           => $unit_cost, // Cost of the product, in cents, as a non-negative integer.
				'quantity'            => $quantity, // The number of items of this type sold, as a non-negative integer.
				'tax_amount'          => $tax_amount, // The amount of tax this item had added to it, in cents, as a non-negative integer.
				'discount_amount'     => $discount_amount, // The amount an item was discounted—if there was a sale,for example, as a non-negative integer.
			];
		};
		$items_to_send = array_map( $process_item, $order_items );

		if ( count( $items_to_send ) > 200 ) {
			// If more than 200 items are present, bundle the last ones in a single item.
			$items_to_send = array_merge(
				array_slice( $items_to_send, 0, 199 ),
				[ $this->bundle_level3_data_from_items( array_slice( $items_to_send, 200 ) ) ]
			);
		}

		$level3_data = [
			'merchant_reference' => (string) $order->get_id(), // An alphanumeric string of up to  characters in length. This unique value is assigned by the merchant to identify the order. Also known as an “Order ID”.
			'customer_reference' => (string) $order->get_id(),
			'shipping_amount'    => WC_Payments_Utils::prepare_amount( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(), $currency ), // The shipping cost, in cents, as a non-negative integer.
			'line_items'         => $items_to_send,
		];

		// The customer’s U.S. shipping ZIP code.
		$shipping_address_zip = $order->get_shipping_postcode();
		if ( WC_Payments_Utils::is_valid_us_zip_code( $shipping_address_zip ) ) {
			$level3_data['shipping_address_zip'] = $shipping_address_zip;
		}

		// The merchant’s U.S. shipping ZIP code.
		$store_postcode = get_option( 'woocommerce_store_postcode' );
		if ( WC_Payments_Utils::is_valid_us_zip_code( $store_postcode ) ) {
			$level3_data['shipping_from_zip'] = $store_postcode;
		}

		return $level3_data;
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

			$intent_id          = $order->get_meta( '_intent_id', true );
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
				$intent    = $this->payments_api_client->get_intent( $intent_id );
				$status    = $intent->get_status();
				$charge_id = $intent->get_charge_id();

				$this->attach_exchange_info_to_order( $order, $charge_id );
				$this->attach_intent_info_to_order( $order, $intent_id, $status, $intent->get_payment_method_id(), $intent->get_customer_id(), $charge_id, $intent->get_currency() );
			} else {
				// For $0 orders, fetch the Setup Intent instead.
				$intent    = $this->payments_api_client->get_setup_intent( $intent_id );
				$status    = $intent['status'];
				$charge_id = '';
			}

			switch ( $status ) {
				case 'succeeded':
					$this->order_service->mark_payment_completed( $order, $intent_id, $status, $charge_id );
					break;
				case 'processing':
				case 'requires_capture':
					$this->order_service->mark_payment_authorized( $order, $intent_id, $status, $charge_id );
					break;
				case 'requires_payment_method':
					$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id );
					break;
			}

			if ( in_array( $status, self::SUCCESSFUL_INTENT_STATUS, true ) ) {
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
					__( 'A WooCommerce Payments payment method was not provided', 'woocommerce-payments' ),
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

			$setup_intent = $this->payments_api_client->get_setup_intent( $setup_intent_id );

			if ( 'succeeded' !== $setup_intent['status'] ) {
				throw new Add_Payment_Method_Exception(
					__( 'Failed to add the provided payment method. Please try again later', 'woocommerce-payments' ),
					'invalid_response_status'
				);
			}

			$payment_method = $setup_intent['payment_method'];
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
		if ( ! isset( $this->account->get_fraud_services_config()['sift'] ) ) {
			return;
		}

		// Sometimes the woocommerce_update_order hook might be called with just the order ID parameter,
		// so we need to fetch the order here.
		if ( is_null( $order ) ) {
			$order = wc_get_order( $order_id );
		}

		// We only want to track orders created by our payment gateway, and orders with a payment method set.
		if ( $order->get_payment_method() !== self::GATEWAY_ID || empty( $order->get_meta_data( '_payment_method_id' ) ) ) {
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
	 * @param WC_Order $order - Order based on which to create intent.
	 * @param array    $payment_methods - A list of allowed payment methods. Eg. card, card_present.
	 * @param string   $capture_method - Controls when the funds will be captured from the customer's account ("automatic" or "manual").
	 *  It must be "manual" for in-person (terminal) payments.
	 *
	 * @return array|WP_Error On success, an array containing info about the newly created intent. On failure, WP_Error object.
	 *
	 * @throws Exception - When an error occurs in intent creation.
	 */
	public function create_intent( WC_Order $order, array $payment_methods, string $capture_method = 'automatic' ) {
		$currency         = strtolower( $order->get_currency() );
		$converted_amount = WC_Payments_Utils::prepare_amount( $order->get_total(), $currency );
		$intent           = null;

		try {
			$intent = $this->payments_api_client->create_intention(
				$converted_amount,
				$currency,
				$payment_methods,
				$order->get_id(),
				$capture_method
			);

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
	 * @throws Exception - When an error occurs in setup intent creation.
	 */
	public function create_and_confirm_setup_intent() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_information = Payment_Information::from_payment_request( $_POST );

		// Determine the customer adding the payment method, create one if we don't have one already.
		$user        = wp_get_current_user();
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );
		if ( null === $customer_id ) {
			$customer_data = WC_Payments_Customer_Service::map_customer_data( null, new WC_Customer( $user->ID ) );
			$customer_id   = $this->customer_service->create_customer_for_user( $user, $customer_data );
		}

		return $this->payments_api_client->create_and_confirm_setup_intent(
			$payment_information->get_payment_method(),
			$customer_id
		);
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

			$setup_intent = $this->create_and_confirm_setup_intent();

			wp_send_json_success( $setup_intent, 200 );
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => WC_Payments_Utils::get_filtered_error_message( $e ),
					],
				]
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
		$charge_id = $order->get_meta( '_charge_id' );
		return $this->compose_transaction_url( $charge_id );
	}

	/**
	 * Composes url for transaction details page.
	 *
	 * @param  string $charge_id Charge id.
	 * @return string            Transaction details page url.
	 */
	protected function compose_transaction_url( $charge_id ) {
		if ( empty( $charge_id ) ) {
			return '';
		}

		return add_query_arg(
			[
				'page' => 'wc-admin',
				'path' => '/payments/transactions/details',
				'id'   => $charge_id,
			],
			admin_url( 'admin.php' )
		);
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
	 * Returns a bundle of products passed as an argument. Useful when working with Stripe's level 3 data
	 *
	 * @param array $items The Stripe's level 3 array of items.
	 *
	 * @return object A bundle of the products passed.
	 */
	public function bundle_level3_data_from_items( array $items ) {
		// Total cost is the sum of each product cost * quantity.
		$items_count = count( $items );
		$total_cost  = array_sum(
			array_map(
				function( $cost, $qty ) {
					return $cost * $qty;
				},
				array_column( $items, 'unit_cost' ),
				array_column( $items, 'quantity' )
			)
		);

		return (object) [
			'product_code'        => (string) substr( uniqid(), 0, 26 ),
			'product_description' => "{$items_count} more items",
			'unit_cost'           => $total_cost,
			'quantity'            => 1,
			'tax_amount'          => array_sum( array_column( $items, 'tax_amount' ) ),
			'discount_amount'     => array_sum( array_column( $items, 'discount_amount' ) ),
		];
	}
}

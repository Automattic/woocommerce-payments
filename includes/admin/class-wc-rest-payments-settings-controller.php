<?php
/**
 * Class WC_REST_Payments_Settings_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Constants\Payment_Method;
use WCPay\Constants\Country_Code;
use WCPay\Fraud_Prevention\Fraud_Risk_Tools;
use WCPay\Constants\Track_Events;
use WCPay\Fraud_Prevention\Models\Rule;
use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for settings.
 */
class WC_REST_Payments_Settings_Controller extends WC_Payments_REST_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/settings';

	/**
	 * Instance of WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	protected $account;

	/**
	 * WC_REST_Payments_Settings_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client WC_Payments_API_Client instance.
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WC_Payment_Gateway_WCPay instance.
	 * @param WC_Payments_Account      $account  Account class instance.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payment_Gateway_WCPay $wcpay_gateway,
		WC_Payments_Account $account
	) {
		parent::__construct( $api_client );

		$this->wcpay_gateway = $wcpay_gateway;
		$this->account       = $account;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		$wcpay_form_fields = $this->wcpay_gateway->get_form_fields();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'is_wcpay_enabled'                     => [
						'description'       => sprintf(
							/* translators: %s: WooPayments */
							__( 'If %s should be enabled.', 'woocommerce-payments' ),
							'WooPayments'
						),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'enabled_payment_method_ids'           => [
						'description'       => __( 'Payment method IDs that should be enabled. Other methods will be disabled.', 'woocommerce-payments' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => $this->wcpay_gateway->get_upe_available_payment_methods(),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_manual_capture_enabled'            => [
						'description'       => sprintf(
						/* translators: %s: WooPayments */
							__( 'If %s manual capture of charges should be enabled.', 'woocommerce-payments' ),
							'WooPayments'
						),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_saved_cards_enabled'               => [
						'description'       => sprintf(
							/* translators: %s: WooPayments */
							__( 'If %s "Saved cards" should be enabled.', 'woocommerce-payments' ),
							'WooPayments'
						),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_test_mode_enabled'                 => [
						'description'       => sprintf(
							/* translators: %s: WooPayments */
							__( '%s test mode setting.', 'woocommerce-payments' ),
							'WooPayments'
						),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_multi_currency_enabled'            => [
						'description'       => sprintf(
							/* translators: %s: WooPayments */
							__( '%s Multi-Currency feature flag setting.', 'woocommerce-payments' ),
							'WooPayments'
						),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_wcpay_subscriptions_enabled'       => [
						'description'       => sprintf(
							/* translators: %s: WooPayments */
							__( '%s Subscriptions feature flag setting.', 'woocommerce-payments' ),
							'WooPayments'
						),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'account_statement_descriptor'         => [
						'description'       => sprintf(
							/* translators: %s: WooPayments */
							__( '%s bank account descriptor to be displayed in customers\' bank accounts.', 'woocommerce-payments' ),
							'WooPayments'
						),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_statement_descriptor' ],
					],
					'account_business_name'                => [
						'description' => __( 'The customer-facing business name.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_business_url'                 => [
						'description' => __( 'The business’s publicly available website.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_business_support_address'     => [
						'description'       => __( 'A publicly available mailing address for sending support issues to.', 'woocommerce-payments' ),
						'type'              => 'object',
						'validate_callback' => [ $this, 'validate_business_support_address' ],
					],
					'account_business_support_email'       => [
						'description'       => __( 'A publicly available email address for sending support issues to.', 'woocommerce-payments' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_business_support_email_address' ],
					],
					'account_business_support_phone'       => [
						'description'       => __( 'A publicly available phone number to call with support issues.', 'woocommerce-payments' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_business_support_phone' ],
					],
					'account_branding_logo'                => [
						'description' => __( 'A logo id for the account that will be used in Checkout', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_branding_icon'                => [
						'description' => __( 'An icon for the account.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_branding_primary_color'       => [
						'description' => __( 'A CSS hex color value representing the primary branding color for this account.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_branding_secondary_color'     => [
						'description' => __( 'A CSS hex color value representing the secondary branding color for this account.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'deposit_schedule_interval'            => [
						'description' => __( 'An interval for deposit scheduling.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'deposit_schedule_weekly_anchor'       => [
						'description' => __( 'Weekly anchor for deposit scheduling when interval is set to weekly', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'deposit_schedule_monthly_anchor'      => [
						'description' => __( 'Monthly anchor for deposit scheduling when interval is set to monthly', 'woocommerce-payments' ),
						'type'        => [ 'integer', 'null' ],
					],
					'is_payment_request_enabled'           => [
						'description'       => sprintf(
							/* translators: %s: WooPayments */
							__( 'If %s express checkouts should be enabled.', 'woocommerce-payments' ),
							'WooPayments'
						),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_apple_google_pay_in_payment_methods_options_enabled' => [
						'description'       => __( 'If Apple Pay / Google Pay should be enabled as an option in the payment methods list.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_enabled_locations'    => [
						'description'       => __( 'Express checkout locations that should be enabled.', 'woocommerce-payments' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $wcpay_form_fields['payment_request_button_locations']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_type'          => [
						'description'       => __( '1-click checkout button types.', 'woocommerce-payments' ),
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $wcpay_form_fields['payment_request_button_type']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_size'          => [
						'description'       => __( '1-click checkout button sizes.', 'woocommerce-payments' ),
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							// it can happen that `$wcpay_form_fields['payment_request_button_size']` is empty (in tests) - fixing temporarily.
							'enum' => array_keys( isset( $wcpay_form_fields['payment_request_button_size']['options'] ) ? $wcpay_form_fields['payment_request_button_size']['options'] : [] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_theme'         => [
						'description'       => __( '1-click checkout button themes.', 'woocommerce-payments' ),
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $wcpay_form_fields['payment_request_button_theme']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_border_radius' => [
						'description'       => __( '1-click checkout button border radius.', 'woocommerce-payments' ),
						'type'              => 'integer',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_woopay_enabled'                    => [
						'description'       => __( 'If WooPay should be enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'woopay_custom_message'                => [
						'description'       => __( 'Custom message to display to WooPay customers.', 'woocommerce-payments' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'woopay_store_logo'                    => [
						'description'       => __( 'Store logo to display to WooPay customers.', 'woocommerce-payments' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'woopay_enabled_locations'             => [
						'description'       => __( 'Express checkout locations that should be enabled.', 'woocommerce-payments' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $wcpay_form_fields['payment_request_button_locations']['options'] ),
						],
						'default'           => array_keys( $wcpay_form_fields['payment_request_button_locations']['options'] ),
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_stripe_billing_enabled'            => [
						'description'       => __( 'If Stripe Billing is enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_migrating_stripe_billing'          => [
						'description'       => __( 'Whether there is a Stripe Billing off-site to on-site billing migration in progress.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'stripe_billing_subscription_count'    => [
						'description'       => __( 'The number of subscriptions using Stripe Billing', 'woocommerce-payments' ),
						'type'              => 'int',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'stripe_billing_migrated_count'        => [
						'description'       => __( 'The number of subscriptions migrated from Stripe Billing to on-site billing.', 'woocommerce-payments' ),
						'type'              => 'int',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/schedule-stripe-billing-migration',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'schedule_stripe_billing_migration' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Validate the statement descriptor argument.
	 *
	 * @since 4.7.0
	 *
	 * @param mixed           $value The value being validated.
	 * @param WP_REST_Request $request The request made.
	 * @param string          $param The parameter name, used in error messages.
	 * @return true|WP_Error
	 */
	public function validate_statement_descriptor( $value, $request, $param ) {
		$string_validation_result = rest_validate_request_arg( $value, $request, $param );
		if ( true !== $string_validation_result ) {
			return $string_validation_result;
		}

		try {
			$this->wcpay_gateway->validate_account_statement_descriptor_field( 'account_statement_descriptor', $value );
		} catch ( Exception $exception ) {
			return new WP_Error(
				'rest_invalid_pattern',
				$exception->getMessage()
			);
		}

		return true;
	}

	/**
	 * Validate the business support email.
	 *
	 * @param string          $value The value being validated.
	 * @param WP_REST_Request $request The request made.
	 * @param string          $param The parameter name, used in error messages.
	 * @return true|WP_Error
	 */
	public function validate_business_support_email_address( string $value, WP_REST_Request $request, string $param ) {
		$string_validation_result = rest_validate_request_arg( $value, $request, $param );
		if ( true !== $string_validation_result ) {
			return $string_validation_result;
		}

		if ( '' !== $value && ! is_email( $value ) ) {
			return new WP_Error(
				'rest_invalid_pattern',
				__( 'Error: Invalid email address: ', 'woocommerce-payments' ) . $value
			);
		}

		return true;
	}

	/**
	 * Validate the business support phone.
	 *
	 * @param string          $value The value being validated.
	 * @param WP_REST_Request $request The request made.
	 * @param string          $param The parameter name, used in error messages.
	 * @return true|WP_Error
	 */
	public function validate_business_support_phone( string $value, WP_REST_Request $request, string $param ) {
		$string_validation_result = rest_validate_request_arg( $value, $request, $param );
		if ( true !== $string_validation_result ) {
			return $string_validation_result;
		}

		if ( '' !== $value && ! WC_Validation::is_phone( $value ) ) {
			return new WP_Error(
				'rest_invalid_pattern',
				__( 'Error: Invalid phone number: ', 'woocommerce-payments' ) . $value
			);
		}

		// Japan accounts require Japanese phone numbers.
		if ( Country_Code::JAPAN === $this->account->get_account_country() ) {
			if ( '+81' !== substr( $value, 0, 3 ) ) {
				return new WP_Error(
					'rest_invalid_pattern',
					__( 'Error: Invalid Japanese phone number: ', 'woocommerce-payments' ) . $value
				);
			}
		}

		return true;
	}

	/**
	 * Validate the business support address.
	 *
	 * @param array           $value The value being validated.
	 * @param WP_REST_Request $request The request made.
	 * @param string          $param The parameter name, used in error messages.
	 * @return true|WP_Error
	 */
	public function validate_business_support_address( array $value, WP_REST_Request $request, string $param ) {
		$string_validation_result = rest_validate_request_arg( $value, $request, $param );
		if ( true !== $string_validation_result ) {
			return $string_validation_result;
		}

		if ( [] !== $value ) {
			foreach ( $value as $field => $field_value ) {
				if ( ! in_array( $field, [ 'city', 'country', 'line1', 'line2', 'postal_code', 'state' ], true ) ) {
					return new WP_Error(
						'rest_invalid_pattern',
						__( 'Error: Invalid address format!', 'woocommerce-payments' )
					);
				}
			}
		}

		return true;
	}

	/**
	 * Retrieve settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		$wcpay_form_fields = $this->wcpay_gateway->get_form_fields();

		$available_upe_payment_methods = $this->wcpay_gateway->get_upe_available_payment_methods();

		/**
		 * It might be possible that enabled payment methods settings have an invalid state. As an example,
		 * if an account is switched to a new country and earlier country had PM's that are no longer valid; or if the PM is not available anymore.
		 * To keep saving settings working, we are ensuring the enabled payment methods are yet available.
		 */
		$enabled_payment_methods = array_values(
			array_intersect(
				$this->wcpay_gateway->get_upe_enabled_payment_method_ids(),
				$available_upe_payment_methods
			)
		);

		// Gather the status of the Stripe Billing migration for use on the settings page.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$stripe_billing_migrated_count = $this->wcpay_gateway->get_subscription_migrated_count();

			if ( class_exists( 'WC_Payments_Subscriptions' ) ) {
				$stripe_billing_migrator = WC_Payments_Subscriptions::get_stripe_billing_migrator();

				if ( $stripe_billing_migrator ) {
					$is_migrating_stripe_billing       = $stripe_billing_migrator->is_migrating();
					$stripe_billing_subscription_count = $stripe_billing_migrator->get_stripe_billing_subscription_count();
				}
			}
		}

		return new WP_REST_Response(
			[
				'enabled_payment_method_ids'             => $enabled_payment_methods,
				'available_payment_method_ids'           => $available_upe_payment_methods,
				'payment_method_statuses'                => $this->wcpay_gateway->get_upe_enabled_payment_method_statuses(),
				'duplicated_payment_method_ids'          => $this->wcpay_gateway->find_duplicates(),
				'is_wcpay_enabled'                       => $this->wcpay_gateway->is_enabled(),
				'is_manual_capture_enabled'              => 'yes' === $this->wcpay_gateway->get_option( 'manual_capture' ),
				'is_test_mode_enabled'                   => WC_Payments::mode()->is_test(),
				'is_test_mode_onboarding'                => WC_Payments::mode()->is_test_mode_onboarding(),
				'is_dev_mode_enabled'                    => WC_Payments::mode()->is_dev(),
				'is_multi_currency_enabled'              => WC_Payments_Features::is_customer_multi_currency_enabled(),
				'is_wcpay_subscriptions_enabled'         => WC_Payments_Features::is_wcpay_subscriptions_enabled(),
				'is_stripe_billing_enabled'              => WC_Payments_Features::is_stripe_billing_enabled(),
				'is_wcpay_subscriptions_eligible'        => WC_Payments_Features::is_wcpay_subscriptions_eligible(),
				'is_subscriptions_plugin_active'         => $this->wcpay_gateway->is_subscriptions_plugin_active(),
				'is_woopay_global_theme_support_enabled' => $this->wcpay_gateway->is_woopay_global_theme_support_enabled(),
				'account_country'                        => $this->wcpay_gateway->get_option( 'account_country' ),
				'account_statement_descriptor'           => $this->wcpay_gateway->get_option( 'account_statement_descriptor' ),
				'account_statement_descriptor_kanji'     => $this->wcpay_gateway->get_option( 'account_statement_descriptor_kanji' ),
				'account_statement_descriptor_kana'      => $this->wcpay_gateway->get_option( 'account_statement_descriptor_kana' ),
				'account_business_name'                  => $this->wcpay_gateway->get_option( 'account_business_name' ),
				'account_business_url'                   => $this->wcpay_gateway->get_option( 'account_business_url' ),
				'account_business_support_address'       => $this->wcpay_gateway->get_option( 'account_business_support_address' ),
				'account_business_support_email'         => $this->wcpay_gateway->get_option( 'account_business_support_email' ),
				'account_business_support_phone'         => $this->wcpay_gateway->get_option( 'account_business_support_phone' ),
				'account_branding_logo'                  => $this->wcpay_gateway->get_option( 'account_branding_logo' ),
				'account_branding_icon'                  => $this->wcpay_gateway->get_option( 'account_branding_icon' ),
				'account_branding_primary_color'         => $this->wcpay_gateway->get_option( 'account_branding_primary_color' ),
				'account_branding_secondary_color'       => $this->wcpay_gateway->get_option( 'account_branding_secondary_color' ),
				'account_domestic_currency'              => $this->wcpay_gateway->get_option( 'account_domestic_currency' ),
				'is_payment_request_enabled'             => 'yes' === $this->wcpay_gateway->get_option( 'payment_request' ),
				'is_apple_google_pay_in_payment_methods_options_enabled' => 'yes' === $this->wcpay_gateway->get_option( 'apple_google_pay_in_payment_methods_options' ),
				'is_debug_log_enabled'                   => 'yes' === $this->wcpay_gateway->get_option( 'enable_logging' ),
				'payment_request_enabled_locations'      => $this->wcpay_gateway->get_option( 'payment_request_button_locations' ),
				'payment_request_button_size'            => $this->wcpay_gateway->get_option( 'payment_request_button_size' ),
				'payment_request_button_type'            => $this->wcpay_gateway->get_option( 'payment_request_button_type' ),
				'payment_request_button_theme'           => $this->wcpay_gateway->get_option( 'payment_request_button_theme' ),
				'payment_request_button_border_radius'   => $this->wcpay_gateway->get_option( 'payment_request_button_border_radius', WC_Payments_Express_Checkout_Button_Handler::DEFAULT_BORDER_RADIUS_IN_PX ),
				'is_saved_cards_enabled'                 => $this->wcpay_gateway->is_saved_cards_enabled(),
				'is_card_present_eligible'               => $this->wcpay_gateway->is_card_present_eligible() && isset( WC()->payment_gateways()->get_available_payment_gateways()['cod'] ),
				'is_woopay_enabled'                      => WC_Payments_Features::is_woopay_eligible() && 'yes' === $this->wcpay_gateway->get_option( 'platform_checkout' ),
				'show_woopay_incompatibility_notice'     => get_option( 'woopay_invalid_extension_found', false ),
				'woopay_custom_message'                  => $this->wcpay_gateway->get_option( 'platform_checkout_custom_message' ),
				'woopay_store_logo'                      => $this->wcpay_gateway->get_option( 'platform_checkout_store_logo' ),
				'woopay_enabled_locations'               => $this->wcpay_gateway->get_option( 'platform_checkout_button_locations', array_keys( $wcpay_form_fields['payment_request_button_locations']['options'] ) ),
				'deposit_schedule_interval'              => $this->wcpay_gateway->get_option( 'deposit_schedule_interval' ),
				'deposit_schedule_monthly_anchor'        => $this->wcpay_gateway->get_option( 'deposit_schedule_monthly_anchor' ),
				'deposit_schedule_weekly_anchor'         => $this->wcpay_gateway->get_option( 'deposit_schedule_weekly_anchor' ),
				'deposit_delay_days'                     => $this->wcpay_gateway->get_option( 'deposit_delay_days' ),
				'deposit_status'                         => $this->wcpay_gateway->get_option( 'deposit_status' ),
				'deposit_restrictions'                   => $this->wcpay_gateway->get_option( 'deposit_restrictions' ),
				'deposit_completed_waiting_period'       => $this->wcpay_gateway->get_option( 'deposit_completed_waiting_period' ),
				'current_protection_level'               => $this->wcpay_gateway->get_option( 'current_protection_level' ),
				'advanced_fraud_protection_settings'     => $this->wcpay_gateway->get_option( 'advanced_fraud_protection_settings' ),
				'is_migrating_stripe_billing'            => $is_migrating_stripe_billing ?? false,
				'stripe_billing_subscription_count'      => $stripe_billing_subscription_count ?? 0,
				'stripe_billing_migrated_count'          => $stripe_billing_migrated_count ?? 0,
			]
		);
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_settings( WP_REST_Request $request ) {
		$this->update_is_wcpay_enabled( $request );
		$this->update_enabled_payment_methods( $request );
		$this->update_is_manual_capture_enabled( $request );
		$this->update_is_test_mode_enabled( $request );
		$this->update_is_debug_log_enabled( $request );
		$this->update_is_multi_currency_enabled( $request );
		$this->update_is_wcpay_subscriptions_enabled( $request );
		$this->update_is_payment_request_enabled( $request );
		$this->update_is_apple_google_pay_in_payment_methods_options_enabled( $request );
		$this->update_payment_request_enabled_locations( $request );
		$this->update_payment_request_appearance( $request );
		$this->update_is_saved_cards_enabled( $request );
		$this->update_is_woopay_enabled( $request );
		$this->update_is_woopay_global_theme_support_enabled( $request );
		$this->update_woopay_store_logo( $request );
		$this->update_woopay_custom_message( $request );
		$this->update_woopay_enabled_locations( $request );
		// Note: Both "current_protection_level" and "advanced_fraud_protection_settings"
		// are handled in the below method.
		$this->update_fraud_protection_settings( $request );
		$this->update_is_stripe_billing_enabled( $request );

		$update_account_result = $this->update_account( $request );

		if ( is_wp_error( $update_account_result ) ) {
			return new WP_REST_Response( [ 'server_error' => $update_account_result->get_error_message() ], 400 );
		}

		// Sync the store setup with the Transact Platform.
		$this->account->store_setup_sync();

		return new WP_REST_Response( $this->get_settings(), 200 );
	}

	/**
	 * Schedule a migration of Stripe Billing subscriptions.
	 *
	 * @param WP_REST_Request $request The request object. Optional. If passed, the function will return a REST response.
	 *
	 * @return WP_REST_Response|null The response object, if this is a REST request.
	 */
	public function schedule_stripe_billing_migration( ?WP_REST_Request $request = null ) {

		if ( class_exists( 'WC_Payments_Subscriptions' ) ) {
			$stripe_billing_migrator = WC_Payments_Subscriptions::get_stripe_billing_migrator();

			if ( $stripe_billing_migrator && ! $stripe_billing_migrator->is_migrating() && $stripe_billing_migrator->get_stripe_billing_subscription_count() > 0 ) {
				$stripe_billing_migrator->schedule_migrate_wcpay_subscriptions_action();
			}
		}

		// Return a response if this is a REST request.
		if ( $request ) {
			return new WP_REST_Response( [], 200 );
		}
	}

	/**
	 * Updates WooPayments enabled status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_wcpay_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_wcpay_enabled' ) ) {
			return;
		}

		$is_wcpay_enabled = $request->get_param( 'is_wcpay_enabled' );

		if ( $is_wcpay_enabled ) {
			$this->wcpay_gateway->enable();
		} else {
			$this->wcpay_gateway->disable();
		}
	}

	/**
	 * Updates the list of enabled payment methods.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_enabled_payment_methods( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'enabled_payment_method_ids' ) ) {
			return;
		}

		$payment_method_ids_to_enable = $request->get_param( 'enabled_payment_method_ids' );
		$available_payment_methods    = $this->wcpay_gateway->get_upe_available_payment_methods();

		// Only 'card' and 'link' support manual capture. Leave them enabled if they're already enabled.
		if ( $request->has_param( 'is_manual_capture_enabled' ) && $request->get_param( 'is_manual_capture_enabled' ) ) {
			$payment_method_ids_to_enable = array_intersect( $payment_method_ids_to_enable, [ Payment_Method::CARD, Payment_Method::LINK ] );
		}

		$payment_method_ids_to_enable = array_values(
			array_filter(
				$payment_method_ids_to_enable,
				function ( $payment_method ) use ( $available_payment_methods ) {
					return in_array( $payment_method, $available_payment_methods, true );
				}
			)
		);

		$this->request_unrequested_payment_methods( $payment_method_ids_to_enable );

		$active_payment_methods   = $this->wcpay_gateway->get_upe_enabled_payment_method_ids();
		$disabled_payment_methods = array_diff( $active_payment_methods, $payment_method_ids_to_enable );
		$enabled_payment_methods  = array_diff( $payment_method_ids_to_enable, $active_payment_methods );

		// Log Tracks events for each enabled/disabled payment method.
		// We log these events before actually enabling/disabling the payment methods
		// to ensure that if enabling/disabling fails, we still have a record of the
		// attempt.
		$pm_to_capability_key_map = $this->wcpay_gateway->get_payment_method_capability_key_map();
		foreach ( $disabled_payment_methods as $disabled_payment_method ) {
			$this->tracks_event(
				Track_Events::PAYMENT_METHOD_DISABLED,
				[
					'payment_method_id' => $disabled_payment_method,
					'capability_id'     => $pm_to_capability_key_map[ $disabled_payment_method ] ?? null,
				]
			);
		}
		foreach ( $enabled_payment_methods as $enabled_payment_method ) {
			$this->tracks_event(
				Track_Events::PAYMENT_METHOD_ENABLED,
				[
					'payment_method_id' => $enabled_payment_method,
					'capability_id'     => $pm_to_capability_key_map[ $enabled_payment_method ] ?? null,
				]
			);
		}

		foreach ( $enabled_payment_methods as $payment_method_id ) {
			$gateway = WC_Payments::get_payment_gateway_by_id( $payment_method_id );
			if ( ! $gateway ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					$logger = wc_get_logger();
					/* translators: 1: Payment method ID, 2: Error message */
					$logger->warning( sprintf( 'Failed to enable payment method %1$s: %2$s', $payment_method_id, 'payment gateway instance not available' ), [ 'source' => 'woopayments' ] );
				}
				continue;
			}
			$gateway->enable();
		}

		foreach ( $disabled_payment_methods as $payment_method_id ) {
			$gateway = WC_Payments::get_payment_gateway_by_id( $payment_method_id );
			if ( ! $gateway ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					$logger = wc_get_logger();
					/* translators: 1: Payment method ID, 2: Error message */
					$logger->warning( sprintf( 'Failed to disable payment method %1$s: %2$s', $payment_method_id, 'payment gateway instance not available' ), [ 'source' => 'woopayments' ] );
				}
				continue;
			}
			$gateway->disable();
		}

		// Keep the enabled payment method IDs list synchronized across gateway setting objects unless we remove this list with all dependencies.
		foreach ( WC_Payments::get_payment_gateway_map() as $payment_gateway ) {
			$payment_gateway->update_option( 'upe_enabled_payment_method_ids', $payment_method_ids_to_enable );
		}
	}

	/**
	 * Requests the capabilities of unrequested payment methods
	 *
	 * @param   array $payment_method_ids_to_enable  Enabled Payment method ID's.
	 *
	 * @return  void
	 */
	private function request_unrequested_payment_methods( $payment_method_ids_to_enable ) {
		$capability_key_map      = $this->wcpay_gateway->get_payment_method_capability_key_map();
		$payment_method_statuses = $this->wcpay_gateway->get_upe_enabled_payment_method_statuses();
		$cache_needs_refresh     = false;
		foreach ( $payment_method_ids_to_enable as $payment_method_id_to_enable ) {
			$stripe_key = $capability_key_map[ $payment_method_id_to_enable ] ?? null;
			if ( array_key_exists( $stripe_key, $payment_method_statuses ) ) {
				if ( 'unrequested' === $payment_method_statuses[ $stripe_key ]['status'] ) {
					$request_result      = $this->api_client->request_capability( $stripe_key, true );
					$cache_needs_refresh = $cache_needs_refresh || 'unrequested' !== $request_result['status'];
				}
			}
		}

		if ( $cache_needs_refresh ) {
			$this->wcpay_gateway->refresh_cached_account_data();
		}
	}

	/**
	 * Updates WooPayments manual capture.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_manual_capture_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_manual_capture_enabled' ) ) {
			return;
		}

		$is_manual_capture_enabled = $request->get_param( 'is_manual_capture_enabled' );

		$this->wcpay_gateway->update_option( 'manual_capture', $is_manual_capture_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates WooPayments test mode.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_test_mode_enabled( WP_REST_Request $request ) {
		// Avoid updating test mode when dev mode is enabled.
		if ( WC_Payments::mode()->is_dev() ) {
			return;
		}

		if ( ! $request->has_param( 'is_test_mode_enabled' ) ) {
			return;
		}

		$is_test_mode_enabled = $request->get_param( 'is_test_mode_enabled' );

		$this->wcpay_gateway->update_option( 'test_mode', $is_test_mode_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates WooPayments test mode.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_debug_log_enabled( WP_REST_Request $request ) {
		// Avoid updating test mode when dev mode is enabled.
		if ( WC_Payments::mode()->is_dev() ) {
			return;
		}

		if ( ! $request->has_param( 'is_debug_log_enabled' ) ) {
			return;
		}

		$is_debug_log_enabled = $request->get_param( 'is_debug_log_enabled' );

		$this->wcpay_gateway->update_option( 'enable_logging', $is_debug_log_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates customer Multi-Currency feature status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_multi_currency_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_multi_currency_enabled' ) ) {
			return;
		}

		$is_multi_currency_enabled = $request->get_param( 'is_multi_currency_enabled' );

		update_option( '_wcpay_feature_customer_multi_currency', $is_multi_currency_enabled ? '1' : '0' );
	}

	/**
	 * Updates the WCPay Subscriptions feature status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_wcpay_subscriptions_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_wcpay_subscriptions_enabled' ) ) {
			return;
		}

		$is_wcpay_subscriptions_enabled = $request->get_param( 'is_wcpay_subscriptions_enabled' );

		// Prevent enabling bundled subscriptions - feature has been removed in 10.2.0.
		// Only allow disabling the feature if it was previously enabled.
		if ( ! $is_wcpay_subscriptions_enabled ) {
			update_option( WC_Payments_Features::WCPAY_SUBSCRIPTIONS_FLAG_NAME, '0' );
		}
	}

	/**
	 * Updates WooPayments account fields
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_account( WP_REST_Request $request ) {
		$updated_fields_callback = function ( $value, string $key ) {
			return array_key_exists( $key, WC_Payment_Gateway_WCPay::ACCOUNT_SETTINGS_MAPPING ) &&
				$this->wcpay_gateway->get_option( $key ) !== $value;
		};
		// Filter out fields that are unchanged or not in the list of fields to update.
		$updated_fields = array_filter( $request->get_params(), $updated_fields_callback, ARRAY_FILTER_USE_BOTH );

		// If we are updating an anchor for the deposit schedule then we must also send through the interval.
		if ( ! isset( $updated_fields['deposit_schedule_interval'] ) && array_intersect( array_keys( $updated_fields ), [ 'deposit_schedule_monthly_anchor', 'deposit_schedule_weekly_anchor' ] ) ) {
			$updated_fields['deposit_schedule_interval'] = $this->wcpay_gateway->get_option( 'deposit_schedule_interval' );
		}

		// If we are updating any deposit schedule values, we should invalidate the next deposit notice dismissed notice option.
		if ( preg_grep( '/^deposit_schedule_/', array_keys( $updated_fields ) ) ) {
			delete_option( 'wcpay_next_deposit_notice_dismissed' );
		}

		return $this->wcpay_gateway->update_account_settings( $updated_fields );
	}

	/**
	 * Updates the "payment request" enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_payment_request_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_payment_request_enabled' ) ) {
			return;
		}

		$is_payment_request_enabled = $request->get_param( 'is_payment_request_enabled' );

		$this->wcpay_gateway->update_option( 'payment_request', $is_payment_request_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates the "Apple Pay / Google Pay in payment methods options" enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_apple_google_pay_in_payment_methods_options_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_apple_google_pay_in_payment_methods_options_enabled' ) ) {
			return;
		}

		$is_apple_google_pay_in_payment_methods_options_enabled = $request->get_param( 'is_apple_google_pay_in_payment_methods_options_enabled' );

		$this->wcpay_gateway->update_option( 'apple_google_pay_in_payment_methods_options', $is_apple_google_pay_in_payment_methods_options_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates the list of locations that will show the payment request button.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_payment_request_enabled_locations( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'payment_request_enabled_locations' ) ) {
			return;
		}

		$payment_request_enabled_locations = $request->get_param( 'payment_request_enabled_locations' );

		$this->wcpay_gateway->update_option( 'payment_request_button_locations', $payment_request_enabled_locations );
	}

	/**
	 * Updates appearance attributes of the payment request button.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_payment_request_appearance( WP_REST_Request $request ) {
		$attributes = [
			'payment_request_button_type'          => 'payment_request_button_type',
			'payment_request_button_size'          => 'payment_request_button_size',
			'payment_request_button_theme'         => 'payment_request_button_theme',
			'payment_request_button_border_radius' => 'payment_request_button_border_radius',
		];
		foreach ( $attributes as $request_key => $attribute ) {
			if ( ! $request->has_param( $request_key ) ) {
				continue;
			}

			$value = $request->get_param( $request_key );
			$this->wcpay_gateway->update_option( $attribute, $value );
		}
	}

	/**
	 * Updates WooPayments "saved cards" feature.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_saved_cards_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_saved_cards_enabled' ) ) {
			return;
		}

		$is_saved_cards_enabled = $request->get_param( 'is_saved_cards_enabled' );

		$this->wcpay_gateway->update_option( 'saved_cards', $is_saved_cards_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates the "woopay" enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_woopay_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_woopay_enabled' ) ) {
			return;
		}

		$is_woopay_enabled = $request->get_param( 'is_woopay_enabled' );

		$this->wcpay_gateway->update_is_woopay_enabled( $is_woopay_enabled );
	}

	/**
	 * Updates the WooPay Global Theme Support enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_woopay_global_theme_support_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_woopay_global_theme_support_enabled' ) ) {
			return;
		}

		$value = $request->get_param( 'is_woopay_global_theme_support_enabled' );

		$this->wcpay_gateway->update_is_woopay_global_theme_support_enabled( $value );
	}

	/**
	 * Updates the custom message that will appear for woopay customers.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_woopay_custom_message( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'woopay_custom_message' ) ) {
			return;
		}

		$woopay_custom_message = $request->get_param( 'woopay_custom_message' );
		$woopay_custom_message = str_replace( '[terms_of_service_link]', '[terms]', $woopay_custom_message );
		$woopay_custom_message = str_replace( '[privacy_policy_link]', '[privacy_policy]', $woopay_custom_message );

		$this->wcpay_gateway->update_option( 'platform_checkout_custom_message', $woopay_custom_message );
	}

	/**
	 * Updates the store logo that will appear for woopay customers.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_woopay_store_logo( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'woopay_store_logo' ) ) {
			return;
		}

		$woopay_store_logo = $request->get_param( 'woopay_store_logo' );

		$this->wcpay_gateway->update_option( 'platform_checkout_store_logo', $woopay_store_logo );
	}

	/**
	 * Updates the list of locations that will show the payment request button.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_woopay_enabled_locations( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'woopay_enabled_locations' ) ) {
			return;
		}

		$is_woopay_enabled = WC_Payments_Features::is_woopay_enabled();
		if ( ! $is_woopay_enabled ) {
			return;
		}

		$woopay_enabled_locations = $request->get_param( 'woopay_enabled_locations' );
		$wcpay_form_fields        = $this->wcpay_gateway->get_form_fields();
		$all_locations            = $wcpay_form_fields['payment_request_button_locations']['options'];

		WC_Payments::woopay_tracker()->woopay_locations_updated( $all_locations, $woopay_enabled_locations );

		$this->wcpay_gateway->update_option( 'platform_checkout_button_locations', $woopay_enabled_locations );
	}

	/**
	 * Updates the settings of fraud protection rules (both settings and level in one function, because they are connected).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @throws InvalidArgumentException If the ruleset configuration is invalid.
	 */
	private function update_fraud_protection_settings( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'current_protection_level' ) || ! $request->has_param( 'advanced_fraud_protection_settings' ) ) {
			return;
		}

		$protection_level = $request->get_param( 'current_protection_level' );

		// Check validity of the protection level.
		if ( ! in_array( $protection_level, [ 'basic', 'standard', 'high', 'advanced' ], true ) ) {
			return;
		}

		// Get rulesets per protection level.
		switch ( $protection_level ) {
			case 'basic':
				$ruleset_config = Fraud_Risk_Tools::get_basic_protection_settings();
				break;
			case 'standard':
				$ruleset_config = Fraud_Risk_Tools::get_standard_protection_settings();
				break;
			case 'high':
				$ruleset_config = Fraud_Risk_Tools::get_high_protection_settings();
				break;
			case 'advanced':
				$received_ruleset = $request->get_param( 'advanced_fraud_protection_settings' );
				if ( ! is_array( $received_ruleset ) ) {
					throw new InvalidArgumentException( 'Invalid ruleset configuration' );
				}
				foreach ( $received_ruleset as $rule ) {
					if ( ! Rule::validate_array( $rule ) ) {
						throw new InvalidArgumentException( 'Invalid ruleset configuration' );
					}
				}
				$ruleset_config = $received_ruleset;
				break;
		}

		// Save ruleset to the server.
		$this->api_client->save_fraud_ruleset( $ruleset_config );

		// Update local cache.
		$this->wcpay_gateway->update_cached_account_data(
			'fraud_mitigation_settings',
			[ 'avs_check_enabled' => $this->get_avs_check_enabled( $ruleset_config ) ]
		);
		delete_transient( 'wcpay_fraud_protection_settings' );
		set_transient( 'wcpay_fraud_protection_settings', $ruleset_config, 1 * DAY_IN_SECONDS );

		// Update the option only when server update succeeds.
		update_option( 'current_protection_level', $protection_level );
	}

	/**
	 * Updates the Stripe Billing Subscriptions feature status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_stripe_billing_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_stripe_billing_enabled' ) ) {
			return;
		}

		$is_stripe_billing_enabled = $request->get_param( 'is_stripe_billing_enabled' );

		update_option( WC_Payments_Features::STRIPE_BILLING_FLAG_NAME, $is_stripe_billing_enabled ? '1' : '0' );

		// Schedule a migration if Stripe Billing was disabled and there are subscriptions to migrate.
		if ( ! $is_stripe_billing_enabled ) {
			$this->schedule_stripe_billing_migration();
		}
	}

	/**
	 * Get the AVS check enabled status from the ruleset config.
	 *
	 * @param array $ruleset_config The ruleset config.
	 *
	 * @return bool
	 */
	private function get_avs_check_enabled( array $ruleset_config ) {
		$avs_check_enabled = false;

		foreach ( $ruleset_config as $rule_definition ) {
			if ( 'avs_verification' === $rule_definition['key'] ) {
				$avs_check_enabled = true;
				break;
			}
		}

		return $avs_check_enabled;
	}

	/**
	 * Send a Tracks event.
	 *
	 * By default Woo adds `url`, `blog_lang`, `blog_id`, `store_id`, `products_count`, and `wc_version`
	 * properties to every event.
	 *
	 * @todo This is a duplicate of the one in the WC_Payments_Account and WC_Payments_Onboarding_Service classes.
	 *
	 * @param string $name       The event name.
	 * @param array  $properties Optional. The event custom properties.
	 *
	 * @return void
	 */
	private function tracks_event( string $name, array $properties = [] ) {
		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}

		// Add default properties to every event.
		$account_service = WC_Payments::get_account_service();
		$tracking_info   = $account_service ? $account_service->get_tracking_info() : [];

		$properties = array_merge(
			$properties,
			[
				'is_test_mode'      => WC_Payments::mode()->is_test(),
				'jetpack_connected' => $this->api_client->is_server_connected(),
				'wcpay_version'     => WCPAY_VERSION_NUMBER,
				'woo_country_code'  => WC()->countries->get_base_country(),
			],
			$tracking_info ?? []
		);

		wc_admin_record_tracks_event( $name, $properties );

		Logger::info( 'Tracks event: ' . $name . ' with data: ' . wp_json_encode( WC_Payments_Utils::redact_array( $properties, [ 'woo_country_code' ] ) ) );
	}
}

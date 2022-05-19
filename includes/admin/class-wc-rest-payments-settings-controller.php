<?php
/**
 * Class WC_REST_Payments_Settings_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for settings.
 */
class WC_REST_Payments_Settings_Controller extends WC_Payments_REST_Controller {

	const ACCOUNT_FIELDS_TO_UPDATE = [
		'account_statement_descriptor',
		'account_business_name',
		'account_business_url',
		'account_business_support_address',
		'account_business_support_email',
		'account_business_support_phone',
		'account_branding_logo',
		'account_branding_icon',
		'account_branding_primary_color',
		'account_branding_secondary_color',
	];

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
	 * WC_REST_Payments_Settings_Controller constructor.
	 *
	 * @param WC_Payments_API_Client   $api_client WC_Payments_API_Client instance.
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WC_Payment_Gateway_WCPay instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payment_Gateway_WCPay $wcpay_gateway ) {
		parent::__construct( $api_client );

		$this->wcpay_gateway = $wcpay_gateway;
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
					'is_wcpay_enabled'                  => [
						'description'       => __( 'If WooCommerce Payments should be enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'enabled_payment_method_ids'        => [
						'description'       => __( 'Payment method IDs that should be enabled. Other methods will be disabled.', 'woocommerce-payments' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => $this->wcpay_gateway->get_upe_available_payment_methods(),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_manual_capture_enabled'         => [
						'description'       => __( 'If WooCommerce Payments manual capture of charges should be enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_saved_cards_enabled'            => [
						'description'       => __( 'If WooCommerce Payments "Saved cards" should be enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_test_mode_enabled'              => [
						'description'       => __( 'WooCommerce Payments test mode setting.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_multi_currency_enabled'         => [
						'description'       => __( 'WooCommerce Payments Multi-Currency feature flag setting.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_wcpay_subscription_enabled'     => [
						'description'       => __( 'WooCommerce Payments Subscriptions feature flag setting.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'account_statement_descriptor'      => [
						'description'       => __( 'WooCommerce Payments bank account descriptor to be displayed in customers\' bank accounts.', 'woocommerce-payments' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_statement_descriptor' ],
					],
					'account_business_name'             => [
						'description' => __( 'The customer-facing business name.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_business_url'              => [
						'description' => __( 'The business’s publicly available website.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_business_support_address'  => [
						'description'       => __( 'A publicly available mailing address for sending support issues to.', 'woocommerce-payments' ),
						'type'              => 'object',
						'validate_callback' => [ $this, 'validate_business_support_address' ],
					],
					'account_business_support_email'    => [
						'description'       => __( 'A publicly available email address for sending support issues to.', 'woocommerce-payments' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_business_support_email_address' ],
					],
					'account_business_support_phone'    => [
						'description'       => __( 'A publicly available phone number to call with support issues.', 'woocommerce-payments' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_business_support_phone' ],
					],
					'account_branding_logo'             => [
						'description' => __( 'A logo id for the account that will be used in Checkout', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_branding_icon'             => [
						'description' => __( 'An icon for the account.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_branding_primary_color'    => [
						'description' => __( 'A CSS hex color value representing the primary branding color for this account.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'account_branding_secondary_color'  => [
						'description' => __( 'A CSS hex color value representing the secondary branding color for this account.', 'woocommerce-payments' ),
						'type'        => 'string',
					],
					'is_payment_request_enabled'        => [
						'description'       => __( 'If WooCommerce Payments express checkouts should be enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_enabled_locations' => [
						'description'       => __( 'Express checkout locations that should be enabled.', 'woocommerce-payments' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $wcpay_form_fields['payment_request_button_locations']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_type'       => [
						'description'       => __( '1-click checkout button types.', 'woocommerce-payments' ),
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $wcpay_form_fields['payment_request_button_type']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_size'       => [
						'description'       => __( '1-click checkout button sizes.', 'woocommerce-payments' ),
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							// it can happen that `$wcpay_form_fields['payment_request_button_size']` is empty (in tests) - fixing temporarily.
							'enum' => array_keys( isset( $wcpay_form_fields['payment_request_button_size']['options'] ) ? $wcpay_form_fields['payment_request_button_size']['options'] : [] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_theme'      => [
						'description'       => __( '1-click checkout button themes.', 'woocommerce-payments' ),
						'type'              => 'string',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $wcpay_form_fields['payment_request_button_theme']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_platform_checkout_enabled'      => [
						'description'       => __( 'If WooPay should be enabled.', 'woocommerce-payments' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'platform_checkout_custom_message'  => [
						'description'       => __( 'Custom message to display to WooPay customers.', 'woocommerce-payments' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'platform_checkout_store_logo'      => [
						'description'       => __( 'Store logo to display to WooPay customers.', 'woocommerce-payments' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
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
		return new WP_REST_Response(
			[
				'enabled_payment_method_ids'        => $this->wcpay_gateway->get_upe_enabled_payment_method_ids(),
				'available_payment_method_ids'      => $this->wcpay_gateway->get_upe_available_payment_methods(),
				'payment_method_statuses'           => $this->wcpay_gateway->get_upe_enabled_payment_method_statuses(),
				'is_wcpay_enabled'                  => $this->wcpay_gateway->is_enabled(),
				'is_manual_capture_enabled'         => 'yes' === $this->wcpay_gateway->get_option( 'manual_capture' ),
				'is_test_mode_enabled'              => $this->wcpay_gateway->is_in_test_mode(),
				'is_dev_mode_enabled'               => $this->wcpay_gateway->is_in_dev_mode(),
				'is_multi_currency_enabled'         => WC_Payments_Features::is_customer_multi_currency_enabled(),
				'is_wcpay_subscriptions_enabled'    => WC_Payments_Features::is_wcpay_subscriptions_enabled(),
				'is_wcpay_subscriptions_eligible'   => WC_Payments_Features::is_wcpay_subscriptions_eligible(),
				'is_subscriptions_plugin_active'    => $this->wcpay_gateway->is_subscriptions_plugin_active(),
				'account_statement_descriptor'      => $this->wcpay_gateway->get_option( 'account_statement_descriptor' ),
				'account_business_name'             => $this->wcpay_gateway->get_option( 'account_business_name' ),
				'account_business_url'              => $this->wcpay_gateway->get_option( 'account_business_url' ),
				'account_business_support_address'  => $this->wcpay_gateway->get_option( 'account_business_support_address' ),
				'account_business_support_email'    => $this->wcpay_gateway->get_option( 'account_business_support_email' ),
				'account_business_support_phone'    => $this->wcpay_gateway->get_option( 'account_business_support_phone' ),
				'account_branding_logo'             => $this->wcpay_gateway->get_option( 'account_branding_logo' ),
				'account_branding_icon'             => $this->wcpay_gateway->get_option( 'account_branding_icon' ),
				'account_branding_primary_color'    => $this->wcpay_gateway->get_option( 'account_branding_primary_color' ),
				'account_branding_secondary_color'  => $this->wcpay_gateway->get_option( 'account_branding_secondary_color' ),
				'is_payment_request_enabled'        => 'yes' === $this->wcpay_gateway->get_option( 'payment_request' ),
				'is_debug_log_enabled'              => 'yes' === $this->wcpay_gateway->get_option( 'enable_logging' ),
				'payment_request_enabled_locations' => $this->wcpay_gateway->get_option( 'payment_request_button_locations' ),
				'payment_request_button_size'       => $this->wcpay_gateway->get_option( 'payment_request_button_size' ),
				'payment_request_button_type'       => $this->wcpay_gateway->get_option( 'payment_request_button_type' ),
				'payment_request_button_theme'      => $this->wcpay_gateway->get_option( 'payment_request_button_theme' ),
				'is_saved_cards_enabled'            => $this->wcpay_gateway->is_saved_cards_enabled(),
				'is_card_present_eligible'          => $this->wcpay_gateway->is_card_present_eligible(),
				'is_platform_checkout_enabled'      => 'yes' === $this->wcpay_gateway->get_option( 'platform_checkout' ),
				'platform_checkout_custom_message'  => $this->wcpay_gateway->get_option( 'platform_checkout_custom_message' ),
				'platform_checkout_store_logo'      => $this->wcpay_gateway->get_option( 'platform_checkout_store_logo' ),
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
		$this->update_payment_request_enabled_locations( $request );
		$this->update_payment_request_appearance( $request );
		$this->update_is_saved_cards_enabled( $request );
		$this->update_account( $request );
		$this->update_is_platform_checkout_enabled( $request );
		$this->update_platform_checkout_custom_message( $request );
		$this->update_platform_checkout_store_logo( $request );

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Updates WooCommerce Payments enabled status.
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

		$payment_method_ids_to_enable = array_values(
			array_filter(
				$payment_method_ids_to_enable,
				function ( $payment_method ) use ( $available_payment_methods ) {
					return in_array( $payment_method, $available_payment_methods, true );
				}
			)
		);

		$this->wcpay_gateway->update_option( 'upe_enabled_payment_method_ids', $payment_method_ids_to_enable );
		if ( $payment_method_ids_to_enable ) {
			$this->request_unrequested_payment_methods( $payment_method_ids_to_enable );
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
	 * Updates WooCommerce Payments manual capture.
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
	 * Updates WooCommerce Payments test mode.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_test_mode_enabled( WP_REST_Request $request ) {
		// avoiding updating test mode when dev mode is enabled.
		if ( $this->wcpay_gateway->is_in_dev_mode() ) {
			return;
		}

		if ( ! $request->has_param( 'is_test_mode_enabled' ) ) {
			return;
		}

		$is_test_mode_enabled = $request->get_param( 'is_test_mode_enabled' );

		$this->wcpay_gateway->update_option( 'test_mode', $is_test_mode_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates WooCommerce Payments test mode.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_debug_log_enabled( WP_REST_Request $request ) {
		// avoiding updating test mode when dev mode is enabled.
		if ( $this->wcpay_gateway->is_in_dev_mode() ) {
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

		update_option( WC_Payments_Features::WCPAY_SUBSCRIPTIONS_FLAG_NAME, $is_wcpay_subscriptions_enabled ? '1' : '0' );
	}

	/**
	 * Updates WooCommerce Payments account fields
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_account( WP_REST_Request $request ) {
		$updated_fields_callback = function ( $value, string $key ) {
			return in_array( $key, static::ACCOUNT_FIELDS_TO_UPDATE, true ) &&
				$this->wcpay_gateway->get_option( $key ) !== $value;
		};
		$updated_fields          = array_filter( $request->get_params(), $updated_fields_callback, ARRAY_FILTER_USE_BOTH );

		$this->wcpay_gateway->update_account_settings( $updated_fields );
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
			'payment_request_button_type'  => 'payment_request_button_type',
			'payment_request_button_size'  => 'payment_request_button_size',
			'payment_request_button_theme' => 'payment_request_button_theme',
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
	 * Updates WooCommerce Payments "saved cards" feature.
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
	 * Updates the "platform checkout" enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_platform_checkout_enabled( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'is_platform_checkout_enabled' ) ) {
			return;
		}

		$is_platform_checkout_enabled = $request->get_param( 'is_platform_checkout_enabled' );

		$this->wcpay_gateway->update_is_platform_checkout_enabled( $is_platform_checkout_enabled );
	}

	/**
	 * Updates the custom message that will appear for platform checkout customers.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_platform_checkout_custom_message( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'platform_checkout_custom_message' ) ) {
			return;
		}

		$platform_checkout_custom_message = $request->get_param( 'platform_checkout_custom_message' );

		$this->wcpay_gateway->update_option( 'platform_checkout_custom_message', $platform_checkout_custom_message );
	}

	/**
	 * Updates the store logo that will appear for platform checkout customers.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_platform_checkout_store_logo( WP_REST_Request $request ) {
		if ( ! $request->has_param( 'platform_checkout_store_logo' ) ) {
			return;
		}

		$platform_checkout_store_logo = $request->get_param( 'platform_checkout_store_logo' );

		$this->wcpay_gateway->update_option( 'platform_checkout_store_logo', $platform_checkout_store_logo );
	}
}

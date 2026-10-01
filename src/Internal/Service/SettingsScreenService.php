<?php
/**
 * Class SettingsScreenService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Payments;
use WC_Payments_Features;
use WC_Payments_Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Registers the WooPayments settings on WooCommerce's experimental payment settings screen.
 *
 * WooCommerce provides the screen, the save flow and the redirect from the classic settings page.
 * This class supplies the settings entity, its fields and its layout.
 */
class SettingsScreenService {
	/**
	 * Screen ID. Also the entity name.
	 */
	const SCREEN_ID = 'woopayments';

	/**
	 * REST route of the settings entity, under `wc/v3`.
	 */
	const REST_ROUTE = '/payments/settings-dataform';

	/**
	 * Script that renders the screen's tabs and provides the existing WooPayments controls.
	 */
	const SCRIPT_HANDLE = 'wcpay-settings-screen';

	/**
	 * Script module that adds the JavaScript parts of the fields.
	 */
	const FIELDS_MODULE_ID = 'woocommerce-payments/settings-screen-fields';

	/**
	 * Express checkout methods with their own customisation sub-page, keyed by the classic `method` argument.
	 * The sub-page ID is also its path on the screen.
	 */
	const EXPRESS_SUBPAGES = [
		'woopay'          => 'woopay',
		'payment_request' => 'apple-pay-google-pay',
		'amazon_pay'      => 'amazon-pay',
	];

	/**
	 * Cards with a description. DataForm card descriptions can only be text, so each description is a
	 * read-only field, rendered with its links by the screen's script and placed first in its card.
	 */
	private const CARD_DESCRIPTIONS = [
		'test-mode'               => 'test_mode_description',
		'fraud-protection'        => 'fraud_protection_description',
		'payouts'                 => 'payouts_description',
		'transactions'            => 'transactions_description',
		'account-notifications'   => 'account_notifications_description',
		'customer-facing-details' => 'customer_facing_details_description',
		'advanced'                => 'advanced_description',
	];

	/**
	 * Registers hooks when the screen is enabled.
	 */
	public function init_hooks(): void {
		if ( ! WC_Payments_Features::is_settings_dataform_enabled() ) {
			return;
		}

		add_filter( 'woocommerce_experimental_payment_settings_screens', [ $this, 'register_screen' ] );
		add_filter( 'woocommerce_experimental_payment_settings_classic_location', [ $this, 'get_classic_location' ], 10, 2 );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'fields_api_init', [ $this, 'register_fields' ] );
		add_filter( 'get_entity_view_config_woo_settings_' . self::SCREEN_ID, [ $this, 'get_view_config' ] );
		add_action( 'init', [ $this, 'register_fields_module' ] );
		// WooCommerce enqueues the screen's scripts at the default priority, so they're registered first.
		add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts' ], 9 );
	}

	/**
	 * Adds the WooPayments screen.
	 *
	 * @param mixed $screens The registered screens.
	 * @return mixed
	 */
	public function register_screen( $screens ) {
		if ( ! is_array( $screens ) ) {
			return $screens;
		}

		$screens[ self::SCREEN_ID ] = [
			'title'           => __( 'WooPayments settings', 'woocommerce-payments' ),
			'rest_path'       => '/wc/v3' . self::REST_ROUTE,
			'scripts'         => [ self::SCRIPT_HANDLE ],
			'classic_section' => 'woocommerce_payments',
		];
		return $screens;
	}

	/**
	 * Opens old links to an express checkout's customisation page (such as `method=woopay`) on its sub-page.
	 *
	 * @param mixed  $location  The location, with `path` and `args`.
	 * @param string $screen_id The screen ID.
	 * @return mixed
	 */
	public function get_classic_location( $location, $screen_id ) {
		$method = is_array( $location ) ? ( $location['args']['method'] ?? null ) : null;
		if ( self::SCREEN_ID !== $screen_id || ! is_string( $method ) || ! isset( self::EXPRESS_SUBPAGES[ $method ] ) ) {
			return $location;
		}

		unset( $location['args']['method'] );
		$location['path'] = self::EXPRESS_SUBPAGES[ $method ];
		return $location;
	}

	/**
	 * Registers the settings entity route.
	 */
	public function register_routes(): void {
		register_rest_route(
			'wc/v3',
			self::REST_ROUTE,
			[
				'methods'             => [ 'GET', 'POST', 'PUT' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'callback'            => [ $this, 'handle_settings_request' ],
			]
		);
	}

	/**
	 * Only store managers can read or change the settings. The delegated route checks this too.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Reads or saves the settings through the existing settings route, returning the saved record.
	 *
	 * The existing route keeps its validation and side effects. Its save response wraps the record
	 * in another response, so the saved settings are read again for core-data.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_settings_request( WP_REST_Request $request ) {
		if ( 'GET' === $request->get_method() ) {
			return $this->get_settings();
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : $request->get_body_params();

		// The existing route needs the advanced rules with the protection level, but core-data only sends changed values.
		if ( isset( $params['current_protection_level'] ) && ! array_key_exists( 'advanced_fraud_protection_settings', $params ) ) {
			$current = $this->get_settings();
			if ( is_wp_error( $current ) ) {
				return $current;
			}
			$params['advanced_fraud_protection_settings'] = $current->get_data()['advanced_fraud_protection_settings'] ?? [];
		}

		$save = new WP_REST_Request( 'POST', '/wc/v3/payments/settings' );
		$save->set_body_params( $params );
		$response = rest_do_request( $save );
		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $this->get_settings();
	}

	/**
	 * Registers the fields with the Fields API.
	 *
	 * The script module adds the parts PHP can't describe, such as conditions and custom controls.
	 *
	 * @param object $registry The Fields API registry.
	 */
	public function register_fields( $registry ): void {
		if ( ! is_object( $registry ) || ! is_callable( [ $registry, 'register' ] ) ) {
			return;
		}

		$registry->register( 'woocommerce-payments', 'woo_settings', self::SCREEN_ID, $this->get_fields(), self::FIELDS_MODULE_ID );
	}

	/**
	 * Gets the fields of the settings entity.
	 *
	 * @return array[]
	 */
	public function get_fields(): array {
		$toggle = static function ( string $id, string $label, string $description = '' ): array {
			return array_filter(
				[
					'id'          => $id,
					'type'        => 'boolean',
					'label'       => $label,
					'description' => $description,
					'Edit'        => 'toggle',
				]
			);
		};
		$text   = static function ( string $id, string $label, string $type = 'text' ): array {
			return [
				'id'    => $id,
				'type'  => $type,
				'label' => $label,
			];
		};
		$select = static function ( string $id, string $label, array $options ): array {
			return [
				'id'       => $id,
				'type'     => 'text',
				'label'    => $label,
				'Edit'     => 'select',
				'elements' => array_map(
					static fn( $value, $option_label ) => [
						'value' => $value,
						'label' => $option_label,
					],
					array_keys( $options ),
					$options
				),
			];
		};

		$intervals = [
			'daily'   => __( 'Daily', 'woocommerce-payments' ),
			'weekly'  => __( 'Weekly', 'woocommerce-payments' ),
			'monthly' => __( 'Monthly', 'woocommerce-payments' ),
		];
		// Japanese accounts can't have daily payouts.
		if ( 'JP' === $this->get_account_country() ) {
			unset( $intervals['daily'] );
		}

		$descriptions = array_map(
			static fn( string $id ): array => [
				'id'       => $id,
				'type'     => 'text',
				'label'    => __( 'Description', 'woocommerce-payments' ),
				'readOnly' => true,
			],
			[ ...array_values( self::CARD_DESCRIPTIONS ), 'payout_bank_account' ]
		);

		return array_merge(
			$descriptions,
			[
				$toggle(
					'is_test_mode_enabled',
					__( 'Enable test mode', 'woocommerce-payments' ),
					__( 'Use test transactions instead of real payments.', 'woocommerce-payments' )
				),
				$text( 'account_communications_email', __( 'Email address', 'woocommerce-payments' ), 'email' ),
				array_merge(
					$select(
						'current_protection_level',
						__( 'Set your payment risk level', 'woocommerce-payments' ),
						[
							'basic'    => __( 'Basic', 'woocommerce-payments' ),
							'advanced' => __( 'Advanced', 'woocommerce-payments' ),
						]
					),
					[ 'Edit' => 'radio' ]
				),
				[
					'id'    => 'enabled_payment_method_ids',
					'label' => __( 'Payment methods', 'woocommerce-payments' ),
				],
				[
					'id'    => 'is_payment_request_enabled',
					'label' => __( 'Express checkouts', 'woocommerce-payments' ),
				],
				$toggle( 'is_saved_cards_enabled', __( 'Enable payments via saved cards', 'woocommerce-payments' ) ),
				$toggle(
					'is_manual_capture_enabled',
					__( 'Issue an authorization on checkout, and capture later', 'woocommerce-payments' ),
					__( 'Charge must be captured on the order details screen within 7 days of authorization, otherwise the authorization and order will be canceled.', 'woocommerce-payments' )
				),
				$text( 'account_statement_descriptor', __( 'Customer bank statement', 'woocommerce-payments' ) ),
				$text( 'account_statement_descriptor_kanji', __( 'Full bank statement (Kanji)', 'woocommerce-payments' ) ),
				$text( 'account_statement_descriptor_kana', __( 'Full bank statement (Kana)', 'woocommerce-payments' ) ),
				$text( 'account_business_support_email', __( 'Support email', 'woocommerce-payments' ), 'email' ),
				$text( 'account_business_support_phone', __( 'Support phone number', 'woocommerce-payments' ) ),
				$select( 'deposit_schedule_interval', __( 'Frequency', 'woocommerce-payments' ), $intervals ),
				$select(
					'deposit_schedule_weekly_anchor',
					__( 'Day', 'woocommerce-payments' ),
					[
						'monday'    => __( 'Monday', 'woocommerce-payments' ),
						'tuesday'   => __( 'Tuesday', 'woocommerce-payments' ),
						'wednesday' => __( 'Wednesday', 'woocommerce-payments' ),
						'thursday'  => __( 'Thursday', 'woocommerce-payments' ),
						'friday'    => __( 'Friday', 'woocommerce-payments' ),
					]
				),
				[
					'id'    => 'deposit_schedule_monthly_anchor',
					'type'  => 'integer',
					'label' => __( 'Date', 'woocommerce-payments' ),
					'Edit'  => 'select',
				],
				$toggle( 'is_multi_currency_enabled', __( 'Enable customer multi-currency', 'woocommerce-payments' ) ),
				$toggle( 'is_stripe_billing_enabled', __( 'Enable Stripe Billing for future subscriptions', 'woocommerce-payments' ) ),
				$toggle( 'is_debug_log_enabled', __( 'Log error messages', 'woocommerce-payments' ) ),
			],
			$this->get_express_checkout_fields( $toggle, $text, $select )
		);
	}

	/**
	 * Lays the fields out as one page of cards, in the order of the designs.
	 *
	 * @param mixed $config The View Config container.
	 * @return mixed
	 */
	public function get_view_config( $config ) {
		if ( ! is_object( $config ) || ! is_callable( [ $config, 'merge' ] ) ) {
			return $config;
		}

		// The payment methods control shows the express checkouts too, in tabs inside its card.
		$payment_methods = [
			'id'     => 'enabled_payment_method_ids',
			'layout' => [
				'type'          => 'regular',
				'labelPosition' => 'none',
			],
		];

		// Frequency and day sit side by side.
		$payout_schedule     = [
			'id'       => 'payout-schedule',
			'layout'   => [ 'type' => 'row' ],
			'children' => [ 'deposit_schedule_interval', 'deposit_schedule_weekly_anchor', 'deposit_schedule_monthly_anchor' ],
		];
		$payout_bank_account = [
			'id'     => 'payout_bank_account',
			'layout' => [
				'type'          => 'regular',
				'labelPosition' => 'none',
			],
		];

		$cards = [
			'test-mode'               => [ __( 'Test mode', 'woocommerce-payments' ), [ 'is_test_mode_enabled' ] ],
			'fraud-protection'        => [ __( 'Fraud protection', 'woocommerce-payments' ), [ 'current_protection_level' ] ],
			'payouts'                 => [ __( 'Payout schedule', 'woocommerce-payments' ), [ $payout_schedule, $payout_bank_account ] ],
			'payment-methods'         => [ __( 'Payment methods', 'woocommerce-payments' ), array_merge( [ $payment_methods ], $this->get_express_subpages() ) ],
			'transactions'            => [ __( 'Transaction preferences', 'woocommerce-payments' ), [ 'is_saved_cards_enabled', 'is_manual_capture_enabled' ] ],
			'account-notifications'   => [ __( 'Account notifications', 'woocommerce-payments' ), [ 'account_communications_email' ] ],
			'customer-facing-details' => [ __( 'Customer-facing details', 'woocommerce-payments' ), [ 'account_statement_descriptor', 'account_statement_descriptor_kanji', 'account_statement_descriptor_kana', 'account_business_support_email', 'account_business_support_phone' ] ],
			'advanced'                => [ __( 'Advanced settings', 'woocommerce-payments' ), [ 'is_multi_currency_enabled', 'is_stripe_billing_enabled' ] ],
			'debug'                   => [ __( 'Debug mode', 'woocommerce-payments' ), [ 'is_debug_log_enabled' ] ],
		];

		$fields = [];
		foreach ( $cards as $id => [ $label, $children ] ) {
			if ( isset( self::CARD_DESCRIPTIONS[ $id ] ) ) {
				array_unshift(
					$children,
					[
						'id'     => self::CARD_DESCRIPTIONS[ $id ],
						'layout' => [
							'type'          => 'regular',
							'labelPosition' => 'none',
						],
					]
				);
			}
			$fields[] = [
				'id'       => $id,
				'label'    => $label,
				'layout'   => [
					'type'          => 'card',
					'isCollapsible' => false,
				],
				'children' => $children,
			];
		}

		return $config->merge(
			[
				'form' => [
					'layout' => [
						'type'          => 'regular',
						'labelPosition' => 'top',
					],
					'fields' => $fields,
				],
			],
			1
		);
	}

	/**
	 * Registers the script module with the fields' JavaScript parts.
	 */
	public function register_fields_module(): void {
		if ( ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		wp_register_script_module(
			self::FIELDS_MODULE_ID,
			plugins_url( 'assets/js/settings-screen-fields.js', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/js/settings-screen-fields.js' )
		);
	}

	/**
	 * Registers the screen's script and styles on the payment settings screen.
	 */
	public function register_scripts(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checks which page is loading.
		if ( ! isset( $_GET['page'] ) || 'wc-payment-settings-wp-admin' !== $_GET['page'] || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		WC_Payments::register_script_with_dependencies( self::SCRIPT_HANDLE, 'dist/settings-screen' );
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'wcpaySettingsScreenConfig',
			[
				'fraudRulesUrl' => admin_url( 'admin.php?page=wc-admin&path=/payments/fraud-protection' ),
			]
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'woocommerce-payments' );
		WC_Payments_Utils::register_style(
			self::SCRIPT_HANDLE,
			plugins_url( 'dist/settings-screen.css', WCPAY_PLUGIN_FILE ),
			[ 'WCPAY_ADMIN_SETTINGS' ],
			WC_Payments::get_file_version( 'dist/settings-screen.css' ),
			'all'
		);
		wp_enqueue_style( self::SCRIPT_HANDLE );

		/**
		 * Adds the account data and styles the existing settings controls need.
		 *
		 * @since 11.2.0
		 *
		 * @param string $handle The screen's script handle.
		 */
		do_action( 'wcpay_settings_screen_register_scripts', self::SCRIPT_HANDLE );
	}

	/**
	 * Gets the fields on the express checkout customisation sub-pages.
	 *
	 * Each method's placement is stored as the method's ID in the express checkout location lists, so the
	 * script module maps the `{method}_on_{location}` toggles to those lists. The button style is shared by
	 * all express checkout buttons, as on the classic page.
	 *
	 * @param callable $toggle Builds a toggle field.
	 * @param callable $text   Builds a text field.
	 * @param callable $select Builds a select field.
	 * @return array[]
	 */
	private function get_express_checkout_fields( callable $toggle, callable $text, callable $select ): array {
		$locations = [
			'product'  => __( 'Show on product page', 'woocommerce-payments' ),
			'cart'     => __( 'Show on cart page', 'woocommerce-payments' ),
			'checkout' => __( 'Show on checkout page', 'woocommerce-payments' ),
		];

		$fields = [];
		foreach ( array_keys( self::EXPRESS_SUBPAGES ) as $method ) {
			foreach ( $locations as $location => $label ) {
				$fields[] = $toggle( "{$method}_on_{$location}", $label );
			}
		}

		$fields[] = $text( 'woopay_custom_message', __( 'Custom message', 'woocommerce-payments' ) );
		$fields[] = $select( 'payment_request_button_type', __( 'Call to action', 'woocommerce-payments' ), $this->get_form_field_options( 'payment_request_button_type' ) );
		$fields[] = $select( 'payment_request_button_size', __( 'Button size', 'woocommerce-payments' ), $this->get_form_field_options( 'payment_request_button_size' ) );
		$fields[] = $select( 'payment_request_button_theme', __( 'Theme', 'woocommerce-payments' ), $this->get_form_field_options( 'payment_request_button_theme' ) );
		$fields[] = [
			'id'    => 'payment_request_button_border_radius',
			'type'  => 'integer',
			'label' => __( 'Border radius', 'woocommerce-payments' ),
		];

		return $fields;
	}

	/**
	 * Gets the options of one of the gateway's form fields, such as the express checkout button types.
	 *
	 * @param string $key The form field key.
	 * @return array<string, string>
	 */
	private function get_form_field_options( string $key ): array {
		$gateway = WC_Payments::get_gateway();
		$options = $gateway ? ( $gateway->form_fields[ $key ]['options'] ?? [] ) : [];
		return is_array( $options ) ? $options : [];
	}

	/**
	 * Gets the express checkout customisation sub-pages. Each method's Customize link opens its sub-page,
	 * so WooCommerce doesn't add a button for it.
	 *
	 * @return array[]
	 */
	private function get_express_subpages(): array {
		$card         = static fn( string $id, string $label, array $children ): array => [
			'id'       => $id,
			'label'    => $label,
			'layout'   => [
				'type'          => 'card',
				'isCollapsible' => false,
			],
			'children' => $children,
		];
		$subpage      = static fn( string $id, string $label, array $children ): array => [
			'id'       => $id,
			'label'    => $label,
			'layout'   => [
				'type'   => 'panel',
				'openAs' => [
					'type'   => 'page',
					'button' => false,
				],
			],
			'children' => $children,
		];
		$placement    = static fn( string $method ): array => [ "{$method}_on_product", "{$method}_on_cart", "{$method}_on_checkout" ];
		$button_style = [ 'payment_request_button_type', 'payment_request_button_size', 'payment_request_button_theme', 'payment_request_button_border_radius' ];

		return [
			$subpage(
				self::EXPRESS_SUBPAGES['woopay'],
				__( 'WooPay', 'woocommerce-payments' ),
				[
					$card( 'woopay-placement', __( 'Button placement', 'woocommerce-payments' ), $placement( 'woopay' ) ),
					$card( 'woopay-appearance', __( 'Checkout appearance', 'woocommerce-payments' ), [ 'woopay_custom_message' ] ),
					$card( 'woopay-button-style', __( 'Button style', 'woocommerce-payments' ), $button_style ),
				]
			),
			$subpage(
				self::EXPRESS_SUBPAGES['payment_request'],
				__( 'Apple Pay / Google Pay', 'woocommerce-payments' ),
				[
					$card( 'payment-request-placement', __( 'Button placement', 'woocommerce-payments' ), $placement( 'payment_request' ) ),
					$card( 'payment-request-button-style', __( 'Button style', 'woocommerce-payments' ), $button_style ),
				]
			),
			$subpage(
				self::EXPRESS_SUBPAGES['amazon_pay'],
				__( 'Amazon Pay', 'woocommerce-payments' ),
				[
					$card( 'amazon-pay-placement', __( 'Button placement', 'woocommerce-payments' ), $placement( 'amazon_pay' ) ),
				]
			),
		];
	}

	/**
	 * Reads the current settings through the existing settings route.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private function get_settings() {
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wc/v3/payments/settings' ) );
		return $response->is_error() ? $response->as_error() : $response;
	}

	/**
	 * Gets the country of the connected account.
	 *
	 * @return string
	 */
	private function get_account_country(): string {
		$gateway = WC_Payments::get_gateway();
		return $gateway ? (string) $gateway->get_option( 'account_country' ) : '';
	}
}

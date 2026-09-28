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
	 * Registers hooks when the screen is enabled.
	 */
	public function init_hooks(): void {
		if ( ! WC_Payments_Features::is_settings_dataform_enabled() ) {
			return;
		}

		add_filter( 'woocommerce_experimental_payment_settings_screens', [ $this, 'register_screen' ] );
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
			'title'           => __( 'WooPayments', 'woocommerce-payments' ),
			'rest_path'       => '/wc/v3' . self::REST_ROUTE,
			'scripts'         => [ self::SCRIPT_HANDLE ],
			'classic_section' => 'woocommerce_payments',
		];
		return $screens;
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

		return [
			$toggle( 'is_wcpay_enabled', __( 'Enable WooPayments', 'woocommerce-payments' ) ),
			$toggle(
				'is_test_mode_enabled',
				__( 'Enable test mode', 'woocommerce-payments' ),
				__( 'Use test transactions instead of real payments.', 'woocommerce-payments' )
			),
			$text( 'account_communications_email', __( 'Account email', 'woocommerce-payments' ), 'email' ),
			$select(
				'current_protection_level',
				__( 'Fraud protection level', 'woocommerce-payments' ),
				[
					'basic'    => __( 'Basic', 'woocommerce-payments' ),
					'advanced' => __( 'Advanced', 'woocommerce-payments' ),
				]
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
			$text( 'account_statement_descriptor', __( 'Full bank statement', 'woocommerce-payments' ) ),
			$text( 'account_statement_descriptor_kanji', __( 'Full bank statement (Kanji)', 'woocommerce-payments' ) ),
			$text( 'account_statement_descriptor_kana', __( 'Full bank statement (Kana)', 'woocommerce-payments' ) ),
			$text( 'account_business_support_email', __( 'Support email', 'woocommerce-payments' ), 'email' ),
			$text( 'account_business_support_phone', __( 'Support phone number', 'woocommerce-payments' ) ),
			$select( 'deposit_schedule_interval', __( 'Payout frequency', 'woocommerce-payments' ), $intervals ),
			$select(
				'deposit_schedule_weekly_anchor',
				__( 'Payout day', 'woocommerce-payments' ),
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
				'label' => __( 'Payout date', 'woocommerce-payments' ),
				'Edit'  => 'select',
			],
			$toggle( 'is_multi_currency_enabled', __( 'Enable customer multi-currency', 'woocommerce-payments' ) ),
			$toggle( 'is_stripe_billing_enabled', __( 'Enable Stripe Billing for future subscriptions', 'woocommerce-payments' ) ),
			$toggle( 'is_debug_log_enabled', __( 'Log error messages', 'woocommerce-payments' ) ),
		];
	}

	/**
	 * Groups the fields into cards. The screen's tabs show a set of these cards each.
	 *
	 * @param mixed $config The View Config container.
	 * @return mixed
	 */
	public function get_view_config( $config ) {
		if ( ! is_object( $config ) || ! is_callable( [ $config, 'merge' ] ) ) {
			return $config;
		}

		$cards = [
			'general'               => [ __( 'General', 'woocommerce-payments' ), [ 'is_wcpay_enabled', 'is_test_mode_enabled' ] ],
			'fraud-protection'      => [ __( 'Fraud protection', 'woocommerce-payments' ), [ 'current_protection_level' ] ],
			'account-notifications' => [ __( 'Account notifications', 'woocommerce-payments' ), [ 'account_communications_email' ] ],
			'payment-methods'       => [ __( 'Payment methods', 'woocommerce-payments' ), [ 'enabled_payment_method_ids' ] ],
			'express-checkouts'     => [ __( 'Express checkouts', 'woocommerce-payments' ), [ 'is_payment_request_enabled' ] ],
			'transactions'          => [ __( 'Transactions', 'woocommerce-payments' ), [ 'is_saved_cards_enabled', 'is_manual_capture_enabled' ] ],
			'bank-statement'        => [ __( 'Customer bank statement', 'woocommerce-payments' ), [ 'account_statement_descriptor', 'account_statement_descriptor_kanji', 'account_statement_descriptor_kana' ] ],
			'customer-support'      => [ __( 'Customer support', 'woocommerce-payments' ), [ 'account_business_support_email', 'account_business_support_phone' ] ],
			'payouts'               => [ __( 'Payout schedule', 'woocommerce-payments' ), [ 'deposit_schedule_interval', 'deposit_schedule_weekly_anchor', 'deposit_schedule_monthly_anchor' ] ],
			'advanced'              => [ __( 'Advanced settings', 'woocommerce-payments' ), [ 'is_multi_currency_enabled', 'is_stripe_billing_enabled', 'is_debug_log_enabled' ] ],
		];

		$fields = [];
		foreach ( $cards as $id => [ $label, $children ] ) {
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

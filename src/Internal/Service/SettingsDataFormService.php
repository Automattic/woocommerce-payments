<?php
/**
 * Experimental integration with the WooCommerce Settings DataForm POC.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/**
 * Supplies the layout and REST bridge for the settings entity.
 */
class SettingsDataFormService {
	/** Register the opt-in experiment. */
	public function init_hooks(): void {
		if ( ! defined( 'WCPAY_SETTINGS_DATAFORM_POC' ) || ! WCPAY_SETTINGS_DATAFORM_POC ) {
			return;
		}
		add_filter( 'get_entity_view_config_woo_settings_woopayments', [ $this, 'get_view_config' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'admin_menu', [ $this, 'add_menu_link' ], 100 );
		add_action( 'wc-settings-dataform_init', [ $this, 'enqueue' ] );
		add_action( 'wc-settings-dataform-wp-admin_init', [ $this, 'enqueue' ] );
	}

	/** Link to the shared POC without replacing the existing settings entry. */
	public function add_menu_link(): void {
		if ( ! function_exists( 'woocommerce_settings_ui_experimental_wc_settings_dataform_wp_admin_render_page' ) ) {
			return;
		}
		add_submenu_page(
			'woocommerce',
			__( 'WooPayments', 'woocommerce-payments' ),
			__( 'WooPayments (DataForm POC)', 'woocommerce-payments' ),
			'manage_woocommerce',
			add_query_arg(
				[
					'page' => 'wc-settings-dataform-wp-admin',
					'p'    => '/settings/woopayments',
				],
				admin_url( 'admin.php' )
			)
		);
	}

	/** Load registration before the POC route starts. */
	public function enqueue(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		\WC_Payments::register_script_with_dependencies( 'wcpay-settings-dataform', 'dist/settings-dataform' );
		/**
		 * Load the account context and styles used by existing WooPayments controls.
		 *
		 * @since 11.1.0
		 */
		do_action( 'wcpay_settings_dataform_enqueue' );
		wp_localize_script(
			'wcpay-settings-dataform',
			'wcpaySettingsDataform',
			[
				'fraudRulesUrl' => admin_url( 'admin.php?page=wc-admin&path=/payments/fraud-protection' ),
				'classicUrl'    => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' ),
			]
		);
		wp_enqueue_script( 'wcpay-settings-dataform' );
		\WC_Payments_Utils::register_style( 'wcpay-settings-dataform', plugins_url( 'dist/settings-dataform.css', WCPAY_PLUGIN_FILE ), [ 'WCPAY_ADMIN_SETTINGS' ], \WC_Payments::get_file_version( 'dist/settings-dataform.css' ), 'all' );
		wp_enqueue_style( 'wcpay-settings-dataform' );
		wp_set_script_translations( 'wcpay-settings-dataform', 'woocommerce-payments' );
	}

	/** Register a flat-record facade over the existing settings controller. */
	public function register_routes(): void {
		register_rest_route(
			'wc/v3',
			'/payments/settings-dataform',
			[
				'methods'             => [ 'GET', 'POST' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'callback'            => [ $this, 'settings' ],
			]
		);
	}

	/**
	 * Require the settings capability; the delegated route also checks access.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Use existing validation and side effects, then return saved entity values.
	 *
	 * @param \WP_REST_Request $request Request from core-data.
	 * @return \WP_REST_Response
	 */
	public function settings( $request ) {
		$forward = new \WP_REST_Request( $request->get_method(), '/wc/v3/payments/settings' );
		$params  = $request->get_params();
		if ( 'POST' === $request->get_method() && isset( $params['current_protection_level'] ) && ! array_key_exists( 'advanced_fraud_protection_settings', $params ) ) {
			$current = rest_do_request( new \WP_REST_Request( 'GET', '/wc/v3/payments/settings' ) );
			if ( $current->is_error() ) {
				return $current;
			}
			// The existing controller requires both values; core-data sends only changed properties.
			$params['advanced_fraud_protection_settings'] = $current->get_data()['advanced_fraud_protection_settings'];
		}
		$forward->set_body_params( $params );
		$response = rest_do_request( $forward );
		if ( $response->is_error() || 'GET' === $request->get_method() ) {
			return $response;
		}
		// The existing POST response wraps a WP_REST_Response; core-data needs the flat record.
		return rest_do_request( new \WP_REST_Request( 'GET', '/wc/v3/payments/settings' ) );
	}

	/**
	 * Keep the main settings groups in their existing order.
	 *
	 * @param mixed $config WordPress View Config container.
	 * @return mixed
	 */
	public function get_view_config( $config ) {
		if ( ! is_object( $config ) || ! is_callable( [ $config, 'merge' ] ) ) {
			return $config;
		}
		$groups = [
			'general'               => [ __( 'General', 'woocommerce-payments' ), [ 'is_wcpay_enabled', 'is_test_mode_enabled' ] ],
			'payment-methods'       => [ __( 'Payment methods', 'woocommerce-payments' ), [ 'enabled_payment_method_ids' ] ],
			'express-checkouts'     => [ __( 'Express checkouts', 'woocommerce-payments' ), [ 'is_payment_request_enabled' ] ],
			'transactions'          => [ __( 'Transactions', 'woocommerce-payments' ), [ 'is_saved_cards_enabled', 'is_manual_capture_enabled', 'account_statement_descriptor', 'account_statement_descriptor_kanji', 'account_statement_descriptor_kana', 'account_business_support_email', 'account_business_support_phone' ] ],
			'deposits'              => [ __( 'Payouts', 'woocommerce-payments' ), [ 'deposit_schedule_interval' ] ],
			'notification-settings' => [ __( 'Account notifications', 'woocommerce-payments' ), [ 'account_communications_email' ] ],
			'fp-settings'           => [ __( 'Fraud protection', 'woocommerce-payments' ), [ 'current_protection_level' ] ],
			'advanced-settings'     => [ __( 'Advanced settings', 'woocommerce-payments' ), [ 'is_multi_currency_enabled', 'is_debug_log_enabled' ] ],
		];
		$fields = [];
		foreach ( $groups as $id => [ $label, $children ] ) {
			if ( in_array( $id, [ 'payment-methods', 'express-checkouts' ], true ) ) {
				$fields = array_merge( $fields, $children );
				continue;
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
}

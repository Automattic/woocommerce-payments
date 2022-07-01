<?php
/**
 * Set up top-level menus for WCPay.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\Jetpack\Identity_Crisis as Jetpack_Identity_Crisis;
use WCPay\Database_Cache;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Admin Class.
 */
class WC_Payments_Admin {

	/**
	 * Badge with number "1" displayed next to a menu item when there is something important to communicate on a page.
	 *
	 * @var string
	 */
	const MENU_NOTIFICATION_BADGE = ' <span class="wcpay-menu-badge awaiting-mod count-1"><span class="plugin-count">1</span></span>';

	/**
	 * Dispute notification badge HTML format (with placeholder for the number of disputes).
	 *
	 * @var string
	 */
	const DISPUTE_NOTIFICATION_BADGE_FORMAT = ' <span class="wcpay-menu-badge awaiting-mod count-%1$s"><span class="plugin-count">%1$d</span></span>';

	/**
	 * WC Payments WordPress Admin menu slug.
	 *
	 * @var string
	 */
	const PAYMENTS_SUBMENU_SLUG = 'wc-admin&path=/payments/overview';

	/**
	 * Client for making requests to the WooCommerce Payments API.
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * WCPay Gateway instance to get information regarding WooCommerce Payments setup.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WCPay admin child pages.
	 *
	 * @var array
	 */
	private $admin_child_pages;

	/**
	 * Database_Cache instance.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Hook in admin menu items.
	 *
	 * @param WC_Payments_API_Client   $payments_api_client WooCommerce Payments API client.
	 * @param WC_Payment_Gateway_WCPay $gateway             WCPay Gateway instance to get information regarding WooCommerce Payments setup.
	 * @param WC_Payments_Account      $account             Account instance.
	 * @param Database_Cache           $database_cache      Database Cache instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payment_Gateway_WCPay $gateway,
		WC_Payments_Account $account,
		Database_Cache $database_cache
	) {
		$this->payments_api_client = $payments_api_client;
		$this->wcpay_gateway       = $gateway;
		$this->account             = $account;
		$this->database_cache      = $database_cache;

		// Add menu items.
		add_action( 'admin_menu', [ $this, 'add_payments_menu' ], 0 );
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_onboarding' ], 11 ); // Run this after the WC setup wizard and onboarding redirection logic.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_payments_scripts' ] );
		add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'payment_gateways_container' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'show_woopay_payment_method_name_admin' ] );

		$this->admin_child_pages = [
			'wc-payments-overview'     => [
				'id'       => 'wc-payments-overview',
				'title'    => __( 'Overview', 'woocommerce-payments' ),
				'parent'   => 'wc-payments',
				'path'     => '/payments/overview',
				'nav_args' => [
					'parent' => 'wc-payments',
					'order'  => 10,
				],
			],
			'wc-payments-deposits'     => [
				'id'       => 'wc-payments-deposits',
				'title'    => __( 'Deposits', 'woocommerce-payments' ),
				'parent'   => 'wc-payments',
				'path'     => '/payments/deposits',
				'nav_args' => [
					'parent' => 'wc-payments',
					'order'  => 20,
				],
			],
			'wc-payments-transactions' => [
				'id'       => 'wc-payments-transactions',
				'title'    => __( 'Transactions', 'woocommerce-payments' ),
				'parent'   => 'wc-payments',
				'path'     => '/payments/transactions',
				'nav_args' => [
					'parent' => 'wc-payments',
					'order'  => 30,
				],
			],
			'wc-payments-disputes'     => [
				'id'       => 'wc-payments-disputes',
				'title'    => __( 'Disputes', 'woocommerce-payments' ),
				'parent'   => 'wc-payments',
				'path'     => '/payments/disputes',
				'nav_args' => [
					'parent' => 'wc-payments',
					'order'  => 40,
				],
			],
		];
	}

	/**
	 * Add deposits and transactions menus for wcpay_empty_state_preview_mode_v1 experiment's treatment group.
	 * This code can be removed once we're done with the experiment.
	 */
	public function add_payments_menu_for_treatment() {
		wc_admin_register_page(
			[
				'id'         => 'wc-payments',
				'title'      => __( 'Payments', 'woocommerce-payments' ),
				'capability' => 'manage_woocommerce',
				'path'       => '/payments/deposits',
				'position'   => '55.7', // After WooCommerce & Product menu items.
				'nav_args'   => [
					'title'        => __( 'WooCommerce Payments', 'woocommerce-payments' ),
					'is_category'  => true,
					'menuId'       => 'plugins',
					'is_top_level' => true,
				],
			]
		);

		wc_admin_register_page( $this->admin_child_pages['wc-payments-deposits'] );
		wc_admin_register_page( $this->admin_child_pages['wc-payments-transactions'] );
		wc_admin_register_page(
			[
				'id'       => 'wc-payments-connect',
				'title'    => __( 'Connect', 'woocommerce-payments' ),
				'parent'   => 'wc-payments',
				'path'     => '/payments/connect',
				'nav_args' => [
					'parent' => 'wc-payments',
					'order'  => 10,
				],
			]
		);
		wp_enqueue_style(
			'wcpay-admin-css',
			plugins_url( 'assets/css/admin.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/css/admin.css' )
		);

		$this->add_menu_notification_badge();
		$this->add_update_business_details_task();
	}

	/**
	 * Add payments menu items.
	 */
	public function add_payments_menu() {
		global $submenu;

		try {
			$should_render_full_menu = $this->account->try_is_stripe_connected();
		} catch ( Exception $e ) {
			// There is an issue with connection but render full menu anyways to provide access to settings.
			$should_render_full_menu = true;
		}

		// When the account is not connected, see if the user is in an A/B test treatment mode.
		if ( false === $should_render_full_menu && $this->is_in_treatment_mode() ) {
			$this->add_payments_menu_for_treatment();
			return;
		}

		$top_level_link = $should_render_full_menu ? '/payments/overview' : '/payments/connect';

		$menu_icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjxzdmcKICAgdmVyc2lvbj0iMS4xIgogICBpZD0ic3ZnNjciCiAgIHNvZGlwb2RpOmRvY25hbWU9IndjcGF5X21lbnVfaWNvbi5zdmciCiAgIHdpZHRoPSI4NTIiCiAgIGhlaWdodD0iNjg0IgogICBpbmtzY2FwZTp2ZXJzaW9uPSIxLjEgKGM0ZThmOWUsIDIwMjEtMDUtMjQpIgogICB4bWxuczppbmtzY2FwZT0iaHR0cDovL3d3dy5pbmtzY2FwZS5vcmcvbmFtZXNwYWNlcy9pbmtzY2FwZSIKICAgeG1sbnM6c29kaXBvZGk9Imh0dHA6Ly9zb2RpcG9kaS5zb3VyY2Vmb3JnZS5uZXQvRFREL3NvZGlwb2RpLTAuZHRkIgogICB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiAgIHhtbG5zOnN2Zz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgogIDxkZWZzCiAgICAgaWQ9ImRlZnM3MSIgLz4KICA8c29kaXBvZGk6bmFtZWR2aWV3CiAgICAgaWQ9Im5hbWVkdmlldzY5IgogICAgIHBhZ2Vjb2xvcj0iI2ZmZmZmZiIKICAgICBib3JkZXJjb2xvcj0iIzY2NjY2NiIKICAgICBib3JkZXJvcGFjaXR5PSIxLjAiCiAgICAgaW5rc2NhcGU6cGFnZXNoYWRvdz0iMiIKICAgICBpbmtzY2FwZTpwYWdlb3BhY2l0eT0iMC4wIgogICAgIGlua3NjYXBlOnBhZ2VjaGVja2VyYm9hcmQ9IjAiCiAgICAgc2hvd2dyaWQ9ImZhbHNlIgogICAgIGZpdC1tYXJnaW4tdG9wPSIwIgogICAgIGZpdC1tYXJnaW4tbGVmdD0iMCIKICAgICBmaXQtbWFyZ2luLXJpZ2h0PSIwIgogICAgIGZpdC1tYXJnaW4tYm90dG9tPSIwIgogICAgIGlua3NjYXBlOnpvb209IjI1NiIKICAgICBpbmtzY2FwZTpjeD0iLTg0Ljg1NzQyMiIKICAgICBpbmtzY2FwZTpjeT0iLTgzLjI5NDkyMiIKICAgICBpbmtzY2FwZTp3aW5kb3ctd2lkdGg9IjEzMTIiCiAgICAgaW5rc2NhcGU6d2luZG93LWhlaWdodD0iMTA4MSIKICAgICBpbmtzY2FwZTp3aW5kb3cteD0iMTE2IgogICAgIGlua3NjYXBlOndpbmRvdy15PSIyMDIiCiAgICAgaW5rc2NhcGU6d2luZG93LW1heGltaXplZD0iMCIKICAgICBpbmtzY2FwZTpjdXJyZW50LWxheWVyPSJzdmc2NyIgLz4KICA8cGF0aAogICAgIHRyYW5zZm9ybT0ic2NhbGUoLTEsIDEpIHRyYW5zbGF0ZSgtODUwLCAwKSIKICAgICBkPSJNIDc2OCw4NiBWIDU5OCBIIDg0IFYgODYgWiBtIDAsNTk4IGMgNDgsMCA4NCwtMzggODQsLTg2IFYgODYgQyA4NTIsMzggODE2LDAgNzY4LDAgSCA4NCBDIDM2LDAgMCwzOCAwLDg2IHYgNTEyIGMgMCw0OCAzNiw4NiA4NCw4NiB6IE0gMzg0LDEyOCB2IDQ0IGggLTg2IHYgODQgaCAxNzAgdiA0NCBIIDM0MCBjIC0yNCwwIC00MiwxOCAtNDIsNDIgdiAxMjggYyAwLDI0IDE4LDQyIDQyLDQyIGggNDQgdiA0NCBoIDg0IHYgLTQ0IGggODYgViA0MjggSCAzODQgdiAtNDQgaCAxMjggYyAyNCwwIDQyLC0xOCA0MiwtNDIgViAyMTQgYyAwLC0yNCAtMTgsLTQyIC00MiwtNDIgaCAtNDQgdiAtNDQgeiIKICAgICBmaWxsPSIjYTJhYWIyIgogICAgIGlkPSJwYXRoNjUiIC8+Cjwvc3ZnPgo=';

		wc_admin_register_page(
			[
				'id'         => 'wc-payments',
				'title'      => __( 'Payments', 'woocommerce-payments' ),
				'capability' => 'manage_woocommerce',
				'path'       => $top_level_link,
				'position'   => '55.7', // After WooCommerce & Product menu items.
				'icon'       => $menu_icon,
				'nav_args'   => [
					'title'        => __( 'WooCommerce Payments', 'woocommerce-payments' ),
					'is_category'  => $should_render_full_menu,
					'menuId'       => 'plugins',
					'is_top_level' => true,
				],
			]
		);

		if ( $this->account->is_account_rejected() ) {
			// If the account is rejected, only show the overview page.
			wc_admin_register_page( $this->admin_child_pages['wc-payments-overview'] );
			return;
		}

		if ( WC_Payments_Utils::is_in_onboarding_treatment_mode() && ! $should_render_full_menu ) {
				wc_admin_register_page(
					[
						'id'         => 'wc-payments-onboarding',
						'title'      => __( 'Onboarding', 'woocommerce-payments' ),
						'parent'     => 'wc-payments',
						'path'       => '/payments/onboarding',
						'capability' => 'manage_woocommerce',
						'nav_args'   => [
							'parent' => 'wc-payments',
						],
					]
				);
				global $submenu;
				remove_submenu_page( 'wc-admin&path=/payments/connect', 'wc-admin&path=/payments/onboarding' );
		}

		if ( $should_render_full_menu ) {
			if ( $this->account->is_card_present_eligible() && $this->account->has_card_readers_available() ) {
				$this->admin_child_pages['wc-payments-card-readers'] = [
					'id'       => 'wc-payments-card-readers',
					'title'    => __( 'Card Readers', 'woocommerce-payments' ),
					'parent'   => 'wc-payments',
					'path'     => '/payments/card-readers',
					'nav_args' => [
						'parent' => 'wc-payments',
						'order'  => 50,
					],
				];
			}

			if ( $this->account->get_capital()['has_previous_loans'] ) {
				$this->admin_child_pages['wc-payments-capital'] = [
					'id'       => 'wc-payments-capital',
					'title'    => __( 'Capital Loans', 'woocommerce-payments' ),
					'parent'   => 'wc-payments',
					'path'     => '/payments/loans',
					'nav_args' => [
						'parent' => 'wc-payments',
						'order'  => 60,
					],
				];
			}

			if ( WC_Payments_Features::is_documents_section_enabled() ) {
				$this->admin_child_pages['wc-payments-documents'] = [
					'id'       => 'wc-payments-documents',
					'title'    => __( 'Documents', 'woocommerce-payments' ),
					'parent'   => 'wc-payments',
					'path'     => '/payments/documents',
					'nav_args' => [
						'parent' => 'wc-payments',
						'order'  => 50,
					],
				];
			}

			/**
			 * Please note that if any other page is registered first and it's
			 * path is different from the $top_level_link it will make
			 * wc_admin_register_page to duplicate "Payments" menu item as a
			 * first item in the sub-menu.
			 */
			foreach ( $this->admin_child_pages as $admin_child_page ) {
				wc_admin_register_page( $admin_child_page );
			}

			wc_admin_connect_page(
				[
					'id'        => 'woocommerce-settings-payments-woocommerce-payments',
					'parent'    => 'woocommerce-settings-payments',
					'screen_id' => 'woocommerce_page_wc-settings-checkout-woocommerce_payments',
					'title'     => __( 'WooCommerce Payments', 'woocommerce-payments' ),
					'nav_args'  => [
						'parent' => 'wc-payments',
						'title'  => __( 'Settings', 'woocommerce-payments' ),
						'url'    => 'wc-settings&tab=checkout&section=woocommerce_payments',
						'order'  => 99,
					],
				]
			);
			// Add the Settings submenu directly to the array, it's the only way to make it link to an absolute URL.
			$submenu_keys                   = array_keys( $submenu );
			$last_submenu_key               = $submenu_keys[ count( $submenu ) - 1 ];
			$submenu[ $last_submenu_key ][] = [ // PHPCS:Ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$this->get_settings_menu_item_name(),
				'manage_woocommerce',
				WC_Payment_Gateway_WCPay::get_settings_url(),
			];

			// Temporary fix to settings menu disappearance is to register the page after settings menu has been manually added.
			// TODO: More robust solution is to be implemented by https://github.com/Automattic/woocommerce-payments/issues/231.
			wc_admin_register_page(
				[
					'id'     => 'wc-payments-deposit-details',
					'title'  => __( 'Deposit details', 'woocommerce-payments' ),
					'parent' => 'wc-payments-transactions', // Not (top level) deposits, as workaround for showing up as submenu page.
					'path'   => '/payments/deposits/details',
				]
			);
			wc_admin_register_page(
				[
					'id'     => 'wc-payments-transaction-details',
					'title'  => __( 'Payment details', 'woocommerce-payments' ),
					'parent' => 'wc-payments-transactions',
					'path'   => '/payments/transactions/details',
				]
			);
			wc_admin_register_page(
				[
					'id'     => 'wc-payments-disputes-details',
					'title'  => __( 'Dispute details', 'woocommerce-payments' ),
					'parent' => 'wc-payments-disputes',
					'path'   => '/payments/disputes/details',
				]
			);
			wc_admin_register_page(
				[
					'id'     => 'wc-payments-disputes-challenge',
					'title'  => __( 'Challenge dispute', 'woocommerce-payments' ),
					'parent' => 'wc-payments-disputes-details',
					'path'   => '/payments/disputes/challenge',
				]
			);
			wc_admin_register_page(
				[
					'id'     => 'wc-payments-additional-payment-methods',
					'parent' => 'woocommerce-settings-payments',
					'title'  => __( 'Add new payment methods', 'woocommerce-payments' ),
					'path'   => '/payments/additional-payment-methods',
				]
			);
			wc_admin_register_page(
				[
					'id'     => 'wc-payments-multi-currency-setup',
					'parent' => 'woocommerce-settings-payments',
					'title'  => __( 'Set up multiple currencies', 'woocommerce-payments' ),
					'path'   => '/payments/multi-currency-setup',
				]
			);

			wc_admin_register_page(
				[
					'id'     => 'wc-payments-card-readers-preview-receipt',
					'parent' => 'wc-payments-card-readers',
					'title'  => __( 'Preview a printed receipt', 'woocommerce-payments' ),
					'path'   => '/payments/card-readers/preview-receipt',

				]
			);
		}

		wp_enqueue_style(
			'wcpay-admin-css',
			plugins_url( 'assets/css/admin.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/css/admin.css' )
		);

		$this->add_menu_notification_badge();
		$this->add_update_business_details_task();
		$this->add_disputes_notification_badge();
	}

	/**
	 * Register the CSS and JS scripts
	 */
	public function register_payments_scripts() {
		$script_src_url    = plugins_url( 'dist/index.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/index.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];
		wp_register_script(
			'WCPAY_DASH_APP',
			$script_src_url,
			$script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/index.js' ),
			true
		);

		// Has on-boarding been disabled? Set the flag for use in the front-end so messages and notices can be altered
		// as appropriate.
		$on_boarding_disabled = WC_Payments_Account::is_on_boarding_disabled();

		$error_message = get_transient( WC_Payments_Account::ERROR_MESSAGE_TRANSIENT );
		delete_transient( WC_Payments_Account::ERROR_MESSAGE_TRANSIENT );

		/**
		 * This is a work around to pass the current user's email address to WCPay's settings until we do not need to rely
		 * on backwards compatibility and can use `getCurrentUser` from `@wordpress/core-data`.
		 */
		$current_user       = wp_get_current_user();
		$current_user_email = $current_user && $current_user->user_email ? $current_user->user_email : get_option( 'admin_email' );

		if ( version_compare( WC_VERSION, '6.0', '<' ) ) {
			$path = WCPAY_ABSPATH . 'i18n/locale-info.php';
		} else {
			$path = WC()->plugin_path() . '/i18n/locale-info.php';
		}

		$locale_info   = include $path;
		$currency_data = [];

		foreach ( $locale_info as $key => $value ) {
			$currency_data[ $key ] = [
				'code'              => $value['currency_code'] ?? '',
				'symbol'            => $value['short_symbol'] ?? '',
				'symbolPosition'    => $value['currency_pos'] ?? '',
				'thousandSeparator' => $value['thousand_sep'] ?? '',
				'decimalSeparator'  => $value['decimal_sep'] ?? '',
				'precision'         => $value['num_decimals'],
			];
		}

		$wcpay_settings = [
			'connectUrl'              => WC_Payments_Account::get_connect_url(),
			'connect'                 => [
				'country'            => WC()->countries->get_base_country(),
				'availableCountries' => WC_Payments_Utils::supported_countries(),
				'availableStates'    => WC()->countries->get_states(),
			],
			'testMode'                => $this->wcpay_gateway->is_in_test_mode(),
			// set this flag for use in the front-end to alter messages and notices if on-boarding has been disabled.
			'onBoardingDisabled'      => WC_Payments_Account::is_on_boarding_disabled(),
			'errorMessage'            => $error_message,
			'featureFlags'            => $this->get_frontend_feature_flags(),
			'isSubscriptionsActive'   => class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' ),
			// used in the settings page by the AccountFees component.
			'zeroDecimalCurrencies'   => WC_Payments_Utils::zero_decimal_currencies(),
			'fraudServices'           => $this->account->get_fraud_services_config(),
			'isJetpackConnected'      => $this->payments_api_client->is_server_connected(),
			'isJetpackIdcActive'      => Jetpack_Identity_Crisis::has_identity_crisis(),
			'accountStatus'           => $this->account->get_account_status_data(),
			'accountFees'             => $this->account->get_fees(),
			'accountLoans'            => $this->account->get_capital(),
			'accountEmail'            => $this->account->get_account_email(),
			'showUpdateDetailsTask'   => get_option( 'wcpay_show_update_business_details_task', 'no' ),
			'wpcomReconnectUrl'       => $this->payments_api_client->is_server_connected() && ! $this->payments_api_client->has_server_connection_owner() ? WC_Payments_Account::get_wpcom_reconnect_url() : null,
			'additionalMethodsSetup'  => [
				'isUpeEnabled' => WC_Payments_Features::is_upe_enabled(),
			],
			'multiCurrencySetup'      => [
				'isSetupCompleted' => get_option( 'wcpay_multi_currency_setup_completed' ),
			],
			'needsHttpsSetup'         => $this->wcpay_gateway->needs_https_setup(),
			'isMultiCurrencyEnabled'  => WC_Payments_Features::is_customer_multi_currency_enabled(),
			'overviewTasksVisibility' => [
				'dismissedTodoTasks'     => get_option( 'woocommerce_dismissed_todo_tasks', [] ),
				'deletedTodoTasks'       => get_option( 'woocommerce_deleted_todo_tasks', [] ),
				'remindMeLaterTodoTasks' => get_option( 'woocommerce_remind_me_later_todo_tasks', [] ),
			],
			'currentUserEmail'        => $current_user_email,
			'currencyData'            => $currency_data,
			'restUrl'                 => get_rest_url( null, '' ), // rest url to concatenate when merchant use Plain permalinks.
		];

		wp_localize_script(
			'WCPAY_DASH_APP',
			'wcpaySettings',
			$wcpay_settings
		);

		wp_set_script_translations( 'WCPAY_DASH_APP', 'woocommerce-payments' );

		wp_register_style(
			'WCPAY_DASH_APP',
			plugins_url( 'dist/index.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/index.css' )
		);

		$tos_script_src_url    = plugins_url( 'dist/tos.js', WCPAY_PLUGIN_FILE );
		$tos_script_asset_path = WCPAY_ABSPATH . 'dist/tos.asset.php';
		$tos_script_asset      = file_exists( $tos_script_asset_path ) ? require_once $tos_script_asset_path : [ 'dependencies' => [] ];

		wp_register_script(
			'WCPAY_TOS',
			$tos_script_src_url,
			$tos_script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/tos.js' ),
			true
		);
		wp_set_script_translations( 'WCPAY_TOS', 'woocommerce-payments' );

		wp_register_style(
			'WCPAY_TOS',
			plugins_url( 'dist/tos.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/tos.css' )
		);

		wp_register_script(
			'WCPAY_ADMIN_ORDER_ACTIONS',
			plugins_url( 'dist/order.js', WCPAY_PLUGIN_FILE ),
			[ 'jquery-tiptip' ],
			WC_Payments::get_file_version( 'dist/order.js' ),
			true
		);

		$settings_script_src_url    = plugins_url( 'dist/settings.js', WCPAY_PLUGIN_FILE );
		$settings_script_asset_path = WCPAY_ABSPATH . 'dist/settings.asset.php';
		$settings_script_asset      = file_exists( $settings_script_asset_path ) ? require_once $settings_script_asset_path : [ 'dependencies' => [] ];
		wp_register_script(
			'WCPAY_ADMIN_SETTINGS',
			$settings_script_src_url,
			$settings_script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/settings.js' ),
			true
		);

		wp_localize_script(
			'WCPAY_ADMIN_SETTINGS',
			'wcpayPaymentRequestParams',
			[
				'stripe' => [
					'publishableKey' => $this->account->get_publishable_key( $this->wcpay_gateway->is_in_test_mode() ),
					'accountId'      => $this->account->get_stripe_account_id(),
					'locale'         => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
				],
			]
		);

		wp_localize_script(
			'WCPAY_ADMIN_SETTINGS',
			'wcpaySettings',
			$wcpay_settings
		);
		wp_set_script_translations( 'WCPAY_ADMIN_SETTINGS', 'woocommerce-payments' );

		wp_register_style(
			'WCPAY_ADMIN_SETTINGS',
			plugins_url( 'dist/settings.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/settings.css' )
		);

		$payment_gateways_script_src_url    = plugins_url( 'dist/payment-gateways.js', WCPAY_PLUGIN_FILE );
		$payment_gateways_script_asset_path = WCPAY_ABSPATH . 'dist/payment-gateways.asset.php';
		$payment_gateways_script_asset      = file_exists( $payment_gateways_script_asset_path ) ? require_once $payment_gateways_script_asset_path : [ 'dependencies' => [] ];

		wp_register_script(
			'WCPAY_PAYMENT_GATEWAYS_PAGE',
			$payment_gateways_script_src_url,
			$payment_gateways_script_asset['dependencies'],
			WC_Payments::get_file_version( 'dist/payment-gateways.js' ),
			true
		);
		wp_register_style(
			'WCPAY_PAYMENT_GATEWAYS_PAGE',
			plugins_url( 'dist/payment-gateways.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/payment-gateways.css' )
		);
	}

	/**
	 * Load the assets
	 */
	public function enqueue_payments_scripts() {
		global $current_tab, $current_section;

		// TODO: Add check to see if user can manage_woocommerce and exit early if they cannot.
		$this->register_payments_scripts();

		if ( WC_Payments_Utils::is_payments_settings_page() ) {
			// Output the settings JS and CSS only on the settings page.
			wp_enqueue_script( 'WCPAY_ADMIN_SETTINGS' );
			wp_enqueue_style( 'WCPAY_ADMIN_SETTINGS' );
		}

		// TODO: Try to enqueue the JS and CSS bundles lazily (will require changes on WC-Admin).
		if ( wc_admin_is_registered_page() ) {
			wp_enqueue_script( 'WCPAY_DASH_APP' );
			wp_enqueue_style( 'WCPAY_DASH_APP' );
		}

		// TODO: Update conditions when ToS script is enqueued.
		$tos_agreement_declined = (
			$current_tab
			&& 'checkout' === $current_tab
			&& isset( $_GET['tos-disabled'] ) // phpcs:ignore WordPress.Security.NonceVerification
		);

		$tos_agreement_required = (
			$this->is_tos_agreement_required() &&
			(
				WC_Payments_Utils::is_payments_settings_page() ||

				// Or a WC Admin page?
				// Note: Merchants can navigate from analytics to payments w/o reload,
				// which is why this is neccessary.
				wc_admin_is_registered_page()
			)
		);

		$track_stripe_connected = get_option( '_wcpay_onboarding_stripe_connected' );

		if ( $tos_agreement_declined || $tos_agreement_required || $track_stripe_connected ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			wp_localize_script(
				'WCPAY_TOS',
				'wcpay_tos_settings',
				[
					'settingsUrl'          => $this->wcpay_gateway->get_settings_url(),
					'tosAgreementRequired' => $tos_agreement_required,
					'tosAgreementDeclined' => $tos_agreement_declined,
					'trackStripeConnected' => $track_stripe_connected,
				]
			);

			wp_enqueue_script( 'WCPAY_TOS' );
			wp_enqueue_style( 'WCPAY_TOS' );
		}

		$is_payment_methods_page = (
			is_admin() &&
			$current_tab && ! $current_section
			&& 'checkout' === $current_tab
		);

		if ( $is_payment_methods_page ) {
			wp_enqueue_script( 'WCPAY_PAYMENT_GATEWAYS_PAGE' );
			wp_enqueue_style( 'WCPAY_PAYMENT_GATEWAYS_PAGE' );
		}

		$screen = get_current_screen();
		if ( 'shop_order' === $screen->id ) {
			$order = wc_get_order();

			if ( WC_Payment_Gateway_WCPay::GATEWAY_ID === $order->get_payment_method() ) {
				wp_localize_script(
					'WCPAY_ADMIN_ORDER_ACTIONS',
					'wcpay_order_config',
					[
						'disableManualRefunds' => ! $this->wcpay_gateway->has_refund_failed( $order ),
						'manualRefundsTip'     => __( 'Refunding manually requires reimbursing your customer offline via cash, check, etc. The refund amounts entered here will only be used to balance your analytics.', 'woocommerce-payments' ),
					]
				);
				wp_enqueue_script( 'WCPAY_ADMIN_ORDER_ACTIONS' );
			}
		}
	}

	/**
	 * Creates an array of features enabled only when external dependencies are of certain versions.
	 *
	 * @return array An associative array containing the flags as booleans.
	 */
	private function get_frontend_feature_flags() {
		return array_merge(
			[
				'paymentTimeline' => self::version_compare( WC_ADMIN_VERSION_NUMBER, '1.4.0', '>=' ),
				'customSearch'    => self::version_compare( WC_ADMIN_VERSION_NUMBER, '1.3.0', '>=' ),
			],
			WC_Payments_Features::to_array()
		);
	}

	/**
	 * A wrapper around version_compare to allow comparing two version numbers even when they are suffixed with a dash and a string, for example 1.3.0-beta.
	 *
	 * @param string $version1 First version number.
	 * @param string $version2 Second version number.
	 * @param string $operator A boolean operator to use when comparing.
	 *
	 * @return bool True if the relationship is the one specified by the operator.
	 */
	private static function version_compare( $version1, $version2, string $operator ): bool {
		// Attempt to extract version numbers.
		$version_regex = '/^([\d\.]+)(-.*)?$/';
		if ( preg_match( $version_regex, $version1, $matches1 ) && preg_match( $version_regex, $version2, $matches2 ) ) {
			// Only compare the numeric parts of the versions, ignore the bit after the dash.
			$version1 = $matches1[1];
			$version2 = $matches2[1];
		}
		return (bool) version_compare( $version1, $version2, $operator );
	}

	/**
	 * Checks whether it's necessary to display a ToS agreement modal.
	 *
	 * @return bool
	 */
	private function is_tos_agreement_required() {
		// The gateway might already be disabled because of ToS.
		if ( ! $this->wcpay_gateway->is_enabled() ) {
			return false;
		}

		// Retrieve the latest agreement and check whether it's regarding the latest ToS version.
		$agreement = $this->account->get_latest_tos_agreement();
		if ( empty( $agreement ) ) {
			// Account data couldn't be fetched, let the merchant solve that first.
			return false;
		}

		return ! $agreement['is_current_version'];
	}

	/**
	 * Attempts to add a notification badge on WordPress menu next to Payments menu item
	 * to remind user that setup is required.
	 */
	public function add_menu_notification_badge() {
		global $menu;
		if ( 'yes' === get_option( 'wcpay_menu_badge_hidden', 'no' ) ) {
			return;
		}

		// If plugin activation date is less than 3 days, do not show the badge.
		$past_3_days = time() - get_option( 'wcpay_activation_timestamp', 0 ) >= ( 3 * DAY_IN_SECONDS );
		if ( false === $past_3_days ) {
			return;
		}

		if ( $this->account->is_stripe_connected() ) {
			update_option( 'wcpay_menu_badge_hidden', 'yes' );
			return;
		}

		foreach ( $menu as $index => $menu_item ) {
			if ( 'wc-admin&path=/payments/connect' === $menu_item[2] ) {
				$menu[ $index ][0] .= self::MENU_NOTIFICATION_BADGE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				break;
			}
		}
	}

	/**
	 * Attempts to add a setup task to remind the user to update
	 * their business details when the account is facing restriction.
	 */
	public function add_update_business_details_task() {
		if ( 'yes' === get_option( 'wcpay_show_update_business_details_task', 'no' ) ) {
			return;
		}

		$account  = $this->account->get_account_status_data();
		$status   = $account['status'] ?? '';
		$past_due = $account['has_overdue_requirements'] ?? false;

		if ( 'restricted_soon' === $status || ( 'restricted' === $status && $past_due ) ) {
			update_option( 'wcpay_show_update_business_details_task', 'yes' );
		}
	}

	/**
	 * Adds a container to the "payment gateways" page.
	 * This is where the "Are you sure you want to disable WCPay?" confirmation dialog is rendered.
	 */
	public function payment_gateways_container() {
		?><div id="wcpay-payment-gateways-container" />
		<?php
	}

	/**
	 * Returns the name to display for the "Payments > Settings" submenu item.
	 *
	 * The name will also contain a notification badge if the UPE settings preview is enabled but UPE is not.
	 *
	 * @return string
	 */
	private function get_settings_menu_item_name() {
		$label = __( 'Settings', 'woocommerce' ); // PHPCS:Ignore WordPress.WP.I18n.TextDomainMismatch

		if ( WC_Payments_Features::is_upe_settings_preview_enabled() && ! WC_Payments_Features::is_upe_enabled() ) {
			$label .= self::MENU_NOTIFICATION_BADGE;
		}

		return $label;
	}

	/**
	 * Check to see if the current user is in an A/B test treatment mode.
	 *
	 * @return bool
	 */
	private function is_in_treatment_mode() {
		if ( ! isset( $_COOKIE['tk_ai'] ) ) {
			return false;
		}

		$abtest = new \WCPay\Experimental_Abtest(
			sanitize_text_field( wp_unslash( $_COOKIE['tk_ai'] ) ),
			'woocommerce',
			'yes' === get_option( 'woocommerce_allow_tracking' )
		);

		return 'treatment' === $abtest->get_variation( 'wcpay_empty_state_preview_mode_v5' );
	}

	/**
	 * Checks if Stripe account is connected and redirects to the onboarding page
	 * if it is not and the user is attempting to view a WCPay admin page.
	 */
	public function maybe_redirect_to_onboarding() {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( $this->is_in_treatment_mode() ) {
			return;
		}

		$url_params = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $url_params['page'] ) || 'wc-admin' !== $url_params['page'] ) {
			return;
		}

		$current_path = ! empty( $url_params['path'] ) ? $url_params['path'] : '';

		if ( empty( $current_path ) ) {
			return;
		}

		$page_paths = [];

		foreach ( $this->admin_child_pages as $payments_child_page ) {
			$page_paths[] = preg_quote( $payments_child_page['path'], '/' );
		}

		if ( ! preg_match( '/^(' . implode( '|', $page_paths ) . ')/', $current_path ) ) {
			return;
		}

		if ( $this->account->is_stripe_connected( true ) ) {
			return;
		}

		$this->account->redirect_to_onboarding_page();
	}

	/**
	 * Add woopay as a payment method to the edit order on admin.
	 *
	 * @param int $order_id order_id.
	 */
	public function show_woopay_payment_method_name_admin( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( 'is_woopay' ) ) {
			return;
		}
		?>
		<div class="wc-payment-gateway-method-name-woopay-wrapper">
			<?php echo esc_html_e( 'Paid with', 'woocommerce-payments' ) . ' '; ?>
			<img alt="WooPay" src="<?php echo esc_url_raw( plugins_url( 'assets/images/woopay.svg', WCPAY_PLUGIN_FILE ) ); ?>">
			<?php
			if ( $order->get_meta( 'last4' ) ) {
				echo esc_html_e( 'Card ending in', 'woocommerce-payments' ) . ' ';
				echo esc_html( $order->get_meta( 'last4' ) );
			}
			?>
		</div>

		<?php
	}

	/**
	 * Adds a notification badge to the Payments > Disputes admin menu item to
	 * indicate the number of disputes that need a response.
	 */
	public function add_disputes_notification_badge() {
		global $submenu;

		if ( ! isset( $submenu[ self::PAYMENTS_SUBMENU_SLUG ] ) ) {
			return;
		}

		$disputes_needing_response = $this->get_disputes_awaiting_response_count();

		if ( $disputes_needing_response <= 0 ) {
			return;
		}

		foreach ( $submenu[ self::PAYMENTS_SUBMENU_SLUG ] as $index => $menu_item ) {
			if ( 'wc-admin&path=/payments/disputes' === $menu_item[2] ) {
				// Direct the user to the disputes which need a response by default.
				$submenu[ self::PAYMENTS_SUBMENU_SLUG ][ $index ][2] = admin_url( add_query_arg( [ 'filter' => 'awaiting_response' ], 'admin.php?page=' . $menu_item[2] ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

				// Append the dispute notification badge to indicate the number of disputes needing a response.
				$submenu[ self::PAYMENTS_SUBMENU_SLUG ][ $index ][0] .= sprintf( self::DISPUTE_NOTIFICATION_BADGE_FORMAT, esc_html( $disputes_needing_response ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				break;
			}
		}
	}

	/**
	 * Gets the number of disputes which need a response. ie have a 'needs_response' or 'warning_needs_response' status.
	 *
	 * @return int The number of disputes which need a response.
	 */
	private function get_disputes_awaiting_response_count() {
		$disputes_status_counts = $this->database_cache->get_or_add(
			Database_Cache::DISPUTE_STATUS_COUNTS_KEY,
			[ $this->payments_api_client, 'get_dispute_status_counts' ],
			// We'll consider all array values to be valid as the cache is only invalidated when it is deleted or it expires.
			'is_array'
		);

		if ( empty( $disputes_status_counts ) ) {
			return 0;
		}

		$needs_response_statuses = [ 'needs_response', 'warning_needs_response' ];
		return (int) array_sum( array_intersect_key( $disputes_status_counts, array_flip( $needs_response_statuses ) ) );
	}
}

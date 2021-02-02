<?php
/**
 * Set up top-level menus for WCPay.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Admin Class.
 */
class WC_Payments_Admin {

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
	 * Hook in admin menu items.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway WCPay Gateway instance to get information regarding WooCommerce Payments setup.
	 * @param WC_Payments_Account      $account Account instance.
	 */
	public function __construct(
		WC_Payment_Gateway_WCPay $gateway,
		WC_Payments_Account $account
	) {
		$this->wcpay_gateway = $gateway;
		$this->account       = $account;

		// Add menu items.
		add_action( 'admin_menu', [ $this, 'add_payments_menu' ], 0 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_payments_scripts' ] );
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

		$top_level_link = $should_render_full_menu ? '/payments/deposits' : '/payments/connect';

		wc_admin_register_page(
			[
				'id'         => 'wc-payments',
				'title'      => __( 'Payments', 'woocommerce-payments' ),
				'capability' => 'manage_woocommerce',
				'path'       => $top_level_link,
				'position'   => '55.7', // After WooCommerce & Product menu items.
				'nav_args'   => [
					'title'        => __( 'WooCommerce Payments', 'woocommerce-payments' ),
					'is_category'  => $should_render_full_menu,
					'menuId'       => 'plugins',
					'is_top_level' => true,
				],
			]
		);

		if ( $should_render_full_menu ) {
			wc_admin_register_page(
				[
					'id'       => 'wc-payments-deposits',
					'title'    => __( 'Deposits', 'woocommerce-payments' ),
					'parent'   => 'wc-payments',
					'path'     => '/payments/deposits',
					'nav_args' => [
						'parent' => 'wc-payments',
						'order'  => 10,
					],
				]
			);

			wc_admin_register_page(
				[
					'id'       => 'wc-payments-transactions',
					'title'    => __( 'Transactions', 'woocommerce-payments' ),
					'parent'   => 'wc-payments',
					'path'     => '/payments/transactions',
					'nav_args' => [
						'parent' => 'wc-payments',
						'order'  => 20,
					],
				]
			);

			wc_admin_register_page(
				[
					'id'       => 'wc-payments-disputes',
					'title'    => __( 'Disputes', 'woocommerce-payments' ),
					'parent'   => 'wc-payments',
					'path'     => '/payments/disputes',
					'nav_args' => [
						'parent' => 'wc-payments',
						'order'  => 30,
					],
				]
			);

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
						'order'  => 40,
					],
				]
			);
			// Add the Settings submenu directly to the array, it's the only way to make it link to an absolute URL.
			$submenu_keys                   = array_keys( $submenu );
			$last_submenu_key               = $submenu_keys[ count( $submenu ) - 1 ];
			$submenu[ $last_submenu_key ][] = [ // PHPCS:Ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				__( 'Settings', 'woocommerce' ), // PHPCS:Ignore WordPress.WP.I18n.TextDomainMismatch
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
		}

		wp_enqueue_style(
			'wcpay-admin-css',
			plugins_url( 'assets/css/admin.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/css/admin.css' )
		);
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

		wp_localize_script(
			'WCPAY_DASH_APP',
			'wcpaySettings',
			[
				'connectUrl'            => WC_Payments_Account::get_connect_url(),
				'testMode'              => $this->wcpay_gateway->is_in_test_mode(),
				'onBoardingDisabled'    => $on_boarding_disabled,
				'errorMessage'          => $error_message,
				'featureFlags'          => $this->get_frontend_feature_flags(),
				'isSubscriptionsActive' => class_exists( 'WC_Payment_Gateway_WCPay_Subscriptions_Compat' ),
				'zeroDecimalCurrencies' => WC_Payments_Utils::zero_decimal_currencies(),
				'fraudServices'         => $this->account->get_fraud_services_config(),
			]
		);

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

		wp_register_style(
			'WCPAY_TOS',
			plugins_url( 'dist/tos.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/tos.css' )
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
			'wcpayAdminSettings',
			[
				'accountStatus' => $this->account->get_account_status_data(),
				'accountFees'   => $this->account->get_fees(),
				'fraudServices' => $this->account->get_fraud_services_config(),
			]
		);

		// wcpaySettings.zeroDecimalCurrencies must be included as part of the WCPAY_ADMIN_SETTINGS as
		// it's used in the settings page by the AccountFees component.
		wp_localize_script(
			'WCPAY_ADMIN_SETTINGS',
			'wcpaySettings',
			[ 'zeroDecimalCurrencies' => WC_Payments_Utils::zero_decimal_currencies() ]
		);

		wp_register_style(
			'WCPAY_ADMIN_SETTINGS',
			plugins_url( 'dist/settings.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/settings.css' )
		);
	}

	/**
	 * Load the assets
	 */
	public function enqueue_payments_scripts() {
		global $current_tab, $current_section;

		$this->register_payments_scripts();

		$is_settings_page = (
			$current_tab && $current_section
			&& 'checkout' === $current_tab
			&& 'woocommerce_payments' === $current_section
		);

		if ( $is_settings_page ) {
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
				$is_settings_page ||

				// Or a WC Admin page?
				// Note: Merchants can navigate from analytics to payments w/o reload,
				// which is why this is neccessary.
				wc_admin_is_registered_page()
			)
		);

		if ( $tos_agreement_declined || $tos_agreement_required ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			wp_localize_script(
				'WCPAY_TOS',
				'wcpay_tos_settings',
				[
					'settingsUrl'          => $this->wcpay_gateway->get_settings_url(),
					'tosAgreementRequired' => $tos_agreement_required,
					'tosAgreementDeclined' => $tos_agreement_declined,
				]
			);

			wp_enqueue_script( 'WCPAY_TOS' );
			wp_enqueue_style( 'WCPAY_TOS' );
		}
	}

	/**
	 * Creates an array of features enabled only when external dependencies are of certain versions.
	 *
	 * @return array An associative array containing the flags as booleans.
	 */
	private function get_frontend_feature_flags() {
		return [
			'paymentTimeline' => self::version_compare( WC_ADMIN_VERSION_NUMBER, '1.4.0', '>=' ),
			'customSearch'    => self::version_compare( WC_ADMIN_VERSION_NUMBER, '1.3.0', '>=' ),
		];
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
	private static function version_compare( $version1, $version2, $operator ) {
		// Attempt to extract version numbers.
		$version_regex = '/^([\d\.]+)(-.*)?$/';
		if ( ! preg_match( $version_regex, $version1, $matches1 )
			|| ! preg_match( $version_regex, $version2, $matches2 ) ) {
				// Fall back to comparing the two versions as they are.
				return version_compare( $version1, $version2, $operator );
		}
		// Only compare the numeric parts of the versions, ignore the bit after the dash.
		$version1 = $matches1[1];
		$version2 = $matches2[1];
		return version_compare( $version1, $version2, $operator );
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
}

<?php
/**
 * Set up top-level menus for WCPay.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\Jetpack\Identity_Crisis as Jetpack_Identity_Crisis;
use Automattic\WooCommerce\Admin\PageController;
use WCPay\Core\Server\Request;
use WCPay\Database_Cache;
use WCPay\Logger;
use WCPay\WooPay\WooPay_Utilities;

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
	 * Badge with a count (number of unresolved items) displayed next to a menu item.
	 * Unresolved refers to items that are unread or need action.
	 *
	 * @var string
	 */
	const UNRESOLVED_NOTIFICATION_BADGE_FORMAT = ' <span class="wcpay-menu-badge awaiting-mod count-%1$s"><span class="plugin-count">%1$d</span></span>';

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
	 * WC_Payments_Onboarding_Service instance to get information for onboarding.
	 *
	 * @var WC_Payments_Onboarding_Service
	 */
	private $onboarding_service;

	/**
	 * Instance of Order Service for accessing order data.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * WC_Payments_Incentives_Service instance to get information for incentives.
	 *
	 * @var WC_Payments_Incentives_Service
	 */
	private $incentives_service;

	/**
	 * WC_Payments_Fraud_Service instance to get information about fraud services.
	 *
	 * @var WC_Payments_Fraud_Service
	 */
	private $fraud_service;

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
	 * The internal cache for WCPay settings passed to JS through script localization.
	 *
	 * This data should only be generated once per request, hence the internal cache.
	 *
	 * @see self::get_js_settings()
	 *
	 * @var ?array
	 */
	private $wcpay_js_settings = null;

	/**
	 * Hook in admin menu items.
	 *
	 * @param WC_Payments_API_Client         $payments_api_client WooCommerce Payments API client.
	 * @param WC_Payment_Gateway_WCPay       $gateway             WCPay Gateway instance to get information regarding WooCommerce Payments setup.
	 * @param WC_Payments_Account            $account             Account instance.
	 * @param WC_Payments_Onboarding_Service $onboarding_service  Onboarding service instance.
	 * @param WC_Payments_Order_Service      $order_service       Order service instance.
	 * @param WC_Payments_Incentives_Service $incentives_service  Incentives service instance.
	 * @param WC_Payments_Fraud_Service      $fraud_service       Fraud service instance.
	 * @param Database_Cache                 $database_cache      Database Cache instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payment_Gateway_WCPay $gateway,
		WC_Payments_Account $account,
		WC_Payments_Onboarding_Service $onboarding_service,
		WC_Payments_Order_Service $order_service,
		WC_Payments_Incentives_Service $incentives_service,
		WC_Payments_Fraud_Service $fraud_service,
		Database_Cache $database_cache
	) {
		$this->payments_api_client = $payments_api_client;
		$this->wcpay_gateway       = $gateway;
		$this->account             = $account;
		$this->onboarding_service  = $onboarding_service;
		$this->order_service       = $order_service;
		$this->incentives_service  = $incentives_service;
		$this->fraud_service       = $fraud_service;
		$this->database_cache      = $database_cache;

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
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'admin_notices', [ $this, 'display_not_supported_currency_notice' ], 9999 );
		add_action( 'admin_notices', [ $this, 'display_isk_decimal_notice' ] );

		add_action( 'woocommerce_admin_order_data_after_payment_info', [ $this, 'render_order_edit_payment_details_container' ] );

		// Add menu items.
		add_action( 'admin_menu', [ $this, 'add_payments_menu' ], 0 );
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_onboarding' ], 11 ); // Run this after the WC setup wizard and onboarding redirection logic.
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_redirect_overview_to_connect' ], 1 ); // Run this late (after `admin_init`) but before any scripts are actually enqueued.
		add_action( 'admin_enqueue_scripts', [ $this, 'register_payments_scripts' ], 9 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_payments_scripts' ], 9 );
		add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'payment_gateways_container' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'show_woopay_payment_method_name_admin' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_wcpay_transaction_fee' ] );
	}

	/**
	 * Add notice explaining that the selected currency is not available.
	 */
	public function display_not_supported_currency_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! $this->wcpay_gateway->is_available_for_current_currency() ) {
			?>
			<div id="wcpay-unsupported-currency-notice" class="notice notice-warning">
				<p>
					<b>
						<?php esc_html_e( 'Unsupported currency:', 'woocommerce-payments' ); ?>
						<?php esc_html( ' ' . get_woocommerce_currency() ); ?>
					</b>
					<?php
						echo sprintf(
							/* translators: %s: WooPayments*/
							esc_html__( 'The selected currency is not available for the country set in your %s account.', 'woocommerce-payments' ),
							'WooPayments'
						);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render a container for adding notices to order details screen payment box.
	 */
	public function render_order_edit_payment_details_container() {
		?>
		<div id="wcpay-order-payment-details-container"></div>
		<?php
	}

	/**
	 * Add notice explaining that ISK cannot have decimals.
	 */
	public function display_isk_decimal_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( get_woocommerce_currency() === 'ISK' && wc_get_price_decimals() !== 0 ) {
			$url = get_admin_url( null, 'admin.php?page=wc-settings' );

			?>
			<div id="wcpay-unsupported-currency-notice" class="notice notice-error">
				<p>
					<b>
						<?php esc_html_e( 'Unsupported currency:', 'woocommerce-payments' ); ?>
						<?php esc_html( ' ' . get_woocommerce_currency() ); ?>
					</b>
					<?php
						echo wp_kses_post(
							sprintf(
								/* Translators: %1$s: Opening anchor tag. %2$s: Closing anchor tag.*/
								__( 'Icelandic Króna does not accept decimals. Please update your currency number of decimals to 0 or select a different currency. %1$sVisit settings%2$s', 'woocommerce-payments' ),
								'<a href="' . $url . '">',
								'</a>'
							)
						);
					?>
				</p>
			</div>
			<?php
		}
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
					'title'        => 'WooPayments',
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

		// RTL=false for these files as they aren't currently transpiled by webpack.
		WC_Payments_Utils::enqueue_style(
			'wcpay-admin-css',
			plugins_url( 'assets/css/admin.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/css/admin.css' ),
			'all',
			false
		);

		$this->add_menu_notification_badge();
	}

	/**
	 * Add payments menu items.
	 */
	public function add_payments_menu() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		global $submenu;

		// If the user is redirected to the page after Stripe KYC with an error, refresh the account data.
		// The GET parameter accessed here comes from server and is just to indicate that some error occured. For this reason we're not using a nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wcpay-connection-error'] ) ) {
			$this->account->refresh_account_data();
		}
		try {
			// Render full payments menu with sub-items only if the merchant completed the KYC (details_submitted = true).
			$should_render_full_menu = $this->account->is_stripe_connected() && $this->account->is_details_submitted();
		} catch ( Exception $e ) {
			// There is an issue with connection, don't render full menu, user will get redirected to the connect page.
			$should_render_full_menu = false;
		}

		$top_level_link = $this->account->is_stripe_connected() ? '/payments/overview' : '/payments/connect';

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
					'title'        => 'WooPayments',
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

		if ( ! $this->account->is_stripe_connected() ) {
			if ( WC_Payments_Utils::should_use_new_onboarding_flow() ) {
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
				remove_submenu_page( 'wc-admin&path=/payments/connect', 'wc-admin&path=/payments/onboarding' );
			}
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
					'title'     => 'WooPayments',
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
				WC_Payments_Admin_Settings::get_settings_url(),
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
					'id'     => 'wc-payments-disputes-details-legacy-redirect',
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

		// RTL=false for these files as they aren't currently transpiled by webpack.
		WC_Payments_Utils::enqueue_style(
			'wcpay-admin-css',
			plugins_url( 'assets/css/admin.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/css/admin.css' ),
			'all',
			false
		);

		$this->add_menu_notification_badge();
		$this->add_disputes_notification_badge();
		if ( \WC_Payments_Features::is_auth_and_capture_enabled() && $this->wcpay_gateway->get_option( 'manual_capture' ) === 'yes' ) {
			$this->add_transactions_notification_badge();
		}
	}

	/**
	 * Register the CSS and JS scripts
	 */
	public function register_payments_scripts() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		WC_Payments::register_script_with_dependencies( 'WCPAY_DASH_APP', 'dist/index', [ 'wp-api-request' ] );

		wp_set_script_translations( 'WCPAY_DASH_APP', 'woocommerce-payments' );

		WC_Payments_Utils::register_style(
			'WCPAY_DASH_APP',
			plugins_url( 'dist/index.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/index.css' ),
			'all'
		);

		WC_Payments::register_script_with_dependencies( 'WCPAY_TOS', 'dist/tos' );
		wp_set_script_translations( 'WCPAY_TOS', 'woocommerce-payments' );

		WC_Payments_Utils::register_style(
			'WCPAY_TOS',
			plugins_url( 'dist/tos.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/tos.css' ),
			'all'
		);

		WC_Payments::register_script_with_dependencies( 'WCPAY_ADMIN_ORDER_ACTIONS', 'dist/order', [ 'jquery-tiptip' ] );

		WC_Payments_Utils::register_style(
			'WCPAY_ADMIN_ORDER_ACTIONS',
			plugins_url( 'dist/order.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/order.css' ),
			'all'
		);

		WC_Payments::register_script_with_dependencies( 'WCPAY_ADMIN_SETTINGS', 'dist/settings' );

		wp_localize_script(
			'WCPAY_ADMIN_SETTINGS',
			'wcpayPaymentRequestParams',
			[
				'stripe' => [
					'publishableKey' => $this->account->get_publishable_key( WC_Payments::mode()->is_test() ),
					'accountId'      => $this->account->get_stripe_account_id(),
					'locale'         => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
				],
			]
		);

		wp_set_script_translations( 'WCPAY_ADMIN_SETTINGS', 'woocommerce-payments' );

		WC_Payments_Utils::register_style(
			'WCPAY_ADMIN_SETTINGS',
			plugins_url( 'dist/settings.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/settings.css' ),
			'all'
		);

		WC_Payments::register_script_with_dependencies( 'WCPAY_PAYMENT_GATEWAYS_PAGE', 'dist/payment-gateways' );

		WC_Payments_Utils::register_style(
			'WCPAY_PAYMENT_GATEWAYS_PAGE',
			plugins_url( 'dist/payment-gateways.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/payment-gateways.css' ),
			'all'
		);
	}

	/**
	 * Load the assets
	 */
	public function enqueue_payments_scripts() {
		global $current_tab, $current_section;

		// Enqueue the admin settings assets on any WCPay settings page.
		// We also need to enqueue and localize on the multi-currency tab.
		if ( WC_Payments_Utils::is_payments_settings_page() || 'wcpay_multi_currency' === $current_tab ) {
			// Localize before actually enqueuing to avoid unnecessary settings generation.
			// Most importantly, the destructive error transient handling.
			wp_localize_script(
				'WCPAY_ADMIN_SETTINGS',
				'wcpaySettings',
				$this->get_js_settings()
			);

			// Output the settings JS and CSS only on the settings page.
			wp_enqueue_script( 'WCPAY_ADMIN_SETTINGS' );
			wp_enqueue_style( 'WCPAY_ADMIN_SETTINGS' );
		}

		// TODO: Try to enqueue the JS and CSS bundles lazily (will require changes on WC-Admin).
		$current_screen = get_current_screen() ? get_current_screen()->base : null;
		if ( wc_admin_is_registered_page() || 'widgets' === $current_screen ) {
			// Localize before actually enqueuing to avoid unnecessary settings generation.
			// Most importantly, the destructive error transient handling.
			wp_localize_script(
				'WCPAY_DASH_APP',
				'wcpaySettings',
				$this->get_js_settings()
			);

			wp_enqueue_script( 'WCPAY_DASH_APP' );
			wp_enqueue_style( 'WCPAY_DASH_APP' );
		}

		// TODO: Update conditions when ToS script is enqueued.
		$tos_agreement_declined = (
			'checkout' === $current_tab
			&& isset( $_GET['tos-disabled'] ) // phpcs:ignore WordPress.Security.NonceVerification
		);

		$tos_agreement_required = (
			$this->is_tos_agreement_required() &&
			(
				WC_Payments_Utils::is_payments_settings_page() ||

				// Or a WC Admin page?
				// Note: Merchants can navigate from analytics to payments w/o reload,
				// which is why this is necessary.
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
					'settingsUrl'          => WC_Payments_Admin_Settings::get_settings_url(),
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
		if ( in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) {
			$order = wc_get_order();

			if ( $order && WC_Payment_Gateway_WCPay::GATEWAY_ID === $order->get_payment_method() ) {
				$refund_amount = $order->get_remaining_refund_amount();

				// Check if the order's test mode meta matches the site's current test mode state.
				// E.g. order and site are both in test mode, or both in live mode.
				$order_mode = $order->get_meta( WC_Payments_Order_Service::WCPAY_MODE_META_KEY );
				if ( '' === $order_mode ) {
					// If the order doesn't have a mode set, assume it was created before the order mode meta was added (< 6.9 PR#7651) and return null.
					$order_test_mode_match = null;
				} else {
					$order_test_mode_match = (
						\WCPay\Constants\Order_Mode::PRODUCTION === $order_mode &&
						WC_Payments::mode()->is_live()
					) || (
						\WCPay\Constants\Order_Mode::TEST === $order_mode &&
						WC_Payments::mode()->is_test()
					);
				}

				wp_localize_script(
					'WCPAY_ADMIN_ORDER_ACTIONS',
					'wcpay_order_config',
					[
						'disableManualRefunds'  => ! $this->wcpay_gateway->has_refund_failed( $order ),
						'manualRefundsTip'      => __( 'Refunding manually requires reimbursing your customer offline via cash, check, etc. The refund amounts entered here will only be used to balance your analytics.', 'woocommerce-payments' ),
						'refundAmount'          => $refund_amount,
						'formattedRefundAmount' => wp_strip_all_tags( wc_price( $refund_amount, [ 'currency' => $order->get_currency() ] ) ),
						'refundedAmount'        => $order->get_total_refunded(),
						'canRefund'             => $this->wcpay_gateway->can_refund_order( $order ),
						'chargeId'              => $this->order_service->get_charge_id_for_order( $order ),
						'hasOpenAuthorization'  => $this->order_service->has_open_authorization( $order ),
						'testMode'              => \WCPay\Constants\Order_Mode::TEST === $order->get_meta( WC_Payments_Order_Service::WCPAY_MODE_META_KEY ),
						'orderTestModeMatch'    => $order_test_mode_match,
					]
				);
				wp_localize_script(
					'WCPAY_ADMIN_ORDER_ACTIONS',
					'wcpaySettings',
					$this->get_js_settings()
				);

				wp_enqueue_script( 'WCPAY_ADMIN_ORDER_ACTIONS' );
				WC_Payments_Utils::enqueue_style( 'WCPAY_ADMIN_ORDER_ACTIONS' );
			}
		}
	}

	/**
	 * Get the WCPay settings to be sent to JS.
	 *
	 * It used an internal cache to make sure it only generates the settings once per request.
	 * This is needed in order to avoid performance issues and simplify error transients handling.
	 *
	 * @return array
	 */
	private function get_js_settings(): array {
		// Return the internally cached data if it is already initialized.
		if ( ! is_null( $this->wcpay_js_settings ) ) {
			return $this->wcpay_js_settings;
		}

		$error_message = get_transient( WC_Payments_Account::ERROR_MESSAGE_TRANSIENT );
		delete_transient( WC_Payments_Account::ERROR_MESSAGE_TRANSIENT );

		/**
		 * This is a workaround to pass the current user's email address to WCPay's settings until we do not need to rely
		 * on backwards compatibility and can use `getCurrentUser` from `@wordpress/core-data`.
		 */
		$current_user       = wp_get_current_user();
		$current_user_email = $current_user && $current_user->user_email ? $current_user->user_email : get_option( 'admin_email' );

		if ( version_compare( WC_VERSION, '6.0', '<' ) ) {
			$path = WCPAY_ABSPATH . 'i18n/locale-info.php';
		} else {
			$path = WC()->plugin_path() . '/i18n/locale-info.php';
		}

		$locale_info = include $path;

		// Get symbols for those currencies without a short one.
		$symbols       = get_woocommerce_currency_symbols();
		$currency_data = [];

		foreach ( $locale_info as $key => $value ) {
			$currency_code         = $value['currency_code'] ?? '';
			$currency_data[ $key ] = [
				'code'              => $currency_code,
				'symbol'            => $value['short_symbol'] ?? $symbols[ $currency_code ] ?? '',
				'symbolPosition'    => $value['currency_pos'] ?? '',
				'thousandSeparator' => $value['thousand_sep'] ?? '',
				'decimalSeparator'  => $value['decimal_sep'] ?? '',
				'precision'         => $value['num_decimals'],
			];
		}

		$account_status_data = $this->account->get_account_status_data();

		$test_mode = false;
		try {
			$test_mode = WC_Payments::mode()->is_test();
		} catch ( Exception $e ) {
			Logger::log( sprintf( 'WooPayments JS settings: Could not determine if WCPay should be in test mode! Message: %s', $e->getMessage() ), 'warning' );
		}

		$dev_mode = false;
		try {
			$dev_mode = WC_Payments::mode()->is_dev();
		} catch ( Exception $e ) {
			Logger::log( sprintf( 'WooPayments JS settings: Could not determine if WCPay should be in sandbox mode! Message: %s', $e->getMessage() ), 'warning' );
		}

		$connect_url       = WC_Payments_Account::get_connect_url();
		$connect_incentive = $this->incentives_service->get_cached_connect_incentive();
		// If we have an incentive ID, attach it to the connect URL.
		if ( ! empty( $connect_incentive['id'] ) ) {
			$connect_url = add_query_arg( [ 'promo' => sanitize_text_field( $connect_incentive['id'] ) ], $connect_url );
		}

		$this->wcpay_js_settings = [
			'version'                       => WCPAY_VERSION_NUMBER,
			'connectUrl'                    => $connect_url,
			'connect'                       => [
				'country'            => WC()->countries->get_base_country(),
				'availableCountries' => WC_Payments_Utils::supported_countries(),
				'availableStates'    => WC()->countries->get_states(),
			],
			'connectIncentive'              => $connect_incentive,
			'devMode'                       => $dev_mode,
			'testMode'                      => $test_mode,
			'onboardingTestMode'            => WC_Payments_Onboarding_Service::is_test_mode_enabled(),
			// Set this flag for use in the front-end to alter messages and notices if on-boarding has been disabled.
			'onBoardingDisabled'            => WC_Payments_Account::is_on_boarding_disabled(),
			'onboardingFieldsData'          => $this->onboarding_service->get_fields_data( get_user_locale() ),
			'onboardingFlowState'           => $this->onboarding_service->get_onboarding_flow_state(),
			'errorMessage'                  => $error_message,
			'featureFlags'                  => $this->get_frontend_feature_flags(),
			'isSubscriptionsActive'         => class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' ),
			// Used in the settings page by the AccountFees component.
			'zeroDecimalCurrencies'         => WC_Payments_Utils::zero_decimal_currencies(),
			'fraudServices'                 => $this->fraud_service->get_fraud_services_config(),
			'isJetpackConnected'            => $this->payments_api_client->is_server_connected(),
			'isJetpackIdcActive'            => Jetpack_Identity_Crisis::has_identity_crisis(),
			'accountStatus'                 => $account_status_data,
			'accountFees'                   => $this->account->get_fees(),
			'accountLoans'                  => $this->account->get_capital(),
			'accountEmail'                  => $this->account->get_account_email(),
			'showUpdateDetailsTask'         => $this->get_should_show_update_business_details_task( $account_status_data ),
			'wpcomReconnectUrl'             => $this->payments_api_client->is_server_connected() && ! $this->payments_api_client->has_server_connection_owner() ? WC_Payments_Account::get_wpcom_reconnect_url() : null,
			'multiCurrencySetup'            => [
				'isSetupCompleted' => get_option( 'wcpay_multi_currency_setup_completed' ),
			],
			'isMultiCurrencyEnabled'        => WC_Payments_Features::is_customer_multi_currency_enabled(),
			'isClientEncryptionEligible'    => WC_Payments_Features::is_client_secret_encryption_eligible(),
			'shouldUseExplicitPrice'        => WC_Payments_Explicit_Price_Formatter::should_output_explicit_price(),
			'overviewTasksVisibility'       => [
				'dismissedTodoTasks'     => get_option( 'woocommerce_dismissed_todo_tasks', [] ),
				'deletedTodoTasks'       => get_option( 'woocommerce_deleted_todo_tasks', [] ),
				'remindMeLaterTodoTasks' => get_option( 'woocommerce_remind_me_later_todo_tasks', [] ),
			],
			'currentUserEmail'              => $current_user_email,
			'currencyData'                  => $currency_data,
			'restUrl'                       => get_rest_url( null, '' ), // rest url to concatenate when merchant use Plain permalinks.
			'isFRTReviewFeatureActive'      => WC_Payments_Features::is_frt_review_feature_active(),
			'fraudProtection'               => [
				'isWelcomeTourDismissed' => WC_Payments_Features::is_fraud_protection_welcome_tour_dismissed(),
			],
			'enabledPaymentMethods'         => $this->get_enabled_payment_method_ids(),
			'progressiveOnboarding'         => $this->account->get_progressive_onboarding_details(),
			'accountDefaultCurrency'        => $this->account->get_account_default_currency(),
			'frtDiscoverBannerSettings'     => get_option( 'wcpay_frt_discover_banner_settings', '' ),
			'storeCurrency'                 => get_option( 'woocommerce_currency' ),
			'isBnplAffirmAfterpayEnabled'   => WC_Payments_Features::is_bnpl_affirm_afterpay_enabled(),
			'isWooPayStoreCountryAvailable' => WooPay_Utilities::is_store_country_available(),
			'woopayLastDisableDate'         => $this->wcpay_gateway->get_option( 'platform_checkout_last_disable_date' ),
			'isStripeBillingEnabled'        => WC_Payments_Features::is_stripe_billing_enabled(),
			'isStripeBillingEligible'       => WC_Payments_Features::is_stripe_billing_eligible(),
			'capabilityRequestNotices'      => get_option( 'wcpay_capability_request_dismissed_notices ', [] ),
			'storeName'                     => get_bloginfo( 'name' ),
			'isNextDepositNoticeDismissed'  => WC_Payments_Features::is_next_deposit_notice_dismissed(),
			'reporting'                     => [
				'exportModalDismissed' => get_option( 'wcpay_reporting_export_modal_dismissed', false ),
			],
			'locale'                        => WC_Payments_Utils::get_language_data( get_locale() ),
		];

		return apply_filters( 'wcpay_js_settings', $this->wcpay_js_settings );
	}

	/**
	 * Helper function to retrieve enabled UPE payment methods.
	 *
	 * TODO: This is duplicating code located in the settings container, we should refactor so that
	 * this is stored in a centralised place and can be retrieved from there.
	 *
	 * @return array
	 */
	private function get_enabled_payment_method_ids(): array {
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

		return $enabled_payment_methods;
	}

	/**
	 * Creates an array of features enabled only when external dependencies are of certain versions.
	 *
	 * @return array An associative array containing the flags as booleans.
	 */
	private function get_frontend_feature_flags(): array {
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

		$badge = self::MENU_NOTIFICATION_BADGE;
		foreach ( $menu as $index => $menu_item ) {
			if ( false === strpos( $menu_item[0], $badge ) && ( 'wc-admin&path=/payments/connect' === $menu_item[2] ) ) {
				$menu[ $index ][0] .= $badge; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

				// One menu item with a badge is more than enough.
				break;
			}
		}
	}

	/**
	 * Check whether a setup task needs to be displayed prompting the user to update
	 * their business details.
	 *
	 * @param array $account_status_data An array containing the account status data.
	 *
	 * @return bool True if we should show the task, false otherwise.
	 */
	public function get_should_show_update_business_details_task( array $account_status_data ) {
		$status           = $account_status_data['status'] ?? '';
		$current_deadline = $account_status_data['currentDeadline'] ?? false;
		$past_due         = $account_status_data['pastDue'] ?? false;

		// If the account is restricted_soon, but there's no current deadline, no action is needed.
		if ( ( 'restricted_soon' === $status && $current_deadline ) || ( 'restricted' === $status && $past_due ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Adds a container to the "payment gateways" page.
	 * This is where the "Are you sure you want to disable WCPay?" confirmation dialog is rendered.
	 */
	public function payment_gateways_container() {
		?>
		<div id="wcpay-payment-gateways-container" />
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
		return __( 'Settings', 'woocommerce' ); // PHPCS:Ignore WordPress.WP.I18n.TextDomainMismatch
	}

	/**
	 * Checks if Stripe account is connected and redirects to the onboarding page
	 * if it is not and the user is attempting to view a WCPay admin page.
	 */
	public function maybe_redirect_to_onboarding() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( wp_doing_ajax() ) {
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

		$this->account->redirect_to_onboarding_welcome_page();
	}

	/**
	 * Avoid WC Admin /payments/overview error page when the current account associated Stripe account is not valid.
	 *
	 * The errored page happens because we don't register a /payments/overview WC admin page when the Stripe account
	 * is not valid and register only a /payments/connect top level menu page.
	 *
	 * Places around our plugin redirect merchants to the overview page by default (or using it for the Stripe KYC
	 * return URL) leading to poor UX.
	 * This is a safety net to prevent that from happening.
	 *
	 * @see self::add_payments_menu()
	 */
	public function maybe_redirect_overview_to_connect() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( wp_doing_ajax() ) {
			return;
		}

		// If the current page is registered, let it pass.
		if ( wc_admin_is_registered_page() ) {
			return;
		}

		$url_params = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( empty( $url_params['page'] ) || 'wc-admin' !== $url_params['page']
			|| empty( $url_params['path'] ) || '/payments/overview' !== $url_params['path'] ) {
			return;
		}

		/**
		 * Determine the path of the top level menu page since that can change between payments/connect and payments/overview.
		 *
		 * @see self::add_payments_menu()
		 */
		$top_level_page_path = PageController::get_instance()->get_path_from_id( 'wc-payments' );
		// If the top level page path is not the payments/connect one, bail.
		if ( 'wc-admin&path=/payments/connect' !== $top_level_page_path ) {
			return;
		}

		$this->account->redirect_to_onboarding_welcome_page();
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
	 * Display the _wcpay_transaction_fee from order metadata to the Order Edit screen on admin.
	 *
	 * @param int $order_id order_id.
	 */
	public function display_wcpay_transaction_fee( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_wcpay_transaction_fee' ) ) {
			return;
		}
		?>
		<tr>
			<td class="label wcpay-transaction-fee">
				<?php
					// phpcs:ignore WordPress.Security.EscapeOutput
					echo wc_help_tip(
						sprintf(
							/* translators: %s: WooPayments */
							__( 'This represents the fee %s collects for the transaction.', 'woocommerce-payments' ),
							'WooPayments'
						)
					);
				?>
				<?php esc_html_e( 'Transaction Fee:', 'woocommerce-payments' ); ?>
			</td>
			<td width="1%"></td>
			<td class="total">
				-<?php echo wp_kses( wc_price( $order->get_meta( '_wcpay_transaction_fee' ), [ 'currency' => $order->get_currency() ] ), 'post' ); ?>
			</td>
		</tr>
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
				$submenu[ self::PAYMENTS_SUBMENU_SLUG ][ $index ][0] .= sprintf( self::UNRESOLVED_NOTIFICATION_BADGE_FORMAT, esc_html( $disputes_needing_response ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				break;
			}
		}
	}

	/**
	 * Adds a notification badge to the Payments > Transactions admin menu item to
	 * indicate the number of transactions that need to be captured.
	 *
	 * @return void
	 */
	public function add_transactions_notification_badge() {
		global $submenu;

		if ( ! isset( $submenu[ self::PAYMENTS_SUBMENU_SLUG ] ) ) {
			return;
		}

		$uncaptured_transactions = $this->get_uncaptured_transactions_count();
		if ( $uncaptured_transactions <= 0 ) {
			return;
		}

		foreach ( $submenu[ self::PAYMENTS_SUBMENU_SLUG ] as $index => $menu_item ) {
			if ( 'wc-admin&path=/payments/transactions' === $menu_item[2] ) {
				$submenu[ self::PAYMENTS_SUBMENU_SLUG ][ $index ][0] .= sprintf( self::UNRESOLVED_NOTIFICATION_BADGE_FORMAT, esc_html( $uncaptured_transactions ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
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
		$send_callback = function() {
			$request = Request::get( WC_Payments_API_Client::DISPUTES_API . '/status_counts' );
			$request->assign_hook( 'wcpay_get_dispute_status_counts' );
			return $request->send();
		};

		$disputes_status_counts = $this->database_cache->get_or_add(
			Database_Cache::DISPUTE_STATUS_COUNTS_KEY,
			$send_callback,
			// We'll consider all array values to be valid as the cache is only invalidated when it is deleted or it expires.
			'is_array'
		);

		if ( empty( $disputes_status_counts ) ) {
			return 0;
		}

		$needs_response_statuses = [ 'needs_response', 'warning_needs_response' ];
		return (int) array_sum( array_intersect_key( $disputes_status_counts, array_flip( $needs_response_statuses ) ) );
	}

	/**
	 * Gets the number of uncaptured transactions, that is authorizations that need to be captured within 7 days.
	 *
	 * @return int The number of uncaptured transactions.
	 */
	private function get_uncaptured_transactions_count() {
		$test_mode = WC_Payments::mode()->is_test();
		$cache_key = $test_mode ? DATABASE_CACHE::AUTHORIZATION_SUMMARY_KEY_TEST_MODE : DATABASE_CACHE::AUTHORIZATION_SUMMARY_KEY;

		$send_callback         = function() {
			$request = Request::get( WC_Payments_API_Client::AUTHORIZATIONS_API . '/summary' );
			$request->assign_hook( 'wc_pay_get_authorizations_summary' );
			return $request->send();
		};
		$authorization_summary = $this->database_cache->get_or_add(
			$cache_key,
			$send_callback,
			// We'll consider all array values to be valid as the cache is only invalidated when it is deleted or it expires.
			'is_array'
		);

		if ( empty( $authorization_summary ) ) {
			return 0;
		}

		return $authorization_summary['count'];
	}
}

<?php
/**
 * Class WC_Payments_Account
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Admin\Notes\DataStore;
use WCPay\Exceptions\API_Exception;
use WCPay\Logger;

/**
 * Class handling any account connection functionality
 */
class WC_Payments_Account {

	// ACCOUNT_TRANSIENT is only used in the supporting dev tools plugin, it can be removed once everyone has upgraded.
	const ACCOUNT_TRANSIENT              = 'wcpay_account_data';
	const ACCOUNT_OPTION                 = 'wcpay_account_data';
	const ACCOUNT_RETRIEVAL_ERROR        = 'ERROR';
	const ON_BOARDING_DISABLED_TRANSIENT = 'wcpay_on_boarding_disabled';
	const ERROR_MESSAGE_TRANSIENT        = 'wcpay_error_message';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Class constructor
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;

		add_action( 'admin_init', [ $this, 'maybe_handle_oauth' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_onboarding' ], 11 ); // Run this after the WC setup wizard and onboarding redirection logic.
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'handle_instant_deposits_inbox_note' ] );
		add_action( 'wcpay_instant_deposit_reminder', [ $this, 'handle_instant_deposits_inbox_reminder' ] );
		add_filter( 'allowed_redirect_hosts', [ $this, 'allowed_redirect_hosts' ] );
		add_action( 'jetpack_site_registered', [ $this, 'clear_cache' ] );
		add_filter( 'woocommerce_debug_tools', [ $this, 'debug_tool' ] );
	}

	/**
	 * Wipes the account data option, forcing to re-fetch the account status from WP.com.
	 */
	public function clear_cache() {
		delete_option( self::ACCOUNT_OPTION );
	}

	/**
	 * Add clear account cache tool to WooCommerce debug tools.
	 *
	 * @param array $tools List of current available tools.
	 */
	public function debug_tool( $tools ) {
		$tools['clear_wcpay_account_cache'] = [
			'name'     => __( 'Clear WooCommerce Payments account cache', 'woocommerce-payments' ),
			'button'   => __( 'Clear', 'woocommerce-payments' ),
			'desc'     => __( 'This tool will clear the account cached values used in WooCommerce Payments.', 'woocommerce-payments' ),
			'callback' => [ $this, 'refresh_account_data' ],
		];
		return $tools;
	}

	/**
	 * Return connected account ID
	 *
	 * @return string|null Account ID if connected, null if not connected or on error
	 */
	public function get_stripe_account_id() {
		$account = $this->get_cached_account_data();

		if ( empty( $account ) ) {
			return null;
		}

		return $account['account_id'];
	}

	/**
	 * Gets public key for the connected account
	 *
	 * @param bool $is_test true to get the test key, false otherwise.
	 *
	 * @return string|null public key if connected, null if not connected.
	 */
	public function get_publishable_key( $is_test ) {
		$account = $this->get_cached_account_data();

		if ( empty( $account ) ) {
			return null;
		}

		if ( $is_test ) {
			return $account['test_publishable_key'];
		}

		return $account['live_publishable_key'];
	}

	/**
	 * Checks if the account is connected, assumes the value of $on_error on server error.
	 *
	 * @param bool $on_error Value to return on server error, defaults to false.
	 *
	 * @return bool True if the account is connected, false otherwise, $on_error on error.
	 */
	public function is_stripe_connected( $on_error = false ) {
		try {
			return $this->try_is_stripe_connected();
		} catch ( Exception $e ) {
			return $on_error;
		}
	}

	/**
	 * Checks if the account is connected, throws on server error.
	 *
	 * @return bool      True if the account is connected, false otherwise.
	 * @throws Exception Throws exception when unable to detect connection status.
	 */
	public function try_is_stripe_connected() {
		$account = $this->get_cached_account_data();

		if ( false === $account ) {
			throw new Exception( __( 'Failed to detect connection status', 'woocommerce-payments' ) );
		}

		if ( is_array( $account ) && empty( $account ) ) {
			// empty means no account.
			return false;
		}

		return true;
	}

	/**
	 * Gets the account status data for rendering on the settings page.
	 *
	 * @return array An array containing the status data, or [ 'error' => true ] on error or no connected account.
	 */
	public function get_account_status_data() {
		$account = $this->get_cached_account_data();

		if ( empty( $account ) ) {
			// empty means no account. This data should not be used when the account is not connected.
			return [
				'error' => true,
			];
		}

		if ( ! isset( $account['status'] )
			|| ! isset( $account['payments_enabled'] )
			|| ! isset( $account['deposits_status'] ) ) {
			// return an error if any of the account data is missing.
			return [
				'error' => true,
			];
		}

		return [
			'email'           => $account['email'] ?? '',
			'status'          => $account['status'],
			'paymentsEnabled' => $account['payments_enabled'],
			'depositsStatus'  => $account['deposits_status'],
			'currentDeadline' => isset( $account['current_deadline'] ) ? $account['current_deadline'] : false,
			'pastDue'         => isset( $account['has_overdue_requirements'] ) ? $account['has_overdue_requirements'] : false,
			'accountLink'     => $this->get_login_url(),
		];
	}

	/**
	 * Gets the account statement descriptor for rendering on the settings page.
	 *
	 * @return string Account statement descriptor.
	 */
	public function get_statement_descriptor() {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['statement_descriptor'] ) ? $account['statement_descriptor'] : '';
	}

	/**
	 * Gets the current account fees for rendering on the settings page.
	 *
	 * @return array Fees.
	 */
	public function get_fees() {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['fees'] ) ? $account['fees'] : [];
	}

	/**
	 * Gets the account live mode value.
	 *
	 * @return bool|null Account is_live value.
	 */
	public function get_is_live() {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['is_live'] ) ? $account['is_live'] : null;
	}

	/**
	 * Gets the various anti-fraud services that must be included on every WCPay-related page.
	 *
	 * @return array Assoc array. Each key is the slug of a fraud service that must be incorporated to every page, the value is service-specific config for it.
	 */
	public function get_fraud_services_config() {
		$account = $this->get_cached_account_data();
		if ( empty( $account ) || ! isset( $account['fraud_services'] ) ) {
			// This was the default before adding new anti-fraud providers, preserve backwards-compatibility.
			return [ 'stripe' => [] ];
		}
		$services_config          = $account['fraud_services'];
		$filtered_services_config = [];
		foreach ( $services_config as $service_id => $config ) {
			$filtered_services_config[ $service_id ] = apply_filters( 'wcpay_prepare_fraud_config', $config, $service_id );
		}
		return $filtered_services_config;
	}

	/**
	 * Utility function to immediately redirect to the main "Welcome to WooCommerce Payments" onboarding page.
	 * Note that this function immediately ends the execution.
	 *
	 * @param string $error_message Optional error message to show in a notice.
	 */
	public function redirect_to_onboarding_page( $error_message = null ) {
		if ( isset( $error_message ) ) {
			set_transient( self::ERROR_MESSAGE_TRANSIENT, $error_message, 30 );
		}

		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];
		if ( count( $params ) === count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			// We are already in the onboarding page, do nothing.
			return;
		}

		wp_safe_redirect( admin_url( add_query_arg( $params, 'admin.php' ) ) );
		exit();
	}

	/**
	 * Checks if Stripe account is connected and redirects to the onboarding page if it is not.
	 *
	 * @return bool True if the redirection happened.
	 */
	public function maybe_redirect_to_onboarding() {
		if ( wp_doing_ajax() ) {
			return false;
		}

		$is_on_settings_page           = WC_Payment_Gateway_WCPay::is_current_page_settings();
		$should_redirect_to_onboarding = get_option( 'wcpay_should_redirect_to_onboarding', false );

		if (
			// If not loading the settings page...
			! $is_on_settings_page
			// ...and we have redirected before.
			&& ! $should_redirect_to_onboarding
		) {
			// Do not attempt to redirect again.
			return false;
		}

		$account = $this->get_cached_account_data();
		if ( false === $account ) {
			// Failed to retrieve account data. Exception is logged in http client.
			return false;
		}

		if ( $should_redirect_to_onboarding ) {
			// Update the option. If there's an account connected, we won't need to redirect in the future.
			// If not, we will redirect once and will not want to redirect again.
			update_option( 'wcpay_should_redirect_to_onboarding', false );
		}

		if ( ! empty( $account ) ) {
			// Do not redirect if connected.
			return false;
		}

		// Redirect if not connected.
		$this->redirect_to_onboarding_page();
		return true;
	}

	/**
	 * Filter function to add Stripe to the list of allowed redirect hosts
	 *
	 * @param array $hosts - array of allowed hosts.
	 *
	 * @return array allowed hosts
	 */
	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'connect.stripe.com';
		return $hosts;
	}

	/**
	 * Handle OAuth (login/init/redirect) routes
	 */
	public function maybe_handle_oauth() {
		if ( ! is_admin() ) {
			return;
		}

		if ( isset( $_GET['wcpay-login'] ) && check_admin_referer( 'wcpay-login' ) ) {
			try {
				$this->redirect_to_login();
			} catch ( Exception $e ) {
				$this->add_notice_to_settings_page(
					__( 'There was a problem redirecting you to the account dashboard. Please try again.', 'woocommerce-payments' ),
					'notice-error'
				);
			}
			return;
		}

		if ( isset( $_GET['wcpay-reconnect-wpcom'] ) && check_admin_referer( 'wcpay-reconnect-wpcom' ) ) {
			$this->payments_api_client->start_server_connection( WC_Payment_Gateway_WCPay::get_settings_url() );
			return;
		}

		if ( isset( $_GET['wcpay-connect'] ) && check_admin_referer( 'wcpay-connect' ) ) {
			$wcpay_connect_param = sanitize_text_field( wp_unslash( $_GET['wcpay-connect'] ) );

			// Hide menu notification badge upon starting setup.
			update_option( 'wcpay_menu_badge_hidden', 'yes' );

			if ( isset( $_GET['wcpay-connect-jetpack-success'] ) && ! $this->payments_api_client->is_server_connected() ) {
				$this->redirect_to_onboarding_page(
					__( 'Connection to WordPress.com failed. Please connect to WordPress.com to start using WooCommerce Payments.', 'woocommerce-payments' )
				);
				return;
			}

			try {
				$this->maybe_init_jetpack_connection( $wcpay_connect_param );
			} catch ( Exception $e ) {
				$this->redirect_to_onboarding_page(
				/* translators: error message. */
					sprintf( __( 'There was a problem connecting this site to WordPress.com: "%s"', 'woocommerce-payments' ), $e->getMessage() )
				);
				return;
			}

			try {
				$this->init_stripe_oauth( $wcpay_connect_param );
			} catch ( Exception $e ) {
				Logger::error( 'Init Stripe oauth flow failed. ' . $e );
				$this->add_notice_to_settings_page(
					__( 'There was a problem redirecting you to the account connection page. Please try again.', 'woocommerce-payments' ),
					'notice-error'
				);
			}
			return;
		}

		if (
			isset( $_GET['wcpay-state'] )
			&& isset( $_GET['wcpay-mode'] )
		) {
			$state = sanitize_text_field( wp_unslash( $_GET['wcpay-state'] ) );
			$mode  = sanitize_text_field( wp_unslash( $_GET['wcpay-mode'] ) );
			$this->finalize_connection( $state, $mode );
			return;
		}
	}

	/**
	 * Get Stripe login url
	 *
	 * @return string Stripe account login url.
	 */
	public static function get_login_url() {
		return add_query_arg(
			[
				'wcpay-login' => '1',
				'_wpnonce'    => wp_create_nonce( 'wcpay-login' ),
			]
		);
	}

	/**
	 * Get Stripe connect url
	 *
	 * @return string Stripe account login url.
	 */
	public static function get_connect_url() {
		return wp_nonce_url( add_query_arg( [ 'wcpay-connect' => '1' ] ), 'wcpay-connect' );
	}

	/**
	 * Payments task page url
	 *
	 * @return string payments task page url
	 */
	public static function get_payments_task_page_url() {
		return add_query_arg(
			[
				'page'   => 'wc-admin',
				'task'   => 'payments',
				'method' => 'wcpay',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get overview page url
	 *
	 * @return string overview page url
	 */
	public static function get_overview_page_url() {
		return add_query_arg(
			[
				'page' => 'wc-admin',
				'path' => '/payments/overview',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Checks if the current page is overview page
	 *
	 * @return boolean
	 */
	public static function is_overview_page() {
		return isset( $_GET['path'] ) && '/payments/overview' === $_GET['path'];
	}

	/**
	 * Get WPCOM/Jetpack reconnect url, for use in case of missing connection owner.
	 *
	 * @return string WPCOM/Jetpack reconnect url.
	 */
	public static function get_wpcom_reconnect_url() {
		return admin_url(
			add_query_arg(
				[
					'wcpay-reconnect-wpcom' => '1',
					'_wpnonce'              => wp_create_nonce( 'wcpay-reconnect-wpcom' ),
				],
				'admin.php'
			)
		);
	}


	/**
	 * Has on-boarding been disabled?
	 *
	 * @return boolean
	 */
	public static function is_on_boarding_disabled() {
		// If the transient isn't set at all, we'll get false indicating that the server hasn't informed us that
		// on-boarding has been disabled (i.e. it's enabled as far as we know).
		return get_transient( self::ON_BOARDING_DISABLED_TRANSIENT );
	}

	/**
	 * Starts the Jetpack connection flow if it's not already fully connected.
	 *
	 * @param string $wcpay_connect_from - where the user should be returned to after connecting.
	 *
	 * @throws API_Exception If there was an error when registering the site on WP.com.
	 */
	private function maybe_init_jetpack_connection( $wcpay_connect_from ) {
		$is_jetpack_fully_connected = $this->payments_api_client->is_server_connected() && $this->payments_api_client->has_server_connection_owner();
		if ( $is_jetpack_fully_connected ) {
			return;
		}

		$redirect = add_query_arg(
			[
				'wcpay-connect'                 => $wcpay_connect_from,
				'wcpay-connect-jetpack-success' => '1',
				'_wpnonce'                      => wp_create_nonce( 'wcpay-connect' ),
			],
			$this->get_oauth_return_url( $wcpay_connect_from )
		);
		$this->payments_api_client->start_server_connection( $redirect );
	}

	/**
	 * For the connected account, fetches the login url from the API and redirects to it
	 */
	private function redirect_to_login() {
		// Clear account transient when generating Stripe dashboard's login link.
		$this->clear_cache();
		$redirect_url = $this->is_overview_page() ? $this->get_overview_page_url() : WC_Payment_Gateway_WCPay::get_settings_url();

		$login_data = $this->payments_api_client->get_login_data( $redirect_url );
		wp_safe_redirect( $login_data['url'] );
		exit;
	}

	/**
	 * Builds the URL to return the user to after the Jetpack/OAuth flow.
	 *
	 * @param string $wcpay_connect_from - Constant to decide where the user should be returned to after connecting.
	 * @return string
	 */
	private function get_oauth_return_url( $wcpay_connect_from ) {
		// If connection originated on the WCADMIN payment task page, return there.
		// else goto the overview page, since now it is GA (earlier it was redirected to plugin settings page).
		return 'WCADMIN_PAYMENT_TASK' === $wcpay_connect_from
			? $this->get_payments_task_page_url()
			: $this->get_overview_page_url();
	}

	/**
	 * Initializes the OAuth flow by fetching the URL from the API and redirecting to it.
	 *
	 * @param string $wcpay_connect_from - where the user should be returned to after connecting.
	 */
	private function init_stripe_oauth( $wcpay_connect_from ) {
		// Clear account transient when generating Stripe's oauth data.
		$this->clear_cache();

		$current_user = wp_get_current_user();
		$return_url   = $this->get_oauth_return_url( $wcpay_connect_from );

		$country = WC()->countries->get_base_country();
		if ( ! array_key_exists( $country, WC_Payments_Utils::supported_countries() ) ) {
			$country = null;
		}

		$oauth_data = $this->payments_api_client->get_oauth_data(
			$return_url,
			[
				'email'         => $current_user->user_email,
				'business_name' => get_bloginfo( 'name' ),
				'url'           => get_home_url(),
				'country'       => $country,
			],
			[
				'site_username' => $current_user->user_login,
			],
			$this->get_actioned_notes()
		);

		// If an account already exists for this site, we're done.
		if ( false === $oauth_data['url'] ) {
			WC_Payments::get_gateway()->update_option( 'enabled', 'yes' );
			update_option( '_wcpay_oauth_stripe_connected', [ 'is_existing_stripe_account' => true ] );
			wp_safe_redirect(
				add_query_arg(
					[ 'wcpay-connection-success' => '1' ],
					$return_url
				)
			);
			exit;
		}

		set_transient( 'wcpay_stripe_oauth_state', $oauth_data['state'], DAY_IN_SECONDS );

		wp_safe_redirect( $oauth_data['url'] );
		exit;
	}

	/**
	 * Once the API redirects back to the site after the OAuth flow, verifies the parameters and stores the data
	 *
	 * @param string $state Secret string.
	 * @param string $mode Mode in which this account has been connected. Either 'test' or 'live'.
	 */
	private function finalize_connection( $state, $mode ) {
		if ( get_transient( 'wcpay_stripe_oauth_state' ) !== $state ) {
			$this->add_notice_to_settings_page(
				__( 'There was a problem processing your account data. Please try again.', 'woocommerce-payments' ),
				'notice-error'
			);
			return;
		}
		delete_transient( 'wcpay_stripe_oauth_state' );
		$this->clear_cache();

		WC_Payments::get_gateway()->update_option( 'enabled', 'yes' );
		WC_Payments::get_gateway()->update_option( 'test_mode', 'test' === $mode ? 'yes' : 'no' );

		// Store a state after completing KYC for tracks. This is stored temporarily in option because
		// user might not have agreed to TOS yet.
		update_option( '_wcpay_oauth_stripe_connected', [ 'is_existing_stripe_account' => false ] );

		wp_safe_redirect(
			add_query_arg(
				[
					'wcpay-state'                => false,
					'wcpay-account-id'           => false,
					'wcpay-live-publishable-key' => false,
					'wcpay-test-publishable-key' => false,
					'wcpay-mode'                 => false,
					'wcpay-connection-success'   => '1',
				]
			)
		);
		exit;
	}

	/**
	 * Gets and caches the data for the account connected to this site.
	 *
	 * @return array|bool Account data or false if failed to retrieve account data.
	 */
	public function get_cached_account_data() {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return [];
		}

		$account = $this->read_account_from_cache();

		if ( $this->is_valid_cached_account( $account ) ) {
			return $account;
		}

		// If the option contains the error value, return false early and do not attempt another API call.
		if ( self::ACCOUNT_RETRIEVAL_ERROR === $account ) {
			return false;
		}

		try {
			// Since we're about to call the server again, clear out the on-boarding disabled flag. We can let the code
			// below re-create it if the server tells us on-boarding is still disabled.
			delete_transient( self::ON_BOARDING_DISABLED_TRANSIENT );

			$account = $this->payments_api_client->get_account_data();
		} catch ( API_Exception $e ) {
			if ( 'wcpay_account_not_found' === $e->get_error_code() ) {
				// Special case - detect account not connected and cache it.
				$account = [];
			} elseif ( 'wcpay_on_boarding_disabled' === $e->get_error_code() ) {
				// Special case - detect account not connected and on-boarding disabled. This will get updated the
				// next time we call the server for account information, but just in case we set the expiry time for
				// this setting an hour longer than the account details transient.
				$account = [];
				set_transient( self::ON_BOARDING_DISABLED_TRANSIENT, true, 2 * HOUR_IN_SECONDS );
			} else {
				// Failed to retrieve account data. Exception is logged in http client.
				// Rate limit the account retrieval failures - set a transient for a short time.
				$this->cache_account( self::ACCOUNT_RETRIEVAL_ERROR, 2 * MINUTE_IN_SECONDS );

				// Return false to signal account retrieval error.
				return false;
			}
		}

		// Cache the account details so we don't call the server every time.
		$this->cache_account( $account );

		// Allow us to tie in functionality to an account refresh.
		do_action( 'woocommerce_payments_account_refreshed', $account );
		return $account;
	}

	/**
	 * Caches account data for a period of time.
	 *
	 * @param array|string $account    - Account data to cache.
	 * @param int|null     $expiration - The length of time to cache the account data, expressed in seconds.
	 */
	private function cache_account( $account, int $expiration = null ) {
		// Default expiration to 2 hours if not set.
		if ( null === $expiration ) {
			$expiration = 2 * HOUR_IN_SECONDS;
		}

		// Add the account data and expiry time to the array we're caching.
		$account_cache            = [];
		$account_cache['account'] = $account;
		$account_cache['expires'] = time() + $expiration;

		// Create or update the account option cache.
		if ( false === get_option( self::ACCOUNT_OPTION ) ) {
			$result = add_option( self::ACCOUNT_OPTION, $account_cache, '', 'no' );
		} else {
			$result = update_option( self::ACCOUNT_OPTION, $account_cache, 'no' );
		}

		return $result;
	}

	/**
	 * Refetches account data and returns the fresh data.
	 *
	 * @return array|bool|string Either the new account data or false if unavailable.
	 */
	public function refresh_account_data() {
		$this->clear_cache();
		return $this->get_cached_account_data();
	}

	/**
	 * Checks if the cached account can be used in the current plugin state.
	 *
	 * @param bool|string|array $account cached account data.
	 *
	 * @return bool True if the cached account is valid.
	 */
	private function is_valid_cached_account( $account ) {
		// false means no account has been cached.
		if ( false === $account ) {
			return false;
		}

		// the rate limiting mechanism has detected an error - not a valid account.
		if ( self::ACCOUNT_RETRIEVAL_ERROR === $account ) {
			return false;
		}

		// empty array - special value to indicate that there's no account connected.
		if ( is_array( $account ) && empty( $account ) ) {
			return true;
		}

		// live accounts are always valid.
		if ( $account['is_live'] ) {
			return true;
		}

		// test accounts are valid only when in dev mode.
		if ( WC_Payments::get_gateway()->is_in_dev_mode() ) {
			return true;
		}

		return false;
	}

	/**
	 * Adds a notice that will be forced to be visible on the settings page, despite WcAdmin hiding other notices.
	 *
	 * @param string $message Notice message.
	 * @param string $classes Classes to apply, for example notice-error, notice-success.
	 */
	private function add_notice_to_settings_page( $message, $classes ) {
		$classes .= ' wcpay-settings-notice'; // add a class that will be shown on the settings page.
		add_filter(
			'admin_notices',
			function () use ( $message, $classes ) {
				WC_Payments::display_admin_notice( $message, $classes );
			}
		);
	}

	/**
	 * Updates Stripe account settings.
	 *
	 * @param array $stripe_account_settings Settings to update.
	 *
	 * @return null|string Error message if update failed.
	 */
	public function update_stripe_account( $stripe_account_settings ) {
		try {
			if ( ! $this->settings_changed( $stripe_account_settings ) ) {
				Logger::info( 'Skip updating account settings. Nothing is changed.' );
				return;
			}
			$updated_account = $this->payments_api_client->update_account( $stripe_account_settings );
			$this->cache_account( $updated_account );
		} catch ( Exception $e ) {
			Logger::error( 'Failed to update Stripe account ' . $e );
			return $e->getMessage();
		}
	}

	/**
	 * Checks if account settings changed.
	 *
	 * @param array $changes Account settings changes.
	 *
	 * @return bool True if at least one parameter value is changed.
	 */
	private function settings_changed( $changes = [] ) {
		$account = $this->read_account_from_cache();

		// Consider changes as valid if we don't have cached account data.
		if ( ! $this->is_valid_cached_account( $account ) ) {
			return true;
		}

		$diff = array_diff_assoc( $changes, $account );
		return ! empty( $diff );
	}

	/**
	 * Retrieves the latest ToS agreement for the account.
	 *
	 * @return array|null Either the agreement or null if unavailable.
	 */
	public function get_latest_tos_agreement() {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['latest_tos_agreement'] )
			? $account['latest_tos_agreement']
			: null;
	}

	/**
	 * Returns an array containing the names of all the WCPay related notes that have be actioned.
	 *
	 * @return array
	 */
	private function get_actioned_notes(): array {
		$wcpay_note_names = [];

		try {
			/**
			 * Data Store for admin notes
			 *
			 * @var DataStore $data_store
			 */
			$data_store = WC_Data_Store::load( 'admin-note' );
		} catch ( Exception $e ) {
			// Don't stop the on-boarding process if something goes wrong here. Log the error and return the empty array
			// of actioned notes.
			Logger::error( $e );
			return $wcpay_note_names;
		}

		// Fetch the last 10 actioned wcpay-promo admin notifications.
		$add_like_clause = function( $where_clause ) {
			return $where_clause . " AND name like 'wcpay-promo-%'";
		};

		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();

		add_filter( 'woocommerce_note_where_clauses', $add_like_clause );

		$wcpay_promo_notes = $data_store->get_notes(
			[
				'status'     => [ $note_class::E_WC_ADMIN_NOTE_ACTIONED ],
				'is_deleted' => false,
				'per_page'   => 10,
			]
		);

		remove_filter( 'woocommerce_note_where_clauses', $add_like_clause );

		// If we didn't get an array back from the data store, return an empty array of results.
		if ( ! is_array( $wcpay_promo_notes ) ) {
			return $wcpay_note_names;
		}

		// Copy the name of each note into the results.
		foreach ( (array) $wcpay_promo_notes as $wcpay_note ) {
			$note               = new $note_class( $wcpay_note->note_id );
			$wcpay_note_names[] = $note->get_name();
		}

		return $wcpay_note_names;
	}

	/**
	 * Gets the account country.
	 *
	 * @return string Country.
	 */
	public function get_account_country() {
		$account = $this->get_cached_account_data();
		return $account['country'] ?? 'US';
	}

	/**
	 * Handles adding a note if the merchant is eligible for Instant Deposits.
	 *
	 * @param array $account The account data.
	 *
	 * @return void
	 */
	public function handle_instant_deposits_inbox_note( $account ) {
		if ( empty( $account ) ) {
			return;
		}

		if ( ! $this->is_instant_deposits_eligible( $account ) ) {
			return;
		}

		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-instant-deposits-eligible.php';
		WC_Payments_Notes_Instant_Deposits_Eligible::possibly_add_note();
		$this->maybe_add_instant_deposit_note_reminder();
	}

	/**
	 * Handles removing note about merchant Instant Deposits eligibility.
	 * Hands off to handle_instant_deposits_inbox_note to add the new note.
	 *
	 * @return void
	 */
	public function handle_instant_deposits_inbox_reminder() {
		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-instant-deposits-eligible.php';
		WC_Payments_Notes_Instant_Deposits_Eligible::possibly_delete_note();
		$this->handle_instant_deposits_inbox_note( $this->get_cached_account_data() );
	}

	/**
	 * Handles adding scheduled action for the Instant Deposit note reminder.
	 *
	 * @return void
	 */
	public function maybe_add_instant_deposit_note_reminder() {
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->payments_api_client );
		$action_hook              = 'wcpay_instant_deposit_reminder';

		if ( $action_scheduler_service->pending_action_exists( $action_hook ) ) {
			return;
		}

		$reminder_time = time() + ( 90 * DAY_IN_SECONDS );
		$action_scheduler_service->schedule_job( $reminder_time, $action_hook );
	}

	/**
	 * Checks to see if the account is eligible for Instant Deposits.
	 *
	 * @param array $account The account data.
	 *
	 * @return bool
	 */
	private function is_instant_deposits_eligible( array $account ): bool {
		if ( ! isset( $account['instant_deposits_eligible'] ) || ! $account['instant_deposits_eligible'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Read the account from the WP option we cache it in.
	 *
	 * @return array|string|bool
	 */
	private function read_account_from_cache() {
		$account_cache = get_option( self::ACCOUNT_OPTION );

		if ( false === $account_cache || ! isset( $account_cache['account'] ) || ! isset( $account_cache['expires'] ) ) {
			// No option found or the data isn't in the shape we expect.
			return false;
		}

		// Set $account to false if the cache has expired, triggering another fetch.
		if ( $account_cache['expires'] < time() ) {
			return false;
		}

		// We have fresh account data in the cache, so return it.
		return $account_cache['account'];
	}
}

<?php
/**
 * Class WC_Payments_Account
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Logger;
use \Automattic\WooCommerce\Admin\Features\Onboarding;

/**
 * Class handling any account connection functionality
 */
class WC_Payments_Account {

	const ACCOUNT_TRANSIENT              = 'wcpay_account_data';
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
		add_action( 'admin_init', [ $this, 'check_stripe_account_status' ], 11 ); // Run this after the WC setup wizard redirection logic.
		add_filter( 'allowed_redirect_hosts', [ $this, 'allowed_redirect_hosts' ] );
		add_action( 'jetpack_site_registered', [ $this, 'clear_cache' ] );
	}

	/**
	 * Wipes the account transient, forcing to re-fetch the account status from WP.com.
	 */
	public function clear_cache() {
		delete_transient( self::ACCOUNT_TRANSIENT );
	}

	/**
	 * Return connected account ID
	 *
	 * @return string|null Account ID if connected, null if not connected or on error
	 *
	 * @throws Exception Bubbles up if get_account_data call fails.
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
	 *
	 * @throws Exception Bubbles up if get_account_data call fails.
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
	 * Checks if the account is connected, assumes the value of $on_error on server error
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
	 * Checks if the account is connected, throws on server error
	 *
	 * @return bool True if the account is connected, false otherwise.
	 *
	 * @throws Exception Bubbles up if get_account_data call fails.
	 */
	public function try_is_stripe_connected() {
		$account = $this->get_cached_account_data();

		if ( is_array( $account ) && empty( $account ) ) {
			// empty array means no account.
			return false;
		}

		return true;
	}

	/**
	 * Gets the account status data for rendering on the settings page.
	 *
	 * @return array An array containing the status data.
	 */
	public function get_account_status_data() {
		try {
			$account = $this->get_cached_account_data();
		} catch ( Exception $e ) {
			return [
				'error' => true,
			];
		}

		if ( is_array( $account ) && empty( $account ) ) {
			// empty array means no account. This data should not be used when the account is not connected.
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
	 * @throws WC_Payments_API_Exception Bubbles up from get_cached_account_data.
	 */
	public function get_statement_descriptor() {
		$account = $this->get_cached_account_data();
		return isset( $account['statement_descriptor'] ) ? $account['statement_descriptor'] : '';
	}

	/**
	 * Utility function to immediately redirect to the main "Welcome to WooCommerce Payments" onboarding page.
	 * Note that this function immediately ends the execution.
	 *
	 * @param string $error_message Optional error message to show in a notice.
	 */
	private function redirect_to_onboarding_page( $error_message = null ) {
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
	 * Whether to do a full-page redirect to the WCPay onboarding page. It has several exceptions, to prevent
	 * "hijacking" the multiple WC/WC-Admin onboarding flows.
	 * This function assumes that the Stripe account hasn't been setup yet.
	 *
	 * @return bool True if the user should be redirected to the WCPay onboarding page, false otherwise.
	 */
	private function should_redirect_to_onboarding() {
		// If the user is in the WCPay settings screen and hasn't onboarded yet, always redirect.
		if ( WC_Payment_Gateway_WCPay::is_current_page_settings() ) {
			return true;
		}

		return get_option( 'wcpay_should_redirect_to_onboarding', false );
	}

	/**
	 * Checks if Stripe account is connected and redirects to the onboarding page if it is not.
	 *
	 * @return bool True if the account is connected properly.
	 */
	public function check_stripe_account_status() {
		if ( wp_doing_ajax() ) {
			return;
		}

		try {
			$account = $this->get_cached_account_data();
		} catch ( Exception $e ) {
			// Return early. The exceptions have been logged in the http client.
			return false;
		}

		if ( empty( $account ) ) {
			if ( $this->should_redirect_to_onboarding() ) {
				update_option( 'wcpay_should_redirect_to_onboarding', false );
				$this->redirect_to_onboarding_page();
			}
			return false;
		}
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

		if ( isset( $_GET['wcpay-connection-success'] ) ) {
			$account_status = $this->get_account_status_data();
			if ( empty( $account_status['error'] ) && $account_status['paymentsEnabled'] ) {
				$message = __( 'Thanks for verifying your business details. You\'re ready to start taking payments!', 'woocommerce-payments' );
			} else {
				$message = __( 'Thanks for verifying your business details!', 'woocommerce-payments' );
			}
			$this->add_notice_to_settings_page( $message, 'notice-success' );
			return;
		}

		if ( isset( $_GET['wcpay-connect'] ) && check_admin_referer( 'wcpay-connect' ) ) {
			$wcpay_connect_param = sanitize_text_field( wp_unslash( $_GET['wcpay-connect'] ) );

			if ( ! $this->payments_api_client->is_server_connected() && isset( $_GET['wcpay-connect-jetpack-success'] ) ) {
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
	 * @throws WC_Payments_API_Exception If there was an error when registering the site on WP.com.
	 */
	private function maybe_init_jetpack_connection( $wcpay_connect_from ) {
		$is_jetpack_fully_connected = $this->payments_api_client->is_server_connected();
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

		$login_data = $this->payments_api_client->get_login_data( WC_Payment_Gateway_WCPay::get_settings_url() );
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
		// Usually the return URL is the WCPay plugin settings page.
		// But if connection originated on the WCADMIN payment task page, return there.
		return 'WCADMIN_PAYMENT_TASK' === $wcpay_connect_from
			? add_query_arg(
				[
					'page'   => 'wc-admin',
					'task'   => 'payments',
					'method' => 'wcpay',
				],
				admin_url( 'admin.php' )
			)
			: WC_Payment_Gateway_WCPay::get_settings_url();
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

		$oauth_data = $this->payments_api_client->get_oauth_data(
			$return_url,
			[
				'email'         => $current_user->user_email,
				'business_name' => get_bloginfo( 'name' ),
				'url'           => get_home_url(),
			]
		);

		// If an account already exists for this site, we're done.
		if ( false === $oauth_data['url'] ) {
			WC_Payments::get_gateway()->update_option( 'enabled', 'yes' );
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
	 * @return array Account data;
	 *
	 * @throws WC_Payments_API_Exception Bubbles up if get_account_data call fails.
	 */
	private function get_cached_account_data() {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return [];
		}

		$account = get_transient( self::ACCOUNT_TRANSIENT );

		if ( $this->is_valid_cached_account( $account ) ) {
			return $account;
		}

		try {
			// Since we're about to call the server again, clear out the on-boarding disabled flag. We can let the code
			// below re-create it if the server tells us on-boarding is still disabled.
			delete_transient( self::ON_BOARDING_DISABLED_TRANSIENT );

			$account = $this->payments_api_client->get_account_data();
		} catch ( WC_Payments_API_Exception $e ) {
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
				throw $e;
			}
		}

		// Cache the account details so we don't call the server every time.
		$this->cache_account( $account );
		return $account;
	}

	/**
	 * Caches account data for two hours
	 *
	 * @param array $account - Account data to cache.
	 */
	private function cache_account( $account ) {
		set_transient( self::ACCOUNT_TRANSIENT, $account, 2 * HOUR_IN_SECONDS );
	}

	/**
	 * Refetches account data.
	 */
	public function refresh_account_data() {
		try {
			delete_transient( self::ACCOUNT_TRANSIENT );
			$this->get_cached_account_data();
		} catch ( Exception $e ) {
			Logger::error( "Failed to refresh account data. Error: $e" );
		}
	}

	/**
	 * Checks if the cached account can be used in the current plugin state.
	 *
	 * @param bool|array $account cached account data.
	 *
	 * @return bool True if the cached account is valid.
	 */
	private function is_valid_cached_account( $account ) {
		// false means no account has been cached.
		if ( false === $account ) {
			return false;
		}

		// empty array - special value to indicate that there's no account connected.
		if ( empty( $account ) ) {
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
		$account = get_transient( self::ACCOUNT_TRANSIENT );

		// Consider changes as valid if we don't have cached account data.
		if ( ! $this->is_valid_cached_account( $account ) ) {
			return true;
		}

		$diff = array_diff_assoc( $changes, $account );
		return ! empty( $diff );
	}
}

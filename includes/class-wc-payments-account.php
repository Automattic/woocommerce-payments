<?php
/**
 * Class WC_Payments_Account
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class handling any account connection functionality
 */
class WC_Payments_Account {

	const ACCOUNT_TRANSIENT = 'wcpay_account_data';

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

		add_action( 'admin_init', array( $this, 'check_stripe_account_status' ) );
		add_action( 'woocommerce_init', array( $this, 'maybe_handle_oauth' ) );
		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
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
	 * Checks if Stripe account is connected and displays admin notices if it is not.
	 *
	 * @return bool True if the account is connected properly.
	 */
	public function check_stripe_account_status() {
		try {
			$account = $this->get_cached_account_data();
		} catch ( Exception $e ) {
			$message = sprintf(
				/* translators: %1: error message */
				__( 'Could not fetch data for your account: "%1$s"', 'woocommerce-payments' ),
				$e->getMessage()
			);

			add_filter(
				'admin_notices',
				function () use ( $message ) {
					WC_Payments::display_admin_error( $message );
				}
			);

			return false;
		}

		if ( empty( $account ) ) {
			$message = self::get_connect_message();
			add_filter(
				'admin_notices',
				function () use ( $message ) {
					WC_Payments::display_admin_notice(
						$message,
						'notice-success'
					);
				}
			);
			return false;
		}

		if ( $account['has_pending_requirements'] ) {
			$message = $this->get_verify_requirements_message( $account['current_deadline'] );
			add_filter(
				'admin_notices',
				function () use ( $message ) {
					WC_Payments::display_admin_error( $message );
				}
			);
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
				WC_Payments::get_gateway()->add_error( __( 'There was a problem redirecting you to the account dashboard. Please try again.', 'woocommerce-payments' ) );
			}
		}

		if ( isset( $_GET['wcpay-connect'] ) && check_admin_referer( 'wcpay-connect' ) ) {
			try {
				$this->init_oauth();
			} catch ( Exception $e ) {
				WC_Payments::get_gateway()->add_error( __( 'There was a problem redirecting you to the account connection page. Please try again.', 'woocommerce-payments' ) );
			}
			return;
		}

		if (
			isset( $_GET['wcpay-state'] )
			&& isset( $_GET['wcpay-account-id'] )
			&& isset( $_GET['wcpay-live-publishable-key'] )
			&& isset( $_GET['wcpay-test-publishable-key'] )
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
		return wp_nonce_url( add_query_arg( [ 'wcpay-login' => '1' ] ), 'wcpay-login' );
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
	 * Get Stripe connect message with connect link
	 */
	public static function get_connect_message() {
		return sprintf(
			/* translators: 1) oauth entry point URL */
			__( 'Accept credit cards online. Simply verify your business details to activate WooCommerce Payments. <a href="%1$s">Get started</a>', 'woocommerce-payments' ),
			self::get_connect_url()
		);
	}

	/**
	 * For the connected account, fetches the login url from the API and redirects to it
	 */
	private function redirect_to_login() {
		// Clear account transient when generating Stripe dashboard's login link.
		delete_transient( self::ACCOUNT_TRANSIENT );

		$login_data = $this->payments_api_client->get_login_data( WC_Payment_Gateway_WCPay::get_settings_url() );
		wp_safe_redirect( $login_data['url'] );
		exit;
	}

	/**
	 * Initializes the OAuth flow by fetching the URL from the API and redirecting to it
	 */
	private function init_oauth() {
		// Clear account transient when generating Stripe's oauth data.
		delete_transient( self::ACCOUNT_TRANSIENT );

		$current_user = wp_get_current_user();

		$oauth_data = $this->payments_api_client->get_oauth_data(
			WC_Payment_Gateway_WCPay::get_settings_url(),
			array(
				'email'         => $current_user->user_email,
				'business_name' => get_bloginfo( 'name' ),
			)
		);

		if ( false === $oauth_data['url'] ) {
			$account_id           = sanitize_text_field( wp_unslash( $oauth_data['account_id'] ) );
			$live_publishable_key = sanitize_text_field( wp_unslash( $oauth_data['live_publishable_key'] ) );
			$test_publishable_key = sanitize_text_field( wp_unslash( $oauth_data['test_publishable_key'] ) );

			WC_Payments::get_gateway()->update_option( 'stripe_account_id', $account_id );
			WC_Payments::get_gateway()->update_option( 'publishable_key', $live_publishable_key );
			WC_Payments::get_gateway()->update_option( 'test_publishable_key', $test_publishable_key );
			WC_Payments::get_gateway()->update_option( 'enabled', 'yes' );

			if ( (bool) $oauth_data['is_live'] ) {
				delete_option( 'connected_account_is_dev' );
			} else {
				update_option( 'connected_account_is_dev', true );
			}

			wp_safe_redirect( WC_Payment_Gateway_WCPay::get_settings_url() );
			exit;
		}

		set_transient( 'wcpay_oauth_state', $oauth_data['state'], DAY_IN_SECONDS );

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
		if ( get_transient( 'wcpay_oauth_state' ) !== $state ) {
			WC_Payments::get_gateway()->add_error( __( 'There was a problem processing your account data. Please try again.', 'woocommerce-payments' ) );
			return;
		}
		delete_transient( 'wcpay_oauth_state' );

		$test_mode = 'test' === $mode;
		if ( $test_mode ) {
			update_option( 'connected_account_is_dev', true );
		} else {
			delete_option( 'connected_account_is_dev' );
		}

		WC_Payments::get_gateway()->update_option( 'stripe_account_id', $account_id );
		$this->update_public_keys( $live_publishable_key, $test_publishable_key );
		WC_Payments::get_gateway()->update_option( 'enabled', 'yes' );
		WC_Payments::get_gateway()->update_option( 'test_mode', 'test' === $mode ? 'yes' : 'no' );

		wp_safe_redirect( remove_query_arg( [ 'wcpay-state', 'wcpay-account-id', 'wcpay-publishable-key', 'wcpay-mode' ] ) );
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
		$account = get_transient( self::ACCOUNT_TRANSIENT );

		if ( false !== $account ) {
			return $account;
		}

		try {
			$account = $this->payments_api_client->get_account_data();
		} catch ( WC_Payments_API_Exception $e ) {
			if ( 'wcpay_account_not_found' === $e->get_error_code() ) {
				// special case - detect account not connected and cache it.
				$account = array();
			} else {
				throw $e;
			}
		}

		set_transient( self::ACCOUNT_TRANSIENT, $account, 2 * HOUR_IN_SECONDS );
		return $account;
	}

	/**
	 * Get Stripe pending requirements message with dashboard link, based on current deadline.
	 *
	 * If $current_deadline is null, it means that the requirements are already past due.
	 *
	 * TODO: Payouts is a Stripe dashboard terminology and it's being used here to avoid confusion.
	 * Once we have our custom dashboard running, Payouts should be renamed to Deposits.
	 *
	 * @param int|null $current_deadline Timestamp for when the requirements are due.
	 */
	private function get_verify_requirements_message( $current_deadline = null ) {
		if ( ! empty( $current_deadline ) ) {
			return sprintf(
				/* translators: 1) formatted requirements current deadline 2) dashboard login URL */
				__( 'We require additional details about your business. Please provide the required information by %1$s to avoid an interruption in your scheduled payouts. <a href="%2$s">Update now</a>', 'woocommerce-payments' ),
				/* translators: date time format to display deadline in "...provide the required information by %1$s to avoid an..."*/
				date_i18n( __( 'ga M j, Y', 'woocommerce-payments' ), $current_deadline ),
				self::get_login_url()
			);
		}

		return sprintf(
			/* translators: 1) dashboard login URL */
			__( 'Your payouts have been suspended. We require additional details about your business. Please provide the requested information so you may continue to receive your payouts. <a href="%1$s">Update now</a>', 'woocommerce-payments' ),
			self::get_login_url()
		);
	}

	/**
	 * Updates the publishable key settings on the gateway.
	 *
	 * @param string $live_key Live publishable key.
	 * @param string $test_key Test publishable key.
	 */
	private function update_public_keys( $live_key, $test_key ) {
		if ( ! empty( $live_key ) ) {
			if ( get_option( 'connected_account_is_dev', false ) ) {
				$this->gateway->update_option( 'publishable_key', $test_key );
			} else {
				$this->gateway->update_option( 'publishable_key', $live_key );
			}
		}

		if ( ! empty( $test_key ) ) {
			$this->gateway->update_option( 'test_publishable_key', $test_key );
		}
	}
}

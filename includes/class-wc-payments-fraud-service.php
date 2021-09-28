<?php
/**
 * WC_Payments_Fraud_Service class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Exceptions\API_Exception;
use WCPay\Logger;

/**
 * Class which includes all the fraud-specific logic.
 */
class WC_Payments_Fraud_Service {
	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_Payments_Customer instance for working with customer information
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * Constructor for WC_Payments_Fraud_Service.
	 *
	 * @param WC_Payments_API_Client       $payments_api_client      - WooCommerce Payments API client.
	 * @param WC_Payments_Customer_Service $customer_service         - Customer class instance.
	 * @param WC_Payments_Account          $account                  - Account class instance.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Account $account
	) {
		$this->payments_api_client = $payments_api_client;
		$this->customer_service    = $customer_service;
		$this->account             = $account;

		add_filter( 'wcpay_prepare_fraud_config', [ $this, 'prepare_fraud_config' ], 10, 2 );
		add_filter( 'wcpay_current_session_id', [ $this, 'get_session_id' ] );
		add_action( 'init', [ $this, 'link_session_if_user_just_logged_in' ] );
		add_action( 'admin_init', [ $this, 'send_forter_cookie_token' ] );
	}

	/**
	 * Prepares the fraud config for a service.
	 *
	 * @param array  $config     Existing config data for the given anti-fraud service.
	 * @param string $service_id Identifier of the anti-fraud service provider.
	 *
	 * @return array|NULL Array with all the required data to initialize the anti-fraud script, or NULL if the service shouldn't be used.
	 */
	public function prepare_fraud_config( $config, $service_id ) {
		switch ( $service_id ) {
			case 'sift':
				return $this->prepare_sift_config( $config );
			case 'forter':
				return $this->prepare_forter_config( $config );
		}
		return $config;
	}

	/**
	 * Adds site-specific config needed to initialize the SIFT anti-fraud JS.
	 *
	 * @param array $config Associative array with the SIFT-related configuration returned from the server.
	 *
	 * @return array|NULL Assoc array, ready for the client to consume, or NULL if the client shouldn't enqueue this script.
	 */
	private function prepare_sift_config( $config ) {
		// The server returns both production and sandbox beacon keys. Use the sandbox one if test mode is enabled.
		if ( WC_Payments::get_gateway()->is_in_test_mode() ) {
			$config['beacon_key'] = $config['sandbox_beacon_key'];
		}
		unset( $config['sandbox_beacon_key'] );

		$wpcom_blog_id = $this->payments_api_client->get_blog_id();
		if ( ! $wpcom_blog_id ) {
			// Don't enqueue the SIFT script if Jetpack hasn't been connected yet.
			return null;
		}

		$config['user_id'] = '';

		if ( is_user_logged_in() ) {
			if ( is_admin() ) {
				$config['user_id'] = $this->account->get_stripe_account_id();
			} else {
				$customer_id = $this->customer_service->get_customer_id_by_user_id( get_current_user_id() );
				if ( isset( $customer_id ) ) {
					$config['user_id'] = $customer_id;
				}
			}
		}

		if ( $this->check_if_user_just_logged_in() ) {
			$config['session_id'] = $this->get_cookie_session_id();
		} else {
			if ( is_a( WC()->session, 'WC_Session' ) ) {
				$config['session_id'] = $wpcom_blog_id . '_' . WC()->session->get_customer_id();
			} else {
				return null; // we do not have a valid session for the current process.
			}
		}

		return $config;
	}

	/**
	 * Adds site-specific config needed to initialize the Forter anti-fraud JS.
	 *
	 * @param array $config Associative array with the Forter-related configuration returned from the server.
	 *
	 * @return array|NULL Assoc array, ready for the client to consume, or NULL if the client shouldn't enqueue this script.
	 */
	private function prepare_forter_config( $config ) {
		$account_id = $this->account->get_stripe_account_id();
		if ( ! is_admin() || get_option( 'wcpay_forter_token_sent' ) === $account_id ) {
			// Only include Forter in admin pages and if the token has not been sent.
			return null;
		}

		return $config;
	}

	/**
	 * Called after the WooCommerce session has been initialized. Check if the current user has just logged in,
	 * and sends that information to the server to link the current browser session with the user.
	 *
	 * @return boolean True if the user has just logged in, false in any other case.
	 */
	public function check_if_user_just_logged_in() {
		if ( ! get_current_user_id() ) {
			return false;
		}
		WC()->initialize_session();
		$session_handler = WC()->session;
		$cookie          = $session_handler->get_session_cookie();
		if ( ! $cookie ) {
			return false;
		}
		$cookie_customer_id = $cookie[0];
		return $session_handler->get_customer_id() !== $cookie_customer_id;
	}

	/**
	 * Called after the WooCommerce session has been initialized. Check if the current user has just logged in,
	 * and sends that information to the server to link the current browser session with the user.
	 */
	public function link_session_if_user_just_logged_in() {
		$wpcom_blog_id = $this->payments_api_client->get_blog_id();
		if ( ! $wpcom_blog_id ) {
			// Don't do anything if Jetpack hasn't been connected yet.
			return;
		}

		if ( ! $this->check_if_user_just_logged_in() ) {
			return;
		}

		$fraud_config = $this->account->get_fraud_services_config();
		if ( ! isset( $fraud_config['sift'] ) ) {
			// Only Sift needs to send data when the user logs in.
			return;
		}

		// The session changed during the current page load, for example if the user just logged in.
		// In this case, send the old session's customer ID alongside the new user_id so SIFT can link them.
		$customer_id = $this->customer_service->get_customer_id_by_user_id( get_current_user_id() );
		if ( ! isset( $customer_id ) ) {
			return;
		}

		try {
			$this->payments_api_client->link_session_to_customer( $this->get_cookie_session_id(), $customer_id );
		} catch ( API_Exception $e ) {
			Logger::log( '[Tracking] Error when linking session with user: ' . $e->getMessage() );
		}
	}

	/**
	 * Send $token param received to the WCPay server so the current browsing session
	 * can be linked to the account. It will only be sent once.
	 *
	 * @param string|null $token Forter token received from the client.
	 */
	public function send_forter_token( string $token = null ) {
		if ( ! $this->account->is_stripe_connected() || empty( $token ) || ! isset( $this->account->get_fraud_services_config()['forter'] ) ) {
			return;
		}

		$account_id = $this->account->get_stripe_account_id();
		if ( get_option( 'wcpay_forter_token_sent' ) !== $account_id ) {
			// Optimistically set the "Forter token already sent" database option, so it's not sent twice if there are several admin requests in parallel.
			update_option( 'wcpay_forter_token_sent', $account_id );
			try {
				$response = $this->payments_api_client->send_forter_token( $token );
				if ( ! isset( $response['result'] ) || 'success' !== $response['result'] ) {
					delete_option( 'wcpay_forter_token_sent' );
				}
			} catch ( API_Exception $e ) {
				delete_option( 'wcpay_forter_token_sent' );
				Logger::log( '[Tracking] Error when sending Forter token: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * If a "forterToken" cookie is present call `send_forter_token` with it.
	 */
	public function send_forter_cookie_token() {
		if ( isset( $_COOKIE['forterToken'] ) ) {
			// The cookie contents are opaque to us, so it's better to not sanitize them.
			//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$this->send_forter_token( $_COOKIE['forterToken'] );
		}
	}

	/**
	 * Get the session ID used until now for the current browsing session.
	 *
	 * @return string|NULL Session ID, or NULL if unknown.
	 */
	private function get_cookie_session_id() {
		$wpcom_blog_id = $this->payments_api_client->get_blog_id();
		if ( ! $wpcom_blog_id ) {
			return null;
		}

		$session_handler = WC()->session;
		if ( ! $session_handler ) {
			return null;
		}
		$cookie = $session_handler->get_session_cookie();
		if ( ! $cookie ) {
			return null;
		}
		$cookie_customer_id = $cookie[0];
		return $wpcom_blog_id . '_' . $cookie_customer_id;
	}
}

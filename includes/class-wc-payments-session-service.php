<?php
/**
 * WC_Payments_Session_Service class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Exceptions\API_Exception;

/**
 * Handles sessions and session details.
 */
class WC_Payments_Session_Service {

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Constructor for WC_Payments_Session_Service.
	 *
	 * @param WC_Payments_API_Client $payments_api_client - WooCommerce Payments API client.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client
	) {
		$this->payments_api_client = $payments_api_client;
	}

	/**
	 * Called after the WooCommerce session has been initialized. Check if the current user has just logged in,
	 * and sends that information to the server to link the current browser session with the user.
	 *
	 * @return boolean True if the user has just logged in, false in any other case.
	 */
	public function user_just_logged_in(): bool {
		if ( ! get_current_user_id() ) {
			return false;
		}

		WC()->initialize_session();
		$session_handler = WC()->session;
		// The Store API SessionHandler (used by WooPay) doesn't provide this method.
		if ( ! method_exists( $session_handler, 'get_session_cookie' ) ) {
			return false;
		}
		$cookie = $session_handler->get_session_cookie();
		if ( ! $cookie ) {
			return false;
		}
		$cookie_customer_id = $cookie[0];

		return $session_handler->get_customer_id() !== $cookie_customer_id;
	}

	/**
	 * Get the Sift session ID for the current browsing session.
	 *
	 * @return string|null The Sift session ID or null if it can't be determined.
	 */
	public function get_sift_session_id(): ?string {
		$wpcom_blog_id = $this->payments_api_client->get_blog_id();
		if ( ! $wpcom_blog_id ) {
			// No session ID if Jetpack hasn't been connected yet.
			return null;
		}

		if ( $this->user_just_logged_in() ) {
			return $this->get_cookie_session_id();
		}

		if ( is_a( WC()->session, 'WC_Session' ) ) {
			return $wpcom_blog_id . '_' . WC()->session->get_customer_id();
		}

		return null; // We do not have a valid session for the current process.
	}

	/**
	 * Get the session ID used until now for the current browsing session.
	 *
	 * @return string|null Session ID, or null if unknown.
	 */
	public function get_cookie_session_id(): ?string {
		$wpcom_blog_id = $this->payments_api_client->get_blog_id();
		if ( ! $wpcom_blog_id ) {
			return null;
		}

		$session_handler = WC()->session;
		if ( ! $session_handler ) {
			return null;
		}
		// The Store API SessionHandler (used by WooPay) doesn't provide this method.
		if ( ! method_exists( $session_handler, 'get_session_cookie' ) ) {
			return null;
		}
		$cookie = $session_handler->get_session_cookie();
		if ( ! $cookie ) {
			return null;
		}
		$cookie_customer_id = $cookie[0];
		return $wpcom_blog_id . '_' . $cookie_customer_id;
	}

	/**
	 * Link a customer with the current browsing session, for tracking purposes.
	 *
	 * @param string $customer_id Stripe customer ID.
	 *
	 * @return array An array, containing a `success` flag.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function link_current_session_to_customer( string $customer_id ): array {
		return $this->payments_api_client->link_session_to_customer( $this->get_cookie_session_id(), $customer_id );
	}
}

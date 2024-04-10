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

	const SESSION_STORE_ID_OPTION = 'wcpay_session_store_id';

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
	 * Checks if the user has just logged in.
	 *
	 * The user just logged in if the session cookie contains a different customer ID than the one in the session.
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
		if ( $this->user_just_logged_in() ) {
			return $this->get_cookie_session_id();
		}

		if ( is_a( WC()->session, 'WC_Session' ) ) {
			return $this->generate_session_id( $this->get_store_id(), (string) WC()->session->get_customer_id() );
		}

		return null; // We do not have a valid session for the current process.
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
		return $this->payments_api_client->link_session_to_customer( $this->get_sift_session_id(), $customer_id );
	}

	/**
	 * Get the session ID used until now for the current browsing session.
	 *
	 * It looks for the logged in user ID stored in the session cookie, and uses that to generate a session ID.
	 *
	 * @return string|null Session ID, or null if unknown.
	 */
	public function get_cookie_session_id(): ?string {
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

		return $this->generate_session_id( $this->get_store_id(), (string) $cookie_customer_id );
	}

	/**
	 * Get the store ID for use in sessions.
	 *
	 * This is used to consistently identify the store in WooPayments sessions.
	 * If it doesn't exist, it is generated randomly and stored in the database.
	 *
	 * @return string The store ID or empty string if it can't be determined.
	 */
	public function get_store_id(): string {
		// We will use a stored random store ID.
		$store_id = get_option( self::SESSION_STORE_ID_OPTION, false );
		if ( ! $store_id ) {
			$store_id = $this->generate_store_id();
			update_option( self::SESSION_STORE_ID_OPTION, $store_id );
		}

		return $store_id;
	}

	/**
	 * Generate a random store ID.
	 *
	 * The generated ID is case-sensitive and contains 32 characters.
	 *
	 * @return string The generated store ID.
	 */
	private function generate_store_id(): string {
		// Prefix it with 'st_' (from store) to make it easier to identify.
		$prefix = 'st_';

		// We will generate 32 characters in total, including the prefix length.
		$length = 32 - strlen( $prefix );

		// We will use alphanumerical characters.
		$include_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		// Add some special characters, not all. Play it safe.
		// See the Sift restrictions for $user_id.
		// @link https://sift.com/developers/docs/curl/events-api/fields.
		$include_chars .= '-$:.^!';
		// Finally, shuffle them for extra randomness.
		$include_chars = str_shuffle( $include_chars );

		$char_length   = strlen( $include_chars );
		$random_string = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $include_chars [ wp_rand( 0, $char_length - 1 ) ];
		}

		return $prefix . $random_string;
	}

	/**
	 * Generate a session ID based on the store ID and the user ID.
	 *
	 * @param string $store_id The session store ID.
	 * @param string $user_id  The user ID.
	 *
	 * @return string
	 */
	private function generate_session_id( string $store_id, string $user_id ): string {
		return $store_id . '_' . $user_id;
	}
}

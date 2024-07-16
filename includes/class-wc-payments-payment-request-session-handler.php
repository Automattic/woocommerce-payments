<?php
/**
 * Class WC_Payments_Payment_Request_Session_Handler
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;

/**
 * WC_Payments_Payment_Request_Session_Handler class
 */
final class WC_Payments_Payment_Request_Session_Handler extends WC_Session_Handler {
	/**
	 * Token from HTTP headers.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * The session id to reference in the sessions table.
	 *
	 * @var string
	 */
	public $session_id;

	/**
	 * Expiration timestamp.
	 *
	 * @var int
	 */
	protected $session_expiration;

	/**
	 * Constructor for the session class.
	 */
	public function __construct() {
		parent::__construct();
		$this->token = wc_clean( wp_unslash( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION'] ?? '' ) );
	}

	/**
	 * Init hooks and session data.
	 *
	 * @throws Exception On possible token mismatch.
	 */
	public function init() {
		$this->init_session_cookie();
		$this->init_session_from_token();

		if ( $this->_customer_id !== $this->_data['token_customer_id'] ) {
			// TODO ~FR: should this be handled differently?
			throw new Exception( 'Invalid token: cookie and session customer mismatch' );
		}

		add_action( 'shutdown', [ $this, 'save_data' ], 20 );
	}

	/**
	 * Process the token header to load the correct session.
	 */
	protected function init_session_from_token() {
		$default_value = [
			'token_customer_id' => $this->_customer_id,
		];

		if ( empty( $this->token ) ) {
			$this->session_id = $this->generate_customer_id();
			$this->_data      = $default_value;
			// session_expiration can remain the same.

			return;
		}

		$payload = JsonWebToken::get_parts( $this->token )->payload;

		$this->session_id         = $payload->session_id;
		$this->session_expiration = $payload->exp;
		$this->_data              = (array) $this->get_session( $this->session_id, $default_value );
	}

	/**
	 * Delete the session from the cache and database.
	 *
	 * @param int $customer_id Customer ID.
	 */
	public function delete_session( $customer_id ) {
		parent::delete_session( $this->session_id );
	}

	/**
	 * Update the session expiry timestamp.
	 *
	 * @param string $customer_id Customer ID.
	 * @param int    $timestamp Timestamp to expire the cookie.
	 */
	public function update_session_timestamp( $customer_id, $timestamp ) {
		parent::update_session_timestamp( $this->session_id, $timestamp );
	}

	/**
	 * Forget all session data without destroying it.
	 */
	public function forget_session() {
		parent::forget_session();

		$this->session_id = $this->generate_customer_id();
	}

	/**
	 * Generate a unique customer ID for both logged-in and guest customers.
	 *
	 * Uses Portable PHP password hashing framework to generate a unique cryptographically strong ID.
	 *
	 * @return string
	 */
	public function generate_customer_id() {
		require_once ABSPATH . 'wp-includes/class-phpass.php';
		$hasher = new PasswordHash( 8, false );

		return 't_' . substr( md5( $hasher->get_random_bytes( 32 ) ), 2 );
	}

	/**
	 * Save data  - copy of parent method with a few modifications.
	 *
	 * @param int $old_session_key session ID before user logs in.
	 */
	public function save_data( $old_session_key = 0 ) {
		// Dirty if something changed - prevents saving nothing new.
		if ( $this->_dirty ) {
			global $wpdb;

			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"INSERT INTO $this->_table (`session_key`, `session_value`, `session_expiry`) VALUES (%s, %s, %d)
 					ON DUPLICATE KEY UPDATE `session_value` = VALUES(`session_value`), `session_expiry` = VALUES(`session_expiry`)",
					$this->session_id,
					maybe_serialize( $this->_data ),
					$this->_session_expiration
				)
			);

			wp_cache_set( $this->get_cache_prefix() . $this->session_id, $this->_data, WC_SESSION_CACHE_GROUP, $this->_session_expiration - time() );
			$this->_dirty = false;
		}
	}

	/**
	 * Gets a cache prefix. This is used in session names so the entire cache can be invalidated with 1 function call.
	 *
	 * @return string
	 */
	private function get_cache_prefix() {
		return WC_Cache_Helper::get_cache_prefix( WC_SESSION_CACHE_GROUP );
	}

	/**
	 * Get a session variable.
	 * Overridden default method, so that the `cart` session data always returns a value, in order to prevent the "saved cart after login" feature to get wrong cart data.
	 * See "WC_Cart_Session::get_cart_from_session".
	 *
	 * @param string $key Key to get.
	 * @param mixed  $default used if the session variable isn't set.
	 * @return array|string value of session variable
	 */
	public function get( $key, $default = null ) {
		if ( 'cart' === $key && ! isset( $this->_data['cart'] ) ) {
			return [];
		}

		return parent::get( $key, $default );
	}
}

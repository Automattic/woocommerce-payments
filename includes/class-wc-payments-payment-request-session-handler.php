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

		if ( $this->_customer_id !== $this->_data['token_customer_id'] ) {
			\WCPay\Logger::error(
				sprintf(
					'Tokenized ECE cookie and session customer mismatch - customer: %s (%s) , session: %s (%s)',
					var_export( $this->_customer_id, true ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- should only be triggered when logging is enabled.
					gettype( $this->_customer_id ),
					var_export( $this->_data['token_customer_id'], true ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- should only be triggered when logging is enabled.
					gettype( $this->_data['token_customer_id'] )
				)
			);

			// throwing an exception here to prevent further processing of the request.
			throw new Exception( __( 'Invalid token: cookie and session customer mismatch', 'woocommerce-payments' ) );
		}

		// saving the session and sending new cookies only when we're not dealing with an ephemeral cart.
		if ( wc_clean( wp_unslash( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_IS_EPHEMERAL_CART'] ?? '' ) ) !== '1' ) {
			add_action( 'shutdown', [ $this, 'save_data' ], 20 );
			add_action( 'woocommerce_set_cart_cookies', [ $this, 'set_customer_session_cookie' ] );
		}
	}

	/**
	 * Setup cookie and customer ID.
	 * We need to ensure that we _also_ call `init_session_from_token` when `init_session_cookie` is called.
	 * Otherwise, this clears everything: https://github.com/woocommerce/woocommerce/blob/de4a8ffdd474ca1879d4aa16487d6c52472a861b/plugins/woocommerce/src/StoreApi/Routes/V1/Checkout.php#L556-L558
	 */
	public function init_session_cookie() {
		// If an account has been created after the session has been initialized, update the session.
		// This method is called directly by WC blocks when an account is created right before placing an order.
		$previous_session_data = null;
		parent::init_session_cookie();

		if ( is_user_logged_in() && strval( get_current_user_id() ) !== $this->_customer_id ) {
			$previous_session_data = $this->_data;
			/**
			 * This is borrowed from WooCommerce core, which also converts the user ID to a string.
			 * https://github.com/woocommerce/woocommerce/blob/f01e9452045e2d483649670adc2f042391774e38/plugins/woocommerce/includes/class-wc-session-handler.php#L107
			 *
			 * @psalm-suppress InvalidPropertyAssignmentValue
			 */
			$this->_customer_id = strval( get_current_user_id() );
		}

		$this->init_session_from_token();
		if ( ! empty( $previous_session_data ) ) {
			$this->_data = $previous_session_data;
		}
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
	 * Save data - copy of parent method with a few modifications.
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

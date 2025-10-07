<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Class SessionHandler
 *
 * This is a copy of the Automattic\WooCommerce\StoreApi\SessionHandler class with the addition of an `init_session_cookie` method.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Platform_Checkout;

use Automattic\Jetpack\Constants;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;
use WC_Session;

defined( 'ABSPATH' ) || exit;

/**
 * SessionHandler class
 */
final class SessionHandler extends WC_Session {
	/**
	 * Token from HTTP headers.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Table name for session data.
	 *
	 * @var string Custom session table name
	 */
	protected $table;

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
		$this->token = wc_clean( wp_unslash( $_SERVER['HTTP_CART_TOKEN'] ?? '' ) );
		$this->table = $GLOBALS['wpdb']->prefix . 'woocommerce_sessions';
	}

	/**
	 * Note: This method was added to the original class for compatibility with WooPay.
	 */
	public function init_session_cookie() {
		$this->init();
	}

	/**
	 * Init hooks and session data.
	 */
	public function init() {
		$this->init_session_from_token();
		add_action( 'shutdown', [ $this, 'save_data' ], 20 );
	}

	/**
	 * Return true if the current user has an active session,.
	 *
	 * @return bool
	 */
	public function has_session() {
		return ! empty( $this->token );
	}

	/**
	 * Returns the session.
	 *
	 * @param string $customer_id Customer ID.
	 * @param mixed  $default Default session value.
	 *
	 * @return string|array|bool
	 */
	public function get_session( $customer_id, $default = false ) {
		global $wpdb;

		// This mimics behaviour from default WC_Session_Handler class. There will be no sessions retrieved while WP setup is due.
		if ( Constants::is_defined( 'WP_SETUP_CONFIG' ) ) {
			return false;
		}

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT session_value FROM $this->table WHERE session_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id
			)
		);

		if ( is_null( $value ) ) {
			$value = $default;
		}

		return maybe_unserialize( $value );
	}

	/**
	 * Save data and delete user session.
	 */
	public function save_data() {
		// Dirty if something changed - prevents saving nothing new.
		if ( $this->_dirty ) {
			global $wpdb;

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $this->table (`session_key`, `session_value`, `session_expiry`) VALUES (%s, %s, %d) ON DUPLICATE KEY UPDATE `session_value` = VALUES(`session_value`), `session_expiry` = VALUES(`session_expiry`)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$this->_customer_id,
					maybe_serialize( $this->_data ),
					$this->session_expiration
				)
			);
			wp_cache_set( $this->get_cache_prefix() . $this->_customer_id, $this->_data, WC_SESSION_CACHE_GROUP, $this->session_expiration - time() );
			$this->_dirty = false;
		}
	}

	/**
	 * Get session data.
	 *
	 * @return array
	 */
	public function get_session_data() {
		return $this->has_session() ? (array) $this->get_session( $this->get_customer_id(), [] ) : [];
	}

	/**
	 * Process the token header to load the correct session.
	 */
	protected function init_session_from_token() {
		$payload = JsonWebToken::get_parts( $this->token )->payload;

		$this->_customer_id       = $payload->user_id;
		$this->session_expiration = $payload->exp;
		$this->_data              = (array) $this->get_session( $this->_customer_id, [] );
	}

	/**
	 * Gets a cache prefix. This is used in session names so the entire cache can be invalidated with 1 function call.
	 *
	 * @return string
	 */
	private function get_cache_prefix() {
		return \WC_Cache_Helper::get_cache_prefix( WC_SESSION_CACHE_GROUP );
	}
}

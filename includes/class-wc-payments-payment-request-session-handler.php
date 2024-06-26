<?php
/**
 * Class PaymentRequestSessionHandler
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use Automattic\Jetpack\Constants;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;

/**
 * PaymentRequestSessionHandler class
 */
final class PaymentRequestSessionHandler extends WC_Session {
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
		$this->token = wc_clean( wp_unslash( $_SERVER['HTTP_PRB_CART_TOKEN'] ?? '' ) );
		$this->table = $GLOBALS['wpdb']->prefix . 'woocommerce_sessions';
	}

	/**
	 * Init hooks and session data.
	 */
	public function init() {
		$this->init_session_from_token();
		add_action( 'shutdown', array( $this, 'save_data' ), 20 );
	}

	/**
	 * Process the token header to load the correct session.
	 */
	protected function init_session_from_token() {
		$payload = JsonWebToken::get_parts( $this->token )->payload;

		$this->_customer_id       = $payload->session_id;
		$this->session_expiration = $payload->exp;
		$this->_data              = (array) $this->get_session( [] );
	}

	/**
	 * Returns the session.
	 *
	 * @param mixed $default_value Default session value.
	 *
	 * @return array|null|bool
	 */
	public function get_session( $default_value = false ) {
		global $wpdb;

		// This mimics behaviour from default WC_Session_Handler class. There will be no sessions retrieved while WP setup is due.
		if ( Constants::is_defined( 'WP_SETUP_CONFIG' ) ) {
			return false;
		}

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT session_value FROM $this->table WHERE session_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->_customer_id
			)
		);

		if ( is_null( $value ) ) {
			$value = $default_value;
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

			$this->_dirty = false;
		}
	}
}

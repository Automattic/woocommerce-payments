<?php
/**
 * Class Session_Rate_Limiter
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

use WC_Payments_Utils;

/**
 * Counts events for the current WooCommerce session, and limits further attempts after a threshold.
 *
 * The count lives in WooCommerce's `wc_rate_limits` table, not in the session data. A request's session data is
 * loaded once and saved whole at shutdown, so requests of one session that overlap would each save their own count
 * over the others'. Each row holds the attempts the session has left (`rate_limit_remaining`) and when that
 * allowance starts over (`rate_limit_expiry`, `$delay` seconds after the last attempt).
 */
class Session_Rate_Limiter {

	/**
	 * Key used in the session to store card declined transactions.
	 *
	 * @type string
	 */
	const SESSION_KEY_DECLINED_CARD_REGISTRY = 'wcpay_card_declined_registry';

	/**
	 * Key used to store the registry in the session
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * Number of elements in the registry needed to enable the rate limiter
	 *
	 * @var int
	 */
	protected $threshold;

	/**
	 * Number of seconds the limiter is enabled for after the threshold is reached
	 *
	 * @var int
	 */
	protected $delay;

	/**
	 * Rate limit keys of the attempts this request reserved and has not yet counted or released.
	 *
	 * @var string[]
	 */
	private $reserved_keys = [];

	/**
	 * Session_Rate_Limiter constructor.
	 *
	 * @param string $key        - Key for the registry.
	 * @param int    $threshold  - Number of elements in the registry before enabling the limiter.
	 * @param int    $delay      - Number of seconds the limiter will be in use after threshold is reached.
	 */
	public function __construct(
		$key,
		$threshold,
		$delay
	) {
		$this->key       = $key;
		$this->threshold = $threshold;
		$this->delay     = $delay;
	}

	/**
	 * Counts an event for the current session.
	 *
	 * When the request reserved an attempt with `is_limited_or_reserve()`, the event uses that attempt.
	 * Once the session has used up its attempts, the limiter stays enabled until `$delay` seconds after the last event.
	 */
	public function bump() {
		global $wpdb;

		$now    = time();
		$expiry = $now + $this->delay;

		$reserved_key = array_pop( $this->reserved_keys );
		if ( null !== $reserved_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must be read and written in the database, not a cache.
			$counted = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wc_rate_limits SET rate_limit_expiry = %d WHERE rate_limit_key = %s",
					$expiry,
					$reserved_key
				)
			);
			if ( false === $counted ) {
				$this->log_query_failure( 'extend the limit after a counted attempt' );
			}
			return;
		}

		$rate_limit_key = $this->get_rate_limit_key();
		if ( null === $rate_limit_key ) {
			return;
		}

		// `rate_limit_remaining` is assigned first, so it still compares the stored expiry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must be read and written in the database, not a cache.
		$counted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}wc_rate_limits (rate_limit_key, rate_limit_expiry, rate_limit_remaining)
				VALUES (%s, %d, %d)
				ON DUPLICATE KEY UPDATE
					rate_limit_remaining = IF( rate_limit_expiry < %d, VALUES( rate_limit_remaining ), GREATEST( rate_limit_remaining - 1, 0 ) ),
					rate_limit_expiry = VALUES( rate_limit_expiry )",
				$rate_limit_key,
				$expiry,
				max( 0, $this->threshold - 1 ),
				$now
			)
		);
		if ( false === $counted ) {
			$this->log_query_failure( 'count an attempt' );
		}
	}

	/**
	 * Checks if the rate limiter is enabled, without reserving an attempt.
	 *
	 * @return bool   The rate limiter is in use.
	 */
	public function is_limited(): bool {
		global $wpdb;

		if ( $this->is_disabled() ) {
			return false;
		}

		$rate_limit_key = $this->get_rate_limit_key();
		if ( null === $rate_limit_key ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must be read from the database, not a cache.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rate_limit_expiry, rate_limit_remaining FROM {$wpdb->prefix}wc_rate_limits WHERE rate_limit_key = %s",
				$rate_limit_key
			)
		);

		return null !== $row && time() <= (int) $row->rate_limit_expiry && (int) $row->rate_limit_remaining <= 0;
	}

	/**
	 * Checks if the rate limiter is enabled and, when it is not, reserves an attempt for this request.
	 *
	 * The check and the reservation are one database update, so requests of one session that run at the same time
	 * cannot all pass the check before any of them is counted. Hand the attempt back with `release()` when it ends
	 * in anything other than a counted event; `bump()` keeps it.
	 *
	 * If the count cannot be stored, the attempt is let through.
	 *
	 * @return bool True when the rate limiter is enabled and nothing was reserved.
	 */
	public function is_limited_or_reserve(): bool {
		global $wpdb;

		if ( $this->is_disabled() ) {
			return false;
		}

		$rate_limit_key = $this->get_rate_limit_key();
		if ( null === $rate_limit_key ) {
			return false;
		}

		$now    = time();
		$expiry = $now + $this->delay;

		// Start a full allowance when the session has none, or its last one has run out.
		// `rate_limit_remaining` is assigned first, so it still compares the stored expiry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must be read and written in the database, not a cache.
		$started = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}wc_rate_limits (rate_limit_key, rate_limit_expiry, rate_limit_remaining)
				VALUES (%s, %d, %d)
				ON DUPLICATE KEY UPDATE
					rate_limit_remaining = IF( rate_limit_expiry < %d, VALUES( rate_limit_remaining ), rate_limit_remaining ),
					rate_limit_expiry = IF( rate_limit_expiry < %d, VALUES( rate_limit_expiry ), rate_limit_expiry )",
				$rate_limit_key,
				$expiry,
				$this->threshold,
				$now,
				$now
			)
		);
		if ( false === $started ) {
			$this->log_query_failure( 'start an allowance' );
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must be read and written in the database, not a cache.
		$reserved = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}wc_rate_limits
				SET rate_limit_remaining = rate_limit_remaining - 1, rate_limit_expiry = %d
				WHERE rate_limit_key = %s AND rate_limit_remaining > 0",
				$expiry,
				$rate_limit_key
			)
		);
		if ( false === $reserved ) {
			$this->log_query_failure( 'reserve an attempt' );
			return false;
		}
		if ( 0 === $reserved ) {
			return true;
		}

		$this->reserved_keys[] = $rate_limit_key;
		return false;
	}

	/**
	 * Hands back the attempt reserved by `is_limited_or_reserve()` when `bump()` did not count it.
	 */
	public function release() {
		global $wpdb;

		$reserved_key = array_pop( $this->reserved_keys );
		if ( null === $reserved_key ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must be read and written in the database, not a cache.
		$released = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}wc_rate_limits
				SET rate_limit_remaining = LEAST( rate_limit_remaining + 1, %d )
				WHERE rate_limit_key = %s AND rate_limit_expiry >= %d",
				$this->threshold,
				$reserved_key,
				time()
			)
		);
		if ( false === $released ) {
			$this->log_query_failure( 'hand back a reserved attempt' );
		}
	}

	/**
	 * Logs a failed `wc_rate_limits` query.
	 *
	 * A failed query lets attempts through, or keeps an attempt reserved until the allowance starts over, so the log is
	 * the only sign of it. It goes to the WooCommerce log because the WooPayments logger is off by default.
	 *
	 * @param string $action What the limiter could not do.
	 */
	private function log_query_failure( string $action ) {
		global $wpdb;

		WC_Payments_Utils::log_to_wc(
			sprintf(
				'Session rate limiter %1$s could not %2$s: %3$s',
				$this->key,
				$action,
				$wpdb->last_error
			)
		);
	}

	/**
	 * Whether the merchant turned this limiter off.
	 *
	 * @return bool
	 */
	private function is_disabled(): bool {
		return 'yes' === get_option( 'wcpay_session_rate_limiter_disabled_' . $this->key );
	}

	/**
	 * The `wc_rate_limits` key for the current session, or null when there is no session.
	 *
	 * @return string|null
	 */
	private function get_rate_limit_key(): ?string {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return null;
		}

		$customer_id = (string) WC()->session->get_customer_id();
		if ( '' === $customer_id ) {
			return null;
		}

		return $this->key . '_' . $customer_id;
	}
}

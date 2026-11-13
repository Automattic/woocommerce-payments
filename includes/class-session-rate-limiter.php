<?php
/**
 * Class Session_Rate_Limiter
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

use WC_Order;
use WC_Payments_Utils;

/**
 * Counts events for the current WooCommerce session, and limits further attempts after a threshold.
 *
 * The session count is kept in the `wc_rate_limits` table, not in session data. Each request saves its session data
 * whole at shutdown, so overlapping requests would overwrite each other's count.
 *
 * When given an order, events are also counted in its meta, so attempts to pay it are counted across sessions.
 */
class Session_Rate_Limiter {

	/**
	 * Key used in the session to store card declined transactions.
	 *
	 * @type string
	 */
	const SESSION_KEY_DECLINED_CARD_REGISTRY = 'wcpay_card_declined_registry';

	/**
	 * Order meta key holding the card declined registry of the order being paid.
	 *
	 * @type string
	 */
	const DECLINED_CARD_REGISTRY_META_KEY = '_' . self::SESSION_KEY_DECLINED_CARD_REGISTRY;

	/**
	 * Seconds an event stays in an order's registry.
	 *
	 * A session forgets its events when it expires, after two days for a guest. An order has no such end,
	 * so without this a shopper retrying an old order could be refused for declines from days before.
	 *
	 * @type int
	 */
	const ORDER_REGISTRY_EXPIRY = DAY_IN_SECONDS;

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
	 * Keys of attempts this request reserved and has not yet counted or released.
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
	 * Counts an event for the current session and, when given, for the order.
	 *
	 * Uses the attempt reserved by `is_limited_or_reserve()`, if there is one.
	 *
	 * @param WC_Order|null $order The order being paid, if any.
	 */
	public function bump( ?WC_Order $order = null ) {
		global $wpdb;

		if ( null !== $order ) {
			// The order keeps its own count, so attempts to pay it are counted across sessions. Each decline gets
			// a row of its own: parallel attempts each add a row, where rewriting one list would drop the others' declines.
			$order->add_meta_data( $this->get_order_meta_key(), (string) time() );
			$order->save_meta_data();
		}

		$now    = time();
		$expiry = $now + $this->delay;

		$reserved_key = array_pop( $this->reserved_keys );
		if ( null !== $reserved_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must not be cached.
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must not be cached.
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
	 * Checks if the rate limiter is enabled for the current session or, when given, for the order.
	 *
	 * With an order, it reloads the order's meta from the database, so pass one without unsaved meta changes.
	 * It also deletes the order's expired declines, and clears its registry once the delay is over.
	 *
	 * @param WC_Order|null $order The order being paid, if any.
	 *
	 * @return bool   The rate limiter is in use.
	 */
	public function is_limited( ?WC_Order $order = null ): bool {
		global $wpdb;

		if ( $this->is_disabled() ) {
			return false;
		}

		if ( null !== $order && $this->is_order_limited( $order ) ) {
			return true;
		}

		$rate_limit_key = $this->get_rate_limit_key();
		if ( null === $rate_limit_key ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must not be cached.
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
	 * Checking and reserving is one update, so overlapping requests cannot all pass before any is counted.
	 * `bump()` keeps the attempt and `release()` hands it back. If the count cannot be stored, the attempt is let through.
	 *
	 * The order count is only checked, not reserved. Its meta is reloaded, as in `is_limited()`.
	 *
	 * @param WC_Order|null $order The order being paid, if any.
	 *
	 * @return bool True when the rate limiter is enabled and nothing was reserved.
	 */
	public function is_limited_or_reserve( ?WC_Order $order = null ): bool {
		global $wpdb;

		if ( $this->is_disabled() ) {
			return false;
		}

		if ( null !== $order && $this->is_order_limited( $order ) ) {
			return true;
		}

		$rate_limit_key = $this->get_rate_limit_key();
		if ( null === $rate_limit_key ) {
			return false;
		}

		$now    = time();
		$expiry = $now + $this->delay;

		// Start a new allowance if the session has none or the last one expired.
		// `rate_limit_remaining` is assigned first, so it still compares the stored expiry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must not be cached.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must not be cached.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The count must not be cached.
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
	 * Logs a failed `wc_rate_limits` query, which is otherwise silent.
	 *
	 * Uses the WooCommerce log, since the WooPayments logger is off by default.
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

	/**
	 * Checks the order registry, and clears it once the delay is over.
	 *
	 * @param WC_Order $order The order being paid.
	 *
	 * @return bool
	 */
	private function is_order_limited( WC_Order $order ): bool {
		// Another attempt may have saved a decline since this order was loaded.
		$order->read_meta_data( true );
		$registry = $this->get_order_registry( $order );
		if ( ! $this->has_reached_threshold( $registry ) ) {
			return false;
		}

		if ( $this->is_within_delay( $registry ) ) {
			return true;
		}

		$order->delete_meta_data( $this->get_order_meta_key() );
		$order->save_meta_data();
		return false;
	}

	/**
	 * The order's registry, without events older than the expiry.
	 *
	 * @param WC_Order $order The order being paid.
	 *
	 * @return int[]
	 */
	private function get_order_registry( WC_Order $order ): array {
		$expired_before = time() - self::ORDER_REGISTRY_EXPIRY;
		$registry       = [];
		$has_expired    = false;
		foreach ( $order->get_meta( $this->get_order_meta_key(), false ) as $meta ) {
			$timestamp = (int) $meta->value;
			if ( $timestamp > $expired_before ) {
				$registry[] = $timestamp;
				continue;
			}

			if ( $meta->id ) {
				$order->delete_meta_data_by_mid( $meta->id );
				$has_expired = true;
			}
		}

		if ( $has_expired ) {
			$order->save_meta_data();
		}

		return $registry;
	}

	/**
	 * Whether a registry holds enough events to enable the rate limiter.
	 *
	 * @param mixed $registry The stored registry.
	 *
	 * @return bool
	 */
	private function has_reached_threshold( $registry ): bool {
		return is_array( $registry ) && count( $registry ) >= $this->threshold;
	}

	/**
	 * Whether the delay that started with the registry's last event is still running.
	 *
	 * @param array $registry The stored registry.
	 *
	 * @return bool
	 */
	private function is_within_delay( array $registry ): bool {
		return time() <= end( $registry ) + $this->delay;
	}

	/**
	 * The order meta key holding the order's registry.
	 *
	 * @return string
	 */
	private function get_order_meta_key(): string {
		return '_' . $this->key;
	}
}

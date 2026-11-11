<?php
/**
 * Class Session_Rate_Limiter
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

use WC_Order;
use WCPay\Exceptions\Rate_Limiter_Enabled_Exception;

/**
 * A wrapper class for keeping track of events in registries, and to trigger a rate limiter after a threshold.
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
	const ORDER_META_KEY_DECLINED_CARD_REGISTRY = '_' . self::SESSION_KEY_DECLINED_CARD_REGISTRY;

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
	 * Maximum time an unfinished request can block another payment attempt.
	 *
	 * @type int
	 */
	const ORDER_LOCK_EXPIRY = 5 * MINUTE_IN_SECONDS;

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
	 * Lock values owned by this request, keyed by option name.
	 *
	 * @var string[]
	 */
	private $order_locks = [];

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
	 * Saves an event in the session registry and, when given, in the order's registry.
	 * Once a registry holds as many events as the threshold, the rate limiter is enabled with the given delay.
	 *
	 * @param WC_Order|null $order The order being paid, if any.
	 */
	public function bump( ?WC_Order $order = null ) {
		if ( isset( WC()->session ) ) {
			$registry   = WC()->session->get( $this->key ) ?? [];
			$registry[] = time();
			WC()->session->set( $this->key, $registry );
		}

		if ( null === $order ) {
			return;
		}

		// The order keeps its own count, so attempts to pay it are counted across sessions. Each decline gets
		// a row of its own: parallel attempts each add a row, where rewriting one list would drop the others' declines.
		$order->add_meta_data( $this->get_order_meta_key(), (string) time() );
		$order->save();
	}

	/**
	 * Holds the order's payment path until the attempt has finished.
	 *
	 * @param WC_Order $order The order being paid.
	 * @throws Rate_Limiter_Enabled_Exception When another attempt owns the order.
	 */
	public function acquire_order_lock( WC_Order $order ): void {
		if ( 'yes' === get_option( 'wcpay_session_rate_limiter_disabled_' . $this->key ) ) {
			return;
		}

		global $wpdb;

		$option_name = $this->get_order_lock_option_name( $order );
		$lock_value  = ( time() + self::ORDER_LOCK_EXPIRY ) . ':' . wp_generate_uuid4();
		if ( $this->insert_order_lock( $option_name, $lock_value ) ) {
			$this->order_locks[ $option_name ] = $lock_value;
			return;
		}

		// An expired owner cannot release a replacement lock: deletion checks the stored expiry,
		// and release_order_lock() checks the unique value written by this request.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `$wpdb->options` WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < %d",
				$option_name,
				time()
			)
		);

		$lock_value = ( time() + self::ORDER_LOCK_EXPIRY ) . ':' . wp_generate_uuid4();
		if ( $this->insert_order_lock( $option_name, $lock_value ) ) {
			$this->order_locks[ $option_name ] = $lock_value;
			return;
		}

		throw new Rate_Limiter_Enabled_Exception(
			__( 'Another payment is being processed for this order. Please wait and try again.', 'woocommerce-payments' ),
			'rate_limiter_order_busy'
		);
	}

	/**
	 * Releases only the lock written by this request.
	 *
	 * @param WC_Order $order The order being paid.
	 */
	public function release_order_lock( WC_Order $order ): void {
		$option_name = $this->get_order_lock_option_name( $order );
		if ( ! isset( $this->order_locks[ $option_name ] ) ) {
			return;
		}

		global $wpdb;

		$lock_value = $this->order_locks[ $option_name ];
		unset( $this->order_locks[ $option_name ] );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `$wpdb->options` WHERE option_name = %s AND option_value = %s",
				$option_name,
				$lock_value
			)
		);
	}

	/**
	 * Checks if the rate limiter is enabled for the current session or, when given, for the order.
	 *
	 * @param WC_Order|null $order The order being paid, if any.
	 *
	 * @return bool   The rate limiter is in use.
	 */
	public function is_limited( ?WC_Order $order = null ): bool {
		if ( 'yes' === get_option( 'wcpay_session_rate_limiter_disabled_' . $this->key ) ) {
			return false;
		}

		if ( $this->is_session_limited() ) {
			return true;
		}

		if ( null === $order ) {
			return false;
		}

		return $this->is_order_limited( $order );
	}

	/**
	 * Checks the session registry, and clears it once the delay is over.
	 *
	 * @return bool
	 */
	private function is_session_limited(): bool {
		if ( ! isset( WC()->session ) ) {
			return false;
		}

		$registry = WC()->session->get( $this->key ) ?? [];
		if ( ! $this->has_reached_threshold( $registry ) ) {
			return false;
		}

		if ( $this->is_within_delay( $registry ) ) {
			return true;
		}

		WC()->session->set( $this->key, [] );
		return false;
	}

	/**
	 * Checks the order registry, and clears it once the delay is over.
	 *
	 * @param WC_Order $order The order being paid.
	 *
	 * @return bool
	 */
	private function is_order_limited( WC_Order $order ): bool {
		// The order may have been loaded before the previous attempt released its lock.
		$order->read_meta_data( true );
		$registry = $this->get_order_registry( $order );
		if ( ! $this->has_reached_threshold( $registry ) ) {
			return false;
		}

		if ( $this->is_within_delay( $registry ) ) {
			return true;
		}

		$order->delete_meta_data( $this->get_order_meta_key() );
		$order->save();
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
	 * Inserts a lock without replacing one owned by another request.
	 *
	 * @param string $option_name The option name.
	 * @param string $lock_value  Expiry and request token.
	 * @return bool Whether this request acquired the lock.
	 */
	private function insert_order_lock( string $option_name, string $lock_value ): bool {
		global $wpdb;

		// add_option() updates an existing row when two requests race, so its return value cannot act as a lock.
		return 1 === $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `$wpdb->options` (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$option_name,
				$lock_value
			)
		);
	}

	/**
	 * Names the lock within this site's options table.
	 *
	 * @param WC_Order $order The order being paid.
	 * @return string
	 */
	private function get_order_lock_option_name( WC_Order $order ): string {
		return 'wcpay_rate_limiter_lock_' . md5( $this->key ) . '_' . $order->get_id();
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

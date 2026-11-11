<?php
/**
 * Class Session_Rate_Limiter
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

use WC_Order;

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
		foreach ( $order->get_meta( $this->get_order_meta_key(), false ) as $meta ) {
			$timestamp = (int) $meta->value;
			if ( $timestamp > $expired_before ) {
				$registry[] = $timestamp;
			}
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

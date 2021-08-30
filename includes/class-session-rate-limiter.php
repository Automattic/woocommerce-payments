<?php
/**
 * Class Session_Rate_Limiter
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit; // block direct access.

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
	 * Saves an event in an specified registry using a key.
	 * If the number of events in the registry match the threshold,
	 * a new rate limiter is enabled with the given delay.
	 *
	 * The registry of declined card attemps is cleaned after a new rate limiter is enabled.
	 */
	public function bump() {
		if ( ! isset( WC()->session ) ) {
			return;
		}

		$registry = WC()->session->get( $this->key ) ?? [];
		$now      = time();
		array_push( $registry, $now );
		WC()->session->set( $this->key, $registry );

		if ( count( $registry ) >= $this->threshold ) {
			$action_id = $this->get_action_id( $this->key );
			if ( isset( $action_id ) ) {
				$this->enable_rate_limiter( $action_id, $this->delay );
				WC()->session->set( $this->key, [] );
			}
		}
	}


	/**
	 * Checks if the ratelimiter for a given key of a registry is enabled.
	 *
	 * Returns a boolean.
	 *
	 * @return bool   The rate limiter is in use.
	 */
	public function is_limited() {
		if ( ! isset( WC()->session ) ) {
			return;
		}

		$next_try_allowed_at = WC()->session->get( $this->get_action_id( ( $this->key ) ) );

		// No record of action running, so action is allowed to run.
		if ( false === $next_try_allowed_at ) {
			return false;
		}

		// Before the next run is allowed, retry forbidden.
		if ( time() <= $next_try_allowed_at ) {
			return true;
		}

		// After the next run is allowed, retry allowed.
		return false;
	}

	/**
	 * Saves an event in an specified registry using a key.
	 *
	 * @param  string $action_id ID that Session_Rate_Limiter uses to identify a rate limiter.
	 * @param  int    $delay Delay in seconds to apply in the new rate limiter.
	 */
	public function enable_rate_limiter( $action_id, $delay ) {
		if ( isset( $action_id ) ) {
			$next_try_allowed_at = time() + $delay;
			WC()->session->set( $action_id, $next_try_allowed_at );
		}
	}

	/**
	 * Checks if the rate limiter for a given key of a registry is enabled.
	 *
	 * Returns a boolean.
	 *
	 * @return string|null The id of the action if the session and the customer_id exist.
	 */
	public function get_action_id() {
		if ( ! isset( WC()->session ) ) {
			return null;
		}
		$customer_id = WC()->session->get_customer_id();
		if ( ! isset( $customer_id ) ) {
			return null;
		}

		return "{$this->key}_{$customer_id}";
	}
}

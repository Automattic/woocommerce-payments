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

		$registry   = WC()->session->get( $this->key ) ?? [];
		$registry[] = time();
		WC()->session->set( $this->key, $registry );
	}


	/**
	 * Checks if the rate limiter is enabled.
	 *
	 * Returns a boolean.
	 *
	 * @return bool   The rate limiter is in use.
	 */
	public function is_limited(): bool {
		if ( ! isset( WC()->session ) ) {
			return false;
		}

		if ( 'yes' === get_option( 'wcpay_session_rate_limiter_disabled_' . $this->key ) ) {
			return false;
		}

		$registry = WC()->session->get( $this->key ) ?? [];

		if ( count( $registry ) >= $this->threshold ) {
			$start_time_limiter  = end( $registry );
			$next_try_allowed_at = $start_time_limiter + $this->delay;
			$is_limited          = time() <= $next_try_allowed_at;
			if ( ! $is_limited ) {
				WC()->session->set( $this->key, [] );
			}

			return $is_limited;
		}

		return false;
	}
}

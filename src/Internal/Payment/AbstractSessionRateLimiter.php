<?php
/**
 * Class AbstractSessionRateLimiter
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\SessionService;

/**
 * A wrapper class for keeping track of events in registries, and to trigger a rate limiter after a threshold.
 */
abstract class AbstractSessionRateLimiter {
	/**
	 * Key used to store the registry in the session
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Number of elements in the registry needed to enable the rate limiter
	 *
	 * @var int
	 */
	private $threshold;

	/**
	 * Number of seconds the limiter is enabled for after the threshold is reached
	 *
	 * @var int
	 */
	private $delay;

	/**
	 * SessionService instance.
	 *
	 * @var SessionService
	 */
	private $session_service;

	/**
	 * LegacyProxy instance.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * AbstractSessionRateLimiter constructor.
	 *
	 * @param string         $key             Key for the registry.
	 * @param int            $threshold       Number of elements in the registry before enabling the limiter.
	 * @param int            $delay           Number of seconds the limiter will be in use after threshold is reached.
	 * @param SessionService $session_service SessionService instance.
	 * @param LegacyProxy    $legacy_proxy    LegacyProxy instance.
	 */
	protected function __construct(
		string $key,
		int $threshold,
		int $delay,
		SessionService $session_service,
		LegacyProxy $legacy_proxy
	) {
		$this->key             = $key;
		$this->threshold       = $threshold;
		$this->delay           = $delay;
		$this->session_service = $session_service;
		$this->legacy_proxy    = $legacy_proxy;
	}

	/**
	 * Saves an event in an specified registry using a key.
	 * If the number of events in the registry match the threshold,
	 * a new rate limiter is enabled with the given delay.
	 *
	 * The registry of declined card attemps is cleaned after a new rate limiter is enabled.
	 */
	final public function bump() {
		$registry   = $this->session_service->get( $this->key ) ?? [];
		$registry[] = $this->legacy_proxy->call_function( 'time' );

		$this->session_service->set( $this->key, $registry );
	}

	/**
	 * Checks if the rate limiter is enabled.
	 *
	 * Returns a boolean.
	 *
	 * @return bool The rate limiter is in use.
	 */
	final public function is_limited(): bool {
		if ( 'yes' === $this->legacy_proxy->call_function( 'get_option', 'wcpay_session_rate_limiter_disabled_' . $this->key ) ) {
			return false;
		}

		$registry = $this->session_service->get( $this->key ) ?? [];

		if ( ( is_countable( $registry ) ? count( $registry ) : 0 ) >= $this->threshold ) {
			$start_time_limiter  = end( $registry );
			$next_try_allowed_at = $start_time_limiter + $this->delay;
			$is_limited          = time() <= $next_try_allowed_at;
			if ( ! $is_limited ) {
				$this->session_service->set( $this->key, [] );
			}

			return $is_limited;
		}

		return false;
	}
}

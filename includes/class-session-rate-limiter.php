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
	 * Saves an event in an specified registry using a key.
	 * If the number of events in the registry match the threshold,
	 * a new rate limiter is enabled with the given delay.
	 *
	 * The registry of declined card attemps is cleaned after a new rate limiter is enabled.
	 *
	 * @param  string $key Key for the registry.
	 * @param  int    $threshold Number of eleements needed in the registry to enable a rate limiter.
	 * @param  int    $delay Delay in seconds to apply in the new rate limiter, if created.
	 */
	public function bump( $key, $threshold, $delay ) {
		if ( ! isset( WC()->session ) ) {
			return;
		}

		$registry = WC()->session->get( $key ) ?? [];
		$now      = time();
		array_push( $registry, $now );
		WC()->session->set( $key, $registry );

		if ( count( $registry ) >= $threshold ) {
			$action_id = $this->get_action_id( $key );
			if ( isset( $action_id ) ) {
				$this->enable_rate_limiter( $action_id, $delay );
				WC()->session->set( $key, [] );
			}
		}
	}


	/**
	 * Checks if the ratelimiter for a given key of a registry is enabled.
	 *
	 * Returns a boolean.
	 *
	 * @param  string $key Key for the registry.
	 * @return bool   The rate limiter is in use.
	 */
	public function is_limited( $key ) {
		if ( ! isset( WC()->session ) ) {
			return;
		}

		$next_try_allowed_at = WC()->session->get( $this->get_action_id( ( $key ) ) );

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
	 * @param  string $key Key for the registry.
	 * @return string|null The id of the action if the session and the customer_id exist.
	 */
	public function get_action_id( $key ) {
		if ( ! isset( WC()->session ) ) {
			return null;
		}
		$customer_id = WC()->session->get_customer_id();
		if ( ! isset( $customer_id ) ) {
			return null;
		}

		return "{$key}_{$customer_id}";
	}
}

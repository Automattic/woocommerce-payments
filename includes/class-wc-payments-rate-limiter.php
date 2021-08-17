<?php
/**
 * Class WC_Payments_Rate_Limiter
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Rate_Limiter;
use DateTime;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * A wrapper class for interacting with WC_Rate_Limiter using a registry of events.
 */
class WC_Payments_Rate_Limiter {

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
	public static function save_datetime_in_key( $key, $threshold, $delay ) {
		if ( ! isset( WC()->session ) ) {
			return;
		}

		$registry = WC()->session->get( $key ) ?? [];
		$now      = new DateTime();
		array_push( $registry, $now );
		WC()->session->set( $key, $registry );

		if ( count( $registry ) >= $threshold ) {
			$action_id = self::get_action_id( $key );
			if ( isset( $action_id ) ) {
				self::enable_rate_limiter( $action_id, $delay );
				WC()->session->set( $key, [] );
			}
		}
	}


	/**
	 * Checks in WC_Rate_Limiter if the ratelimiter for a given key of a registry is enabled.
	 *
	 * Returns a boolean.
	 *
	 * @param  string $key Key for the registry.
	 * @return bool   The rate limiter is in use.
	 */
	public static function is_rate_limiter_enabled( $key ) {
		return WC_Rate_Limiter::retried_too_soon( self::get_action_id( ( $key ) ) );
	}

	/**
	 * Saves an event in an specified registry using a key.
	 * If the number of events in the registry match the threshold,
	 * a new rate limiter is enabled with the given delay.
	 *
	 * The registry of declined card attemps is cleaned after a new rate limiter is enabled.
	 *
	 * @param  string $action_id ID that WC_Rate_Limiter uses to identify a rate limiter.
	 * @param  int    $delay Delay in seconds to apply in the new rate limiter.
	 */
	public static function enable_rate_limiter( $action_id, $delay ) {
		if ( isset( $action_id ) ) {
			WC_Rate_Limiter::set_rate_limit( $action_id, $delay );
		}
	}

	/**
	 * Checks in WC_Rate_Limiter if the ratelimiter for a given key of a registry is enabled.
	 *
	 * Returns a boolean.
	 *
	 * @param  string $key Key for the registry.
	 * @return string|null The id of the action if the session and the customer_id exist.
	 */
	public static function get_action_id( $key ) {
		if ( ! isset( WC()->session ) ) {
			return null;
		}
		$session_data = WC()->session->get_session_data();
		$customer_id  = $session_data['wcpay_customer_id'] ?? null;
		if ( ! isset( $customer_id ) ) {
			return null;
		}

		return "{$key}_{$customer_id}";
	}
}

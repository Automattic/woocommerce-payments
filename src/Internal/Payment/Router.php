<?php
/**
 * Class Router
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WCPay\Core\Server\Request\Get_Payment_Process_Factors;
use WCPay\Database_Cache;
use WCPay\Exceptions\API_Exception;
use WCPay\Internal\Payment\Factor;

/**
 * Until the new payment process is fully developed, and the legacy
 * process is gone, the new process is managed as a feature behind a router.
 *
 * This class will be removed once the new process is the default
 * option for all payments.
 */
class Router {
	/**
	 * Database cache.
	 *
	 * @var Database_Cache
	 */
	protected $database_cache;

	/**
	 * Class constructor, receiving dependencies.
	 *
	 * @param Database_Cache $database_cache Database cache.
	 */
	public function __construct( Database_Cache $database_cache ) {
		$this->database_cache = $database_cache;
	}

	/**
	 * Checks whether a given payment should use the new payment process.
	 *
	 * @param array $factors Factors, describing the type and conditions of the payment.
	 * @return bool
	 */
	public function should_use_new_payment_process( array $factors ): bool {
		$allowed = $this->get_enabled_factors();

		// This would make sure that the payment process is a factor as well.
		$factors[ Factor::NEW_PAYMENT_PROCESS ] = true;

		foreach ( $factors as $key => $enabled ) {
			// If a factor is not present, there is no need to check for it.
			if ( ! $enabled ) {
				continue;
			}

			// The factor should exist, and be allowed.
			if ( ! isset( $allowed[ $key ] ) || ! $allowed[ $key ] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns all factors, which can be handled by the new payment process.
	 * If any factor, not enabled in the returned array, is present while paying,
	 * the payment should fall back to the legacy process.
	 *
	 * @return array
	 */
	public function get_enabled_factors() {
		$factors = $this->get_cached_factors() ?? [];
		$factors = apply_filters( 'wcpay_new_payment_process_enabled_factors', $factors );
		return $factors;
	}

	/**
	 * Checks if cached data is valid.
	 *
	 * @param mixed $cache The cached data.
	 * @return bool
	 */
	public function is_valid_cache( $cache ): bool {
		return is_array( $cache ) && isset( $cache[ Factor::NEW_PAYMENT_PROCESS ] );
	}

	/**
	 * Refetches payment factors and returns the fresh data.
	 *
	 * @return array Either the new arrayh of factors, or false if unavailable.
	 */
	public function refresh_factors() {
		return $this->get_cached_factors( true );
	}

	/**
	 * Gets and chaches all factors, which can be handled by the new payment process.
	 * If any factor, not enabled in the returned array, is present while paying,
	 * the payment should fall back to the legacy process.
	 *
	 * @param bool $force_refresh Forces data to be fetched from the server, rather than using the cache.
	 * @return array Factors, or an empty array.
	 */
	private function get_cached_factors( bool $force_refresh = false ) {
		$factors = $this->database_cache->get_or_add(
			Database_Cache::PAYMENT_PROCESS_FACTORS_KEY,
			function () {
				try {
					$request  = Get_Payment_Process_Factors::create();
					$response = $request->send( 'wcpay_Get_Payment_Process_Factors' );
					return $response->to_array();
				} catch ( API_Exception $e ) {
					// Return false to signal retrieval error.
					return false;
				}
			},
			[ $this, 'is_valid_cache' ],
			$force_refresh
		);

		return $factors ?? [];
	}
}

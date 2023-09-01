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
	 * @param Factor[] $factors Factors, describing the type and conditions of the payment.
	 * @return bool
	 * @psalm-suppress MissingThrowsDocblock
	 */
	public function should_use_new_payment_process( array $factors ): bool {
		$allowed_factors = $this->get_allowed_factors();

		foreach ( $factors as $present_factor ) {
			if ( ! in_array( $present_factor, $allowed_factors, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns all factors, which can be handled by the new payment process.
	 *
	 * @return Factor[]
	 */
	public function get_allowed_factors() {
		// Might be false if loading failed.
		$cached      = $this->get_cached_factors();
		$all_factors = is_array( $cached ) ? $cached : [];
		$allowed     = [];

		foreach ( ( $all_factors ?? [] ) as $key => $enabled ) {
			if ( $enabled ) {
				$allowed[] = Factor::$key();
			}
		}

		$allowed = apply_filters( 'wcpay_new_payment_process_enabled_factors', $allowed );
		return $allowed;
	}

	/**
	 * Checks if cached data is valid.
	 *
	 * @psalm-suppress MissingThrowsDocblock
	 * @param mixed $cache The cached data.
	 * @return bool
	 */
	public function is_valid_cache( $cache ): bool {
		return is_array( $cache ) && isset( $cache[ Factor::NEW_PAYMENT_PROCESS()->get_value() ] );
	}

	/**
	 * Gets and chaches all factors, which can be handled by the new payment process.
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
					$response = $request->send( 'wcpay_get_payment_process_factors' );
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

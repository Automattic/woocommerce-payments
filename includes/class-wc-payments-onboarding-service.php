<?php
/**
 * Class WC_Payments_Onboarding_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\API_Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class handling onboarding related business logic.
 */
class WC_Payments_Onboarding_Service {

	const BUSINESS_TYPES_OPTION          = 'wcpay_business_types_data';
	const BUSINESS_TYPES_RETRIEVAL_ERROR = 'error';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Class constructor
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;
	}

	/**
	 * Gets and caches the business types per country from the server.
	 *
	 * @param bool $force_refresh Forces data to be fetched from the server, rather than using the cache.
	 *
	 * @return array|bool Business types, or false if failed to retrieve.
	 */
	public function get_cached_business_types( bool $force_refresh = false ) {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return [];
		}

		// If we want to force a refresh, we can skip this logic and go straight to the server request.
		if ( ! $force_refresh ) {
			$business_types = $this->read_business_types_from_cache();

			if ( false !== $business_types && is_array( $business_types ) && ! empty( $business_types ) ) {
				return $business_types;
			}

			// If the option contains the error value, return false early and do not attempt another API call.
			if ( self::BUSINESS_TYPES_RETRIEVAL_ERROR === $business_types ) {
				return false;
			}
		}

		try {
			$business_types = $this->payments_api_client->get_onboarding_business_types();
		} catch ( API_Exception $e ) {
			// Failed to retrieve the data. Exception logged in HTTP client.
			// Rate limit the failures by setting a transient for a short time.
			$this->cache_business_types( self::BUSINESS_TYPES_RETRIEVAL_ERROR, 2 * MINUTE_IN_SECONDS );

			// Return false to signal retrieval error.
			return false;
		}

		// Cache the details so we don't call the server every time.
		$this->cache_business_types( $business_types );

		return $business_types;
	}

	/**
	 * Caches the business types for a period of time.
	 *
	 * @param string|array $business_types - The business types to cache, or the business types retrieval error.
	 * @param int          $expiration     - The length of time to cache the data, expressed in seconds.
	 */
	private function cache_business_types( $business_types, int $expiration = null ) {
		if ( null === $expiration ) {
			$expiration = WEEK_IN_SECONDS;
		}

		// Add the business types and the expiry time to the array we're caching.
		$business_types_cache = [
			'business_types' => $business_types,
			'expires'        => time() * $expiration,
		];

		$result = update_option( self::BUSINESS_TYPES_OPTION, $business_types_cache, 'no' );

		return $result;
	}

	/**
	 * Read the business types from the WP option we cache it in.
	 *
	 * @return array|bool
	 */
	private function read_business_types_from_cache() {
		$business_types_cache = get_option( self::BUSINESS_TYPES_OPTION );

		if ( false === $business_types_cache || ! isset( $business_types_cache['business_types'] ) || ! isset( $business_types_cache['expires'] ) ) {
			// No option found or the data isn't in the shape we expect.
			return false;
		}

		// Set $account to false if the cache has expired, triggering another fetch.
		if ( $business_types_cache['expires'] < time() ) {
			return false;
		}

		// We have fresh business types data in the cache, so return it.
		return $business_types_cache['business_types'];
	}
}

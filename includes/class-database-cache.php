<?php
/**
 * Class Database_Cache
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * A class for caching data as an option in the database.
 */
class Database_Cache {
	const ACCOUNT_KEY                 = 'wcpay_account_data';
	const ONBOARDING_FIELDS_DATA_KEY  = 'wcpay_onboarding_fields_data';
	const BUSINESS_TYPES_KEY          = 'wcpay_business_types_data';
	const CURRENCIES_KEY              = 'wcpay_multi_currency_cached_currencies';
	const PAYMENT_PROCESS_FACTORS_KEY = 'wcpay_payment_process_factors';
	const FRAUD_SERVICES_KEY          = 'wcpay_fraud_services_data';

	/**
	 * Refresh during AJAX calls is avoided, but white-listing
	 * a key here will allow the refresh to happen.
	 *
	 * @var string[]
	 */
	const AJAX_ALLOWED_KEYS = [
		self::PAYMENT_PROCESS_FACTORS_KEY,
	];

	/**
	 * Payment methods cache key prefix. Used in conjunction with the customer_id to cache a customer's payment methods.
	 */
	const PAYMENT_METHODS_KEY_PREFIX = 'wcpay_pm_';

	/**
	 * Dispute status counts cache key.
	 *
	 * @var string
	 */
	const DISPUTE_STATUS_COUNTS_KEY = 'wcpay_dispute_status_counts_cache';

	/**
	 * Active disputes cache key.
	 *
	 * @var string
	 */
	const ACTIVE_DISPUTES_KEY = 'wcpay_active_dispute_cache';

	/**
	 * Cache key for authorization summary data like count, total amount, etc.
	 *
	 * @var string
	 */
	const AUTHORIZATION_SUMMARY_KEY = 'wcpay_authorization_summary_cache';

	/**
	 * Cache key for authorization summary data like count, total amount, etc in test mode.
	 *
	 * @var string
	 */
	const AUTHORIZATION_SUMMARY_KEY_TEST_MODE = 'wcpay_test_authorization_summary_cache';

	/**
	 * Cache key for eligible connect incentive data.
	 */
	const CONNECT_INCENTIVE_KEY = 'wcpay_connect_incentive';

	/**
	 * Tracking info cache key.
	 *
	 * @var string
	 */
	const TRACKING_INFO_KEY = 'wcpay_tracking_info_cache';

	/**
	 * Refresh disabled flag, controlling the behaviour of the get_or_add function.
	 *
	 * @var bool
	 */
	private $refresh_disabled;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->refresh_disabled = false;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'action_scheduler_before_execute', [ $this, 'disable_refresh' ] );
	}

	/**
	 * Gets a value from cache or regenerates and adds it to the cache.
	 *
	 * @param string   $key           The options key to cache the data under.
	 * @param callable $generator     Function/callable regenerating the missing value. If null or false is returned, it will be treated as an error.
	 * @param callable $validate_data Function/callable validating the data after it is retrieved from the cache. If it returns false, the cache will be refreshed.
	 * @param boolean  $force_refresh Regenerates the cache regardless of its state if true.
	 * @param boolean  $refreshed     Is set to true if the cache has been refreshed without errors and with a non-empty value.
	 *
	 * @return mixed|null The cache contents. NULL on failure
	 */
	public function get_or_add( string $key, callable $generator, callable $validate_data, bool $force_refresh = false, bool &$refreshed = false ) {
		$cache_contents = get_option( $key );
		$data           = null;
		$old_data       = null;

		// If the stored data is valid, prepare it for return in case we don't need to refresh.
		// Also initialize old_data in case of errors.
		if ( is_array( $cache_contents ) && array_key_exists( 'data', $cache_contents ) && $validate_data( $cache_contents['data'] ) ) {
			$data     = $cache_contents['data'];
			$old_data = $data;
		}

		if ( $this->should_refresh_cache( $key, $cache_contents, $validate_data, $force_refresh ) ) {
			$errored = false;

			try {
				$data    = $generator();
				$errored = false === $data || null === $data;
			} catch ( \Throwable $e ) {
				$errored = true;
			}

			$refreshed = ! $errored;

			if ( $errored ) {
				// Still return the old data on error and refresh the cache with it.
				$data = $old_data;
			}

			$cache_contents = $this->write_to_cache( $key, $data, $errored );
		}

		return $data;
	}

	/**
	 * Gets a value from the cache.
	 *
	 * @param string $key The key to look for.
	 * @param bool   $force If set, return from the cache without checking for expiry.
	 *
	 * @return mixed The cache contents.
	 */
	public function get( string $key, bool $force = false ) {
		$cache_contents = get_option( $key );
		if ( is_array( $cache_contents ) && array_key_exists( 'data', $cache_contents ) ) {
			if ( ! $force && $this->is_expired( $key, $cache_contents ) ) {
				return null;
			}

			return $cache_contents['data'];
		}
		return null;
	}

	/**
	 * Stores a value in the cache.
	 *
	 * @param string $key  The key to store the value under.
	 * @param mixed  $data The value to store.
	 *
	 * @return void
	 */
	public function add( string $key, $data ) {
		$this->write_to_cache( $key, $data, false );
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key The key to delete.
	 *
	 * @return void
	 */
	public function delete( string $key ) {
		delete_option( $key );
	}

	/**
	 * Hook function allowing the cache refresh to be selectively disabled in certain situations
	 * (such as while running an Action Scheduler job). While the refresh is disabled, get_or_add
	 * will only return the cached value and never regenerate it, even if it's expired.
	 *
	 * @return void
	 */
	public function disable_refresh() {
		$this->refresh_disabled = true;
	}

	/**
	 * Deletes all saved by looking for cache key prefix. This is useful when you want to cache user related data by
	 *
	 * @param string $key Cache key prefix to delete.
	 *
	 * @return void
	 */
	public function delete_by_prefix( string $key ) {

		// Protection against accidentally deleting all options or options that are not related to WcPay caching.
		// Since only one cache key prefix is supported, we will check only this one by checking does key starts with payment method key prefix.
		// Feel free to update this statement if more prefix cache keys you are planning to add.

		if ( strncmp( $key, self::PAYMENT_METHODS_KEY_PREFIX, strlen( self::PAYMENT_METHODS_KEY_PREFIX ) ) !== 0 ) {
			return; // Maybe throw exception here...
		}
		global $wpdb;

		$plugin_options = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", $key . '%' ) );

		foreach ( $plugin_options as $option ) {
			$this->delete( $option->option_name );
		}
	}

	/**
	 * Validates the cache contents and, given the passed params and the current application state, determines whether the cache should be refreshed.
	 * See get_or_add.
	 *
	 * @param string   $key            The cache key.
	 * @param mixed    $cache_contents The cache contents.
	 * @param callable $validate_data  Callback used to validate the cached data by the callee.
	 * @param boolean  $force_refresh  Whether a refresh should be forced.
	 *
	 * @return boolean True if the cache needs to be refreshed.
	 */
	private function should_refresh_cache( string $key, $cache_contents, callable $validate_data, bool $force_refresh ): bool {
		// Always refresh if the flag is set.
		if ( $force_refresh ) {
			return true;
		}

		// Do not refresh if doing ajax or the refresh has been disabled (running an AS job).
		if (
			defined( 'DOING_CRON' )
			|| ( wp_doing_ajax() && ! in_array( $key, self::AJAX_ALLOWED_KEYS, true ) )
			|| $this->refresh_disabled ) {
			return false;
		}

		// The value of false means that there was never an option set in the database.
		if ( false === $cache_contents ) {
			return true;
		}

		// Non-array, empty array, or missing expected fields mean corrupted data.
		// This also handles potential legacy data, which might have those keys missing.
		if ( ! is_array( $cache_contents )
			|| empty( $cache_contents )
			|| ! array_key_exists( 'data', $cache_contents )
			|| ! isset( $cache_contents['fetched'] )
			|| ! array_key_exists( 'errored', $cache_contents )
		) {
			return true;
		}

		// If the data is not errored and invalid, refresh it.
		if (
			! $cache_contents['errored'] &&
			! $validate_data( $cache_contents['data'] )
		) {
			return true;
		}

		// Refresh the expired data.
		if ( $this->is_expired( $key, $cache_contents ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Wraps the data in the cache metadata and stores it.
	 *
	 * @param string  $key     The key to store the data under.
	 * @param mixed   $data    The data to store.
	 * @param boolean $errored Whether the refresh operation resulted in an error before this has been called.
	 *
	 * @return array The cache contents (data and metadata).
	 */
	private function write_to_cache( string $key, $data, bool $errored ): array {
		// Add the  data and expiry time to the array we're caching.
		$cache_contents            = [];
		$cache_contents['data']    = $data;
		$cache_contents['fetched'] = time();
		$cache_contents['errored'] = $errored;

		// Create or update the option cache.
		if ( false === get_option( $key ) ) {
			add_option( $key, $cache_contents, '', 'no' );
		} else {
			update_option( $key, $cache_contents, 'no' );
		}

		return $cache_contents;
	}

	/**
	 * Checks if the cache contents are expired.
	 *
	 * @param string $key            The cache key.
	 * @param array  $cache_contents The cache contents.
	 *
	 * @return boolean True if the contents are expired.
	 */
	private function is_expired( string $key, array $cache_contents ): bool {
		$ttl     = $this->get_ttl( $key, $cache_contents );
		$expires = $cache_contents['fetched'] + $ttl;
		$now     = time();
		return $expires < $now;
	}

	/**
	 * Given the key and the cache contents, and based on the application state, determines the cache TTL.
	 *
	 * @param string $key            The cache key.
	 * @param array  $cache_contents The cache contents.
	 *
	 * @return integer The cache TTL.
	 */
	private function get_ttl( string $key, array $cache_contents ): int {
		switch ( $key ) {
			case self::ACCOUNT_KEY:
				if ( is_admin() ) {
					// Fetches triggered from the admin panel should be more frequent.
					if ( $cache_contents['errored'] ) {
						// Attempt to refresh the data quickly if the last fetch was an error.
						$ttl = 2 * MINUTE_IN_SECONDS;
					} else {
						// If the data was fetched successfully, fetch it every 2h.
						$ttl = 2 * HOUR_IN_SECONDS;
					}
				} else {
					// Non-admin requests should always refresh only after 24h since the last fetch.
					$ttl = DAY_IN_SECONDS;
				}
				break;
			case self::CURRENCIES_KEY:
				// Refresh the errored currencies quickly, otherwise cache for 6h.
				$ttl = $cache_contents['errored'] ? 2 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS;
				break;
			case self::BUSINESS_TYPES_KEY:
			case self::ONBOARDING_FIELDS_DATA_KEY:
				// Cache these for a week.
				$ttl = WEEK_IN_SECONDS;
				break;
			case self::CONNECT_INCENTIVE_KEY:
				$ttl = $cache_contents['data']['ttl'] ?? HOUR_IN_SECONDS * 6;
				break;
			case self::PAYMENT_PROCESS_FACTORS_KEY:
				$ttl = 2 * HOUR_IN_SECONDS;
				break;
			case self::TRACKING_INFO_KEY:
				$ttl = $cache_contents['errored'] ? 2 * MINUTE_IN_SECONDS : MONTH_IN_SECONDS;
				break;
			default:
				// Default to 24h.
				$ttl = DAY_IN_SECONDS;
				break;
		}

		return apply_filters( 'wcpay_database_cache_ttl', $ttl, $key, $cache_contents );
	}
}

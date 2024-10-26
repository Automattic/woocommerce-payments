<?php
/**
 * Interface MultiCurrencyCacheInterface
 *
 * @package WooCommerce\Payments\MultiCurrency\Interfaces
 */

namespace WCPay\MultiCurrency\Interfaces;

defined( 'ABSPATH' ) || exit;

interface MultiCurrencyCacheInterface {
	const CURRENCIES_KEY = 'wcpay_multi_currency_cached_currencies';

	/**
	 * Gets a value from the cache.
	 *
	 * @param string $key   The key to look for.
	 * @param bool   $force If set, return from the cache without checking for expiry.
	 *
	 * @return mixed The cache contents.
	 */
	public function get( string $key, bool $force = false );

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
	public function get_or_add( string $key, callable $generator, callable $validate_data, bool $force_refresh = false, bool &$refreshed = false );

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key The key to delete.
	 *
	 * @return void
	 */
	public function delete( string $key );
}

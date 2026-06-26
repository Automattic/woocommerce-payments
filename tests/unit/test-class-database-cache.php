<?php
/**
 * Class Database_Cache_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Database_Cache;

/**
 * Database_Cache unit tests.
 */
class Database_Cache_Test extends WCPAY_UnitTestCase {

	const MOCK_KEY = 'mock_key';

	/**
	 * Database_Cache under test
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	public function set_up() {
		parent::set_up();

		$this->database_cache = new Database_Cache();
	}

	public function tear_down() {
		delete_option( self::MOCK_KEY );
		delete_option( Database_Cache::ADDRESS_AUTOCOMPLETE_JWT_KEY );
		delete_option( Database_Cache::ACCOUNT_KEY );
		delete_option( Database_Cache::CURRENCIES_KEY );
		delete_option( Database_Cache::TRACKING_INFO_KEY );

		parent::tear_down();
	}

	public function test_get_or_add_returns_cached_value() {
		$refreshed = false;
		$value     = [ 'mock' => true ];

		$this->write_mock_cache( $value );

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () {
				$this->fail( 'Should not call the generator.' );
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertFalse( $refreshed );
	}

	public function test_get_or_add_generates_and_caches_value_for_the_first_time() {
		$refreshed = false;
		$value     = [ 'mock' => true ];

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value ) {
				return $value;
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertTrue( $refreshed );
		$this->assert_cache_contains( $value );
	}

	public function test_get_or_add_generates_and_caches_value_if_cache_fails_validation() {
		$refreshed = false;
		$value     = [ 'mock' => true ];

		$this->write_mock_cache( [ 'invalid' => true ] );

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value ) {
				return $value;
			},
			'__return_false',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertTrue( $refreshed );
		$this->assert_cache_contains( $value );
	}

	public function test_get_or_add_forces_refresh() {
		$refreshed = false;
		$value     = [ 'mock' => true ];

		$this->write_mock_cache( [ 'old' => true ] );

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value ) {
				return $value;
			},
			'__return_true',
			true,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertTrue( $refreshed );
		$this->assert_cache_contains( $value );
	}

	public function test_get_or_add_regenerates_if_expired() {
		$refreshed = false;
		$value     = [ 'mock' => true ];

		$this->write_mock_cache( [ 'old' => true ], time() - YEAR_IN_SECONDS );

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value ) {
				return $value;
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertTrue( $refreshed );
		$this->assert_cache_contains( $value );
	}

	public function test_get_or_add_returns_old_data_on_error() {
		$refreshed        = false;
		$called_generator = false;
		$old              = [ 'old' => true ];

		$this->write_mock_cache( $old, time() - YEAR_IN_SECONDS );

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( &$called_generator ) {
				$called_generator = true;
				throw new \Exception( 'test' );
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertTrue( $called_generator );
		$this->assertEquals( $old, $res );
		$this->assertFalse( $refreshed );
		$this->assert_cache_contains( $old, true );
	}

	public function test_get_or_add_handles_error_when_there_was_no_old_data() {
		$refreshed        = false;
		$called_generator = false;

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( &$called_generator ) {
				$called_generator = true;
				throw new \Exception( 'test' );
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertTrue( $called_generator );
		$this->assertNull( $res );
		$this->assertFalse( $refreshed );
		$this->assert_cache_contains( null, true );
	}

	public function test_get_or_add_refreshes_on_legacy_or_malformed_data() {
		$refreshed = false;
		$value     = [ 'mock' => true ];
		$old       = [ 'old' => true ];

		update_option(
			self::MOCK_KEY,
			[
				'account' => $old,
				'expires' => time() + DAY_IN_SECONDS,
			]
		);

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value ) {
				return $value;
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertTrue( $refreshed );
		$this->assert_cache_contains( $value );
	}

	public function test_get_or_add_does_not_refresh_errored_out_invalid_value() {
		$refreshed        = false;
		$value            = [ 'mock' => true ];
		$old              = [ 'old' => true ];
		$called_generator = false;

		$this->write_mock_cache( $old, time() + YEAR_IN_SECONDS, true );

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value, &$called_generator ) {
				$called_generator = true;
				return $value;
			},
			'__return_false',
			false,
			$refreshed
		);

		$this->assertNull( $res );
		$this->assertFalse( $refreshed );
		$this->assertFalse( $called_generator );
		$this->assert_cache_contains( $old, true );
	}

	public function test_get_or_add_does_not_refresh_on_subsequent_db_write_errors() {
		$old           = [ 'old' => true ];
		$value         = [ 'mock' => true ];
		$another_value = [ 'another_mock' => true ];

		// Write an expired cache value.
		$this->write_mock_cache( $old, time() - YEAR_IN_SECONDS );

		// All DB write queries will fail.
		add_filter(
			'query',
			function ( $query ) {
				if ( str_starts_with( $query, 'UPDATE' ) || str_starts_with( $query, 'INSERT INTO' ) ) {
					return false;
				}

				return $query;
			}
		);

		// First call will call the generator, fail to write to the DB, and cache the value in the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value, &$called_generator ) {
				$called_generator = true;

				return $value;
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertTrue( $refreshed );
		$this->assertTrue( $called_generator );
		$this->assert_cache_contains( $old );

		// The second call will NOT call the generator, but the value will be returned from the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( &$called_generator ) {
				$called_generator = true;

				return [];
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertFalse( $refreshed );
		$this->assertFalse( $called_generator );
		$this->assert_cache_contains( $old );

		remove_all_filters( 'query' );

		// The third call will NOT call the generator, NOT write to the DB, but the value will be returned from the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( &$called_generator ) {
				$called_generator = true;

				return [];
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertFalse( $refreshed );
		$this->assertFalse( $called_generator );
		$this->assert_cache_contains( $old );

		// Fourth call will call the generator, write to the DB, and cache the value in the in-memory cache,
		// but only because we are forcing it to refresh.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $another_value, &$called_generator ) {
				$called_generator = true;

				return $another_value;
			},
			'__return_true',
			true, // It will refresh only because we are forcing it.
			$refreshed
		);

		$this->assertEquals( $another_value, $res );
		$this->assertTrue( $refreshed );
		$this->assertTrue( $called_generator );
		$this->assert_cache_contains( $another_value );
	}

	public function test_get_or_add_with_no_cached_data_fetches_but_does_not_refresh_on_subsequent_db_write_errors() {
		$value         = [ 'mock' => true ];
		$another_value = [ 'another_mock' => true ];

		// All DB write queries will fail.
		add_filter(
			'query',
			function ( $query ) {
				if ( str_starts_with( $query, 'UPDATE' ) || str_starts_with( $query, 'INSERT INTO' ) ) {
					return false;
				}

				return $query;
			}
		);

		// First call will call the generator, fail to write to the DB, and cache the value in the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value, &$called_generator ) {
				$called_generator = true;

				return $value;
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertTrue( $refreshed );
		$this->assertTrue( $called_generator );

		// The second call will NOT call the generator, but the value will be returned from the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( &$called_generator ) {
				$called_generator = true;

				return [];
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertFalse( $refreshed );
		$this->assertFalse( $called_generator );

		remove_all_filters( 'query' );

		// Third call will call the generator, write to the DB, and cache the value in the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $another_value, &$called_generator ) {
				$called_generator = true;

				return $another_value;
			},
			'__return_true',
			true, // It will refresh only because we are forcing it.
			$refreshed
		);

		$this->assertEquals( $another_value, $res );
		$this->assertTrue( $refreshed );
		$this->assertTrue( $called_generator );
		$this->assert_cache_contains( $another_value );
	}

	public function test_get_or_add_refreshes_on_cache_cleared_despite_previous_db_write_errors() {
		$old           = [ 'old' => true ];
		$value         = [ 'mock' => true ];
		$another_value = [ 'another_mock' => true ];

		// Write an expired cache value.
		$this->write_mock_cache( $old, time() - YEAR_IN_SECONDS );

		// All DB write queries will fail.
		add_filter(
			'query',
			function ( $query ) {
				if ( str_starts_with( $query, 'UPDATE' ) || str_starts_with( $query, 'INSERT INTO' ) ) {
					return false;
				}

				return $query;
			}
		);

		// First call will call the generator, fail to write to the DB, and cache the value in the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value, &$called_generator ) {
				$called_generator = true;

				return $value;
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertTrue( $refreshed );
		$this->assertTrue( $called_generator );
		$this->assert_cache_contains( $old );

		// The second call will NOT call the generator, but the value will be returned from the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( &$called_generator ) {
				$called_generator = true;

				return [];
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $value, $res );
		$this->assertFalse( $refreshed );
		$this->assertFalse( $called_generator );

		// Clear the cache.
		$this->database_cache->delete( self::MOCK_KEY );

		// Third call will call the generator, fail to write to the DB, and cache the value in the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $another_value, &$called_generator ) {
				$called_generator = true;

				return $another_value;
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $another_value, $res );
		$this->assertTrue( $refreshed );
		$this->assertTrue( $called_generator );

		// Fourth call will NOT call the generator, but the value will be returned from the in-memory cache.
		$called_generator = false;
		$refreshed        = false;
		$res              = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( &$called_generator ) {
				$called_generator = true;

				return [];
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $another_value, $res );
		$this->assertFalse( $refreshed );
		$this->assertFalse( $called_generator );

		remove_all_filters( 'query' );
	}

	public function test_delete_clears_wp_object_cache_even_when_db_option_missing() {
		$cache_contents = [
			'data'    => [ 'stale' => true ],
			'fetched' => time() - HOUR_IN_SECONDS,
			'errored' => false,
		];

		// Seed the WP object cache directly (simulating Memcached having a stale entry).
		wp_cache_set( self::MOCK_KEY, $cache_contents, 'options' );

		// Ensure there's NO DB option row — simulates the double-delete scenario.
		delete_option( self::MOCK_KEY );

		// Sanity check: WP object cache has the stale entry.
		$this->assertNotFalse( wp_cache_get( self::MOCK_KEY, 'options' ), 'Precondition: WP object cache should have the stale entry.' );

		// Act: delete via Database_Cache.
		$this->database_cache->delete( self::MOCK_KEY );

		// Assert: WP object cache entry must be gone.
		$this->assertFalse( wp_cache_get( self::MOCK_KEY, 'options' ), 'wp_cache_delete should be called even when delete_option returns false.' );
	}

	public function test_delete_clears_wp_object_cache_when_db_option_exists() {
		$value = [ 'mock' => true ];

		// Write a valid cache entry to DB (non-autoloaded, matching production behavior).
		$this->write_mock_cache( $value );

		// Sanity check: both DB and WP object cache have the entry.
		$this->assertNotFalse( get_option( self::MOCK_KEY ), 'Precondition: DB option should exist.' );
		$this->assertNotFalse( wp_cache_get( self::MOCK_KEY, 'options' ), 'Precondition: WP object cache should have the entry.' );

		// Act: delete via Database_Cache.
		$this->database_cache->delete( self::MOCK_KEY );

		// Assert: both DB and WP object cache entries must be gone.
		$this->assertFalse( get_option( self::MOCK_KEY ), 'DB option should be deleted.' );
		$this->assertFalse( wp_cache_get( self::MOCK_KEY, 'options' ), 'WP object cache entry should be deleted.' );
	}

	public function test_get_or_add_does_not_refresh_if_disabled() {
		$refreshed = false;
		$value     = [ 'mock' => true ];
		$old       = [ 'old' => true ];

		$this->write_mock_cache( $old, time() - YEAR_IN_SECONDS );

		$this->database_cache->disable_refresh();

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value ) {
				return $value;
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertEquals( $old, $res );
		$this->assertFalse( $refreshed );
		$this->assert_cache_contains( $old );
	}

	public function test_address_autocomplete_errored_cache_expires_after_two_minutes() {
		// An errored address autocomplete cache entry should expire after 2 minutes,
		// allowing a quick retry rather than being stuck for 12 hours.
		update_option(
			Database_Cache::ADDRESS_AUTOCOMPLETE_JWT_KEY,
			[
				'data'    => null,
				'fetched' => time() - 3 * MINUTE_IN_SECONDS,
				'errored' => true,
			],
			'no'
		);

		$generator_called = false;
		$this->database_cache->get_or_add(
			Database_Cache::ADDRESS_AUTOCOMPLETE_JWT_KEY,
			function () use ( &$generator_called ) {
				$generator_called = true;
				return 'new_token';
			},
			'__return_true'
		);

		$this->assertTrue( $generator_called, 'Generator should be called because the errored cache (2 min TTL) has expired.' );
	}

	public function test_address_autocomplete_success_cache_does_not_expire_after_two_minutes() {
		// A successful address autocomplete cache entry should last 12 hours,
		// not expire after just 2 minutes.
		update_option(
			Database_Cache::ADDRESS_AUTOCOMPLETE_JWT_KEY,
			[
				'data'    => 'valid_jwt_token',
				'fetched' => time() - 3 * MINUTE_IN_SECONDS,
				'errored' => false,
			],
			'no'
		);

		$generator_called = false;
		$result           = $this->database_cache->get_or_add(
			Database_Cache::ADDRESS_AUTOCOMPLETE_JWT_KEY,
			function () use ( &$generator_called ) {
				$generator_called = true;
				return 'new_token';
			},
			'__return_true'
		);

		$this->assertFalse( $generator_called, 'Generator should NOT be called because the successful cache (12h TTL) has not expired.' );
		$this->assertSame( 'valid_jwt_token', $result );
	}

	/**
	 * @dataProvider provider_errored_ttl_ladder
	 */
	public function test_tracking_info_errored_entries_use_progressive_backoff( int $consecutive_errors, int $expected_ttl ) {
		$this->assert_cache_get_respects_ttl(
			Database_Cache::TRACKING_INFO_KEY,
			[ 'tracking' => true ],
			true,
			$expected_ttl,
			$consecutive_errors
		);
	}

	public function provider_errored_ttl_ladder(): array {
		return [
			'zero (legacy payload)'          => [ 0, 2 * MINUTE_IN_SECONDS ],
			'first error'                    => [ 1, 2 * MINUTE_IN_SECONDS ],
			'second error'                   => [ 2, 5 * MINUTE_IN_SECONDS ],
			'third error'                    => [ 3, 10 * MINUTE_IN_SECONDS ],
			'fourth error'                   => [ 4, 15 * MINUTE_IN_SECONDS ],
			'fifth error stays at cap'       => [ 5, 15 * MINUTE_IN_SECONDS ],
			'tenth error stays at cap'       => [ 10, 15 * MINUTE_IN_SECONDS ],
			'hundredth error stays at cap'   => [ 100, 15 * MINUTE_IN_SECONDS ],
			'negative (defensive) clamps up' => [ -1, 2 * MINUTE_IN_SECONDS ],
		];
	}

	public function test_tracking_info_errored_entries_without_counter_use_first_backoff_step() {
		$this->assert_cache_get_respects_ttl(
			Database_Cache::TRACKING_INFO_KEY,
			[ 'tracking' => true ],
			true,
			2 * MINUTE_IN_SECONDS
		);
	}

	public function test_errored_write_sets_consecutive_errors_to_one_on_first_failure() {
		$refreshed = false;

		$this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () {
				return false; // Errored generator.
			},
			'__return_true',
			false,
			$refreshed
		);

		$this->assertFalse( $refreshed );
		$cache = get_option( self::MOCK_KEY );
		$this->assertSame( 1, $cache['consecutive_errors'] );
		$this->assertTrue( $cache['errored'] );
	}

	public function test_errored_write_increments_consecutive_errors_from_previous_entry() {
		$this->write_mock_cache( null, time() - HOUR_IN_SECONDS, true, 2 );
		$refreshed = false;

		$this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () {
				return false;
			},
			'__return_true',
			true, // Force refresh to bypass freshness check.
			$refreshed
		);

		$cache = get_option( self::MOCK_KEY );
		$this->assertSame( 3, $cache['consecutive_errors'] );
	}

	public function test_errored_write_grows_counter_without_cap() {
		// Seed a very large previous counter to prove write_to_cache does not clamp.
		$this->write_mock_cache( null, time() - HOUR_IN_SECONDS, true, 50 );
		$refreshed = false;

		$this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () {
				return false;
			},
			'__return_true',
			true,
			$refreshed
		);

		$cache = get_option( self::MOCK_KEY );
		$this->assertSame( 51, $cache['consecutive_errors'] );
	}

	public function test_successful_write_resets_consecutive_errors_to_zero() {
		$this->write_mock_cache( null, time() - HOUR_IN_SECONDS, true, 4 );
		$refreshed = false;

		$value = [ 'mock' => true ];
		$this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () use ( $value ) {
				return $value;
			},
			'__return_true',
			true,
			$refreshed
		);

		$this->assertTrue( $refreshed );
		$cache = get_option( self::MOCK_KEY );
		$this->assertSame( 0, $cache['consecutive_errors'] );
		$this->assertFalse( $cache['errored'] );
	}

	public function test_errored_write_without_previous_entry_starts_at_one() {
		// No previous cache entry.
		delete_option( self::MOCK_KEY );
		$refreshed = false;

		$this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () {
				return false;
			},
			'__return_true',
			false,
			$refreshed
		);

		$cache = get_option( self::MOCK_KEY );
		$this->assertSame( 1, $cache['consecutive_errors'] );
	}

	/**
	 * @dataProvider provider_account_key_ladder
	 */
	public function test_account_key_errored_ttl_follows_ladder( int $consecutive_errors, int $expected_ttl_seconds ) {
		set_current_screen( 'edit-page' );

		$this->assert_cache_get_respects_ttl(
			Database_Cache::ACCOUNT_KEY,
			[ 'id' => 'acct_test' ],
			true,
			$expected_ttl_seconds,
			$consecutive_errors
		);

		set_current_screen( 'front' );
	}

	public function provider_account_key_ladder(): array {
		return [
			'step 1 (first error)'  => [ 1, 2 * MINUTE_IN_SECONDS ],
			'step 2 (second error)' => [ 2, 5 * MINUTE_IN_SECONDS ],
			'step 3 (third error)'  => [ 3, 10 * MINUTE_IN_SECONDS ],
			'step 4 (fourth error)' => [ 4, 15 * MINUTE_IN_SECONDS ],
			'step cap (10 errors)'  => [ 10, 15 * MINUTE_IN_SECONDS ],
			'legacy (0 counter)'    => [ 0, 2 * MINUTE_IN_SECONDS ],
		];
	}

	public function test_account_key_successful_ttl_is_two_hours_in_admin() {
		set_current_screen( 'edit-page' );

		$this->assert_cache_get_respects_ttl(
			Database_Cache::ACCOUNT_KEY,
			[ 'id' => 'acct_test' ],
			false,
			2 * HOUR_IN_SECONDS,
			0
		);
		set_current_screen( 'front' );
	}

	/**
	 * @dataProvider provider_currencies_key_ladder
	 */
	public function test_currencies_key_errored_ttl_follows_ladder( int $consecutive_errors, int $expected_ttl_seconds ) {
		set_current_screen( 'edit-page' );

		$this->assert_cache_get_respects_ttl(
			Database_Cache::CURRENCIES_KEY,
			[
				'currencies' => [],
				'updated'    => time(),
			],
			true,
			$expected_ttl_seconds,
			$consecutive_errors
		);
		set_current_screen( 'front' );
	}

	public function provider_currencies_key_ladder(): array {
		return [
			'step 1' => [ 1, 2 * MINUTE_IN_SECONDS ],
			'step 2' => [ 2, 5 * MINUTE_IN_SECONDS ],
			'step 3' => [ 3, 10 * MINUTE_IN_SECONDS ],
			'step 4' => [ 4, 15 * MINUTE_IN_SECONDS ],
			'cap'    => [ 10, 15 * MINUTE_IN_SECONDS ],
		];
	}

	public function test_errored_write_over_legacy_entry_without_counter_starts_at_one() {
		// Legacy entry: has errored field but no consecutive_errors.
		update_option(
			self::MOCK_KEY,
			[
				'data'    => null,
				'fetched' => time() - HOUR_IN_SECONDS,
				'errored' => true,
			],
			'no'
		);
		$refreshed = false;

		$this->database_cache->get_or_add(
			self::MOCK_KEY,
			function () {
				return false;
			},
			'__return_true',
			true,
			$refreshed
		);

		$cache = get_option( self::MOCK_KEY );
		$this->assertSame( 1, $cache['consecutive_errors'] );
	}

	private function write_mock_cache( $data, ?int $fetch_time = null, bool $errored = false, ?int $consecutive_errors = null ) {
		$this->write_cache(
			self::MOCK_KEY,
			$data,
			$fetch_time,
			$errored,
			$consecutive_errors
		);
	}

	private function assert_cache_contains( $data, $errored = false, ?int $consecutive_errors = null ) {
		$cache_contents = get_option( self::MOCK_KEY );
		$this->assertIsArray( $cache_contents );
		$this->assertEquals( $data, $cache_contents['data'] );
		$this->assertEquals( $errored, $cache_contents['errored'] );

		if ( null !== $consecutive_errors ) {
			$this->assertEquals( $consecutive_errors, $cache_contents['consecutive_errors'] ?? null );
		}
	}

	private function assert_cache_get_respects_ttl( string $key, $data, bool $errored, int $ttl, ?int $consecutive_errors = null ) {
		$buffer_seconds = 5;

		$this->write_cache( $key, $data, time() - $ttl + $buffer_seconds, $errored, $consecutive_errors );
		$this->database_cache = new Database_Cache();
		$this->assertSame( $data, $this->database_cache->get( $key ) );

		$this->write_cache( $key, $data, time() - $ttl - $buffer_seconds, $errored, $consecutive_errors );
		$this->database_cache = new Database_Cache();
		$this->assertNull( $this->database_cache->get( $key ) );
	}

	private function write_cache( string $key, $data, ?int $fetch_time = null, bool $errored = false, ?int $consecutive_errors = null ) {
		$contents = [
			'data'    => $data,
			'fetched' => $fetch_time ?? time(),
			'errored' => $errored,
		];

		if ( null !== $consecutive_errors ) {
			$contents['consecutive_errors'] = $consecutive_errors;
		}

		update_option(
			$key,
			$contents,
			'no' // Match production: Database_Cache stores options as non-autoloaded.
		);
		wp_cache_delete( $key, 'options' );
	}
}

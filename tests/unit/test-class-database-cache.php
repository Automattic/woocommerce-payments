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
class Database_Cache_Test extends WP_UnitTestCase {

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

		parent::tear_down();
	}

	public function test_get_or_add_returns_cached_value() {
		$refreshed = false;
		$value     = [ 'mock' => true ];

		$this->write_mock_cache( $value );

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function() {
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
			function() use ( $value ) {
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
			function() use ( $value ) {
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
			function() use ( $value ) {
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
			function() use ( $value ) {
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
		$value            = [ 'mock' => true ];
		$old              = [ 'old' => true ];

		$this->write_mock_cache( $old, time() - YEAR_IN_SECONDS );

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function() use ( &$called_generator ) {
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
		$value            = [ 'mock' => true ];

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function() use ( &$called_generator ) {
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
			function() use ( $value ) {
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
			function() use ( $value ) {
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

	public function test_get_or_add_does_not_refresh_if_disabled() {
		$refreshed = false;
		$value     = [ 'mock' => true ];
		$old       = [ 'old' => true ];

		$this->write_mock_cache( $old, time() - YEAR_IN_SECONDS );

		$this->database_cache->disable_refresh();

		$res = $this->database_cache->get_or_add(
			self::MOCK_KEY,
			function() use ( $value ) {
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

	public function test_delete_cache_by_prefix_will_not_delete_values_that_are_not_cache_keys() {
		$cache_value = 'foo';
		$this->write_mock_cache( $cache_value, time() + YEAR_IN_SECONDS );

		$this->database_cache->delete_by_prefix( self::MOCK_KEY );

		$this->assert_cache_contains( $cache_value );
	}

	public function test_delete_cache_by_prefix_will_delete_cached_data_that_starts_with_prefix() {
		$payment_method_cache_key_one = Database_Cache::PAYMENT_METHODS_KEY_PREFIX . '1';
		$payment_method_cache_key_two = Database_Cache::PAYMENT_METHODS_KEY_PREFIX . '2';
		$this->database_cache->add( $payment_method_cache_key_one, 'foo' );
		$this->database_cache->add( $payment_method_cache_key_two, 'bar' );

		$this->database_cache->delete_by_prefix( Database_Cache::PAYMENT_METHODS_KEY_PREFIX );

		$this->assertNull( $this->database_cache->get( $payment_method_cache_key_one ) );
		$this->assertNull( $this->database_cache->get( $payment_method_cache_key_two ) );
	}

	private function write_mock_cache( $data, ?int $fetch_time = null, bool $errored = false ) {
		update_option(
			self::MOCK_KEY,
			[
				'data'    => $data,
				'fetched' => $fetch_time ?? time(),
				'errored' => $errored,
			]
		);
	}

	private function assert_cache_contains( $data, $errored = false ) {
		$cache_contents = get_option( self::MOCK_KEY );
		$this->assertIsArray( $cache_contents );
		$this->assertEquals( $data, $cache_contents['data'] );
		$this->assertEquals( $errored, $cache_contents['errored'] );
	}
}

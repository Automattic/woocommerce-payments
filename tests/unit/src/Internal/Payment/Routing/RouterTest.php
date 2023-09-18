<?php
/**
 * Class RouterTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use WCPAY_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Get_Payment_Process_Factors;
use WCPay\Database_Cache;
use WCPay\Exceptions\API_Exception;
use WCPay\Internal\Payment\Routing\Factor;
use WCPay\Internal\Payment\Routing\Router;

/**
 * New payment process as a router router test.
 */
class RouterTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var Router
	 */
	private $sut;

	/**
	 * Database cache mock.
	 *
	 * @var Database_Cache|MockObject
	 */
	private $mock_db_cache;

	/**
	 * Tests set-up.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->mock_db_cache = $this->createMock( Database_Cache::class );
		$this->sut           = new Router( $this->mock_db_cache );
	}

	/**
	 * Tests that the router returns false if a factor is **not present** in the account cache.
	 */
	public function test_should_use_new_payment_process_returns_false_with_missing_factor() {
		$this->mock_db_cache_factors( [] );

		$result = $this->sut->should_use_new_payment_process( [ Factor::USE_SAVED_PM() ] );
		$this->assertFalse( $result );
	}

	/**
	 * Tests that the router returns false if a factor is **false** in the account cache.
	 */
	public function test_should_use_new_payment_process_returns_false_with_unavailable_factor() {
		$this->mock_db_cache_factors( [ Factor::USE_SAVED_PM => false ] );

		$result = $this->sut->should_use_new_payment_process( [ Factor::USE_SAVED_PM() ] );
		$this->assertFalse( $result );
	}

	/**
	 * Tests that the router returns true when a factor is both present, and true in the account cache.
	 */
	public function test_should_use_new_payment_process_returns_true_with_available_factor() {
		$this->mock_db_cache_factors( [ Factor::USE_SAVED_PM => true ] );

		$result = $this->sut->should_use_new_payment_process( [ Factor::USE_SAVED_PM() ] );
		$this->assertTrue( $result );
	}

	/**
	 * Tests that the router handles multiple flags properly,
	 * and returns false in case any of them is not available.
	 */
	public function test_should_use_new_payment_process_with_multiple_factors_returns_false() {
		$this->mock_db_cache_factors(
			[
				Factor::USE_SAVED_PM        => true,
				Factor::SUBSCRIPTION_SIGNUP => false,
				Factor::WOOPAY_ENABLED      => true,
				Factor::PAYMENT_REQUEST     => false,
			]
		);

		$result = $this->sut->should_use_new_payment_process(
			[
				Factor::USE_SAVED_PM(),
				Factor::SUBSCRIPTION_SIGNUP(),
				Factor::WOOPAY_ENABLED(),
			]
		);
		$this->assertFalse( $result );
	}

	/**
	 * Tests that the router handles multiple flags properly,
	 * and returns true when all factors are present.
	 */
	public function test_should_use_new_payment_process_with_multiple_factors_returns_true() {
		$this->mock_db_cache_factors(
			[
				Factor::USE_SAVED_PM        => true,
				Factor::SUBSCRIPTION_SIGNUP => true,
				Factor::WOOPAY_ENABLED      => true,
				Factor::PAYMENT_REQUEST     => false,
			]
		);

		$result = $this->sut->should_use_new_payment_process(
			[
				Factor::USE_SAVED_PM(),
				Factor::SUBSCRIPTION_SIGNUP(),
				Factor::WOOPAY_ENABLED(),
			]
		);
		$this->assertTrue( $result );
	}

	/**
	 * Check that `get_allowed_factors` returns the factors, provided by the cache.
	 */
	public function test_get_allowed_factors_returns_factors() {
		$cached_factors    = [
			Factor::SAVE_PM             => true,
			Factor::SUBSCRIPTION_SIGNUP => false,
		];
		$processed_factors = [ Factor::SAVE_PM() ];

		$this->mock_db_cache_factors( $cached_factors, false );

		$result = $this->sut->get_allowed_factors();

		$this->assertIsArray( $result );
		$this->assertSame( $processed_factors, $result );
	}

	/**
	 * Ensures that `get_allowed_factors` returns an array, even with broken cache.
	 */
	public function test_get_allowed_factors_returns_empty_array() {
		// Return nothing to force an empty array.
		$this->mock_db_cache_factors( null, false );

		$result = $this->sut->get_allowed_factors();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Confirms that `get_allowed_factors` allows filters to work.
	 */
	public function test_get_allowed_factors_allows_filters() {
		$cached_factors   = [
			Factor::SAVE_PM             => true,
			Factor::SUBSCRIPTION_SIGNUP => false,
		];
		$replaced_factors = [
			Factor::NO_PAYMENT(),
		];
		$this->mock_db_cache_factors( $cached_factors, false );

		$filter_cb = function() use ( $replaced_factors ) {
			return $replaced_factors;
		};
		add_filter( 'wcpay_new_payment_process_enabled_factors', $filter_cb );

		$result = $this->sut->get_allowed_factors();

		$this->assertIsArray( $result );
		$this->assertSame( $replaced_factors, $result );

		remove_filter( 'wcpay_new_payment_process_enabled_factors', $filter_cb );
	}

	/**
	 * Verify that `is_valid_cache` returns false with a non-array.
	 */
	public function test_is_valid_cache_requires_array() {
		$this->assertFalse( $this->sut->is_valid_cache( false ) );
	}

	/**
	 * Verify that `is_valid_cache` returns false with incorrect arrays.
	 */
	public function test_is_valid_cache_requires_base_factor() {
		$cache = [ Factor::NO_PAYMENT => true ];
		$this->assertFalse( $this->sut->is_valid_cache( $cache ) );
	}

	/**
	 * Verify that `is_valid_cache` accepts well-formed data.
	 */
	public function test_is_valid_cache_with_well_formed_data() {
		$cache = [ Factor::NEW_PAYMENT_PROCESS => true ];
		$this->assertTrue( $this->sut->is_valid_cache( $cache ) );
	}

	/**
	 *
	 */
	public function test_get_cached_factors_populates_cache() {
		$request_response  = [
			Factor::NEW_PAYMENT_PROCESS => true,
		];
		$processed_factors = [ Factor::NEW_PAYMENT_PROCESS() ];

		$this->mock_wcpay_request( Get_Payment_Process_Factors::class, 1, null, $request_response );

		$this->mock_db_cache->expects( $this->once() )
			->method( 'get_or_add' )
			->with(
				Database_Cache::PAYMENT_PROCESS_FACTORS_KEY,
				$this->callback(
					function ( $cb ) use ( $request_response ) {
						return $request_response === $cb();
					}
				),
				[ $this->sut, 'is_valid_cache' ],
				false
			)
			->willReturn( $request_response );

		$result = $this->sut->get_allowed_factors();
		$this->assertSame( $processed_factors, $result );
	}

	/**
	 * Ensures that a server error would handle exceptions correctly.
	 */
	public function test_get_cached_factors_handles_exceptions() {
		$generator = function( $cb ) {
			$this->mock_wcpay_request( Get_Payment_Process_Factors::class )
				->expects( $this->once() )
				->method( 'format_response' )
				->will( $this->throwException( new API_Exception( 'Does not work', 'forced', 1234 ) ) );

			$result = $cb();
			return false === $result;
		};

		$this->mock_db_cache->expects( $this->once() )
			->method( 'get_or_add' )
			->with(
				Database_Cache::PAYMENT_PROCESS_FACTORS_KEY,
				$this->callback( $generator )
			)
			->willReturn( false );

		$this->assertEmpty( $this->sut->get_allowed_factors() );
	}

	/**
	 * Simulates specific factors, being returned by `Database_Cache`.
	 *
	 * @param array|null $factors  The factors to simulate.
	 * @param bool       $add_base Whether to add the base `NEW_PAYMENT_PROCESS` factor.
	 */
	private function mock_db_cache_factors( array $factors = null, bool $add_base = true ) {
		if ( $add_base && ! isset( $factors[ Factor::NEW_PAYMENT_PROCESS ] ) ) {
			$factors[ Factor::NEW_PAYMENT_PROCESS ] = true;
		}

		$this->mock_db_cache->expects( $this->once() )
			->method( 'get_or_add' )
			->with( Database_Cache::PAYMENT_PROCESS_FACTORS_KEY )
			->willreturn( $factors );
	}
}

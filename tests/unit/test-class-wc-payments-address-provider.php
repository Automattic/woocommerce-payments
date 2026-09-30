<?php
/**
 * Class WC_Payments_Address_Provider_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;

/**
 * WC_Payments_Address_Provider unit tests.
 */
class WC_Payments_Address_Provider_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var MockObject|WC_Payments_API_Client
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var MockObject|WC_Payments_Account
	 */
	private $mock_account;

	/**
	 * Mock Database_Cache.
	 *
	 * @var MockObject|Database_Cache
	 */
	private $mock_database_cache;

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Address_Provider
	 */
	private $provider;

	/**
	 * Pre-test setup.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'Automattic\\WooCommerce\\Internal\\AddressProvider\\AbstractAutomatticAddressProvider' ) ) {
			$this->markTestSkipped( 'AbstractAutomatticAddressProvider not available (requires WC 10.3+).' );
		}

		require_once WCPAY_ABSPATH . 'includes/class-wc-payments-address-provider.php';

		$this->mock_api_client     = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_account        = $this->createMock( WC_Payments_Account::class );
		$this->mock_database_cache = $this->createMock( Database_Cache::class );

		$this->provider = new WC_Payments_Address_Provider(
			$this->mock_api_client,
			$this->mock_account,
			$this->mock_database_cache
		);
	}

	public function test_get_address_service_jwt_returns_token_when_stripe_connected() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturnCallback(
				function ( $key, $generator ) {
					return $generator();
				}
			);

		$this->mock_api_client
			->method( 'get_address_autocomplete_token' )
			->willReturn( [ 'token' => 'jwt_test_token' ] );

		$result = $this->provider->get_address_service_jwt();

		$this->assertSame( 'jwt_test_token', $result );
	}

	public function test_get_address_service_jwt_returns_wp_error_when_not_stripe_connected() {
		$this->mock_account
			->expects( $this->once() )
			->method( 'is_stripe_connected' )
			->with( true )
			->willReturn( false );

		// Should not reach the cache at all.
		$this->mock_database_cache
			->expects( $this->never() )
			->method( 'get_or_add' );

		// Should clear any previously cached token.
		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'delete' )
			->with( Database_Cache::ADDRESS_AUTOCOMPLETE_JWT_KEY );

		$result = $this->provider->get_address_service_jwt();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wcpay_address_service_error', $result->get_error_code() );
	}

	public function test_get_address_service_jwt_returns_wp_error_on_api_exception() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturnCallback(
				function ( $key, $generator ) {
					return $generator();
				}
			);

		$this->mock_api_client
			->method( 'get_address_autocomplete_token' )
			->willThrowException( new Exception( 'API error' ) );

		$result = $this->provider->get_address_service_jwt();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wcpay_address_service_error', $result->get_error_code() );
	}

	public function test_get_address_service_jwt_returns_wp_error_when_token_missing_from_response() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturnCallback(
				function ( $key, $generator ) {
					return $generator();
				}
			);

		$this->mock_api_client
			->method( 'get_address_autocomplete_token' )
			->willReturn( [] );

		$result = $this->provider->get_address_service_jwt();

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_get_address_service_jwt_returns_cached_token() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturn( 'cached_jwt_token' );

		// API client should not be called when cache is warm.
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_address_autocomplete_token' );

		$result = $this->provider->get_address_service_jwt();

		$this->assertSame( 'cached_jwt_token', $result );
	}

	public function test_get_address_service_jwt_returns_wp_error_when_cache_returns_null() {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		// When get_or_add returns null (e.g. after a failed fetch with no prior cached data),
		// the method should return a WP_Error instead of null.
		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturn( null );

		$result = $this->provider->get_address_service_jwt();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wcpay_address_service_error', $result->get_error_code() );
	}

	public function test_generator_returns_null_on_api_failure_for_cache_error_detection() {
		// Verify the generator returns null (not INVALID_TOKEN) so that
		// Database_Cache::get_or_add treats it as an error and uses a shorter TTL.
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$captured_generator = null;
		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturnCallback(
				function ( $key, $generator ) use ( &$captured_generator ) {
					$captured_generator = $generator;
					return $generator();
				}
			);

		$this->mock_api_client
			->method( 'get_address_autocomplete_token' )
			->willThrowException( new Exception( 'API error' ) );

		$this->provider->get_address_service_jwt();

		// Call the generator directly to verify it returns null, not INVALID_TOKEN.
		$this->assertNull( $captured_generator() );
	}

	public function test_get_address_service_jwt_returns_wp_error_for_legacy_cached_invalid_token() {
		// Backward compat: a cached INVALID_TOKEN string from a pre-fix version
		// should still be treated as an error.
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturn( WC_Payments_Address_Provider::INVALID_TOKEN );

		$result = $this->provider->get_address_service_jwt();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wcpay_address_service_error', $result->get_error_code() );
	}

	public function test_get_address_service_jwt_passes_on_error_true_to_connection_check() {
		// Asserts that get_address_service_jwt() calls is_stripe_connected() with
		// on_error=true. That flag makes the connection check resolve to "connected"
		// when account data is transiently unavailable, so a transient error proceeds
		// to the cache rather than deleting a potentially valid cached token.
		$this->mock_account
			->expects( $this->once() )
			->method( 'is_stripe_connected' )
			->with( true )
			->willReturn( true );

		$this->mock_database_cache
			->expects( $this->never() )
			->method( 'delete' );

		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturn( 'cached_jwt_token' );

		$result = $this->provider->get_address_service_jwt();

		$this->assertSame( 'cached_jwt_token', $result );
	}
}

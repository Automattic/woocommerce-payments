<?php
/**
 * Class WC_Payments_Customer_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Customer_Service unit tests.
 */
class WC_Payments_Customer_Service_Test extends WP_UnitTestCase {

	const CUSTOMER_LIVE_META_KEY = '_wcpay_customer_id_live';
	const CUSTOMER_TEST_META_KEY = '_wcpay_customer_id_test';

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->customer_service = new WC_Payments_Customer_Service( $this->mock_api_client );
	}

	/**
	 * Post-test teardown
	 */
	public function tearDown() {
		delete_user_option( 1, self::CUSTOMER_LIVE_META_KEY );
		delete_user_option( 1, self::CUSTOMER_TEST_META_KEY );
		delete_user_option( 1, '_wcpay_customer_id' );
		WC_Payments::get_gateway()->update_option( 'test_mode', 'no' );
		parent::tearDown();
	}

	/**
	 * Test get customer ID by user ID for live mode.
	 */
	public function test_get_customer_id_by_user_id() {
		update_user_option( 1, self::CUSTOMER_LIVE_META_KEY, 'cus_test12345' );

		$customer_id = $this->customer_service->get_customer_id_by_user_id( 1 );

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

	/**
	 * Test get customer ID by user ID for test mode.
	 */
	public function test_get_customer_id_by_user_id_test_mode() {
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );
		update_user_option( 1, self::CUSTOMER_TEST_META_KEY, 'cus_test12345' );

		$customer_id = $this->customer_service->get_customer_id_by_user_id( 1 );

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

	public function test_get_customer_id_by_user_id_migrates_deprecated_meta() {
		update_user_option( 1, '_wcpay_customer_id', 'cus_12345' );

		$customer_id = $this->customer_service->get_customer_id_by_user_id( 1 );

		$this->assertEquals( 'cus_12345', $customer_id );
		$this->assertEquals( 'cus_12345', get_user_option( self::CUSTOMER_LIVE_META_KEY, 1 ) );
		$this->assertFalse( get_user_option( self::CUSTOMER_TEST_META_KEY, 1 ) );
		$this->assertFalse( get_user_option( '_wcpay_customer_id', 1 ) );
	}

	public function test_get_customer_id_by_user_id_does_not_migrate_deprecated_meta_test_mode() {
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );
		update_user_option( 1, '_wcpay_customer_id', 'cus_12345' );

		$customer_id = $this->customer_service->get_customer_id_by_user_id( 1 );

		$this->assertNull( $customer_id );
		$this->assertEquals( 'cus_12345', get_user_option( '_wcpay_customer_id', 1 ) );
		$this->assertFalse( get_user_option( self::CUSTOMER_LIVE_META_KEY, 1 ) );
		$this->assertFalse( get_user_option( self::CUSTOMER_TEST_META_KEY, 1 ) );
	}

	/**
	 * Test get customer ID by user ID when no stored customer ID.
	 */
	public function test_get_customer_id_by_user_id_when_no_stored_customer_id() {
		$customer_id = $this->customer_service->get_customer_id_by_user_id( 1 );

		$this->assertEquals( null, $customer_id );
	}

	/**
	 * Test get customer ID by user ID with null user ID.
	 */
	public function test_get_customer_id_by_user_id_with_null_user_id() {
		$customer_id = $this->customer_service->get_customer_id_by_user_id( null );

		$this->assertEquals( null, $customer_id );
	}

	/**
	 * Test get customer ID by user ID with user ID of 0.
	 */
	public function test_get_customer_id_by_user_id_with_user_id_of_zero() {
		$customer_id = $this->customer_service->get_customer_id_by_user_id( 0 );

		$this->assertEquals( null, $customer_id );
	}

	/**
	 * Test create customer for user for live mode.
	 *
	 * @throws API_Exception
	 */
	public function test_create_customer_for_user() {
		$user             = new WP_User( 1 );
		$user->user_login = 'testUser';

		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with( 'Test User', 'test.user@example.com', 'Name: Test User, Username: testUser' )
			->willReturn( 'cus_test12345' );

		$customer_id = $this->customer_service->create_customer_for_user( $user, 'Test User', 'test.user@example.com' );

		$this->assertEquals( 'cus_test12345', $customer_id );
		$this->assertEquals( 'cus_test12345', get_user_option( self::CUSTOMER_LIVE_META_KEY, $user->ID ) );
		$this->assertEquals( false, get_user_option( self::CUSTOMER_TEST_META_KEY, $user->ID ) );
	}

	/**
	 * Test create customer for user for test mode.
	 */
	public function test_create_customer_for_user_test_mode() {
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );
		$user             = new WP_User( 1 );
		$user->user_login = 'testUser';

		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with( 'Test User', 'test.user@example.com', 'Name: Test User, Username: testUser' )
			->willReturn( 'cus_test12345' );

		$customer_id = $this->customer_service->create_customer_for_user( $user, 'Test User', 'test.user@example.com' );

		$this->assertEquals( 'cus_test12345', $customer_id );
		$this->assertEquals( 'cus_test12345', get_user_option( self::CUSTOMER_TEST_META_KEY, $user->ID ) );
		$this->assertEquals( false, get_user_option( self::CUSTOMER_LIVE_META_KEY, $user->ID ) );
	}

	/**
	 * Test update customer for user.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_for_user() {
		$user = new WP_User( 0 );

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_customer' )
			->with(
				'cus_test12345',
				[
					'name'        => 'Test User',
					'email'       => 'test.user@example.com',
					'description' => 'Name: Test User, Guest',
				]
			);

		$customer_id = $this->customer_service->update_customer_for_user(
			'cus_test12345',
			$user,
			'Test User',
			'test.user@example.com'
		);

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

	/**
	 * Test update customer for user when user not found for live mode.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_for_user_when_user_not_found() {
		$user             = new WP_User( 1 );
		$user->user_login = 'testUser';

		// Wire the mock to throw a resource not found exception.
		$this->mock_api_client->expects( $this->once() )
			->method( 'update_customer' )
			->with(
				'cus_test12345',
				[
					'name'        => 'Test User',
					'email'       => 'test.user@example.com',
					'description' => 'Name: Test User, Username: testUser',
				]
			)
			->willThrowException( new API_Exception( 'Error Message', 'resource_missing', 400 ) );

		// Check that the API call to create customer happens.
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with( 'Test User', 'test.user@example.com', 'Name: Test User, Username: testUser' )
			->willReturn( 'cus_test67890' );

		$customer_id = $this->customer_service->update_customer_for_user(
			'cus_test12345',
			$user,
			'Test User',
			'test.user@example.com'
		);

		$this->assertEquals( 'cus_test67890', $customer_id );
		$this->assertEquals( 'cus_test67890', get_user_option( self::CUSTOMER_LIVE_META_KEY, $user->ID ) );
	}

	/**
	 * Test update customer for user when user not found for test mode.
	 */
	public function test_update_customer_for_user_when_user_not_found_test_mode() {
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );
		$user             = new WP_User( 1 );
		$user->user_login = 'testUser';

		// Wire the mock to throw a resource not found exception.
		$this->mock_api_client->expects( $this->once() )
			->method( 'update_customer' )
			->with(
				'cus_test12345',
				[
					'name'        => 'Test User',
					'email'       => 'test.user@example.com',
					'description' => 'Name: Test User, Username: testUser',
				]
			)
			->willThrowException( new API_Exception( 'Error Message', 'resource_missing', 400 ) );

		// Check that the API call to create customer happens.
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with( 'Test User', 'test.user@example.com', 'Name: Test User, Username: testUser' )
			->willReturn( 'cus_test67890' );

		$customer_id = $this->customer_service->update_customer_for_user(
			'cus_test12345',
			$user,
			'Test User',
			'test.user@example.com'
		);

		$this->assertEquals( 'cus_test67890', $customer_id );
		$this->assertEquals( 'cus_test67890', get_user_option( self::CUSTOMER_TEST_META_KEY, $user->ID ) );
	}

	/**
	 * Test update customer for user when a general exception is thrown.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_for_user_when_general_exception_is_thrown() {
		$user             = new WP_User( 1 );
		$user->user_login = 'testUser';

		// Wire the mock to throw a resource not found exception.
		$this->mock_api_client->expects( $this->once() )
			->method( 'update_customer' )
			->with(
				'cus_test12345',
				[
					'name'        => 'Test User',
					'email'       => 'test.user@example.com',
					'description' => 'Name: Test User, Username: testUser',
				]
			)
			->willThrowException( new API_Exception( 'Generic Error Message', 'generic_error', 500 ) );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Generic Error Message' );

		$this->customer_service->update_customer_for_user(
			'cus_test12345',
			$user,
			'Test User',
			'test.user@example.com'
		);
	}

	public function test_set_default_payment_method_for_customer() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_customer' )
			->with(
				'cus_12345',
				[
					'invoice_settings' => [ 'default_payment_method' => 'pm_mock' ],
				]
			);

			$this->customer_service->set_default_payment_method_for_customer( 'cus_12345', 'pm_mock' );
	}

	public function test_get_payment_methods_for_customer_fetches_from_api() {
		$mock_payment_methods = [
			[ 'id' => 'pm_mock1' ],
			[ 'id' => 'pm_mock2' ],
		];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_methods' )
			->with( 'cus_12345', 'card' )
			->willReturn( [ 'data' => $mock_payment_methods ] );

		$response = $this->customer_service->get_payment_methods_for_customer( 'cus_12345' );

		$this->assertEquals( $mock_payment_methods, $response );
	}

	public function test_get_payment_methods_for_customer_fetches_from_transient() {
		$mock_payment_methods = [
			[ 'id' => 'pm_mock1' ],
			[ 'id' => 'pm_mock2' ],
		];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_methods' )
			->with( 'cus_12345', 'card' )
			->willReturn( [ 'data' => $mock_payment_methods ] );

		$response = $this->customer_service->get_payment_methods_for_customer( 'cus_12345' );
		$this->assertEquals( $mock_payment_methods, $response );

		$response = $this->customer_service->get_payment_methods_for_customer( 'cus_12345' );
		$this->assertEquals( $mock_payment_methods, $response );
	}

	public function test_get_payment_methods_for_customer_no_customer() {
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_payment_methods' );

		$response = $this->customer_service->get_payment_methods_for_customer( '' );
		$this->assertEquals( [], $response );
	}

	public function test_update_payment_method_with_billing_details_from_order() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_payment_method' )
			->with(
				'pm_mock',
				[
					'billing_details' => [
						'address' => [
							'city'        => 'WooCity',
							'country'     => 'US',
							'line1'       => 'WooAddress',
							'postal_code' => '12345',
							'state'       => 'NY',
						],
						'email'   => 'admin@example.org',
						'name'    => 'Jeroen Sormani',
						'phone'   => '555-32123',
					],
				]
			);

		$order = WC_Helper_Order::create_order();

		$this->customer_service->update_payment_method_with_billing_details_from_order( 'pm_mock', $order );
	}

	public function test_get_payment_methods_for_customer_not_throw_resource_missing_code_exception() {
		$this->mock_api_client->expects( $this->once() )
			->method( 'get_payment_methods' )
			->with( 'cus_test12345' )
			->willThrowException( new API_Exception( 'Error Message', 'resource_missing', 400 ) );

		try {
			$methods = $this->customer_service->get_payment_methods_for_customer( 'cus_test12345' );
			// We return an empty array as the exception was handled in the function and not bubbled up.
			$this->assertEquals( $methods, [] );
		} catch ( API_Exception $e ) {
			$this->fail( 'customer_service->get_payment_methods_for_customer not handling the resource_missing code of API_Exception.' );
		}
	}
}

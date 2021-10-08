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
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_account    = $this->createMock( WC_Payments_Account::class );

		$this->customer_service = new WC_Payments_Customer_Service( $this->mock_api_client, $this->mock_account );
	}

	/**
	 * Post-test teardown
	 */
	public function tearDown() {
		delete_user_option( 1, self::CUSTOMER_LIVE_META_KEY );
		delete_user_option( 1, self::CUSTOMER_TEST_META_KEY );
		delete_user_option( 1, '_wcpay_customer_id' );
		WC_Payments::get_gateway()->update_option( 'test_mode', 'no' );
		WC()->session->__unset( WC_Payments_Customer_Service::CUSTOMER_ID_SESSION_KEY );
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

	public function test_get_customer_id_by_user_id_migrates_deprecated_meta_to_live_key_for_live_accounts() {
		// We're using test mode here to assert the account is migrated to the live key regardless of it.
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );
		update_user_option( 1, '_wcpay_customer_id', 'cus_12345' );
		$this->mock_account->method( 'get_is_live' )->willReturn( true );

		$customer_id = $this->customer_service->get_customer_id_by_user_id( 1 );

		$this->assertNull( $customer_id );
		$this->assertEquals( 'cus_12345', get_user_option( self::CUSTOMER_LIVE_META_KEY, 1 ) );
		$this->assertFalse( get_user_option( self::CUSTOMER_TEST_META_KEY, 1 ) );
		$this->assertFalse( get_user_option( '_wcpay_customer_id', 1 ) );
	}

	public function test_get_customer_id_by_user_id_migrates_deprecated_meta_to_live_key_for_undefined_accounts() {
		update_user_option( 1, '_wcpay_customer_id', 'cus_12345' );
		$this->mock_account->method( 'get_is_live' )->willReturn( null );

		$customer_id = $this->customer_service->get_customer_id_by_user_id( 1 );

		$this->assertEquals( 'cus_12345', $customer_id );
		$this->assertEquals( 'cus_12345', get_user_option( self::CUSTOMER_LIVE_META_KEY, 1 ) );
		$this->assertFalse( get_user_option( self::CUSTOMER_TEST_META_KEY, 1 ) );
		$this->assertFalse( get_user_option( '_wcpay_customer_id', 1 ) );
	}

	public function test_get_customer_id_by_user_id_migrates_deprecated_meta_to_test_key_for_test_accounts() {
		// We're using live mode here to assert the account is migrated to the test key regardless of it.
		update_user_option( 1, '_wcpay_customer_id', 'cus_12345' );
		$this->mock_account->method( 'get_is_live' )->willReturn( false );

		$customer_id = $this->customer_service->get_customer_id_by_user_id( 1 );

		$this->assertNull( $customer_id );
		$this->assertEquals( 'cus_12345', get_user_option( self::CUSTOMER_TEST_META_KEY, 1 ) );
		$this->assertFalse( get_user_option( self::CUSTOMER_LIVE_META_KEY, 1 ) );
		$this->assertFalse( get_user_option( '_wcpay_customer_id', 1 ) );
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

		$mock_customer_data = $this->get_mock_customer_data();

		$this->mock_account->expects( $this->once() )
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'sift' => [ 'session_id' => 'woo_session_id' ] ] );

		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with(
				array_merge(
					$mock_customer_data,
					[ 'session_id' => 'woo_session_id' ]
				)
			)
			->willReturn( 'cus_test12345' );

		$customer_id = $this->customer_service->create_customer_for_user( $user, $mock_customer_data );

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

		$mock_customer_data = $this->get_mock_customer_data();

		$this->mock_account
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'sift' => [ 'session_id' => 'woo_session_id' ] ] );

		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with(
				array_merge(
					$mock_customer_data,
					[ 'session_id' => 'woo_session_id' ]
				)
			)
			->willReturn( 'cus_test12345' );

		$customer_id = $this->customer_service->create_customer_for_user( $user, $mock_customer_data );

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

		$mock_customer_data = $this->get_mock_customer_data();

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_customer' )
			->with(
				'cus_test12345',
				$mock_customer_data
			);

		$customer_id = $this->customer_service->update_customer_for_user(
			'cus_test12345',
			$user,
			$mock_customer_data
		);

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

	/**
	 * Test non logged in user keeps its customer id saved in the Session.
	 */
	public function test_non_logged_in_user_saves_customer_id_in_session() {
		$user               = new WP_User( 0 );
		$mock_customer_data = $this->get_mock_customer_data();
		$customer_id        = 'cus_test12345';

		$this->mock_account->expects( $this->once() )
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'sift' => [ 'session_id' => 'woo_session_id' ] ] );

		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with(
				array_merge(
					$mock_customer_data,
					[ 'session_id' => 'woo_session_id' ]
				)
			)
			->willReturn( $customer_id );

		$this->customer_service->create_customer_for_user( $user, $mock_customer_data );
		$this->assertEquals(
			WC()->session->get( WC_Payments_Customer_Service::CUSTOMER_ID_SESSION_KEY ),
			$this->customer_service->get_customer_id_by_user_id( 0 )
		);

		$this->assertEquals(
			WC()->session->get( WC_Payments_Customer_Service::CUSTOMER_ID_SESSION_KEY ),
			$customer_id
		);

	}

	/**
	 * Test update customer for user when user not found for live mode.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_for_user_when_user_not_found() {
		$user             = new WP_User( 1 );
		$user->user_login = 'testUser';

		$mock_customer_data = $this->get_mock_customer_data();

		// Wire the mock to throw a resource not found exception.
		$this->mock_api_client->expects( $this->once() )
			->method( 'update_customer' )
			->with(
				'cus_test12345',
				$mock_customer_data
			)
			->willThrowException( new API_Exception( 'Error Message', 'resource_missing', 400 ) );

		// Check that the API call to create customer happens.
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with(
				array_merge(
					$mock_customer_data,
					[ 'session_id' => null ]
				)
			)
			->willReturn( 'cus_test67890' );

		$customer_id = $this->customer_service->update_customer_for_user(
			'cus_test12345',
			$user,
			$mock_customer_data
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

		$mock_customer_data = $this->get_mock_customer_data();

		// Wire the mock to throw a resource not found exception.
		$this->mock_api_client->expects( $this->once() )
			->method( 'update_customer' )
			->with(
				'cus_test12345',
				$mock_customer_data
			)
			->willThrowException( new API_Exception( 'Error Message', 'resource_missing', 400 ) );

		// Check that the API call to create customer happens.
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with(
				array_merge(
					$mock_customer_data,
					[ 'session_id' => null ]
				)
			)
			->willReturn( 'cus_test67890' );

		$customer_id = $this->customer_service->update_customer_for_user(
			'cus_test12345',
			$user,
			$mock_customer_data
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

		$mock_customer_data = $this->get_mock_customer_data();

		// Wire the mock to throw a resource not found exception.
		$this->mock_api_client->expects( $this->once() )
			->method( 'update_customer' )
			->with(
				'cus_test12345',
				$mock_customer_data
			)
			->willThrowException( new API_Exception( 'Generic Error Message', 'generic_error', 500 ) );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Generic Error Message' );

		$this->customer_service->update_customer_for_user(
			'cus_test12345',
			$user,
			$mock_customer_data
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

	public function test_map_customer_data_from_order() {
		$mock_order    = $this->get_mock_wc_object_for_customer_data( WC_Order::class );
		$expected_data = $this->get_mock_customer_data();

		$customer_data = WC_Payments_Customer_Service::map_customer_data( $mock_order );

		$this->assertEquals( $expected_data, $customer_data );
	}

	public function test_map_customer_data_from_customer() {
		$mock_customer = $this->get_mock_wc_object_for_customer_data( WC_Customer::class );
		$expected_data = $this->get_mock_customer_data();

		$customer_data = WC_Payments_Customer_Service::map_customer_data( null, $mock_customer );

		$this->assertEquals( $expected_data, $customer_data );
	}

	public function test_map_customer_data_from_logged_in_customer() {
		$mock_customer = $this->get_mock_wc_object_for_customer_data( WC_Customer::class );
		$mock_customer->method( 'get_username' )->willReturn( 'testUser' );

		// Logged-in user should have a description.
		$expected_data = $this->get_mock_customer_data( [ 'description' => 'Name: Test Name, Username: testUser' ] );

		$customer_data = WC_Payments_Customer_Service::map_customer_data( null, $mock_customer );

		$this->assertEquals( $expected_data, $customer_data );
	}

	public function test_map_customer_data_from_order_and_customer() {
		$order_email = 'mock@order.email';

		$mock_order    = $this->get_mock_wc_object_for_customer_data( WC_Order::class, [ 'get_billing_email' => $order_email ] );
		$mock_customer = $this->get_mock_wc_object_for_customer_data( WC_Customer::class );
		$mock_customer->method( 'get_username' )->willReturn( 'testUser' );

		$expected_data = $this->get_mock_customer_data(
			[
				// The email on order should override the customer email.
				'email'       => 'mock@order.email',
				// Logged-in user should have a description.
				'description' => 'Name: Test Name, Username: testUser',
			]
		);

		$customer_data = WC_Payments_Customer_Service::map_customer_data( $mock_order, $mock_customer );

		$this->assertEquals( $expected_data, $customer_data );
	}

	public function test_map_customer_data_from_nulls() {
		$customer_data = WC_Payments_Customer_Service::map_customer_data();
		$this->assertEmpty( $customer_data );
	}

	private function get_mock_wc_object_for_customer_data( $object_class, $mock_return_overrides = [] ) {
		$mock_object = $this->getMockBuilder( $object_class )
			->disableOriginalConstructor()
			->getMock();

		$mock_return_values = array_merge(
			[
				'get_billing_first_name'  => 'Test',
				'get_billing_last_name'   => 'Name',
				'get_billing_email'       => 'test@customer.email',
				'get_billing_phone'       => '123456',
				'get_billing_address_1'   => '1 Street St',
				'get_billing_address_2'   => '',
				'get_billing_postcode'    => '09876',
				'get_billing_city'        => 'City',
				'get_billing_state'       => 'State',
				'get_billing_country'     => 'US',
				'get_shipping_first_name' => 'Shipping',
				'get_shipping_last_name'  => 'Ship',
				'get_shipping_address_1'  => '2 Street St',
				'get_shipping_address_2'  => '',
				'get_shipping_postcode'   => '76543',
				'get_shipping_city'       => 'City2',
				'get_shipping_state'      => 'State2',
				'get_shipping_country'    => 'US',
			],
			$mock_return_overrides
		);

		foreach ( $mock_return_values as $method => $value ) {
			$mock_object->method( $method )->willReturn( $value );
		}

		return $mock_object;
	}

	private function get_mock_customer_data( $overrides = [] ) {
		return array_merge(
			[
				'name'        => 'Test Name',
				'description' => 'Name: Test Name, Guest',
				'email'       => 'test@customer.email',
				'phone'       => '123456',
				'address'     => [
					'line1'       => '1 Street St',
					'line2'       => '',
					'postal_code' => '09876',
					'city'        => 'City',
					'state'       => 'State',
					'country'     => 'US',
				],
				'shipping'    => [
					'name'    => 'Shipping Ship',
					'address' => [
						'line1'       => '2 Street St',
						'line2'       => '',
						'postal_code' => '76543',
						'city'        => 'City2',
						'state'       => 'State2',
						'country'     => 'US',
					],
				],
			],
			$overrides
		);
	}

	/**
	 * Test $customer_service->get_customer_id_for_order( $order ).
	 */
	public function test_get_customer_id_for_order() {
		// order with no customer.
		$order = WC_Helper_Order::create_order();
		$order->set_customer_id( 0 );

		$this->assertEquals( $this->customer_service->get_customer_id_for_order( $order ), null );

		// reset order to belong to customer 1.
		$order->set_customer_id( 1 );

		// test fetching and existing WCPay customer ID from and order.
		update_user_option( 1, self::CUSTOMER_LIVE_META_KEY, 'wcpay_cus_12345' );
		$this->assertEquals( $this->customer_service->get_customer_id_for_order( $order ), 'wcpay_cus_12345' );

		// test creating a new WCPay customer ID if the order customer doesn't have one.
		delete_user_option( 1, self::CUSTOMER_LIVE_META_KEY );
		$mock_customer_data = [
			'name'        => 'Jeroen Sormani',
			'description' => 'Name: Jeroen Sormani, Username: admin',
			'email'       => 'admin@example.org',
			'phone'       => '555-32123',
			'address'     => [
				'line1'       => 'WooAddress',
				'line2'       => '',
				'postal_code' => '12345',
				'city'        => 'WooCity',
				'state'       => 'NY',
				'country'     => 'US',
			],
		];

		$this->mock_account
			->method( 'get_fraud_services_config' )
			->willReturn( [ 'sift' => [ 'session_id' => 'woo_session_id' ] ] );

		$this->mock_api_client->expects( $this->once() )
			->method( 'create_customer' )
			->with(
				array_merge(
					$mock_customer_data,
					[ 'session_id' => 'woo_session_id' ]
				)
			)
			->willReturn( 'wcpay_cus_test12345' );

		$this->assertEquals( $this->customer_service->get_customer_id_for_order( $order ), 'wcpay_cus_test12345' );
	}
}

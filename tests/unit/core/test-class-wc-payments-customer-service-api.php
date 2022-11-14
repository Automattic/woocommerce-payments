<?php
/**
 * Class WC_Payments_Customer_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;
use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Customer_Service unit tests.
 */
class WC_Payments_Customer_Service_API_Test extends WCPAY_UnitTestCase {

	const CUSTOMER_LIVE_META_KEY = '_wcpay_customer_id_live';
	const CUSTOMER_TEST_META_KEY = '_wcpay_customer_id_test';

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Customer_Service_API
	 */
	private $customer_service_api;

	/**
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
	 * Mock Database_Cache.
	 *
	 * @var Database_Cache|MockObject
	 */
	private $mock_db_cache;


	/**
	 * Pre-test setup
	 */
	public function set_up() {
		$this->mock_api_client      = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_account         = $this->createMock( WC_Payments_Account::class );
		$this->mock_db_cache        = $this->createMock( Database_Cache::class );
		$this->customer_service     = new WC_Payments_Customer_Service( $this->mock_api_client, $this->mock_account, $this->mock_db_cache );
		$this->customer_service_api = new WC_Payments_Customer_Service_API( $this->customer_service );
		parent::set_up();
	}

	/**
	 * Post-test teardown
	 */
	public function tear_down() {
		parent::tear_down();
	}

	/**
	 * Test get customer ID by user ID for live mode.
	 */
	public function test_get_customer_id_by_user_id() {
		update_user_option( 1, self::CUSTOMER_LIVE_META_KEY, 'cus_test12345' );

		$customer_id = $this->customer_service_api->get_customer_id_by_user_id( 1 );

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

		/**
		 * Test create customer for user.
		 *
		 * @throws API_Exception
		 */
	public function test_create_customer_for_user() {
		$user             = new WP_User( 1 );
		$user->user_login = 'testUser';

		$mock_customer_data = [
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
		];

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

		$customer_id = $this->customer_service_api->create_customer_for_user( $user, $mock_customer_data );

		$this->assertEquals( 'cus_test12345', $customer_id );
		$this->assertEquals( 'cus_test12345', get_user_option( self::CUSTOMER_LIVE_META_KEY, $user->ID ) );
		$this->assertEquals( false, get_user_option( self::CUSTOMER_TEST_META_KEY, $user->ID ) );
	}

}

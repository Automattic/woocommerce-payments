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
	 * Mock WC_Payments_Http.
	 *
	 * @var WC_Payments_Http|MockObject
	 */
	private $mock_http_client;


	/**
	 * Pre-test setup
	 */
	public function set_up() {
		$this->mock_http_client = $this
			->getMockBuilder( 'WC_Payments_Http' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_blog_id', 'is_connected', 'remote_request' ] )
			->getMock();
		add_filter(
			'wc_payments_http',
			function() {
				return $this->mock_http_client;
			}
		);
		$this->customer_service     = new WC_Payments_Customer_Service( WC_Payments::create_api_client(), WC_Payments::get_account_service(), WC_Payments::get_database_cache() );
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

	public function test_get_customer_id_by_user_id_with_null_user_id() {
		$customer_id = $this->customer_service_api->get_customer_id_by_user_id( null );
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
		$this->mock_http_client->expects( $this->once() )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     =>
					json_encode(
						[
							'id'   => 'cus_test12345',
							'type' => 'customer',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$customer_id = $this->customer_service->create_customer_for_user(
			$user,
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
			]
		);

		$this->assertEquals( 'cus_test12345', $customer_id );
		$this->assertEquals( 'cus_test12345', get_user_option( self::CUSTOMER_LIVE_META_KEY, $user->ID ) );
		$this->assertEquals( false, get_user_option( self::CUSTOMER_TEST_META_KEY, $user->ID ) );
	}

}

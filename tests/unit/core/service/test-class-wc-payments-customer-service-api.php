<?php
/**
 * Class WC_Payments_Customer_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;
use WCPay\Exceptions\API_Exception;
use WCPay\Core\WC_Payments_Customer_Service_API;

/**
 * WC_Payments_Customer_Service unit tests.
 */
class WC_Payments_Customer_Service_API_Test extends WCPAY_UnitTestCase {

	const CUSTOMER_LIVE_META_KEY = '_wcpay_customer_id_live';

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
	 * Filter callback to return the mock http client
	 *
	 * @return void
	 */
	public function replace_http_client() {
		return $this->mock_http_client;
	}

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();
		// mock WC_Payments_Http and use it to set up system under test.
		$this->mock_http_client = $this
			->getMockBuilder( 'WC_Payments_Http' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_blog_id', 'is_connected', 'remote_request' ] )
			->getMock();
		add_filter(
			'wc_payments_http',
			[ $this, 'replace_http_client' ]
		);
		$this->customer_service     = new WC_Payments_Customer_Service( WC_Payments::create_api_client(), WC_Payments::get_account_service(), WC_Payments::get_database_cache() );
		$this->customer_service_api = new WC_Payments_Customer_Service_API( $this->customer_service );

	}

	/**
	 * Post-test teardown
	 */
	public function tear_down() {
		parent::tear_down();
		remove_filter(
			'wc_payments_http',
			[ $this, 'replace_http_client' ]
		);
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
	 * Test get customer ID by user ID  with null user ID.
	 */
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
					wp_json_encode(
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

		$customer_id = $this->customer_service_api->create_customer_for_user(
			$user,
			$this->get_mock_customer_data()
		);

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

	/**
	 * Test update customer for user.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_for_user() {
		$user = new WP_User( 0 );

		$customer_id = $this->customer_service_api->update_customer_for_user(
			'cus_test12345',
			$user,
			$this->get_mock_customer_data()
		);

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

	/**
	 * Test error in update customer for user.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_for_user_error() {
		$user = new WP_User( 0 );

		// mock that updating the customer failed, and customer gets created.
		$this->mock_http_client
			->expects( $this->exactly( 2 ) )
			->method( 'remote_request' )
			->willReturnOnConsecutiveCalls(
				[
					'body'     => wp_json_encode(
						[
							'error' => [
								'code'    => 'resource_missing',
								'message' => 'No such customer',
							],
						]
					),
					'response' => [
						'code'    => 404,
						'message' => 'OK',
					],
				],
				[
					'body'     =>
					wp_json_encode(
						[
							'id'   => 'cus_test123456',
							'type' => 'customer',
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$customer_id = $this->customer_service_api->update_customer_for_user(
			'cus_test12345',
			$user,
			$this->get_mock_customer_data()
		);

		$this->assertEquals( 'cus_test123456', $customer_id );
	}

	/**
	 * Test setting default payment methods for customer
	 *
	 * @throws API_Exception
	 */
	public function test_set_default_payment_method_for_customer() {
		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->assertSame( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/customers/cus_1234', $data['url'] );
						$this->assertSame( 'POST', $data['method'] );
						return true;
					}
				),
				wp_json_encode(
					[
						'test_mode'        => false,
						'invoice_settings' => [
							'default_payment_method' => 'pm_mock',
						],
					]
				),
				true,
				false
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'data' => [] ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);
		$this->customer_service_api->set_default_payment_method_for_customer( 'cus_1234', 'pm_mock' );
	}

	/**
	 * Test get Payment methods for a customer
	 *
	 * @return void
	 */
	public function test_get_payment_methods_for_customer() {
		$mock_payment_methods = [
			[ 'id' => 'pm_mock1' ],
			[ 'id' => 'pm_mock2' ],
		];

		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->assertSame( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/payment_methods?test_mode=0&customer=cus_12345&type=card&limit=100', $data['url'] );
						$this->assertSame( 'GET', $data['method'] );
						return true;
					}
				)
			)->willReturn(
				[
					'body'     => wp_json_encode( [ 'data' => $mock_payment_methods ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$response = $this->customer_service_api->get_payment_methods_for_customer( 'cus_12345' );

		$this->assertEquals( $mock_payment_methods, $response );
	}

	/**
	 * Test get Payment methods for a customer when error occurs
	 * An empty array is sent back as response
	 *
	 * @return void
	 */
	public function test_get_payment_methods_for_customer_error() {

		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->assertSame( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/payment_methods?test_mode=0&customer=cus_12345&type=card&limit=100', $data['url'] );
						$this->assertSame( 'GET', $data['method'] );
						return true;
					}
				)
			)->willReturn(
				[
					'body'     => wp_json_encode(
						[
							'error' => [
								'code'    => 'resource_missing',
								'message' => 'No such customer',
							],
						]
					),
					'response' => [
						'code'    => 404,
						'message' => 'OK',
					],
				]
			);

		$response = $this->customer_service_api->get_payment_methods_for_customer( 'cus_12345' );

		// When the payment methods are unable to update, a empty array is sent back.
		$this->assertEquals( [], $response );
	}


	/**
	 * test updating payment method with billing details
	 *
	 * @return void
	 */
	public function test_update_payment_method_with_billing_details_from_order() {
		$order = WC_Helper_Order::create_order();
		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->assertSame( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/payment_methods/pm_mock', $data['url'] );
						$this->assertSame( 'POST', $data['method'] );
						return true;
					}
				),
				wp_json_encode(
					[
						'test_mode'       => false,
						'billing_details' => [
							'address' => [
								'city'        => $order->get_billing_city(),
								'country'     => $order->get_billing_country(),
								'line1'       => $order->get_billing_address_1(),
								'postal_code' => $order->get_billing_postcode(),
								'state'       => $order->get_billing_state(),
							],
							'email'   => $order->get_billing_email(),
							'name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
							'phone'   => $order->get_billing_phone(),
						],
					]
				),
				true,
				false
			)->willReturn(
				[
					'body'     => wp_json_encode( [] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$this->customer_service_api->update_payment_method_with_billing_details_from_order( 'pm_mock', $order );
	}

	/**
	 * test updating payment method with billing details
	 *
	 * @return void
	 */
	public function test_update_payment_method_with_billing_details_from_order_on_error() {
		$order = WC_Helper_Order::create_order();
		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->assertSame( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/payment_methods/pm_mock', $data['url'] );
						$this->assertSame( 'POST', $data['method'] );
						return true;
					}
				)
			)->willReturn(
				[
					'body'     => wp_json_encode(
						[
							'error' => [
								'code'    => 'resource_missing',
								'message' => 'No such payment method',
							],
						]
					),
					'response' => [
						'code'    => 404,
						'message' => 'OK',
					],
				]
			);

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'No such payment method' );
		$this->customer_service_api->update_payment_method_with_billing_details_from_order( 'pm_mock', $order );
	}


	/**
	 * Test clearing cached payment methods.
	 *
	 * @return void
	 */
	public function test_clear_cached_payment_methods_for_user() {
		// get payment methods for a customer so that it gets cached.
		$mock_payment_methods = [
			[ 'id' => 'pm_mock1' ],
			[ 'id' => 'pm_mock2' ],
		];
		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->assertSame( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/payment_methods?test_mode=0&customer=cus_123&type=card&limit=100', $data['url'] );
						$this->assertSame( 'GET', $data['method'] );
						return true;
					}
				)
			)->willReturn(
				[
					'body'     => wp_json_encode( [ 'data' => $mock_payment_methods ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);
		$response = $this->customer_service_api->get_payment_methods_for_customer( 'cus_123' );
		$this->assertEquals( $mock_payment_methods, $response );

		// check if can retrieve from cache.
		$db_cache       = WC_Payments::get_database_cache();
		$cache_response = $db_cache->get( Database_Cache::PAYMENT_METHODS_KEY_PREFIX . 'cus_123_card' );
		$this->assertEquals( $mock_payment_methods, $cache_response );

		// set up the user for customer.
		update_user_option( 1, self::CUSTOMER_LIVE_META_KEY, 'cus_test123' );

		// run the method.
		$this->customer_service_api->clear_cached_payment_methods_for_user( 'cus_123' );

		// check that cache is empty.
		$cache_response = $db_cache->get( Database_Cache::PAYMENT_METHODS_KEY_PREFIX . 'cus_12345_card' );
		$this->assertEquals( null, $cache_response );
	}

	/**
	 * Get mock customer data.
	 *
	 * @return array
	 */
	private function get_mock_customer_data() {
		return [
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
	}

}

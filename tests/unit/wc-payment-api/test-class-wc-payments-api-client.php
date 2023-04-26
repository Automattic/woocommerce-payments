<?php
/**
 * Class WC_Payments_API_Client_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Payment_Intent_Status;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Connection_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Fraud_Prevention\Buyer_Fingerprinting_Service;

/**
 * WC_Payments_API_Client unit tests.
 */
class WC_Payments_API_Client_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Mock HTTP client.
	 *
	 * @var WC_Payments_Http|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_http_client;

	/**
	 * Mock DB wrapper.
	 *
	 * @var WC_Payments_DB|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_db_wrapper;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_http_client = $this
			->getMockBuilder( 'WC_Payments_Http' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_blog_id', 'is_connected', 'remote_request' ] )
			->getMock();

		$this->mock_db_wrapper = $this
			->getMockBuilder( 'WC_Payments_DB' )
			->disableOriginalConstructor()
			->getMock();

		$this->payments_api_client = new WC_Payments_API_Client(
			'Unit Test Agent/0.1.0',
			$this->mock_http_client,
			$this->mock_db_wrapper
		);
	}

	/**
	 * Test a successful fetch of a single transaction.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_transaction_success() {
		$transaction_id = 'txn_231mdaism';

		$this->set_http_mock_response(
			200,
			[
				'id'        => $transaction_id,
				'type'      => 'charge',
				'charge_id' => 'ch_ji3djhabvh23',
			]
		);

		$transaction = $this->payments_api_client->get_transaction( $transaction_id );
		$this->assertEquals( $transaction_id, $transaction['id'] );
	}

	/**
	 * Test fetching of non existing transaction.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_transaction_not_found() {
		$transaction_id = 'txn_231mdaism';
		$error_code     = 'resource_missing';
		$error_message  = 'No such balance transaction';

		$this->set_http_mock_response(
			404,
			[
				'error' => [
					'code'    => $error_code,
					'message' => $error_message,
				],
			]
		);
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( "Error: $error_message" );

		$this->payments_api_client->get_transaction( $transaction_id );
	}


	/**
	 * Test creating a customer.
	 *
	 * @throws API_Exception
	 */
	public function test_create_customer_success() {
		$customer_data = [
			'name'        => 'Test Customer',
			'email'       => 'test.customer@example.com',
			'description' => 'Test Customer Description',
		];

		$this->set_http_mock_response(
			200,
			[
				'id'   => 'cus_test12345',
				'type' => 'customer',
			]
		);

		$customer_id = $this->payments_api_client->create_customer( $customer_data );

		$this->assertEquals( 'cus_test12345', $customer_id );
	}

	/**
	 * Test updating a customer.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_success() {
		$name        = 'Test Customer';
		$email       = 'test.customer@example.com';
		$description = 'Test Customer Description';

		// Mock the HTTP client manually so that we can assert against the request used.
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->validate_default_remote_request_params( $data, 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/customers/cus_test12345', 'POST' );
						$this->assertSame( 'POST', $data['method'] );
						return true;
					}
				),
				wp_json_encode(
					[
						'test_mode'   => false,
						'name'        => 'Test Customer',
						'email'       => 'test.customer@example.com',
						'description' => 'Test Customer Description',
					]
				),
				true,
				false
			)
			->will(
				$this->returnValue(
					[
						'body'     => wp_json_encode(
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
				)
			);

		$this->payments_api_client->update_customer(
			'cus_test12345',
			[
				'name'        => $name,
				'email'       => $email,
				'description' => $description,
			]
		);
	}

	/**
	 * Test updating a customer with null customer ID.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_with_null_customer_id() {
		// Ensure we don't make a call to the server.
		$this->mock_http_client
			->expects( $this->never() )
			->method( 'remote_request' );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Customer ID is required' );

		$this->payments_api_client->update_customer( null );
	}

	/**
	 * Test updating a customer with an empty string customer ID.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_with_empty_string_customer_id() {
		// Ensure we don't make a call to the server.
		$this->mock_http_client
			->expects( $this->never() )
			->method( 'remote_request' );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Customer ID is required' );

		$this->payments_api_client->update_customer( '' );
	}

	/**
	 * Test updating a customer with an empty string customer ID.
	 *
	 * @throws API_Exception
	 */
	public function test_update_customer_with_whitespace_customer_id() {
		// Ensure we don't make a call to the server.
		$this->mock_http_client
			->expects( $this->never() )
			->method( 'remote_request' );

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Customer ID is required' );

		$this->payments_api_client->update_customer( ' ' );
	}

	/**
	 * Test getting initial onboarding data.
	 *
	 * @throws API_Exception
	 */
	public function test_get_onboarding_data() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->validate_default_remote_request_params( $data, 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/onboarding/init', 'POST' );
						$this->assertSame( 'POST', $data['method'] );
						return true;
					}
				),
				wp_json_encode(
					[
						'test_mode'                   => false,
						'return_url'                  => 'http://localhost',
						'site_data'                   => [
							'site_username' => 'admin',
							'site_locale'   => 'en_US',
						],
						'create_live_account'         => true,
						'actioned_notes'              => [
							'd' => 4,
							'e' => 5,
							'f' => 6,
						],
						'progressive'                 => false,
						'collect_payout_requirements' => false,
						'account_data'                => [],
					]
				),
				true,
				true // get_onboarding_data should use user token auth.
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'url' => false ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		// Call the method under test.
		$result = $this->payments_api_client->get_onboarding_data(
			'http://localhost',
			[
				'site_username' => 'admin',
				'site_locale'   => 'en_US',
			],
			[
				'd' => 4,
				'e' => 5,
				'f' => 6,
			]
		);

		// Assert the response is correct.
		$this->assertEquals( [ 'url' => false ], $result );
	}

	/**
	 * Test getting onboarding business types.
	 *
	 * @throws API_Exception
	 */
	public function test_get_onboarding_business_types() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/onboarding/business_types?test_mode=0',
					'method'          => 'GET',
					'headers'         => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'Unit Test Agent/0.1.0',
					],
					'timeout'         => 70,
					'connect_timeout' => 70,
				],
				null,
				true,
				true // get_onboarding_business_types should use user token auth.
			);

		$this->payments_api_client->get_onboarding_business_types();
	}

	/**
	 * Test getting onboarding required verification information.
	 *
	 * @throws API_Exception
	 */
	public function test_get_onboarding_required_verification_information() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/onboarding/required_verification_information?test_mode=0&country=country&type=type',
					'method'          => 'GET',
					'headers'         => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'Unit Test Agent/0.1.0',
					],
					'timeout'         => 70,
					'connect_timeout' => 70,
				],
				null,
				true,
				true // get_onboarding_required_verification_information should use user token auth.
			);

		$this->payments_api_client->get_onboarding_required_verification_information( 'country', 'type' );
	}

	public function test_get_link() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $data ): bool {
						$this->validate_default_remote_request_params( $data, 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/links', 'POST' );
						$this->assertSame( 'POST', $data['method'] );
						return true;
					}
				),
				wp_json_encode(
					[
						'test_mode' => false,
						'type'      => 'login_link',
						'param'     => 'some_other_param',
					]
				),
				true,
				true // get_link should use user token auth.
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'url' => 'https://login.url' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->get_link(
			[
				'type'  => 'login_link',
				'param' => 'some_other_param',
			]
		);

		$this->assertEquals( [ 'url' => 'https://login.url' ], $result );
	}

	public function test_get_currency_rates() {
		$currency_from = 'USD';

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/currency/rates?test_mode=0&currency_from=USD',
					'method'          => 'GET',
					'headers'         => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'Unit Test Agent/0.1.0',
					],
					'timeout'         => 70,
					'connect_timeout' => 70,
				],
				null,
				true,
				false
			)->willReturn(
				[
					'body'     => wp_json_encode(
						[
							'GBP' => 0.75,
							'EUR' => 0.82,
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		$result = $this->payments_api_client->get_currency_rates( $currency_from );

		$this->assertEquals(
			[
				'GBP' => 0.75,
				'EUR' => 0.82,
			],
			$result
		);
	}


	/**
	 * @dataProvider data_request_with_level3_data
	 */
	public function test_request_with_level3_data( $input_args, $expected_level3_args ) {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->anything(),
				$this->callback(
					function ( $request_args_json ) use ( $expected_level3_args ) {
						$request_args = json_decode( $request_args_json, true );

						$this->assertSame( $expected_level3_args, $request_args['level3'] );

						return true;
					}
				)
			)
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'result' => 'success' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request_with_level3_data',
			[ $input_args, 'intentions', 'POST' ]
		);
	}

	public function test_create_terminal_location_validation_array() {
		$this->expectException( API_Exception::class );
		$this->expectExceptionMessageMatches( '~address.*required~i' );
		$this->payments_api_client->create_terminal_location( 'Example', '' );
	}

	public function test_create_terminal_location_validation_values() {
		$this->expectException( API_Exception::class );
		$this->expectExceptionMessageMatches( '~address.*required~i' );
		$this->payments_api_client->create_terminal_location(
			'Example',
			[
				'country' => 'US',
			]
		);
	}

	public function test_create_terminal_location_success() {
		$location = [
			'display_name' => 'Example',
			'address'      => [
				'country' => 'US',
				'line1'   => 'Some Str. 2',
			],
			'metadata'     => [],
		];

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $request ) {
						return 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/terminal/locations' === $request['url'] && 'POST' === $request['method'];
					}
				),
				$this->callback(
					function ( $body ) use ( $location ) {
						$flags = [ 'test_mode' => false ];

						return wp_json_encode( array_merge( $flags, $location ) ) === $body;
					}
				)
			)
			->will(
				$this->returnValue(
					[
						'body'     => wp_json_encode( $location ),
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
					]
				)
			);

		$result = $this->payments_api_client->create_terminal_location( $location['display_name'], $location['address'] );
		// The returned value is an object, even though Stripe specifies an array.
		$result['metadata'] = (array) $result['metadata'];
		$this->assertSame( $location, $result );
	}

	public function test_delete_terminal_location_success() {
		$delete_location_response = [
			'id'      => 'tml_XXXXXXX',
			'object'  => 'terminal.deleted',
			'deleted' => true,
		];

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/terminal/locations/tml_XXXXXXX?test_mode=0',
					'method'          => 'DELETE',
					'headers'         => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'Unit Test Agent/0.1.0',
					],
					'timeout'         => 70,
					'connect_timeout' => 70,
				],
				null,
				true,
				false
			)
			->will(
				$this->returnValue(
					[
						'body'     => wp_json_encode( $delete_location_response ),
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
					]
				)
			);

		$this->assertSame(
			$this->payments_api_client->delete_terminal_location( 'tml_XXXXXXX' ),
			$delete_location_response
		);
	}

	/**
	 * Data provider for test_request_with_level3_data
	 */
	public function data_request_with_level3_data() {
		return [
			'australian_merchant'               => [
				[
					'level3' => [],
				],
				[],
			],
			'american_merchant_no_line_items'   => [
				[
					'level3' => [
						'merchant_reference' => 'abc123',
					],
				],
				[
					'merchant_reference' => 'abc123',
					'line_items'         => [
						[
							'discount_amount'     => 0,
							'product_code'        => 'empty-order',
							'product_description' => 'The order is empty',
							'quantity'            => 1,
							'tax_amount'          => 0,
							'unit_cost'           => 0,
						],
					],
				],
			],
			'american_merchant_with_line_items' => [
				[
					'level3' => [
						'merchant_reference' => 'abc123',
						'line_items'         => [
							[
								'discount_amount'     => 0,
								'product_code'        => 'free-hug',
								'product_description' => 'Free hug',
								'quantity'            => 1,
								'tax_amount'          => 0,
								'unit_cost'           => 0,
							],
						],
					],
				],
				[
					'merchant_reference' => 'abc123',
					'line_items'         => [
						[
							'discount_amount'     => 0,
							'product_code'        => 'free-hug',
							'product_description' => 'Free hug',
							'quantity'            => 1,
							'tax_amount'          => 0,
							'unit_cost'           => 0,
						],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider data_get_intent_description
	 */
	public function test_get_intent_description( $order_id, $blog_id, $expected_intent_description ) {
		$this->mock_http_client
			->method( 'is_connected' )
			->willReturn( true );

		$this->mock_http_client
			->method( 'get_blog_id' )
			->willReturn( $blog_id );

		$actual_intent_description = PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'get_intent_description',
			[ $order_id ]
		);

		$this->assertSame( $expected_intent_description, $actual_intent_description );
	}

	/**
	 * Data provider for test_get_intent_description
	 */
	public function data_get_intent_description() {
		return [
			'no_order_id'               => [
				0,
				999,
				'Online Payment for example.org blog_id 999',
			],
			'no_blog_id'                => [
				100,
				null,
				'Online Payment for Order #100 for example.org',
			],
			'with_order_id_and_blog_id' => [
				100,
				999,
				'Online Payment for Order #100 for example.org blog_id 999',
			],
		];
	}

	/**
	 * Test a successful fetch of a single invoice.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_invoice_success() {
		$invoice_id = 'in_test_invoice';

		$this->set_http_mock_response(
			200,
			[
				'id'     => $invoice_id,
				'object' => 'invoice',
			]
		);

		$invoice = $this->payments_api_client->get_invoice( $invoice_id );
		$this->assertEquals( $invoice_id, $invoice['id'] );
	}

	/**
	 * Test a successful call to cancel subscription.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_cancel_subscription() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				[
					'url'             => 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/subscriptions/sub_test?test_mode=0',
					'method'          => 'DELETE',
					'headers'         => [
						'Content-Type' => 'application/json; charset=utf-8',
						'User-Agent'   => 'Unit Test Agent/0.1.0',
					],
					'timeout'         => 70,
					'connect_timeout' => 70,
				],
				null,
				true,
				false
			)
			->will(
				$this->returnValue(
					[
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'body'     => wp_json_encode(
							[
								'id'     => 'sub_test',
								'object' => 'subscription',
							]
						),
					]
				)
			);

		$result = $this->payments_api_client->cancel_subscription( 'sub_test' );
		$this->assertSame( 'sub_test', $result['id'] );
		$this->assertSame( 'subscription', $result['object'] );
	}

	/**
	 * Test redacting request params.
	 *
	 * @dataProvider redacting_params_data
	 * @throws Exception - In the event of test failure.
	 */
	public function test_redacting_params( $request_arguments, $logger_num_calls, ...$logger_expected_arguments ) {
		$mock_logger = $this->getMockBuilder( 'WC_Logger' )
			->setMethods( [ 'log' ] )
			->getMock();

		$logger_ref = new ReflectionProperty( 'WCPay\Logger', 'logger' );
		$logger_ref->setAccessible( true );
		$logger_ref->setValue( null, $mock_logger );

		WC_Payments::mode()->dev();

		$mock_logger
			->expects( $this->exactly( $logger_num_calls ) )
			->method( 'log' )
			->withConsecutive( ...$logger_expected_arguments );

		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					[
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
						'body'     => wp_json_encode(
							[
								'status' => true,
							]
						),
					]
				)
			);

		$reflection     = new ReflectionClass( $this->payments_api_client );
		$request_method = $reflection->getMethod( 'request' );
		$request_method->setAccessible( true );
		$request_method->invokeArgs( $this->payments_api_client, $request_arguments );
		$request_method->setAccessible( false );

		// clean up.
		$logger_ref->setAccessible( true );
		$logger_ref->setValue( null, null );
		WC_Payments::mode()->live();
	}

	/**
	 * Data provider for test_redacting_params
	 */
	public function redacting_params_data() {
		$string_should_not_include_secret = function ( $string ) {
			return false === strpos( $string, 'some-secret' );
		};

		return [
			'delete' => [
				[ [ 'client_secret' => 'some-secret' ], 'abc', 'DELETE' ],
				4,
				[
					$this->anything(),
					$this->callback( $string_should_not_include_secret ),
				],
				[
					$this->anything(),
					$this->anything(),
				],
			],
			'get'    => [
				[ [ 'client_secret' => 'some-secret' ], 'abc', 'GET' ],
				4,
				[
					$this->anything(),
					$this->callback( $string_should_not_include_secret ),
				],
				[
					$this->anything(),
					$this->anything(),
				],
			],
			'post'   => [
				[ [ 'client_secret' => 'some-secret' ], 'abc', 'POST' ],
				5,
				[
					$this->anything(),
					$this->callback( $string_should_not_include_secret ),
				],
				[
					$this->anything(),
					$this->callback( $string_should_not_include_secret ),
				],
				[
					$this->anything(),
					$this->anything(),
				],
			],
		];
	}

	/**
	 * Test a sucessful fetch of disputes summary
	 *
	 * @throws Exception
	 */
	public function test_get_disputes_summary_success() {
		$this->set_http_mock_response(
			200,
			[
				'data' => [
					'count' => 12,
				],
			]
		);

		$disputes_summary = $this->payments_api_client->get_disputes_summary();
		$this->assertSame( 12, $disputes_summary['data']['count'] );
	}

	public function get_onboarding_po_eligible() {
		$this->set_http_mock_response(
			200,
			[
				'result' => 'eligible',
				'data'   => [],
			]
		);

		$po_eligible = $this->payments_api_client->get_onboarding_po_eligible(
			[
				'business' => [
					'country'           => 'US',
					'type'              => 'company',
					'mcc'               => 'computers_peripherals_and_software',
					'annual_revenue'    => 'less_than_250k',
					'go_live_timeframe' => 'within_1month',
				],
			]
		);
		$this->assertSame( 'eligible', $po_eligible['result'] );
	}


	public function test_get_platform_checkout_eligibility_success() {
		$this->set_http_mock_response(
			200,
			[
				'platform_checkout_eligible' => true,
			]
		);

		$response = $this->payments_api_client->get_platform_checkout_eligibility();
		$this->assertTrue( $response['platform_checkout_eligible'] );
	}

	/**
	 * Test a sucessful fetch of documents summary
	 *
	 * @throws Exception
	 */
	public function test_get_documents_summary_success() {
		$this->set_http_mock_response(
			200,
			[
				'data' => [
					'count' => 12,
				],
			]
		);

		$documents_summary = $this->payments_api_client->get_documents_summary();
		$this->assertSame( 12, $documents_summary['data']['count'] );
	}

	/**
	 * Test a successful fetch of a document
	 *
	 * @throws Exception
	 */
	public function test_get_document_success() {
		$this->mock_http_client
			->expects( $this->once() )
			->method( 'remote_request' )
			->with(
				$this->callback(
					function ( $request ) {
						$this->assertSame( 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/documents/someDocument?test_mode=0', $request['url'] );
						$this->assertSame( 'GET', $request['method'] );
						return true;
					}
				)
			)
			->will(
				$this->returnValue(
					[
						'headers'  => [ 'content-type' => 'text/html' ],
						'body'     => '<html><body>Document</body></html>',
						'response' => [
							'code'    => 200,
							'message' => 'OK',
						],
					]
				)
			);

		$documents_summary = $this->payments_api_client->get_document( 'someDocument' );
		$this->assertSame( '<html><body>Document</body></html>', $documents_summary['body'] );
		$this->assertSame( 'text/html', $documents_summary['headers']['content-type'] );
	}

	/**
	 * Test fetch of a document that errors
	 *
	 * @throws Exception
	 */
	public function test_get_document_error() {
		$this->set_http_mock_response(
			404,
			[
				'code'    => 'wcpay_document_not_found',
				'message' => 'Document not found',
				'data'    => [ 'status' => 404 ],
			]
		);

		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Error: Document not found' );

		$this->payments_api_client->get_document( 'someDocument' );
	}

	/**
	 * Test a successful fetch of a single authorization.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_authorization_success() {
		$payment_intent_id = 'pi_123smtm';

		$this->set_http_mock_response(
			200,
			[
				'payment_intent_id' => $payment_intent_id,
			]
		);

		$authorization = $this->payments_api_client->get_authorization( $payment_intent_id );
		$this->assertSame( $payment_intent_id, $authorization['payment_intent_id'] );
	}

	/**
	 * Test fetching of non existing authorization.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_get_authorization_not_found() {
		$payment_intent_id = 'pi_123smtm';
		$error_message     = 'The authorization you asked for does not exist';

		$this->set_http_mock_response(
			404,
			[
				'error' => [
					'code'    => 'authorization_missing',
					'message' => $error_message,
				],
			]
		);
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( "Error: $error_message" );

		$this->payments_api_client->get_authorization( $payment_intent_id );
	}
	/**
	 * Test a successful fetch of authorizations summary.
	 *
	 * @throws Exception In case of test failure.
	 */
	public function test_authorizations_summary_success() {
		$this->set_http_mock_response(
			200,
			[
				'count' => 123,
				'total' => 1200,
			]
		);

		$summary = $this->payments_api_client->get_authorizations_summary();

		$this->assertSame( 123, $summary['count'] );
		$this->assertSame( 1200, $summary['total'] );
	}

	/**
	 * Test that API client will retry request in case of network error
	 *
	 * POST calls have `Idempotency-Key` set in the `request`, thus are
	 * possible to retry.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_retries_post_on_network_failure() {
		$this->mock_http_client
			->expects( $this->exactly( 4 ) )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'result' => 'error' ] ),
					'response' => [
						'code'    => 0,
						'message' => 'Unknown network error',
					],
				]
			);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'POST' ]
		);
	}

	/**
	 * Test that API client will retry request in case of network error
	 * indiciated by Connection_Exception.
	 *
	 * POST calls have `Idempotency-Key` set in the `request`, thus are
	 * possible to retry.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_retries_post_on_network_failure_exception() {
		$this->mock_http_client
			->expects( $this->exactly( 4 ) )
			->method( 'remote_request' )
			->willThrowException(
				new Connection_Exception( 'HTTP request failed', 'wcpay_http_request_failed', 500 )
			);

		$this->expectException( Connection_Exception::class );

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'POST' ]
		);
	}

	/**
	 * Test that API client will retry request in case of network error
	 * and stop on success.
	 *
	 * POST calls have `Idempotency-Key` set in the `request`, thus are
	 * possible to retry.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_retries_post_on_network_failure_exception_and_stops_on_success() {
		$this->mock_http_client
			->expects( $this->exactly( 3 ) )
			->method( 'remote_request' )
			->willReturnOnConsecutiveCalls(
				$this->throwException(
					new Connection_Exception( 'HTTP request failed', 'wcpay_http_request_failed', 500 )
				),
				$this->throwException(
					new Connection_Exception( 'HTTP request failed', 'wcpay_http_request_failed', 500 )
				),
				[
					'body'     => wp_json_encode( [ 'result' => 'success' ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
				]
			);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'POST' ]
		);
	}

	/**
	 * Test that API client will not retry if connection exception indicates there
	 * was a response.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_doesnt_retry_on_other_exceptions() {
		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->willThrowException(
				new Exception( 'Random exception' )
			);

		$this->expectException( Exception::class );

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'POST' ]
		);
	}

	/**
	 * Test that API client will retry request in case of network error with
	 * Idempotency-Key header
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_retries_get_with_idempotency_header_on_network_failure() {
		$this->mock_http_client
			->expects( $this->exactly( 4 ) )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'result' => 'error' ] ),
					'response' => [
						'code'    => 0,
						'message' => 'Unknown network error',
					],
				]
			);

		$callable = function ( $headers ) {
			$headers['Idempotency-Key'] = 'ik_42';
			return $headers;
		};

		add_filter(
			'wcpay_api_request_headers',
			$callable,
			10,
			2
		);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'GET' ]
		);

		remove_filter(
			'wcpay_api_request_headers',
			$callable,
			10
		);
	}

	/**
	 * Test that API client won't retry GET request without Idemptency-Key header.
	 *
	 * @throws Exception in case of the test failure.
	 */
	public function test_request_doesnt_retry_get_without_idempotency_header_on_network_failure() {
		$this->mock_http_client
			->expects( $this->exactly( 1 ) )
			->method( 'remote_request' )
			->willReturn(
				[
					'body'     => wp_json_encode( [ 'result' => 'error' ] ),
					'response' => [
						'code'    => 0,
						'message' => 'Unknown network error',
					],
				]
			);

		PHPUnit_Utils::call_method(
			$this->payments_api_client,
			'request',
			[ [], 'intentions', 'GET' ]
		);
	}
	/**
	 * Set up http mock response.
	 *
	 * @param int $status_code status code for the mocked response.
	 * @param array $body body for the mocked response.
	 * @param array $headers headers for the mocked response.
	 * @param array $cookies cookies to be used in the mocked response.
	 */
	private function set_http_mock_response( $status_code, $body = [], $headers = [], $cookies = [] ) {
		$this->mock_http_client
			->expects( $this->any() )
			->method( 'remote_request' )
			->will(
				$this->returnValue(
					[
						'headers'  => $headers,
						'body'     => wp_json_encode( $body ),
						'response' => [
							'code'    => $status_code,
							'message' => 'OK',
						],
						'cookies'  => $cookies,
						'filename' => null,
					]
				)
			);
	}

	/**
	 * Mock/validate default remote HTTP Params
	 *
	 * @param array $data
	 * @param string $url
	 * @param string $method
	 *
	 */
	private function validate_default_remote_request_params( $data, $url, $method ) {
		$this->assertIsArray( $data );
		$this->assertCount( 5, $data );
		$this->assertArrayHasKey( 'url', $data );
		$this->assertSame( $url, $data['url'] );
		$this->assertNotFalse( filter_var( $data['url'], FILTER_VALIDATE_URL ) );
		$this->assertArrayHasKey( 'method', $data );
		$this->assertSame( $method, $data['method'] );
		$this->assertArrayHasKey( 'headers', $data );
		$this->assertArrayHasKey( 'Idempotency-Key', $data['headers'] );
		$this->assertNotEmpty( $data['headers']['Idempotency-Key'] );
		$this->assertArrayHasKey( 'User-Agent', $data['headers'] );
		$this->assertNotEmpty( $data['headers']['User-Agent'] );
		$this->assertArrayHasKey( 'Content-Type', $data['headers'] );
		$this->assertSame( 'application/json; charset=utf-8', $data['headers']['Content-Type'] );
		$this->assertArrayHasKey( 'url', $data );
		$this->assertArrayHasKey( 'timeout', $data );
		$this->assertSame( 70, $data['timeout'] );
		$this->assertArrayHasKey( 'connect_timeout', $data );
		$this->assertSame( 70, $data['connect_timeout'] );

	}
}

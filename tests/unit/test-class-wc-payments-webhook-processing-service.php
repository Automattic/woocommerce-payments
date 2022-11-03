<?php
/**
 * Class WC_Payments_Webhook_Processing_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Invalid_Payment_Method_Exception;
use WCPay\Exceptions\Invalid_Webhook_Data_Exception;
use WCPay\Exceptions\Rest_Request_Exception;
use WCPay\Database_Cache;

// Need to use WC_Mock_Data_Store.
require_once dirname( __FILE__ ) . '/helpers/class-wc-mock-wc-data-store.php';

/**
 * WC_Payments_Webhook_Processing_Service unit tests.
 */
class WC_Payments_Webhook_Processing_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Webhook_Processing_Service
	 */
	private $webhook_processing_service;

	/**
	 * @var WC_Payments_DB|MockObject
	 */
	private $mock_db_wrapper;

	/**
	 * @var WC_Payments_Remote_Note_Service|MockObject
	 */
	private $mock_remote_note_service;

	/**
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * receipt_service
	 *
	 * @var WC_Payments_In_Person_Payments_Receipts_Service|MockObject
	 */
	private $mock_receipt_service;

	/**
	 * mock_wcpay_gateway
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_wcpay_gateway;

	/**
	 * Mock customer service
	 *
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock database cache
	 *
	 * @var Database_Cache
	 */
	private $mock_database_cache;

	/**
	 * @var array
	 */
	private $event_body;

	/**
	 * @var WC_Order
	 */
	private $mock_order;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		/** @var WC_Payments_API_Client|MockObject $mock_api_client */
		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
								->disableOriginalConstructor()
								->getMock();

		$mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		$this->order_service = new WC_Payments_Order_Service(
			$this->createMock( WC_Payments_API_Client::class )
		);

		$this->mock_db_wrapper = $this->getMockBuilder( WC_Payments_DB::class )
									->disableOriginalConstructor()
									->setMethods( [ 'order_from_charge_id', 'order_from_intent_id', 'order_from_order_id' ] )
									->getMock();

		$this->mock_remote_note_service = $this->createMock( WC_Payments_Remote_Note_Service::class );

		$this->mock_receipt_service = $this->createMock( WC_Payments_In_Person_Payments_Receipts_Service::class );

		$this->mock_wcpay_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );

		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );

		$this->mock_database_cache = $this->createMock( Database_Cache::class );

		$this->webhook_processing_service = new WC_Payments_Webhook_Processing_Service( $mock_api_client, $this->mock_db_wrapper, $mock_wcpay_account, $this->mock_remote_note_service, $this->order_service, $this->mock_receipt_service, $this->mock_wcpay_gateway, $this->mock_customer_service, $this->mock_database_cache );

		// Build the event body data.
		$event_object = [];

		$event_data           = [];
		$event_data['object'] = $event_object;

		$this->event_body         = [];
		$this->event_body['data'] = $event_data;

		$this->mock_order = $this->createMock( WC_Order::class );
		$this->mock_order
			->expects( $this->any() )
			->method( 'get_id' )
			->willReturn( 1234 );
	}

	/**
	 * Test processing a webhook that requires no action.
	 */
	public function test_noop_webhook() {
		// Setup test request data.
		$this->event_body['type'] = 'unknown.webhook.event';

		// Run the test.
		$result = $this->webhook_processing_service->process( $this->event_body );

		// This is to ensure that process is still gone through without any issue.
		$this->assertNull( $result );
	}

	/**
	 * Test a webhook with no type property.
	 */
	public function test_webhook_with_no_type_property() {

		$this->expectException( Invalid_Webhook_Data_Exception::class );
		$this->expectExceptionMessage( 'type not found in array' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Test a webhook with no object property.
	 */
	public function test_webhook_with_no_object_property() {
		// Setup test request data.
		$this->event_body['type'] = 'charge.refund.updated';
		unset( $this->event_body['data']['object'] );

		$this->expectException( Invalid_Webhook_Data_Exception::class );
		$this->expectExceptionMessage( 'object not found in array' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );

	}

	/**
	 * Test a webhook with no data property.
	 */
	public function test_webhook_with_no_data_property() {
		// Setup test request data.
		$this->event_body['type'] = 'charge.refund.updated';
		unset( $this->event_body['data'] );

		$this->expectException( Invalid_Webhook_Data_Exception::class );
		$this->expectExceptionMessage( 'data not found in array' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Test a valid refund sets failed meta.
	 */
	public function test_valid_failed_refund_webhook_sets_failed_meta() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.refund.updated';
		$this->event_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'test_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'gbp',
		];

		$this->mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				'A refund of <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>9.99</bdi></span> was <strong>unsuccessful</strong> using WooCommerce Payments (<code>test_refund_id</code>).'
			);

		// The expects condition here is the real test; we expect that the 'update_meta_data' function
		// is called with the appropriate values.
		$this->mock_order
			->expects( $this->once() )
			->method( 'update_meta_data' )
			->with( '_wcpay_refund_status', 'failed' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Test a vaild refund failure deletes WooCommerce Refund.
	 */
	public function test_valid_failed_refund_webhook_deletes_wc_refund() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.refund.updated';
		$this->event_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'test_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'usd',
		];

		$mock_refund_1 = $this->createMock( WC_Order_Refund::class );
		$mock_refund_1->method( 'get_meta' )->willReturn( 'another_test_refund_id' );

		$mock_refund_2 = $this->createMock( WC_Order_Refund::class );
		$mock_refund_2->method( 'get_meta' )->willReturn( 'test_refund_id' );

		$this->mock_order->method( 'get_refunds' )->willReturn(
			[
				$mock_refund_1,
				$mock_refund_2,
			]
		);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		$mock_refund_1
			->expects( $this->never() )
			->method( 'delete' );

		$mock_refund_2
			->expects( $this->once() )
			->method( 'delete' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Test a valid refund does not set failed meta.
	 */
	public function test_non_failed_refund_update_webhook_does_not_set_failed_meta() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.refund.updated';
		$this->event_body['data']['object'] = [
			'status' => 'success',
		];

		$this->mock_db_wrapper
			->expects( $this->never() )
			->method( 'order_from_charge_id' );

		// The expects condition here is the real test; we expect that the 'update_meta_data' function
		// is never called to update the meta data.
		$this->mock_order
			->expects( $this->never() )
			->method( 'update_meta_data' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Test a valid failed refund update webhook.
	 */
	public function test_valid_failed_refund_update_webhook() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.refund.updated';
		$this->event_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'test_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'gbp',
		];

		$this->mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				'A refund of <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>9.99</bdi></span> was <strong>unsuccessful</strong> using WooCommerce Payments (<code>test_refund_id</code>).'
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Test a valid failed refund update webhook for non-USD.
	 */
	public function test_valid_failed_refund_update_webhook_non_usd() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.refund.updated';
		$this->event_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'test_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'eur',
		];

		$this->mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with( 'A refund of <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&euro;</span>9.99</bdi></span> was <strong>unsuccessful</strong> using WooCommerce Payments (<code>test_refund_id</code>).' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Test a valid failed refund update webhook for zero decimal currency.
	 */
	public function test_valid_failed_refund_update_webhook_zero_decimal_currency() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.refund.updated';
		$this->event_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'test_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'jpy',
		];

		$this->mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with( 'A refund of <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&yen;</span>999.00</bdi></span> was <strong>unsuccessful</strong> using WooCommerce Payments (<code>test_refund_id</code>).' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Test a valid failed refund update webhook with an unknown charge ID.
	 */
	public function test_valid_failed_refund_update_webhook_with_unknown_charge_id() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.refund.updated';
		$this->event_body['data']['object'] = [
			'status'   => 'failed',
			'charge'   => 'unknown_charge_id',
			'id'       => 'test_refund_id',
			'amount'   => 999,
			'currency' => 'gbp',
		];

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'unknown_charge_id' )
			->willReturn( false );

		$this->expectException( Invalid_Payment_Method_Exception::class );
		$this->expectExceptionMessage( 'Could not find order via charge ID: unknown_charge_id' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Test a valid non-failed refund update webhook
	 */
	public function test_non_failed_refund_update_webhook() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.refund.updated';
		$this->event_body['data']['object'] = [
			'status' => 'updated',
			'charge' => 'test_charge_id',
			'id'     => 'test_refund_id',
			'amount' => 999,
		];

		$this->mock_db_wrapper
			->expects( $this->never() )
			->method( 'order_from_charge_id' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a remote note webhook puts the note in the inbox.
	 */
	public function test_remote_note_puts_note() {
		// Setup test request data.
		$this->event_body['type'] = 'wcpay.notification';
		$this->event_body['data'] = [
			'title'   => 'test',
			'content' => 'hello',
		];
		$this->mock_remote_note_service
			->expects( $this->once() )
			->method( 'put_note' )
			->with(
				[
					'title'   => 'test',
					'content' => 'hello',
				]
			);

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );

	}

	/**
	 * Tests that a remote note webhook handles service exceptions.
	 */
	public function test_remote_note_fails_returns_expected_webhook_exception() {
		// Setup test request data.
		$this->event_body['type'] = 'wcpay.notification';
		$this->event_body['data'] = [
			'foo' => 'bar',
		];
		$this->mock_remote_note_service
			->expects( $this->once() )
			->method( 'put_note' )
			->willThrowException( new Rest_Request_Exception( 'Invalid note.' ) );

		$this->expectException( Invalid_Webhook_Data_Exception::class );
		$this->expectExceptionMessage( 'Invalid note.' );

		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that an exception thrown in an action will be caught but webhook will still be handled successfully
	 */
	public function test_action_hook_exception_returns_response() {
		add_action(
			'woocommerce_payments_before_webhook_delivery',
			function() {
				throw new Exception( 'Crash before' );
			}
		);

		add_action(
			'woocommerce_payments_after_webhook_delivery',
			function() {
				throw new Exception( 'Crash after' );
			}
		);

		// Setup test request data.
		$this->event_body['type'] = 'wcpay.notification';
		$this->event_body['data'] = [
			'title'   => 'test',
			'content' => 'hello',
		];
		$this->mock_remote_note_service
			->expects( $this->once() )
			->method( 'put_note' )
			->with(
				[
					'title'   => 'test',
					'content' => 'hello',
				]
			);

		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a payment_intent.succeeded event will complete the order.
	 */
	public function test_payment_intent_successful_and_completes_order() {
		$this->event_body['type']           = 'payment_intent.succeeded';
		$this->event_body['data']['object'] = [
			'id'       => $id            = 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [
				'data' => [
					[
						'id'             => $charge_id         = 'py_123123123123123',
						'payment_method' => $payment_method_id = 'pm_foo',
					],
				],
			],
			'currency' => $currency      = 'eur',
			'status'   => $intent_status = 'succeeded',
		];

		$this->mock_order
			->expects( $this->exactly( 5 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_intent_id', $id ],
				[ '_charge_id', $charge_id ],
				[ '_payment_method_id', $payment_method_id ],
				[ WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, $currency ],
				[ '_intention_status', $intent_status ]
			);

		$this->mock_order
			->expects( $this->exactly( 2 ) )
			->method( 'save' );

		$this->mock_order
			->expects( $this->exactly( 2 ) )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( false );

		$this->mock_order
			->expects( $this->once() )
			->method( 'payment_complete' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( $this->mock_order );

		$this->mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		$this->mock_receipt_service
			->expects( $this->never() )
			->method( 'send_customer_ipp_receipt_email' );

		$this->mock_wcpay_gateway
			->expects( $this->never() )
			->method( 'get_option' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a payment_intent.succeeded event will complete the order even if the intent was not properly attached into the order.
	 */
	public function test_payment_intent_successful_and_completes_order_without_intent_id() {
		$this->event_body['type']           = 'payment_intent.succeeded';
		$this->event_body['data']['object'] = [
			'id'       => $id            = 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [
				'data' => [
					[
						'id'             => $charge_id         = 'py_123123123123123',
						'payment_method' => $payment_method_id = 'pm_foo',
					],
				],
			],
			'currency' => $currency      = 'eur',
			'status'   => $intent_status = 'succeeded',
			'metadata' => [ 'order_id' => 'id_1323' ], // Using order_id inside of the intent metadata to find the order.
		];

		$this->mock_order
			->expects( $this->exactly( 5 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_intent_id', $id ],
				[ '_charge_id', $charge_id ],
				[ '_payment_method_id', $payment_method_id ],
				[ WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, $currency ],
				[ '_intention_status', $intent_status ]
			);

		$this->mock_order
			->expects( $this->exactly( 2 ) )
			->method( 'save' );

		$this->mock_order
			->expects( $this->exactly( 2 ) )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( false );

		$this->mock_order
			->expects( $this->once() )
			->method( 'payment_complete' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( null );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_order_id' )
			->with( 'id_1323' )
			->willReturn( $this->mock_order );

		$this->mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		$this->mock_receipt_service
			->expects( $this->never() )
			->method( 'send_customer_ipp_receipt_email' );

		$this->mock_wcpay_gateway
			->expects( $this->never() )
			->method( 'get_option' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a payment_intent.succeeded event will not complete the order
	 * if it is already completed/processed.
	 */
	public function test_payment_intent_successful_when_retrying() {
		$this->event_body['type']           = 'payment_intent.succeeded';
		$this->event_body['data']['object'] = [
			'id'       => $id            = 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [
				'data' => [
					[
						'id'             => $charge_id         = 'py_123123123123123',
						'payment_method' => $payment_method_id = 'pm_foo',
					],
				],
			],
			'currency' => $currency      = 'eur',
			'status'   => $intent_status = 'succeeded',
		];

		$this->mock_order
			->expects( $this->exactly( 4 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_intent_id', $id ],
				[ '_charge_id', $charge_id ],
				[ '_payment_method_id', $payment_method_id ],
				[ WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, $currency ]
			);

		$this->mock_order
			->expects( $this->once() )
			->method( 'save' );

		$this->mock_order
			->expects( $this->once() )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( true );

		$this->mock_order
			->expects( $this->never() )
			->method( 'payment_complete' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( $this->mock_order );

		$this->mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		$this->mock_receipt_service
			->expects( $this->never() )
			->method( 'send_customer_ipp_receipt_email' );

		$this->mock_wcpay_gateway
			->expects( $this->never() )
			->method( 'get_option' );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );

	}

	/**
	 * Tests that a payment_intent.succeeded event will complete the order and
	 * send the card reader receipt to the customer.
	 */
	public function test_payment_intent_successful_and_send_card_reader_receipt() {
		$this->event_body['type']           = 'payment_intent.succeeded';
		$this->event_body['data']['object'] = [
			'id'       => 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [
				'data' => [
					[
						'id'                     => 'py_123123123123123',
						'payment_method_details' => [
							'type' => 'card_present',
						],
					],
				],
			],
			'currency' => 'eur',
			'status'   => 'succeeded',
		];

		$mock_merchant_settings = [
			'business_name' => 'Test Business',
			'support_info'  => [
				'address' => [],
				'phone'   => '42424',
				'email'   => 'some@example.com',
			],
		];

		$this->mock_order
			->expects( $this->exactly( 2 ) )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( false );

		$this->mock_order
			->expects( $this->once() )
			->method( 'payment_complete' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( $this->mock_order );

		$this->mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		$this->mock_receipt_service
			->expects( $this->once() )
			->method( 'send_customer_ipp_receipt_email' )
			->with(
				$this->mock_order,
				$mock_merchant_settings,
				$this->event_body['data']['object']['charges']['data'][0]
			);

		$this->mock_wcpay_gateway
			->expects( $this->exactly( 4 ) )
			->method( 'get_option' )
			->withConsecutive(
				[ 'account_business_name' ],
				[ 'account_business_support_address' ],
				[ 'account_business_support_phone' ],
				[ 'account_business_support_email' ]
			)
			->willReturnOnConsecutiveCalls(
				$mock_merchant_settings['business_name'],
				$mock_merchant_settings['support_info']['address'],
				$mock_merchant_settings['support_info']['phone'],
				$mock_merchant_settings['support_info']['email']
			);

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a payment_intent.succeeded event will complete the order.
	 */
	public function test_payment_intent_fails_and_fails_order() {
		$this->event_body['type']           = 'payment_intent.payment_failed';
		$this->event_body['data']['object'] = [
			'id'       => 'pi_123123123123123', // Payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [
				'data' => [
					[
						'id'                     => 'py_123123123123123',
						'payment_method'         => 'pm_123123123123123', // Payment method ID.
						'payment_method_details' => [
							'type' => 'us_bank_account',
						],
					],
				],
			],
			'currency' => 'usd',
			'status'   => 'requires_payment_method',
		];

		$this->mock_order
			->expects( $this->exactly( 2 ) )
			->method( 'get_meta' )
			->withConsecutive(
				[ '_payment_method_id' ],
				[ '_intention_status' ]
			)
			->willReturnOnConsecutiveCalls(
				'pm_123123123123123',
				false
			);

		$this->mock_order
			->expects( $this->exactly( 3 ) )
			->method( 'has_status' )
			->withConsecutive(
				[ [ 'failed' ] ],
				[ [ 'processing', 'completed' ] ],
				[ [ 'processing', 'completed' ] ]
			)
			->willReturn( false );

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/The payment was not able to be processed/'
				)
			);

		$this->mock_order
			->expects( $this->once() )
			->method( 'update_status' )
			->with( 'failed' );

		$this->mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a dispute created event adds a respective order note.
	 */
	public function test_dispute_created_order_note() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.dispute.created';
		$this->event_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
			'reason' => 'test_reason',
		];

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment has been disputed as test_reason/'
				)
			);

		$this->mock_order
			->expects( $this->once() )
			->method( 'update_status' )
			->with( 'on-hold' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a dispute closed event adds a respective order note.
	 */
	public function test_dispute_closed_order_note() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.dispute.closed';
		$this->event_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
			'status' => 'test_status',
		];

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment dispute has been closed with status test_status/'
				)
			);

		$this->mock_order
			->expects( $this->once() )
			->method( 'update_status' )
			->with( 'completed' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a dispute updated event adds a respective order note.
	 */
	public function test_dispute_updated_order_note() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.dispute.updated';
		$this->event_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
		];

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment dispute has been updated/'
				)
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a dispute funds withdrawn event adds a respective order note.
	 */
	public function test_dispute_funds_withdrawn_order_note() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.dispute.funds_withdrawn';
		$this->event_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
		];

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment dispute funds have been withdrawn/'
				)
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a dispute funds reinstated event adds a respective order note.
	 */
	public function test_dispute_funds_reinstated_order_note() {
		// Setup test request data.
		$this->event_body['type']           = 'charge.dispute.funds_reinstated';
		$this->event_body['data']['object'] = [
			'id'     => 'test_dispute_id',
			'charge' => 'test_charge_id',
		];

		$this->mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment dispute funds have been reinstated/'
				)
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $this->mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}
}

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

// TODO must fix this after the merge - check all changes related to $order_service on this file in the PR 3675.

// Need to use WC_Mock_Data_Store.
require_once dirname( __FILE__ ) . '/helpers/class-wc-mock-wc-data-store.php';

/**
 * WC_Payments_Webhook_Processing_Service unit tests.
 */
class WC_Payments_Webhook_Processing_Service_Test extends WP_UnitTestCase {

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
	 * @var array
	 */
	private $event_body;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		/** @var WC_Payments_API_Client|MockObject $mock_api_client */
		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
								->disableOriginalConstructor()
								->getMock();

		$account = new WC_Payments_Account( $mock_api_client );

		$this->mock_db_wrapper = $this->getMockBuilder( WC_Payments_DB::class )
									->disableOriginalConstructor()
									->setMethods( [ 'order_from_charge_id', 'order_from_intent_id', 'order_from_order_id' ] )
									->getMock();

		$this->mock_remote_note_service = $this->createMock( WC_Payments_Remote_Note_Service::class );

		$this->webhook_processing_service = new WC_Payments_Webhook_Processing_Service( $this->mock_db_wrapper, $account, $this->mock_remote_note_service );

		// Build the event body data.
		$event_object = [];

		$event_data           = [];
		$event_data['object'] = $event_object;

		$this->event_body         = [];
		$this->event_body['data'] = $event_data;
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

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				'A refund of <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>9.99</bdi></span> was <strong>unsuccessful</strong> using WooCommerce Payments (<code>test_refund_id</code>).'
			);

		// The expects condition here is the real test; we expect that the 'update_meta_data' function
		// is called with the appropriate values.
		$mock_order
			->expects( $this->once() )
			->method( 'update_meta_data' )
			->with( '_wcpay_refund_status', 'failed' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

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

		$mock_order = $this->createMock( WC_Order::class );

		$this->mock_db_wrapper
			->expects( $this->never() )
			->method( 'order_from_charge_id' );

		// The expects condition here is the real test; we expect that the 'update_meta_data' function
		// is never called to update the meta data.
		$mock_order
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

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				'A refund of <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&pound;</span>9.99</bdi></span> was <strong>unsuccessful</strong> using WooCommerce Payments (<code>test_refund_id</code>).'
			);

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

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

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with( 'A refund of <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&euro;</span>9.99</bdi></span> was <strong>unsuccessful</strong> using WooCommerce Payments (<code>test_refund_id</code>).' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

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

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order->method( 'get_currency' )->willReturn( 'GBP' );

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with( 'A refund of <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&yen;</span>999.00</bdi></span> was <strong>unsuccessful</strong> using WooCommerce Payments (<code>test_refund_id</code>).' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

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
		$this->expectExceptionMessage( 'Could not find order via charge ID: unknown_charge_i' );

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
	public function test_remote_note_fails_returns_response() {
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
			'id'       => 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [],
			'currency' => 'eur',
		];

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order
			->expects( $this->once() )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( false );

		$mock_order
			->expects( $this->once() )
			->method( 'payment_complete' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( $mock_order );

		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}

	/**
	 * Tests that a payment_intent.succeeded event will complete the order even if the intent was not properly attached into the order.
	 */
	public function test_payment_intent_successful_and_completes_order_without_intent_id() {
		$this->event_body['type']           = 'payment_intent.succeeded';
		$this->event_body['data']['object'] = [
			'id'       => 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [],
			'currency' => 'eur',
			'metadata' => [ 'order_id' => 'id_1323' ], // Using order_id inside of the intent metadata to find the order.
		];

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order
			->expects( $this->once() )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( false );

		$mock_order
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
			->willReturn( $mock_order );

		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

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
			'id'       => 'pi_123123123123123', // payment_intent's ID.
			'object'   => 'payment_intent',
			'amount'   => 1500,
			'charges'  => [],
			'currency' => 'eur',
		];

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order
			->expects( $this->once() )
			->method( 'has_status' )
			->with( [ 'processing', 'completed' ] )
			->willReturn( true );

		$mock_order
			->expects( $this->never() )
			->method( 'payment_complete' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( $mock_order );

		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

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
						'payment_method'         => 'pm_123123123123123', // Payment method ID.
						'payment_method_details' => [
							'type' => 'us_bank_account',
						],
					],
				],
			],
			'currency' => 'usd',
		];

		$mock_order = $this->createMock( WC_Order::class );

		$mock_order
			->expects( $this->exactly( 3 ) )
			->method( 'get_meta' )
			->withConsecutive(
				[ '_payment_method_id' ],
				[ '_charge_id' ],
				[ '_intent_id' ]
			)
			->willReturnOnConsecutiveCalls(
				'pm_123123123123123',
				'py_123123123123123',
				'pi_123123123123123'
			);

		$mock_order
			->expects( $this->once() )
			->method( 'has_status' )
			->with( [ 'failed' ] )
			->willReturn( false );

		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/The payment was not able to be processed/'
				)
			);

		$mock_order
			->expects( $this->once() )
			->method( 'update_status' )
			->with( 'failed' );

		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_intent_id' )
			->with( 'pi_123123123123123' )
			->willReturn( $mock_order );

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

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment has been disputed as test_reason/'
				)
			);

		$mock_order
			->expects( $this->once() )
			->method( 'update_status' )
			->with( 'on-hold' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

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

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->matchesRegularExpression(
					'/Payment dispute has been closed with status test_status/'
				)
			);

		$mock_order
			->expects( $this->once() )
			->method( 'update_status' )
			->with( 'completed' );

		$this->mock_db_wrapper
			->expects( $this->once() )
			->method( 'order_from_charge_id' )
			->with( 'test_charge_id' )
			->willReturn( $mock_order );

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

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
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
			->willReturn( $mock_order );

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

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
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
			->willReturn( $mock_order );

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

		$mock_order = $this->createMock( WC_Order::class );
		$mock_order
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
			->willReturn( $mock_order );

		// Run the test.
		$this->webhook_processing_service->process( $this->event_body );
	}
}

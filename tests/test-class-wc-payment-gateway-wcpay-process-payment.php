<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Process_Payment_Test extends WP_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $mock_wcpay_gateway;

	/**
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $wcpay_account;

	/**
	 * Mocked value of return_url.
	 * The value is used in the set up and tests, so it's set as a private
	 * variable for easy reference.
	 *
	 * @var string
	 */
	private $return_url = 'test_url';

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		// Arrange: Mock WC_Payments_API_Client so we can configure the
		// return value of create_and_confirm_intention().
		// Note that we cannot use createStub here since it's not defined in PHPUnit 6.5.
		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( [ 'create_and_confirm_intention' ] )
			->getMock();

		// Arrange: Create new WC_Payments_Account instance to use later.
		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );

		// Arrange: Mock WC_Payments_Customer_Service so its methods aren't called directly.
		$this->mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
			->disableOriginalConstructor()
			->getMock();

		// Arrange: Mock WC_Payment_Gateway_WCPay so that some of its methods can be
		// mocked, and their return values can be used for testing.
		$this->mock_wcpay_gateway = $this->getMockBuilder( 'WC_Payment_Gateway_WCPay' )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->wcpay_account,
					$this->mock_customer_service,
				]
			)
			->setMethods(
				[
					'get_return_url',
					'mark_payment_complete_for_order',
					'get_level3_data_from_order', // To avoid needing to mock the order items.
				]
			)
			->getMock();

		// Arrange: Set the return value of get_return_url() so it can be used in a test later.
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'get_return_url' )
			->will(
				$this->returnValue( $this->return_url )
			);

		// Arrange: Define a $_POST array which includes the payment method,
		// so that get_payment_method_from_request() does not throw error.
		$_POST = [
			'wcpay-payment-method' => true,
		];
	}

	/**
	 * Test processing payment with the status 'succeeded'.
	 */
	public function test_intent_status_success() {
		// Arrange: Reusable data.
		$intent_id = 'pi_123';
		$charge_id = 'ch_123';
		$status    = 'succeeded';
		$secret    = 'client_secret_123';
		$order_id  = 123;
		$total     = 12.23;

		// Arrange: Create an order to test with.
		$mock_order = $this->createMock( 'WC_Order' );

		// Arrange: Set a good return value for order ID.
		$mock_order
			->method( 'get_id' )
			->willReturn( $order_id );

		// Arrange: Set a good return value for order total.
		$mock_order
			->method( 'get_total' )
			->willReturn( $total );

		// Arrange: Return a successful response from create_and_confirm_intention().
		$intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			new DateTime(),
			$status,
			$charge_id,
			$secret
		);
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will(
				$this->returnValue( $intent )
			);

		// Assert: Order has correct charge id meta data.
		// Assert: Order has correct intention status meta data.
		// Assert: Order has correct intent ID.
		// This test is a little brittle because we don't really care about the order
		// in which the different calls are made, but it's not possible to write it
		// otherwise for now.
		// There's an issue open for that here:
		// https://github.com/sebastianbergmann/phpunit/issues/4026.
		$mock_order
			->expects( $this->exactly( 3 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_intent_id', $intent_id ],
				[ '_charge_id', $charge_id ],
				[ '_intention_status', $status ]
			);

		// Assert: The order note contains all the information we want:
		// - status
		// - intention id
		// - amount charged.
		$mock_order
			->expects( $this->once() )
			->method( 'add_order_note' )
			->with(
				$this->callback(
					function( $note ) use ( $intent_id, $total ) {
						return (
						strpos( $note, 'successfully charged' )
						&& strpos( $note, $intent_id )
						&& strpos( $note, strval( $total ) )
						);
					}
				)
			);

		// Assert: `payment_complete` is called.
		$mock_order
			->expects( $this->once() )
			->method( 'payment_complete' );

		// Act: process a successful payment.
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_order );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $this->return_url, $result['redirect'] );
	}

	/**
	 * Test processing payment with the status "requires_capture".
	 */
	public function test_intent_status_requires_capture() {
		// Arrange: Reusable data.
		$intent_id = 'pi_123';
		$charge_id = 'ch_123';
		$status    = 'requires_capture';
		$secret    = 'client_secret_123';
		$order_id  = 123;
		$total     = 12.23;

		// Arrange: Create an order to test with.
		$mock_order = $this->createMock( 'WC_Order' );

		// Arrange: Set a good return value for order ID.
		$mock_order
			->method( 'get_id' )
			->willReturn( $order_id );

		// Arrange: Set a good return value for order total.
		$mock_order
			->method( 'get_total' )
			->willReturn( $total );

		// Arrange: Return a 'requires_capture' response from create_and_confirm_intention().
		$intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			new DateTime(),
			$status,
			$charge_id,
			$secret
		);
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will(
				$this->returnValue( $intent )
			);

		// Assert: Order has correct charge id meta data.
		// Assert: Order has correct intention status meta data.
		// Assert: Order has correct intent ID.
		// This test is a little brittle because we don't really care about the order
		// in which the different calls are made, but it's not possible to write it
		// otherwise for now.
		// There's an issue open for that here:
		// https://github.com/sebastianbergmann/phpunit/issues/4026.
		$mock_order
			->expects( $this->exactly( 3 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_intent_id', $intent_id ],
				[ '_charge_id', $charge_id ],
				[ '_intention_status', $status ]
			);

		// Assert: The order note contains all the information we want:
		// - status
		// - intention id
		// - amount charged.
		// Note that the note and the order status are updated at the same
		// time using `update_status()`.
		$mock_order
			->expects( $this->exactly( 1 ) )
			->method( 'update_status' )
			->with(
				'on-hold',
				$this->callback(
					function( $note ) use ( $intent_id, $total ) {
						return (
						strpos( $note, 'authorized' )
						&& strpos( $note, $intent_id )
						&& strpos( $note, strval( $total ) )
						);
					}
				)
			);

		// Assert: Order has correct transaction ID set.
		$mock_order
			->expects( $this->exactly( 1 ) )
			->method( 'set_transaction_id' )
			->with( $intent_id );

		// Act: process payment.
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_order );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $this->return_url, $result['redirect'] );
	}

	public function test_exception_thrown() {
		// Arrange: Reusable data.
		$error_message = 'Error: No such customer: 123';

		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		// Arrange: Throw an exception in create_and_confirm_intention.
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will(
				$this->throwException(
					new WC_Payments_API_Exception(
						$error_message,
						'resource_missing',
						400
					)
				)
			);

		// Act: process payment.
		$result = $this->mock_wcpay_gateway->process_payment( $order->get_id() );

		// Assert: A notice has been added.
		$this->assertTrue( wc_has_notice( $error_message, 'error' ) );

		// Assert: Order has correct  status.
		// Need to get the order again to see the correct order status.
		$updated_order = wc_get_order( $order->get_id() );
		$this->assertEquals( $updated_order->get_status(), 'failed' );

		// Assert: Returning correct array.
		$this->assertEquals( 'fail', $result['result'] );
		$this->assertEquals( '', $result['redirect'] );

		// Assert: Order does not have transaction ID set.
		$this->assertEquals( $updated_order->get_transaction_id(), '' );

		// Assert: Order does not have charge id meta data.
		$this->assertEquals( $order->get_meta( '_charge_id' ), '' );

		// Assert: Order does not have intention status meta data.
		$this->assertEquals( $order->get_meta( '_intention_status' ), '' );

		// Assert: Order does not have intent ID meta data.
		$this->assertEquals( $order->get_meta( '_intent_id' ), '' );

		// Assert: There is no order note added.
		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
			]
		);
		$latest_wcpay_note = current(
			array_filter(
				$notes,
				function( $note ) {
					return false !== strpos( $note->content, 'WooCommerce Payments' );
				}
			)
		);
		// Note that for empty arrays, current() may return
		// Boolean FALSE, but may also return a non-Boolean value which evaluates to FALSE.
		// That's why it's being cast to Boolean for the test.
		$this->assertFalse( (bool) $latest_wcpay_note );
	}

	/**
	 * Test processing payment with the status "requires_action".
	 * This is the status returned when the payment requires
	 * further authentication with 3DS.
	 */
	public function test_intent_status_requires_action() {
		// Arrange: Reusable data.
		$intent_id = 'pi_123';
		$charge_id = 'ch_123';
		$status    = 'requires_action';
		$secret    = 'client_secret_123';

		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		// Arrange: Return a 'requires_action' response from create_and_confirm_intention().
		$intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			new DateTime(),
			$status,
			$charge_id,
			$secret
		);
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will(
				$this->returnValue( $intent )
			);

		// Act: process payment.
		$result = $this->mock_wcpay_gateway->process_payment( $order->get_id() );

		// Assert: The order note contains all the information we want:
		// - status
		// - intention id
		// - amount charged.
		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
			]
		);
		$latest_wcpay_note = current(
			array_filter(
				$notes,
				function( $note ) {
					return false !== strpos( $note->content, 'WooCommerce Payments' );
				}
			)
		);
		$this->assertContains( 'started', $latest_wcpay_note->content );
		$this->assertContains( $intent_id, $latest_wcpay_note->content );
		$this->assertContains( $order->get_total(), $latest_wcpay_note->content );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals(
			'#wcpay-confirm-pi:' . $order->get_id() . ':' . $secret,
			$result['redirect']
		);

		// Assert: Order has correct  status.
		// Need to get the order again to see the correct order status.
		$updated_order = wc_get_order( $order->get_id() );
		$this->assertEquals( $updated_order->get_status(), 'pending' );

		// Assert: Order does not have transaction IsD set.
		$this->assertEquals( $updated_order->get_transaction_id(), '' );

		// Assert: Order does not have charge id meta data.
		$this->assertEquals( $order->get_meta( '_charge_id' ), '' );

		// Assert: Order does not have intention status meta data.
		$this->assertEquals( $order->get_meta( '_intention_status' ), 'requires_action' );

		// Assert: Order has correct intent ID.
		$this->assertEquals( $order->get_meta( '_intent_id' ), $intent_id );
	}
}

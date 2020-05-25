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
			->setMethods( [ 'get_return_url' ] )
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
	 */
	public function test_intent_status_success() {
		// Arrange: Reusable data.
		$intent_id = 'pi_123';
		$charge_id = 'ch_123';
		$status    = 'succeeded';

		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		// Arrange: Return a successful response from create_and_confirm_intention().
		$intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			new DateTime(),
			$status,
			$charge_id
		);
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will(
				$this->returnValue( $intent )
			);

		// Act: process a successful payment.
		$result = $this->mock_wcpay_gateway->process_payment( $order->get_id() );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $this->return_url, $result['redirect'] );

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
		$this->assertContains( 'successfully charged', $latest_wcpay_note->content );
		$this->assertContains( $intent_id, $latest_wcpay_note->content );
		$this->assertContains( $order->get_total(), $latest_wcpay_note->content );

		// Assert: Order has correct charge id meta data.
		$this->assertEquals( $order->get_meta( '_charge_id' ), $charge_id );

		// Assert: Order has correct intention status meta data.
		$this->assertEquals( $order->get_meta( '_intention_status' ), $status );
	}

	/**
	 * Test processing payment with the status "requires_capture".
	 */
	public function test_intent_status_requires_capture() {
		// Arrange: Reusable data.
		$intent_id = 'pi_123';
		$charge_id = 'ch_123';
		$status    = 'requires_capture';

		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		// Arrange: Return a 'requires_capture' response from create_and_confirm_intention().
		$intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			new DateTime(),
			$status,
			$charge_id
		);
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will(
				$this->returnValue( $intent )
			);

		// Act: process payment.
		$result = $this->mock_wcpay_gateway->process_payment( $order->get_id() );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $this->return_url, $result['redirect'] );

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
		$this->assertContains( 'authorized', $latest_wcpay_note->content );
		$this->assertContains( $intent_id, $latest_wcpay_note->content );
		$this->assertContains( $order->get_total(), $latest_wcpay_note->content );

		// Assert: Order has correct  status.
		// Need to get the order again to see the correct order status.
		$updated_order = wc_get_order( $order->get_id() );
		$this->assertEquals( $updated_order->get_status(), 'on-hold' );

		// Assert: Order has correct transaction ID set.
		$this->assertEquals( $updated_order->get_transaction_id(), $intent_id );

		// Assert: Order has correct charge id meta data.
		$this->assertEquals( $order->get_meta( '_charge_id' ), $charge_id );

		// Assert: Order has correct intention status meta data.
		$this->assertEquals( $order->get_meta( '_intention_status' ), $status );
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
		$this->assertEquals( $order->get_meta( '_intention_status' ), null );

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
}

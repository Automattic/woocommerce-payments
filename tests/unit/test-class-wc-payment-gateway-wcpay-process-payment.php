<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;

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
	 * Mock WC_Payments_Token_Service.
	 *
	 * @var WC_Payments_Token_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_token_service;

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
			->setMethods( [ 'create_and_confirm_intention', 'get_payment_method' ] )
			->getMock();

		// Arrange: Create new WC_Payments_Account instance to use later.
		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );

		// Arrange: Mock WC_Payments_Customer_Service so its methods aren't called directly.
		$this->mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
			->disableOriginalConstructor()
			->getMock();

		// Arrange: Mock WC_Payments_Customer_Service so its methods aren't called directly.
		$this->mock_token_service = $this->getMockBuilder( 'WC_Payments_Token_Service' )
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
					$this->mock_token_service,
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
			'wcpay-payment-method' => 'pm_mock',
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

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

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

		// Assert: empty_cart() was called.
		$mock_cart
			->expects( $this->once() )
			->method( 'empty_cart' );

		// Act: process a successful payment.
		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $mock_order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result              = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

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

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

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
		// time using `set_status()`.
		$mock_order
			->expects( $this->exactly( 1 ) )
			->method( 'set_status' )
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

		// Assert: empty_cart() was called.
		$mock_cart
			->expects( $this->once() )
			->method( 'empty_cart' );

		// Act: process payment.
		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $mock_order, WCPay\Constants\Payment_Type::SINGLE(), WCPay\Constants\Payment_Initiated_By::CUSTOMER(), WCPay\Constants\Payment_Capture_Type::MANUAL() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result              = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $this->return_url, $result['redirect'] );
	}

	public function test_exception_thrown() {
		// Arrange: Reusable data.
		$error_message = 'Error: No such customer: 123.';
		$order_id      = 123;
		$total         = 12.23;

		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		// Arrange: Throw an exception in create_and_confirm_intention.
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will(
				$this->throwException(
					new API_Exception(
						$error_message,
						'resource_missing',
						400
					)
				)
			);

		// Act: process payment.
		$result       = $this->mock_wcpay_gateway->process_payment( $order->get_id(), false );
		$result_order = wc_get_order( $order->get_id() );

		// Assert: Order status was updated.
		$this->assertEquals( 'failed', $result_order->get_status() );

		// Assert: Order transaction ID was not set.
		$this->assertEquals( '', $result_order->get_meta( '_transaction_id' ) );

		// Assert: Order meta was not updated with charge ID, intention status, or intent ID.
		$this->assertEquals( '', $result_order->get_meta( '_intent_id' ) );
		$this->assertEquals( '', $result_order->get_meta( '_charge_id' ) );
		$this->assertEquals( '', $result_order->get_meta( '_intention_status' ) );

		// Assert: No order note was added, besides the status change and failed transaction details.
		$notes = wc_get_order_notes( [ 'order_id' => $result_order->get_id() ] );
		$this->assertCount( 2, $notes );
		$this->assertEquals( 'Order status changed from Pending payment to Failed.', $notes[1]->content );
		$this->assertContains( 'A payment of &pound;50.00 failed to complete with the following message: Error: No such customer: 123.', strip_tags( $notes[0]->content, '' ) );

		// Assert: A WooCommerce notice was added.
		$this->assertTrue( wc_has_notice( $error_message, 'error' ) );

		// Assert: Returning correct array.
		$this->assertEquals( 'fail', $result['result'] );
		$this->assertEquals( '', $result['redirect'] );
	}

	public function test_api_exception_filters_are_run() {
		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		$error_data = [
			'currency'   => 'usd',
			'min_amount' => 60,
		];

		// Arrange: Throw an exception in create_and_confirm_intention.
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will(
				$this->throwException(
					new API_Exception(
						'Test',
						'wcpay_amount_too_low',
						400,
						0,
						null,
						$error_data
					)
				)
			);

		// Act: process payment.
		$result = $this->mock_wcpay_gateway->process_payment( $order->get_id(), false );

		$error_message = sprintf(
			// translators: %1$s is a formatted amount with currency code.
			__(
				'Sorry, the minimum allowed order total is %1$s to use this payment method.',
				'woocommerce-payments'
			),
			wc_price( $error_data['min_amount'] / 100, [ 'currency' => strtoupper( $error_data['currency'] ) ] )
		);

		// Assert: A WooCommerce notice was added.
		$this->assertTrue( wc_has_notice( $error_message, 'error' ) );
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

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

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

		// Assert: Order charge id meta data was updated with `update_meta_data()`.
		// Assert: Order does not have intention status meta data.
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
				[ '_intention_status', 'requires_action' ]
			);

		// Assert: Order status was not updated.
		$mock_order
			->expects( $this->never() )
			->method( 'set_status' );

		// Assert: The order note contains all the information we want:
		// - status
		// - intention id
		// - amount charged.
		$mock_order
			->expects( $this->exactly( 1 ) )
			->method( 'add_order_note' )
			->with(
				$this->callback(
					function( $note ) use ( $intent_id, $total ) {
						return (
						strpos( $note, 'started' )
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

		// Assert: empty_cart() was not called.
		$mock_cart
			->expects( $this->never() )
			->method( 'empty_cart' );

		// Act: process payment.
		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $mock_order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result              = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals(
			'#wcpay-confirm-pi:' . $order_id . ':' . $secret . ':' . wp_create_nonce( 'wcpay_update_order_status_nonce' ),
			$result['redirect']
		);
	}

	public function test_saved_card_at_checkout() {
		$order = WC_Helper_Order::create_order();

		$intent = new WC_Payments_API_Intention( 'pi_mock', 1500, new DateTime(), 'succeeded', 'ch_mock', 'client_secret_123' );

		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->with( $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), true, $this->anything(), $this->anything() )
			->will( $this->returnValue( $intent ) );

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( 'pm_mock', $order->get_user() )
			->will( $this->returnValue( new WC_Payment_Token_CC() ) );

		$_POST['wc-woocommerce_payments-new-payment-method'] = 'true';
		$result = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_not_saved_card_at_checkout() {
		$order = WC_Helper_Order::create_order();

		$intent = new WC_Payments_API_Intention( 'pi_mock', 1500, new DateTime(), 'succeeded', 'ch_mock', 'client_secret_123' );

		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->with( $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), false, $this->anything(), $this->anything() )
			->will( $this->returnValue( $intent ) );

		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		$result = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_does_not_update_new_payment_method() {
		$order = WC_Helper_Order::create_order();

		$intent = new WC_Payments_API_Intention( 'pi_mock', 1500, new DateTime(), 'succeeded', 'ch_mock', 'client_secret_123' );

		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will( $this->returnValue( $intent ) );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'update_payment_method_with_billing_details_from_order' );

		$this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_updates_payment_method_billing_details() {
		$_POST = $this->setup_saved_payment_method();

		$order = WC_Helper_Order::create_order();

		$intent = new WC_Payments_API_Intention( 'pi_mock', 1500, new DateTime(), 'succeeded', 'ch_mock', 'client_secret_123' );

		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_intention' )
			->will( $this->returnValue( $intent ) );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'update_payment_method_with_billing_details_from_order' )
			->with(
				'pm_mock',
				$this->callback(
					function( $source_order ) use ( $order ) {
						return $source_order->get_id() === $order->get_id();
					}
				)
			);

		$this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	private function setup_saved_payment_method() {
		$token = WC_Helper_Token::create_token( 'pm_mock' );

		return [
			'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' => (string) $token->get_id(),
		];
	}
}

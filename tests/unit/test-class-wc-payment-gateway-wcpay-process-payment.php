<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Core\Server\Request\Get_Charge;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Connection_Exception;
use WCPay\Session_Rate_Limiter;
// Need to use WC_Mock_Data_Store.
require_once dirname( __FILE__ ) . '/helpers/class-wc-mock-wc-data-store.php';

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Process_Payment_Test extends WCPAY_UnitTestCase {
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
	 * Mock WC_Payments_Action_Scheduler_Service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * Mock Session_Rate_Limiter.
	 *
	 * @var Session_Rate_Limiter|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_rate_limiter;

	/**
	 * Mock WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_order_service;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_wcpay_account;

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
	public function set_up() {
		parent::set_up();

		// Arrange: Mock WC_Payments_API_Client so we can configure the
		// return value of create_and_confirm_intention().
		// Note that we cannot use createStub here since it's not defined in PHPUnit 6.5.
		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( [ 'create_and_confirm_intention', 'create_and_confirm_setup_intent', 'get_payment_method', 'is_server_connected', 'request' ] )
			->getMock();

		// Arrange: Mock WC_Payments_Account instance to use later.
		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$this->mock_wcpay_account
			->method( 'get_account_default_currency' )
			->willReturn( 'USD' );

		// Arrange: Mock WC_Payments_Customer_Service so its methods aren't called directly.
		$this->mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
			->disableOriginalConstructor()
			->getMock();

		// Arrange: Mock WC_Payments_Customer_Service so its methods aren't called directly.
		$this->mock_token_service = $this->getMockBuilder( 'WC_Payments_Token_Service' )
			->disableOriginalConstructor()
			->getMock();

		// Arrange: Mock WC_Payments_Action_Scheduler_Service so its methods aren't called directly.
		$this->mock_action_scheduler_service = $this->getMockBuilder( 'WC_Payments_Action_Scheduler_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_rate_limiter = $this->createMock( Session_Rate_Limiter::class );

		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );

		// Arrange: Mock WC_Payment_Gateway_WCPay so that some of its methods can be
		// mocked, and their return values can be used for testing.
		$this->mock_wcpay_gateway = $this->getMockBuilder( 'WC_Payment_Gateway_WCPay' )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_rate_limiter,
					$this->mock_order_service,
				]
			)
			->setMethods(
				[
					'get_return_url',
					'mark_payment_complete_for_order',
					'get_level3_data_from_order', // To avoid needing to mock the order items.
					'should_use_stripe_platform_on_checkout_page',
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
		$intent_id   = 'pi_mock';
		$charge_id   = 'ch_mock';
		$customer_id = 'cus_mock';
		$status      = 'succeeded';
		$order_id    = 123;
		$total       = 12.23;

		// Arrange: Create an order to test with.
		$mock_order = $this->createMock( 'WC_Order' );

		// Arrange: Set a good return value for the order's data store.
		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		// Arrange: Set a good return value for order ID.
		$mock_order
			->method( 'get_id' )
			->willReturn( $order_id );

		// Arrange: Set a good return value for order total.
		$mock_order
			->method( 'get_total' )
			->willReturn( $total );

		// Arrange: Set a WP_User object as a return value of order's get_user.
		$mock_order
			->method( 'get_user' )
			->willReturn( wp_get_current_user() );

		// Arrange: Set a good return value for customer ID.
		$this->mock_customer_service->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( $customer_id );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Arrange: Return a successful response from create_and_confirm_intention().
		$intent = WC_Helper_Intention::create_intention();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		// Assert: Order has correct charge id meta data.
		// Assert: Order has correct intention status meta data.
		// Assert: Order has correct intent ID.
		// This test is a little brittle because we don't really care about the order
		// in which the different calls are made, but it's not possible to write it
		// otherwise for now.
		// There's an issue open for that here:
		// https://github.com/sebastianbergmann/phpunit/issues/4026.
		$mock_order
			->expects( $this->exactly( 10 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ '_wcpay_mode', WC_Payments::mode()->is_test() ? 'test' : 'prod' ],
				[ '_intent_id', $intent_id ],
				[ '_charge_id', $charge_id ],
				[ '_intention_status', $status ],
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, 'USD' ],
				[ '_wcpay_multi_currency_stripe_exchange_rate', 0.86 ]
			);

		// Assert: The Order_Service is called correctly.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'mark_payment_completed' )
			->with( $mock_order, $intent_id, $status, $charge_id );

		// Assert: empty_cart() was called.
		$mock_cart
			->expects( $this->once() )
			->method( 'empty_cart' );
		$charge_request = $this->mock_wcpay_request( Get_Charge::class );

		$charge_request->expects( $this->once() )
			->method( 'set_charge_id' )
			->with( 'ch_mock' );

		$charge_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [ 'balance_transaction' => [ 'exchange_rate' => 0.86 ] ] );

		// Act: process a successful payment.
		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $mock_order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result              = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $this->return_url, $result['redirect'] );
	}

	/**
	 * Test processing payment with the status 'succeeded'.
	 */
	public function test_intent_status_success_logged_out_user() {
		// Arrange: Reusable data.
		$order_id = 123;
		$total    = 12.23;

		// Arrange: Create an order to test with.
		$mock_order = $this->createMock( 'WC_Order' );

		// Arrange: Set a good return value for the order's data store.
		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		// Arrange: Set a good return value for order ID.
		$mock_order
			->method( 'get_id' )
			->willReturn( $order_id );

		// Arrange: Set a good return value for order total.
		$mock_order
			->method( 'get_total' )
			->willReturn( $total );

		// Arrange: Set false as a return value of order's get_user.
		$mock_order
			->method( 'get_user' )
			->willReturn( false );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Arrange: Return a successful response from create_and_confirm_intention().
		$intent = WC_Helper_Intention::create_intention();

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		// Assert: customer_service should still be called with a WP_User object (representing a logged-out user).
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->with( $this->isInstanceOf( WP_User::class ) );

		$charge_request = $this->mock_wcpay_request( Get_Charge::class );

		$charge_request->expects( $this->once() )
			->method( 'set_charge_id' )
			->with( 'ch_mock' );

		$charge_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [ 'balance_transaction' => [ 'exchange_rate' => 0.86 ] ] );

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
		$intent_id   = 'pi_mock';
		$charge_id   = 'ch_mock';
		$customer_id = 'cus_mock';
		$status      = 'requires_capture';
		$order_id    = 123;
		$total       = 12.23;

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

		// Arrange: Set a WP_User object as a return value of order's get_user.
		$mock_order
			->method( 'get_user' )
			->willReturn( wp_get_current_user() );

		// Arrange: Set a good return value for customer ID.
		$this->mock_customer_service->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->willReturn( $customer_id );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Arrange: Return a 'requires_capture' response from create_and_confirm_intention().
		$intent = WC_Helper_Intention::create_intention( [ 'status' => $status ] );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		// Assert: Order has correct charge id meta data.
		// Assert: Order has correct intention status meta data.
		// Assert: Order has correct intent ID.
		// This test is a little brittle because we don't really care about the order
		// in which the different calls are made, but it's not possible to write it
		// otherwise for now.
		// There's an issue open for that here:
		// https://github.com/sebastianbergmann/phpunit/issues/4026.
		$mock_order
			->expects( $this->exactly( 10 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ '_wcpay_mode', WC_Payments::mode()->is_test() ? 'test' : 'prod' ],
				[ '_intent_id', $intent_id ],
				[ '_charge_id', $charge_id ],
				[ '_intention_status', $status ],
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, 'USD' ],
				[ '_wcpay_multi_currency_stripe_exchange_rate', 0.86 ]
			);

		// Assert: The Order_Service is called correctly.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'mark_payment_authorized' )
			->with( $mock_order, $intent_id, $status, $charge_id );

		// Assert: Order has correct transaction ID set.
		$mock_order
			->expects( $this->exactly( 1 ) )
			->method( 'set_transaction_id' )
			->with( $intent_id );

		// Assert: empty_cart() was called.
		$mock_cart
			->expects( $this->once() )
			->method( 'empty_cart' );

		$charge_request = $this->mock_wcpay_request( Get_Charge::class );

		$charge_request->expects( $this->once() )
			->method( 'set_charge_id' )
			->with( 'ch_mock' );

		$charge_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [ 'balance_transaction' => [ 'exchange_rate' => 0.86 ] ] );

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
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		// Arrange: Throw an exception in create_and_confirm_intention.
		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new API_Exception(
						$error_message,
						'resource_missing',
						400
					)
				)
			);
		$this->expectException( 'Exception' );

		// Act: process payment.
		$this->expectException( Exception::class );
		try {
			$this->mock_wcpay_gateway->process_payment( $order->get_id(), false );
		} catch ( Exception $e ) {
			$result_order = wc_get_order( $order->get_id() );

			// Assert: Order status was updated.
			$this->assertEquals( 'failed', $result_order->get_status() );

			// Assert: Order transaction ID was not set.
			$this->assertEquals( '', $result_order->get_transaction_id() );

			// Assert: Order meta was not updated with charge ID, intention status, or intent ID.
			$this->assertEquals( '', $result_order->get_meta( '_intent_id' ) );
			$this->assertEquals( '', $result_order->get_meta( '_charge_id' ) );
			$this->assertEquals( '', $result_order->get_meta( '_intention_status' ) );

			// Assert: No order note was added, besides the status change and failed transaction details.
			$notes = wc_get_order_notes( [ 'order_id' => $result_order->get_id() ] );
			$this->assertCount( 2, $notes );
			$this->assertEquals( 'Order status changed from Pending payment to Failed.', $notes[1]->content );
			$this->assertStringContainsString( 'A payment of &#36;50.00 failed to complete with the following message: Error: No such customer: 123.', strip_tags( $notes[0]->content, '' ) );

			// Assert: A WooCommerce notice was added.
			$this->assertSame( $error_message, $e->getMessage() );

			throw $e;
		}
	}

	public function test_connection_exception_thrown() {
		// Arrange: Reusable data.
		$error_message = 'Test error.';
		$error_notice  = 'There was an error while processing this request. If you continue to see this notice, please contact the admin.';

		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		// Arrange: Throw an exception in create_and_confirm_intention.
		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new Connection_Exception(
						$error_message,
						'wcpay_http_request_failed',
						400
					)
				)
			);
		// Arrange: Prepare for the upcoming exception.
		$this->expectException( 'Exception' );

		// Act: process payment.
		$this->expectException( Exception::class );
		try {
			$this->mock_wcpay_gateway->process_payment( $order->get_id(), false );
		} catch ( Exception $e ) {
			$result_order = wc_get_order( $order->get_id() );

			// Assert: Order status was updated.
			$this->assertEquals( 'failed', $result_order->get_status() );

			// Assert: No order note was added, besides the status change and failed transaction details.
			$notes = wc_get_order_notes( [ 'order_id' => $result_order->get_id() ] );
			$this->assertCount( 2, $notes );
			$this->assertEquals( 'Order status changed from Pending payment to Failed.', $notes[1]->content );
			$this->assertStringContainsString( 'A payment of &#36;50.00 failed to complete with the following message: Test error.', strip_tags( $notes[0]->content, '' ) );

			// Assert: A WooCommerce notice was added.
			$this->assertSame( $error_notice, $e->getMessage() );

			throw $e;
		}
	}

	/**
	 * Tests that the rawte limiter is bumped for certain error codes
	 *
	 * @dataProvider rate_limiter_error_code_provider
	 */
	public function test_failed_transaction_rate_limiter_bumped( $error_code ) {
		$order = WC_Helper_Order::create_order();

		$this->mock_rate_limiter
			->expects( $this->once() )
			->method( 'bump' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		// Arrange: Throw an exception in create_and_confirm_intention.
		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new API_Exception(
						'test error',
						$error_code,
						400,
						'card_error'
					)
				)
			);

		$this->expectException( Exception::class );

		// Act: process payment.
		$this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function rate_limiter_error_code_provider() {
		return [
			[ 'card_declined' ],
			[ 'incorrect_number' ],
			[ 'incorrect_cvc' ],
		];
	}

	public function test_failed_transaction_rate_limiter_is_limited() {
		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		// Arrange: Rate limiter is limited.
		$this->mock_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( true );

		// Arrange: Prepare for the upcoming exception.
		$this->expectException( 'Exception' );

		// Act: process payment.
		$this->expectException( Exception::class );
		try {
			$this->mock_wcpay_gateway->process_payment( $order->get_id(), false );
		} catch ( Exception $e ) {
			$result_order = wc_get_order( $order->get_id() );

			// Assert: Order status was updated.
			$this->assertEquals( 'failed', $result_order->get_status() );

			// Assert: No order note was added, besides the status change and failed transaction details.
			$notes = wc_get_order_notes( [ 'order_id' => $result_order->get_id() ] );
			$this->assertCount( 2, $notes );
			$this->assertEquals( 'Order status changed from Pending payment to Failed.', $notes[1]->content );
			$this->assertStringContainsString( 'A payment of &#36;50.00 failed to complete because of too many failed transactions. A rate limiter was enabled for the user to prevent more attempts temporarily.', strip_tags( $notes[0]->content, '' ) );

			throw $e;
		}
	}

	public function test_bad_request_exception_thrown() {
		$error_message = 'Test error.';
		$error_notice  = 'We\'re not able to process this request. Please refresh the page and try again.';

		$order = WC_Helper_Order::create_order();
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new API_Exception(
						$error_message,
						'wcpay_bad_request',
						400
					)
				)
			);

		// Arrange: Prepare for the upcoming exception.
		$this->expectException( 'Exception' );

		// Act: process payment.
		$this->expectException( Exception::class );
		try {
			$this->mock_wcpay_gateway->process_payment( $order->get_id(), false );
		} catch ( Exception $e ) {
			$result_order = wc_get_order( $order->get_id() );

			// Assert: Order status was updated.
			$this->assertEquals( 'failed', $result_order->get_status() );

			// Assert: No order note was added, besides the status change and failed transaction details.
			$notes = wc_get_order_notes( [ 'order_id' => $result_order->get_id() ] );
			$this->assertCount( 2, $notes );
			$this->assertEquals( 'Order status changed from Pending payment to Failed.', $notes[1]->content );
			$this->assertStringContainsString( "A payment of &#36;50.00 failed to complete with the following message: $error_message", strip_tags( $notes[0]->content, '' ) );

			// Assert: A WooCommerce notice was added.
			$this->assertSame( $error_notice, $e->getMessage() );

			throw $e;
		}
	}

	public function test_incorrect_zip_exception_thrown() {
		$error_message = 'Test error.';
		$error_note    = 'We couldn’t verify the postal code in the billing address. If the issue persists, suggest the customer to reach out to the card issuing bank.';

		$order = WC_Helper_Order::create_order();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will(
				$this->throwException(
					new API_Exception(
						$error_message,
						'incorrect_zip',
						400,
						'card_error'
					)
				)
			);

		// Act: process payment.
		$this->expectException( Exception::class );
		try {
			$this->mock_wcpay_gateway->process_payment( $order->get_id(), false );
		} catch ( Exception $e ) {
			$result_order = wc_get_order( $order->get_id() );

			// Assert: No order note was added, besides the status change and failed transaction details.
			$notes = wc_get_order_notes( [ 'order_id' => $result_order->get_id() ] );

			// Assert: Correct order notes are added.
			$this->assertCount( 2, $notes );
			$this->assertEquals( 'Order status changed from Pending payment to Failed.', $notes[1]->content );
			$this->assertStringContainsString( "A payment of &#36;50.00 failed. $error_note", strip_tags( $notes[0]->content, '' ) );

			throw $e;
		}
	}

	/**
	 * Test processing payment with the status "requires_action".
	 * This is the status returned when the payment requires
	 * further authentication with 3DS.
	 */
	public function test_intent_status_requires_action() {
		// Arrange: Reusable data.
		$intent_id   = 'pi_mock';
		$charge_id   = 'ch_mock';
		$customer_id = 'cus_mock';
		$status      = 'requires_action';
		$secret      = 'cs_mock';
		$order_id    = 123;
		$total       = 12.23;

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

		// Arrange: Set a WP_User object as a return value of order's get_user.
		$mock_order
			->method( 'get_user' )
			->willReturn( wp_get_current_user() );

		// Arrange: Set a good return value for customer ID.
		$this->mock_customer_service->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->willReturn( $customer_id );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Arrange: Return a 'requires_action' response from create_and_confirm_intention().
		$intent = WC_Helper_Intention::create_intention( [ 'status' => $status ] );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		// Assert: Order charge id meta data was updated with `update_meta_data()`.
		// Assert: Order does not have intention status meta data.
		// Assert: Order has correct intent ID.
		// This test is a little brittle because we don't really care about the order
		// in which the different calls are made, but it's not possible to write it
		// otherwise for now.
		// There's an issue open for that here:
		// https://github.com/sebastianbergmann/phpunit/issues/4026.
		$mock_order
			->expects( $this->exactly( 10 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ '_wcpay_mode', WC_Payments::mode()->is_test() ? 'test' : 'prod' ],
				[ '_intent_id', $intent_id ],
				[ '_charge_id', $charge_id ],
				[ '_intention_status', $status ],
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, 'USD' ],
				[ '_wcpay_multi_currency_stripe_exchange_rate', 0.86 ]
			);

		// Assert: The Order_Service is called correctly.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'mark_payment_started' )
			->with( $mock_order, $intent_id, $status, $charge_id );

		// Assert: Order has correct transaction ID set.
		$mock_order
			->expects( $this->exactly( 1 ) )
			->method( 'set_transaction_id' )
			->with( $intent_id );

		// Assert: empty_cart() was not called.
		$mock_cart
			->expects( $this->never() )
			->method( 'empty_cart' );

		$charge_request = $this->mock_wcpay_request( Get_Charge::class );

		$charge_request->expects( $this->once() )
			->method( 'set_charge_id' )
			->with( 'ch_mock' );

		$charge_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [ 'balance_transaction' => [ 'exchange_rate' => 0.86 ] ] );

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

	/**
	 * Test processing free order with the status "requires_action".
	 * This is the status returned when the saved card setup requires
	 * further authentication with 3DS.
	 */
	public function test_setup_intent_status_requires_action() {
		// Arrange: Reusable data.
		$intent_id   = 'pi_mock';
		$customer_id = 'cus_mock';
		$status      = 'requires_action';
		$secret      = 'cs_mock';
		$order_id    = 123;
		$total       = 0;
		$currency    = 'USD';

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

		// Arrange: Set currency for order total.
		$mock_order
			->method( 'get_currency' )
			->willReturn( $currency );

		// Arrange: Set a WP_User object as a return value of order's get_user.
		$mock_order
			->method( 'get_user' )
			->willReturn( wp_get_current_user() );

		// Arrange: Set a good return value for customer ID.
		$this->mock_customer_service->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->willReturn( $customer_id );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Arrange: Return a 'requires_action' response from create_and_confirm_setup_intent().
		$intent = [
			'id'            => $intent_id,
			'status'        => $status,
			'client_secret' => $secret,
			'next_action'   => [],
		];
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'create_and_confirm_setup_intent' )
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
			->expects( $this->exactly( 9 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ '_wcpay_mode', WC_Payments::mode()->is_test() ? 'test' : 'prod' ],
				[ '_intent_id', $intent_id ],
				[ '_charge_id', '' ],
				[ '_intention_status', $status ],
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, 'USD' ]
			);

		// Assert: Order status was not updated.
		$mock_order
			->expects( $this->never() )
			->method( 'set_status' );

		// Assert: No order note added because payment not needed.
		$mock_order
			->expects( $this->never() )
			->method( 'add_order_note' );

		// Assert: Order has correct transaction ID set.
		$mock_order
			->expects( $this->exactly( 1 ) )
			->method( 'set_transaction_id' )
			->with( $intent_id );

		// Assert: empty_cart() was not called.
		$mock_cart
			->expects( $this->never() )
			->method( 'empty_cart' );

		// Act: prepare payment information.
		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $mock_order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_information->must_save_payment_method_to_store();

		// Act: process payment.
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals(
			'#wcpay-confirm-si:' . $order_id . ':' . $secret . ':' . wp_create_nonce( 'wcpay_update_order_status_nonce' ),
			$result['redirect']
		);
	}

	public function test_saved_card_at_checkout() {
		$order = WC_Helper_Order::create_order();

		$intent = WC_Helper_Intention::create_intention();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( $intent->get_payment_method_id(), $order->get_user() )
			->will( $this->returnValue( new WC_Payment_Token_CC() ) );

		$_POST['wc-woocommerce_payments-new-payment-method'] = 'true';

		$this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_not_saved_card_at_checkout() {
		$order = WC_Helper_Order::create_order();

		$intent = WC_Helper_Intention::create_intention();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		$result = $this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_does_not_update_new_payment_method() {
		$order = WC_Helper_Order::create_order();

		$intent = WC_Helper_Intention::create_intention();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'update_payment_method_with_billing_details_from_order' );

		$this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_updates_payment_method_billing_details() {
		$_POST = $this->setup_saved_payment_method();

		$order = WC_Helper_Order::create_order();

		$order_id = $order->get_id();

		$intent = WC_Helper_Intention::create_intention();

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->anything(),
				WC_Payment_Gateway_WCPay::UPDATE_SAVED_PAYMENT_METHOD,
				[
					'payment_method' => 'pm_mock',
					'order_id'       => $order_id,
					'is_test_mode'   => false,
				]
			);

		$this->mock_wcpay_gateway->process_payment( $order_id );
	}

	public function test_updates_customer_with_order_data() {
		$customer_id = 'cus_mock';

		// Arrange: Create an order to test with.
		$mock_order = $this->createMock( 'WC_Order' );

		// Arrange: Set a good return value for order ID.
		$mock_order
			->method( 'get_id' )
			->willReturn( 123 );

		// Arrange: Set a WP_User object as a return value of order's get_user.
		$mock_order
			->method( 'get_user' )
			->willReturn( wp_get_current_user() );

		// Arrange: Set a good return value for order total.
		$mock_order
			->method( 'get_total' )
			->willReturn( 0 );

		// Arrange: WC_Payments_Customer_Service returns a valid customer ID.
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( $customer_id );

		// Assert: UPDATE_CUSTOMER_WITH_ORDER_DATA job scheduled correctly.
		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' )
			->with(
				$this->anything(),
				WC_Payment_Gateway_WCPay::UPDATE_CUSTOMER_WITH_ORDER_DATA,
				[
					'order_id'     => $mock_order->get_id(),
					'customer_id'  => $customer_id,
					'is_test_mode' => false,
					'is_woopay'    => false,
				]
			);

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $mock_order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Act: process a successful payment.
		$this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );
	}

	public function test_save_payment_method_to_platform() {
		$order = WC_Helper_Order::create_order();

		$intent = WC_Helper_Intention::create_intention();

		$_POST['save_user_in_platform_checkout'] = 'true';

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );
		$this->mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_process_payment_using_platform_payment_method_adds_platform_payment_method_flag_to_request() {
		// Arrange: Create an order to test with.
		$mock_order = $this->createMock( 'WC_Order' );

		// Arrange: Set a good return value for the order's data store.
		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		// Arrange: Set a good return value for order ID.
		$mock_order
			->method( 'get_id' )
			->willReturn( 123 );

		// Arrange: Set a good return value for order total.
		$mock_order
			->method( 'get_total' )
			->willReturn( 100 );

		$mock_order
			->method( 'get_order_number' )
			->willReturn( 'order_number' );

		// Arrange: Set a WP_User object as a return value of order's get_user.
		$mock_order
			->method( 'get_user' )
			->willReturn( wp_get_current_user() );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Arrange: Payment method was created with platform.
		$this->mock_wcpay_gateway
			->expects( $this->any() )
			->method( 'should_use_stripe_platform_on_checkout_page' )
			->willReturn( true );

		$_POST['wcpay-is-platform-payment-method'] = 1;

		// Arrange: Return a successful response from create_and_confirm_intention().
		$intent = WC_Helper_Intention::create_intention();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$charge_request = $this->mock_wcpay_request( Get_Charge::class );

		$charge_request->expects( $this->once() )
			->method( 'set_charge_id' )
			->with( 'ch_mock' );

		$charge_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [ 'id' => 'ch_mock' ] );

		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $mock_order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Act: process a successful payment.
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_process_payment_for_subscription_in_woopay_adds_subscription_flag_to_request() {

		$customer = 'cus_12345';

		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		// This meta is added to the order by WooPay.
		$order->add_meta_data( '_woopay_has_subscription', '1' );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Arrange: Return a successful response from create_and_confirm_intention().
		$intent = WC_Helper_Intention::create_intention();
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( $customer ) );
		// Assert: API is called with additional flag.
		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( 5000 )
			->willReturn( $request );

		$request->expects( $this->once() )
			->method( 'set_payment_method' )
			->with( 'pm_mock' )
			->willReturn( $request );

		$request->expects( $this->once() )
			->method( 'set_customer' )
			->with( $customer )
			->willReturn( $request );

		$request->expects( $this->once() )
			->method( 'set_capture_method' )
			->with( false )
			->willReturn( $request );

		$request->expects( $this->once() )
			->method( 'set_metadata' )
			->with(
				$this->callback(
					function( $metadata ) {
						$required_keys = [ 'customer_name', 'customer_email', 'site_url', 'order_id', 'order_number', 'order_key', 'payment_type' ];
						foreach ( $required_keys as $key ) {
							if ( ! array_key_exists( $key, $metadata ) ) {
								return false;
							}
						}
						return true;
					}
				)
			)
				->willReturn( $request );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );
		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Act: process a successful payment.
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_process_payment_from_woopay_adds_meta_to_oder() {
		// Arrange: Set request value to mimic a payment through WooPay.
		$_POST['is_woopay'] = true;

		// Arrange: Create an order to test with.
		$mock_order = $this->createMock( 'WC_Order' );

		// Arrange: Set a good return value for the order's data store.
		$mock_order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		// Arrange: Set a good return value for order ID.
		$mock_order
			->method( 'get_id' )
			->willReturn( 123 );

		// Arrange: Set a good return value for order total.
		$mock_order
			->method( 'get_total' )
			->willReturn( 100 );

		// Arrange: Set a WP_User object as a return value of order's get_user.
		$mock_order
			->method( 'get_user' )
			->willReturn( wp_get_current_user() );

		$mock_order
			->method( 'get_order_number' )
			->willReturn( 'order_number_1' );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Assert: Order gets the 'is_woopay' meta data added.
		$mock_order
			->expects( $this->any() )
			->method( 'add_meta_data' )
			->with( 'is_woopay', true );

		// Arrange: Return a successful response from create_and_confirm_intention().
		$intent = WC_Helper_Intention::create_intention();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( 'cus_12345' ) );
		// Assert: API is called with additional flag.
		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$charge_request = $this->mock_wcpay_request( Get_Charge::class );

		$charge_request->expects( $this->once() )
			->method( 'set_charge_id' )
			->with( 'ch_mock' );

		$charge_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( [ 'id' => 'ch_mock' ] );
		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $mock_order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Act: process a successful payment.
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_process_payment_for_subscription_from_woopay_does_not_save_token_if_exists() {
		$subscription_payment_method_id = 'pm_subscription_mock';

		// Arrange: Set request value to mimic a payment through WooPay.
		$_POST['is_woopay'] = true;

		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		// Arrange: Make the order contain a subscription.
		$this->mock_wcs_order_contains_subscription( true );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Arrange: Add a payment method to the user.
		$token = WC_Helper_Token::create_token( $subscription_payment_method_id, $order->get_user_id() );

		// Arrange: Make the payment method selected in WooPay to be the same one the user has stored.
		$intent = WC_Helper_Intention::create_intention( [ 'payment_method_id' => $subscription_payment_method_id ] );

		// Arrange: Return a successful response from create_and_confirm_intention().
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		// Arrange: Throw an exception in create_and_confirm_intention.
		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_information->must_save_payment_method_to_store();

		// Assert: The payment method is not added to the user.
		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		// Act: process a successful payment.
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_process_payment_for_subscription_from_woopay_save_token_if_does_not_exist() {

		// Arrange: Set request value to mimic a payment through WooPay.
		$_POST['is_woopay'] = true;

		// Arrange: Create an order to test with.
		$order = WC_Helper_Order::create_order();

		// Arrange: Make the order contain a subscription.
		$this->mock_wcs_order_contains_subscription( true );
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_mock' );

		// Arrange: Create a mock cart.
		$mock_cart = $this->createMock( 'WC_Cart' );

		// Arrange: Add a payment method to the user.
		$token = WC_Helper_Token::create_token( 'pm_existing_mock', $order->get_user_id() );

		// Arrange: Make the payment method selected in WooPay to be different from the onethe user has stored.
		$intent = WC_Helper_Intention::create_intention( [ 'payment_method_id' => 'pm_new_mock' ] );

		// Arrange: Return a successful response from create_and_confirm_intention().
		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$payment_information = WCPay\Payment_Information::from_payment_request( $_POST, $order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_information->must_save_payment_method_to_store();

		// Assert: The payment method is added to the user.
		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( $intent->get_payment_method_id(), $order->get_user() )
			->will( $this->returnValue( new WC_Payment_Token_CC() ) );

		// Act: process a successful payment.
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
	}

	private function mock_wcs_order_contains_subscription( $value ) {
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function ( $order ) use ( $value ) {
				return $value;
			}
		);
	}

	private function setup_saved_payment_method() {
		$token = WC_Helper_Token::create_token( 'pm_mock' );

		return [
			'payment_method' => WC_Payment_Gateway_WCPay::GATEWAY_ID,
			'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' => (string) $token->get_id(),
		];
	}
}

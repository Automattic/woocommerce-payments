<?php
/**
 * Class Sepa_Payment_Gateway_Test
 * @package WCPay\Payment_Gateways\Tests
 */

namespace WCPay\Payment_Methods;

use DateTime;
use PHPUnit_Framework_MockObject_MockObject;
use WC_Payment_Gateway_WCPay_Subscriptions_Compat;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_API_Intention;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WC_Payments_Utils;
use WCPay\Constants\Payment_Capture_Type;
use WCPay\Constants\Payment_Initiated_By;
use WCPay\Constants\Payment_Type;
use WCPay\Payment_Information;
use WP_UnitTestCase;
use WP_User;

// Need to use WC_Mock_Data_Store.
require_once dirname( __FILE__, 2 ) . '/helpers/class-wc-mock-wc-data-store.php';

/**
 * WCPay\Payment_Gateway\Sepa Unit tests
 */
class Sepa_Payment_Gateway_Test extends WP_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var Sepa_Payment_Gateway
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

	public function setUp() {
		parent::setUp();

		// Arrange: Mock WC_Payments_API_Client so we can configure the
		// return value of create_and_confirm_intention().
		// Note that we cannot use createStub here since it's not defined in PHPUnit 6.5.
		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( [ 'create_and_confirm_intention', 'get_payment_method', 'is_server_connected', 'get_charge' ] )
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

		// Arrange: Mock WC_Payments_Action_Scheduler_Service so its methods aren't called directly.
		$this->mock_action_scheduler_service = $this->getMockBuilder( 'WC_Payments_Action_Scheduler_Service' )
			->disableOriginalConstructor()
			->getMock();

		// Arrange: Mock WC_Payment_Gateway_WCPay so that some of its methods can be
		// mocked, and their return values can be used for testing.
		$this->mock_wcpay_gateway = $this->getMockBuilder( Sepa_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
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
			'payment_method'       => 'woocommerce_payments_sepa',
		];
	}

	/**
	 * Test processing payment with the status 'succeeded'.
	 */
	public function test_intent_status_success_logged_out_user() {
		// Arrange: Reusable data.
		$intent_id = 'pi_123';
		$charge_id = 'ch_123';
		$status    = 'succeeded';
		$secret    = 'client_secret_123';
		$order_id  = 123;
		$total     = 12.23;

		// Arrange: Create an order to test with.
		$mock_order = $this->createMock( 'WC_Order' );

		// Arrange: Set a order data store.
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
		$intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			'eur',
			'cus_12345',
			'pm_12345',
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

		// Assert: customer_service should still be called with a WP_User object (representing a logged-out user).
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->with( $this->isInstanceOf( WP_User::class ) );

		// Act: process a successful payment.
		$payment_information = Payment_Information::from_payment_request( $_POST, $mock_order ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_charge' )
			->willReturn( [ 'balance_transaction' => [ 'exchange_rate' => 0.86 ] ] );
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $this->return_url, $result['redirect'] );
	}

	/**
	 * Test processing payment with the status "requires_capture".
	 */
	public function test_intent_status_requires_capture() {
		// Arrange: Reusable data.
		$intent_id   = 'pi_123';
		$charge_id   = 'ch_123';
		$customer_id = 'cu_123';
		$status      = 'processing'; // This is the status SEPA payments have.
		$secret      = 'client_secret_123';
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
		$intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			'eur',
			$customer_id,
			'pm_12345',
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
			->expects( $this->exactly( 9 ) )
			->method( 'update_meta_data' )
			->withConsecutive(
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ '_intent_id', $intent_id ],
				[ '_charge_id', $charge_id ],
				[ '_intention_status', $status ],
				[ '_payment_method_id', 'pm_mock' ],
				[ '_stripe_customer_id', $customer_id ],
				[ WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, 'EUR' ],
				[ '_wcpay_multi_currency_stripe_exchange_rate', 0.86 ]
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
		$payment_information = Payment_Information::from_payment_request( $_POST, $mock_order, Payment_Type::SINGLE(), Payment_Initiated_By::CUSTOMER(), Payment_Capture_Type::MANUAL() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_charge' )
			->willReturn( [ 'balance_transaction' => [ 'exchange_rate' => 0.86 ] ] );
		$result = $this->mock_wcpay_gateway->process_payment_for_order( $mock_cart, $payment_information );

		// Assert: Returning correct array.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $this->return_url, $result['redirect'] );
	}
}

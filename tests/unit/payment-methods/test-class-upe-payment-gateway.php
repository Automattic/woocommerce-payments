<?php
/**
 * Class UPE_Payment_Gateway_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Payment_Methods;

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Connection_Exception;

use WCPay\Logger;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WC_Payments;
use WC_Helper_Order;
use WC_Payments_Utils;
use WP_UnitTestCase;
use WP_User;
use Exception;

/**
 * UPE_Payment_Gateway unit tests
 */
class UPE_Payment_Gateway_Test extends WP_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var UPE_Payment_Gateway
	 */
	private $mock_upe_gateway;

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

	/**
	 * Mocked object to be used as response from create_payment_intent()
	 *
	 * @var array
	 */
	private $mock_payment_intent = [
		'id'            => 'pi_mock',
		'client_secret' => 'cs_mock',
	];

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
			->setMethods( [ 'create_intention', 'get_payment_method', 'is_server_connected' ] )
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

		// Arrange: Mock UPE_Payment_Gateway so that some of its methods can be
		// mocked, and their return values can be used for testing.
		$this->mock_upe_gateway = $this->getMockBuilder( UPE_Payment_Gateway::class )
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
					'create_payment_intent',
					'get_return_url',
				]
			)
			->getMock();

		// Arrange: Set the return value of get_return_url() so it can be used in a test later.
		$this->mock_upe_gateway
			->expects( $this->any() )
			->method( 'get_return_url' )
			->will(
				$this->returnValue( $this->return_url )
			);
		$this->mock_upe_gateway
			->expects( $this->any() )
			->method( 'create_payment_intent' )
			->will(
				$this->returnValue( $this->mock_payment_intent )
			);

		// Arrange: Define a $_POST array which includes the payment method,
		// so that get_payment_method_from_request() does not throw error.
		$_POST = [
			'wcpay-payment-method' => 'pm_mock',
		];
	}

	public function test_payment_fields_outputs_fields() {
		$this->mock_upe_gateway->payment_fields();

		$this->expectOutputRegex( '/<div id="wcpay-upe-element"><\/div>/' );
	}

	public function test_process_payment_returns_correct_redirect_url() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );

		$this->assertEquals( 'success', $result['result'] );
		$this->assertRegExp( "/order_id=$order_id/", $result['redirect_url'] );
		$this->assertRegExp( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertRegExp( '/save_payment_method=no/', $result['redirect_url'] );
	}

	public function test_process_payment_passes_save_payment_method() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		$gateway_id                   = UPE_Payment_Gateway::GATEWAY_ID;
		$save_payment_param           = "wc-$gateway_id-new-payment-method";
		$_POST[ $save_payment_param ] = 'yes';

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );

		unset( $_POST[ $save_payment_param ] );// phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->assertEquals( 'success', $result['result'] );
		$this->assertRegExp( "/order_id=$order_id/", $result['redirect_url'] );
		$this->assertRegExp( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertRegExp( '/save_payment_method=yes/', $result['redirect_url'] );
	}

}

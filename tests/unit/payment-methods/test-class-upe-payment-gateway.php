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
use WCPay\Exceptions\Process_Payment_Exception;

use WCPay\Logger;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_API_Intention;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WC_Payments;
use WC_Helper_Order;
use WC_Helper_Token;
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
			->setMethods( [ 'create_intention', 'get_intent', 'get_payment_method', 'is_server_connected' ] )
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
			->setMethods( [ 'add_payment_method_to_user' ] )
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
					'manage_customer_details_for_order',
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

	public function test_process_redirect_payment_intent_processing() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$save_payment_method = 'no';
		$user                = wp_get_current_user();
		$intent_status       = 'processing';
		$charge_id           = 'ch_mock';
		$client_secret       = 'cs_mock';
		$customer_id         = 'cus_mock';
		$intent_id           = 'pi_mock';
		$payment_method_id   = 'pm_mock';

		$_GET['payment_intent']      = $intent_id;
		$_GET['save_payment_method'] = $save_payment_method;

		$payment_intent = new WC_Payments_API_Intention(
			$intent_id,
			$order->get_total(),
			$order->get_currency(),
			$customer_id,
			$payment_method_id,
			new \DateTime( 'NOW' ),
			$intent_status,
			$charge_id,
			$client_secret
		);

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_intent' )
			->with( $intent_id )
			->will(
				$this->returnValue( $payment_intent )
			);

		$this->mock_upe_gateway->process_redirect_payment( $order_id );

		$result_order = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertContains( 'authorized', $note->content );
		$this->assertEquals( $result_order->get_meta( '_intent_id', true ), $intent_id );
		$this->assertEquals( $result_order->get_meta( '_charge_id', true ), $charge_id );
		$this->assertEquals( $result_order->get_meta( '_intention_status', true ), $intent_status );
		$this->assertEquals( $result_order->get_meta( '_payment_method_id', true ), $payment_method_id );
		$this->assertEquals( $result_order->get_meta( '_stripe_customer_id', true ), $customer_id );
		$this->assertEquals( $result_order->get_status(), 'on-hold' );
	}

	public function test_process_redirect_payment_intent_succeded() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$save_payment_method = 'no';
		$user                = wp_get_current_user();
		$intent_status       = 'succeeded';
		$charge_id           = 'ch_mock';
		$client_secret       = 'cs_mock';
		$customer_id         = 'cus_mock';
		$intent_id           = 'pi_mock';
		$payment_method_id   = 'pm_mock';

		$_GET['payment_intent']      = $intent_id;
		$_GET['save_payment_method'] = $save_payment_method;

		$payment_intent = new WC_Payments_API_Intention(
			$intent_id,
			$order->get_total(),
			$order->get_currency(),
			$customer_id,
			$payment_method_id,
			new \DateTime( 'NOW' ),
			$intent_status,
			$charge_id,
			$client_secret
		);

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_intent' )
			->with( $intent_id )
			->will(
				$this->returnValue( $payment_intent )
			);

		$this->mock_upe_gateway->process_redirect_payment( $order_id );

		$result_order = wc_get_order( $order_id );

		$this->assertEquals( $result_order->get_meta( '_intent_id', true ), $intent_id );
		$this->assertEquals( $result_order->get_meta( '_charge_id', true ), $charge_id );
		$this->assertEquals( $result_order->get_meta( '_intention_status', true ), $intent_status );
		$this->assertEquals( $result_order->get_meta( '_payment_method_id', true ), $payment_method_id );
		$this->assertEquals( $result_order->get_meta( '_stripe_customer_id', true ), $customer_id );
		$this->assertEquals( $result_order->get_status(), 'processing' );
	}

	public function test_process_redirect_payment_save_payment_token() {
		$order               = WC_Helper_Order::create_order();
		$token               = WC_Helper_Token::create_token( 'pm_mock' );
		$order_id            = $order->get_id();
		$save_payment_method = 'yes';
		$user                = wp_get_current_user();
		$intent_status       = 'processing';
		$charge_id           = 'ch_mock';
		$client_secret       = 'cs_mock';
		$customer_id         = 'cus_mock';
		$intent_id           = 'pi_mock';
		$payment_method_id   = 'pm_mock';

		$_GET['payment_intent']      = $intent_id;
		$_GET['save_payment_method'] = $save_payment_method;

		$payment_intent = new WC_Payments_API_Intention(
			$intent_id,
			$order->get_total(),
			$order->get_currency(),
			$customer_id,
			$payment_method_id,
			new \DateTime( 'NOW' ),
			$intent_status,
			$charge_id,
			$client_secret
		);

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_intent' )
			->with( $intent_id )
			->will(
				$this->returnValue( $payment_intent )
			);

		$this->mock_token_service->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->will(
				$this->returnValue( $token )
			);

		$this->mock_upe_gateway->process_redirect_payment( $order_id );

		$result_order = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertContains( 'authorized', $note->content );
		$this->assertEquals( $result_order->get_meta( '_intent_id', true ), $intent_id );
		$this->assertEquals( $result_order->get_meta( '_charge_id', true ), $charge_id );
		$this->assertEquals( $result_order->get_meta( '_intention_status', true ), $intent_status );
		$this->assertEquals( $result_order->get_meta( '_payment_method_id', true ), $payment_method_id );
		$this->assertEquals( $result_order->get_meta( '_stripe_customer_id', true ), $customer_id );
		$this->assertEquals( $result_order->get_status(), 'on-hold' );
		$this->assertEquals( count( $result_order->get_payment_tokens() ), 1 );
	}

}

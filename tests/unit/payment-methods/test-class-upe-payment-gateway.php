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
use WCPay\Constants\Payment_Type;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_API_Intention;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WC_Payments;
use WC_Customer;
use WC_Helper_Order;
use WC_Helper_Token;
use WC_Payments_Utils;
use WC_Subscriptions;
use WC_Subscriptions_Cart;
use WP_UnitTestCase;
use WP_User;
use Exception;

/**
 * Overriding global function within namespace for testing
 */
function get_woocommerce_currency() {
	return UPE_Payment_Gateway_Test::$mock_site_currency ? UPE_Payment_Gateway_Test::$mock_site_currency : \get_woocommerce_currency();
}

/**
 * UPE_Payment_Gateway unit tests
 */
class UPE_Payment_Gateway_Test extends WP_UnitTestCase {

	/**
	 * Mock site currency string
	 *
	 * @var string
	 */
	public static $mock_site_currency = '';

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
	 * Array of mock UPE payment methods.
	 *
	 * @var array
	 */
	private $mock_payment_methods;

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
	 * Mocked object to be used as response from process_payment_using_saved_method()
	 *
	 * @var array
	 */
	private $mock_payment_result = [
		'result'         => 'success',
		'payment_needed' => true,
		'redirect'       => 'testURL/key=mock_order_key',
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
			->setMethods(
				[
					'create_intention',
					'create_setup_intention',
					'update_intention',
					'get_intent',
					'get_setup_intent',
					'get_payment_method',
					'is_server_connected',
					'get_charge',
				]
			)
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

		$this->mock_payment_methods = [];
		$payment_method_classes     = [
			CC_Payment_Method::class,
			Giropay_Payment_Method::class,
			Sofort_Payment_Method::class,
			Ideal_Payment_Method::class,
		];
		foreach ( $payment_method_classes as $payment_method_class ) {
			$mock_payment_method = $this->getMockBuilder( $payment_method_class )
				->setConstructorArgs( [ $this->mock_token_service ] )
				->setMethods( [ 'is_subscription_item_in_cart' ] )
				->getMock();
			$this->mock_payment_methods[ $mock_payment_method->get_id() ] = $mock_payment_method;
		}

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
					$this->mock_payment_methods,
				]
			)
			->setMethods(
				[
					'get_return_url',
					'manage_customer_details_for_order',
					'process_payment_using_saved_method',
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
			->method( 'process_payment_using_saved_method' )
			->will(
				$this->returnValue( $this->mock_payment_result )
			);

		// Arrange: Define a $_POST array which includes the payment method,
		// so that get_payment_method_from_request() does not throw error.
		$_POST = [
			'wcpay-payment-method' => 'pm_mock',
		];
	}

	public function test_payment_fields_outputs_fields() {
		$this->set_cart_contains_subscription_items( false );
		$this->mock_upe_gateway->payment_fields();

		$this->expectOutputRegex( '/<div id="wcpay-upe-element"><\/div>/' );
	}

	public function test_update_payment_intent_adds_customer_save_payment_and_level3_data() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$product_item        = current( $order->get_items( 'line_item' ) );
		$intent_id           = 'pi_mock';
		$user                = '';
		$customer_id         = 'cus_12345';
		$save_payment_method = true;

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_intention' )
			->with(
				'pi_mock',
				5000,
				'usd',
				true,
				'cus_12345',
				[
					'customer_name'  => 'Jeroen Sormani',
					'customer_email' => 'admin@example.org',
					'site_url'       => 'http://example.org',
					'order_id'       => $order_id,
					'order_key'      => $order->get_order_key(),
					'payment_type'   => Payment_Type::SINGLE(),
				],
				[
					'merchant_reference' => (string) $order_id,
					'customer_reference' => (string) $order_id,
					'shipping_amount'    => 1000.0,
					'line_items'         => [
						(object) [
							'product_code'        => 30,
							'product_description' => 'Beanie with Logo',
							'unit_cost'           => 1800,
							'quantity'            => 1,
							'tax_amount'          => 270,
							'discount_amount'     => 0,
							'product_code'        => $product_item->get_product_id(),
							'product_description' => 'Dummy Product',
							'unit_cost'           => 1000.0,
							'quantity'            => 4,
							'tax_amount'          => 0.0,
							'discount_amount'     => 0.0,
						],
					],
				]
			)
			->willReturn(
				[
					'sucess' => 'true',
				]
			);

		$result = $this->mock_upe_gateway->update_payment_intent( $intent_id, $order_id, $save_payment_method );
	}

	public function test_update_payment_intent_with_selected_upe_payment_method() {
		$order                     = WC_Helper_Order::create_order();
		$order_id                  = $order->get_id();
		$product_item              = current( $order->get_items( 'line_item' ) );
		$intent_id                 = 'pi_mock';
		$user                      = '';
		$customer_id               = 'cus_12345';
		$save_payment_method       = true;
		$selected_upe_payment_type = 'giropay';

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_intention' )
			->with(
				'pi_mock',
				5000,
				'usd',
				true,
				'cus_12345',
				[
					'customer_name'  => 'Jeroen Sormani',
					'customer_email' => 'admin@example.org',
					'site_url'       => 'http://example.org',
					'order_id'       => $order_id,
					'order_key'      => $order->get_order_key(),
					'payment_type'   => Payment_Type::SINGLE(),
				],
				[
					'merchant_reference' => (string) $order_id,
					'shipping_amount'    => 1000.0,
					'line_items'         => [
						(object) [
							'product_code'        => 30,
							'product_description' => 'Beanie with Logo',
							'unit_cost'           => 1800,
							'quantity'            => 1,
							'tax_amount'          => 270,
							'discount_amount'     => 0,
							'product_code'        => $product_item->get_product_id(),
							'product_description' => 'Dummy Product',
							'unit_cost'           => 1000.0,
							'quantity'            => 4,
							'tax_amount'          => 0.0,
							'discount_amount'     => 0.0,
						],
					],
					'customer_reference' => (string) $order_id,
				],
				'giropay'
			)
			->willReturn(
				[
					'sucess' => 'true',
				]
			);

		$result = $this->mock_upe_gateway->update_payment_intent( $intent_id, $order_id, $save_payment_method, $selected_upe_payment_type );
	}

	public function test_create_payment_intent_uses_order_amount_if_order() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = new WC_Payments_API_Intention( 'pi_mock', 5000, 'usd', null, null, new \DateTime(), 'requires_payment_method', null, 'client_secret_123' );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_intention' )
			->with( 5000, 'usd', [ 'card' ] )
			->willReturn( $intent );
		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->create_payment_intent( $order_id );
	}

	public function test_create_setup_intent_existing_customer() {
		$_POST = [ 'wcpay-payment-method' => 'pm_mock' ];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( 'cus_12345' ) );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_setup_intention' )
			->with( 'cus_12345', [ 'card' ] )
			->willReturn(
				[
					'id'            => 'seti_mock',
					'client_secret' => 'client_secret_mock',
				]
			);

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->create_setup_intent();

		$this->assertEquals( 'seti_mock', $result['id'] );
		$this->assertEquals( 'client_secret_mock', $result['client_secret'] );
	}

	public function test_create_setup_intent_no_customer() {
		$_POST = [ 'wcpay-payment-method' => 'pm_mock' ];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( null ) );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->will( $this->returnValue( 'cus_12346' ) );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_setup_intention' )
			->with( 'cus_12346', [ 'card' ] )
			->willReturn(
				[
					'id'            => 'seti_mock',
					'client_secret' => 'client_secret_mock',
				]
			);

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->create_setup_intent();

		$this->assertEquals( 'seti_mock', $result['id'] );
		$this->assertEquals( 'client_secret_mock', $result['client_secret'] );
	}

	public function test_process_payment_returns_correct_redirect_url() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );

		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( true, $result['payment_needed'] );
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

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );

		unset( $_POST[ $save_payment_param ] );// phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->assertEquals( 'success', $result['result'] );
		$this->assertRegExp( "/order_id=$order_id/", $result['redirect_url'] );
		$this->assertRegExp( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertRegExp( '/save_payment_method=yes/', $result['redirect_url'] );
	}

	public function test_process_payment_returns_correct_redirect_when_using_saved_payment() {
		$order = WC_Helper_Order::create_order();
		$_POST = $this->setup_saved_payment_method();

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );
		$this->assertEquals( 'success', $result['result'] );
		$this->assertRegExp( '/key=mock_order_key/', $result['redirect'] );
	}

	public function test_process_redirect_payment_intent_processing() {
		$order                  = WC_Helper_Order::create_order();
		$order_id               = $order->get_id();
		$save_payment_method    = false;
		$user                   = wp_get_current_user();
		$intent_status          = 'processing';
		$charge_id              = 'ch_mock';
		$client_secret          = 'cs_mock';
		$customer_id            = 'cus_mock';
		$intent_id              = 'pi_mock';
		$payment_method_id      = 'pm_mock';
		$payment_method_details = [
			'type' => 'card',
			'card' => [
				'network' => 'visa',
				'funding' => 'credit',
			],
		];

		$payment_intent = new WC_Payments_API_Intention(
			$intent_id,
			$order->get_total(),
			$order->get_currency(),
			$customer_id,
			$payment_method_id,
			new \DateTime( 'NOW' ),
			$intent_status,
			$charge_id,
			$client_secret,
			[],
			[],
			$payment_method_details
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

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway->process_redirect_payment( $order_id, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertContains( 'authorized', $note->content );
		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $charge_id, $result_order->get_meta( '_charge_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( 'on-hold', $result_order->get_status() );
	}

	public function test_process_redirect_payment_intent_succeded() {
		$order                  = WC_Helper_Order::create_order();
		$order_id               = $order->get_id();
		$save_payment_method    = false;
		$user                   = wp_get_current_user();
		$intent_status          = 'succeeded';
		$charge_id              = 'ch_mock';
		$client_secret          = 'cs_mock';
		$customer_id            = 'cus_mock';
		$intent_id              = 'pi_mock';
		$payment_method_id      = 'pm_mock';
		$payment_method_details = [
			'type' => 'card',
			'card' => [
				'network' => 'visa',
				'funding' => 'credit',
			],
		];

		$payment_intent = new WC_Payments_API_Intention(
			$intent_id,
			$order->get_total(),
			$order->get_currency(),
			$customer_id,
			$payment_method_id,
			new \DateTime( 'NOW' ),
			$intent_status,
			$charge_id,
			$client_secret,
			[],
			[],
			$payment_method_details
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

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway->process_redirect_payment( $order_id, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );

		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $charge_id, $result_order->get_meta( '_charge_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( 'processing', $result_order->get_status() );
	}

	public function test_process_redirect_setup_intent_succeded() {
		$order                  = WC_Helper_Order::create_order();
		$order_id               = $order->get_id();
		$save_payment_method    = true;
		$user                   = wp_get_current_user();
		$intent_status          = 'succeeded';
		$client_secret          = 'cs_mock';
		$customer_id            = 'cus_mock';
		$intent_id              = 'si_mock';
		$payment_method_id      = 'pm_mock';
		$token                  = WC_Helper_Token::create_token( $payment_method_id );
		$payment_method_details = [
			'type' => 'card',
			'card' => [
				'network' => 'visa',
				'funding' => 'credit',
			],
		];

		$order->set_shipping_total( 0 );
		$order->set_shipping_tax( 0 );
		$order->set_cart_tax( 0 );
		$order->set_total( 0 );
		$order->save();

		$setup_intent = [
			'client_secret'          => $client_secret,
			'status'                 => $intent_status,
			'payment_method'         => $payment_method_id,
			'payment_method_options' => [
				'card' => [
					'request_three_d_secure' => 'automatic',
				],
			],
			'last_setup_error'       => [],
		];

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_setup_intent' )
			->with( $intent_id )
			->will(
				$this->returnValue( $setup_intent )
			);

		$this->mock_token_service->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->will(
				$this->returnValue( $token )
			);

		$this->set_cart_contains_subscription_items( true );

		$this->mock_upe_gateway->process_redirect_payment( $order_id, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );

		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( 'processing', $result_order->get_status() );
		$this->assertEquals( 1, count( $result_order->get_payment_tokens() ) );
	}

	public function test_process_redirect_payment_save_payment_token() {
		$order                  = WC_Helper_Order::create_order();
		$order_id               = $order->get_id();
		$save_payment_method    = true;
		$user                   = wp_get_current_user();
		$intent_status          = 'processing';
		$charge_id              = 'ch_mock';
		$client_secret          = 'cs_mock';
		$customer_id            = 'cus_mock';
		$intent_id              = 'pi_mock';
		$payment_method_id      = 'pm_mock';
		$token                  = WC_Helper_Token::create_token( $payment_method_id );
		$payment_method_details = [
			'type' => 'card',
			'card' => [
				'network' => 'visa',
				'funding' => 'credit',
			],
		];

		$payment_intent = new WC_Payments_API_Intention(
			$intent_id,
			$order->get_total(),
			$order->get_currency(),
			$customer_id,
			$payment_method_id,
			new \DateTime( 'NOW' ),
			$intent_status,
			$charge_id,
			$client_secret,
			[],
			[],
			$payment_method_details
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

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway->process_redirect_payment( $order_id, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertContains( 'authorized', $note->content );
		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $charge_id, $result_order->get_meta( '_charge_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( 'on-hold', $result_order->get_status() );
		$this->assertEquals( 1, count( $result_order->get_payment_tokens() ) );
	}

	public function test_correct_payment_method_title_for_order() {
		$order = WC_Helper_Order::create_order();

		$visa_credit_details       = [
			'type' => 'card',
			'card' => [
				'network' => 'visa',
				'funding' => 'credit',
			],
		];
		$visa_debit_details        = [
			'type' => 'card',
			'card' => [
				'network' => 'visa',
				'funding' => 'debit',
			],
		];
		$mastercard_credit_details = [
			'type' => 'card',
			'card' => [
				'network' => 'mastercard',
				'funding' => 'credit',
			],
		];
		$giropay_details           = [
			'type' => 'giropay',
		];
		$sofort_details            = [
			'type' => 'sofort',
		];
		$ideal_details             = [
			'type' => 'ideal',
		];

		$charge_payment_method_details  = [
			$visa_credit_details,
			$visa_debit_details,
			$mastercard_credit_details,
			$giropay_details,
			$sofort_details,
			$ideal_details,
		];
		$expected_payment_method_titles = [
			'Visa credit card',
			'Visa debit card',
			'Mastercard credit card',
			'giropay',
			'Sofort',
			'iDEAL',
		];

		foreach ( $charge_payment_method_details as $i => $payment_method_details ) {
			$this->mock_upe_gateway->set_payment_method_title_for_order( $order, $payment_method_details['type'], $payment_method_details );
			$this->assertEquals( $expected_payment_method_titles[ $i ], $order->get_payment_method_title() );
		}
	}

	public function test_payment_methods_show_correct_default_outputs() {
		$mock_token = WC_Helper_Token::create_token( 'pm_mock' );
		$this->mock_token_service->expects( $this->any() )
			->method( 'add_payment_method_to_user' )
			->will(
				$this->returnValue( $mock_token )
			);

		$mock_user              = 'mock_user';
		$mock_payment_method_id = 'pm_mock';

		$mock_visa_details       = [
			'type' => 'card',
			'card' => [
				'network' => 'visa',
				'funding' => 'debit',
			],
		];
		$mock_mastercard_details = [
			'type' => 'card',
			'card' => [
				'network' => 'mastercard',
				'funding' => 'credit',
			],
		];
		$mock_giropay_details    = [
			'type' => 'giropay',
		];
		$mock_sofort_details     = [
			'type' => 'sofort',
		];
		$mock_ideal_details      = [
			'type' => 'ideal',
		];

		$this->set_cart_contains_subscription_items( false );
		$card_method    = $this->mock_payment_methods['card'];
		$giropay_method = $this->mock_payment_methods['giropay'];
		$sofort_method  = $this->mock_payment_methods['sofort'];
		$ideal_method   = $this->mock_payment_methods['ideal'];

		$this->assertEquals( 'card', $card_method->get_id() );
		$this->assertEquals( 'Credit card / debit card', $card_method->get_title() );
		$this->assertEquals( 'Visa debit card', $card_method->get_title( $mock_visa_details ) );
		$this->assertEquals( 'Mastercard credit card', $card_method->get_title( $mock_mastercard_details ) );
		$this->assertTrue( $card_method->is_enabled_at_checkout() );
		$this->assertTrue( $card_method->is_reusable() );
		$this->assertEquals( $mock_token, $card_method->get_payment_token_for_user( $mock_user, $mock_payment_method_id ) );

		$this->assertEquals( 'giropay', $giropay_method->get_id() );
		$this->assertEquals( 'giropay', $giropay_method->get_title() );
		$this->assertEquals( 'giropay', $giropay_method->get_title( $mock_giropay_details ) );
		$this->assertTrue( $giropay_method->is_enabled_at_checkout() );
		$this->assertFalse( $giropay_method->is_reusable() );

		$this->assertEquals( 'sofort', $sofort_method->get_id() );
		$this->assertEquals( 'Sofort', $sofort_method->get_title() );
		$this->assertEquals( 'Sofort', $sofort_method->get_title( $mock_sofort_details ) );
		$this->assertTrue( $sofort_method->is_enabled_at_checkout() );
		$this->assertFalse( $sofort_method->is_reusable() );

		$this->assertEquals( 'ideal', $ideal_method->get_id() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title( $mock_ideal_details ) );
		$this->assertTrue( $ideal_method->is_enabled_at_checkout() );
		$this->assertFalse( $ideal_method->is_reusable() );
	}

	public function test_only_reusabled_payment_methods_enabled_with_subscription_item_present() {
		$this->set_cart_contains_subscription_items( true );
		$card_method    = $this->mock_payment_methods['card'];
		$giropay_method = $this->mock_payment_methods['giropay'];
		$sofort_method  = $this->mock_payment_methods['sofort'];
		$ideal_method   = $this->mock_payment_methods['ideal'];

		$this->assertTrue( $card_method->is_enabled_at_checkout() );
		$this->assertFalse( $giropay_method->is_enabled_at_checkout() );
		$this->assertFalse( $sofort_method->is_enabled_at_checkout() );
		$this->assertFalse( $ideal_method->is_enabled_at_checkout() );
	}

	public function test_only_valid_payment_methods_returned_for_currency() {
		$card_method    = $this->mock_payment_methods['card'];
		$giropay_method = $this->mock_payment_methods['giropay'];
		$sofort_method  = $this->mock_payment_methods['sofort'];
		$ideal_method   = $this->mock_payment_methods['ideal'];

		self::$mock_site_currency = 'EUR';

		$this->assertTrue( $card_method->is_currency_valid() );
		$this->assertTrue( $giropay_method->is_currency_valid() );
		$this->assertTrue( $sofort_method->is_currency_valid() );
		$this->assertTrue( $ideal_method->is_currency_valid() );

		self::$mock_site_currency = 'USD';

		$this->assertTrue( $card_method->is_currency_valid() );
		$this->assertFalse( $giropay_method->is_currency_valid() );
		$this->assertFalse( $sofort_method->is_currency_valid() );
		$this->assertFalse( $ideal_method->is_currency_valid() );

		self::$mock_site_currency = '';
	}

	public function test_create_token_from_setup_intent_adds_token() {
		$mock_token           = WC_Helper_Token::create_token( 'pm_mock' );
		$mock_setup_intent_id = 'si_mock';
		$mock_user            = wp_get_current_user();

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_setup_intent' )
			->with( $mock_setup_intent_id )
			->willReturn(
				[
					'id'             => $mock_setup_intent_id,
					'payment_method' => 'pm_mock',
				]
			);

		$this->mock_token_service->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( 'pm_mock', $mock_user )
			->will(
				$this->returnValue( $mock_token )
			);

		$this->assertEquals( $mock_token, $this->mock_upe_gateway->create_token_from_setup_intent( $mock_setup_intent_id, $mock_user ) );
	}

	/**
	 * Helper function to mock subscriptions for internal UPE payment methods.
	 */
	private function set_cart_contains_subscription_items( $cart_contains_subscriptions ) {
		foreach ( $this->mock_payment_methods as $mock_payment_method ) {
			$mock_payment_method->expects( $this->any() )
				->method( 'is_subscription_item_in_cart' )
				->will(
					$this->returnValue( $cart_contains_subscriptions )
				);
		}
	}

	private function setup_saved_payment_method() {
		$token = WC_Helper_Token::create_token( 'pm_mock' );

		return [
			'payment_method' => WC_Payment_Gateway_WCPay::GATEWAY_ID,
			'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' => (string) $token->get_id(),
		];
	}

}

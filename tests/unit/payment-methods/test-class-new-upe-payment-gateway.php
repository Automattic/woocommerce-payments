<?php
/**
 * Class UPE_Payment_Gateway_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Payment_Methods;

use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Payment_Intent_Status;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Core\Server\Request\Create_Setup_Intention;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Core\Server\Request\Update_Intention;
use WCPay\Core\Server\Response;
use WCPay\Constants\Payment_Method;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Platform_Checkout\Platform_Checkout_Utilities;
use WCPay\Session_Rate_Limiter;
use WCPay\WC_Payments_UPE_Checkout;
use WCPAY_UnitTestCase;
use WC_Helper_Order;
use WC_Helper_Intention;
use WC_Helper_Token;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Order_Service;
use WC_Payments_Token_Service;
use Exception;
use WCPay\Payment_Methods\New_UPE_Payment_Gateway;

require_once dirname( __FILE__ ) . '/../helpers/class-wc-helper-site-currency.php';

/**
 * UPE_Payment_Gateway unit tests
 */
class New_UPE_Payment_Gateway_Test extends WCPAY_UnitTestCase {

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
	 * Mock UPE payment gateway.
	 *
	 * @var New_UPE_Payment_Gateway
	 */
	private $mock_upe_gateway;

	/**
	 * WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Array of mock UPE payment methods.
	 *
	 * @var array
	 */
	private $mock_payment_methods;

	/**
	 * Array of mock UPE payment gateways created by individual payment methods.
	 *
	 * @var array
	 */
	private $mock_payment_gateways;

	/**
	 * WC_Payments_Checkout.
	 *
	 * @var WC_Payments_Checkout
	 */
	private $mock_legacy_checkout;


	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $mock_wcpay_account;

	/**
	 * Platform_Checkout_Utilities instance.
	 *
	 * @var Platform_Checkout_Utilities
	 */
	private $mock_platform_checkout_utilities;

	/**
	 * Mocked value of return_url.
	 * The value is used in the set up and tests, so it's set as a private
	 * variable for easy reference.
	 *
	 * @var string
	 */
	private $return_url = 'test_url';

	/**
	 * @var string Mocked value of return_url.
	 */
	private $icon_url = 'test_icon_url';

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
	 * Mapping for payment ID to payment method.
	 *
	 * @var array
	 */
	private $payment_method_classes = [
		Payment_Method::CARD       => CC_Payment_Method::class,
		Payment_Method::GIROPAY    => Giropay_Payment_Method::class,
		Payment_Method::SOFORT     => Sofort_Payment_Method::class,
		Payment_Method::BANCONTACT => Bancontact_Payment_Method::class,
		Payment_Method::EPS        => EPS_Payment_Method::class,
		Payment_Method::P24        => P24_Payment_Method::class,
		Payment_Method::IDEAL      => Ideal_Payment_Method::class,
		Payment_Method::SEPA       => Sepa_Payment_Method::class,
		Payment_Method::BECS       => Becs_Payment_Method::class,
		Payment_Method::LINK       => Link_Payment_Method::class,
	];

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_payment_gateways = [];
		$this->mock_payment_methods  = [];

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
					'get_timeline',
				]
			)
			->getMock();

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$this->mock_wcpay_account->method( 'get_account_country' )->willReturn( 'US' );

		$payment_methods = [
			'link' => [
				'base' => 0.1,
			],
		];

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_fees' )
			->willReturn( $payment_methods );

		$this->mock_platform_checkout_utilities = $this->createMock( Platform_Checkout_Utilities::class );

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

		$this->mock_legacy_checkout = $this->getMockBuilder( WC_Payments_Checkout::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_rate_limiter = $this->createMock( Session_Rate_Limiter::class );
		$this->order_service     = new WC_Payments_Order_Service( $this->mock_api_client );

		// Arrange: Define a $_POST array which includes the payment method,
		// so that get_payment_method_from_request() does not throw error.
		$_POST = [
			'wcpay-payment-method' => 'pm_mock',
		];

		foreach ( $this->payment_method_classes as $payment_method_id => $payment_method_class ) {
			$mock_payment_method = $this->getMockBuilder( $payment_method_class )
				->setConstructorArgs( [ $this->mock_token_service ] )
				->setMethods( [ 'is_subscription_item_in_cart', 'get_icon' ] )
				->getMock();
			$this->mock_payment_methods[ $mock_payment_method->get_id() ] = $mock_payment_method;

			$mock_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
				->setConstructorArgs(
					[
						$this->mock_api_client,
						$this->mock_wcpay_account,
						$this->mock_customer_service,
						$this->mock_token_service,
						$this->mock_action_scheduler_service,
						$mock_payment_method,
						$this->mock_payment_methods,
						$this->mock_rate_limiter,
						$this->order_service,
					]
				)
				->setMethods(
					[
						'get_return_url',
						'manage_customer_details_for_order',
						'parent_process_payment',
						'get_upe_enabled_payment_method_statuses',
						'is_payment_recurring',
						'get_payment_method_ids_enabled_at_checkout',
						'wc_payments_get_payment_gateway_by_id',
					]
				)
				->getMock();

			// Arrange: Set the return value of get_return_url() so it can be used in a test later.
			$mock_gateway
				->expects( $this->any() )
				->method( 'get_return_url' )
				->will(
					$this->returnValue( $this->return_url )
				);
			$mock_gateway
				->expects( $this->any() )
				->method( 'parent_process_payment' )
				->will(
					$this->returnValue( $this->mock_payment_result )
				);

			$this->mock_payment_gateways[ $payment_method_id ] = $mock_gateway;

			WC_Helper_Site_Currency::$mock_site_currency = '';

			update_option( '_wcpay_feature_upe_split', '1' );
		}
	}

	public function test_payment_fields_outputs_fields() {
		foreach ( $this->mock_payment_gateways as $payment_method_id => $mock_payment_gateway ) {
			new WC_Payments_UPE_Checkout(
				$mock_payment_gateway,
				$this->mock_platform_checkout_utilities,
				$this->mock_wcpay_account,
				$this->mock_customer_service
			);

			$mock_payment_gateway
				->method( 'get_payment_method_ids_enabled_at_checkout' )
				->willReturn( [] );

			$mock_payment_gateway
				->method( 'wc_payments_get_payment_gateway_by_id' )
				->willReturn( $mock_payment_gateway );

			/**
			* This tests each payment method output separately without concatenating the output
			* into 1 single buffer. Each iteration has 1 assertion.
			*/
			ob_start();
			$mock_payment_gateway->payment_fields();
			$actual_output = ob_get_contents();
			ob_end_clean();

			$this->assertStringContainsString( '<div class="wcpay-upe-element" data-payment-method-type="' . $payment_method_id . '"></div>', $actual_output );
		}

	}

	public function test_process_payment_returns_correct_redirect_url() {
		$order                         = WC_Helper_Order::create_order();
		$order_id                      = $order->get_id();
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$payment_intent = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::PROCESSING ] );

		$this->set_cart_contains_subscription_items( false );

		foreach ( $this->mock_payment_gateways as $mock_payment_gateway ) {
			$this->mock_wcpay_request( Update_Intention::class, 1, $payment_intent->get_id() )
				->expects( $this->once() )
				->method( 'format_response' )
				->willReturn( $payment_intent );

			$result = $mock_payment_gateway->process_payment( $order->get_id() );
			$this->assertEquals( 'success', $result['result'] );
			$this->assertEquals( true, $result['payment_needed'] );
			$this->assertMatchesRegularExpression( "/order_id=$order_id/", $result['redirect_url'] );
			$this->assertMatchesRegularExpression( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
			$this->assertMatchesRegularExpression( '/save_payment_method=no/', $result['redirect_url'] );
		}

		unset( $_POST['wc_payment_intent_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public function test_process_payment_passes_save_payment_method_to_store() {
		$mock_sepa_payment_gateway = $this->mock_payment_gateways[ Payment_Method::SEPA ];

		$order                         = WC_Helper_Order::create_order();
		$order_id                      = $order->get_id();
		$gateway_id                    = New_UPE_Payment_Gateway::GATEWAY_ID . '_' . Payment_Method::SEPA;
		$save_payment_param            = "wc-$gateway_id-new-payment-method";
		$_POST[ $save_payment_param ]  = 'yes';
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$payment_intent = WC_Helper_Intention::create_intention( [ 'status' => Payment_Intent_Status::PROCESSING ] );

		$this->mock_wcpay_request( Update_Intention::class, 1, $payment_intent->get_id() )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				$payment_intent
			);

		$this->set_cart_contains_subscription_items( false );

		// Test saving with SEPA.
		$result = $mock_sepa_payment_gateway->process_payment( $order->get_id() );
		$this->assertEquals( 'success', $result['result'] );
		$this->assertMatchesRegularExpression( "/order_id=$order_id/", $result['redirect_url'] );
		$this->assertMatchesRegularExpression( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=yes/', $result['redirect_url'] );

		unset( $_POST[ $save_payment_param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST['wc_payment_intent_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public function test_process_payment_returns_correct_redirect_when_using_saved_payment() {
		$mock_card_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

		$order = WC_Helper_Order::create_order();
		$_POST = $this->setup_saved_payment_method();

		$this->set_cart_contains_subscription_items( false );

		$result = $mock_card_payment_gateway->process_payment( $order->get_id() );

		$mock_card_payment_gateway
			->expects( $this->never() )
			->method( 'manage_customer_details_for_order' );
		$this->assertEquals( 'success', $result['result'] );
		$this->assertMatchesRegularExpression( '/key=mock_order_key/', $result['redirect'] );
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

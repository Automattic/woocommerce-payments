<?php
/**
 * Class UPE_Payment_Gateway_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Payment_Methods;

use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Intent_Status;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Core\Server\Request\Create_Setup_Intention;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Core\Server\Request\Get_Setup_Intention;
use WCPay\Core\Server\Request\Update_Intention;
use WCPay\Constants\Payment_Method;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\WooPay\WooPay_Utilities;
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
use WCPay\Duplicate_Payment_Prevention_Service;
use WC_Payments_Localization_Service;

require_once dirname( __FILE__ ) . '/../helpers/class-wc-helper-site-currency.php';

/**
 * UPE_Payment_Gateway unit tests
 */
class UPE_Payment_Gateway_Test extends WCPAY_UnitTestCase {

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
	 * Mock Session_Rate_Limiter.
	 *
	 * @var Session_Rate_Limiter|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_rate_limiter;

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
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $mock_wcpay_account;

	/**
	 * WooPay_Utilities instance.
	 *
	 * @var WooPay_Utilities
	 */
	private $mock_woopay_utilities;

	/**
	 * Duplicate_Payment_Prevention_Service instance.
	 * @var Duplicate_Payment_Prevention_Service
	 */
	private $mock_dpps;

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
	 * WC_Payments_Localization_Service instance.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $mock_localization_service;

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
			->setMethods(
				[
					'get_payment_method',
					'is_server_connected',
					'get_timeline',
				]
			)
			->getMock();

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$this->mock_wcpay_account->method( 'get_account_country' )->willReturn( 'US' );
		$this->mock_wcpay_account->method( 'get_account_default_currency' )->willReturn( 'USD' );

		$payment_methods = [
			'link' => [
				'base' => 0.1,
			],
		];

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_fees' )
			->willReturn( $payment_methods );

		$this->mock_woopay_utilities = $this->createMock( WooPay_Utilities::class );

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

		$this->mock_dpps = $this->createMock( Duplicate_Payment_Prevention_Service::class );

		$this->mock_localization_service = $this->createMock( WC_Payments_Localization_Service::class );

		$this->mock_payment_methods = [];
		$payment_method_classes     = [
			CC_Payment_Method::class,
			Giropay_Payment_Method::class,
			Sofort_Payment_Method::class,
			Bancontact_Payment_Method::class,
			EPS_Payment_Method::class,
			P24_Payment_Method::class,
			Ideal_Payment_Method::class,
			Sepa_Payment_Method::class,
			Becs_Payment_Method::class,
			Link_Payment_Method::class,
			Affirm_Payment_Method::class,
			Afterpay_Payment_Method::class,
		];

		$this->mock_rate_limiter = $this->createMock( Session_Rate_Limiter::class );
		foreach ( $payment_method_classes as $payment_method_class ) {
			$mock_payment_method = $this->getMockBuilder( $payment_method_class )
				->setConstructorArgs( [ $this->mock_token_service ] )
				->setMethods( [ 'is_subscription_item_in_cart', 'get_icon' ] )
				->getMock();
			$this->mock_payment_methods[ $mock_payment_method->get_id() ] = $mock_payment_method;
		}

		$this->order_service = new WC_Payments_Order_Service( $this->mock_api_client );

		// Arrange: Mock UPE_Payment_Gateway so that some of its methods can be
		// mocked, and their return values can be used for testing.
		$this->mock_upe_gateway = $this->getMockBuilder( UPE_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods(
				[
					'get_return_url',
					'manage_customer_details_for_order',
					'parent_process_payment',
					'get_upe_enabled_payment_method_statuses',
					'is_payment_recurring',
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
			->method( 'parent_process_payment' )
			->will(
				$this->returnValue( $this->mock_payment_result )
			);

		update_option( '_wcpay_feature_upe', '1' );
		update_option( '_wcpay_feature_upe_split', '0' );

		// Arrange: Define a $_POST array which includes the payment method,
		// so that get_payment_method_from_request() does not throw error.
		$_POST = [
			'wcpay-payment-method' => 'pm_mock',
			'payment_method'       => UPE_Payment_Gateway::GATEWAY_ID,
		];
	}

	/**
	 * Cleanup after tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();
		update_option( '_wcpay_feature_upe', '0' );
		update_option( '_wcpay_feature_upe_split', '0' );
	}

	public function test_payment_fields_outputs_fields() {
		$this->set_cart_contains_subscription_items( false );
		$this->set_get_upe_enabled_payment_method_statuses_return_value();

		// Add the UPE Checkout action.
		$checkout = new WC_Payments_UPE_Checkout(
			$this->mock_upe_gateway,
			$this->mock_woopay_utilities,
			$this->mock_wcpay_account,
			$this->mock_customer_service
		);
		$checkout->init_hooks();

		$this->mock_upe_gateway->payment_fields();

		$this->expectOutputRegex( '/<div id="wcpay-upe-element" class="wcpay-upe-element"><\/div>/' );
	}

	public function test_update_payment_intent_adds_customer_save_payment_and_level3_data() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$order_number        = $order->get_order_number();
		$product_item        = current( $order->get_items( 'line_item' ) );
		$intent_id           = 'pi_mock';
		$user                = '';
		$customer_id         = 'cus_mock';
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

		$intent = WC_Helper_Intention::create_intention(
			[
				'id'     => $intent_id,
				'amount' => 5000,
			]
		);

		$request = $this->mock_wcpay_request( Update_Intention::class, 1, 'pi_mock' );

		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( 5000 );

		$request->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( 'usd' );

		$request->expects( $this->once() )
			->method( 'set_customer' )
			->with( 'cus_mock' );

		$request->expects( $this->once() )
			->method( 'setup_future_usage' );

		$request->expects( $this->once() )
			->method( 'set_metadata' )
			->with(
				[
					'customer_name'  => 'Jeroen Sormani',
					'customer_email' => 'admin@example.org',
					'site_url'       => 'http://example.org',
					'order_id'       => $order_id,
					'order_number'   => $order_number,
					'order_key'      => $order->get_order_key(),
					'payment_type'   => Payment_Type::SINGLE(),
				]
			);

		$request->expects( $this->once() )
			->method( 'set_level3' )
			->with(
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
			);

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$result = $this->mock_upe_gateway->update_payment_intent( $intent_id, $order_id, $save_payment_method );
	}

	public function test_update_payment_intent_with_selected_upe_payment_method() {
		$order                     = WC_Helper_Order::create_order();
		$order_id                  = $order->get_id();
		$order_number              = $order->get_order_number();
		$product_item              = current( $order->get_items( 'line_item' ) );
		$intent_id                 = 'pi_mock';
		$user                      = '';
		$customer_id               = 'cus_mock';
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

		$intent = WC_Helper_Intention::create_intention(
			[
				'id'     => $intent_id,
				'amount' => 5000,
			]
		);

		$request = $this->mock_wcpay_request( Update_Intention::class, 1, 'pi_mock' );

		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( 5000 );

		$request->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( 'usd' );

		$request->expects( $this->once() )
			->method( 'set_customer' )
			->with( 'cus_mock' );

		$request->expects( $this->once() )
			->method( 'set_payment_method_types' )
			->with( [ 'giropay' ] );

		$request->expects( $this->once() )
			->method( 'setup_future_usage' );

		$request->expects( $this->once() )
			->method( 'set_metadata' )
			->with(
				[
					'customer_name'  => 'Jeroen Sormani',
					'customer_email' => 'admin@example.org',
					'site_url'       => 'http://example.org',
					'order_id'       => $order_id,
					'order_number'   => $order_number,
					'order_key'      => $order->get_order_key(),
					'payment_type'   => Payment_Type::SINGLE(),
				]
			);

		$request->expects( $this->once() )
			->method( 'set_level3' )
			->with(
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
				]
			);

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$result = $this->mock_upe_gateway->update_payment_intent( $intent_id, $order_id, $save_payment_method, $selected_upe_payment_type );
	}

	public function test_update_payment_intent_with_payment_country() {
		$order        = WC_Helper_Order::create_order();
		$order_id     = $order->get_id();
		$intent_id    = 'pi_mock';
		$order_number = $order->get_order_number();
		$product_item = current( $order->get_items( 'line_item' ) );

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ '', 'cus_mock' ] )
			);

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$intent = WC_Helper_Intention::create_intention(
			[
				'id'     => $intent_id,
				'amount' => 5000,
			]
		);

		$request = $this->mock_wcpay_request( Update_Intention::class, 1, 'pi_mock' );

		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( 5000 );

		$request->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( 'usd' );

		$request->expects( $this->once() )
			->method( 'set_customer' )
			->with( 'cus_mock' );

		$request->expects( $this->once() )
			->method( 'set_payment_country' )
			->with( 'US' );

		$request->expects( $this->never() )
			->method( 'setup_future_usage' );

		$request->expects( $this->once() )
			->method( 'set_metadata' )
			->with(
				[
					'customer_name'  => 'Jeroen Sormani',
					'customer_email' => 'admin@example.org',
					'site_url'       => 'http://example.org',
					'order_id'       => $order_id,
					'order_number'   => $order_number,
					'order_key'      => $order->get_order_key(),
					'payment_type'   => Payment_Type::SINGLE(),
				]
			);

		$request->expects( $this->once() )
			->method( 'set_level3' )
			->with(
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
				]
			);

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->mock_upe_gateway->update_payment_intent( 'pi_mock', $order_id, false, null, 'US' );
	}

	public function test_create_payment_intent_uses_order_amount_if_order() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );
		$request  = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( $intent->get_amount() );

		$request->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( strtolower( $intent->get_currency() ) );

		$request->expects( $this->once() )
			->method( 'set_capture_method' )
			->with( false );

		$request->expects( $this->once() )
			->method( 'set_metadata' )
			->with(
				$this->callback(
					function( $metadata ) {
						return isset( $metadata['order_number'] );
					}
				)
			);

		$request->expects( $this->once() )
			->method( 'set_payment_method_types' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->set_cart_contains_subscription_items( false );
		$this->set_get_upe_enabled_payment_method_statuses_return_value();

		$result = $this->mock_upe_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_payment_intent_defaults_to_automatic_capture() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );
		$request  = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( $intent->get_amount() );

		$request->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( strtolower( $intent->get_currency() ) );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->set_get_upe_enabled_payment_method_statuses_return_value();

		$this->mock_upe_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_payment_intent_with_automatic_capture() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );
		$this->mock_upe_gateway->settings['manual_capture'] = 'no';
		$request = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( $intent->get_amount() );

		$request->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( strtolower( $intent->get_currency() ) );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->set_get_upe_enabled_payment_method_statuses_return_value();

		$this->mock_upe_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_payment_intent_with_manual_capture() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );
		$this->mock_upe_gateway->settings['manual_capture'] = 'yes';
		$request = $this->mock_wcpay_request( Create_Intention::class );

		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( $intent->get_amount() );

		$request->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( strtolower( $intent->get_currency() ) );

		$request->expects( $this->once() )
			->method( 'set_capture_method' )
			->with( true );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->set_get_upe_enabled_payment_method_statuses_return_value();

		$this->mock_upe_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_payment_intent_with_fingerprint() {
		$order       = WC_Helper_Order::create_order();
		$order_id    = $order->get_id();
		$fingerprint = 'abc123';
		$intent      = WC_Helper_Intention::create_intention();

		$request = $this->mock_wcpay_request( Create_Intention::class, 1 );
		$request
			->expects( $this->once() )
			->method( 'set_amount' )
			->with( 5000 );
		$request
			->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( 'usd' );
		$request
			->expects( $this->once() )
			->method( 'set_payment_method_types' )
			->with( [ 'card' ] );
		$request
			->expects( $this->once() )
			->method( 'set_metadata' )
			->with( [ 'order_number' => $order_id ] );
		$request
			->expects( $this->once() )
			->method( 'set_fingerprint' )
			->with( $fingerprint );
		$request
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );
		$this->set_get_upe_enabled_payment_method_statuses_return_value();

		$this->mock_upe_gateway->create_payment_intent( [ 'card' ], $order_id, $fingerprint );
	}

	public function test_create_payment_intent_with_no_fingerprint() {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = WC_Helper_Intention::create_intention();
		$request  = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( $intent->get_amount() );

		$request->expects( $this->once() )
			->method( 'set_fingerprint' )
			->with( '' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );
		$this->set_get_upe_enabled_payment_method_statuses_return_value();

		$this->mock_upe_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_setup_intent_existing_customer() {
		$_POST = [ 'wcpay-payment-method' => 'pm_mock' ];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( 'cus_mock' ) );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$request = $this->mock_wcpay_request( Create_Setup_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_customer' )
			->with( 'cus_mock' );

		$request->expects( $this->once() )
			->method( 'set_payment_method_types' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_setup_intention(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'client_secret_mock',
					]
				)
			);

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->create_setup_intent( [ 'card' ] );

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

		$request = $this->mock_wcpay_request( Create_Setup_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_customer' )
			->with( 'cus_12346' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_setup_intention(
					[
						'id'            => 'seti_mock',
						'client_secret' => 'client_secret_mock',
					]
				)
			);

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->create_setup_intent( [ 'card' ] );

		$this->assertEquals( 'seti_mock', $result['id'] );
		$this->assertEquals( 'client_secret_mock', $result['client_secret'] );
	}

	public function test_process_payment_returns_correct_redirect_url() {
		$order                         = WC_Helper_Order::create_order();
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$payment_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::PROCESSING ] );

		$request = $this->mock_wcpay_request( Update_Intention::class, 1, 'pi_mock' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $payment_intent );

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );

		unset( $_POST['wc_payment_intent_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( true, $result['payment_needed'] );
		$this->assertMatchesRegularExpression( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=no/', $result['redirect_url'] );
	}

	public function test_process_payment_passes_save_payment_method_to_store() {
		$order                         = WC_Helper_Order::create_order();
		$gateway_id                    = UPE_Payment_Gateway::GATEWAY_ID;
		$save_payment_param            = "wc-$gateway_id-new-payment-method";
		$_POST[ $save_payment_param ]  = 'yes';
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$payment_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::PROCESSING ] );

		$request = $this->mock_wcpay_request( Update_Intention::class, 1, 'pi_mock' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $payment_intent );

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );

		unset( $_POST[ $save_payment_param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST['wc_payment_intent_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->assertEquals( 'success', $result['result'] );
		$this->assertMatchesRegularExpression( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=yes/', $result['redirect_url'] );
	}

	public function test_process_subscription_payment_passes_save_payment_method() {
		$order                         = WC_Helper_Order::create_order();
		$order_id                      = $order->get_id();
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$payment_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::PROCESSING ] );

		$request = $this->mock_wcpay_request( Update_Intention::class, 1, 'pi_mock' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $payment_intent );

		$this->mock_upe_gateway
			->expects( $this->once() )
			->method( 'is_payment_recurring' )
			->willReturn( true );

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );

		unset( $_POST['wc_payment_intent_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( true, $result['payment_needed'] );
		$this->assertMatchesRegularExpression( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=yes/', $result['redirect_url'] );
	}

	public function test_process_payment_returns_correct_redirect_when_using_saved_payment() {
		$order = WC_Helper_Order::create_order();
		$_POST = $this->setup_saved_payment_method();

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway
			->expects( $this->never() )
			->method( 'manage_customer_details_for_order' );

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );

		$this->assertEquals( 'success', $result['result'] );
		$this->assertMatchesRegularExpression( '/key=mock_order_key/', $result['redirect'] );
	}

	public function test_process_payment_returns_correct_redirect_when_using_payment_request() {
		$order                         = WC_Helper_Order::create_order();
		$_POST['payment_request_type'] = 'google_pay';

		$this->set_cart_contains_subscription_items( false );

		$result = $this->mock_upe_gateway->process_payment( $order->get_id() );

		$this->mock_upe_gateway
			->expects( $this->never() )
			->method( 'manage_customer_details_for_order' );
		$this->assertEquals( 'success', $result['result'] );
		$this->assertMatchesRegularExpression( '/key=mock_order_key/', $result['redirect'] );
	}

	public function test_upe_process_payment_check_session_order_redirect_to_previous_order() {
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$response = [
			'dummy_result' => 'xyz',
		];

		// Arrange the order is being processed.
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();

		// Arrange the DPPS to return an order from the session.
		$this->mock_dpps->expects( $this->once() )
			->method( 'check_against_session_processing_order' )
			->with( wc_get_order( $current_order ) )
			->willReturn( $response );

		// Assert: no call to the server to confirm the payment.
		$this->mock_wcpay_request( Update_Intention::class, 0, 'pi_XXXXX' );

		// Act: process the order but redirect to the previous/session paid order.
		$result = $this->mock_upe_gateway->process_payment( $current_order_id );

		// Assert: the result of check_against_session_processing_order.
		$this->assertSame( $response, $result );
	}

	public function test_upe_process_payment_check_session_with_failed_intent_then_order_id_saved_to_session() {
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		// Arrange the order is being processed.
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();

		// Arrange a failed intention.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => 'failed' ] );

		// Assert.
		$update_request = $this->mock_wcpay_request( Update_Intention::class, 1, $intent->get_id() );
		$update_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		// Arrange the DPPS not to return an order from the session.
		$this->mock_dpps->expects( $this->once() )
			->method( 'check_against_session_processing_order' )
			->with( wc_get_order( $current_order ) )
			->willReturn( null );

		// Assert: maybe_update_session_processing_order takes action and its value is kept.
		$this->mock_dpps->expects( $this->once() )
			->method( 'maybe_update_session_processing_order' )
			->with( $current_order_id );

		// Act: process the order but redirect to the previous/session paid order.
		$this->mock_upe_gateway->process_payment( $current_order_id );
	}

	public function test_upe_process_payment_check_session_and_continue_processing() {
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		// Arrange the order is being processed.
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// Arrange a successful intention.
		$intent = WC_Helper_Intention::create_intention();

		// Arrange the DPPS not to return an order from the session.
		$this->mock_dpps->expects( $this->once() )
			->method( 'check_against_session_processing_order' )
			->with( wc_get_order( $order ) )
			->willReturn( null );

		// Assert: Order is removed from the session.
		$this->mock_dpps->expects( $this->once() )
			->method( 'remove_session_processing_order' )
			->with( $order_id );

		// Assert: the payment process continues.
		$this->mock_wcpay_request( Update_Intention::class, 1, $intent->get_id() )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		// Act.
		$this->mock_upe_gateway->process_payment( $order_id );
	}

	public function test_upe_check_payment_intent_attached_to_order_succeeded_return_redirection() {
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$response = [
			'dummy_result' => 'xyz',
		];

		// Arrange order.
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// Arrange the DPPS to return a prepared response.
		$this->mock_dpps->expects( $this->once() )
			->method( 'check_payment_intent_attached_to_order_succeeded' )
			->with( wc_get_order( $order ) )
			->willReturn( $response );

		// Assert: no more call to the server to update the intention.
		$this->mock_wcpay_request( Update_Intention::class, 0 );

		// Act: process the order but redirect to the order.
		$result = $this->mock_upe_gateway->process_payment( $order_id );

		// Assert: the result of check_intent_attached_to_order_succeeded.
		$this->assertSame( $response, $result );
	}

	public function is_proper_intent_used_with_order_returns_false() {
		$this->assertFalse( $this->mock_upe_gateway->is_proper_intent_used_with_order( WC_Helper_Order::create_order(), 'wrong_intent_id' ) );
	}

	public function test_process_redirect_payment_intent_processing() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$save_payment_method = false;
		$user                = wp_get_current_user();
		$intent_status       = Intent_Status::PROCESSING;
		$intent_metadata     = [ 'order_id' => (string) $order_id ];
		$charge_id           = 'ch_mock';
		$customer_id         = 'cus_mock';
		$intent_id           = 'pi_mock';
		$payment_method_id   = 'pm_mock';

		// Supply the order with the intent id so that it can be retrieved during the redirect payment processing.
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->save();

		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => $intent_status,
				'metadata' => $intent_metadata,
			]
		);

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->returnValue( $payment_intent ) );

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertStringContainsString( 'authorized', $note->content );
		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $charge_id, $result_order->get_meta( '_charge_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( Order_Status::ON_HOLD, $result_order->get_status() );
	}

	public function test_process_redirect_payment_intent_succeded() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$save_payment_method = false;
		$user                = wp_get_current_user();
		$intent_status       = Intent_Status::SUCCEEDED;
		$intent_metadata     = [ 'order_id' => (string) $order_id ];
		$charge_id           = 'ch_mock';
		$customer_id         = 'cus_mock';
		$intent_id           = 'pi_mock';
		$payment_method_id   = 'pm_mock';

		// Supply the order with the intent id so that it can be retrieved during the redirect payment processing.
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->save();

		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => $intent_status,
				'metadata' => $intent_metadata,
			]
		);

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->returnValue( $payment_intent ) );

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );

		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $charge_id, $result_order->get_meta( '_charge_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( Order_Status::PROCESSING, $result_order->get_status() );
	}

	public function test_validate_order_id_received_vs_intent_meta_order_id_throw_exception() {
		$order           = WC_Helper_Order::create_order();
		$intent_metadata = [ 'order_id' => (string) ( $order->get_id() + 100 ) ];

		$this->expectException( Process_Payment_Exception::class );
		$this->expectExceptionMessage( "We're not able to process this payment due to the order ID mismatch. Please try again later." );

		\PHPUnit_Utils::call_method(
			$this->mock_upe_gateway,
			'validate_order_id_received_vs_intent_meta_order_id',
			[ $order, $intent_metadata ]
		);
	}

	public function test_validate_order_id_received_vs_intent_meta_order_id_returning_void() {
		$order           = WC_Helper_Order::create_order();
		$intent_metadata = [ 'order_id' => (string) ( $order->get_id() ) ];

		$res = \PHPUnit_Utils::call_method(
			$this->mock_upe_gateway,
			'validate_order_id_received_vs_intent_meta_order_id',
			[ $order, $intent_metadata ]
		);

		$this->assertSame( null, $res );
	}
	public function test_process_redirect_setup_intent_succeded() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$save_payment_method = true;
		$user                = wp_get_current_user();
		$intent_status       = Intent_Status::SUCCEEDED;
		$client_secret       = 'cs_mock';
		$customer_id         = 'cus_mock';
		$intent_id           = 'si_mock';
		$payment_method_id   = 'pm_mock';
		$token               = WC_Helper_Token::create_token( $payment_method_id );

		// Supply the order with the intent id so that it can be retrieved during the redirect payment processing.
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->save();

		$order->set_shipping_total( 0 );
		$order->set_shipping_tax( 0 );
		$order->set_cart_tax( 0 );
		$order->set_total( 0 );
		$order->save();

		$setup_intent = WC_Helper_Intention::create_setup_intention(
			[
				'id'                     => $intent_id,
				'client_secret'          => $client_secret,
				'status'                 => $intent_status,
				'payment_method'         => $payment_method_id,
				'payment_method_options' => [
					'card' => [
						'request_three_d_secure' => 'automatic',
					],
				],
				'last_setup_error'       => [],
			]
		);

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$request = $this->mock_wcpay_request( Get_Setup_Intention::class, 1, $intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $setup_intent );

		$this->mock_token_service->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->will(
				$this->returnValue( $token )
			);

		$this->set_cart_contains_subscription_items( true );

		$this->mock_upe_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );

		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( Order_Status::PROCESSING, $result_order->get_status() );
		$this->assertEquals( 1, count( $result_order->get_payment_tokens() ) );
	}

	public function test_process_redirect_payment_save_payment_token() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$save_payment_method = true;
		$user                = wp_get_current_user();
		$intent_status       = Intent_Status::PROCESSING;
		$intent_metadata     = [ 'order_id' => (string) $order_id ];
		$charge_id           = 'ch_mock';
		$customer_id         = 'cus_mock';
		$intent_id           = 'pi_mock';
		$payment_method_id   = 'pm_mock';
		$token               = WC_Helper_Token::create_token( $payment_method_id );

		// Supply the order with the intent id so that it can be retrieved during the redirect payment processing.
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->save();

		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => $intent_status,
				'metadata' => $intent_metadata,
			]
		);

		$this->mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->returnValue( $payment_intent ) );

		$this->mock_token_service->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->will(
				$this->returnValue( $token )
			);

		$this->set_cart_contains_subscription_items( false );

		$this->mock_upe_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );
		$note         = wc_get_order_notes(
			[
				'order_id' => $order_id,
				'limit'    => 1,
			]
		)[0];

		$this->assertStringContainsString( 'authorized', $note->content );
		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $charge_id, $result_order->get_meta( '_charge_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( Order_Status::ON_HOLD, $result_order->get_status() );
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
		$eps_details               = [
			'type' => 'eps',
		];
		$giropay_details           = [
			'type' => 'giropay',
		];
		$p24_details               = [
			'type' => 'p24',
		];
		$sofort_details            = [
			'type' => 'sofort',
		];
		$bancontact_details        = [
			'type' => 'bancontact',
		];
		$sepa_details              = [
			'type' => 'sepa_debit',
		];
		$ideal_details             = [
			'type' => 'ideal',
		];
		$becs_details              = [
			'type' => 'au_becs_debit',
		];

		$charge_payment_method_details = [
			$visa_credit_details,
			$visa_debit_details,
			$mastercard_credit_details,
			$giropay_details,
			$sofort_details,
			$bancontact_details,
			$eps_details,
			$p24_details,
			$ideal_details,
			$sepa_details,
			$becs_details,
		];

		$expected_payment_method_titles = [
			'Visa credit card',
			'Visa debit card',
			'Mastercard credit card',
			'giropay',
			'Sofort',
			'Bancontact',
			'EPS',
			'Przelewy24 (P24)',
			'iDEAL',
			'SEPA Direct Debit',
			'BECS Direct Debit',
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
		$mock_p24_details        = [
			'type' => 'p24',
		];
		$mock_sofort_details     = [
			'type' => 'sofort',
		];
		$mock_bancontact_details = [
			'type' => 'bancontact',
		];
		$mock_eps_details        = [
			'type' => 'eps',
		];
		$mock_sepa_details       = [
			'type' => 'sepa_debit',
		];
		$mock_ideal_details      = [
			'type' => 'ideal',
		];
		$mock_becs_details       = [
			'type' => 'au_becs_debit',
		];
		$mock_affirm_details     = [
			'type' => 'affirm',
		];
		$mock_afterpay_details   = [
			'type' => 'afterpay_clearpay',
		];

		$this->set_cart_contains_subscription_items( false );
		$card_method       = $this->mock_payment_methods['card'];
		$giropay_method    = $this->mock_payment_methods['giropay'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$ideal_method      = $this->mock_payment_methods['ideal'];
		$becs_method       = $this->mock_payment_methods['au_becs_debit'];
		$affirm_method     = $this->mock_payment_methods['affirm'];
		$afterpay_method   = $this->mock_payment_methods['afterpay_clearpay'];

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

		$this->assertEquals( 'p24', $p24_method->get_id() );
		$this->assertEquals( 'Przelewy24 (P24)', $p24_method->get_title() );
		$this->assertEquals( 'Przelewy24 (P24)', $p24_method->get_title( $mock_p24_details ) );
		$this->assertTrue( $p24_method->is_enabled_at_checkout() );
		$this->assertFalse( $p24_method->is_reusable() );

		$this->assertEquals( 'sofort', $sofort_method->get_id() );
		$this->assertEquals( 'Sofort', $sofort_method->get_title() );
		$this->assertEquals( 'Sofort', $sofort_method->get_title( $mock_sofort_details ) );
		$this->assertTrue( $sofort_method->is_enabled_at_checkout() );
		$this->assertFalse( $sofort_method->is_reusable() );

		$this->assertEquals( 'bancontact', $bancontact_method->get_id() );
		$this->assertEquals( 'Bancontact', $bancontact_method->get_title() );
		$this->assertEquals( 'Bancontact', $bancontact_method->get_title( $mock_bancontact_details ) );
		$this->assertTrue( $bancontact_method->is_enabled_at_checkout() );
		$this->assertFalse( $bancontact_method->is_reusable() );

		$this->assertEquals( 'eps', $eps_method->get_id() );
		$this->assertEquals( 'EPS', $eps_method->get_title() );
		$this->assertEquals( 'EPS', $eps_method->get_title( $mock_eps_details ) );
		$this->assertTrue( $eps_method->is_enabled_at_checkout() );
		$this->assertFalse( $eps_method->is_reusable() );

		$this->assertEquals( 'sepa_debit', $sepa_method->get_id() );
		$this->assertEquals( 'SEPA Direct Debit', $sepa_method->get_title() );
		$this->assertEquals( 'SEPA Direct Debit', $sepa_method->get_title( $mock_sepa_details ) );
		$this->assertTrue( $sepa_method->is_enabled_at_checkout() );
		$this->assertTrue( $sepa_method->is_reusable() );

		$this->assertEquals( 'ideal', $ideal_method->get_id() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title( $mock_ideal_details ) );
		$this->assertTrue( $ideal_method->is_enabled_at_checkout() );
		$this->assertFalse( $ideal_method->is_reusable() );

		$this->assertEquals( 'au_becs_debit', $becs_method->get_id() );
		$this->assertEquals( 'BECS Direct Debit', $becs_method->get_title() );
		$this->assertEquals( 'BECS Direct Debit', $becs_method->get_title( $mock_becs_details ) );
		$this->assertTrue( $becs_method->is_enabled_at_checkout() );
		$this->assertFalse( $becs_method->is_reusable() );

		$this->assertSame( 'affirm', $affirm_method->get_id() );
		$this->assertSame( 'Affirm', $affirm_method->get_title() );
		$this->assertSame( 'Affirm', $affirm_method->get_title( $mock_affirm_details ) );
		$this->assertTrue( $affirm_method->is_enabled_at_checkout() );
		$this->assertFalse( $affirm_method->is_reusable() );

		$this->assertSame( 'afterpay_clearpay', $afterpay_method->get_id() );
		$this->assertSame( 'Afterpay', $afterpay_method->get_title() );
		$this->assertSame( 'Afterpay', $afterpay_method->get_title( $mock_afterpay_details ) );
		$this->assertTrue( $afterpay_method->is_enabled_at_checkout() );
		$this->assertFalse( $afterpay_method->is_reusable() );
	}

	public function test_only_reusabled_payment_methods_enabled_with_subscription_item_present() {
		$this->set_cart_contains_subscription_items( true );

		$card_method       = $this->mock_payment_methods['card'];
		$giropay_method    = $this->mock_payment_methods['giropay'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$ideal_method      = $this->mock_payment_methods['ideal'];
		$becs_method       = $this->mock_payment_methods['au_becs_debit'];
		$affirm_method     = $this->mock_payment_methods['affirm'];
		$afterpay_method   = $this->mock_payment_methods['afterpay_clearpay'];

		$this->assertTrue( $card_method->is_enabled_at_checkout() );
		$this->assertFalse( $giropay_method->is_enabled_at_checkout() );
		$this->assertFalse( $sofort_method->is_enabled_at_checkout() );
		$this->assertFalse( $bancontact_method->is_enabled_at_checkout() );
		$this->assertFalse( $eps_method->is_enabled_at_checkout() );
		$this->assertTrue( $sepa_method->is_enabled_at_checkout() );
		$this->assertFalse( $p24_method->is_enabled_at_checkout() );
		$this->assertFalse( $ideal_method->is_enabled_at_checkout() );
		$this->assertFalse( $becs_method->is_enabled_at_checkout() );
		$this->assertFalse( $affirm_method->is_enabled_at_checkout() );
		$this->assertFalse( $afterpay_method->is_enabled_at_checkout() );
	}

	public function test_only_valid_payment_methods_returned_for_currency() {
		$card_method       = $this->mock_payment_methods['card'];
		$giropay_method    = $this->mock_payment_methods['giropay'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$ideal_method      = $this->mock_payment_methods['ideal'];
		$becs_method       = $this->mock_payment_methods['au_becs_debit'];
		$affirm_method     = $this->mock_payment_methods['affirm'];
		$afterpay_method   = $this->mock_payment_methods['afterpay_clearpay'];

		WC_Helper_Site_Currency::$mock_site_currency = 'EUR';

		$account_domestic_currency = 'USD';
		$this->assertTrue( $card_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $giropay_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $sofort_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $bancontact_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $eps_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $sepa_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $p24_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $ideal_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $becs_method->is_currency_valid( $account_domestic_currency ) );
		// BNPLs can accept only domestic payments.
		$this->assertFalse( $affirm_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $afterpay_method->is_currency_valid( $account_domestic_currency ) );

		WC_Helper_Site_Currency::$mock_site_currency = 'USD';

		$this->assertTrue( $card_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $giropay_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $sofort_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $bancontact_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $eps_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $sepa_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $p24_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $ideal_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $becs_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $affirm_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $afterpay_method->is_currency_valid( $account_domestic_currency ) );

		WC_Helper_Site_Currency::$mock_site_currency = 'AUD';
		$this->assertTrue( $becs_method->is_currency_valid( $account_domestic_currency ) );

		// BNPLs can accept only domestic payments.
		WC_Helper_Site_Currency::$mock_site_currency = 'USD';
		$account_domestic_currency                   = 'CAD';
		$this->assertFalse( $affirm_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $afterpay_method->is_currency_valid( $account_domestic_currency ) );

		WC_Helper_Site_Currency::$mock_site_currency = '';
	}

	public function test_payment_method_compares_correct_currency() {
		$card_method       = $this->mock_payment_methods['card'];
		$giropay_method    = $this->mock_payment_methods['giropay'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$ideal_method      = $this->mock_payment_methods['ideal'];
		$becs_method       = $this->mock_payment_methods['au_becs_debit'];
		$affirm_method     = $this->mock_payment_methods['affirm'];
		$afterpay_method   = $this->mock_payment_methods['afterpay_clearpay'];

		WC_Helper_Site_Currency::$mock_site_currency = 'EUR';
		$account_domestic_currency                   = 'USD';

		$this->assertTrue( $card_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $giropay_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $sofort_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $bancontact_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $eps_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $sepa_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $p24_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $ideal_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $becs_method->is_currency_valid( $account_domestic_currency ) );

		global $wp;
		$order          = WC_Helper_Order::create_order();
		$order_id       = $order->get_id();
		$wp->query_vars = [ 'order-pay' => strval( $order_id ) ];
		$order->set_currency( 'USD' );

		$this->assertTrue( $card_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $giropay_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $sofort_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $bancontact_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $eps_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $sepa_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $p24_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $ideal_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertFalse( $becs_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $affirm_method->is_currency_valid( $account_domestic_currency ) );
		$this->assertTrue( $afterpay_method->is_currency_valid( $account_domestic_currency ) );

		$wp->query_vars = [];
	}

	public function test_create_token_from_setup_intent_adds_token() {
		$mock_token           = WC_Helper_Token::create_token( 'pm_mock' );
		$mock_setup_intent_id = 'si_mock';
		$mock_user            = wp_get_current_user();

		$request = $this->mock_wcpay_request( Get_Setup_Intention::class, 1, $mock_setup_intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_setup_intention(
					[
						'id'             => $mock_setup_intent_id,
						'payment_method' => 'pm_mock',
					]
				)
			);

		$this->mock_token_service->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( 'pm_mock', $mock_user )
			->will(
				$this->returnValue( $mock_token )
			);

		$this->assertEquals( $mock_token, $this->mock_upe_gateway->create_token_from_setup_intent( $mock_setup_intent_id, $mock_user ) );
	}




	public function test_create_payment_intent_uses_cached_minimum_amount() {
		$order = WC_Helper_Order::create_order();
		$order->set_total( 0.45 );
		$order->save();

		set_transient( 'wcpay_minimum_amount_usd', '50', DAY_IN_SECONDS );

		$intent = WC_Helper_Intention::create_intention(
			[
				'status' => Intent_Status::REQUIRES_PAYMENT_METHOD,
				'amount' => 50,
			]
		);

		$request = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( $intent->get_amount() );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->set_get_upe_enabled_payment_method_statuses_return_value();

		$this->mock_upe_gateway->create_payment_intent( [ 'card' ], $order->get_id() );
	}

	public function test_create_payment_intent_creates_new_intent_with_minimum_amount() {
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 0.45 );
		$order->save();

		$intent = WC_Helper_Intention::create_intention(
			[
				'status' => Intent_Status::REQUIRES_PAYMENT_METHOD,
				'amount' => 50,
			]
		);

		$request = $this->mock_wcpay_request( Create_Intention::class, 2 );

		$request->expects( $this->exactly( 2 ) )
			->method( 'set_amount' )
			->withConsecutive( [ 45 ], [ $intent->get_amount() ] );

		$request->expects( $this->once() )
			->method( 'set_currency_code' )
			->with( strtolower( $intent->get_currency() ) );

		$request->expects( $this->exactly( 2 ) )
			->method( 'format_response' )
			->will(
				$this->onConsecutiveCalls(
					$this->throwException( new Amount_Too_Small_Exception( 'Error: Amount must be at least $0.50 usd', 50, 'usd', 400 ) ),
					$this->returnValue( $intent )
				)
			);
		$this->set_get_upe_enabled_payment_method_statuses_return_value();

		$result = $this->mock_upe_gateway->create_payment_intent( [ 'card' ], $order->get_id() );
		$this->assertsame( 'cs_mock', $result['client_secret'] );
	}

	public function test_process_payment_rejects_with_cached_minimum_acount() {
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 0.45 );
		$order->save();

		set_transient( 'wcpay_minimum_amount_usd', '50', DAY_IN_SECONDS );
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		// Make sure that the payment was not actually processed.
		$price   = wp_strip_all_tags( html_entity_decode( wc_price( 0.5, [ 'currency' => 'USD' ] ) ) );
		$message = 'The selected payment method requires a total amount of at least ' . $price . '.';
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $message );
		$this->mock_upe_gateway->process_payment( $order->get_id() );
	}

	public function test_exception_will_be_thrown_if_phone_number_is_invalid() {
		$order = WC_Helper_Order::create_order();
		$order->set_billing_phone( '+1123456789123456789123' );
		$order->save();
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid phone number.' );
		$this->mock_upe_gateway->process_payment( $order->get_id() );
	}

	public function test_process_payment_caches_mimimum_amount_and_displays_error_upon_exception() {
		$amount            = 0.45;
		$customer          = 'cus_12345';
		$payment_intent_id = 'pi_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_total( $amount );
		$order->save();

		delete_transient( 'wcpay_minimum_amount_usd' );

		$_POST['wc_payment_intent_id'] = $payment_intent_id;

		$request = $this->mock_wcpay_request( Update_Intention::class, 1, $payment_intent_id );

		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( (int) ( $amount * 100 ) );

		$request->expects( $this->once() )
			->method( 'set_metadata' )
			->with(
				$this->callback(
					function( $metadata ) {
						return is_array( $metadata );
					}
				)
			);

		$request->expects( $this->once() )
			->method( 'set_level3' )
			->with(
				$this->callback(
					function( $argument ) {
						return is_array( $argument );
					}
				)
			);

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->throwException( new Amount_Too_Small_Exception( 'Error: Amount must be at least $60 usd', 6000, 'usd', 400 ) ) );

		$price   = wp_strip_all_tags( html_entity_decode( wc_price( 60, [ 'currency' => 'USD' ] ) ) );
		$message = 'The selected payment method requires a total amount of at least ' . $price . '.';
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $message );

		try {
			$this->mock_upe_gateway->process_payment( $order->get_id() );
		} catch ( Exception $e ) {
			$this->assertEquals( '6000', get_transient( 'wcpay_minimum_amount_usd' ) );
			throw $e;
		}
	}

	/**
	 * @dataProvider maybe_filter_gateway_title_data_provider
	 */
	public function test_maybe_filter_gateway_title_with_no_additional_feature_flags_enabled( $data ) {
		$data           = $data[0];
		$default_option = $this->mock_upe_gateway->get_option( 'upe_enabled_payment_method_ids' );
		$this->mock_upe_gateway->update_option( 'upe_enabled_payment_method_ids', $data['methods'] );
		WC_Helper_Site_Currency::$mock_site_currency = $data['currency'];
		$this->set_get_upe_enabled_payment_method_statuses_return_value( $data['statuses'] );
		$this->assertSame( $data['expected'], $this->mock_upe_gateway->maybe_filter_gateway_title( $data['title'], $data['id'] ) );
		$this->mock_upe_gateway->update_option( 'upe_enabled_payment_method_ids', $default_option );
	}

	public function test_maybe_filter_gateway_title_skips_update_due_to_enabled_split_upe() {
		update_option( '_wcpay_feature_upe_split', '1' );
		update_option( '_wcpay_feature_upe_deferred_intent', '0' );

		$data = [
			'methods'  => [
				'card',
				'bancontact',
			],
			'statuses' => [
				'card_payments'       => [
					'status' => 'active',
				],
				'bancontact_payments' => [
					'status' => 'active',
				],
			],
			'currency' => 'EUR',
			'title'    => 'WooPayments',
			'id'       => UPE_Payment_Gateway::GATEWAY_ID,
			'expected' => 'WooPayments',
		];

		$default_option = $this->mock_upe_gateway->get_option( 'upe_enabled_payment_method_ids' );
		$this->mock_upe_gateway->update_option( 'upe_enabled_payment_method_ids', $data['methods'] );

		WC_Helper_Site_Currency::$mock_site_currency = $data['currency'];
		$this->set_get_upe_enabled_payment_method_statuses_return_value( $data['statuses'] );
		$this->assertSame( $data['expected'], $this->mock_upe_gateway->maybe_filter_gateway_title( $data['title'], $data['id'] ) );
		$this->mock_upe_gateway->update_option( 'upe_enabled_payment_method_ids', $default_option );
	}

	public function test_maybe_filter_gateway_title_skips_update_due_to_enabled_upe_with_deferred_intent_creation() {
		update_option( '_wcpay_feature_upe_split', '0' );
		update_option( '_wcpay_feature_upe_deferred_intent', '1' );
		$data = [
			'methods'  => [
				'card',
				'bancontact',
			],
			'statuses' => [
				'card_payments'       => [
					'status' => 'active',
				],
				'bancontact_payments' => [
					'status' => 'active',
				],
			],
			'currency' => 'EUR',
			'title'    => 'WooPayments',
			'id'       => UPE_Payment_Gateway::GATEWAY_ID,
			'expected' => 'WooPayments',
		];

		$default_option = $this->mock_upe_gateway->get_option( 'upe_enabled_payment_method_ids' );
		$this->mock_upe_gateway->update_option( 'upe_enabled_payment_method_ids', $data['methods'] );

		WC_Helper_Site_Currency::$mock_site_currency = $data['currency'];
		$this->set_get_upe_enabled_payment_method_statuses_return_value( $data['statuses'] );
		$this->assertSame( $data['expected'], $this->mock_upe_gateway->maybe_filter_gateway_title( $data['title'], $data['id'] ) );
		$this->mock_upe_gateway->update_option( 'upe_enabled_payment_method_ids', $default_option );
	}

	public function test_remove_link_payment_method_if_card_disabled() {

		$mock_upe_gateway = $this->getMockBuilder( UPE_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods(
				[
					'get_upe_enabled_payment_method_statuses',
					'get_upe_enabled_payment_method_ids',
				]
			)
			->getMock();

		$mock_upe_gateway
			->expects( $this->once() )
			->method( 'get_upe_enabled_payment_method_ids' )
			->will(
				$this->returnValue( [ 'link' ] )
			);
		$mock_upe_gateway
			->expects( $this->once() )
			->method( 'get_upe_enabled_payment_method_statuses' )
			->will(
				$this->returnValue( [ 'link_payments' => [ 'status' => 'active' ] ] )
			);

		$upe_checkout = new WC_Payments_UPE_Checkout(
			$mock_upe_gateway,
			$this->mock_woopay_utilities,
			$this->mock_wcpay_account,
			$this->mock_customer_service
		);

		$this->assertSame( $upe_checkout->get_payment_fields_js_config()['paymentMethodsConfig'], [] );
	}

	public function test_link_payment_method_if_card_enabled() {
		WC_Helper_Site_Currency::$mock_site_currency = 'USD';

		$mock_upe_gateway = $this->getMockBuilder( UPE_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods(
				[
					'get_upe_enabled_payment_method_statuses',
					'get_upe_enabled_payment_method_ids',
				]
			)
			->getMock();
		$mock_upe_gateway
			->expects( $this->once() )
			->method( 'get_upe_enabled_payment_method_ids' )
			->will(
				$this->returnValue( [ 'card', 'link' ] )
			);
		$mock_upe_gateway
			->expects( $this->once() )
			->method( 'get_upe_enabled_payment_method_statuses' )
			->will(
				$this->returnValue(
					[
						'link_payments' => [ 'status' => 'active' ],
						'card_payments' => [ 'status' => 'active' ],
					]
				)
			);

		$this->mock_payment_methods[ Payment_Method::LINK ]->expects( $this->any() )
			->method( 'get_icon' )
			->will(
				$this->returnValue( $this->icon_url )
			);
		$this->mock_payment_methods[ Payment_Method::CARD ]->expects( $this->any() )
			->method( 'get_icon' )
			->will(
				$this->returnValue( $this->icon_url )
			);

		$upe_checkout = new WC_Payments_UPE_Checkout(
			$mock_upe_gateway,
			$this->mock_woopay_utilities,
			$this->mock_wcpay_account,
			$this->mock_customer_service
		);

		$this->assertSame(
			$upe_checkout->get_payment_fields_js_config()['paymentMethodsConfig'],
			[
				'card' => [
					'isReusable'     => true,
					'title'          => 'Credit card / debit card',
					'icon'           => $this->icon_url,
					'showSaveOption' => true,
					'countries'      => [],
				],
				'link' => [
					'isReusable'     => true,
					'title'          => 'Link',
					'icon'           => $this->icon_url,
					'showSaveOption' => true,
					'countries'      => [],
				],
			]
		);
	}

	public function maybe_filter_gateway_title_data_provider() {
		$method_title   = 'WooPayments';
		$checkout_title = 'Popular payment methods';
		$card_title     = 'Credit card / debit card';

		$data_set[] = [ // Allows for $checkout_title due to UPE method and EUR.
			'methods'  => [
				'card',
				'bancontact',
			],
			'statuses' => [
				'card_payments'       => [
					'status' => 'active',
				],
				'bancontact_payments' => [
					'status' => 'active',
				],
			],
			'currency' => 'EUR',
			'title'    => $method_title,
			'id'       => UPE_Payment_Gateway::GATEWAY_ID,
			'expected' => $checkout_title,
		];
		$data_set[] = [ // No UPE method, only card, so $card_title is expected.
			'methods'  => [
				'card',
			],
			'statuses' => [
				'card_payments' => [
					'status' => 'active',
				],
			],
			'currency' => 'EUR',
			'title'    => $method_title,
			'id'       => UPE_Payment_Gateway::GATEWAY_ID,
			'expected' => $card_title,
		];
		$data_set[] = [ // Only UPE method, so UPE method title is expected.
			'methods'  => [
				'bancontact',
			],
			'statuses' => [
				'bancontact_payments' => [
					'status' => 'active',
				],
			],
			'currency' => 'EUR',
			'title'    => $method_title,
			'id'       => UPE_Payment_Gateway::GATEWAY_ID,
			'expected' => 'Bancontact',
		];
		$data_set[] = [ // Card and UPE enabled, but USD, $card_title expected.
			'methods'  => [
				'card',
				'bancontact',
			],
			'statuses' => [
				'card_payments'       => [
					'status' => 'active',
				],
				'bancontact_payments' => [
					'status' => 'active',
				],
			],
			'currency' => 'USD',
			'title'    => $method_title,
			'id'       => UPE_Payment_Gateway::GATEWAY_ID,
			'expected' => $card_title,
		];
		$data_set[] = [ // Card and UPE enabled, but not our title, other title expected.
			'methods'  => [
				'card',
				'bancontact',
			],
			'statuses' => [
				'card_payments'       => [
					'status' => 'active',
				],
				'bancontact_payments' => [
					'status' => 'active',
				],
			],
			'currency' => 'EUR',
			'title'    => 'Some other title',
			'id'       => UPE_Payment_Gateway::GATEWAY_ID,
			'expected' => 'Some other title',
		];
		$data_set[] = [ // Card and UPE enabled, but not our id, $method_title expected.
			'methods'  => [
				'card',
				'bancontact',
			],
			'statuses' => [
				'card_payments'       => [
					'status' => 'active',
				],
				'bancontact_payments' => [
					'status' => 'active',
				],
			],
			'currency' => 'USD',
			'title'    => $method_title,
			'id'       => 'some_other_id',
			'expected' => $method_title,
		];
		$data_set[] = [ // No methods at all, so defaults to card, so $card_title is expected.
			'methods'  => [],
			'statuses' => [],
			'currency' => 'EUR',
			'title'    => $method_title,
			'id'       => UPE_Payment_Gateway::GATEWAY_ID,
			'expected' => $card_title,
		];
		foreach ( $data_set as $data ) {
			$return_data[] = [ [ $data ] ];
		}
		return $return_data;
	}

	public function test_get_payment_method_ids_enabled_at_checkout_jp() {
		$this->mock_upe_gateway->update_option( 'upe_enabled_payment_method_ids', [ Payment_Method::CARD, Payment_Method::JCB ] );
		$this->set_get_upe_enabled_payment_method_statuses_return_value();
		$this->assertSame( [ 'card' ], $this->mock_upe_gateway->get_payment_method_ids_enabled_at_checkout() );
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

	private function set_get_upe_enabled_payment_method_statuses_return_value( $return_value = null ) {
		if ( null === $return_value ) {
			$return_value = [
				'card_payments' => [
					'status' => 'active',
				],
			];
		}
		$this->mock_upe_gateway
			->expects( $this->any() )
			->method( 'get_upe_enabled_payment_method_statuses' )
			->will( $this->returnValue( $return_value ) );
	}
}

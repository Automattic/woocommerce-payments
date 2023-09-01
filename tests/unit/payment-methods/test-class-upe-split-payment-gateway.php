<?php
/**
 * Class UPE_Split_Payment_Gateway_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Payment_Methods;

use Exception;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Intent_Status;
use WCPay\Core\Server\Request\Get_Request;
use WCPay\Core\Server\Request\Get_Setup_Intention;
use WCPay\Exceptions\Amount_Too_Small_Exception;
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
use WCPay\Constants\Payment_Method;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Core\Server\Request\Create_Setup_Intention;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Core\Server\Request\Update_Intention;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\WC_Payments_Checkout;
use WCPay\Payment_Information;
use WC_Payments;
use WC_Payments_Localization_Service;

require_once dirname( __FILE__ ) . '/../helpers/class-wc-helper-site-currency.php';

/**
 * UPE_Split_Payment_Gateway unit tests
 */
class UPE_Split_Payment_Gateway_Test extends WCPAY_UnitTestCase {

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
	 * Array of mock UPE payment gateways created by individual payment methods.
	 *
	 * @var array
	 */
	private $mock_payment_gateways;

	/**
	 * WC_Payments_Checkout
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
	 * WooPay_Utilities instance.
	 *
	 * @var WooPay_Utilities
	 */
	private $mock_woopay_utilities;

	/**
	 * Duplicate Payments Prevention Service.
	 *
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
					'get_payment_method',
					'is_server_connected',
					'get_charge',
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

		$this->mock_legacy_checkout = $this->getMockBuilder( WC_Payments_Checkout::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_rate_limiter = $this->createMock( Session_Rate_Limiter::class );
		$this->order_service     = new WC_Payments_Order_Service( $this->mock_api_client );

		$this->mock_dpps = $this->createMock( Duplicate_Payment_Prevention_Service::class );

		$this->mock_localization_service = $this->createMock( WC_Payments_Localization_Service::class );

		// Arrange: Define a $_POST array which includes the payment method,
		// so that get_payment_method_from_request() does not throw error.
		$_POST = [
			'wcpay-payment-method' => 'pm_mock',
			'payment_method'       => UPE_Split_Payment_Gateway::GATEWAY_ID,
		];

		$get_payment_gateway_by_id_return_value_map = [];

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
						'get_payment_method_ids_enabled_at_checkout',
						'wc_payments_get_payment_gateway_by_id',
						'get_selected_payment_method',
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

			$get_payment_gateway_by_id_return_value_map[] = [ $payment_method_id, $mock_gateway ];

			WC_Helper_Site_Currency::$mock_site_currency = '';

			update_option( '_wcpay_feature_upe_split', '1' );
		}

		foreach ( $this->mock_payment_gateways as $id => $mock_gateway ) {
			$mock_gateway->expects( $this->any() )
				->method( 'wc_payments_get_payment_gateway_by_id' )
				->will(
					$this->returnValueMap( $get_payment_gateway_by_id_return_value_map )
				);
		}
	}

	/**
	 * Cleanup after tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();
		update_option( '_wcpay_feature_upe_split', '0' );
		update_option( '_wcpay_feature_upe_deferred_intent', '0' );
	}

	/**
	 * Test the UI <div> container that will hold the payment method.
	 *
	 * @return void
	 */
	public function test_payment_fields_outputs_fields() {
		$checkout = new WC_Payments_UPE_Checkout(
			$this->mock_payment_gateways[ Payment_Method::CARD ],
			$this->mock_woopay_utilities,
			$this->mock_wcpay_account,
			$this->mock_customer_service
		);
		$checkout->init_hooks();

		$registered_card_gateway = WC_Payments::get_registered_card_gateway();
		WC_Payments::set_registered_card_gateway( $this->mock_payment_gateways[ Payment_Method::CARD ] );

		foreach ( $this->mock_payment_gateways as $payment_method_id => $mock_payment_gateway ) {
			$mock_payment_gateway
				->method( 'get_payment_method_ids_enabled_at_checkout' )
				->willReturn( [] );
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

		WC_Payments::set_registered_card_gateway( $registered_card_gateway );
	}

	public function test_should_not_use_stripe_platform_on_checkout_page_for_upe() {
		$payment_gateway = $this->mock_payment_gateways[ Payment_Method::SEPA ];
		$this->assertFalse( $payment_gateway->should_use_stripe_platform_on_checkout_page() );
	}

	public function test_non_reusable_payment_method_is_not_available_when_subscription_is_in_cart() {
		$non_reusable_payment_method = Payment_Method::BANCONTACT;
		$payment_gateway             = $this->mock_payment_gateways[ $non_reusable_payment_method ];

		$this->set_cart_contains_subscription_items( true );

		$this->assertFalse( $payment_gateway->is_available() );
	}

	public function test_update_payment_intent_adds_customer_save_payment_and_level3_data() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$order_number        = $order->get_order_number();
		$product_item        = current( $order->get_items( 'line_item' ) );
		$user                = '';
		$customer_id         = 'cus_mock';
		$save_payment_method = true;
		$intent              = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );

		$this->set_cart_contains_subscription_items( false );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$metadata = [
			'customer_name'        => 'Jeroen Sormani',
			'customer_email'       => 'admin@example.org',
			'site_url'             => 'http://example.org',
			'order_id'             => $order_id,
			'order_number'         => $order_number,
			'order_key'            => $order->get_order_key(),
			'payment_type'         => Payment_Type::SINGLE(),
			'gateway_type'         => 'split_upe',
			'checkout_type'        => '',
			'client_version'       => WCPAY_VERSION_NUMBER,
			'subscription_payment' => 'no',
		];

		$level3 = [
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
		];

		// Test update_payment_intent on each payment gateway.
		foreach ( $this->mock_payment_gateways as $mock_payment_gateway ) {
			$request = $this->mock_wcpay_request( Update_Intention::class, 1, $intent->get_id() );
			$request->expects( $this->once() )->method( 'set_amount' )->with( 5000 );
			$request->expects( $this->once() )->method( 'set_currency_code' )->with( 'usd' );
			$request->expects( $this->once() )->method( 'setup_future_usage' );
			$request->expects( $this->once() )->method( 'set_customer' )->with( 'cus_mock' );
			$request->expects( $this->once() )->method( 'set_metadata' )->with( $metadata );
			$request->expects( $this->once() )->method( 'set_level3' )->with( $level3 );
			$request->expects( $this->once() )
				->method( 'format_response' )
				->willReturn( $intent );

			$mock_payment_gateway
				->method( 'manage_customer_details_for_order' )
				->will(
					$this->returnValue( [ $user, $customer_id ] )
				);
			$result = $mock_payment_gateway->update_payment_intent( $intent->get_id(), $order_id, $save_payment_method );
			$this->assertSame( [ 'success' => true ], $result );
		}
	}

	public function test_update_payment_intent_with_selected_upe_payment_method() {
		$order               = WC_Helper_Order::create_order();
		$order_id            = $order->get_id();
		$order_number        = $order->get_order_number();
		$product_item        = current( $order->get_items( 'line_item' ) );
		$user                = '';
		$customer_id         = 'cus_mock';
		$save_payment_method = true;
		$intent              = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );

		$this->set_cart_contains_subscription_items( false );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$metadata = [
			'customer_name'        => 'Jeroen Sormani',
			'customer_email'       => 'admin@example.org',
			'site_url'             => 'http://example.org',
			'order_id'             => $order_id,
			'order_number'         => $order_number,
			'order_key'            => $order->get_order_key(),
			'payment_type'         => Payment_Type::SINGLE(),
			'gateway_type'         => 'split_upe',
			'checkout_type'        => '',
			'client_version'       => WCPAY_VERSION_NUMBER,
			'subscription_payment' => 'no',

		];

		$level3 = [
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
		];

		/**
		* In order to test each gateway, we need to setup mock_api_client so that
		* its input are mocked in sequence, matching the gateways.
		*/
		foreach ( $this->mock_payment_gateways as $payment_method_id => $mock_payment_gateway ) {
			$request = $this->mock_wcpay_request( Update_Intention::class, 1, $intent->get_id() );
			$request->expects( $this->once() )->method( 'set_amount' )->with( 5000 );
			$request->expects( $this->once() )->method( 'set_currency_code' )->with( 'usd' );
			$request->expects( $this->once() )->method( 'setup_future_usage' );
			$request->expects( $this->once() )->method( 'set_customer' )->with( 'cus_mock' );
			$request->expects( $this->once() )->method( 'set_metadata' )->with( $metadata );
			$request->expects( $this->once() )->method( 'set_level3' )->with( $level3 );
			$request->expects( $this->once() )->method( 'set_payment_method_types' )->with( [ $payment_method_id ] );

			$request->expects( $this->once() )
				->method( 'format_response' )
				->willReturn( $intent );

			// Test update_payment_intent on each payment gateway.
			$mock_payment_gateway
				->method( 'manage_customer_details_for_order' )
				->will(
					$this->returnValue( [ $user, $customer_id ] )
				);
			$result = $mock_payment_gateway->update_payment_intent( $intent->get_id(), $order_id, $save_payment_method, $payment_method_id );
			$this->assertSame( [ 'success' => true ], $result );
		}
	}

	public function test_update_payment_intent_with_payment_country() {
		$order        = WC_Helper_Order::create_order();
		$order_id     = $order->get_id();
		$order_number = $order->get_order_number();
		$product_item = current( $order->get_items( 'line_item' ) );
		$intent       = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );

		$this->set_cart_contains_subscription_items( false );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$metadata = [
			'customer_name'        => 'Jeroen Sormani',
			'customer_email'       => 'admin@example.org',
			'site_url'             => 'http://example.org',
			'order_id'             => $order_id,
			'order_number'         => $order_number,
			'order_key'            => $order->get_order_key(),
			'payment_type'         => Payment_Type::SINGLE(),
			'gateway_type'         => 'split_upe',
			'checkout_type'        => '',
			'client_version'       => WCPAY_VERSION_NUMBER,
			'subscription_payment' => 'no',
		];

		$level3 = [
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
		];

		// Test update_payment_intent on each payment gateway.
		foreach ( $this->mock_payment_gateways as $mock_payment_gateway ) {
			$request = $this->mock_wcpay_request( Update_Intention::class, 1, $intent->get_id() );
			$request->expects( $this->once() )->method( 'set_amount' )->with( 5000 );
			$request->expects( $this->once() )->method( 'set_currency_code' )->with( 'usd' );
			$request->expects( $this->once() )->method( 'set_customer' )->with( 'cus_mock' );
			$request->expects( $this->once() )->method( 'set_metadata' )->with( $metadata );
			$request->expects( $this->once() )->method( 'set_level3' )->with( $level3 );
			$request->expects( $this->once() )->method( 'set_payment_country' )->with( 'US' );
			$request->expects( $this->once() )
				->method( 'format_response' )
				->willReturn( $intent );

			$mock_payment_gateway
				->method( 'manage_customer_details_for_order' )
				->will(
					$this->returnValue( [ '', 'cus_mock' ] )
				);
			$result = $mock_payment_gateway->update_payment_intent( $intent->get_id(), $order_id, false, null, 'US' );
			$this->assertSame( [ 'success' => true ], $result );
		}
	}

	public function test_create_payment_intent_uses_order_amount_if_order() {
		$mock_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );

		$request = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( 5000 );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->set_cart_contains_subscription_items( false );

		$mock_payment_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::CARD ] );

		$this->set_get_upe_enabled_payment_method_statuses_return_value( $mock_payment_gateway );
		$mock_payment_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_payment_intent_defaults_to_automatic_capture() {
		$mock_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );

		$request = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )->method( 'set_capture_method' )->with( false );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$mock_payment_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::CARD ] );

		$this->set_get_upe_enabled_payment_method_statuses_return_value( $mock_payment_gateway );

		$mock_payment_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_payment_intent_with_automatic_capture() {
		$mock_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );
		$mock_payment_gateway->settings['manual_capture'] = 'no';
		$request = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )->method( 'set_capture_method' )->with( false );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$mock_payment_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::CARD ] );

		$this->set_get_upe_enabled_payment_method_statuses_return_value( $mock_payment_gateway );

		$mock_payment_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_payment_intent_with_manual_capture() {
		$mock_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();
		$intent   = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_PAYMENT_METHOD ] );
		$mock_payment_gateway->settings['manual_capture'] = 'yes';

		$request = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )->method( 'set_capture_method' )->with( true );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$mock_payment_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::CARD ] );

		$this->set_get_upe_enabled_payment_method_statuses_return_value( $mock_payment_gateway );

		$mock_payment_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_payment_intent_with_fingerprint() {
		$order                     = WC_Helper_Order::create_order();
		$order_id                  = $order->get_id();
		$fingerprint               = 'abc123';
		$intent                    = WC_Helper_Intention::create_intention();
		$mock_card_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

		$request = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )->method( 'set_fingerprint' )->with( $fingerprint );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$mock_card_payment_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::CARD ] );
		$this->set_get_upe_enabled_payment_method_statuses_return_value( $mock_card_payment_gateway );

		$mock_card_payment_gateway->create_payment_intent( [ 'card' ], $order_id, $fingerprint );
	}

	public function test_create_payment_intent_with_no_fingerprint() {
		$mock_card_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];
		$order                     = WC_Helper_Order::create_order();
		$order_id                  = $order->get_id();
		$intent                    = WC_Helper_Intention::create_intention();

		$request = $this->mock_wcpay_request( Create_Intention::class );
		$request->expects( $this->once() )->method( 'set_fingerprint' )->with( '' );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$mock_card_payment_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::CARD ] );
		$this->set_get_upe_enabled_payment_method_statuses_return_value( $mock_card_payment_gateway );

		$mock_card_payment_gateway->create_payment_intent( [ 'card' ], $order_id );
	}

	public function test_create_setup_intent_existing_customer() {
		$mock_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

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

		$result = $mock_payment_gateway->create_setup_intent( [ 'card' ] );

		$this->assertEquals( 'seti_mock', $result['id'] );
		$this->assertEquals( 'client_secret_mock', $result['client_secret'] );
	}

	public function test_create_setup_intent_no_customer() {
		$mock_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

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

		$result = $mock_payment_gateway->create_setup_intent( [ 'card' ] );

		$this->assertEquals( 'seti_mock', $result['id'] );
		$this->assertEquals( 'client_secret_mock', $result['client_secret'] );
	}

	public function test_process_payment_returns_correct_redirect_url() {
		$order                         = WC_Helper_Order::create_order();
		$order_id                      = $order->get_id();
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$payment_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::PROCESSING ] );

		$this->set_cart_contains_subscription_items( false );

		foreach ( $this->mock_payment_gateways as $mock_payment_gateway ) {
			$this->mock_wcpay_request( Update_Intention::class, 1, $payment_intent->get_id() )
				->expects( $this->once() )
				->method( 'format_response' )
				->willReturn( $payment_intent );

			$result = $mock_payment_gateway->process_payment( $order->get_id() );
			$this->assertEquals( 'success', $result['result'] );
			$this->assertEquals( true, $result['payment_needed'] );
			$this->assertMatchesRegularExpression( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
			$this->assertMatchesRegularExpression( '/save_payment_method=no/', $result['redirect_url'] );
		}

		unset( $_POST['wc_payment_intent_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public function test_process_payment_passes_save_payment_method_to_store() {
		$mock_sepa_payment_gateway = $this->mock_payment_gateways[ Payment_Method::SEPA ];

		$order                         = WC_Helper_Order::create_order();
		$gateway_id                    = UPE_Split_Payment_Gateway::GATEWAY_ID . '_' . Payment_Method::SEPA;
		$save_payment_param            = "wc-$gateway_id-new-payment-method";
		$_POST[ $save_payment_param ]  = 'yes';
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$payment_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::PROCESSING ] );

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
		$this->assertMatchesRegularExpression( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=yes/', $result['redirect_url'] );

		unset( $_POST[ $save_payment_param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST['wc_payment_intent_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public function test_process_subscription_payment_passes_save_payment_method() {
		$mock_card_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];
		$mock_sepa_payment_gateway = $this->mock_payment_gateways[ Payment_Method::SEPA ];

		$order                         = WC_Helper_Order::create_order();
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$payment_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::PROCESSING ] );

		// Test card.
		$this->mock_wcpay_request( Update_Intention::class, 1, $payment_intent->get_id() )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				$payment_intent
			);

		$mock_card_payment_gateway
			->expects( $this->once() )
			->method( 'is_payment_recurring' )
			->willReturn( true );
		$result = $mock_card_payment_gateway->process_payment( $order->get_id() );
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( true, $result['payment_needed'] );
		$this->assertMatchesRegularExpression( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=yes/', $result['redirect_url'] );

		// Test SEPA.
		$this->mock_wcpay_request( Update_Intention::class, 1, $payment_intent->get_id() )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				$payment_intent
			);

		$mock_sepa_payment_gateway
			->expects( $this->once() )
			->method( 'is_payment_recurring' )
			->willReturn( true );
		$result = $mock_sepa_payment_gateway->process_payment( $order->get_id() );
		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( true, $result['payment_needed'] );
		$this->assertMatchesRegularExpression( '/wc_payment_method=woocommerce_payments/', $result['redirect_url'] );
		$this->assertMatchesRegularExpression( '/save_payment_method=yes/', $result['redirect_url'] );

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

	public function test_process_payment_returns_correct_redirect_when_using_payment_request() {
		$mock_card_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

		$order                         = WC_Helper_Order::create_order();
		$_POST['payment_request_type'] = 'google_pay';

		$this->set_cart_contains_subscription_items( false );

		$result = $mock_card_payment_gateway->process_payment( $order->get_id() );

		$mock_card_payment_gateway
			->expects( $this->never() )
			->method( 'manage_customer_details_for_order' );
		$this->assertEquals( 'success', $result['result'] );
		$this->assertMatchesRegularExpression( '/key=mock_order_key/', $result['redirect'] );
	}

	public function test_upe_process_payment_check_session_order_redirect_to_previous_order() {
		$_POST['wc_payment_intent_id'] = 'pi_mock';
		$mock_upe_gateway              = $this->mock_payment_gateways[ Payment_Method::SEPA ];

		$response = [
			'dummy_result' => 'xyz',
		];

		// Arrange the order is being processed.
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// Arrange the DPPs to return a redirect.
		$this->mock_dpps->expects( $this->once() )
			->method( 'check_against_session_processing_order' )
			->with( wc_get_order( $order ) )
			->willReturn( $response );

		// Act: process the order but redirect to the previous/session paid order.
		$result = $mock_upe_gateway->process_payment( $order_id );

		// Assert: the result of check_against_session_processing_order.
		$this->assertSame( $response, $result );
	}

	public function test_upe_process_payment_check_session_with_failed_intent_then_order_id_saved_to_session() {
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		// Arrange the order is being processed.
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// Arrange a failed intention.
		$intent = WC_Helper_Intention::create_intention( [ 'status' => 'failed' ] );

		// Assert.
		$this->mock_wcpay_request( Update_Intention::class, 1, $intent->get_id() )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		// Make sure the DPPS will store the order.
		$this->mock_dpps->expects( $this->once() )
			->method( 'maybe_update_session_processing_order' )
			->with( $order_id );

		// Act: process the order but redirect to the previous/session paid order.
		$this->mock_payment_gateways[ Payment_Method::SEPA ]->process_payment( $order_id );
	}

	public function test_upe_process_payment_check_session_and_continue_processing() {
		$_POST['wc_payment_intent_id'] = 'pi_mock';
		$mock_upe_gateway              = $this->mock_payment_gateways[ Payment_Method::SEPA ];

		// Arrange the order is being processed.
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// Arrange a successful intention.
		$intent = WC_Helper_Intention::create_intention();

		$mock_upe_gateway
			->expects( $this->once() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [] );

		// Arrange the DPPs not to return anything.
		$this->mock_dpps->expects( $this->once() )
			->method( 'check_against_session_processing_order' )
			->with( wc_get_order( $order ) )
			->willReturn( null );

		$this->mock_wcpay_request( Update_Intention::class, 1, $intent->get_id() )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		// Act.
		$mock_upe_gateway->process_payment( $order_id );
	}

	public function test_upe_check_payment_intent_attached_to_order_succeeded_with_invalid_intent_id_continue_process_payment() {
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		// Arrange order.
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// Arrange the DPPS not to return a redirect.
		$this->mock_dpps->expects( $this->once() )
			->method( 'check_payment_intent_attached_to_order_succeeded' )
			->with( wc_get_order( $order ) )
			->willReturn( null );

		// Assert: the payment process continues.
		$intent = WC_Helper_Intention::create_intention();
		$this->mock_wcpay_request( Update_Intention::class, 1, $intent->get_id() )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		// Act: process the order.
		$this->mock_payment_gateways[ Payment_Method::SEPA ]->process_payment( $order_id );
	}

	public function test_upe_check_payment_intent_attached_to_order_succeeded_return_redirection() {
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$response = [
			'dummy_result' => 'xyz',
		];

		// Arrange order.
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		// Arrange the DPPS to return a redirect based on a redirect.
		$this->mock_dpps->expects( $this->once() )
			->method( 'check_payment_intent_attached_to_order_succeeded' )
			->with( wc_get_order( $order ) )
			->willReturn( $response );

		// Assert: no more call to the server to update the intention.
		$this->mock_wcpay_request( Update_Intention::class, 0 );

		// Act: process the order but redirect to the order.
		$result = $this->mock_payment_gateways[ Payment_Method::SEPA ]->process_payment( $order_id );

		// Assert: the result of check_intent_attached_to_order_succeeded.
		$this->assertSame( $response, $result );
	}

	public function test_process_redirect_payment_intent_processing() {

		$mock_upe_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];
		$order            = WC_Helper_Order::create_order();

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

		$card_method = $this->mock_payment_methods['card'];

		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => $intent_status,
				'metadata' => $intent_metadata,
			]
		);

		$mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$mock_upe_gateway->expects( $this->any() )
			->method( 'get_selected_payment_method' )
			->willReturn( $card_method );

		$this->mock_wcpay_request( Get_Intention::class, 1, $intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $payment_intent );

		$this->set_cart_contains_subscription_items( false );

		$mock_upe_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

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

		$mock_upe_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];
		$order            = WC_Helper_Order::create_order();

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

		$card_method = $this->mock_payment_methods['card'];

		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => $intent_status,
				'metadata' => $intent_metadata,
			]
		);

		$mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$this->mock_wcpay_request( Get_Intention::class, 1, $intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $payment_intent );

		$mock_upe_gateway->expects( $this->any() )
			->method( 'get_selected_payment_method' )
			->willReturn( $card_method );

		$this->set_cart_contains_subscription_items( false );

		$mock_upe_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );

		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $charge_id, $result_order->get_meta( '_charge_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( Order_Status::PROCESSING, $result_order->get_status() );
	}

	public function is_proper_intent_used_with_order_returns_false() {
		$this->assertFalse( $this->mock_upe_gateway->is_proper_intent_used_with_order( WC_Helper_Order::create_order(), 'wrong_intent_id' ) );
	}

	public function test_process_redirect_setup_intent_succeded() {

		$order            = WC_Helper_Order::create_order();
		$mock_upe_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

		$order_id            = $order->get_id();
		$save_payment_method = true;
		$user                = wp_get_current_user();
		$intent_status       = Intent_Status::SUCCEEDED;
		$intent_metadata     = [ 'order_id' => (string) $order_id ];
		$client_secret       = 'cs_mock';
		$customer_id         = 'cus_mock';
		$intent_id           = 'si_mock';
		$payment_method_id   = 'pm_mock';
		$token               = WC_Helper_Token::create_token( $payment_method_id );

		// Supply the order with the intent id so that it can be retrieved during the redirect payment processing.
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->save();

		$card_method = $this->mock_payment_methods['card'];

		$order->set_shipping_total( 0 );
		$order->set_shipping_tax( 0 );
		$order->set_cart_tax( 0 );
		$order->set_total( 0 );
		$order->save();

		$setup_intent = WC_Helper_Intention::create_setup_intention(
			[
				'id'                     => 'pi_mock',
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

		$mock_upe_gateway->expects( $this->once() )
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

		$mock_upe_gateway->expects( $this->any() )
			->method( 'get_selected_payment_method' )
			->willReturn( $card_method );

		$this->set_cart_contains_subscription_items( true );

		$mock_upe_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );

		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( Order_Status::PROCESSING, $result_order->get_status() );
		$this->assertEquals( 1, count( $result_order->get_payment_tokens() ) );
	}

	public function test_process_redirect_payment_save_payment_token() {

		$mock_upe_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

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

		$card_method = $this->mock_payment_methods['card'];

		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => $intent_status,
				'metadata' => $intent_metadata,
			]
		);

		$mock_upe_gateway->expects( $this->once() )
			->method( 'manage_customer_details_for_order' )
			->will(
				$this->returnValue( [ $user, $customer_id ] )
			);

		$this->mock_wcpay_request( Get_Intention::class, 1, $intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $payment_intent );

		$this->mock_token_service->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->will(
				$this->returnValue( $token )
			);

		$mock_upe_gateway->expects( $this->any() )
			->method( 'get_selected_payment_method' )
			->willReturn( $card_method );

		$this->set_cart_contains_subscription_items( false );

		$mock_upe_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

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
			$payment_method_id = $payment_method_details['type'];
			$mock_upe_gateway  = $this->mock_payment_gateways[ $payment_method_id ];
			$payment_method    = $this->mock_payment_methods[ $payment_method_id ];
			$mock_upe_gateway->expects( $this->any() )
				->method( 'get_selected_payment_method' )
				->willReturn( $payment_method );
			$mock_upe_gateway->set_payment_method_title_for_order( $order, $payment_method_id, $payment_method_details );
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
	}

	public function test_only_reusabled_payment_methods_enabled_with_subscription_item_present() {
		// Setup $this->mock_payment_methods.

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

		$this->assertTrue( $card_method->is_enabled_at_checkout() );
		$this->assertFalse( $giropay_method->is_enabled_at_checkout() );
		$this->assertFalse( $sofort_method->is_enabled_at_checkout() );
		$this->assertFalse( $bancontact_method->is_enabled_at_checkout() );
		$this->assertFalse( $eps_method->is_enabled_at_checkout() );
		$this->assertTrue( $sepa_method->is_enabled_at_checkout() );
		$this->assertFalse( $p24_method->is_enabled_at_checkout() );
		$this->assertFalse( $ideal_method->is_enabled_at_checkout() );
		$this->assertFalse( $becs_method->is_enabled_at_checkout() );
	}

	public function test_only_valid_payment_methods_returned_for_currency() {
		// Setup $this->mock_payment_methods.

		$card_method       = $this->mock_payment_methods['card'];
		$giropay_method    = $this->mock_payment_methods['giropay'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$ideal_method      = $this->mock_payment_methods['ideal'];
		$becs_method       = $this->mock_payment_methods['au_becs_debit'];

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

		WC_Helper_Site_Currency::$mock_site_currency = 'AUD';
		$this->assertTrue( $becs_method->is_currency_valid( $account_domestic_currency ) );

		WC_Helper_Site_Currency::$mock_site_currency = '';
	}

	public function test_create_token_from_setup_intent_adds_token() {

		$mock_token           = WC_Helper_Token::create_token( 'pm_mock' );
		$mock_setup_intent_id = 'si_mock';
		$mock_user            = wp_get_current_user();

		$this->mock_token_service
			->method( 'add_payment_method_to_user' )
			->with( 'pm_mock', $mock_user )
			->will(
				$this->returnValue( $mock_token )
			);

		foreach ( $this->mock_payment_gateways as $mock_upe_gateway ) {
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
			$this->assertEquals( $mock_token, $mock_upe_gateway->create_token_from_setup_intent( $mock_setup_intent_id, $mock_user ) );
		}
	}

	public function test_create_payment_intent_uses_cached_minimum_amount() {
		$mock_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

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
		$request->expects( $this->once() )->method( 'set_amount' )->with( 50 );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$mock_payment_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::CARD ] );

		$this->set_get_upe_enabled_payment_method_statuses_return_value( $mock_payment_gateway );

		$mock_payment_gateway->create_payment_intent( [ 'card' ], $order->get_id() );
	}

	public function test_create_payment_intent_creates_new_intent_with_minimum_amount() {
		$mock_payment_gateway = $this->mock_payment_gateways[ Payment_Method::CARD ];

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
		$request->expects( $this->exactly( 2 ) )->method( 'set_amount' )->withConsecutive( [ 45 ], [ 50 ] );
		$request->expects( $this->exactly( 2 ) )
			->method( 'format_response' )
			->will(
				$this->onConsecutiveCalls(
					$this->throwException( new Amount_Too_Small_Exception( 'Error: Amount must be at least $0.50 usd', 50, 'usd', 400 ) ),
					$this->returnValue( $intent )
				)
			);

		$mock_payment_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::CARD ] );

		$this->set_get_upe_enabled_payment_method_statuses_return_value( $mock_payment_gateway );

		$result = $mock_payment_gateway->create_payment_intent( [ 'card' ], $order->get_id() );
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

		foreach ( $this->mock_payment_gateways as $mock_payment_gateway ) {
			$this->expectException( Exception::class );
			$this->expectExceptionMessage( $message );
			$mock_payment_gateway->process_payment( $order->get_id() );
		}
	}

	public function test_process_payment_caches_mimimum_amount_and_displays_error_upon_exception() {
		$order = WC_Helper_Order::create_order();
		$order->set_total( 0.45 );
		$order->save();

		delete_transient( 'wcpay_minimum_amount_usd' );
		$_POST['wc_payment_intent_id'] = 'pi_mock';

		$price   = wp_strip_all_tags( html_entity_decode( wc_price( 60, [ 'currency' => 'USD' ] ) ) );
		$message = 'The selected payment method requires a total amount of at least ' . $price . '.';
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $message );

		try {
			foreach ( $this->mock_payment_gateways as $mock_payment_gateway ) {
				$this->mock_wcpay_request( Update_Intention::class, 1, 'pi_mock' )
					->expects( $this->once() )
					->method( 'format_response' )
					->will( $this->throwException( new Amount_Too_Small_Exception( 'Error: Amount must be at least $60 usd', 6000, 'usd', 400 ) ) );

				$mock_payment_gateway->process_payment( $order->get_id() );

				break;
			}
		} catch ( Exception $e ) {
			$this->assertEquals( '6000', get_transient( 'wcpay_minimum_amount_usd' ) );
			throw $e;
		}
	}

	public function test_no_save_option_for_non_sepa_upe() {
		$payment_methods_with_no_save_option = [
			Payment_Method::BANCONTACT,
			Payment_Method::EPS,
			Payment_Method::GIROPAY,
			Payment_Method::IDEAL,
			Payment_Method::P24,
			Payment_Method::SOFORT,
		];

		foreach ( $payment_methods_with_no_save_option as $payment_method ) {
			$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
				->setConstructorArgs(
					[
						$this->mock_api_client,
						$this->mock_wcpay_account,
						$this->mock_customer_service,
						$this->mock_token_service,
						$this->mock_action_scheduler_service,
						$this->mock_payment_methods[ $payment_method ],
						$this->mock_payment_methods,
						$this->mock_rate_limiter,
						$this->order_service,
						$this->mock_dpps,
						$this->mock_localization_service,
					]
				)
				->setMethods(
					[
						'get_payment_method_ids_enabled_at_checkout',
						'wc_payments_get_payment_method_by_id',
						'wc_payments_get_payment_gateway_by_id',
						'is_saved_cards_enabled',
						'is_subscription_item_in_cart',
					]
				)
				->getMock();

			$mock_upe_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
				->willReturn( [ $payment_method ] );

			$mock_upe_gateway
				->method( 'wc_payments_get_payment_method_by_id' )
				->with( $payment_method )
				->willReturn( $this->mock_payment_methods[ $payment_method ] );

			$mock_upe_gateway
				->method( 'wc_payments_get_payment_gateway_by_id' )
				->with( $payment_method )
				->willReturn( $this->mock_payment_gateways[ $payment_method ] );

			$upe_checkout = new WC_Payments_UPE_Checkout(
				$mock_upe_gateway,
				$this->mock_woopay_utilities,
				$this->mock_wcpay_account,
				$this->mock_customer_service
			);

			$this->assertSame( $upe_checkout->get_payment_fields_js_config()['paymentMethodsConfig'][ $payment_method ]['showSaveOption'], false );
		}
	}

	public function test_no_save_option_for_sepa_due_to_subscription_cart() {
		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods[ Payment_Method::SEPA ],
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods(
				[
					'get_payment_method_ids_enabled_at_checkout',
					'wc_payments_get_payment_method_by_id',
					'wc_payments_get_payment_gateway_by_id',
					'is_saved_cards_enabled',
					'is_subscription_item_in_cart',
				]
			)
			->getMock();

		// saved cards enabled.
		$mock_upe_gateway
			->method( 'is_saved_cards_enabled' )
			->will(
				$this->returnValue( true )
			);

		// there is a subscription item in cart, which should disable the save option checkbox for a payment method.
		$mock_upe_gateway
			->method( 'is_subscription_item_in_cart' )
			->will(
				$this->returnValue( true )
			);

		$mock_upe_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::SEPA ] );

		$mock_upe_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->with( Payment_Method::SEPA )
			->willReturn( $this->mock_payment_methods[ Payment_Method::SEPA ] );

		$mock_upe_gateway
			->method( 'wc_payments_get_payment_gateway_by_id' )
			->with( Payment_Method::SEPA )
			->willReturn( $this->mock_payment_gateways[ Payment_Method::SEPA ] );

		$upe_checkout = new WC_Payments_UPE_Checkout(
			$mock_upe_gateway,
			$this->mock_woopay_utilities,
			$this->mock_wcpay_account,
			$this->mock_customer_service
		);

		$this->assertSame( $upe_checkout->get_payment_fields_js_config()['paymentMethodsConfig'][ Payment_Method::SEPA ]['showSaveOption'], false );
	}

	public function test_no_save_option_for_sepa_due_to_saved_cards_disabled() {
		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods[ Payment_Method::SEPA ],
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods(
				[
					'get_payment_method_ids_enabled_at_checkout',
					'wc_payments_get_payment_method_by_id',
					'wc_payments_get_payment_gateway_by_id',
					'is_saved_cards_enabled',
					'is_subscription_item_in_cart',
				]
			)
			->getMock();

		// saved cards disabled.
		$mock_upe_gateway
			->method( 'is_saved_cards_enabled' )
			->will(
				$this->returnValue( false )
			);

		// no subscription item in cart.
		$mock_upe_gateway
			->method( 'is_subscription_item_in_cart' )
			->will(
				$this->returnValue( false )
			);

		$mock_upe_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::SEPA ] );

		$mock_upe_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->with( Payment_Method::SEPA )
			->willReturn( $this->mock_payment_methods[ Payment_Method::SEPA ] );

		$mock_upe_gateway
			->method( 'wc_payments_get_payment_gateway_by_id' )
			->with( Payment_Method::SEPA )
			->willReturn( $this->mock_payment_gateways[ Payment_Method::SEPA ] );

		$upe_checkout = new WC_Payments_UPE_Checkout(
			$mock_upe_gateway,
			$this->mock_woopay_utilities,
			$this->mock_wcpay_account,
			$this->mock_customer_service
		);

		$this->assertSame( $upe_checkout->get_payment_fields_js_config()['paymentMethodsConfig'][ Payment_Method::SEPA ]['showSaveOption'], false );
	}

	public function test_save_option_for_sepa_debit() {
		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods[ Payment_Method::SEPA ],
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods(
				[
					'get_payment_method_ids_enabled_at_checkout',
					'wc_payments_get_payment_method_by_id',
					'wc_payments_get_payment_gateway_by_id',
					'is_saved_cards_enabled',
					'is_subscription_item_in_cart',
				]
			)
			->getMock();

		$mock_upe_gateway->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::SEPA ] );

		$mock_upe_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->with( Payment_Method::SEPA )
			->willReturn( $this->mock_payment_methods[ Payment_Method::SEPA ] );

		$mock_upe_gateway
			->method( 'wc_payments_get_payment_gateway_by_id' )
			->with( Payment_Method::SEPA )
			->willReturn( $this->mock_payment_gateways[ Payment_Method::SEPA ] );

		// saved cards enabled.
		$mock_upe_gateway
			->method( 'is_saved_cards_enabled' )
			->will(
				$this->returnValue( true )
			);

		// no subscription items in cart.
		$mock_upe_gateway
			->method( 'is_subscription_item_in_cart' )
			->will(
				$this->returnValue( false )
			);

		$upe_checkout = new WC_Payments_UPE_Checkout(
			$mock_upe_gateway,
			$this->mock_woopay_utilities,
			$this->mock_wcpay_account,
			$this->mock_customer_service
		);

		$this->assertSame( $upe_checkout->get_payment_fields_js_config()['paymentMethodsConfig'][ Payment_Method::SEPA ]['showSaveOption'], true );
	}

	public function test_remove_link_payment_method_if_card_disabled() {
		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods[ Payment_Method::LINK ],
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
					'wc_payments_get_payment_method_by_id',
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
		$mock_upe_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->with( Payment_Method::LINK )
			->willReturn( $this->mock_payment_methods[ Payment_Method::LINK ] );

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

		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods[ Payment_Method::CARD ],
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
					'wc_payments_get_payment_method_by_id',
					'wc_payments_get_payment_gateway_by_id',
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

		$mock_upe_gateway
			->method( 'wc_payments_get_payment_method_by_id' )
			->willReturnMap(
				[
					[ Payment_Method::CARD, $this->mock_payment_methods[ Payment_Method::CARD ] ],
					[ Payment_Method::LINK, $this->mock_payment_methods[ Payment_Method::LINK ] ],
				]
			);

		$mock_upe_gateway
			->method( 'wc_payments_get_payment_gateway_by_id' )
			->willReturnCallback(
				function ( $payment_method ) {
					if ( Payment_Method::CARD === $payment_method ) {
						return $this->mock_payment_gateways[ Payment_Method::CARD ];
					} elseif ( Payment_Method::LINK === $payment_method ) {
						return $this->mock_payment_gateways[ Payment_Method::LINK ];
					}
				}
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
					'isReusable'             => true,
					'title'                  => 'Credit card / debit card',
					'icon'                   => null,
					'showSaveOption'         => true,
					'countries'              => [],
					'upePaymentIntentData'   => null,
					'upeSetupIntentData'     => null,
					'testingInstructions'    => '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://woocommerce.com/document/woocommerce-payments/testing-and-troubleshooting/testing/#test-cards" target="_blank">here</a>.',
					'forceNetworkSavedCards' => false,
				],
				'link' => [
					'isReusable'             => true,
					'title'                  => 'Link',
					'icon'                   => $this->icon_url,
					'showSaveOption'         => true,
					'countries'              => [],
					'upePaymentIntentData'   => null,
					'upeSetupIntentData'     => null,
					'testingInstructions'    => '',
					'forceNetworkSavedCards' => false,
				],
			]
		);
	}

	public function test_remove_upe_setup_intent_from_session() {
		// Two payment methods (SEPA and giropay) are enabled.
		$sepa_setup_intent_key    = UPE_Split_Payment_Gateway::KEY_UPE_SETUP_INTENT . '_sepa_debit';
		$giropay_setup_intent_key = UPE_Split_Payment_Gateway::KEY_UPE_SETUP_INTENT . '_giropay';

		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods['card'],
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods( [ 'wc_payments_get_payment_method_map' ] )
			->getMock();

		$mock_upe_gateway
			->expects( $this->once() )
			->method( 'wc_payments_get_payment_method_map' )
			->will(
				$this->returnValue(
					[
						'sepa_debit' => $this->mock_payment_methods[ Payment_Method::SEPA ],
						'giropay'    => $this->mock_payment_methods[ Payment_Method::GIROPAY ],
					]
				)
			);

		// and both SEPA and giropay have a setup intent stored in WC session object.
		WC()->session->set( $sepa_setup_intent_key, 'pi_test_setup_intent_sepa_debit' );
		WC()->session->set( $giropay_setup_intent_key, 'pi_test_setup_intent_giropay' );

		$this->assertNotNull( WC()->session->get( $sepa_setup_intent_key ) );
		$this->assertNotNull( WC()->session->get( $giropay_setup_intent_key ) );

		$mock_upe_gateway->remove_upe_setup_intent_from_session();

		$this->assertNull( WC()->session->get( $sepa_setup_intent_key ) );
		$this->assertNull( WC()->session->get( $giropay_setup_intent_key ) );
	}

	/**
	 * Test get_payment_method_types with regular checkout post request context.
	 *
	 * @return void
	 */
	public function test_get_payment_methods_with_request_context() {
		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods[ Payment_Method::CARD ],
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods( [ 'get_payment_methods_from_gateway_id' ] )
			->getMock();

		$order               = WC_Helper_Order::create_order();
		$payment_information = new Payment_Information( 'pm_mock', $order );

		$_POST['payment_method'] = 'woocommerce_payments';

		$mock_upe_gateway->expects( $this->once() )
			->method( 'get_payment_methods_from_gateway_id' )
			->with( 'woocommerce_payments' )
			->will(
				$this->returnValue( [ Payment_Method::CARD ] )
			);

		$payment_methods = $mock_upe_gateway->get_payment_method_types( $payment_information );

		$this->assertSame( [ Payment_Method::CARD ], $payment_methods );

		unset( $_POST['payment_method'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Test get_payment_method_types without post request context.
	 *
	 * @return void
	 */
	public function test_get_payment_methods_without_request_context() {
		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods[ Payment_Method::CARD ],
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods( [ 'get_payment_methods_from_gateway_id' ] )
			->getMock();

		$token               = WC_Helper_Token::create_token( 'pm_mock' );
		$order               = WC_Helper_Order::create_order();
		$payment_information = new Payment_Information( 'pm_mock', $order, null, $token );

		unset( $_POST['payment_method'] ); // phpcs:ignore WordPress.Security.NonceVerification

		$mock_upe_gateway->expects( $this->once() )
			->method( 'get_payment_methods_from_gateway_id' )
			->with( $token->get_gateway_id(), $order->get_id() )
			->will(
				$this->returnValue( [ Payment_Method::CARD ] )
			);

		$payment_methods = $mock_upe_gateway->get_payment_method_types( $payment_information );

		$this->assertSame( [ Payment_Method::CARD ], $payment_methods );
	}

	/**
	 * Test get_payment_method_types without post request context or saved token.
	 *
	 * @return void
	 */
	public function test_get_payment_methods_without_request_context_or_token() {
		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods[ Payment_Method::CARD ],
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods(
				[
					'get_payment_methods_from_gateway_id',
					'get_payment_method_ids_enabled_at_checkout',
				]
			)
			->getMock();

		$payment_information = new Payment_Information( 'pm_mock' );

		unset( $_POST['payment_method'] ); // phpcs:ignore WordPress.Security.NonceVerification

		$gateway = WC_Payments::get_gateway();
		WC_Payments::set_gateway( $mock_upe_gateway );

		$mock_upe_gateway->expects( $this->never() )
			->method( 'get_payment_methods_from_gateway_id' );

		$mock_upe_gateway->expects( $this->once() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn( [ Payment_Method::CARD ] );

		$payment_methods = $mock_upe_gateway->get_payment_method_types( $payment_information );

		$this->assertSame( [ Payment_Method::CARD ], $payment_methods );

		WC_Payments::set_gateway( $gateway );
	}

	/**
	 * Test get_payment_methods_from_gateway_id function.
	 *
	 * @return void
	 */
	public function test_get_payment_methods_from_gateway_id() {
		$order            = WC_Helper_Order::create_order();
		$mock_upe_gateway = $this->getMockBuilder( UPE_Split_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_methods[ Payment_Method::CARD ],
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
				]
			)
			->setMethods(
				[
					'get_upe_enabled_payment_method_ids',
					'get_payment_method_ids_enabled_at_checkout',
				]
			)
			->getMock();

		$gateway = WC_Payments::get_gateway();
		WC_Payments::set_gateway( $mock_upe_gateway );

		$mock_upe_gateway->expects( $this->any() )
			->method( 'get_upe_enabled_payment_method_ids' )
			->will(
				$this->returnValue( [ Payment_Method::CARD, Payment_Method::LINK ] )
			);

		$payment_methods = $mock_upe_gateway->get_payment_methods_from_gateway_id( UPE_Split_Payment_Gateway::GATEWAY_ID );

		$this->assertSame( [ Payment_Method::CARD, Payment_Method::LINK ], $payment_methods );

		$payment_methods = $mock_upe_gateway->get_payment_methods_from_gateway_id( UPE_Split_Payment_Gateway::GATEWAY_ID . '_' . Payment_Method::BANCONTACT );

		$this->assertSame( [ Payment_Method::BANCONTACT ], $payment_methods );

		update_option( '_wcpay_feature_upe_deferred_intent', '1' );

		$payment_methods = $mock_upe_gateway->get_payment_methods_from_gateway_id( UPE_Split_Payment_Gateway::GATEWAY_ID );

		$this->assertSame( [ Payment_Method::CARD, Payment_Method::LINK ], $payment_methods );

		update_option( '_wcpay_feature_upe_split', '0' );
		update_option( '_wcpay_feature_upe_deferred_intent', '0' );

		$mock_upe_gateway->expects( $this->any() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->will(
				$this->returnValueMap(
					[
						[ null, true, [ Payment_Method::CARD, Payment_Method::BANCONTACT ] ],
						[ $order->get_id(), true, [ Payment_Method::CARD ] ],
					]
				)
			);

		$payment_methods = $mock_upe_gateway->get_payment_methods_from_gateway_id( UPE_Split_Payment_Gateway::GATEWAY_ID );

		$this->assertSame( [ Payment_Method::CARD, Payment_Method::BANCONTACT ], $payment_methods );

		$payment_methods = $mock_upe_gateway->get_payment_methods_from_gateway_id( UPE_Split_Payment_Gateway::GATEWAY_ID, $order->get_id() );

		$this->assertSame( [ Payment_Method::CARD ], $payment_methods );

		WC_Payments::set_gateway( $gateway );
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

	private function set_get_upe_enabled_payment_method_statuses_return_value( $mock_payment_gateway, $return_value = null ) {
		if ( null === $return_value ) {
			$return_value = [
				'card_payments' => [
					'status' => 'active',
				],
			];
		}
		$mock_payment_gateway
			->expects( $this->any() )
			->method( 'get_upe_enabled_payment_method_statuses' )
			->will( $this->returnValue( $return_value ) );
	}
}

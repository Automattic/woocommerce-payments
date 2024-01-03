<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Payment_Methods;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Payments_Fraud_Service;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Intent_Status;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Core\Server\Request\Get_Setup_Intention;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\Session_Rate_Limiter;
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
use WC_Payments;
use WCPay\Duplicate_Payment_Prevention_Service;
use WC_Payments_Localization_Service;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Database_Cache;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;

/**
 * WC_Payment_Gateway_WCPay unit tests
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
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $mock_gateway;

	/**
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock WC_Payments_Token_Service.
	 *
	 * @var WC_Payments_Token_Service|MockObject
	 */
	private $mock_token_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Action_Scheduler_Service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service|MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * Mock Session_Rate_Limiter.
	 *
	 * @var Session_Rate_Limiter|MockObject
	 */
	private $mock_rate_limiter;

	/**
	 * Mock WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service|MockObject
	 */
	private $mock_order_service;

	/**
	 * Array of mock UPE payment methods.
	 *
	 * @var array
	 */
	private $mock_payment_methods;

	/**
	 * Mock UPE payment method.
	 *
	 * @var UPE_Payment_Method|MockObject
	 */
	private $mock_payment_method;

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
	 * Mock Fraud Service.
	 *
	 * @var WC_Payments_Fraud_Service|MockObject;
	 */
	private $mock_fraud_service;

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
			->onlyMethods(
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

		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );

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
			->onlyMethods( [ 'add_payment_method_to_user' ] )
			->getMock();

		// Arrange: Mock WC_Payments_Action_Scheduler_Service so its methods aren't called directly.
		$this->mock_action_scheduler_service = $this->getMockBuilder( 'WC_Payments_Action_Scheduler_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_dpps = $this->createMock( Duplicate_Payment_Prevention_Service::class );

		$this->mock_localization_service = $this->createMock( WC_Payments_Localization_Service::class );
		$this->mock_fraud_service        = $this->createMock( WC_Payments_Fraud_Service::class );

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
				->onlyMethods( [ 'is_subscription_item_in_cart', 'get_icon' ] )
				->getMock();
			$this->mock_payment_methods[ $mock_payment_method->get_id() ] = $mock_payment_method;
		}

		$this->mock_order_service = $this->getMockBuilder( WC_Payments_Order_Service::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
				]
			)
			->onlyMethods(
				[
					'get_payment_method_id_for_order',
				]
			)
			->getMock();

		$this->mock_payment_method = $this->getMockBuilder( $payment_method_class )
			->setConstructorArgs( [ $this->mock_token_service ] )
			->onlyMethods( [ 'is_subscription_item_in_cart', 'get_icon' ] )
			->getMock();
		$this->mock_payment_methods[ $this->mock_payment_method->get_id() ] = $this->mock_payment_method;

		// Arrange: Mock WC_Payment_Gateway_WCPay so that some of its methods can be
		// mocked, and their return values can be used for testing.
		$this->mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->mock_payment_method,
					$this->mock_payment_methods,
					$this->mock_rate_limiter,
					$this->mock_order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
					$this->mock_fraud_service,
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
		$this->mock_gateway
			->expects( $this->any() )
			->method( 'get_return_url' )
			->will(
				$this->returnValue( $this->return_url )
			);
		$this->mock_gateway
			->expects( $this->any() )
			->method( 'parent_process_payment' )
			->will(
				$this->returnValue( $this->mock_payment_result )
			);

		// Arrange: Define a $_POST array which includes the payment method,
		// so that get_payment_method_from_request() does not throw error.
		$_POST = [
			'wcpay-payment-method' => 'pm_mock',
			'payment_method'       => WC_Payment_Gateway_WCPay::GATEWAY_ID,
		];

		// Mock the level3 service to always return an empty array.
		$mock_level3_service = $this->createMock( Level3Service::class );
		$mock_level3_service->expects( $this->any() )
			->method( 'get_data_from_order' )
			->willReturn( [] );
		wcpay_get_test_container()->replace( Level3Service::class, $mock_level3_service );

		// Mock the order service to always return an empty array for meta.
		$mock_order_service = $this->createMock( OrderService::class );
		$mock_order_service->expects( $this->any() )
			->method( 'get_payment_metadata' )
			->willReturn( [] );
		wcpay_get_test_container()->replace( OrderService::class, $mock_order_service );
	}

	/**
	 * Cleanup after tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();
		WC_Payments::set_database_cache( $this->_cache );
		wcpay_get_test_container()->reset_all_replacements();
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

		$this->assertTrue( $card_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $giropay_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $sofort_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $bancontact_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $eps_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $sepa_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $p24_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $ideal_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $becs_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $affirm_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $afterpay_method->is_enabled_at_checkout( 'US' ) );
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

		$this->assertEquals( $mock_token, $this->mock_gateway->create_token_from_setup_intent( $mock_setup_intent_id, $mock_user ) );
	}

	public function test_exception_will_be_thrown_if_phone_number_is_invalid() {
		$order = WC_Helper_Order::create_order();
		$order->set_billing_phone( '+1123456789123456789123' );
		$order->save();
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid phone number.' );
		$this->mock_gateway->process_payment( $order->get_id() );
	}

	public function test_remove_link_payment_method_if_card_disabled() {
		$this->mock_gateway->settings['upe_enabled_payment_method_ids'] = [ 'link' ];

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_upe_enabled_payment_method_statuses' )
			->will(
				$this->returnValue( [ 'link_payments' => [ 'status' => 'active' ] ] )
			);

		$this->assertSame( $this->mock_gateway->get_payment_method_ids_enabled_at_checkout(), [] );
	}

	/**
	 * @dataProvider available_payment_methods_provider
	 */
	public function test_get_upe_available_payment_methods( $payment_methods, $expected_result ) {
		$mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_fees' )
			->willReturn( $payment_methods );

		$gateway = new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$mock_wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$this->mock_payment_method,
			$this->mock_payment_methods,
			$this->mock_rate_limiter,
			$this->mock_order_service,
			$this->mock_dpps,
			$this->mock_localization_service,
			$this->mock_fraud_service
		);

		$this->assertEquals( $expected_result, $gateway->get_upe_available_payment_methods() );
	}

	public function available_payment_methods_provider() {
		return [
			'card only'                  => [
				[ 'card' => [ 'base' => 0.1 ] ],
				[ 'card' ],
			],
			'no match with fees'         => [
				[ 'some_other_payment_method' => [ 'base' => 0.1 ] ],
				[],
			],
			'multiple matches with fees' => [
				[
					'card'       => [ 'base' => 0.1 ],
					'bancontact' => [ 'base' => 0.2 ],
				],
				[ 'card', 'bancontact' ],
			],
			'no fees no methods'         => [
				[],
				[],
			],
		];
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
		$this->mock_gateway
			->expects( $this->any() )
			->method( 'get_upe_enabled_payment_method_statuses' )
			->will( $this->returnValue( $return_value ) );
	}
}

<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Cancel_Intention;
use WCPay\Core\Server\Request\Capture_Intention;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Core\Server\Request\Create_And_Confirm_Setup_Intention;
use WCPay\Core\Server\Request\Get_Charge;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Core\Server\Request\Get_Setup_Intention;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Intent_Status;
use WCPay\Constants\Payment_Method;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Duplicates_Detection_Service;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Fraud_Prevention_Enabled_Exception;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Exceptions\Order_ID_Mismatch_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Internal\Payment\Factor;
use WCPay\Internal\Payment\Router;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\PaymentProcessingService;
use WCPay\Payment_Information;
use WCPay\Payment_Methods\Affirm_Payment_Method;
use WCPay\Payment_Methods\Afterpay_Payment_Method;
use WCPay\Payment_Methods\Bancontact_Payment_Method;
use WCPay\Payment_Methods\Becs_Payment_Method;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Eps_Payment_Method;
use WCPay\Payment_Methods\Giropay_Payment_Method;
use WCPay\Payment_Methods\Ideal_Payment_Method;
use WCPay\Payment_Methods\Klarna_Payment_Method;
use WCPay\Payment_Methods\Link_Payment_Method;
use WCPay\Payment_Methods\P24_Payment_Method;
use WCPay\Payment_Methods\Sepa_Payment_Method;
use WCPay\Payment_Methods\Sofort_Payment_Method;
use WCPay\Payment_Methods\WC_Helper_Site_Currency;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\Session_Rate_Limiter;

// Need to use WC_Mock_Data_Store.
require_once __DIR__ . '/helpers/class-wc-mock-wc-data-store.php';

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Test extends WCPAY_UnitTestCase {

	const NO_REQUIREMENTS      = false;
	const PENDING_REQUIREMENTS = true;

	/**
	 * System under test.
	 *
	 * The card gateway is predominantly used for testing compared to other gateways,
	 * therefore it is assigned its own variable.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $card_gateway;

	/**
	 * Arrays of system under test.
	 *
	 * Useful when testing operations involving multiple gateways.
	 *
	 * @var WC_Payment_Gateway_WCPay[]
	 */
	private $gateways;

	/**
	 * Arrays of payment methods.
	 *
	 * Useful when testing operations involving multiple payment methods.
	 *
	 * @var WCPay\Payment_Methods\UPE_Payment_Method[]
	 */
	private $payment_methods;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

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
	 * Mock WC_Payments_Action_Scheduler_Service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service|MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_wcpay_account;

	/**
	 * Session_Rate_Limiter instance.
	 *
	 * @var Session_Rate_Limiter|MockObject
	 */
	private $mock_rate_limiter;

	/**
	 * UPE_Payment_Method instance.
	 *
	 * @var UPE_Payment_Method|MockObject
	 */
	private $mock_payment_method;

	/**
	 * WC_Payments_Order_Service instance.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * WooPay_Utilities instance.
	 *
	 * @var WooPay_Utilities
	 */
	private $woopay_utilities;

	/**
	 * Duplicate_Payment_Prevention_Service instance.
	 * @var Duplicate_Payment_Prevention_Service|MockObject
	 */
	private $mock_dpps;

	/**
	 * @var string
	 */
	private $mock_charge_id = 'ch_mock';

	/**
	 * @var integer
	 */
	private $mock_charge_created = 1653076178;

	/**
	 * WC_Payments_Localization_Service instance.
	 *
	 * @var WC_Payments_Localization_Service|MockObject
	 */
	private $mock_localization_service;

	/**
	 * Mock Fraud Service.
	 *
	 * @var WC_Payments_Fraud_Service|MockObject
	 */
	private $mock_fraud_service;

		/**
		 * Mock Duplicates Detection Service.
		 *
		 * @var Duplicates_Detection_Service
		 */
	private $mock_duplicates_detection_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client = $this
			->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_account_data',
					'is_server_connected',
					'get_blog_id',
					'create_intention',
					'create_and_confirm_intention',
					'create_and_confirm_setup_intent',
					'get_payment_method',
					'get_timeline',
				]
			)
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );
		$this->mock_api_client->expects( $this->any() )->method( 'get_blog_id' )->willReturn( 1234567 );

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );

		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );

		$this->mock_token_service = $this->createMock( WC_Payments_Token_Service::class );

		$this->mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );

		$this->mock_rate_limiter = $this->createMock( Session_Rate_Limiter::class );

		$this->order_service = new WC_Payments_Order_Service( $this->mock_api_client );

		$this->mock_dpps = $this->createMock( Duplicate_Payment_Prevention_Service::class );

		$this->mock_localization_service = $this->createMock( WC_Payments_Localization_Service::class );
		$this->mock_localization_service->expects( $this->any() )
			->method( 'get_country_locale_data' )
			->willReturn(
				[
					'currency_code' => 'usd',
				]
			);
		$this->mock_fraud_service                = $this->createMock( WC_Payments_Fraud_Service::class );
		$this->mock_duplicates_detection_service = $this->createMock( Duplicates_Detection_Service::class );

		$this->mock_payment_method = $this->getMockBuilder( CC_Payment_Method::class )
			->setConstructorArgs( [ $this->mock_token_service ] )
			->setMethods( [ 'is_subscription_item_in_cart' ] )
			->getMock();

		$this->init_gateways();

		// Replace the main class's gateway for testing purposes.
		$this->_gateway = WC_Payments::get_gateway();
		WC_Payments::set_gateway( $this->card_gateway );

		$this->woopay_utilities = new WooPay_Utilities();

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
	 * Post-test teardown
	 */
	public function tear_down() {
		parent::tear_down();

		delete_option( 'woocommerce_woocommerce_payments_settings' );

		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );

		// Restore the gateway in the main class.
		WC_Payments::set_gateway( $this->_gateway );

		// Fall back to an US store.
		update_option( 'woocommerce_store_postcode', '94110' );
		$this->card_gateway->update_option( 'saved_cards', 'yes' );

		// Some tests simulate payment method parameters.
		$payment_method_keys = [
			'payment_method',
			'wc-woocommerce_payments-payment-token',
			'wc-woocommerce_payments-new-payment-method',
		];
		foreach ( $payment_method_keys as $key ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $key ] ) ) {
				unset( $_POST[ $key ] );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}

		wcpay_get_test_container()->reset_all_replacements();
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

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->returnValue( $payment_intent ) );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( $customer_id ) );

		$this->card_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

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
		$order = WC_Helper_Order::create_order();

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

		$this->mock_wcpay_request( Get_Intention::class, 1, $intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $payment_intent );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( $customer_id ) );

		$this->card_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

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
			$this->card_gateway,
			'validate_order_id_received_vs_intent_meta_order_id',
			[ $order, $intent_metadata ]
		);
	}

	public function test_validate_order_id_received_vs_intent_meta_order_id_returning_void() {
		$order           = WC_Helper_Order::create_order();
		$intent_metadata = [ 'order_id' => (string) ( $order->get_id() ) ];

		$res = \PHPUnit_Utils::call_method(
			$this->card_gateway,
			'validate_order_id_received_vs_intent_meta_order_id',
			[ $order, $intent_metadata ]
		);

		$this->assertSame( null, $res );
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
			$this->card_gateway->set_payment_method_title_for_order( $order, $payment_method_details['type'], $payment_method_details );
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

		$card_method       = $this->payment_methods['card'];
		$giropay_method    = $this->payment_methods['giropay'];
		$p24_method        = $this->payment_methods['p24'];
		$sofort_method     = $this->payment_methods['sofort'];
		$bancontact_method = $this->payment_methods['bancontact'];
		$eps_method        = $this->payment_methods['eps'];
		$sepa_method       = $this->payment_methods['sepa_debit'];
		$ideal_method      = $this->payment_methods['ideal'];
		$becs_method       = $this->payment_methods['au_becs_debit'];
		$affirm_method     = $this->payment_methods['affirm'];
		$afterpay_method   = $this->payment_methods['afterpay_clearpay'];

		$this->assertEquals( 'card', $card_method->get_id() );
		$this->assertEquals( 'Credit card / debit card', $card_method->get_title() );
		$this->assertEquals( 'Visa debit card', $card_method->get_title( 'US', $mock_visa_details ) );
		$this->assertEquals( 'Mastercard credit card', $card_method->get_title( 'US', $mock_mastercard_details ) );
		$this->assertTrue( $card_method->is_enabled_at_checkout( 'US' ) );
		$this->assertTrue( $card_method->is_reusable() );
		$this->assertEquals( $mock_token, $card_method->get_payment_token_for_user( $mock_user, $mock_payment_method_id ) );

		$this->assertEquals( 'giropay', $giropay_method->get_id() );
		$this->assertEquals( 'giropay', $giropay_method->get_title() );
		$this->assertEquals( 'giropay', $giropay_method->get_title( 'US', $mock_giropay_details ) );
		$this->assertTrue( $giropay_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $giropay_method->is_reusable() );

		$this->assertEquals( 'p24', $p24_method->get_id() );
		$this->assertEquals( 'Przelewy24 (P24)', $p24_method->get_title() );
		$this->assertEquals( 'Przelewy24 (P24)', $p24_method->get_title( 'US', $mock_p24_details ) );
		$this->assertTrue( $p24_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $p24_method->is_reusable() );

		$this->assertEquals( 'sofort', $sofort_method->get_id() );
		$this->assertEquals( 'Sofort', $sofort_method->get_title() );
		$this->assertEquals( 'Sofort', $sofort_method->get_title( 'US', $mock_sofort_details ) );
		$this->assertTrue( $sofort_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $sofort_method->is_reusable() );

		$this->assertEquals( 'bancontact', $bancontact_method->get_id() );
		$this->assertEquals( 'Bancontact', $bancontact_method->get_title() );
		$this->assertEquals( 'Bancontact', $bancontact_method->get_title( 'US', $mock_bancontact_details ) );
		$this->assertTrue( $bancontact_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $bancontact_method->is_reusable() );

		$this->assertEquals( 'eps', $eps_method->get_id() );
		$this->assertEquals( 'EPS', $eps_method->get_title() );
		$this->assertEquals( 'EPS', $eps_method->get_title( 'US', $mock_eps_details ) );
		$this->assertTrue( $eps_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $eps_method->is_reusable() );

		$this->assertEquals( 'sepa_debit', $sepa_method->get_id() );
		$this->assertEquals( 'SEPA Direct Debit', $sepa_method->get_title() );
		$this->assertEquals( 'SEPA Direct Debit', $sepa_method->get_title( 'US', $mock_sepa_details ) );
		$this->assertTrue( $sepa_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $sepa_method->is_reusable() );

		$this->assertEquals( 'ideal', $ideal_method->get_id() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title( 'US', $mock_ideal_details ) );
		$this->assertTrue( $ideal_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $ideal_method->is_reusable() );

		$this->assertEquals( 'au_becs_debit', $becs_method->get_id() );
		$this->assertEquals( 'BECS Direct Debit', $becs_method->get_title() );
		$this->assertEquals( 'BECS Direct Debit', $becs_method->get_title( 'US', $mock_becs_details ) );
		$this->assertTrue( $becs_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $becs_method->is_reusable() );

		$this->assertSame( 'affirm', $affirm_method->get_id() );
		$this->assertSame( 'Affirm', $affirm_method->get_title() );
		$this->assertSame( 'Affirm', $affirm_method->get_title( 'US', $mock_affirm_details ) );
		$this->assertTrue( $affirm_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $affirm_method->is_reusable() );

		$this->assertSame( 'afterpay_clearpay', $afterpay_method->get_id() );
		$this->assertSame( 'Afterpay', $afterpay_method->get_title() );
		$this->assertSame( 'Afterpay', $afterpay_method->get_title( 'US', $mock_afterpay_details ) );
		$this->assertTrue( $afterpay_method->is_enabled_at_checkout( 'US' ) );
		$this->assertFalse( $afterpay_method->is_reusable() );

		$this->assertSame( 'afterpay_clearpay', $afterpay_method->get_id() );
		$this->assertSame( 'Clearpay', $afterpay_method->get_title( 'GB' ) );
		$this->assertSame( 'Clearpay', $afterpay_method->get_title( 'GB', $mock_afterpay_details ) );
		$this->assertTrue( $afterpay_method->is_enabled_at_checkout( 'GB' ) );
		$this->assertFalse( $afterpay_method->is_reusable() );
	}

	public function test_only_reusabled_payment_methods_enabled_with_subscription_item_present() {
		// Simulate is_changing_payment_method_for_subscription being true so that is_enabled_at_checkout() checks if the payment method is reusable().
		$_GET['change_payment_method'] = 10;
		WC_Subscriptions::set_wcs_is_subscription(
			function ( $order ) {
				return true;
			}
		);

		$card_method       = $this->payment_methods['card'];
		$giropay_method    = $this->payment_methods['giropay'];
		$sofort_method     = $this->payment_methods['sofort'];
		$bancontact_method = $this->payment_methods['bancontact'];
		$eps_method        = $this->payment_methods['eps'];
		$sepa_method       = $this->payment_methods['sepa_debit'];
		$p24_method        = $this->payment_methods['p24'];
		$ideal_method      = $this->payment_methods['ideal'];
		$becs_method       = $this->payment_methods['au_becs_debit'];
		$affirm_method     = $this->payment_methods['affirm'];
		$afterpay_method   = $this->payment_methods['afterpay_clearpay'];

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
		$card_method       = $this->payment_methods['card'];
		$giropay_method    = $this->payment_methods['giropay'];
		$sofort_method     = $this->payment_methods['sofort'];
		$bancontact_method = $this->payment_methods['bancontact'];
		$eps_method        = $this->payment_methods['eps'];
		$sepa_method       = $this->payment_methods['sepa_debit'];
		$p24_method        = $this->payment_methods['p24'];
		$ideal_method      = $this->payment_methods['ideal'];
		$becs_method       = $this->payment_methods['au_becs_debit'];
		$affirm_method     = $this->payment_methods['affirm'];
		$afterpay_method   = $this->payment_methods['afterpay_clearpay'];

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
		$card_method       = $this->payment_methods['card'];
		$giropay_method    = $this->payment_methods['giropay'];
		$sofort_method     = $this->payment_methods['sofort'];
		$bancontact_method = $this->payment_methods['bancontact'];
		$eps_method        = $this->payment_methods['eps'];
		$sepa_method       = $this->payment_methods['sepa_debit'];
		$p24_method        = $this->payment_methods['p24'];
		$ideal_method      = $this->payment_methods['ideal'];
		$becs_method       = $this->payment_methods['au_becs_debit'];
		$affirm_method     = $this->payment_methods['affirm'];
		$afterpay_method   = $this->payment_methods['afterpay_clearpay'];

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

		WC_Helper_Site_Currency::$mock_site_currency = 'USD';
		$wp->query_vars                              = [];
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

		$this->assertEquals( $mock_token, $this->card_gateway->create_token_from_setup_intent( $mock_setup_intent_id, $mock_user ) );
	}

	public function test_exception_will_be_thrown_if_phone_number_is_invalid() {
		$order = WC_Helper_Order::create_order();
		$order->set_billing_phone( '+1123456789123456789123' );
		$order->save();
		try {
			$this->card_gateway->process_payment( $order->get_id() );
		} catch ( Exception $e ) {
			$this->assertEquals( 'Exception', get_class( $e ) );
			$this->assertEquals( 'Invalid phone number.', $e->getMessage() );
			$this->assertEquals( 'WCPay\Exceptions\Invalid_Phone_Number_Exception', get_class( $e->getPrevious() ) );
		}
	}

	public function test_remove_link_payment_method_if_card_disabled() {
		$link_gateway = $this->get_gateway( Payment_Method::LINK );
		$link_gateway->settings['upe_enabled_payment_method_ids'] = [ 'link' ];

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'capabilities'            => [
						'link_payments' => 'active',
					],
					'capability_requirements' => [
						'link_payments' => [],
					],
				]
			);

		$this->assertSame( $link_gateway->get_payment_method_ids_enabled_at_checkout(), [] );
	}

	/**
	 * @dataProvider available_payment_methods_provider
	 */
	public function test_get_upe_available_payment_methods( $payment_methods, $expected_result ) {
		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_fees' )
			->willReturn( $payment_methods );

		$this->assertEquals( $expected_result, $this->card_gateway->get_upe_available_payment_methods() );
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

	// This test uses a mock of the gateway class due to get_selected_payment_method()'s reliance on the static method WC_Payments::get_payment_method_map(), which can't be mocked. Refactoring the gateway class to avoid using this static method would allow mocking it in tests.
	public function test_process_redirect_setup_intent_succeded() {
		$order = WC_Helper_Order::create_order();

		/** @var WC_Payment_Gateway_WCPay */
		$mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->payment_methods['card'],
					[ $this->payment_methods ],
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
					$this->mock_fraud_service,
					$this->mock_duplicates_detection_service,
				]
			)
			->onlyMethods(
				[
					'manage_customer_details_for_order',
					'get_selected_payment_method',
				]
			)
			->getMock();

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

		$card_method = $this->payment_methods['card'];

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

		$mock_gateway->expects( $this->once() )
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

		$mock_gateway->expects( $this->any() )
			->method( 'get_selected_payment_method' )
			->willReturn( $card_method );

		// Simulate is_changing_payment_method_for_subscription being true so that is_enabled_at_checkout() checks if the payment method is reusable().
		$_GET['change_payment_method'] = 10;
		WC_Subscriptions::set_wcs_is_subscription(
			function ( $order ) {
				return true;
			}
		);

		$mock_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

		$result_order = wc_get_order( $order_id );

		$this->assertEquals( $intent_id, $result_order->get_meta( '_intent_id', true ) );
		$this->assertEquals( $intent_status, $result_order->get_meta( '_intention_status', true ) );
		$this->assertEquals( $payment_method_id, $result_order->get_meta( '_payment_method_id', true ) );
		$this->assertEquals( $customer_id, $result_order->get_meta( '_stripe_customer_id', true ) );
		$this->assertEquals( Order_Status::PROCESSING, $result_order->get_status() );
		$this->assertEquals( 1, count( $result_order->get_payment_tokens() ) );
	}

	// This test uses a mock of the gateway class due to get_selected_payment_method()'s reliance on the static method WC_Payments::get_payment_method_map(), which can't be mocked. Refactoring the gateway class to avoid using this static method would allow mocking it in tests.
	public function test_process_redirect_payment_save_payment_token() {
		/** @var WC_Payment_Gateway_WCPay */
		$mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->mock_wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
					$this->payment_methods['card'],
					[ $this->payment_methods ],
					$this->mock_rate_limiter,
					$this->order_service,
					$this->mock_dpps,
					$this->mock_localization_service,
					$this->mock_fraud_service,
					$this->mock_duplicates_detection_service,
				]
			)
			->onlyMethods(
				[
					'manage_customer_details_for_order',
					'get_selected_payment_method',
				]
			)
			->getMock();

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

		$card_method = $this->payment_methods['card'];

		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => $intent_status,
				'metadata' => $intent_metadata,
			]
		);

		$mock_gateway->expects( $this->once() )
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

		$mock_gateway->expects( $this->any() )
			->method( 'get_selected_payment_method' )
			->willReturn( $card_method );

		// Simulate is_changing_payment_method_for_subscription being true so that is_enabled_at_checkout() checks if the payment method is reusable().
		$_GET['change_payment_method'] = 10;
		WC_Subscriptions::set_wcs_is_subscription(
			function ( $order ) {
				return true;
			}
		);

		$mock_gateway->process_redirect_payment( $order, $intent_id, $save_payment_method );

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

	public function test_get_payment_methods_with_post_request_context() {
		$order               = WC_Helper_Order::create_order();
		$payment_information = new Payment_Information( 'pm_mock', $order );

		$_POST['payment_method'] = 'woocommerce_payments';

		$payment_methods = $this->card_gateway->get_payment_method_types( $payment_information );

		$this->assertSame( [ Payment_Method::CARD ], $payment_methods );

		unset( $_POST['payment_method'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public function test_get_payment_methods_without_post_request_context() {
		$token               = WC_Helper_Token::create_token( 'pm_mock' );
		$order               = WC_Helper_Order::create_order();
		$payment_information = new Payment_Information( 'pm_mock', $order, null, $token );

		unset( $_POST['payment_method'] ); // phpcs:ignore WordPress.Security.NonceVerification

		$payment_methods = $this->card_gateway->get_payment_method_types( $payment_information );

		$this->assertSame( [ Payment_Method::CARD ], $payment_methods );
	}

	public function test_get_payment_methods_without_request_context_or_token() {
		$payment_information = new Payment_Information( 'pm_mock' );

		unset( $_POST['payment_method'] ); // phpcs:ignore WordPress.Security.NonceVerification

		$gateway = WC_Payments::get_gateway();
		WC_Payments::set_gateway( $this->card_gateway );

		$payment_methods = $this->card_gateway->get_payment_method_types( $payment_information );

		$this->assertSame( [ Payment_Method::CARD ], $payment_methods );

		WC_Payments::set_gateway( $gateway );
	}

	public function test_get_payment_methods_from_gateway_id_upe() {
		WC_Helper_Order::create_order();

		$gateway = WC_Payments::get_gateway();

		$payment_methods = $this->card_gateway->get_payment_methods_from_gateway_id( WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . Payment_Method::BANCONTACT );
		$this->assertSame( [ Payment_Method::BANCONTACT ], $payment_methods );

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'capabilities'            => [
						'link_payments' => 'active',
						'card_payments' => 'active',
					],
					'capability_requirements' => [
						'link_payments' => [],
						'card_payments' => [],
					],
				]
			);
		$this->card_gateway->settings['upe_enabled_payment_method_ids'] = [ Payment_Method::LINK, Payment_Method::CARD ];
		WC_Payments::set_gateway( $this->card_gateway );
		$payment_methods = $this->card_gateway->get_payment_methods_from_gateway_id( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$this->assertSame( [ Payment_Method::CARD, Payment_Method::LINK ], $payment_methods );

		$this->card_gateway->settings['upe_enabled_payment_method_ids'] = [ Payment_Method::CARD ];
		$payment_methods = $this->card_gateway->get_payment_methods_from_gateway_id( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$this->assertSame( [ Payment_Method::CARD ], $payment_methods );

		WC_Payments::set_gateway( $gateway );
	}

	public function test_display_gateway_html() {
		foreach ( $this->gateways as $gateway ) {
			/**
			* This tests each payment method output separately without concatenating the output
			* into 1 single buffer. Each iteration has 1 assertion.
			*/
			ob_start();
			$gateway->display_gateway_html();
			$actual_output = ob_get_contents();
			ob_end_clean();

			$this->assertStringContainsString( '<div class="wcpay-upe-element" data-payment-method-type="' . $gateway->get_payment_method()->get_id() . '"></div>', $actual_output );
		}
	}

	public function test_should_not_use_stripe_platform_on_checkout_page_for_non_card() {
		foreach ( $this->get_gateways_excluding( [ Payment_Method::CARD ] ) as $gateway ) {
			$this->assertFalse( $gateway->should_use_stripe_platform_on_checkout_page() );
		}
	}

	public function test_attach_exchange_info_to_order_with_no_conversion() {
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->set_currency( 'USD' );
		$order->save();

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_default_currency' )
			->willReturn( 'usd' );

		$this->card_gateway->attach_exchange_info_to_order( $order, $charge_id );

		// The meta key should not be set.
		$this->assertEquals( '', $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate' ) );
	}

	public function test_attach_exchange_info_to_order_with_different_account_currency_no_conversion() {
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->set_currency( 'USD' );
		$order->save();

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_default_currency' )
			->willReturn( 'jpy' );

		$this->card_gateway->attach_exchange_info_to_order( $order, $charge_id );

		// The meta key should not be set.
		$this->assertEquals( '', $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate' ) );
	}

	public function test_attach_exchange_info_to_order_with_zero_decimal_order_currency() {
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->set_currency( 'JPY' );
		$order->save();

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_default_currency' )
			->willReturn( 'usd' );

		$charge_request = $this->mock_wcpay_request( Get_Charge::class, 1, 'ch_mock' );

		$charge_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				[
					'id'                  => 'ch_123456',
					'amount'              => 4500,
					'balance_transaction' => [
						'amount'        => 4450,
						'fee'           => 50,
						'currency'      => 'USD',
						'exchange_rate' => 0.9414,
					],
				]
			);

		$this->card_gateway->attach_exchange_info_to_order( $order, $charge_id );
		$this->assertEquals( 0.009414, $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate' ) );
	}

	public function test_attach_exchange_info_to_order_with_different_order_currency() {
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->set_currency( 'EUR' );
		$order->save();

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_default_currency' )
			->willReturn( 'usd' );

		$charge_request = $this->mock_wcpay_request( Get_Charge::class, 1, 'ch_mock' );
		$charge_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				[
					'id'                  => 'ch_123456',
					'amount'              => 4500,
					'balance_transaction' => [
						'amount'        => 4450,
						'fee'           => 50,
						'currency'      => 'USD',
						'exchange_rate' => 0.853,
					],
				]
			);

		$this->card_gateway->attach_exchange_info_to_order( $order, $charge_id );
		$this->assertEquals( 0.853, $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate' ) );
	}

	public function test_save_payment_method_checkbox_displayed() {
		// Use a callback to get and test the output (also suppresses the output buffering being printed to the CLI).
		$this->setOutputCallback(
			function ( $output ) {
				$input_element = preg_match_all( '/.*<input.*id="wc-woocommerce_payments-new-payment-method".*\/>.*/', $output );
				$parent_div    = preg_match_all( '/<div >.*<\/div>/s', $output );

				$this->assertSame( 1, $input_element );
				$this->assertSame( 1, $parent_div );
			}
		);

		$this->card_gateway->save_payment_method_checkbox();
	}

	public function test_save_payment_method_checkbox_not_displayed_when_force_checked() {
		$this->setOutputCallback(
			function ( $output ) {
				$input_element = preg_match_all( '/.*<input.*id="wc-woocommerce_payments-new-payment-method".*\/>.*/', $output );
				$parent_div    = preg_match_all( '/<div style="display:none;">.*<\/div>/s', $output );

				$this->assertSame( 1, $input_element );
				$this->assertSame( 1, $parent_div );
			}
		);

		$this->card_gateway->save_payment_method_checkbox( true );
	}

	public function test_save_payment_method_checkbox_not_displayed_when_stripe_platform_account_used() {
		// Setup the test so that should_use_stripe_platform_on_checkout_page returns true.
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->card_gateway->update_option( 'platform_checkout', 'yes' );
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		WC()->session->init();
		WC()->cart->add_to_cart( WC_Helper_Product::create_simple_product()->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->setOutputCallback(
			function ( $output ) {
				$input_element = preg_match_all( '/.*<input.*id="wc-woocommerce_payments-new-payment-method".*\/>.*/', $output );
				$parent_div    = preg_match_all( '/<div style="display:none;">.*<\/div>/s', $output );

				$this->assertSame( 1, $input_element );
				$this->assertSame( 1, $parent_div );
			}
		);

		$this->card_gateway->save_payment_method_checkbox( false );

		remove_filter( 'woocommerce_is_checkout', '__return_true' );
		WC()->cart->empty_cart();
	}

	public function test_capture_charge_success() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_CAPTURE ] );

		$capture_intent_request = $this->mock_wcpay_request( Capture_Intention::class, 1, $intent_id );
		$capture_intent_request->expects( $this->once() )
			->method( 'set_amount_to_capture' )
			->with( $mock_intent->get_amount() );
		$capture_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention() );

		$result = $this->card_gateway->capture_charge( $order );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => Intent_Status::SUCCEEDED,
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 200,
			],
			$result
		);
		$this->assertStringContainsString( 'successfully captured', $latest_wcpay_note->content );
		$this->assertStringContainsString( wc_price( $order->get_total() ), $latest_wcpay_note->content );
		$this->assertEquals( Intent_Status::SUCCEEDED, $order->get_meta( '_intention_status', true ) );
		$this->assertEquals( Order_Status::PROCESSING, $order->get_status() );
	}

	public function test_capture_charge_success_non_usd() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'currency' => 'eur',
			]
		);

		$capture_intent_request = $this->mock_wcpay_request( Capture_Intention::class, 1, $intent_id );
		$capture_intent_request->expects( $this->once() )
			->method( 'set_amount_to_capture' )
			->with( $mock_intent->get_amount() );
		$capture_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention( [ 'currency' => 'eur' ] ) );

		$result = $this->card_gateway->capture_charge( $order );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		$note_currency = WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ), $order );

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => Intent_Status::SUCCEEDED,
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 200,
			],
			$result
		);
		$this->assertStringContainsString( 'successfully captured', $latest_wcpay_note->content );
		$this->assertStringContainsString( $note_currency, $latest_wcpay_note->content );
		$this->assertEquals( Intent_Status::SUCCEEDED, $order->get_meta( '_intention_status', true ) );
		$this->assertEquals( Order_Status::PROCESSING, $order->get_status() );
	}

	public function test_capture_charge_failure() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_CAPTURE ] );

		$capture_intent_request = $this->mock_wcpay_request( Capture_Intention::class, 1, $intent_id );
		$capture_intent_request->expects( $this->once() )
			->method( 'set_amount_to_capture' )
			->with( $mock_intent->get_amount() );
		$capture_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$result = $this->card_gateway->capture_charge( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => Intent_Status::REQUIRES_CAPTURE,
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 502,
			],
			$result
		);
		$this->assertStringContainsString( 'failed', $note->content );
		$this->assertStringContainsString( wc_price( $order->get_total() ), $note->content );
		$this->assertEquals( Intent_Status::REQUIRES_CAPTURE, $order->get_meta( '_intention_status', true ) );
		$this->assertEquals( Order_Status::ON_HOLD, $order->get_status() );
	}

	public function test_capture_charge_failure_non_usd() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );
		$order->set_currency( 'EUR' );

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'currency' => 'eur',
			]
		);

		$capture_intent_request = $this->mock_wcpay_request( Capture_Intention::class, 1, $intent_id );
		$capture_intent_request->expects( $this->once() )
			->method( 'set_amount_to_capture' )
			->with( $mock_intent->get_amount() );
		$capture_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$result = $this->card_gateway->capture_charge( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		$note_currency = WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ), $order );

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => Intent_Status::REQUIRES_CAPTURE,
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 502,
			],
			$result
		);
		$this->assertStringContainsString( 'failed', $note->content );
		$this->assertStringContainsString( $note_currency, $note->content );
		$this->assertEquals( Intent_Status::REQUIRES_CAPTURE, $order->get_meta( '_intention_status', true ) );
		$this->assertEquals( Order_Status::ON_HOLD, $order->get_status() );
	}

	public function test_capture_charge_api_failure() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_CAPTURE ] );

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->exactly( 1 ) )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$capture_intent_request = $this->mock_wcpay_request( Capture_Intention::class, 1, $intent_id );
		$capture_intent_request->expects( $this->once() )
			->method( 'set_amount_to_capture' )
			->with( $mock_intent->get_amount() );
		$capture_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->throwException( new API_Exception( 'test exception', 'server_error', 500 ) ) );

		$result = $this->card_gateway->capture_charge( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => 'failed',
				'id'        => $intent_id,
				'message'   => 'test exception',
				'http_code' => 500,
			],
			$result
		);
		$this->assertStringContainsString( 'failed', $note->content );
		$this->assertStringContainsString( 'test exception', $note->content );
		$this->assertStringContainsString( wc_price( $order->get_total() ), $note->content );
		$this->assertEquals( Intent_Status::REQUIRES_CAPTURE, $order->get_meta( '_intention_status', true ) );
		$this->assertEquals( Order_Status::ON_HOLD, $order->get_status() );
	}

	public function test_capture_charge_api_failure_non_usd() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );
		WC_Payments_Utils::set_order_intent_currency( $order, 'EUR' );

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'currency' => 'jpy',
			]
		);

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->exactly( 1 ) )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$capture_intent_request = $this->mock_wcpay_request( Capture_Intention::class, 1, $intent_id );
		$capture_intent_request->expects( $this->once() )
			->method( 'set_amount_to_capture' )
			->with( $mock_intent->get_amount() );
		$capture_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->throwException( new API_Exception( 'test exception', 'server_error', 500 ) ) );

		$result = $this->card_gateway->capture_charge( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		$note_currency = WC_Payments_Explicit_Price_Formatter::get_explicit_price( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ), $order );

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => 'failed',
				'id'        => $intent_id,
				'message'   => 'test exception',
				'http_code' => 500,
			],
			$result
		);
		$this->assertStringContainsString( 'failed', $note->content );
		$this->assertStringContainsString( 'test exception', $note->content );
		$this->assertStringContainsString( $note_currency, $note->content );
		$this->assertEquals( Intent_Status::REQUIRES_CAPTURE, $order->get_meta( '_intention_status', true ) );
		$this->assertEquals( Order_Status::ON_HOLD, $order->get_status() );
	}

	public function test_capture_charge_expired() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::CANCELED ] );

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->exactly( 1 ) )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$capture_intent_request = $this->mock_wcpay_request( Capture_Intention::class, 1, $intent_id );
		$capture_intent_request->expects( $this->once() )
			->method( 'set_amount_to_capture' )
			->with( $mock_intent->get_amount() );
		$capture_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->throwException( new API_Exception( 'test exception', 'server_error', 500 ) ) );

		$result = $this->card_gateway->capture_charge( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => 'failed',
				'id'        => $intent_id,
				'message'   => 'test exception',
				'http_code' => 500,
			],
			$result
		);
		$this->assertStringContainsString( 'expired', $note->content );
		$this->assertSame( Intent_Status::CANCELED, $order->get_meta( '_intention_status', true ) );
		$this->assertSame( Order_Status::FAILED, $order->get_status() );
	}

	public function test_capture_charge_metadata() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$charge = $this->create_charge_object();

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'status'   => Intent_Status::REQUIRES_CAPTURE,
				'metadata' => [
					'customer_name' => 'Test',
					'reader_ID'     => 'wisepad',
				],
			]
		);

		$capture_intent_request = $this->mock_wcpay_request( Capture_Intention::class, 1, $intent_id );
		$capture_intent_request->expects( $this->once() )
			->method( 'set_amount_to_capture' )
			->with( $mock_intent->get_amount() );
		$capture_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention() );

		$result = $this->card_gateway->capture_charge( $order, true, [] );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertSame(
			[
				'status'    => Intent_Status::SUCCEEDED,
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 200,
			],
			$result
		);
		$this->assertStringContainsString( 'successfully captured', $note->content );
		$this->assertStringContainsString( wc_price( $order->get_total() ), $note->content );
		$this->assertSame( $order->get_meta( '_intention_status', true ), Intent_Status::SUCCEEDED );
		$this->assertSame( $order->get_status(), Order_Status::PROCESSING );
	}

	public function test_capture_charge_without_level3() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$mock_intent = WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::REQUIRES_CAPTURE ] );

		$capture_intent_request = $this->mock_wcpay_request( Capture_Intention::class, 1, $intent_id );
		$capture_intent_request->expects( $this->once() )
			->method( 'set_amount_to_capture' )
			->with( $mock_intent->get_amount() );
		$capture_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention() );

		$this->mock_wcpay_account
			->expects( $this->never() )
			->method( 'get_account_country' ); // stand-in for get_level3_data_from_order.

		$result = $this->card_gateway->capture_charge( $order, false );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => Intent_Status::SUCCEEDED,
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 200,
			],
			$result
		);
		$this->assertStringContainsString( 'successfully captured', $latest_wcpay_note->content );
		$this->assertStringContainsString( wc_price( $order->get_total() ), $latest_wcpay_note->content );
		$this->assertEquals( Intent_Status::SUCCEEDED, $order->get_meta( '_intention_status', true ) );
		$this->assertEquals( Order_Status::PROCESSING, $order->get_status() );
	}

	public function test_capture_cancelling_order_cancels_authorization() {
		$intent_id = uniqid( 'pi_' );
		$charge_id = uniqid( 'ch_' );

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'id'     => $intent_id,
				'status' => Intent_Status::REQUIRES_CAPTURE,
				'charge' => [
					'amount_captured' => 0,
					'status'          => Intent_Status::SUCCEEDED,
					'id'              => $charge_id,
				],
			]
		);

		$mock_canceled_intent = WC_Helper_Intention::create_intention(
			[
				'id'     => $intent_id,
				'status' => Intent_Status::CANCELED,
				'charge' => [
					'status' => Intent_Status::CANCELED,
					'id'     => $charge_id,
				],
			]
		);

		$get_intent_request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );
		$get_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$cancel_intent_request = $this->mock_wcpay_request( Cancel_Intention::class, 1, $intent_id );
		$cancel_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_canceled_intent );

		$order->set_status( Order_Status::CANCELLED );
		$order->save();

		$order = wc_get_order( $order->get_id() );

		$this->assertSame( Intent_Status::CANCELED, $order->get_meta( '_intention_status', true ) );
		$this->assertSame( Order_Status::CANCELLED, $order->get_status() );
	}

	/**
	 * Test for various scenarios where we don't want to cancel existing
	 * payment intent.
	 *
	 * @dataProvider provider_capture_cancelling_order_does_not_cancel_captured_authorization
	 */
	public function test_capture_cancelling_order_does_not_cancel_captured_authorization( WC_Payments_API_Payment_Intention $intent ) {
		$intent_id = $intent->get_id();
		$charge    = $intent->get_charge();
		$charge_id = null !== $charge ? $charge->get_id() : null;

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		if ( null !== $charge_id ) {
			$order->update_meta_data( '_charge_id', $charge_id );
		}
		$order->update_meta_data( '_intention_status', $intent->get_status() );
		$order->update_status( Order_Status::PROCESSING );

		$get_intent_request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );
		$get_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $intent );

		$this->mock_wcpay_request( Cancel_Intention::class, 0, $intent_id );

		$order->set_status( Order_Status::CANCELLED );
		$order->save();

		$order = wc_get_order( $order->get_id() );

		$this->assertSame( $intent->get_status(), $order->get_meta( '_intention_status', true ), 'Intent status is not modified' );
		$this->assertSame( Order_Status::CANCELLED, $order->get_status(), 'Order should become cancelled' );
	}

	/**
	 * Provider for test_capture_cancelling_order_does_not_cancel_captured_authorization.
	 *
	 * @return array
	 */
	public function provider_capture_cancelling_order_does_not_cancel_captured_authorization() {
		return [
			'Captured intent'                     => [
				WC_Helper_Intention::create_intention(
					[
						'id'     => uniqid( 'pi_' ),
						'status' => Intent_Status::SUCCEEDED,
						'charge' => [
							'status' => Intent_Status::SUCCEEDED,
							'id'     => uniqid( 'ch_' ),
						],
					]
				),
			],
			'Intent without charge'               => [
				WC_Helper_Intention::create_intention(
					[
						'id'     => uniqid( 'pi_' ),
						'status' => Intent_Status::SUCCEEDED,
					],
					false
				),
			],
			'Canceled intent'                     => [
				WC_Helper_Intention::create_intention(
					[
						'id'     => uniqid( 'pi_' ),
						'status' => Intent_Status::CANCELED,
						'charge' => [
							'status' => Intent_Status::SUCCEEDED,
							'id'     => uniqid( 'ch_' ),
						],
					]
				),
			],
			'Captured charge, intent out of sync' => [
				WC_Helper_Intention::create_intention(
					[
						'id'     => uniqid( 'pi_' ),
						'status' => Intent_Status::REQUIRES_CAPTURE,
						'charge' => [
							'status'   => Intent_Status::SUCCEEDED,
							'id'       => uniqid( 'ch_' ),
							'captured' => true,
						],
					]
				),
			],
		];
	}

	public function test_cancel_authorization_handles_api_exception_when_canceling() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$cancel_intent_request = $this->mock_wcpay_request( Cancel_Intention::class, 1, $intent_id );
		$cancel_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->throwException( new API_Exception( 'test exception', 'test', 123 ) ) );

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::CANCELED ] ) );

		$this->card_gateway->cancel_authorization( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		$this->assertStringContainsString( 'cancelled', strtolower( $note->content ) );
		$this->assertEquals( Order_Status::CANCELLED, $order->get_status() );
	}

	public function test_cancel_authorization_handles_all_api_exceptions() {
		$intent_id = 'pi_mock';
		$charge_id = 'ch_mock';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', Intent_Status::REQUIRES_CAPTURE );
		$order->update_status( Order_Status::ON_HOLD );

		$cancel_intent_request = $this->mock_wcpay_request( Cancel_Intention::class, 1, $intent_id );
		$cancel_intent_request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->throwException( new API_Exception( 'test exception', 'test', 123 ) ) );

		$request = $this->mock_wcpay_request( Get_Intention::class, 1, $intent_id );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->throwException( new API_Exception( 'ignore this', 'test', 123 ) ) );

		$this->card_gateway->cancel_authorization( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		$this->assertStringContainsString( 'failed', $note->content );
		$this->assertStringContainsString( 'test exception', $note->content );
		$this->assertEquals( Order_Status::ON_HOLD, $order->get_status() );
	}

	public function test_add_payment_method_no_method() {
		$result = $this->card_gateway->add_payment_method();
		$this->assertEquals( 'error', $result['result'] );
	}

	public function test_create_and_confirm_setup_intent_existing_customer() {
		$_POST = [ 'wcpay-payment-method' => 'pm_mock' ];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( 'cus_12345' ) );

		$this->mock_customer_service
			->expects( $this->never() )
			->method( 'create_customer_for_user' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Setup_Intention::class );

		$request->expects( $this->once() )
			->method( 'set_customer' )
			->with( 'cus_12345' );

		$request->expects( $this->once() )
			->method( 'set_payment_method' )
			->with( 'pm_mock' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_setup_intention(
					[ 'id' => 'seti_mock_123' ]
				)
			);

		$result = $this->card_gateway->create_and_confirm_setup_intent();

		$this->assertSame( 'seti_mock_123', $result->get_id() );
	}

	public function test_create_and_confirm_setup_intent_no_customer() {
		$_POST = [ 'wcpay-payment-method' => 'pm_mock' ];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( null ) );

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->will( $this->returnValue( 'cus_12345' ) );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Setup_Intention::class );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_setup_intention(
					[ 'id' => 'seti_mock_123' ]
				)
			);

		$result = $this->card_gateway->create_and_confirm_setup_intent();

		$this->assertSame( 'seti_mock_123', $result->get_id() );
	}

	public function test_add_payment_method_no_intent() {
		$result = $this->card_gateway->add_payment_method();
		$this->assertEquals( 'error', $result['result'] );
	}

	public function test_add_payment_method_success() {
		$_POST = [ 'wcpay-setup-intent' => 'sti_mock' ];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( 'cus_12345' ) );

		$request = $this->mock_wcpay_request( Get_Setup_Intention::class, 1, 'sti_mock' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_setup_intention(
					[
						'status'         => Intent_Status::SUCCEEDED,
						'payment_method' => 'pm_mock',
					]
				)
			);

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->with( 'pm_mock', wp_get_current_user() );

		$result = $this->card_gateway->add_payment_method();

		$this->assertEquals( 'success', $result['result'] );
	}

	public function test_add_payment_method_no_customer() {
		$_POST = [ 'wcpay-setup-intent' => 'sti_mock' ];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( null ) );

		$this->mock_wcpay_request( Get_Setup_Intention::class, 0 );

		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		$result = $this->card_gateway->add_payment_method();

		$this->assertEquals( 'error', $result['result'] );
	}

	public function test_add_payment_method_cancelled_intent() {
		$_POST = [ 'wcpay-setup-intent' => 'sti_mock' ];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( 'cus_12345' ) );

		$request = $this->mock_wcpay_request( Get_Setup_Intention::class, 1, 'sti_mock' );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_setup_intention( [ 'status' => Intent_Status::CANCELED ] ) );

		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		$result = $this->card_gateway->add_payment_method();

		$this->assertEquals( 'error', $result['result'] );
		wc_clear_notices();
	}

	public function test_schedule_order_tracking_with_wrong_payment_gateway() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'square' );

		// If the payment gateway isn't WC Pay, this function should never get called.
		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->card_gateway->schedule_order_tracking( $order->get_id(), $order );
	}

	public function test_schedule_order_tracking_with_sift_disabled() {
		$order = WC_Helper_Order::create_order();

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->mock_fraud_service
			->expects( $this->once() )
			->method( 'get_fraud_services_config' )
			->willReturn(
				[
					'stripe' => [],
				]
			);

		$this->card_gateway->schedule_order_tracking( $order->get_id(), $order );
	}

	public function test_schedule_order_tracking_with_no_payment_method_id() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->delete_meta_data( '_new_order_tracking_complete' );

		$this->mock_action_scheduler_service
			->expects( $this->never() )
			->method( 'schedule_job' );

		$this->mock_fraud_service
			->expects( $this->once() )
			->method( 'get_fraud_services_config' )
			->willReturn(
				[
					'stripe' => [],
					'sift'   => [],
				]
			);

		$this->card_gateway->schedule_order_tracking( $order->get_id(), $order );
	}

	public function test_schedule_order_tracking() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->update_meta_data( '_payment_method_id', 'pm_123' );
		$order->update_meta_data( '_wcpay_mode', WC_Payments::mode()->is_test() ? 'test' : 'prod' );
		$order->delete_meta_data( '_new_order_tracking_complete' );
		$order->save_meta_data();
		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' );

		$this->mock_fraud_service
			->expects( $this->once() )
			->method( 'get_fraud_services_config' )
			->willReturn(
				[
					'stripe' => [],
					'sift'   => [],
				]
			);

		$this->card_gateway->schedule_order_tracking( $order->get_id(), $order );
	}

	public function test_schedule_order_tracking_on_already_created_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->add_meta_data( '_new_order_tracking_complete', 'yes' );
		$order->update_meta_data( '_payment_method_id', 'pm_123' );
		$order->save_meta_data();

		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' );

		$this->mock_fraud_service
			->expects( $this->once() )
			->method( 'get_fraud_services_config' )
			->willReturn(
				[
					'stripe' => [],
					'sift'   => [],
				]
			);

		$this->card_gateway->schedule_order_tracking( $order->get_id(), $order );
	}

	public function test_outputs_payments_settings_screen() {
		ob_start();
		$this->card_gateway->output_payments_settings_screen();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wcpay-account-settings-container"%a', $output );
	}

	public function test_outputs_express_checkout_settings_screen() {
		$_GET['method'] = 'foo';
		ob_start();
		$this->card_gateway->output_payments_settings_screen();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wcpay-express-checkout-settings-container"%a', $output );
		$this->assertStringMatchesFormat( '%adata-method-id="foo"%a', $output );
	}

	/**
	 * Tests account statement descriptor validator
	 *
	 * @dataProvider account_statement_descriptor_validation_provider
	 */
	public function test_validate_account_statement_descriptor_field( $is_valid, $value, $expected = null ) {
		$key = 'account_statement_descriptor';
		if ( $is_valid ) {
			$validated_value = $this->card_gateway->validate_account_statement_descriptor_field( $key, $value );
			$this->assertEquals( $expected ?? $value, $validated_value );
		} else {
			$this->expectExceptionMessage( 'Customer bank statement is invalid.' );
			$this->card_gateway->validate_account_statement_descriptor_field( $key, $value );
		}
	}

	public function account_statement_descriptor_validation_provider() {
		return [
			'valid'          => [ true, 'WCPAY dev' ],
			'allow_digits'   => [ true, 'WCPay dev 2020' ],
			'allow_special'  => [ true, 'WCPay-Dev_2020' ],
			'allow_amp'      => [ true, 'WCPay&Dev_2020' ],
			'strip_slashes'  => [ true, 'WCPay\\\\Dev_2020', 'WCPay\\Dev_2020' ],
			'allow_long_amp' => [ true, 'aaaaaaaaaaaaaaaaaaa&aa' ],
			'trim_valid'     => [ true, '   good_descriptor  ', 'good_descriptor' ],
			'empty'          => [ false, '' ],
			'short'          => [ false, 'WCP' ],
			'long'           => [ false, 'WCPay_dev_WCPay_dev_WCPay_dev_WCPay_dev' ],
			'no_*'           => [ false, 'WCPay * dev' ],
			'no_sqt'         => [ false, 'WCPay \'dev\'' ],
			'no_dqt'         => [ false, 'WCPay "dev"' ],
			'no_lt'          => [ false, 'WCPay<dev' ],
			'no_gt'          => [ false, 'WCPay>dev' ],
			'req_latin'      => [ false, 'дескриптор' ],
			'req_letter'     => [ false, '123456' ],
			'trim_too_short' => [ false, '  aaa    ' ],
		];
	}

	public function test_payment_request_form_field_defaults() {
		// need to delete the existing options to ensure nothing is in the DB from the `setUp` phase, where the method is instantiated.
		delete_option( 'woocommerce_woocommerce_payments_settings' );

		$this->assertEquals(
			[
				'product',
				'cart',
				'checkout',
			],
			$this->card_gateway->get_option( 'payment_request_button_locations' )
		);
		$this->assertEquals(
			'medium',
			$this->card_gateway->get_option( 'payment_request_button_size' )
		);

		$form_fields = $this->card_gateway->get_form_fields();

		$this->assertEquals(
			[
				'default',
				'buy',
				'donate',
				'book',
			],
			array_keys( $form_fields['payment_request_button_type']['options'] )
		);
		$this->assertEquals(
			[
				'dark',
				'light',
				'light-outline',
			],
			array_keys( $form_fields['payment_request_button_theme']['options'] )
		);
	}

	public function test_payment_gateway_enabled_for_supported_currency() {
		$current_currency = strtolower( get_woocommerce_currency() );
		$this->mock_wcpay_account->expects( $this->once() )->method( 'get_account_customer_supported_currencies' )->will(
			$this->returnValue(
				[
					$current_currency,
				]
			)
		);
		$this->assertTrue( $this->card_gateway->is_available_for_current_currency() );
	}

	public function test_payment_gateway_enabled_for_empty_supported_currency_list() {
		// We want to avoid disabling the gateway in case the API doesn't give back any currency suppported.
		$this->mock_wcpay_account->expects( $this->once() )->method( 'get_account_customer_supported_currencies' )->will(
			$this->returnValue(
				[]
			)
		);
		$this->assertTrue( $this->card_gateway->is_available_for_current_currency() );
	}

	public function test_payment_gateway_disabled_for_unsupported_currency() {
		$this->mock_wcpay_account->expects( $this->once() )->method( 'get_account_customer_supported_currencies' )->will(
			$this->returnValue(
				[
					'btc',
				]
			)
		);
		$this->assertFalse( $this->card_gateway->is_available_for_current_currency() );
	}

	public function test_process_payment_for_order_not_from_request() {
		// There is no payment method data within the request. This is the case e.g. for the automatic subscription renewals.
		$_POST['payment_method'] = '';

		$token = WC_Helper_Token::create_token( 'pm_mock' );

		$expected_upe_payment_method = 'card';
		$order                       = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 100 );
		$order->add_payment_token( $token );
		$order->save();

		$pi = new Payment_Information( 'pm_test', $order, null, null, null, null, null, '', 'card' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_payment_methods' )
			->with( [ $expected_upe_payment_method ] );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention( [ 'status' => 'success' ] ) );

		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_no_billing_details_update_for_legacy_card_object() {
		$legacy_card = 'card_mock';

		// There is no payment method data within the request. This is the case e.g. for the automatic subscription renewals.
		$_POST['payment_method'] = '';

		$token = WC_Helper_Token::create_token( $legacy_card );

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 100 );
		$order->add_payment_token( $token );
		$order->save();

		$pi             = new Payment_Information( $legacy_card, $order, null, $token, null, null, null, '', 'card' );
		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status' => 'success',
			]
		);

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->returnValue( $payment_intent ) );

		$request->expects( $this->never() )
			->method( 'set_payment_method_update_data' );

		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_no_billing_details_update_for_legacy_card_object_src() {
		$legacy_card = 'src_mock';

		// There is no payment method data within the request. This is the case e.g. for the automatic subscription renewals.
		$_POST['payment_method'] = '';

		$token = WC_Helper_Token::create_token( $legacy_card );

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 100 );
		$order->add_payment_token( $token );
		$order->save();

		$pi             = new Payment_Information( $legacy_card, $order, null, $token, null, null, null, '', 'card' );
		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status' => 'success',
			]
		);

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->returnValue( $payment_intent ) );

		$request->expects( $this->never() )
			->method( 'set_payment_method_update_data' );

		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_billing_details_update_if_not_empty() {
		// There is no payment method data within the request. This is the case e.g. for the automatic subscription renewals.
		$_POST['payment_method'] = '';

		$token = WC_Helper_Token::create_token( 'pm_mock' );

		$expected_upe_payment_method = 'card';
		$order                       = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 100 );
		$order->add_payment_token( $token );
		$order->save();

		$pi = new Payment_Information( 'pm_mock', $order, null, $token, null, null, null, '', 'card' );

		$payment_intent = WC_Helper_Intention::create_intention(
			[
				'status' => 'success',
			]
		);

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->returnValue( $payment_intent ) );

		$request->expects( $this->once() )
			->method( 'set_payment_method_update_data' );

		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_process_payment_for_order_rejects_with_cached_minimum_amount() {
		set_transient( 'wcpay_minimum_amount_usd', '50', DAY_IN_SECONDS );

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 0.45 );
		$order->save();

		$pi = new Payment_Information( 'pm_test', $order, null, null, null, null, null, '', 'card' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The selected payment method requires a total amount of at least $0.50.' );
		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_process_payment_for_order_rejects_with_order_id_mismatch() {
		$order                = WC_Helper_Order::create_order();
		$intent_meta_order_id = 0;
		$woopay_intent_id     = 'woopay_invalid_intent_id_mock';
		$payment_intent       = WC_Helper_Intention::create_intention(
			[
				'status'   => 'success',
				'metadata' => [ 'order_id' => (string) $intent_meta_order_id ],
			]
		);

		$_POST['platform-checkout-intent'] = $woopay_intent_id;

		$payment_information = new Payment_Information( 'pm_test', $order, null, null, null, null, null, '', 'card' );

		$this->mock_wcpay_request( Get_Intention::class, 1, $woopay_intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $payment_intent );

		$this->expectException( 'WCPay\Exceptions\Order_ID_Mismatch_Exception' );
		$this->expectExceptionMessage( 'We&#039;re not able to process this payment. Please try again later. WooPayMeta: intent_meta_order_id: ' . $intent_meta_order_id . ', order_id: ' . $order->get_id() );
		$this->card_gateway->process_payment_for_order( WC()->cart, $payment_information );
	}

	public function test_set_mandate_data_to_payment_intent_if_not_required() {
		$payment_method = 'woocommerce_payments_sepa_debit';
		$order          = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 100 );
		$order->save();

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';
		$_POST['payment_method']               = $payment_method;
		$pi                                    = new Payment_Information( 'pm_test', $order, null, null, null, null, null, '', 'card' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention( [ 'status' => 'success' ] ) );
		$request->expects( $this->never() )
			->method( 'set_mandate_data' );

		// Mandate data is required for SEPA and Stripe Link only, $this->card_gateway is created with card hence mandate data should not be added.
		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_set_mandate_data_to_payment_intent_if_required() {
		// Mandate data is required for SEPA and Stripe Link, hence creating the gateway with a SEPA payment method should add mandate data.
		$gateway        = $this->get_gateway( Payment_Method::SEPA );
		$payment_method = 'woocommerce_payments_sepa_debit';
		$order          = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 100 );
		$order->save();

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';
		$_POST['payment_method']               = $payment_method;
		$pi                                    = new Payment_Information( 'pm_test', $order, null, null, null, null, null, '', 'card' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention( [ 'status' => 'success' ] ) );

		$request->expects( $this->once() )
			->method( 'set_mandate_data' )
			->with(
				$this->callback(
					function ( $data ) {
								return isset( $data['customer_acceptance']['type'] ) &&
								'online' === $data['customer_acceptance']['type'] &&
								isset( $data['customer_acceptance']['online'] ) &&
								is_array( $data['customer_acceptance']['online'] );
					}
				)
			);

		$gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_set_mandate_data_with_setup_intent_request_when_link_is_disabled() {
		// Disabled link is reflected in upe_enabled_payment_method_ids: when link is disabled, the array contains only card.
		$this->card_gateway->settings['upe_enabled_payment_method_ids'] = [ 'card' ];

		$payment_method = 'woocommerce_payments';
		$order          = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 0 );
		$order->save();
		$customer = 'cus_12345';

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( $customer ) );

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';
		$_POST['payment_method']               = $payment_method;
		$pi                                    = new Payment_Information( 'pm_test', $order, null, null, null, null, null, '', 'card' );
		$pi->must_save_payment_method_to_store();

		$request = $this->mock_wcpay_request( Create_And_Confirm_Setup_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_setup_intention(
					[ 'id' => 'seti_mock_123' ]
				)
			);
		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->willReturn( new WC_Payment_Token_CC() );

			$request->expects( $this->never() )
				->method( 'set_mandate_data' );

		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_set_mandate_data_with_setup_intent_request_when_link_is_enabled() {
		$this->card_gateway->settings['upe_enabled_payment_method_ids'] = [ 'card', 'link' ];

		$payment_method = 'woocommerce_payments';
		$order          = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 0 );
		$order->save();
		$customer = 'cus_12345';

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( $customer ) );

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';
		$_POST['payment_method']               = $payment_method;
		$pi                                    = new Payment_Information( 'pm_test', $order, null, null, null, null, null, '', 'card' );
		$pi->must_save_payment_method_to_store();

		$request = $this->mock_wcpay_request( Create_And_Confirm_Setup_Intention::class );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_setup_intention(
					[ 'id' => 'seti_mock_123' ]
				)
			);
		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->willReturn( new WC_Payment_Token_CC() );

			$request->expects( $this->once() )
				->method( 'set_mandate_data' )
				->with(
					$this->callback(
						function ( $data ) {
									return isset( $data['customer_acceptance']['type'] ) &&
									'online' === $data['customer_acceptance']['type'] &&
									isset( $data['customer_acceptance']['online'] ) &&
									is_array( $data['customer_acceptance']['online'] );
						}
					)
				);

		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
		$this->card_gateway->settings['upe_enabled_payment_method_ids'] = [ 'card' ];
	}

	public function test_is_mandate_data_required_card_and_link() {
		$this->card_gateway->update_option( 'upe_enabled_payment_method_ids', [ Payment_Method::LINK ] );
		$this->assertTrue( $this->card_gateway->is_mandate_data_required() );
	}

	public function test_is_mandate_data_required_sepa() {
		$sepa = $this->get_gateway( Payment_Method::SEPA );
		$this->assertTrue( $sepa->is_mandate_data_required() );
	}

	public function test_is_mandate_data_required_returns_false() {
		foreach ( $this->get_gateways_excluding( [ Payment_Method::SEPA, Payment_Method::CARD ] ) as $gateway ) {
			$this->assertFalse( $gateway->is_mandate_data_required() );
		}
	}

	public function test_non_reusable_gateways_not_available_when_changing_payment_method_for_card() {
		// Simulate is_changing_payment_method_for_subscription being true so that is_enabled_at_checkout() checks if the payment method is reusable().
		$_GET['change_payment_method'] = 10;
		WC_Subscriptions::set_wcs_is_subscription(
			function ( $order ) {
				return true;
			}
		);

		foreach ( $this->get_gateways_excluding( [ Payment_Method::CARD ] ) as $gateway ) {
			$this->assertFalse( $gateway->is_available() );
		}
	}

	public function test_gateway_enabled_when_payment_method_is_enabled() {
		$afterpay = $this->get_gateway( Payment_Method::AFTERPAY );
		$afterpay->update_option( 'upe_enabled_payment_method_ids', [ Payment_Method::AFTERPAY, Payment_Method::CARD, Payment_Method::P24, Payment_Method::BANCONTACT ] );
		$this->prepare_gateway_for_availability_testing( $afterpay );

		$this->assertTrue( $afterpay->is_available() );
	}

	public function test_gateway_disabled_when_payment_method_is_disabled() {
		$afterpay = $this->get_gateway( Payment_Method::AFTERPAY );
		$afterpay->update_option( 'upe_enabled_payment_method_ids', [ Payment_Method::CARD, Payment_Method::P24, Payment_Method::BANCONTACT ] );
		$this->prepare_gateway_for_availability_testing( $afterpay );

		$this->assertFalse( $afterpay->is_available() );
	}

	public function test_process_payment_for_order_cc_payment_method() {
		$payment_method                              = 'woocommerce_payments';
		$expected_upe_payment_method_for_pi_creation = 'card';
		$order                                       = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 100 );
		$order->save();

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';
		$_POST['payment_method']               = $payment_method;
		$pi                                    = new Payment_Information( 'pm_test', $order, null, null, null, null, null, '', 'card' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_payment_methods' )
			->with( [ $expected_upe_payment_method_for_pi_creation ] );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention( [ 'status' => 'success' ] ) );

		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_process_payment_for_order_upe_payment_method() {
		$payment_method                              = 'woocommerce_payments_sepa_debit';
		$expected_upe_payment_method_for_pi_creation = 'sepa_debit';
		$order                                       = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 100 );
		$order->save();

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';
		$_POST['payment_method']               = $payment_method;
		$pi                                    = new Payment_Information( 'pm_test', $order, null, null, null, null, null, '', 'card' );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );
		$request->expects( $this->once() )
			->method( 'set_payment_methods' )
			->with( [ $expected_upe_payment_method_for_pi_creation ] );
		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( WC_Helper_Intention::create_intention( [ 'status' => 'success' ] ) );

		$this->card_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_process_payment_caches_mimimum_amount_and_displays_error_upon_exception() {
		delete_transient( 'wcpay_minimum_amount_usd' );

		$amount   = 0.45;
		$customer = 'cus_12345';

		$order = WC_Helper_Order::create_order();
		$order->set_total( $amount );
		$order->save();

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( $customer ) );

		$_POST = [ 'wcpay-payment-method' => $pm = 'pm_mock' ];

		$this->get_fraud_prevention_service_mock()
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( false );

		$request = $this->mock_wcpay_request( Create_And_Confirm_Intention::class );

		$request->expects( $this->once() )
			->method( 'set_amount' )
			->with( (int) ( $amount * 100 ) );

		$request->expects( $this->once() )
			->method( 'set_payment_method' )
			->with( $pm );

		$request->expects( $this->once() )
			->method( 'set_customer' )
			->with( $customer );

		$request->expects( $this->once() )
			->method( 'set_capture_method' )
			->with( false );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->will( $this->throwException( new Amount_Too_Small_Exception( 'Error: Amount must be at least $60 usd', 6000, 'usd', 400 ) ) );
		$this->expectException( Exception::class );
		$price   = html_entity_decode( wp_strip_all_tags( wc_price( 60, [ 'currency' => 'USD' ] ) ) );
		$message = 'The selected payment method requires a total amount of at least ' . $price . '.';
		$this->expectExceptionMessage( $message );

		try {
			$this->card_gateway->process_payment( $order->get_id() );
		} catch ( Exception $e ) {
			$this->assertEquals( '6000', get_transient( 'wcpay_minimum_amount_usd' ) );
			throw $e;
		}
	}

	public function test_process_payment_rejects_if_missing_fraud_prevention_token() {
		$order = WC_Helper_Order::create_order();

		$fraud_prevention_service_mock = $this->get_fraud_prevention_service_mock();

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		try {
			$this->card_gateway->process_payment( $order->get_id() );
		} catch ( Exception $e ) {
			$this->assertEquals( 'Exception', get_class( $e ) );
			$this->assertEquals( "We're not able to process this payment. Please refresh the page and try again.", $e->getMessage() );
			$this->assertEquals( 'WCPay\Exceptions\Fraud_Prevention_Enabled_Exception', get_class( $e->getPrevious() ) );
		}
	}

	public function test_process_payment_rejects_if_invalid_fraud_prevention_token() {
		$order = WC_Helper_Order::create_order();

		$fraud_prevention_service_mock = $this->get_fraud_prevention_service_mock();

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'verify_token' )
			->with( 'incorrect-token' )
			->willReturn( false );

		$_POST['wcpay-fraud-prevention-token'] = 'incorrect-token';

		try {
			$this->card_gateway->process_payment( $order->get_id() );
		} catch ( Exception $e ) {
			$this->assertEquals( 'Exception', get_class( $e ) );
			$this->assertEquals( "We're not able to process this payment. Please refresh the page and try again.", $e->getMessage() );
			$this->assertEquals( 'WCPay\Exceptions\Fraud_Prevention_Enabled_Exception', get_class( $e->getPrevious() ) );
		}
	}

	public function test_process_payment_marks_order_as_blocked_for_fraud() {
		$order = WC_Helper_Order::create_order();

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$fraud_prevention_service_mock = $this->get_fraud_prevention_service_mock();

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'verify_token' )
			->with( 'correct-token' )
			->willReturn( true );

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';

		$this->mock_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( false );

		$mock_order_service = $this->getMockBuilder( WC_Payments_Order_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_order_service
			->expects( $this->once() )
			->method( 'mark_order_blocked_for_fraud' );

		$mock_wcpay_gateway = $this->get_partial_mock_for_gateway(
			[ 'prepare_payment_information', 'process_payment_for_order' ],
			[ WC_Payments_Order_Service::class => $mock_order_service ]
		);

		$error_message = "There's a problem with this payment. Please try again or use a different payment method.";

		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'prepare_payment_information' );
		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'process_payment_for_order' )
			->willThrowException( new API_Exception( $error_message, 'wcpay_blocked_by_fraud_rule', 400, 'card_error' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $error_message );

		$mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_process_payment_marks_order_as_blocked_for_fraud_avs_mismatch() {
		$ruleset_config = [
			[
				'key'     => 'avs_verification',
				'outcome' => 'block',
				'check'   => [
					'key'      => 'avs_mismatch',
					'operator' => 'equals',
					'value'    => true,
				],
			],
		];
		set_transient( 'wcpay_fraud_protection_settings', $ruleset_config, DAY_IN_SECONDS );

		$order = WC_Helper_Order::create_order();

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$fraud_prevention_service_mock = $this->get_fraud_prevention_service_mock();

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'verify_token' )
			->with( 'correct-token' )
			->willReturn( true );

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';

		$this->mock_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( false );

		$mock_order_service = $this->getMockBuilder( WC_Payments_Order_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_order_service
			->expects( $this->once() )
			->method( 'mark_order_blocked_for_fraud' );

		$mock_wcpay_gateway = $this->get_partial_mock_for_gateway(
			[ 'prepare_payment_information', 'process_payment_for_order' ],
			[ WC_Payments_Order_Service::class => $mock_order_service ]
		);

		$error_message = "There's a problem with this payment. Please try again or use a different payment method.";

		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'prepare_payment_information' );
		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'process_payment_for_order' )
			->willThrowException( new API_Exception( $error_message, 'incorrect_zip', 400, 'card_error' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $error_message );

		$mock_wcpay_gateway->process_payment( $order->get_id() );

		delete_transient( 'wcpay_fraud_protection_settings' );
	}

	public function test_process_payment_marks_order_as_blocked_for_postal_code_mismatch() {
		$ruleset_config = [
			[
				'key'     => 'address_mismatch',
				'outcome' => 'block',
				'check'   => [
					'key'      => 'address_mismatch',
					'operator' => 'equals',
					'value'    => true,
				],
			],
		];
		set_transient( 'wcpay_fraud_protection_settings', $ruleset_config, DAY_IN_SECONDS );

		$order = WC_Helper_Order::create_order();

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$fraud_prevention_service_mock = $this->get_fraud_prevention_service_mock();

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'verify_token' )
			->with( 'correct-token' )
			->willReturn( true );

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';

		$this->mock_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( false );

		$mock_order_service = $this->getMockBuilder( WC_Payments_Order_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$mock_order_service
			->expects( $this->never() )
			->method( 'mark_order_blocked_for_fraud' );

		$mock_wcpay_gateway = $this->get_partial_mock_for_gateway(
			[ 'prepare_payment_information', 'process_payment_for_order' ],
			[ WC_Payments_Order_Service::class => $mock_order_service ]
		);

		$error_message = 'We couldn’t verify the postal code in your billing address. Make sure the information is current with your card issuing bank and try again.';

		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'prepare_payment_information' );
		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'process_payment_for_order' )
			->willThrowException( new API_Exception( $error_message, 'incorrect_zip', 400, 'card_error' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $error_message );

		$mock_wcpay_gateway->process_payment( $order->get_id() );

		delete_transient( 'wcpay_fraud_protection_settings' );
	}

	public function test_process_payment_continues_if_valid_fraud_prevention_token() {
		$order = WC_Helper_Order::create_order();

		$fraud_prevention_service_mock = $this->get_fraud_prevention_service_mock();

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'is_enabled' )
			->willReturn( true );

		$fraud_prevention_service_mock
			->expects( $this->once() )
			->method( 'verify_token' )
			->with( 'correct-token' )
			->willReturn( true );

		$_POST['wcpay-fraud-prevention-token'] = 'correct-token';

		$this->mock_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( false );

		$mock_wcpay_gateway = $this->get_partial_mock_for_gateway( [ 'prepare_payment_information', 'process_payment_for_order' ] );
		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'prepare_payment_information' );
		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'process_payment_for_order' );

		$mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_process_payment_continues_if_missing_fraud_prevention_token_but_request_is_from_woopay() {
		$order = WC_Helper_Order::create_order();

		add_filter( 'wcpay_is_woopay_store_api_request', '__return_true' );

		$fraud_prevention_service_mock = $this->get_fraud_prevention_service_mock();

		$fraud_prevention_service_mock
			->expects( $this->never() )
			->method( 'is_enabled' );

		$this->mock_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( false );

		$mock_wcpay_gateway = $this->get_partial_mock_for_gateway( [ 'prepare_payment_information', 'process_payment_for_order' ] );
		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'prepare_payment_information' );
		$mock_wcpay_gateway
			->expects( $this->once() )
			->method( 'process_payment_for_order' );

		$mock_wcpay_gateway->process_payment( $order->get_id() );

		remove_filter( 'wcpay_is_woopay_store_api_request', '__return_true' );
	}

	public function test_get_upe_enabled_payment_method_statuses_with_empty_cache() {
		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [] );

		$this->assertEquals(
			[
				'card_payments' => [
					'status'       => 'active',
					'requirements' => [],
				],
			],
			$this->card_gateway->get_upe_enabled_payment_method_statuses()
		);
	}

	public function test_get_upe_enabled_payment_method_statuses_with_cache() {
		$caps             = [
			'card_payments'       => 'active',
			'sepa_debit_payments' => 'active',
		];
		$cap_requirements = [
			'card_payments'       => [],
			'sepa_debit_payments' => [],
		];
		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'capabilities'            => $caps,
					'capability_requirements' => $cap_requirements,
				]
			);

		$this->assertEquals(
			[
				'card_payments'       => [
					'status'       => 'active',
					'requirements' => [],
				],
				'sepa_debit_payments' => [
					'status'       => 'active',
					'requirements' => [],
				],
			],
			$this->card_gateway->get_upe_enabled_payment_method_statuses()
		);
	}

	public function test_woopay_form_field_defaults() {
		// need to delete the existing options to ensure nothing is in the DB from the `setUp` phase, where the method is instantiated.
		delete_option( 'woocommerce_woocommerce_payments_settings' );

		$this->assertEquals(
			[
				'product',
				'cart',
				'checkout',
			],
			$this->card_gateway->get_option( 'platform_checkout_button_locations' )
		);

		$this->assertEquals(
			'By placing this order, you agree to our [terms] and understand our [privacy_policy].',
			$this->card_gateway->get_option( 'platform_checkout_custom_message' )
		);
	}

	public function test_is_woopay_enabled_returns_true() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->card_gateway->update_option( 'platform_checkout', 'yes' );
		wp_set_current_user( 1 );
		add_filter( 'woocommerce_is_checkout', '__return_true' );

		$this->assertTrue( $this->woopay_utilities->should_enable_woopay( $this->card_gateway ) );

		remove_filter( 'woocommerce_is_checkout', '__return_true' );
	}

	public function test_should_use_stripe_platform_on_checkout_page_not_woopay_eligible() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => false ] );
		$this->assertFalse( $this->card_gateway->should_use_stripe_platform_on_checkout_page() );
	}

	public function test_should_use_stripe_platform_on_checkout_page_not_woopay() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->card_gateway->update_option( 'platform_checkout', 'no' );

		$this->assertFalse( $this->card_gateway->should_use_stripe_platform_on_checkout_page() );
	}

	public function is_woopay_falsy_value_provider() {
		return [
			[ '0' ],
			[ 0 ],
			[ null ],
			[ false ],
			'(bool) true is not strictly equal to (int) 1' => [ true ],
			[ 'foo' ],
			[ [] ],
		];
	}

	/**
	 * @expectedDeprecated is_in_dev_mode
	 */
	public function test_is_in_dev_mode() {
		$mode = WC_Payments::mode();

		$mode->dev();
		$this->assertTrue( $this->card_gateway->is_in_dev_mode() );

		$mode->test();
		$this->assertFalse( $this->card_gateway->is_in_dev_mode() );

		$mode->live();
		$this->assertFalse( $this->card_gateway->is_in_dev_mode() );
	}

	/**
	 * @expectedDeprecated is_in_test_mode
	 */
	public function test_is_in_test_mode() {
		$mode = WC_Payments::mode();

		$mode->dev();
		$this->assertTrue( $this->card_gateway->is_in_test_mode() );

		$mode->test();
		$this->assertTrue( $this->card_gateway->is_in_test_mode() );

		$mode->live();
		$this->assertFalse( $this->card_gateway->is_in_test_mode() );
	}

	/**
	 * Create a partial mock for WC_Payment_Gateway_WCPay class.
	 *
	 * @param array $methods                 Method names that need to be mocked.
	 * @param array $constructor_replacement Array of constructor arguments that need to be replaced.
	 * The key is the class name and the value is the replacement object.
	 * [ WC_Payments_Order_Service::class => $mock_order_service ]
	 *
	 * @return MockObject|WC_Payment_Gateway_WCPay
	 */
	private function get_partial_mock_for_gateway( array $methods = [], array $constructor_replacement = [] ) {
		$constructor_args = [
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$this->mock_payment_method,
			[ $this->mock_payment_method ],
			$this->mock_rate_limiter,
			$this->order_service,
			$this->mock_dpps,
			$this->mock_localization_service,
			$this->mock_fraud_service,
			$this->mock_duplicates_detection_service,
		];

		foreach ( $constructor_replacement as $key => $value ) {
			foreach ( $constructor_args as $index => $arg ) {
				if ( $arg instanceof $key ) {
					$constructor_args[ $index ] = $value;
				}
			}
		}

		return $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->setConstructorArgs( $constructor_args )
			->setMethods( $methods )
			->getMock();
	}


	/**
	 * Tests that no payment is processed when the $_POST 'is-woopay-preflight-check` is present.
	 */
	public function test_no_payment_is_processed_for_woopay_preflight_check_request() {
		$_POST['is-woopay-preflight-check'] = true;

		// Arrange: Create an order to test with.
		$order_data = [
			'status' => 'draft',
			'total'  => '100',
		];

		$order = wc_create_order( $order_data );

		$mock_wcpay_gateway = $this->get_partial_mock_for_gateway( [ 'process_payment_for_order' ] );

		// Assert: No payment was processed.
		$mock_wcpay_gateway
			->expects( $this->never() )
			->method( 'process_payment_for_order' );

		// Act: process payment.
		$response = $mock_wcpay_gateway->process_payment( $order->get_id() );
	}

	public function test_should_use_new_process_requires_dev_mode() {
		$mock_router = $this->createMock( Router::class );
		wcpay_get_test_container()->replace( Router::class, $mock_router );

		$order = WC_Helper_Order::create_order();

		// Assert: The router is never called.
		$mock_router->expects( $this->never() )
			->method( 'should_use_new_payment_process' );

		$this->assertFalse( $this->card_gateway->should_use_new_process( $order ) );
	}

	public function test_should_use_new_process_returns_false_if_feature_unavailable() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$mock_router = $this->createMock( Router::class );
		wcpay_get_test_container()->replace( Router::class, $mock_router );

		$order = WC_Helper_Order::create_order();

		// Assert: Feature returns false.
		$mock_router->expects( $this->once() )
			->method( 'should_use_new_payment_process' )
			->willReturn( false );

		// Act: Call the method.
		$result = $this->card_gateway->should_use_new_process( $order );
		$this->assertFalse( $result );
	}

	public function test_should_use_new_process_uses_the_new_process() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$mock_router  = $this->createMock( Router::class );
		$mock_service = $this->createMock( PaymentProcessingService::class );
		$order        = WC_Helper_Order::create_order();

		wcpay_get_test_container()->replace( Router::class, $mock_router );
		wcpay_get_test_container()->replace( PaymentProcessingService::class, $mock_service );

		// Assert: Feature returns false.
		$mock_router->expects( $this->once() )
			->method( 'should_use_new_payment_process' )
			->willReturn( true );

		// Act: Call the method.
		$result = $this->card_gateway->should_use_new_process( $order );
		$this->assertTrue( $result );
	}

	public function test_should_use_new_process_adds_base_factor() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order( 1, 0 );

		$this->expect_router_factor( Factor::NEW_PAYMENT_PROCESS(), true );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_no_payment() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order( 1, 0 );

		$this->expect_router_factor( Factor::NO_PAYMENT(), true );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_no_payment() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();
		$order->set_total( 10 );
		$order->save();

		$this->expect_router_factor( Factor::NO_PAYMENT(), false );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_no_payment_when_saving_pm() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order( 1, 0 );

		// Simulate a payment method being saved to force payment processing.
		$_POST['wc-woocommerce_payments-new-payment-method'] = 'pm_XYZ';

		$this->expect_router_factor( Factor::NO_PAYMENT(), false );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_use_saved_pm() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();
		$token = WC_Helper_Token::create_token( 'pm_XYZ' );

		// Simulate that a saved token is being used.
		$_POST['payment_method']                        = 'woocommerce_payments';
		$_POST['wc-woocommerce_payments-payment-token'] = $token->get_id();

		$this->expect_router_factor( Factor::USE_SAVED_PM(), true );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_use_saved_pm() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		// Simulate that a saved token is being used.
		$_POST['payment_method']                        = 'woocommerce_payments';
		$_POST['wc-woocommerce_payments-payment-token'] = 'new';

		$this->expect_router_factor( Factor::USE_SAVED_PM(), false );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_save_pm() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		$_POST['wc-woocommerce_payments-new-payment-method'] = '1';

		$this->expect_router_factor( Factor::SAVE_PM(), true );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_save_pm_for_subscription() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		WC_Subscriptions::$wcs_order_contains_subscription = '__return_true';

		$this->expect_router_factor( Factor::SAVE_PM(), true );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_save_pm() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();
		$token = WC_Helper_Token::create_token( 'pm_XYZ' );

		// Simulate that a saved token is being used.
		$_POST['wc-woocommerce_payments-new-payment-method'] = '1';
		$_POST['payment_method']                             = 'woocommerce_payments';
		$_POST['wc-woocommerce_payments-payment-token']      = $token->get_id();

		$this->expect_router_factor( Factor::SAVE_PM(), false );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_subscription_signup() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		WC_Subscriptions::$wcs_order_contains_subscription = '__return_true';

		$this->expect_router_factor( Factor::SUBSCRIPTION_SIGNUP(), true );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_subscription_signup() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		WC_Subscriptions::$wcs_order_contains_subscription = '__return_false';

		$this->expect_router_factor( Factor::SUBSCRIPTION_SIGNUP(), false );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_woopay_payment() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		$_POST['platform-checkout-intent'] = 'pi_ZYX';

		$this->expect_router_factor( Factor::WOOPAY_PAYMENT(), true );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_woopay_payment() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST['platform-checkout-intent'] );

		$this->expect_router_factor( Factor::WOOPAY_PAYMENT(), false );
		$this->card_gateway->should_use_new_process( $order );
	}

	/**
	 * Testing the positive WCPay subscription signup factor is not possible,
	 * as the check relies on the existence of the `WC_Subscriptions` class
	 * through an un-mockable method, and the class simply exists.
	 */
	public function test_should_use_new_process_determines_negative_wcpay_subscription_signup() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		WC_Subscriptions::$wcs_order_contains_subscription = '__return_true';
		add_filter( 'wcpay_is_wcpay_subscriptions_enabled', '__return_true' );

		$this->expect_router_factor( Factor::WCPAY_SUBSCRIPTION_SIGNUP(), false );
		$this->card_gateway->should_use_new_process( $order );
	}

	public function test_new_process_payment() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$mock_service = $this->createMock( PaymentProcessingService::class );
		$mock_router  = $this->createMock( Router::class );
		$order        = WC_Helper_Order::create_order();
		$mock_state   = $this->createMock( CompletedState::class );

		wcpay_get_test_container()->replace( PaymentProcessingService::class, $mock_service );
		wcpay_get_test_container()->replace( Router::class, $mock_router );

		$mock_router->expects( $this->once() )
			->method( 'should_use_new_payment_process' )
			->willReturn( true );

		// Assert: The new service is called.
		$mock_service->expects( $this->once() )
			->method( 'process_payment' )
			->with( $order->get_id() )
			->willReturn( $mock_state );

		$result = $this->card_gateway->process_payment( $order->get_id() );
		$this->assertSame(
			[
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			],
			$result
		);
	}

	public function test_process_payment_rate_limiter_enabled_throw_exception() {
		$order = WC_Helper_Order::create_order();

		$this->mock_rate_limiter
			->expects( $this->once() )
			->method( 'is_limited' )
			->willReturn( true );

		try {
			$this->card_gateway->process_payment( $order->get_id() );
		} catch ( Exception $e ) {
			$this->assertEquals( 'Exception', get_class( $e ) );
			$this->assertEquals( 'Your payment was not processed.', $e->getMessage() );
			$this->assertEquals( 'WCPay\Exceptions\Rate_Limiter_Enabled_Exception', get_class( $e->getPrevious() ) );
		}
	}

	public function test_process_payment_returns_correct_redirect() {
		$order = WC_Helper_Order::create_order();
		$_POST = [ 'wcpay-payment-method' => 'pm_mock' ];

		$this->mock_wcpay_request( Create_And_Confirm_Intention::class, 1 )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::PROCESSING ] )
			);

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->willReturn( new WC_Payment_Token_CC() );

		$result = $this->card_gateway->process_payment( $order->get_id() );

		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $order->get_checkout_order_received_url(), $result['redirect'] );
	}

	public function test_process_payment_returns_correct_redirect_when_using_payment_request() {
		$order                         = WC_Helper_Order::create_order();
		$_POST['payment_request_type'] = 'google_pay';
		$_POST                         = [ 'wcpay-payment-method' => 'pm_mock' ];

		$this->mock_wcpay_request( Create_And_Confirm_Intention::class, 1 )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn(
				WC_Helper_Intention::create_intention( [ 'status' => Intent_Status::PROCESSING ] )
			);

		$this->mock_token_service
			->expects( $this->once() )
			->method( 'add_payment_method_to_user' )
			->willReturn( new WC_Payment_Token_CC() );

		$result = $this->card_gateway->process_payment( $order->get_id() );

		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( $order->get_checkout_order_received_url(), $result['redirect'] );
	}

	public function is_proper_intent_used_with_order_returns_false() {
		$this->assertFalse( $this->card_gateway->is_proper_intent_used_with_order( WC_Helper_Order::create_order(), 'wrong_intent_id' ) );
	}

	/**
	 * Sets up the expectation for a certain factor for the new payment
	 * process to be either set or unset.
	 *
	 * @param Factor $factor_name Factor constant.
	 * @param bool   $value       Expected value.
	 */
	private function expect_router_factor( $factor_name, $value ) {
		$mock_router = $this->createMock( Router::class );
		wcpay_get_test_container()->replace( Router::class, $mock_router );

		$checker = function ( $factors ) use ( $factor_name, $value ) {
			$is_in_array = in_array( $factor_name, $factors, true );
			return $value ? $is_in_array : ! $is_in_array;
		};

		$mock_router->expects( $this->once() )
			->method( 'should_use_new_payment_process' )
			->with( $this->callback( $checker ) );
	}

	/**
	 * Mocks Fraud_Prevention_Service.
	 *
	 * @return MockObject|Fraud_Prevention_Service
	 */
	private function get_fraud_prevention_service_mock() {
		$fraud_prevention_service_mock = $this->getMockBuilder( Fraud_Prevention_Service::class )
			->disableOriginalConstructor()
			->getMock();

		Fraud_Prevention_Service::set_instance( $fraud_prevention_service_mock );

		return $fraud_prevention_service_mock;
	}

	private function create_charge_object() {
		$created = new DateTime();
		$created->setTimestamp( $this->mock_charge_created );

		return new WC_Payments_API_Charge( $this->mock_charge_id, 1500, $created );
	}

	private function prepare_gateway_for_availability_testing( $gateway ) {
		WC_Payments::mode()->test();
		$current_currency = strtolower( get_woocommerce_currency() );
		$this->mock_wcpay_account->expects( $this->once() )->method( 'get_account_customer_supported_currencies' )->will(
			$this->returnValue(
				[
					$current_currency,
				]
			)
		);

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'capabilities'            => [
						'afterpay_clearpay_payments' => 'active',
					],
					'capability_requirements' => [
						'afterpay_clearpay_payments' => [],
					],
				]
			);

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_account_status_data' )
			->willReturn(
				[
					'paymentsEnabled' => true,
				]
			);
			$gateway->update_option( WC_Payment_Gateway_WCPay::METHOD_ENABLED_KEY, 'yes' );
			$gateway->init_settings();
	}

	private function init_payment_methods() {
		$payment_methods = [];

		$payment_method_classes = [
			CC_Payment_Method::class,
			Bancontact_Payment_Method::class,
			Sepa_Payment_Method::class,
			Giropay_Payment_Method::class,
			Sofort_Payment_Method::class,
			P24_Payment_Method::class,
			Ideal_Payment_Method::class,
			Becs_Payment_Method::class,
			Eps_Payment_Method::class,
			Link_Payment_Method::class,
			Affirm_Payment_Method::class,
			Afterpay_Payment_Method::class,
			Klarna_Payment_Method::class,
		];

		foreach ( $payment_method_classes as $payment_method_class ) {
			$payment_method                               = new $payment_method_class( $this->mock_token_service );
			$payment_methods[ $payment_method->get_id() ] = new $payment_method_class( $this->mock_token_service );
		}
		$this->payment_methods = $payment_methods;
	}

	private function init_gateways() {
		$this->init_payment_methods();
		$gateways = [];

		foreach ( $this->payment_methods as $payment_method ) {
			$gateways[] = new WC_Payment_Gateway_WCPay(
				$this->mock_api_client,
				$this->mock_wcpay_account,
				$this->mock_customer_service,
				$this->mock_token_service,
				$this->mock_action_scheduler_service,
				$payment_method,
				$this->payment_methods,
				$this->mock_rate_limiter,
				$this->order_service,
				$this->mock_dpps,
				$this->mock_localization_service,
				$this->mock_fraud_service,
				$this->mock_duplicates_detection_service
			);
		}

		$this->gateways     = $gateways;
		$this->card_gateway = $gateways[0];
	}

	private function get_gateways_excluding( $excluded_payment_method_ids ) {
		return array_filter(
			$this->gateways,
			function ( $gateway ) use ( $excluded_payment_method_ids ) {
				return ! in_array( $gateway->get_payment_method()->get_id(), $excluded_payment_method_ids, true );
			}
		);
	}

	private function get_gateway( $payment_method_id ) {
		return ( array_values(
			array_filter(
				$this->gateways,
				function ( $gateway ) use ( $payment_method_id ) {
					return $payment_method_id === $gateway->get_payment_method()->get_id();
				}
			)
		) )[0] ?? null;
	}
}

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
use WCPay\Core\Server\Request\Update_Intention;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Intent_Status;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\API_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Internal\Payment\Factor;
use WCPay\Internal\Payment\Router;
use WCPay\Internal\Payment\State\CompletedState;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\PaymentProcessingService;
use WCPay\Payment_Information;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Sepa_Payment_Method;
use WCPay\WooPay\WooPay_Utilities;
use WCPay\Session_Rate_Limiter;

// Need to use WC_Mock_Data_Store.
require_once dirname( __FILE__ ) . '/helpers/class-wc-mock-wc-data-store.php';

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Test extends WCPAY_UnitTestCase {

	const NO_REQUIREMENTS      = false;
	const PENDING_REQUIREMENTS = true;

	/**
	 * System under test.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

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
		$this->mock_wcpay_account
			->expects( $this->any() )
			->method( 'get_fees' )
			->willReturn(
				[
					'card' => [
						'base' => 0.1,
					],
				]
			);

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
		$this->mock_fraud_service = $this->createMock( WC_Payments_Fraud_Service::class );

		$this->mock_payment_method = $this->getMockBuilder( CC_Payment_Method::class )
			->setConstructorArgs( [ $this->mock_token_service ] )
			->setMethods( [ 'is_subscription_item_in_cart' ] )
			->getMock();

		$this->wcpay_gateway = new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$this->mock_payment_method,
			[ 'card' => $this->mock_payment_method ],
			$this->mock_rate_limiter,
			$this->order_service,
			$this->mock_dpps,
			$this->mock_localization_service,
			$this->mock_fraud_service
		);

		WC_Payments::set_gateway( $this->wcpay_gateway );

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

		// Fall back to an US store.
		update_option( 'woocommerce_store_postcode', '94110' );
		$this->wcpay_gateway->update_option( 'saved_cards', 'yes' );

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

		$this->wcpay_gateway->attach_exchange_info_to_order( $order, $charge_id );

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

		$this->wcpay_gateway->attach_exchange_info_to_order( $order, $charge_id );

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

		$this->wcpay_gateway->attach_exchange_info_to_order( $order, $charge_id );
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

		$this->wcpay_gateway->attach_exchange_info_to_order( $order, $charge_id );
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

		$this->wcpay_gateway->save_payment_method_checkbox();
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

		$this->wcpay_gateway->save_payment_method_checkbox( true );
	}

	public function test_save_payment_method_checkbox_not_displayed_when_stripe_platform_account_used() {
		// Setup the test so that should_use_stripe_platform_on_checkout_page returns true.
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->wcpay_gateway->update_option( 'platform_checkout', 'yes' );
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

		$this->wcpay_gateway->save_payment_method_checkbox( false );

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

		$result = $this->wcpay_gateway->capture_charge( $order );

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

		$result = $this->wcpay_gateway->capture_charge( $order );

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

		$result = $this->wcpay_gateway->capture_charge( $order );

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

		$result = $this->wcpay_gateway->capture_charge( $order );

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

		$result = $this->wcpay_gateway->capture_charge( $order );

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

		$result = $this->wcpay_gateway->capture_charge( $order );

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

		$result = $this->wcpay_gateway->capture_charge( $order );

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

		$result = $this->wcpay_gateway->capture_charge( $order, true, [] );

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

		$result = $this->wcpay_gateway->capture_charge( $order, false );

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

		$this->wcpay_gateway->cancel_authorization( $order );

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

		$this->wcpay_gateway->cancel_authorization( $order );

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
		$result = $this->wcpay_gateway->add_payment_method();
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

		$result = $this->wcpay_gateway->create_and_confirm_setup_intent();

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

		$result = $this->wcpay_gateway->create_and_confirm_setup_intent();

		$this->assertSame( 'seti_mock_123', $result->get_id() );
	}

	public function test_add_payment_method_no_intent() {
		$result = $this->wcpay_gateway->add_payment_method();
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

		$result = $this->wcpay_gateway->add_payment_method();

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

		$result = $this->wcpay_gateway->add_payment_method();

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

		$result = $this->wcpay_gateway->add_payment_method();

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

		$this->wcpay_gateway->schedule_order_tracking( $order->get_id(), $order );
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

		$this->wcpay_gateway->schedule_order_tracking( $order->get_id(), $order );
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

		$this->wcpay_gateway->schedule_order_tracking( $order->get_id(), $order );
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

		$this->wcpay_gateway->schedule_order_tracking( $order->get_id(), $order );
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

		$this->wcpay_gateway->schedule_order_tracking( $order->get_id(), $order );
	}

	public function test_outputs_payments_settings_screen() {
		ob_start();
		$this->wcpay_gateway->output_payments_settings_screen();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wcpay-account-settings-container"%a', $output );
	}

	public function test_outputs_express_checkout_settings_screen() {
		$_GET['method'] = 'foo';
		ob_start();
		$this->wcpay_gateway->output_payments_settings_screen();
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
			$validated_value = $this->wcpay_gateway->validate_account_statement_descriptor_field( $key, $value );
			$this->assertEquals( $expected ?? $value, $validated_value );
		} else {
			$this->expectExceptionMessage( 'Customer bank statement is invalid.' );
			$this->wcpay_gateway->validate_account_statement_descriptor_field( $key, $value );
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
			$this->wcpay_gateway->get_option( 'payment_request_button_locations' )
		);
		$this->assertEquals(
			'medium',
			$this->wcpay_gateway->get_option( 'payment_request_button_size' )
		);

		$form_fields = $this->wcpay_gateway->get_form_fields();

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
		$this->assertTrue( $this->wcpay_gateway->is_available_for_current_currency() );
	}

	public function test_payment_gateway_enabled_for_empty_supported_currency_list() {
		// We want to avoid disabling the gateway in case the API doesn't give back any currency suppported.
		$this->mock_wcpay_account->expects( $this->once() )->method( 'get_account_customer_supported_currencies' )->will(
			$this->returnValue(
				[]
			)
		);
		$this->assertTrue( $this->wcpay_gateway->is_available_for_current_currency() );
	}

	public function test_payment_gateway_disabled_for_unsupported_currency() {
		$this->mock_wcpay_account->expects( $this->once() )->method( 'get_account_customer_supported_currencies' )->will(
			$this->returnValue(
				[
					'btc',
				]
			)
		);
		$this->assertFalse( $this->wcpay_gateway->is_available_for_current_currency() );
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

		$this->wcpay_gateway->process_payment_for_order( WC()->cart, $pi );
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
		$this->wcpay_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_mandate_data_not_added_to_payment_intent_if_not_required() {
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

		// Mandate data is required for SEPA and Stripe Link only, $this->wcpay_gateway is created with card hence mandate data should not be added.
		$this->wcpay_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_mandate_data_added_to_payment_intent_if_required() {
		// Mandate data is required for SEPA and Stripe Link, hence creating the gateway with a SEPA payment method should add mandate data.
		$gateway        = $this->create_gateway_with( new Sepa_Payment_Method( $this->mock_token_service ) );
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

	public function test_mandate_data_not_added_to_setup_intent_request_when_link_is_disabled() {
		// Disabled link is reflected in upe_enabled_payment_method_ids: when link is disabled, the array contains only card.
		$this->wcpay_gateway->settings['upe_enabled_payment_method_ids'] = [ 'card' ];

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

		$this->wcpay_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_mandate_data_added_to_setup_intent_request_when_link_is_enabled() {
		$this->wcpay_gateway->settings['upe_enabled_payment_method_ids'] = [ 'card', 'link' ];

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

		$this->wcpay_gateway->process_payment_for_order( WC()->cart, $pi );
		$this->wcpay_gateway->settings['upe_enabled_payment_method_ids'] = [ 'card' ];
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

		$this->wcpay_gateway->process_payment_for_order( WC()->cart, $pi );
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

		$this->wcpay_gateway->process_payment_for_order( WC()->cart, $pi );
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
			$this->wcpay_gateway->process_payment( $order->get_id() );
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

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( "We're not able to process this payment. Please refresh the page and try again." );
		$this->wcpay_gateway->process_payment( $order->get_id() );
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

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( "We're not able to process this payment. Please refresh the page and try again." );
		$this->wcpay_gateway->process_payment( $order->get_id() );
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
			$this->wcpay_gateway->get_upe_enabled_payment_method_statuses()
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
			$this->wcpay_gateway->get_upe_enabled_payment_method_statuses()
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
			$this->wcpay_gateway->get_option( 'platform_checkout_button_locations' )
		);

		$this->assertEquals(
			'By placing this order, you agree to our [terms] and understand our [privacy_policy].',
			$this->wcpay_gateway->get_option( 'platform_checkout_custom_message' )
		);
	}

	public function test_is_woopay_enabled_returns_true() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->wcpay_gateway->update_option( 'platform_checkout', 'yes' );
		wp_set_current_user( 1 );
		add_filter( 'woocommerce_is_checkout', '__return_true' );

		$this->assertTrue( $this->woopay_utilities->should_enable_woopay( $this->wcpay_gateway ) );

		remove_filter( 'woocommerce_is_checkout', '__return_true' );
	}

	public function test_should_use_stripe_platform_on_checkout_page_not_woopay_eligible() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => false ] );
		$this->assertFalse( $this->wcpay_gateway->should_use_stripe_platform_on_checkout_page() );
	}

	public function test_should_use_stripe_platform_on_checkout_page_not_woopay() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->wcpay_gateway->update_option( 'platform_checkout', 'no' );

		$this->assertFalse( $this->wcpay_gateway->should_use_stripe_platform_on_checkout_page() );
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
		$this->assertTrue( $this->wcpay_gateway->is_in_dev_mode() );

		$mode->test();
		$this->assertFalse( $this->wcpay_gateway->is_in_dev_mode() );

		$mode->live();
		$this->assertFalse( $this->wcpay_gateway->is_in_dev_mode() );
	}

	/**
	 * @expectedDeprecated is_in_test_mode
	 */
	public function test_is_in_test_mode() {
		$mode = WC_Payments::mode();

		$mode->dev();
		$this->assertTrue( $this->wcpay_gateway->is_in_test_mode() );

		$mode->test();
		$this->assertTrue( $this->wcpay_gateway->is_in_test_mode() );

		$mode->live();
		$this->assertFalse( $this->wcpay_gateway->is_in_test_mode() );
	}

	/**
	 * Create a partial mock for WC_Payment_Gateway_WCPay class.
	 *
	 * @param array $methods Method names that need to be mocked.
	 * @return MockObject|WC_Payment_Gateway_WCPay
	 */
	private function get_partial_mock_for_gateway( array $methods = [] ) {
		return $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->setConstructorArgs(
				[
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
				]
			)
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

		$this->assertFalse( $this->wcpay_gateway->should_use_new_process( $order ) );
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
		$result = $this->wcpay_gateway->should_use_new_process( $order );
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
		$result = $this->wcpay_gateway->should_use_new_process( $order );
		$this->assertTrue( $result );
	}

	public function test_should_use_new_process_adds_base_factor() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order( 1, 0 );

		$this->expect_router_factor( Factor::NEW_PAYMENT_PROCESS(), true );
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_no_payment() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order( 1, 0 );

		$this->expect_router_factor( Factor::NO_PAYMENT(), true );
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_no_payment() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();
		$order->set_total( 10 );
		$order->save();

		$this->expect_router_factor( Factor::NO_PAYMENT(), false );
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_no_payment_when_saving_pm() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order( 1, 0 );

		// Simulate a payment method being saved to force payment processing.
		$_POST['wc-woocommerce_payments-new-payment-method'] = 'pm_XYZ';

		$this->expect_router_factor( Factor::NO_PAYMENT(), false );
		$this->wcpay_gateway->should_use_new_process( $order );
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
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_use_saved_pm() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		// Simulate that a saved token is being used.
		$_POST['payment_method']                        = 'woocommerce_payments';
		$_POST['wc-woocommerce_payments-payment-token'] = 'new';

		$this->expect_router_factor( Factor::USE_SAVED_PM(), false );
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_save_pm() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		$_POST['wc-woocommerce_payments-new-payment-method'] = '1';

		$this->expect_router_factor( Factor::SAVE_PM(), true );
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_save_pm_for_subscription() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		WC_Subscriptions::$wcs_order_contains_subscription = '__return_true';

		$this->expect_router_factor( Factor::SAVE_PM(), true );
		$this->wcpay_gateway->should_use_new_process( $order );
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
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_subscription_signup() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		WC_Subscriptions::$wcs_order_contains_subscription = '__return_true';

		$this->expect_router_factor( Factor::SUBSCRIPTION_SIGNUP(), true );
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_subscription_signup() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		WC_Subscriptions::$wcs_order_contains_subscription = '__return_false';

		$this->expect_router_factor( Factor::SUBSCRIPTION_SIGNUP(), false );
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_positive_woopay_payment() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		$_POST['platform-checkout-intent'] = 'pi_ZYX';

		$this->expect_router_factor( Factor::WOOPAY_PAYMENT(), true );
		$this->wcpay_gateway->should_use_new_process( $order );
	}

	public function test_should_use_new_process_determines_negative_woopay_payment() {
		// The new payment process is only accessible in dev mode.
		WC_Payments::mode()->dev();

		$order = WC_Helper_Order::create_order();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST['platform-checkout-intent'] );

		$this->expect_router_factor( Factor::WOOPAY_PAYMENT(), false );
		$this->wcpay_gateway->should_use_new_process( $order );
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
		$this->wcpay_gateway->should_use_new_process( $order );
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

		$result = $this->wcpay_gateway->process_payment( $order->get_id() );
		$this->assertSame(
			[
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			],
			$result
		);
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

		$checker = function( $factors ) use ( $factor_name, $value ) {
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

	private function create_gateway_with( $payment_method ) {
		return new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$payment_method,
			[ $payment_method ],
			$this->mock_rate_limiter,
			$this->order_service,
			$this->mock_dpps,
			$this->mock_localization_service,
			$this->mock_fraud_service
		);
	}
}

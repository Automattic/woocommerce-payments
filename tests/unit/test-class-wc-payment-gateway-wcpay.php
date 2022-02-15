<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\API_Exception;
use WCPay\Constants\Payment_Type;
use WCPay\Payment_Information;

// Need to use WC_Mock_Data_Store.
require_once dirname( __FILE__ ) . '/helpers/class-wc-mock-wc-data-store.php';

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Test extends WP_UnitTestCase {

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
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

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
	 * Mock WC_Payments_Action_Scheduler_Service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_wcpay_account;

	/**
	 * Session_Rate_Limiter instance.
	 *
	 * @var Session_Rate_Limiter|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_rate_limiter;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_account_data',
					'is_server_connected',
					'get_blog_id',
					'capture_intention',
					'cancel_intention',
					'get_intent',
					'create_intention',
					'create_and_confirm_intention',
					'create_and_confirm_setup_intent',
					'get_setup_intent',
					'get_payment_method',
					'refund_charge',
					'get_charge',
					'update_intention_metadata',
				]
			)
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );
		$this->mock_api_client->expects( $this->any() )->method( 'get_blog_id' )->willReturn( 1234567 );

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );

		$this->mock_token_service = $this->createMock( WC_Payments_Token_Service::class );

		$this->mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );

		$this->mock_rate_limiter = $this->createMock( Session_Rate_Limiter::class );

		$this->wcpay_gateway = new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$this->mock_rate_limiter
		);
	}

	/**
	 * Post-test teardown
	 */
	public function tearDown() {
		parent::tearDown();

		delete_option( 'woocommerce_woocommerce_payments_settings' );
		delete_option( WC_Payments_Account::ACCOUNT_OPTION );

		// Fall back to an US store.
		update_option( 'woocommerce_store_postcode', '94110' );
		$this->wcpay_gateway->update_option( 'saved_cards', 'yes' );
	}

	public function test_attach_exchange_info_to_order_with_no_conversion() {
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->set_currency( 'USD' );
		$order->save();

		$this->mock_wcpay_account->expects( $this->once() )
			->method( 'get_account_default_currency' )
			->willReturn( 'usd' );

		$this->wcpay_gateway->attach_exchange_info_to_order( $order, $charge_id );

		// The meta key should not be set.
		$this->assertEquals( '', $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate' ) );
	}

	public function test_attach_exchange_info_to_order_with_different_account_currency_no_conversion() {
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->set_currency( 'USD' );
		$order->save();

		$this->mock_wcpay_account->expects( $this->once() )
			->method( 'get_account_default_currency' )
			->willReturn( 'jpy' );

		$this->wcpay_gateway->attach_exchange_info_to_order( $order, $charge_id );

		// The meta key should not be set.
		$this->assertEquals( '', $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate' ) );
	}

	public function test_attach_exchange_info_to_order_with_zero_decimal_order_currency() {
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->set_currency( 'JPY' );
		$order->save();

		$this->mock_wcpay_account->expects( $this->once() )
			->method( 'get_account_default_currency' )
			->willReturn( 'usd' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_charge' )
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
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->set_currency( 'EUR' );
		$order->save();

		$this->mock_wcpay_account->expects( $this->once() )
			->method( 'get_account_default_currency' )
			->willReturn( 'usd' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_charge' )
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

	public function test_process_refund() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$this->mock_api_client->expects( $this->once() )->method( 'refund_charge' )->will(
			$this->returnValue(
				[
					'id'                       => 're_123456789',
					'object'                   => 'refund',
					'amount'                   => 19.99,
					'balance_transaction'      => 'txn_987654321',
					'charge'                   => 'ch_121212121212',
					'created'                  => 1610123467,
					'payment_intent'           => 'pi_1234567890',
					'reason'                   => null,
					'reciept_number'           => null,
					'source_transfer_reversal' => null,
					'status'                   => 'succeeded',
					'transfer_reversal'        => null,
					'currency'                 => 'usd',
				]
			)
		);

		$result = $this->wcpay_gateway->process_refund( $order->get_id(), 19.99 );

		$this->assertTrue( $result );
	}

	public function test_process_refund_non_usd() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$this->mock_api_client->expects( $this->once() )->method( 'refund_charge' )->will(
			$this->returnValue(
				[
					'id'                       => 're_123456789',
					'object'                   => 'refund',
					'amount'                   => 19.99,
					'balance_transaction'      => 'txn_987654321',
					'charge'                   => 'ch_121212121212',
					'created'                  => 1610123467,
					'payment_intent'           => 'pi_1234567890',
					'reason'                   => null,
					'reciept_number'           => null,
					'source_transfer_reversal' => null,
					'status'                   => 'succeeded',
					'transfer_reversal'        => null,
					'currency'                 => 'eur',
				]
			)
		);

		$result = $this->wcpay_gateway->process_refund( $order->get_id(), 19.99 );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		$this->assertTrue( $result );
		$this->assertContains( 'successfully processed', $latest_wcpay_note->content );
		$this->assertContains( wc_price( 19.99, [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
	}

	public function test_process_refund_with_reason_non_usd() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$this->mock_api_client->expects( $this->once() )->method( 'refund_charge' )->will(
			$this->returnValue(
				[
					'id'                       => 're_123456789',
					'object'                   => 'refund',
					'amount'                   => 19.99,
					'balance_transaction'      => 'txn_987654321',
					'charge'                   => 'ch_121212121212',
					'created'                  => 1610123467,
					'payment_intent'           => 'pi_1234567890',
					'reason'                   => null,
					'reciept_number'           => null,
					'source_transfer_reversal' => null,
					'status'                   => 'succeeded',
					'transfer_reversal'        => null,
					'currency'                 => 'eur',
				]
			)
		);

		$result = $this->wcpay_gateway->process_refund( $order->get_id(), 19.99, 'some reason' );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		$this->assertContains( 'successfully processed', $latest_wcpay_note->content );
		$this->assertContains( 'some reason', $latest_wcpay_note->content );
		$this->assertContains( wc_price( 19.99, [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
		$this->assertTrue( $result );
	}

	public function test_process_refund_on_uncaptured_payment() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );
		$order->save();

		$order_id = $order->get_id();

		$result = $this->wcpay_gateway->process_refund( $order_id, 19.99 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'uncaptured-payment', $result->get_error_code() );
	}

	public function test_process_refund_on_invalid_amount() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$order_id = $order->get_id();

		$result = $this->wcpay_gateway->process_refund( $order_id, 0 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid-amount', $result->get_error_code() );

		$result = $this->wcpay_gateway->process_refund( $order_id, -5 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid-amount', $result->get_error_code() );

		$result = $this->wcpay_gateway->process_refund( $order_id, $order->get_total() + 1 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid-amount', $result->get_error_code() );
	}

	public function test_process_refund_success_does_not_set_refund_failed_meta() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$this->mock_api_client->expects( $this->once() )->method( 'refund_charge' )->will(
			$this->returnValue(
				[
					'id'                       => 're_123456789',
					'object'                   => 'refund',
					'amount'                   => 19.99,
					'balance_transaction'      => 'txn_987654321',
					'charge'                   => 'ch_121212121212',
					'created'                  => 1610123467,
					'payment_intent'           => 'pi_1234567890',
					'reason'                   => null,
					'receipt_number'           => null,
					'source_transfer_reversal' => null,
					'status'                   => 'succeeded',
					'transfer_reversal'        => null,
					'currency'                 => 'usd',
				]
			)
		);

		$this->wcpay_gateway->process_refund( $order->get_id(), 19.99 );

		// Reload the order information to get the new meta.
		$order = wc_get_order( $order->get_id() );
		$this->assertFalse( $this->wcpay_gateway->has_refund_failed( $order ) );
	}

	public function test_process_refund_failure_sets_refund_failed_meta() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_status( 'processing' );
		$order->save();

		$order_id = $order->get_id();

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'refund_charge' )
			->willThrowException( new \Exception( 'Test message' ) );

		$this->wcpay_gateway->process_refund( $order_id, 19.99 );

		// Reload the order information to get the new meta.
		$order = wc_get_order( $order_id );
		$this->assertTrue( $this->wcpay_gateway->has_refund_failed( $order ) );
	}

	public function test_process_refund_on_api_error() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_status( 'processing' );
		$order->save();

		$order_id = $order->get_id();

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'refund_charge' )
			->willThrowException( new \Exception( 'Test message' ) );

		$result = $this->wcpay_gateway->process_refund( $order_id, 19.99 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'wcpay_edit_order_refund_failure', $result->get_error_code() );
		$this->assertEquals( 'Test message', $result->get_error_message() );
	}

	public function test_process_refund_on_api_error_non_usd() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		WC_Payments_Utils::set_order_intent_currency( $order, 'EUR' );
		$order->update_status( 'processing' );
		$order->save();

		$order_id = $order->get_id();

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'refund_charge' )
			->willThrowException( new API_Exception( 'Test message', 'server_error', 500 ) );

		$result = $this->wcpay_gateway->process_refund( $order_id, 19.99 );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'wcpay_edit_order_refund_failure', $result->get_error_code() );
		$this->assertEquals( 'Test message', $result->get_error_message() );
		$this->assertContains( 'failed to complete', $latest_wcpay_note->content );
		$this->assertContains( 'Test message', $latest_wcpay_note->content );
		$this->assertContains( wc_price( 19.99, [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
	}

	public function test_payment_fields_outputs_fields() {
		$this->wcpay_gateway->payment_fields();

		$this->expectOutputRegex( '/<div id="wcpay-card-element"><\/div>/' );
	}

	public function test_save_card_checkbox_not_displayed_when_saved_cards_disabled() {
		$this->wcpay_gateway->update_option( 'saved_cards', 'no' );

		// Use a callback to get and test the output (also suppresses the output buffering being printed to the CLI).
		$this->setOutputCallback(
			function( $output ) {
				$result = preg_match_all( '/.*<input.*id="wc-woocommerce_payments-new-payment-method".*\/>.*/', $output );

				$this->assertEquals( 0, $result );
			}
		);

		$this->wcpay_gateway->payment_fields();
	}

	protected function mock_level_3_order( $shipping_postcode, $with_fee = false, $quantity = 1 ) {
		// Setup the item.
		$mock_item = $this->getMockBuilder( WC_Order_Item_Product::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_name', 'get_quantity', 'get_subtotal', 'get_total_tax', 'get_total', 'get_variation_id', 'get_product_id' ] )
			->getMock();

		$mock_item
			->method( 'get_name' )
			->will( $this->returnValue( 'Beanie with Logo' ) );

		$mock_item
			->method( 'get_quantity' )
			->will( $this->returnValue( $quantity ) );

		$mock_item
			->method( 'get_total' )
			->will( $this->returnValue( 18 ) );

		$mock_item
			->method( 'get_subtotal' )
			->will( $this->returnValue( 18 ) );

		$mock_item
			->method( 'get_total_tax' )
			->will( $this->returnValue( 2.7 ) );

		$mock_item
			->method( 'get_variation_id' )
			->will( $this->returnValue( false ) );

		$mock_item
			->method( 'get_product_id' )
			->will( $this->returnValue( 30 ) );

		$mock_items[] = $mock_item;

		if ( $with_fee ) {
			// Setup the fee.
			$mock_fee = $this->getMockBuilder( WC_Order_Item_Fee::class )
				->disableOriginalConstructor()
				->setMethods( [ 'get_name', 'get_quantity', 'get_total_tax', 'get_total' ] )
				->getMock();

			$mock_fee
				->method( 'get_name' )
				->will( $this->returnValue( 'fee' ) );

			$mock_fee
				->method( 'get_quantity' )
				->will( $this->returnValue( 1 ) );

			$mock_fee
				->method( 'get_total' )
				->will( $this->returnValue( 10 ) );

			$mock_fee
				->method( 'get_total_tax' )
				->will( $this->returnValue( 1.5 ) );

			$mock_items[] = $mock_fee;
		}

		// Setup the order.
		$mock_order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_id', 'get_items', 'get_currency', 'get_shipping_total', 'get_shipping_tax', 'get_shipping_postcode' ] )
			->getMock();

		$mock_order
			->method( 'get_id' )
			->will( $this->returnValue( 210 ) );

		$mock_order
			->method( 'get_items' )
			->will( $this->returnValue( $mock_items ) );

		$mock_order
			->method( 'get_currency' )
			->will( $this->returnValue( 'USD' ) );

		$mock_order
			->method( 'get_shipping_total' )
			->will( $this->returnValue( 30 ) );

		$mock_order
			->method( 'get_shipping_tax' )
			->will( $this->returnValue( 8 ) );

		$mock_order
			->method( 'get_shipping_postcode' )
			->will( $this->returnValue( $shipping_postcode ) );

		return $mock_order;
	}

	public function test_full_level3_data() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		update_option( 'woocommerce_store_postcode', '94110' );

		$this->mock_wcpay_account->method( 'get_account_country' )->willReturn( 'US' );
		$mock_order   = $this->mock_level_3_order( '98012' );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_full_level3_data_with_fee() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
				(object) [
					'product_code'        => 'fee',
					'product_description' => 'fee',
					'unit_cost'           => 1000,
					'quantity'            => 1,
					'tax_amount'          => 150,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		update_option( 'woocommerce_store_postcode', '94110' );

		$this->mock_wcpay_account->method( 'get_account_country' )->willReturn( 'US' );
		$mock_order   = $this->mock_level_3_order( '98012', true );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_us_store_level_3_data() {
		// Use a non-us customer postcode to ensure it's not included in the level3 data.
		$this->mock_wcpay_account->method( 'get_account_country' )->willReturn( 'US' );
		$mock_order   = $this->mock_level_3_order( '9000' );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertArrayNotHasKey( 'shipping_address_zip', $level_3_data );
	}

	public function test_us_customer_level_3_data() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
		];

		// Use a non-US postcode.
		update_option( 'woocommerce_store_postcode', '9000' );

		$this->mock_wcpay_account->method( 'get_account_country' )->willReturn( 'US' );
		$mock_order   = $this->mock_level_3_order( '98012' );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_non_us_customer_level_3_data() {
		$expected_data = [];

		$this->mock_wcpay_account->method( 'get_account_country' )->willReturn( 'CA' );
		$mock_order   = $this->mock_level_3_order( 'K0A' );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_full_level3_data_with_float_quantity() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 450,
					'quantity'            => 4,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		update_option( 'woocommerce_store_postcode', '94110' );

		$this->mock_wcpay_account->method( 'get_account_country' )->willReturn( 'US' );
		$mock_order   = $this->mock_level_3_order( '98012', false, 3.7 );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_full_level3_data_with_float_quantity_zero() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		update_option( 'woocommerce_store_postcode', '94110' );

		$this->mock_wcpay_account->method( 'get_account_country' )->willReturn( 'US' );
		$mock_order   = $this->mock_level_3_order( '98012', false, 0.4 );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_capture_charge_success() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );

		$mock_intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			$order->get_currency(),
			'cus_12345',
			'pm_12345',
			new DateTime(),
			'requires_capture',
			$charge_id,
			'...'
		);

		$this->mock_api_client->expects( $this->once() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'update_intention_metadata' )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					$order->get_currency(),
					'cus_12345',
					'pm_12345',
					new DateTime(),
					'succeeded',
					$charge_id,
					'...'
				)
			)
		);

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_country' )
			->willReturn( 'US' );

		$result = $this->wcpay_gateway->capture_charge( $order );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 2,
			]
		);
		$latest_wcpay_note = $notes[1]; // The latest note is the "status changed" message, we want the previous one.

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => 'succeeded',
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 200,
			],
			$result
		);
		$this->assertContains( 'successfully captured', $latest_wcpay_note->content );
		$this->assertContains( wc_price( $order->get_total() ), $latest_wcpay_note->content );
		$this->assertEquals( $order->get_meta( '_intention_status', true ), 'succeeded' );
		$this->assertEquals( $order->get_status(), 'processing' );
	}

	public function test_capture_charge_success_non_usd() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );

		$mock_intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			'eur',
			'cus_12345',
			'pm_12345',
			new DateTime(),
			'requires_capture',
			$charge_id,
			'...'
		);

		$this->mock_api_client->expects( $this->once() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'update_intention_metadata' )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					'eur',
					'cus_12345',
					'pm_12345',
					new DateTime(),
					'succeeded',
					$charge_id,
					'...'
				)
			)
		);

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_country' )
			->willReturn( 'US' );

		$result = $this->wcpay_gateway->capture_charge( $order );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 2,
			]
		);
		$latest_wcpay_note = $notes[1]; // The latest note is the "status changed" message, we want the previous one.

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertEquals(
			[
				'status'    => 'succeeded',
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 200,
			],
			$result
		);
		$this->assertContains( 'successfully captured', $latest_wcpay_note->content );
		$this->assertContains( wc_price( $order->get_total(), [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
		$this->assertEquals( $order->get_meta( '_intention_status', true ), 'succeeded' );
		$this->assertEquals( $order->get_status(), 'processing' );
	}

	public function test_capture_charge_failure() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );

		$mock_intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			$order->get_currency(),
			'cus_12345',
			'pm_12345',
			new DateTime(),
			'requires_capture',
			$charge_id,
			'...'
		);
		$this->mock_api_client->expects( $this->once() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'update_intention_metadata' )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					$order->get_currency(),
					'cus_12345',
					'pm_12345',
					new DateTime(),
					'requires_capture',
					$charge_id,
					'...'
				)
			)
		);

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_country' )
			->willReturn( 'US' );

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
				'status'    => 'requires_capture',
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 502,
			],
			$result
		);
		$this->assertContains( 'failed', $note->content );
		$this->assertContains( wc_price( $order->get_total() ), $note->content );
		$this->assertEquals( $order->get_meta( '_intention_status', true ), 'requires_capture' );
		$this->assertEquals( $order->get_status(), 'on-hold' );
	}

	public function test_capture_charge_failure_non_usd() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );

		$mock_intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			'eur',
			'cus_12345',
			'pm_12345',
			new DateTime(),
			'requires_capture',
			$charge_id,
			'...'
		);
		$this->mock_api_client->expects( $this->once() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'update_intention_metadata' )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					'eur',
					'cus_12345',
					'pm_12345',
					new DateTime(),
					'requires_capture',
					$charge_id,
					'...'
				)
			)
		);

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_country' )
			->willReturn( 'US' );

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
				'status'    => 'requires_capture',
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 502,
			],
			$result
		);
		$this->assertContains( 'failed', $note->content );
		$this->assertContains( wc_price( $order->get_total(), [ 'currency' => 'EUR' ] ), $note->content );
		$this->assertEquals( $order->get_meta( '_intention_status', true ), 'requires_capture' );
		$this->assertEquals( $order->get_status(), 'on-hold' );
	}

	public function test_capture_charge_api_failure() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );

		$mock_intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			'usd',
			'cus_12345',
			'pm_12345',
			new DateTime(),
			'requires_capture',
			$charge_id,
			'...'
		);

		$this->mock_api_client->expects( $this->atLeastOnce() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'update_intention_metadata' )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->throwException( new API_Exception( 'test exception', 'server_error', 500 ) )
		);

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_country' )
			->willReturn( 'US' );

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
		$this->assertContains( 'failed', $note->content );
		$this->assertContains( 'test exception', $note->content );
		$this->assertContains( wc_price( $order->get_total() ), $note->content );
		$this->assertEquals( $order->get_meta( '_intention_status', true ), 'requires_capture' );
		$this->assertEquals( $order->get_status(), 'on-hold' );
	}

	public function test_capture_charge_api_failure_non_usd() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );
		WC_Payments_Utils::set_order_intent_currency( $order, 'EUR' );

		$mock_intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			'jpy',
			'cus_12345',
			'pm_12345',
			new DateTime(),
			'requires_capture',
			$charge_id,
			'...'
		);

		$this->mock_api_client->expects( $this->atLeastOnce() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'update_intention_metadata' )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->throwException( new API_Exception( 'test exception', 'server_error', 500 ) )
		);

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_country' )
			->willReturn( 'US' );

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
		$this->assertContains( 'failed', $note->content );
		$this->assertContains( 'test exception', $note->content );
		$this->assertContains( wc_price( $order->get_total(), [ 'currency' => 'EUR' ] ), $note->content );
		$this->assertEquals( $order->get_meta( '_intention_status', true ), 'requires_capture' );
		$this->assertEquals( $order->get_status(), 'on-hold' );
	}

	public function test_capture_charge_expired() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );

		$mock_intent = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			'usd',
			'cus_12345',
			'pm_12345',
			new DateTime(),
			'canceled',
			$charge_id,
			'...'
		);

		$this->mock_api_client->expects( $this->atLeastOnce() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'update_intention_metadata' )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->throwException( new API_Exception( 'test exception', 'server_error', 500 ) )
		);

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_country' )
			->willReturn( 'US' );

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
		$this->assertContains( 'expired', $note->content );
		$this->assertEquals( $order->get_meta( '_intention_status', true ), 'canceled' );
		$this->assertEquals( $order->get_status(), 'cancelled' );
	}

	public function test_capture_charge_metadata() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );

		$mock_intent     = new WC_Payments_API_Intention(
			$intent_id,
			1500,
			$order->get_currency(),
			'cus_12345',
			'pm_12345',
			new DateTime(),
			'requires_capture',
			$charge_id,
			'...',
			[],
			[],
			[],
			[
				'customer_name' => 'Test',
			]
		);
		$merged_metadata = [
			'customer_name'  => 'Test',
			'customer_email' => $order->get_billing_email(),
			'site_url'       => esc_url( get_site_url() ),
			'order_id'       => $order->get_id(),
			'order_key'      => $order->get_order_key(),
			'payment_type'   => Payment_Type::SINGLE(),
		];

		$this->mock_api_client->expects( $this->once() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'update_intention_metadata' )->with( $intent_id, $merged_metadata )->will(
			$this->returnValue( $mock_intent )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					$order->get_currency(),
					'cus_12345',
					'pm_12345',
					new DateTime(),
					'succeeded',
					$charge_id,
					'...'
				)
			)
		);

		$this->mock_wcpay_account
			->expects( $this->once() )
			->method( 'get_account_country' )
			->willReturn( 'US' );

		$result = $this->wcpay_gateway->capture_charge( $order );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 2,
			]
		);
		$latest_wcpay_note = $notes[1]; // The latest note is the "status changed" message, we want the previous one.

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertSame(
			[
				'status'    => 'succeeded',
				'id'        => $intent_id,
				'message'   => null,
				'http_code' => 200,
			],
			$result
		);
		$this->assertContains( 'successfully captured', $latest_wcpay_note->content );
		$this->assertContains( wc_price( $order->get_total() ), $latest_wcpay_note->content );
		$this->assertSame( $order->get_meta( '_intention_status', true ), 'succeeded' );
		$this->assertSame( $order->get_status(), 'processing' );
	}

	public function test_cancel_authorization_handles_api_exception_when_canceling() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'cancel_intention' )
			->will( $this->throwException( new API_Exception( 'test exception', 'test', 123 ) ) );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->willReturn(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					'usd',
					'cus_12345',
					'pm_12345',
					new DateTime(),
					'canceled',
					$charge_id,
					'...'
				)
			);

		$this->wcpay_gateway->cancel_authorization( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		$this->assertContains( 'cancelled', $note->content );
		$this->assertEquals( $order->get_status(), 'cancelled' );
	}

	public function test_cancel_authorization_handles_all_api_exceptions() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( $intent_id );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_intention_status', 'requires_capture' );
		$order->update_status( 'on-hold' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'cancel_intention' )
			->will( $this->throwException( new API_Exception( 'test exception', 'test', 123 ) ) );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->will( $this->throwException( new API_Exception( 'ignore this', 'test', 123 ) ) );

		$this->wcpay_gateway->cancel_authorization( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		$this->assertContains( 'failed', $note->content );
		$this->assertContains( 'test exception', $note->content );
		$this->assertEquals( $order->get_status(), 'on-hold' );
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

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_setup_intent' )
			->with( 'pm_mock', 'cus_12345' )
			->willReturn( [ 'id' => 'pm_mock' ] );

		$result = $this->wcpay_gateway->create_and_confirm_setup_intent();

		$this->assertEquals( 'pm_mock', $result['id'] );
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

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_setup_intent' )
			->with( 'pm_mock', 'cus_12345' )
			->willReturn( [ 'id' => 'pm_mock' ] );

		$result = $this->wcpay_gateway->create_and_confirm_setup_intent();

		$this->assertEquals( 'pm_mock', $result['id'] );
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

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_setup_intent' )
			->with( 'sti_mock' )
			->willReturn(
				[
					'status'         => 'succeeded',
					'payment_method' => 'pm_mock',
				]
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

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_setup_intent' );

		$this->mock_token_service
			->expects( $this->never() )
			->method( 'add_payment_method_to_user' );

		$result = $this->wcpay_gateway->add_payment_method();

		$this->assertEquals( 'error', $result['result'] );
	}

	public function test_add_payment_method_canceled_intent() {
		$_POST = [ 'wcpay-setup-intent' => 'sti_mock' ];

		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->will( $this->returnValue( 'cus_12345' ) );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_setup_intent' )
			->with( 'sti_mock' )
			->willReturn( [ 'status' => 'canceled' ] );

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

		$this->mock_wcpay_account
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

		$this->mock_wcpay_account
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
		$order->delete_meta_data( '_new_order_tracking_complete' );

		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' );

		$this->mock_wcpay_account
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

		$this->mock_action_scheduler_service
			->expects( $this->once() )
			->method( 'schedule_job' );

		$this->mock_wcpay_account
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

		$this->wcpay_gateway = new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service
		);

		$this->assertEquals(
			[
				'product',
				'cart',
				'checkout',
			],
			$this->wcpay_gateway->get_option( 'payment_request_button_locations' )
		);
		$this->assertEquals(
			'default',
			$this->wcpay_gateway->get_option( 'payment_request_button_size' )
		);

		$form_fields = $this->wcpay_gateway->get_form_fields();

		$this->assertEquals( [ 'default', 'buy', 'donate', 'book' ], array_keys( $form_fields['payment_request_button_type']['options'] ) );
		$this->assertEquals( [ 'dark', 'light', 'light-outline' ], array_keys( $form_fields['payment_request_button_theme']['options'] ) );
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

	public function test_process_payment_for_order_rejects_with_cached_minimum_amount() {
		set_transient( 'wcpay_minimum_amount_usd', '50', DAY_IN_SECONDS );

		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( 0.45 );
		$order->save();

		$pi = new Payment_Information( 'pm_test', $order );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The selected payment method requires a total amount of at least $0.50.' );
		$this->wcpay_gateway->process_payment_for_order( WC()->cart, $pi );
	}

	public function test_process_payment_caches_mimimum_amount_and_displays_error_upon_exception() {
		delete_transient( 'wcpay_minimum_amount_usd' );

		$order = WC_Helper_Order::create_order();
		$order->set_total( 0.45 );
		$order->save();

		$_POST = [ 'wcpay-payment-method' => 'pm_mock' ];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_and_confirm_intention' )
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

	public function test_attach_intent_info_to_order() {
		$order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->setMethods( [ 'update_meta_data', 'save' ] )
			->getMock();

		$intent_id      = 'pi_xxxxxxxxxxxxx';
		$charge_id      = 'ch_yyyyyyyyyyyyy';
		$customer_id    = 'cus_12345';
		$payment_method = 'woocommerce_payments';
		$intent_status  = 'succeeded';
		$currency       = 'USD';

		$order->expects( $this->atLeast( 2 ) )->method( 'update_meta_data' )->withConsecutive(
			[ '_intent_id', $intent_id ],
			[ '_charge_id', $charge_id ]
		);

		$this->wcpay_gateway->attach_intent_info_to_order( $order, $intent_id, $intent_status, $payment_method, $customer_id, $charge_id, $currency );
	}

	public function test_update_order_status_from_intent_success_payment_complete() {
		$order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->setMethods( [ 'update_meta_data', 'save', 'payment_complete', 'get_data_store' ] )
			->getMock();

		$order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		$intent_id     = 'pi_xxxxxxxxxxxxx';
		$charge_id     = 'ch_yyyyyyyyyyyyy';
		$intent_status = 'succeeded';
		$currency      = 'USD';

		$order->expects( $this->once() )
			->method( 'payment_complete' )
			->with( $intent_id );

		$this->wcpay_gateway->update_order_status_from_intent( $order, $intent_id, $intent_status, $charge_id, $currency );
	}

	public function test_update_order_status_from_intent_fails_payment_complete() {
		// test if metadata needed for refunds is being saved despite the payment_complete method.
		$order = $this->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->setMethods( [ 'update_meta_data', 'save', 'payment_complete', 'get_data_store' ] )
			->getMock();

		$order
			->method( 'get_data_store' )
			->willReturn( new \WC_Mock_WC_Data_Store() );

		$intent_id     = 'pi_xxxxxxxxxxxxx';
		$charge_id     = 'ch_yyyyyyyyyyyyy';
		$intent_status = 'succeeded';
		$currency      = 'USD';

		$order->expects( $this->once() )
			->method( 'payment_complete' )
			->willThrowException( new Exception( 'something went wrong' ) );

		$this->wcpay_gateway->update_order_status_from_intent( $order, $intent_id, $intent_status, $charge_id, $currency );
	}

	public function test_create_intent_success() {
		$intent_id       = 'pi_xxxxxxxxxxxxx';
		$charge_id       = 'ch_yyyyyyyyyyyyy';
		$payment_methods = [ 'card_present' ];
		$capture_method  = 'manual';

		$order = WC_Helper_Order::create_order();
		$order->update_status( 'on-hold' );

		$this->mock_api_client->expects( $this->once() )->method( 'create_intention' )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					$order->get_currency(),
					'cus_12345',
					'pm_12345',
					new DateTime(),
					'requires_payment_method',
					$charge_id,
					'...'
				)
			)
		);

		$result = $this->wcpay_gateway->create_intent( $order, $payment_methods, $capture_method );

		// Assert the returned data contains fields required by the REST endpoint.
		$this->assertSame(
			[
				'id' => $intent_id,
			],
			$result
		);
	}

	public function test_create_intent_api_failure() {
		$payment_methods = [ 'card_present' ];
		$capture_method  = 'manual';

		$order = WC_Helper_Order::create_order();
		$order->update_status( 'on-hold' );

		$this->mock_api_client->expects( $this->once() )->method( 'create_intention' )->will(
			$this->throwException( new API_Exception( 'test exception', 'server_error', 500 ) )
		);

		$result = $this->wcpay_gateway->create_intent( $order, $payment_methods, $capture_method );

		$this->assertInstanceOf( 'WP_Error', $result );
		$data = $result->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
		$this->assertSame( 'Intent creation failed with the following message: test exception', $result->get_error_message() );
	}

	public function test_is_platform_checkout_is_returned_as_true() {
		update_option( '_wcpay_feature_platform_checkout', '1' );
		$this->wcpay_gateway->update_option( 'platform_checkout', 'yes' );
		$this->assertTrue( $this->wcpay_gateway->get_payment_fields_js_config()['isPlatformCheckoutEnabled'] );
	}

	/**
	 * @dataProvider is_platform_checkout_falsy_value_provider
	 */
	public function test_is_platform_checkout_is_returned_as_false_if_feature_flag_is_not_equal_1() {
		update_option( '_wcpay_feature_platform_checkout', '0' );
		$this->assertFalse( $this->wcpay_gateway->get_payment_fields_js_config()['isPlatformCheckoutEnabled'] );
	}

	public function test_is_platform_checkout_is_returned_as_option_is_not_equal_1() {
		$this->wcpay_gateway->update_option( 'platform_checkout', 'yes' );
		$this->assertFalse( $this->wcpay_gateway->get_payment_fields_js_config()['isPlatformCheckoutEnabled'] );
	}

	public function test_is_platform_checkout_is_returned_as_false_if_missing() {
		delete_option( '_wcpay_feature_platform_checkout' );
		$this->assertFalse( $this->wcpay_gateway->get_payment_fields_js_config()['isPlatformCheckoutEnabled'] );
	}

	public function is_platform_checkout_falsy_value_provider() {
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
}

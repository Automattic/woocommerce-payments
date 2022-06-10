<?php
/**
 * Class WC_Payment_Gateway_WCPay_Process_Refund_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;
use WCPay\Session_Rate_Limiter;

// Need to use WC_Mock_Data_Store.
require_once dirname( __FILE__ ) . '/helpers/class-wc-mock-wc-data-store.php';

/**
 * WC_Payment_Gateway_WCPay::process_refund unit tests.
 */
class WC_Payment_Gateway_WCPay_Process_Refund_Test extends WP_UnitTestCase {
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
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_wcpay_account;

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
	 * Mock Session_Rate_Limiter.
	 *
	 * @var Session_Rate_Limiter|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_rate_limiter;

	/**
	 * Mock WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_order_service;

	public function set_up() {
		parent::set_up();

		$this->mock_api_client               = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_wcpay_account            = $this->createMock( WC_Payments_Account::class );
		$this->mock_customer_service         = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_token_service            = $this->createMock( WC_Payments_Token_Service::class );
		$this->mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );
		$this->mock_rate_limiter             = $this->createMock( Session_Rate_Limiter::class );
		$this->mock_order_service            = $this->createMock( WC_Payments_Order_Service::class );

		$this->wcpay_gateway = new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service,
			$this->mock_action_scheduler_service,
			$this->mock_rate_limiter,
			$this->mock_order_service
		);
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
					'receipt_number'           => null,
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

	public function test_process_refund_should_work_without_payment_method_id_meta() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_charge_id', 'ch_yyyyyyyyy' );
		$order->save();

		// Arrange: Mock Stripe's call with an empty payment method ID.
		$this->mock_api_client->method( 'get_payment_method' )->with( '' )->willThrowException( new Exception( 'Missing required parameter: type.' ) );

		$this->mock_api_client->expects( $this->once() )->method( 'refund_charge' )->will(
			$this->returnValue(
				[
					'id'       => 're_123456789',
					'amount'   => 5000,
					'currency' => 'usd',
				]
			)
		);

		$result = $this->wcpay_gateway->process_refund( $order->get_id(), $order->get_total() );

		$this->assertTrue( $result );
	}

	/**
	 * Test saving WCPay refund id to WC Refund meta and WC Order Note.
	 */
	public function test_process_refund_save_wcpay_refund_id_to_refund_meta_and_order_note() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		$refund = wc_create_refund( [ 'order_id' => $order->get_id() ] );

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

		$result = $this->wcpay_gateway->process_refund( $order->get_id(), 19.99 );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		$this->assertTrue( $result );
		$this->assertEquals( 're_123456789', $refund->get_meta( '_wcpay_refund_id', true ) );
		$this->assertStringContainsString( 'successfully processed', $latest_wcpay_note->content );
		$this->assertStringContainsString( wc_price( 19.99, [ 'currency' => 'USD' ] ), $latest_wcpay_note->content );
		$this->assertStringContainsString( 're_123456789', $latest_wcpay_note->content );
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
					'receipt_number'           => null,
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
		$this->assertStringContainsString( 'successfully processed', $latest_wcpay_note->content );
		$this->assertStringContainsString( wc_price( 19.99, [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
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
					'receipt_number'           => null,
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

		$this->assertStringContainsString( 'successfully processed', $latest_wcpay_note->content );
		$this->assertStringContainsString( 'some reason', $latest_wcpay_note->content );
		$this->assertStringContainsString( wc_price( 19.99, [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
		$this->assertTrue( $result );
	}

	public function test_process_refund_interac_present() {
		$intent_id         = 'pi_xxxxxxxxxxxxx';
		$charge_id         = 'ch_yyyyyyyyyyyyy';
		$payment_method_id = 'pm_zzzzzzzzzzzzz';

		$order = WC_Helper_Order::create_order( null, 30 );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_payment_method_id', $payment_method_id );
		$order->update_meta_data( WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, 'EUR' );
		$order->save();

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( $payment_method_id )
			->willReturn(
				[
					'id'     => $payment_method_id,
					'object' => 'payment_method',
					'type'   => 'interac_present',
				]
			);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'list_refunds' )
			->with( $charge_id )
			->willReturn(
				[
					'data' => [
						[
							'id'                       => 're_123456789',
							'object'                   => 'refund',
							'amount'                   => 1999,
							'balance_transaction'      => 'txn_987654321',
							'charge'                   => 'ch_121212121212',
							'created'                  => 1610123467,
							'payment_intent'           => 'pi_1234567890',
							'reason'                   => null,
							'receipt_number'           => null,
							'source_transfer_reversal' => null,
							'status'                   => 'succeeded',
							'transfer_reversal'        => null,
							'currency'                 => 'eur',
						],
					],
				]
			);

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'refund_charge' );

		$result = $this->wcpay_gateway->process_refund( $order->get_id(), 19.99 );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'successfully processed', $latest_wcpay_note->content );
		$this->assertStringContainsString( wc_price( 19.99, [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
	}

	public function test_process_refund_interac_present_without_payment_method_id_meta() {
		$intent_id = 'pi_xxxxxxxxxxxxx';
		$charge_id = 'ch_yyyyyyyyyyyyy';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->save();

		// Arrange: Mock Stripe's call with an empty payment method ID.
		$this->mock_api_client->method( 'get_payment_method' )->with( '' )->willThrowException( new Exception( 'Missing required parameter: type.' ) );

		$this->mock_api_client
			->method( 'get_intent' )
			->with( $intent_id )
			->willReturn(
				new WC_Payments_API_Intention(
					$intent_id,
					5000,
					'usd',
					1,
					'pm_zzzzzzzz',
					new DateTime(),
					'succeeded',
					$charge_id,
					'client_secret',
					[],
					[],
					[ 'type' => 'interac_present' ]
				)
			);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'list_refunds' )
			->with( $charge_id )
			->willReturn(
				[
					'data' => [
						[
							'id'                       => 're_123456789',
							'object'                   => 'refund',
							'amount'                   => 5000,
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
						],
					],
				]
			);

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'refund_charge' );

		$result = $this->wcpay_gateway->process_refund( $order->get_id(), $order->get_total() );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		);
		$latest_wcpay_note = $notes[0];

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'successfully processed', $latest_wcpay_note->content );
		$this->assertStringContainsString( wc_price( 50, [ 'currency' => 'USD' ] ), $latest_wcpay_note->content );
	}

	public function test_process_refund_interac_present_without_app_refund() {
		$intent_id         = 'pi_xxxxxxxxxxxxx';
		$charge_id         = 'ch_yyyyyyyyyyyyy';
		$payment_method_id = 'pm_zzzzzzzzzzzzz';

		$order = WC_Helper_Order::create_order( null, 30 );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_payment_method_id', $payment_method_id );
		$order->update_meta_data( WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, 'EUR' );
		$order->save();

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( $payment_method_id )
			->willReturn(
				[
					'id'     => $payment_method_id,
					'object' => 'payment_method',
					'type'   => 'interac_present',
				]
			);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'list_refunds' )
			->with( $charge_id )
			->willReturn(
				[
					'data' => [],
				]
			);

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'refund_charge' );

		$result = $this->wcpay_gateway->process_refund( $order->get_id(), 19.99 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wcpay_edit_order_refund_not_possible', $result->get_error_code() );
	}

	public function test_process_refund_interac_present_with_unsuccessful_app_refund() {
		$intent_id         = 'pi_xxxxxxxxxxxxx';
		$charge_id         = 'ch_yyyyyyyyyyyyy';
		$payment_method_id = 'pm_zzzzzzzzzzzzz';

		$order = WC_Helper_Order::create_order( null, 30 );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_payment_method_id', $payment_method_id );
		$order->update_meta_data( WC_Payments_Utils::ORDER_INTENT_CURRENCY_META_KEY, 'EUR' );
		$order->save();

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( $payment_method_id )
			->willReturn(
				[
					'id'     => $payment_method_id,
					'object' => 'payment_method',
					'type'   => 'interac_present',
				]
			);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'list_refunds' )
			->with( $charge_id )
			->willReturn(
				[
					'data' => [
						[
							'id'                       => 're_123456789',
							'object'                   => 'refund',
							'amount'                   => 1999,
							'balance_transaction'      => 'txn_987654321',
							'charge'                   => 'ch_121212121212',
							'created'                  => 1610123467,
							'payment_intent'           => 'pi_1234567890',
							'reason'                   => null,
							'receipt_number'           => null,
							'source_transfer_reversal' => null,
							'status'                   => 'failed',
							'transfer_reversal'        => null,
							'currency'                 => 'eur',
						],
					],
				]
			);

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'refund_charge' );

		$result = $this->wcpay_gateway->process_refund( $order->get_id(), 19.99 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wcpay_edit_order_refund_not_possible', $result->get_error_code() );
	}

	public function test_process_refund_card_present() {
		$intent_id         = 'pi_xxxxxxxxxxxxx';
		$charge_id         = 'ch_yyyyyyyyyyyyy';
		$payment_method_id = 'pm_zzzzzzzzzzzzz';

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->update_meta_data( '_charge_id', $charge_id );
		$order->update_meta_data( '_payment_method_id', $payment_method_id );
		$order->save();

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payment_method' )
			->with( $payment_method_id )
			->willReturn(
				[
					'id'     => $payment_method_id,
					'object' => 'payment_method',
					'type'   => 'card_present',
				]
			);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'refund_charge' )
			->willReturn(
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
					'currency'                 => 'eur',
				]
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
		$this->assertStringContainsString( 'successfully processed', $latest_wcpay_note->content );
		$this->assertStringContainsString( wc_price( 19.99, [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
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

		$result = $this->wcpay_gateway->process_refund( $order_id, - 5 );

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
		$this->assertStringContainsString( 'failed to complete', $latest_wcpay_note->content );
		$this->assertStringContainsString( 'Test message', $latest_wcpay_note->content );
		$this->assertStringContainsString( wc_price( 19.99, [ 'currency' => 'EUR' ] ), $latest_wcpay_note->content );
	}
}

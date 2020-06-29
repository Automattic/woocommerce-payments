<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

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
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $wcpay_account;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_account_data', 'is_server_connected', 'capture_intention', 'get_intent' ] )
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );

		/** @var WC_Payments_Customer_Service|MockObject $mock_customer_service */
		$mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );

		/** @var WC_Payments_Token_Service|MockObject $mock_token_service */
		$mock_token_service = $this->createMock( WC_Payments_Token_Service::class );

		$this->wcpay_gateway = new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->wcpay_account,
			$mock_customer_service,
			$mock_token_service,
		);
	}

	/**
	 * Post-test teardown
	 */
	public function tearDown() {
		delete_option( 'woocommerce_woocommerce_payments_settings' );
		delete_transient( WC_Payments_Account::ACCOUNT_TRANSIENT );

		// Fall back to an US store.
		update_option( 'woocommerce_store_postcode', '94110' );
	}

	public function test_payment_fields_outputs_fields() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->returnValue(
				[
					'account_id'               => 'acc_test',
					'live_publishable_key'     => 'pk_live_',
					'test_publishable_key'     => 'pk_test_',
					'has_pending_requirements' => false,
					'is_live'                  => true,
				]
			)
		);

		$this->wcpay_gateway->payment_fields();

		$this->expectOutputRegex( '/<div id="wcpay-card-element"><\/div>/' );
	}

	public function test_payment_fields_outputs_error() {
		$this->mock_api_client->expects( $this->once() )->method( 'get_account_data' )->will(
			$this->throwException( new WC_Payments_API_Exception( 'test', 'test', 123 ) )
		);

		$this->wcpay_gateway->payment_fields();

		$this->expectOutputRegex( '/An error was encountered when preparing the payment form\. Please try again later\./' );
	}

	protected function mock_level_3_order( $shipping_postcode ) {
		// Setup the item.
		$mock_item = $this->getMockBuilder( WC_Order_Item::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_name', 'get_quantity', 'get_subtotal', 'get_total_tax', 'get_total', 'get_variation_id', 'get_product_id' ] )
			->getMock();

		$mock_item
			->method( 'get_name' )
			->will( $this->returnValue( 'Beanie with Logo' ) );

		$mock_item
			->method( 'get_quantity' )
			->will( $this->returnValue( 1 ) );

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
			->will( $this->returnValue( [ $mock_item ] ) );

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

		$mock_order   = $this->mock_level_3_order( '98012' );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertEquals( $level_3_data, $expected_data );
	}

	public function test_us_store_level_3_data() {
		// Use a non-us customer postcode to ensure it's not included in the level3 data.
		$mock_order   = $this->mock_level_3_order( '9000' );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertArrayNotHasKey( 'shipping_address_zip', $level_3_data );
	}

	public function test_us_customer_level_3_data() {
		$expected_data = [
			'merchant_reference'   => '210',
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

		$mock_order   = $this->mock_level_3_order( '98012' );
		$level_3_data = $this->wcpay_gateway->get_level3_data_from_order( $mock_order );

		$this->assertEquals( $level_3_data, $expected_data );
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

		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					new DateTime(),
					'succeeded',
					$charge_id,
					'...'
				)
			)
		);

		$this->wcpay_gateway->capture_charge( $order );

		$notes             = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 2,
			]
		);
		$latest_wcpay_note = $notes[1]; // The latest note is the "status changed" message, we want the previous one.

		$this->assertContains( 'successfully captured', $latest_wcpay_note->content );
		$this->assertContains( wc_price( $order->get_total() ), $latest_wcpay_note->content );
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

		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					new DateTime(),
					'requires_capture',
					$charge_id,
					'...'
				)
			)
		);

		$this->wcpay_gateway->capture_charge( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		$this->assertContains( 'failed', $note->content );
		$this->assertContains( wc_price( $order->get_total() ), $note->content );
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

		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->throwException( new WC_Payments_API_Exception( 'test', 'server_error', 500 ) )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					new DateTime(),
					'requires_capture',
					$charge_id,
					'...'
				)
			)
		);

		$this->wcpay_gateway->capture_charge( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		$this->assertContains( 'failed', $note->content );
		$this->assertContains( wc_price( $order->get_total() ), $note->content );
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

		$this->mock_api_client->expects( $this->once() )->method( 'capture_intention' )->will(
			$this->throwException( new WC_Payments_API_Exception( 'test', 'server_error', 500 ) )
		);
		$this->mock_api_client->expects( $this->once() )->method( 'get_intent' )->with( $intent_id )->will(
			$this->returnValue(
				new WC_Payments_API_Intention(
					$intent_id,
					1500,
					new DateTime(),
					'canceled',
					$charge_id,
					'...'
				)
			)
		);

		$this->wcpay_gateway->capture_charge( $order );

		$note = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'limit'    => 1,
			]
		)[0];

		$this->assertContains( 'expired', $note->content );
		$this->assertEquals( $order->get_meta( '_intention_status', true ), 'canceled' );
		$this->assertEquals( $order->get_status(), 'cancelled' );
	}
}

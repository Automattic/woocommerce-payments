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
			->setMethods(
				[
					'get_account_data',
					'is_server_connected',
					'capture_intention',
					'get_intent',
					'create_setup_intent',
					'get_setup_intent',
					'get_payment_method',
				]
			)
			->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );

		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );

		$this->mock_token_service = $this->createMock( WC_Payments_Token_Service::class );

		$this->wcpay_gateway = new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->wcpay_account,
			$this->mock_customer_service,
			$this->mock_token_service
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

	public function test_add_payment_method_no_method() {
		$result = $this->wcpay_gateway->add_payment_method();
		$this->assertEquals( 'error', $result['result'] );
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
			->method( 'create_setup_intent' )
			->with( 'pm_mock', 'cus_12345' )
			->willReturn( [ 'id' => 'pm_mock' ] );

		$result = $this->wcpay_gateway->create_setup_intent();

		$this->assertEquals( 'pm_mock', $result['id'] );
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
			->will( $this->returnValue( 'cus_12345' ) );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_setup_intent' )
			->with( 'pm_mock', 'cus_12345' )
			->willReturn( [ 'id' => 'pm_mock' ] );

		$result = $this->wcpay_gateway->create_setup_intent();

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
	}

	/**
	 * Tests account statement descriptor validator
	 *
	 * @dataProvider account_statement_descriptor_validation_provider
	 */
	public function test_validate_account_statement_descriptor_field( $is_valid, $value ) {
		$key = 'account_statement_descriptor';
		if ( $is_valid ) {
			$validated_value = $this->wcpay_gateway->validate_account_statement_descriptor_field( $key, $value );
			$this->assertNotEmpty( $validated_value );
		} else {
			$this->expectExceptionMessage( 'Invalid Statement descriptor.' );
			$this->wcpay_gateway->validate_account_statement_descriptor_field( $key, $value );
		}
	}

	public function account_statement_descriptor_validation_provider() {
		return [
			'valid'         => [ true, 'WCPAY dev' ],
			'allow_digits'  => [ true, 'WCPay dev 2020' ],
			'allow_special' => [ true, 'WCPay-Dev_2020' ],
			'empty'         => [ false, '' ],
			'short'         => [ false, 'WCP' ],
			'long'          => [ false, 'WCPay_dev_WCPay_dev_WCPay_dev_WCPay_dev' ],
			'no_*'          => [ false, 'WCPay * dev' ],
			'no_sqt'        => [ false, 'WCPay \'dev\'' ],
			'no_dqt'        => [ false, 'WCPay "dev"' ],
			'req_latin'     => [ false, 'дескриптор' ],
			'req_letter'    => [ false, '123456' ],
		];
	}
}

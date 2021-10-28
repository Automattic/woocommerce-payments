<?php
/**
 * Class WC_Payments_Invoice_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Invoice_Service_Test unit tests.
 */
class WC_Payments_Invoice_Service_Test extends WP_UnitTestCase {

	const PRICE_ID_KEY                       = '_wcpay_product_price_id';
	const PENDING_INVOICE_ID_KEY             = '_wcpay_pending_invoice_id';
	const ORDER_INVOICE_ID_KEY               = '_wcpay_billing_invoice_id';
	const SUBSCRIPTION_ID_META_KEY           = '_wcpay_subscription_id';
	const SUBSCRIPTION_ITEM_ID_META_KEY      = '_wcpay_subscription_item_id';
	const SUBSCRIPTION_DISCOUNT_IDS_META_KEY = '_wcpay_subscription_discount_ids';

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Product_Service.
	 *
	 * @var WC_Payments_Product_Service|MockObject
	 */
	private $mock_product_service;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client      = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_product_service = $this->createMock( WC_Payments_Product_Service::class );
		$this->mock_gateway         = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->invoice_service      = new WC_Payments_Invoice_Service( $this->mock_api_client, $this->mock_product_service, $this->mock_gateway );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::mark_pending_invoice_for_subscription()
	 */
	public function test_mark_pending_invoice_for_subscription() {
		$mock_subscription = new WC_Subscription();
		$invoice_id        = 'in_123abc';

		$this->invoice_service->mark_pending_invoice_for_subscription( $mock_subscription, $invoice_id );
		$this->assertEquals( $invoice_id, $mock_subscription->get_meta( self::PENDING_INVOICE_ID_KEY, true ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::mark_pending_invoice_paid_for_subscription()
	 */
	public function test_mark_pending_invoice_paid_for_subscription() {
		$mock_subscription = new WC_Subscription();

		$this->invoice_service->mark_pending_invoice_paid_for_subscription( $mock_subscription );
		$this->assertEquals( '', $mock_subscription->get_meta( self::PENDING_INVOICE_ID_KEY, true ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::get_pending_invoice_id()
	 */
	public function test_get_pending_invoice_id() {
		$mock_subscription = new WC_Subscription();

		$this->assertEquals( '', $this->invoice_service->get_pending_invoice_id( $mock_subscription ) );

		$mock_subscription->update_meta_data( self::PENDING_INVOICE_ID_KEY, 'in_test123' );
		$this->assertEquals( 'in_test123', $this->invoice_service->get_pending_invoice_id( $mock_subscription ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::set_pending_invoice_id()
	 */
	public function test_set_pending_invoice_id() {
		$mock_subscription = new WC_Subscription();

		PHPUnit_Utils::call_method(
			$this->invoice_service,
			'set_pending_invoice_id',
			[ $mock_subscription, 'in_test_abc' ]
		);
		$this->assertEquals( 'in_test_abc', $mock_subscription->get_meta( self::PENDING_INVOICE_ID_KEY, true ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::get_order_invoice_id()
	 */
	public function test_get_order_invoice_id() {
		$mock_order = WC_Helper_Order::create_order();

		$this->assertEquals( '', $this->invoice_service->get_order_invoice_id( $mock_order ) );

		$mock_order->update_meta_data( self::ORDER_INVOICE_ID_KEY, 'in_foo' );
		$this->assertEquals( 'in_foo', $this->invoice_service->get_order_invoice_id( $mock_order ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::set_order_invoice_id()
	 */
	public function test_set_order_invoice_id() {
		$mock_order = WC_Helper_Order::create_order();

		$this->invoice_service->set_order_invoice_id( $mock_order, 'in_bar' );
		$this->assertEquals( 'in_bar', $mock_order->get_meta( self::ORDER_INVOICE_ID_KEY, true ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::get_order_id_by_invoice_id()
	 */
	public function test_get_order_id_by_invoice_id() {
		$mock_order = WC_Helper_Order::create_order();

		$mock_order->update_meta_data( self::ORDER_INVOICE_ID_KEY, 'in_invoice123' );
		$mock_order->save();

		$this->assertEquals( $mock_order->get_id(), $this->invoice_service->get_order_id_by_invoice_id( 'in_invoice123' ) );
		$this->assertEquals( 0, $this->invoice_service->get_order_id_by_invoice_id( 'invalid_invoice_id' ) );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::maybe_record_invoice_payment()
	 */
	public function test_maybe_record_invoice_payment() {
		$invoice_id        = 'in_foo123';
		$mock_order        = WC_Helper_Order::create_order();
		$mock_subscription = new WC_Subscription();

		// With the following calls to `maybe_record_first_invoice_payment()`, we only expect 2 calls (see Positive Cases) to result in an API call.
		$this->mock_api_client->expects( $this->exactly( 2 ) )
			->method( 'charge_invoice' )
			->with( $invoice_id, [ 'paid_out_of_band' => 'true' ] );

		$mock_subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, $invoice_id );
		$mock_subscription->save();

		// Positive Cases.
		// First Invoice.
		$this->mock_wcs_get_subscriptions_for_order( [ $mock_subscription ] );
		$this->invoice_service->maybe_record_invoice_payment( $mock_order->get_id() );
		// Manual Renewal.
		$mock_subscription->set_requires_manual_renewal( true );
		$this->invoice_service->maybe_record_invoice_payment( $mock_order->get_id() );
		$this->assertNotTrue( $mock_subscription->is_manual() );

		// Negative Cases.
		// Order contains invoice ID meta.
		$mock_order->update_meta_data( self::ORDER_INVOICE_ID_KEY, $invoice_id );
		$mock_order->save();
		$this->invoice_service->maybe_record_invoice_payment( $mock_order->get_id() );
		// Invalid order ID.
		$this->invoice_service->maybe_record_invoice_payment( 0 );
		// Order isn't related to a subscription.
		$this->mock_wcs_get_subscriptions_for_order( [] );
		$this->invoice_service->maybe_record_invoice_payment( $mock_order->get_id() );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::validate_invoice() with valid data.
	 */
	public function test_validate_invoice_with_valid_data() {
		$mock_order        = WC_Helper_Order::create_order();
		$mock_subscription = new WC_Subscription();
		$mock_subscription->set_parent( $mock_order );
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, [ 'sub_test123' ] );
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_DISCOUNT_IDS_META_KEY, [ 'di_test123' ] );

		foreach ( $mock_order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item ) {
			$item->update_meta_data( self::SUBSCRIPTION_ITEM_ID_META_KEY, 'si_test123_' . $item->get_type() );
		}

		$mock_item_data = [
			[
				'subscription_item' => 'si_test123_line_item',
				'quantity'          => 4,
				'price'             =>
				[
					'unit_amount_decimal' => 1000,
					'currency'            => 'usd',
					'recurring'           =>
					[
						'interval'       => 'month',
						'interval_count' => 1,
					],
				],
			],
			[
				'subscription_item' => 'si_test123_shipping',
				'quantity'          => 1,
				'price'             =>
				[
					'unit_amount_decimal' => 1000,
					'currency'            => 'usd',
					'recurring'           =>
					[
						'interval'       => 'month',
						'interval_count' => 1,
					],
				],
			],
		];

		$mock_discount_data = [ 'di_test123' ];

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'update_subscription_item' );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'update_subscription' );

		$this->invoice_service->validate_invoice( $mock_item_data, $mock_discount_data, $mock_subscription );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::validate_invoice() with invalid data.
	 */
	public function test_validate_invoice_with_invalid_data() {
		$mock_order        = WC_Helper_Order::create_order();
		$mock_subscription = new WC_Subscription();
		$mock_subscription->set_parent( $mock_order );
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, 'sub_test123' );
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_DISCOUNT_IDS_META_KEY, [ 'di_test123' ] );

		foreach ( $mock_order->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item ) {
			$item->update_meta_data( self::SUBSCRIPTION_ITEM_ID_META_KEY, 'si_test123_' . $item->get_type() );
		}

		$mock_item_data = [
			[
				'subscription_item' => 'si_test123_line_item',
				'quantity'          => 1,
				'price'             =>
				[
					'unit_amount_decimal' => 1000,
					'currency'            => 'usd',
					'recurring'           =>
					[
						'interval'       => 'month',
						'interval_count' => 1,
					],
				],
			],
			[
				'subscription_item' => 'si_test123_shipping',
				'quantity'          => 1,
				'price'             =>
				[
					'unit_amount_decimal' => 1000,
					'currency'            => 'usd',
					'recurring'           =>
					[
						'interval'       => 'month',
						'interval_count' => 1,
					],
				],
			],
		];

		$mock_discount_data = [ 'di_test456' ];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_subscription_item' )
			->with(
				'si_test123_line_item',
				[
					'quantity' => 4,
				]
			);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_subscription' )
			->with(
				'sub_test123',
				[
					'discounts' => [],
				]
			)
			->willReturn(
				[ 'discounts' => [] ]
			);

		$this->invoice_service->validate_invoice( $mock_item_data, $mock_discount_data, $mock_subscription );
		$this->assertSame( [], $mock_subscription->get_meta( self::SUBSCRIPTION_DISCOUNT_IDS_META_KEY, true ) );
	}

	/**
	 * Tests WC_Payments_Invoice_Service::get_and_attach_intent_info_to_order() with a valid Intention object.
	 */
	public function test_get_and_attach_intent_info_to_order() {
		$mock_order = WC_Helper_Order::create_order();
		$intent_id  = 'pi_paymentIntentID';

		$intent = new WC_Payments_API_Intention(
			$intent_id,
			'10',
			'USD',
			'customer_id',
			'payment_method_id',
			new DateTime(),
			'succeeded', // Intent status.
			'charge_id',
			'client_secret'
		);

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->with( $intent_id )
			->willReturn( $intent );

		$this->mock_gateway
			->expects( $this->once() )
			->method( 'attach_intent_info_to_order' )
			->willReturn( null );

		$this->invoice_service->get_and_attach_intent_info_to_order( $mock_order, $intent_id );
	}

	/**
	 * Tests WC_Payments_Invoice_Service::get_and_attach_intent_info_to_order() with a thrown exception when retrieving the PaymentIntent.
	 */
	public function test_get_and_attach_intent_info_to_order_with_exception() {
		$mock_order = WC_Helper_Order::create_order();
		$intent_id  = 'pi_paymentIntentID';

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->with( $intent_id )
			->will( $this->throwException( new API_Exception( 'whoops', 'mock_error', 403 ) ) );

		$this->mock_gateway
			->expects( $this->never() )
			->method( 'attach_intent_info_to_order' )
			->willReturn( null );

		$this->invoice_service->get_and_attach_intent_info_to_order( $mock_order, $intent_id );
	}

	/**
	 * Mocks the wcs_order_contains_subscription function return.
	 *
	 * @param bool $value The value to return to wcs_order_contains_subscription() calls.
	 */
	private function mock_wcs_get_subscriptions_for_order( $value ) {
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order ) use ( $value ) {
				return $value;
			}
		);
	}
}

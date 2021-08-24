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

	const PENDING_INVOICE_ID_KEY = '_wcpay_pending_invoice_id';
	const ORDER_INVOICE_ID_KEY   = '_wcpay_billing_invoice_id';

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->invoice_service = new WC_Payments_Invoice_Service( $this->mock_api_client, null );
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
	 * Tests for WC_Payments_Invoice_Service::delete_invoice_items()
	 */
	public function test_delete_invoice_items() {
		$invoice_ids = [ 'in_foo', 'in_bar', 'in_baz' ];

		$this->mock_api_client->expects( $this->exactly( 3 ) )
			->method( 'delete_invoice_item' )
			->withConsecutive(
				[ $this->equalTo( 'in_foo' ) ],
				[ $this->equalTo( 'in_bar' ) ],
				[ $this->equalTo( 'in_baz' ) ]
			);

		$this->invoice_service->delete_invoice_items( $invoice_ids );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::format_invoice_item_data()
	 */
	public function test_format_invoice_item_data() {
		$result = PHPUnit_Utils::call_method(
			$this->invoice_service,
			'format_invoice_item_data',
			[ 10, 'USD', 'foobar' ]
		);

		$this->assertEquals(
			[
				'amount'      => (float) 1000,
				'currency'    => 'USD',
				'description' => 'foobar',
				'tax_rates'   => [],
			],
			$result
		);
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
	 * Tests for WC_Payments_Invoice_Service::prepare_invoice_item_data()
	 */
	public function test_prepare_invoice_item_data() {
		$mock_order        = WC_Helper_Order::create_order();
		$mock_subscription = new WC_Subscription();

		$mock_subscription->set_parent( $mock_order );
		$mock_order->set_discount_total( 20 );

		$result = PHPUnit_Utils::call_method(
			$this->invoice_service,
			'prepare_invoice_item_data',
			[ $mock_subscription ]
		);

		$this->assertTrue( is_array( $result ) );
		$this->assertContainsOnly( 'array', $result );
		$this->assertEquals( 2, count( $result ) );

		foreach ( $result as $item_data ) {
			foreach ( [ 'amount', 'currency', 'description', 'tax_rates' ] as $key ) {
				$this->assertArrayHasKey( $key, $item_data );
			}
		}
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::create_invoice_items_for_subscription()
	 */
	public function test_create_invoice_items_for_subscription() {
		$wcpay_subscription_id = 'sub_test123';
		$wcpay_customer_id     = 'cus_test123';

		$mock_order        = WC_Helper_Order::create_order();
		$mock_subscription = new WC_Subscription();

		$mock_subscription->set_parent( $mock_order );

		// The mock order comes with a $10 flat rate shipping so the expected invoice items are the following.
		$expected_args = [
			'amount'       => 1000,
			'currency'     => 'USD',
			'description'  => 'Flat rate shipping',
			'tax_rates'    => [],
			'customer'     => $wcpay_customer_id,
			'subscription' => $wcpay_subscription_id,
		];

		$this->mock_api_client->expects( $this->once() )
			->method( 'create_invoice_item' )
			->with( $expected_args );

		$this->invoice_service->create_invoice_items_for_subscription( $mock_subscription, $wcpay_customer_id, $wcpay_subscription_id );
	}

	/**
	 * Tests for WC_Payments_Invoice_Service::maybe_record_first_invoice_payment()
	 */
	public function test_maybe_record_first_invoice_payment() {
		$invoice_id        = 'in_foo123';
		$mock_order        = WC_Helper_Order::create_order();
		$mock_subscription = new WC_Subscription();

		// With the following calls to `maybe_record_first_invoice_payment()`, we only expect 2 calls (see Positive Cases) to result in an API call.
		$this->mock_api_client->expects( $this->exactly( 6 ) )
			->method( 'charge_invoice' )
			->with( $invoice_id, [ 'paid_out_of_band' => 'true' ] );

		// Mock an empty list of subscriptions.
		$this->mock_wcs_get_subscriptions_for_order( [] );

		// Negative Cases.
		// Order isn't related to a subscription.
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'pending', 'processing' );

		$subscriptions = [ $mock_subscription ];

		// Mock the get subscriptions from order call.
		$this->mock_wcs_get_subscriptions_for_order( $subscriptions );

		// Order doesn't have a pending invoice.
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'pending', 'processing' );

		// Mock the order having a invoice ID stored in meta.
		$mock_subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, $invoice_id );
		$mock_subscription->save();

		// Invalid order ID.
		$this->invoice_service->maybe_record_first_invoice_payment( 0, 'pending', 'processing' );

		// An already completed order.
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'processing', 'completed' );

		// A failed order.
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'pending', 'failed' );

		// Positive Cases.
		// Successful calls - Subscription parent order, with an invoice, transitioning to a paid status (processing or completed) from an unpaid status (pending, on-hold, failed).
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'pending', 'processing' );
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'on-hold', 'processing' );
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'failed', 'processing' );
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'pending', 'completed' );
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'on-hold', 'completed' );
		$this->invoice_service->maybe_record_first_invoice_payment( $mock_order->get_id(), 'failed', 'completed' );
	}

	/**
	 * Mocks the wcs_order_contains_subscription function return.
	 *
	 * @param bool The value to return to wcs_order_contains_subscription() calls.
	 */
	private function mock_wcs_get_subscriptions_for_order( $value ) {
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order ) use ( $value ) {
				return $value;
			}
		);
	}
}

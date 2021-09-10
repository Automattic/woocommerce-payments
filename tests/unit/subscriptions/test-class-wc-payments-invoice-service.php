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
		$this->invoice_service      = new WC_Payments_Invoice_Service( $this->mock_api_client, $this->mock_product_service );
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

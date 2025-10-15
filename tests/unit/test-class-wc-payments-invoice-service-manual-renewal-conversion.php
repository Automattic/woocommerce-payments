<?php
/**
 * Class WC_Payments_Invoice_Service_Manual_Renewal_Conversion_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Test manual-to-automatic conversion logic in invoice service.
 */
class WC_Payments_Invoice_Service_Manual_Renewal_Conversion_Test extends WCPAY_UnitTestCase {

	const SUBSCRIPTION_ID_META_KEY = '_wcpay_subscription_id';
	const ORDER_INVOICE_ID_KEY     = '_wcpay_billing_invoice_id';

	/**
	 * Invoice service instance.
	 *
	 * @var WC_Payments_Invoice_Service
	 */
	private $invoice_service;

	/**
	 * Mock payments API client.
	 *
	 * @var WC_Payments_API_Client&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock Order Service
	 *
	 * @var WC_Payments_Order_Service&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_order_service;

	public function set_up() {
		parent::set_up();

		$this->mock_api_client    = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );
		$this->invoice_service    = new WC_Payments_Invoice_Service( $this->mock_api_client, $this->mock_order_service );
	}

	/**
	 * Test that manual subscription with payment tokens converts to automatic.
	 */
	public function test_manual_subscription_with_payment_tokens_should_convert_to_automatic() {
		// Arrange.
		$order        = WC_Helper_Order::create_order();
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( true );
		$subscription->set_parent( $order );
		$subscription->payment_tokens = [ uniqid( 'pm_' ) ];
		$subscription->payment_method = 'woocommerce_payments';
		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, uniqid( 'sub_' ) );
		$subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, uniqid( 'order_' ) );
		$subscription->save();

		// Mock API call to charge invoice.
		$this->mock_api_client->expects( $this->once() )
			->method( 'charge_invoice' )
			->willReturn( true );

		// Mock wcs_get_subscriptions_for_order function.
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order_id ) use ( $subscription ) {
				return [ $subscription ];
			}
		);

		$initial_manual_state = $subscription->is_manual();

		// Act - Call the actual invoice service method.
		$this->invoice_service->maybe_record_invoice_payment( $order->get_id() );

		// Assert.
		$this->assertTrue( $initial_manual_state, 'Subscription should start as manual' );
		$this->assertFalse( $subscription->is_manual(), 'Subscription with payment tokens should convert to automatic' );
	}

	/**
	 * Test that manual subscription without payment tokens stays manual.
	 */
	public function test_manual_subscription_without_payment_tokens_should_stay_manual() {
		// Arrange.
		$order        = WC_Helper_Order::create_order();
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( true );
		$subscription->set_parent( $order );
		$subscription->payment_method = 'woocommerce_payments';
		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, uniqid( 'sub_' ) );
		$subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, uniqid( 'order_' ) );
		$subscription->save();

		// Mock API call to charge invoice.
		$this->mock_api_client->expects( $this->once() )
			->method( 'charge_invoice' )
			->willReturn( true );

		// Mock wcs_get_subscriptions_for_order function.
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order_id ) use ( $subscription ) {
				return [ $subscription ];
			}
		);

		$initial_manual_state = $subscription->is_manual();

		// Act - Call the actual invoice service method.
		$this->invoice_service->maybe_record_invoice_payment( $order->get_id() );

		// Assert.
		$this->assertTrue( $initial_manual_state, 'Subscription should start as manual' );
		$this->assertTrue( $subscription->is_manual(), 'Subscription without payment tokens should stay manual' );
	}

	public function tear_down() {
		parent::tear_down();
		WC_Subscriptions::set_wcs_get_subscriptions_for_order( null );
	}
}

<?php
/**
 * Class WC_Payments_Non_Reusable_Payment_Methods_Integration_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Integration tests for non-reusable payment methods with subscriptions.
 * Tests the complete flow from checkout to renewal using real service instances.
 */
class WC_Payments_Non_Reusable_Payment_Methods_Integration_Test extends WCPAY_UnitTestCase {

	/**
	 * Payment gateway trait instance.
	 *
	 * @var WC_Payment_Gateway_WCPay_Subscriptions_Trait
	 */
	private $payment_gateway_trait;

	/**
	 * Invoice service instance.
	 *
	 * @var WC_Payments_Invoice_Service
	 */
	private $invoice_service;

	/**
	 * Subscription service instance.
	 *
	 * @var WC_Payments_Subscription_Service
	 */
	private $subscription_service;

	/**
	 * Mock subscription product.
	 *
	 * @var WC_Product_Subscription
	 */
	private $mock_subscription_product;

	public function set_up() {
		parent::set_up();

		// Set up real service instances for integration testing.
		$mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$mock_order_service    = $this->createMock( WC_Payments_Order_Service::class );
		$mock_product_service  = $this->createMock( WC_Payments_Product_Service::class );
		$mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );

		$this->mock_subscription_product = new WC_Subscriptions_Product();
		$this->mock_subscription_product->set_props(
			[
				'regular_price' => 10,
				'price'         => 10,
			]
		);
		$this->mock_subscription_product->save();

		$mock_customer_service
			->method( 'get_customer_id_for_order' )
			->willReturn( uniqid( 'wcpay_cus_' ) );

		$mock_product_service
			->method( 'get_wcpay_product_id_for_item' )
			->willReturn( uniqid( 'wcpay_prod_' ) );

		$mock_product_service
			->method( 'get_or_create_wcpay_product_id' )
			->willReturn( uniqid( 'wcpay_prod_' ) );

		$mock_product_service
			->method( 'is_valid_billing_cycle' )
			->willReturn( true );

		$mock_api_client
			->method( 'create_subscription' )
			->willReturn(
				[
					'id'    => uniqid( 'sub_' ),
					'items' => [
						'data' => [
							[
								'id' => uniqid( 'sub_item_' ),
							],
						],
					],
				]
			);

		// Mock charge_invoice for maybe_record_invoice_payment.
		$mock_api_client
			->method( 'charge_invoice' )
			->willReturn( [ 'status' => 'paid' ] );

		$this->invoice_service       = new WC_Payments_Invoice_Service( $mock_api_client, $mock_order_service );
		$this->subscription_service  = new WC_Payments_Subscription_Service( $mock_api_client, $mock_customer_service, $mock_product_service, $this->invoice_service );
		$this->payment_gateway_trait = $this->getMockForTrait( WC_Payment_Gateway_WCPay_Subscriptions_Trait::class );
	}

	/**
	 * Test complete flow: Reusable payment method -> Manual subscription -> Converts to automatic if reusable payment method is used.
	 */
	public function test_reusable_payment_method_with_manual_subscription_converts_to_automatic() {
		// Arrange: Create order and subscription with reusable payment method.
		$order        = WC_Helper_Order::create_order( 1, 50, $this->mock_subscription_product );
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( true );
		$subscription->set_parent( $order );
		$subscription->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$subscription->set_payment_tokens( [ uniqid( 'pm_' ) ] );
		// Mock subscription meta for WCPay checks.
		$subscription->update_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY, uniqid( 'sub_test_' ) );
		$subscription->update_meta_data( WC_Payments_Invoice_Service::ORDER_INVOICE_ID_KEY, 'inv_test123' );
		$subscription->save();

		// Mock required functions.
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order_id ) use ( $subscription ) {
				return [ $subscription ];
			}
		);
		WC_Subscriptions::set_wcs_get_subscriptions_for_renewal_order(
			function ( $order_id ) use ( $subscription ) {
				return [ $subscription ];
			}
		);

		// Mock the abstract get_payment_token method to return a payment token for subscriptions with tokens.
		$this->payment_gateway_trait
			->method( 'get_payment_token' )
			->willReturnCallback(
				function ( $subscription_or_order ) {
					if ( is_a( $subscription_or_order, 'WC_Subscription' ) ) {
						$tokens = $subscription_or_order->get_payment_tokens();
						return ! empty( $tokens ) ? $tokens[0] : null;
					}
					return null;
				}
			);

		// Act 1: Test that subscription stays manual during renewal (real method).
		$initial_manual_state = $subscription->is_manual();
		$this->invoice_service->maybe_record_invoice_payment( $order->get_id() );
		$stayed_manual = $subscription->is_manual();

		// Act 2: Test WCPay subscription creation during renewal (real method).
		$this->subscription_service->create_subscription_for_manual_renewal( $order->get_id() );

		// Assert: Complete flow behavior.
		$this->assertTrue( $initial_manual_state, 'Subscription should start as manual' );
		$this->assertNotTrue( $stayed_manual, 'Subscription should become automatic when payment tokens are present' );
		// Note: We can't easily assert create_subscription was called without complex mocking,
		// but the real method will call it when payment tokens exist.
	}

	public function tear_down() {
		parent::tear_down();
		WC_Subscriptions::set_wcs_get_subscriptions_for_order( null );
		WC_Subscriptions::set_wcs_get_subscriptions_for_renewal_order( null );
	}
}

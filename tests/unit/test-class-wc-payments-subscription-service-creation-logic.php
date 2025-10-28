<?php
/**
 * Class WC_Payments_Subscription_Service_Creation_Logic_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test WCPay subscription creation logic for manual renewals.
 */
class WC_Payments_Subscription_Service_Creation_Logic_Test extends WCPAY_UnitTestCase {

	const SUBSCRIPTION_ID_META_KEY = '_wcpay_subscription_id';
	const ORDER_INVOICE_ID_KEY     = '_wcpay_billing_invoice_id';

	/**
	 * Subscription service instance.
	 *
	 * @var WC_Payments_Subscription_Service
	 */
	private $subscription_service;

	/**
	 * Mock payments API client.
	 *
	 * @var WC_Payments_API_Client&MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Product_Service instance.
	 *
	 * @var WC_Payments_Product_Service&MockObject
	 */
	private $mock_product_service;

	/**
	 * Mock WC_Payments_Customer_Service instance.
	 *
	 * @var WC_Payments_Customer_Service&MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock WC_Payments_Invoice_Service instance.
	 *
	 * @var WC_Payments_Invoice_Service&MockObject
	 */
	private $mock_invoice_service;

	/**
	 * Mock Subscription Product instance.
	 *
	 * @var WC_Subscriptions_Product
	 */
	private $mock_subscription_product;

	/**
	 * Mock callback for woocommerce_get_product_from_item filter.
	 *
	 * @var callable
	 */
	private $mock_get_product_from_item_callback;

	public function set_up() {
		parent::set_up();

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_product_service  = $this->createMock( WC_Payments_Product_Service::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_invoice_service  = $this->createMock( WC_Payments_Invoice_Service::class );
		$this->subscription_service  = new WC_Payments_Subscription_Service( $this->mock_api_client, $this->mock_customer_service, $this->mock_product_service, $this->mock_invoice_service );

		$this->mock_subscription_product = new WC_Subscriptions_Product();
		WC_Subscriptions_Product::set_period( 'month' );
		WC_Subscriptions_Product::set_interval( 1 );
		$this->mock_subscription_product->set_props(
			[
				'regular_price' => 10,
				'price'         => 10,
			]
		);
		$this->mock_subscription_product->save();

		$this->mock_get_product_from_item_callback = function () {
			return $this->mock_subscription_product;
		};

		add_filter( 'woocommerce_get_product_from_item', $this->mock_get_product_from_item_callback );
	}

	/**
	 * Test that WCPay subscription IS created for manual renewal with payment tokens.
	 */
	public function test_should_create_wcpay_subscription_for_manual_renewal_with_payment_tokens() {
		// Arrange.
		$order        = WC_Helper_Order::create_order( 1, 50, $this->mock_subscription_product );
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( true );
		$subscription->set_parent( $order );
		$subscription->set_props(
			[
				'payment_method' => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				// Payment tokens saved (characteristic of reusable payment methods like cards).
				'payment_tokens' => [ uniqid( 'pm_' ) ],
			]
		);
		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, '' );
		$subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, uniqid( 'order_' ) );
		$subscription->save();

		$mock_line_item                  = array_values( $order->get_items() )[0];
		$mock_shipping_item              = array_values( $order->get_items( 'shipping' ) )[0];
		$mock_wcpay_product_id           = uniqid( 'wcpay_prod_' );
		$mock_wcpay_subscription_id      = uniqid( 'wcpay_subscription_' );
		$mock_wcpay_subscription_item_id = uniqid( 'wcpay_subscription_item_' );
		$mock_subscription_data          = [
			'customer' => uniqid( 'cus_' ),
			'items'    => [
				[
					'quantity'   => 4,
					'metadata'   => [
						'wc_item_id' => $mock_line_item->get_id(),
					],
					'price_data' => [
						'currency'            => 'USD',
						'product'             => $mock_wcpay_product_id,
						'unit_amount_decimal' => 1000.0,
						'recurring'           => [
							'interval'       => 'month',
							'interval_count' => 1,
						],
					],
				],
				[
					'price_data' => [
						'product'             => $mock_wcpay_product_id,
						'currency'            => 'USD',
						'unit_amount_decimal' => 1000.0,
						'recurring'           => [
							'interval'       => 'month',
							'interval_count' => 1,
						],
					],
					'metadata'   => [
						'wc_item_id' => $mock_shipping_item->get_id(),
						'method'     => $mock_shipping_item->get_name(),
					],
				],
			],
			'metadata' => [
				'subscription_source' => 'woo_subscriptions',
			],
		];

		$this->mock_customer_service
			->method( 'get_customer_id_for_order' )
			->willReturn( $mock_subscription_data['customer'] );

		$this->mock_product_service
			->expects( $this->once() )
			->method( 'get_wcpay_product_id_for_item' )
			->willReturn( $mock_wcpay_product_id );

		$this->mock_product_service
			->expects( $this->once() )
			->method( 'get_or_create_wcpay_product_id' )
			->willReturn( $mock_wcpay_product_id );

		$this->mock_product_service
			->method( 'is_valid_billing_cycle' )
			->willReturn( true );

		// Mock subscription creation API call.
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_subscription' )
			->with( $mock_subscription_data )
			->willReturn(
				[
					'id'             => $mock_wcpay_subscription_id,
					'latest_invoice' => 'mock_wcpay_invoice_id',
					'items'          => [
						'data' => [
							[
								'id'       => $mock_wcpay_subscription_item_id,
								'metadata' => [
									'wc_item_id' => $mock_line_item->get_id(),
								],
							],
							[
								'id'       => $mock_wcpay_subscription_item_id,
								'metadata' => [
									'wc_item_id' => $mock_shipping_item->get_id(),
								],
							],
						],
					],
				]
			);

		// Mock wcs_get_subscriptions_for_renewal_order function.
		WC_Subscriptions::set_wcs_get_subscriptions_for_renewal_order(
			function ( $order_id ) use ( $subscription ) {
				return [ $subscription ];
			}
		);

		// Act - Call the actual subscription service method.
		$this->subscription_service->create_subscription_for_manual_renewal( $order->get_id() );

		// Assert - Verify that create_subscription was called (indicating WCPay subscription was created).
		// The mock expectation for create_subscription will verify this.
	}

	/**
	 * Test that WCPay subscription is NOT created for manual renewal without payment tokens.
	 */
	public function test_should_not_create_wcpay_subscription_for_manual_renewal_without_payment_tokens() {
		// Arrange.
		$order        = WC_Helper_Order::create_order();
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( true );
		$subscription->set_parent( $order );

		// Mock empty payment tokens to simulate non-reusable payment method.
		$subscription->payment_tokens = [];
		$subscription->payment_method = 'woocommerce_payments';
		// Mock that subscription doesn't have WCPay subscription ID yet.
		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, '' );
		$subscription->save();

		// Mock subscription creation API call should NOT be called.
		$this->mock_api_client->expects( $this->never() )
			->method( 'create_subscription' );

		// Mock wcs_get_subscriptions_for_renewal_order function.
		WC_Subscriptions::set_wcs_get_subscriptions_for_renewal_order(
			function ( $order_id ) use ( $subscription ) {
				return [ $subscription ];
			}
		);

		// Act - Call the actual subscription service method.
		$this->subscription_service->create_subscription_for_manual_renewal( $order->get_id() );

		// Assert - Verify that create_subscription was NOT called (indicating WCPay subscription was NOT created).
		// The mock expectation for never() will verify this.
	}

	/**
	 * Test that WCPay subscription is NOT created for automatic subscriptions.
	 */
	public function test_should_not_create_wcpay_subscription_for_automatic_subscription() {
		// Arrange.
		$order        = WC_Helper_Order::create_order();
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( false ); // Automatic subscription.
		$subscription->set_parent( $order );
		// Even with payment tokens, automatic subscriptions use different logic.
		$subscription->payment_tokens = [ 'pm_test123' ];
		$subscription->payment_method = 'woocommerce_payments';
		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, uniqid( 'sub_' ) );
		$subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, uniqid( 'order_' ) );
		$subscription->save();

		// Mock subscription creation API call should NOT be called.
		$this->mock_api_client->expects( $this->never() )
			->method( 'create_subscription' );

		// Mock wcs_get_subscriptions_for_renewal_order function.
		WC_Subscriptions::set_wcs_get_subscriptions_for_renewal_order(
			function ( $order_id ) use ( $subscription ) {
				return [ $subscription ];
			}
		);

		// Act - Call the actual subscription service method.
		$this->subscription_service->create_subscription_for_manual_renewal( $order->get_id() );

		// Assert - Verify that create_subscription was NOT called since subscription is automatic.
		// The mock expectation for never() will verify this.
	}

	public function tear_down() {
		parent::tear_down();
		WC_Subscriptions::set_wcs_get_subscriptions_for_renewal_order( null );
		remove_filter( 'woocommerce_get_product_from_item', $this->mock_get_product_from_item_callback );
	}
}

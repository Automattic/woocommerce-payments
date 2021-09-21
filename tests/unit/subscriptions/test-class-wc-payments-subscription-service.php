<?php
/**
 * Class WC_Payments_Subscription_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Subscription_Service_Test unit tests.
 */
class WC_Payments_Subscription_Service_Test extends WP_UnitTestCase {

	/**
	 * Subscription meta key used to store WCPay subscription's ID.
	 *
	 * @const string
	 */
	const SUBSCRIPTION_ID_META_KEY = '_wcpay_subscription_id';

	/**
	 * Subscription meta key used to store WCPay subscription item's ID.
	 *
	 * @const string
	 */
	const SUBSCRIPTION_ITEM_ID_META_KEY = '_wcpay_subscription_item_id';

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock WC_Payments_Product_Service.
	 *
	 * @var WC_Payments_Product_Service|MockObject
	 */
	private $mock_product_service;

	/**
	 * Mock WC_Payments_Invoice_Service.
	 *
	 * @var WC_Payments_Invoice_Service|MockObject
	 */
	private $mock_invoice_service;

	/**
	 * Mock WC_Payments_Subscription_Service
	 *
	 * @var WC_Payments_Subscription_Service
	 */
	private $subscription_service;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_product_service  = $this->createMock( WC_Payments_Product_Service::class );
		$this->mock_invoice_service  = $this->createMock( WC_Payments_Invoice_Service::class );

		$this->subscription_service = new WC_Payments_Subscription_Service( $this->mock_api_client, $this->mock_customer_service, $this->mock_product_service, $this->mock_invoice_service );
	}

	/**
	 * Test WC_Payments_Subscription_Service->get_wcpay_subscription().
	 */
	public function test_get_wcpay_subscription() {
		$mock_subscription          = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_subscription_id_12345';

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_subscription' )
			->with( $mock_wcpay_subscription_id )
			->willReturn( [ 'subscription_id' => $mock_wcpay_subscription_id ] );

		// Check subscription that isn't a WCPay Subscription.
		$this->assertEquals( $this->subscription_service->get_wcpay_subscription( $mock_subscription ), false );

		// set WCPay Subscription ID.
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );
		$this->assertEquals( $this->subscription_service->get_wcpay_subscription( $mock_subscription ), [ 'subscription_id' => $mock_wcpay_subscription_id ] );
	}

	/**
	 * Test WC_Payments_Subscription_Service->create_subscription()
	 */
	public function test_create_subscription() {
		$mock_subscription            = new WC_Subscription();
		$mock_subscription->trial_end = 0;
		$mock_subscription_product    = new WC_Subscriptions_Product();
		$mock_subscription_product->save();
		$mock_order = WC_Helper_Order::create_order( 1, 50, $mock_subscription_product );
		$mock_subscription->set_parent( $mock_order );
		$mock_line_item     = array_values( $mock_order->get_items() )[0];
		$mock_shipping_item = array_values( $mock_order->get_items( 'shipping' ) )[0];

		WC_Subscriptions_Synchroniser::$is_syncing_enabled = false;

		$mock_wcpay_product_id           = 'wcpay_prod_test123';
		$mock_wcpay_price_id             = 'wcpay_price_test123';
		$mock_wcpay_subscription_id      = 'wcpay_subscription_test12345';
		$mock_wcpay_subscription_item_id = 'wcpay_subscription_item_test12345';
		$mock_subscription_data          = [
			'add_invoice_items' => [],
			'customer'          => '1',
			'discounts'         => [],
			'items'             => [
				[
					'price'     => $mock_wcpay_price_id,
					'quantity'  => 4,
					'metadata'  => [
						'wc_item_id' => $mock_line_item->get_id(),
					],
					'tax_rates' => [],
				],
				[
					'price_data' => [
						'product'     => $mock_wcpay_product_id,
						'currency'    => 'USD',
						'unit_amount' => 1000,
						'recurring'   => [
							'interval'       => 'month',
							'interval_count' => 1,
						],
					],
					'metadata'   => [
						'wc_item_id' => $mock_shipping_item->get_id(),
					],
					'tax_rates'  => [],
				],
			],
		];

		$this->assertNotEquals( $mock_subscription->get_meta( self::SUBSCRIPTION_ID_META_KEY ), $mock_wcpay_subscription_id );

		$this->mock_customer_service->expects( $this->once() )
			->method( 'get_customer_id_for_order' )
			->with( $mock_subscription )
			->willReturn( $mock_subscription_data['customer'] );

		$this->mock_product_service->expects( $this->once() )
			->method( 'get_stripe_price_id' )
			->willReturn( $mock_wcpay_price_id );

		$this->mock_product_service->expects( $this->once() )
			->method( 'get_stripe_product_id_for_item' )
			->willReturn( $mock_wcpay_product_id );

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

		$this->subscription_service->create_subscription( $mock_subscription );

		// check the subscription was created and the create_subscription() correctly stored the wcpay subscription ID on the subscription.
		$this->assertEquals( $mock_subscription->get_meta( self::SUBSCRIPTION_ID_META_KEY ), $mock_wcpay_subscription_id );

		// check whether create_subscription() correctly stored the wcpay subscription item IDs on the subscription items.
		foreach ( $mock_order->get_items( 'line_item', 'shipping' ) as $item ) {
			$this->assertEquals( $item->get_meta( self::SUBSCRIPTION_ITEM_ID_META_KEY ), $mock_wcpay_subscription_item_id );
		}
	}

	/**
	 * Test WC_Payments_Subscription_Service->cancel_subscription()
	 */
	public function test_cancel_subscription() {
		$mock_subscription          = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_canceled_test12345';

		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );

		$this->mock_api_client->expects( $this->once() )
			->method( 'cancel_subscription' )
			->with( $mock_wcpay_subscription_id )
			->willReturn( [ 'id' => $mock_wcpay_subscription_id ] );

		$this->subscription_service->cancel_subscription( $mock_subscription );
	}

	/**
	 * Test WC_Payments_Subscription_Service->suspend_subscription()
	 */
	public function test_suspend_subscription() {
		$mock_subscription          = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_suspended_test12345';
		$input_data                 = [ 'pause_collection' => [ 'behavior' => 'void' ] ];

		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_subscription' )
			->with( $mock_wcpay_subscription_id, $input_data )
			->willReturn( [ 'id' => $mock_wcpay_subscription_id ] );

		$this->subscription_service->suspend_subscription( $mock_subscription );
	}

	/**
	 * Test WC_Payments_Subscription_Service->reactivate_subscription()
	 */
	public function test_reactivate_subscription() {
		$mock_subscription          = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_activated_test12345';
		$input_data                 = [
			'cancel_at_period_end' => 'false',
			'pause_collection'     => '',
		];

		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_subscription' )
			->with( $mock_wcpay_subscription_id, $input_data )
			->willReturn( [ 'id' => $mock_wcpay_subscription_id ] );

		$this->subscription_service->reactivate_subscription( $mock_subscription );
	}

	/**
	 * Test WC_Payments_Subscription_Service->set_pending_cancel_for_subscription()
	 */
	public function test_set_pending_cancel_for_subscription() {
		$mock_subscription          = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_pending_canceled_test12345';
		$input_data                 = [ 'cancel_at_period_end' => 'true' ];

		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_subscription' )
			->with( $mock_wcpay_subscription_id, $input_data )
			->willReturn( [ 'id' => $mock_wcpay_subscription_id ] );

		$this->subscription_service->set_pending_cancel_for_subscription( $mock_subscription );
	}

	/**
	 * Test WC_Payments_Subscription_Service->update_wcpay_subscription_payment_method()
	 */
	public function test_update_wcpay_subscription_payment_method() {
		$subscription               = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_subscription_test12345';
		$mock_wcpay_token_id        = 'wcpay_test_token1234';
		$token                      = WC_Helper_Token::create_token( $mock_wcpay_token_id, 1 );

		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $subscription ) {
				return $subscription;
			}
		);

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_subscription' )
			->with( $mock_wcpay_subscription_id, [ 'default_payment_method' => $mock_wcpay_token_id ] )
			->willReturn( [ 'id' => $mock_wcpay_subscription_id ] );

		$this->subscription_service->update_wcpay_subscription_payment_method( 1, $token->get_id(), $token );
	}

	/**
	 * Test WC_Payments_Subscription_Service->maybe_update_date_for_subscription()
	 */
	public function test_maybe_update_date_for_subscription() {
		$this->assertTrue( true );
		$subscription               = new WC_Subscription();
		$mock_subscription_id       = 1;
		$mock_wcpay_subscription_id = 'wcpay_update_date_test12345';
		$subscription->trial_end    = 0;

		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );

		$_POST['woocommerce_meta_nonce']  = wp_create_nonce( 'woocommerce_save_data' );
		$_POST['trial_end_timestamp_utc'] = time();

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $subscription ) {
				return $subscription;
			}
		);

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_subscription' )
			->with( $mock_wcpay_subscription_id, [ 'trial_end' => $_POST['trial_end_timestamp_utc'] ] ) //PHPCS:ignore WordPress.Security
			->willReturn( [ 'id' => $mock_wcpay_subscription_id ] );

		$this->subscription_service->maybe_update_date_for_subscription( $mock_subscription_id );
	}

	/**
	 * Test WC_Payments_Subscription_Service->prepare_wcpay_subscription_data()
	 */
	public function test_prepare_wcpay_subscription_data() {
		$mock_wcpay_subscription_id = 'wcpay_prepare_sub12345';
		$mock_wcpay_customer_id     = 'wcpay_prepare_cus12345';

		update_user_option( 1, WC_Payments_Customer_Service::WCPAY_LIVE_CUSTOMER_ID_OPTION, $mock_wcpay_customer_id );

		$mock_subscription            = new WC_Subscription();
		$mock_subscription->trial_end = 0;
		$mock_subscription_product    = new WC_Subscriptions_Product();
		$mock_subscription_product->save();
		$mock_order  = WC_Helper_Order::create_order( 1, 50, $mock_subscription_product );
		$mock_coupon = new WC_Coupon();
		$mock_coupon->set_code( 'test_coupon' );
		$mock_coupon->set_amount( 10.0 );
		$mock_coupon->save();
		$mock_order->apply_coupon( $mock_coupon );
		$mock_subscription->set_parent( $mock_order );
		$mock_line_item     = array_values( $mock_order->get_items() )[0];
		$mock_shipping_item = array_values( $mock_order->get_items( 'shipping' ) )[0];

		$this->mock_product_service->expects( $this->once() )
			->method( 'get_stripe_price_id' )
			->willReturn( 'wcpay_price_test123' );

		$this->mock_product_service->expects( $this->once() )
			->method( 'get_stripe_product_id_for_item' )
			->willReturn( 'wcpay_prod_test123' );

		$expected_result = [
			'add_invoice_items' => [],
			'customer'          => $mock_wcpay_customer_id,
			'discounts'         => [
				[
					'amount_off' => 1000,
					'currency'   => 'USD',
					'duration'   => 'once',
					'name'       => 'Coupon - test_coupon',
				],
			],
			'items'             => [
				[
					'metadata'  => [
						'wc_item_id' => $mock_line_item->get_id(),
					],
					'price'     => 'wcpay_price_test123',
					'quantity'  => 4,
					'tax_rates' => [],
				],
				[
					'price_data' => [
						'product'     => 'wcpay_prod_test123',
						'currency'    => 'USD',
						'unit_amount' => 1000,
						'recurring'   => [
							'interval'       => 'month',
							'interval_count' => 1,
						],
					],
					'metadata'   => [
						'wc_item_id' => $mock_shipping_item->get_id(),
					],
					'tax_rates'  => [],
				],
			],
		];

		$actual_result = PHPUnit_Utils::call_method(
			$this->subscription_service,
			'prepare_wcpay_subscription_data',
			[ $mock_wcpay_customer_id, $mock_subscription ]
		);

		$this->assertEquals( $expected_result, $actual_result );
	}

	/**
	 * Test WC_Payments_Subscription_Service->update_subscription()
	 */
	public function test_update_subscription() {
		$mock_subscription          = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_update_subscription_test12345';

		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );
		$mock_data = [ 'trial_end' => 0 ];

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_subscription' )
			->with( $mock_wcpay_subscription_id, $mock_data )
			->willReturn( [ 'updated' => $mock_wcpay_subscription_id ] );

		$actual_result = PHPUnit_Utils::call_method(
			$this->subscription_service,
			'update_subscription',
			[ $mock_subscription, $mock_data ]
		);

		$this->assertEquals( [ 'updated' => $mock_wcpay_subscription_id ], $actual_result );
	}

	/**
	 * Test WC_Payments_Subscription_Service->set_trial_end_for_subscription()
	 */
	public function test_set_trial_end_for_subscription() {
		$mock_subscription          = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_set_trial12345';
		$mock_trial_end             = time();

		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_subscription' )
			->with( $mock_wcpay_subscription_id, [ 'trial_end' => $mock_trial_end ] )
			->willReturn( [ 'updated_trial_end' => $mock_trial_end ] );

		PHPUnit_Utils::call_method(
			$this->subscription_service,
			'set_trial_end_for_subscription',
			[ $mock_subscription, $mock_trial_end ]
		);
	}

	/**
	 * Test WC_Payments_Subscription_Service->maybe_attempt_payment_for_subscription()
	 */
	public function test_maybe_attempt_payment_for_subscription() {
		$mock_subscription       = new WC_Subscription();
		$mock_pending_invoice_id = 'wcpay_pending_invoice_idtest123';

		$mock_subscription->update_meta_data( WC_Payments_Invoice_Service_Test::PENDING_INVOICE_ID_KEY, $mock_pending_invoice_id );

		WC_Subscriptions::set_wcs_is_subscription(
			function ( $subscription ) {
				return true;
			}
		);

		$this->mock_api_client->expects( $this->once() )
			->method( 'charge_invoice' )
			->with( $mock_pending_invoice_id )
			->willReturn( [ 'invoice_paid' ] );

		PHPUnit_Utils::call_method(
			$this->subscription_service,
			'maybe_attempt_payment_for_subscription',
			[ $mock_subscription, new WC_Payment_Token_CC() ]
		);
	}

	/**
	 * Test WC_Payments_Subscription_Service->prevent_wcpay_subscription_changes()
	 */
	public function test_prevent_wcpay_subscription_changes() {
		$mock_subscription          = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_prevent_changes12345';

		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( true, 'random_feature', $mock_subscription ) );

		$mock_subscription->payment_method = 'woocommerce_payments';
		$mock_subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );

		$this->assertFalse( $this->subscription_service->prevent_wcpay_subscription_changes( true, 'random_feature', $mock_subscription ) );

		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( false, 'subscriptions', $mock_subscription ) );
		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( true, 'subscriptions', $mock_subscription ) );

		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( false, 'gateway_scheduled_payments', $mock_subscription ) );
		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( true, 'gateway_scheduled_payments', $mock_subscription ) );

		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( false, 'subscriptions', $mock_subscription ) );
		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( true, 'subscriptions', $mock_subscription ) );

		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( false, 'subscription_suspension', $mock_subscription ) );
		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( true, 'subscription_suspension', $mock_subscription ) );

		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( false, 'subscription_reactivation', $mock_subscription ) );
		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( true, 'subscription_reactivation', $mock_subscription ) );

		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( false, 'subscription_cancellation', $mock_subscription ) );
		$this->assertTrue( $this->subscription_service->prevent_wcpay_subscription_changes( true, 'subscription_cancellation', $mock_subscription ) );
	}

	/**
	 * Test WC_Payments_Subscription_Service->has_delayed_payment()
	 */
	public function test_has_delayed_payment() {
		$mock_subscription = new WC_Subscription();
		$order             = WC_Helper_Order::create_order();

		$mock_subscription->set_parent( $order );
		$mock_subscription->trial_end = 0;

		$this->assertFalse( WC_Payments_Subscription_Service::has_delayed_payment( $mock_subscription ) );

		$mock_subscription->trial_end = time() + 1000;

		$this->assertTrue( WC_Payments_Subscription_Service::has_delayed_payment( $mock_subscription ) );
	}

	/**
	 * Test WC_Payments_Subscription_Service->get_wcpay_subscription_id()
	 */
	public function test_get_wcpay_subscription_id() {
		$subscription               = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_subscription_test111';

		$this->assertEquals( '', WC_Payments_Subscription_Service::get_wcpay_subscription_id( $subscription ) );

		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $mock_wcpay_subscription_id );

		$this->assertEquals( $mock_wcpay_subscription_id, WC_Payments_Subscription_Service::get_wcpay_subscription_id( $subscription ) );
	}

	/**
	 * Test WC_Payments_Subscription_Service->set_wcpay_subscription_id()
	 */
	public function test_set_wcpay_subscription_id() {
		$subscription                 = new WC_Subscription();
		$mock_wcpay_subscription_id_1 = 'wcpay_subscription_test1';
		$mock_wcpay_subscription_id_2 = 'wcpay_subscription_test2';

		// Test a subscription with no WCPay subscription ID is set.
		$this->assertNotEquals( $mock_wcpay_subscription_id_1, $subscription->get_meta( self::SUBSCRIPTION_ID_META_KEY ) );

		PHPUnit_Utils::call_method(
			$this->subscription_service,
			'set_wcpay_subscription_id',
			[ $subscription, $mock_wcpay_subscription_id_1 ]
		);

		$this->assertEquals( $mock_wcpay_subscription_id_1, $subscription->get_meta( self::SUBSCRIPTION_ID_META_KEY ) );

		// Test overriding an existing WCPay Subscription ID.
		PHPUnit_Utils::call_method(
			$this->subscription_service,
			'set_wcpay_subscription_id',
			[ $subscription, $mock_wcpay_subscription_id_2 ]
		);

		$this->assertEquals( $mock_wcpay_subscription_id_2, $subscription->get_meta( self::SUBSCRIPTION_ID_META_KEY ) );
	}

	/**
	 * Test WC_Payments_Subscription_Service->is_wcpay_subscription().
	 */
	public function test_is_wcpay_subscription() {
		$subscription = new WC_Subscription();
		$this->assertFalse( WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) );

		$subscription->payment_method = 'woocommerce_payments';
		$this->assertFalse( WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) );

		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, 'test_is_wcpay_subscription' );

		$this->assertTrue( WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) );
	}

	/**
	 * Test WC_Payments_Subscription_Service->update_dates_to_match_wcpay_subscription().
	 */
	public function test_update_dates_to_match_wcpay_subscription() {
		$subscription      = new WC_Subscription();
		$next_payment_time = time() + 10000;
		$wcpay_dates       = [ 'current_period_end' => $next_payment_time ];

		$this->subscription_service->update_dates_to_match_wcpay_subscription( $wcpay_dates, $subscription );

		$this->assertEquals( $next_payment_time, $subscription->get_time( 'next_payment' ) );
	}
}

<?php
/**
 * Class WC_Payment_Gateway_WCPay_Subscriptions_Trait_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payment_Gateway_WCPay_Subscriptions_Trait_Test unit tests.
 */
class WC_Payment_Gateway_WCPay_Subscriptions_Trait_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payment_Gateway_WCPay_Subscriptions_Trait|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_wcpay_subscriptions_trait;

	public function set_up() {
		parent::set_up();

		$this->mock_wcpay_subscriptions_trait = $this->getMockForTrait(
			WC_Payment_Gateway_WCPay_Subscriptions_Trait::class,
			[],
			'',
			true,
			true,
			true,
			[
				'is_subscriptions_enabled',
				'is_subscriptions_plugin_active',
			]
		);

		$this->mock_wcpay_subscriptions_trait->supports = [];
		$this->mock_wcpay_subscriptions_trait->id       = 'woocommerce_payments';
	}

	public function test_maybe_init_subscriptions_with_subscriptions_disabled() {
		$this->mock_wcpay_subscriptions_trait
			->method( 'is_subscriptions_enabled' )
			->willReturn( false );

		$this->mock_wcpay_subscriptions_trait->maybe_init_subscriptions();

		$expected = [];

		$this->assertSame( $expected, $this->mock_wcpay_subscriptions_trait->supports );
	}

	public function test_maybe_init_subscriptions_with_wcs_enabled() {
		$this->mock_wcpay_subscriptions_trait
			->method( 'is_subscriptions_enabled' )
			->willReturn( true );

		$this->mock_wcpay_subscriptions_trait
			->method( 'is_subscriptions_plugin_active' )
			->willReturn( true );

		$this->mock_wcpay_subscriptions_trait->maybe_init_subscriptions();

		$expected = [
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_payment_method_change_admin',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change',
			'subscription_reactivation',
			'subscription_suspension',
			'subscriptions',
			'subscription_amount_changes',
			'subscription_date_changes',
		];

		$this->assertSame( $expected, $this->mock_wcpay_subscriptions_trait->supports );
	}

	public function test_maybe_init_subscriptions_with_stripe_billing_enabled() {
		$this->mock_wcpay_subscriptions_trait
			->method( 'is_subscriptions_enabled' )
			->willReturn( true );

		$this->mock_wcpay_subscriptions_trait
			->method( 'is_subscriptions_plugin_active' )
			->willReturn( false );

		update_option( '_wcpay_feature_stripe_billing', '1' );

		$this->mock_wcpay_subscriptions_trait->maybe_init_subscriptions();

		$expected = [
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_payment_method_change_admin',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change',
			'subscription_reactivation',
			'subscription_suspension',
			'subscriptions',
			'gateway_scheduled_payments',
		];

		$this->assertSame( $expected, $this->mock_wcpay_subscriptions_trait->supports );

		delete_option( '_wcpay_feature_stripe_billing' );
	}

	/**
	 * Test update_renewal_meta_data method excludes WooPayments meta keys.
	 */
	public function test_update_renewal_meta_data_excludes_wcpay_meta_keys() {
		$original_query = 'SELECT * FROM wp_postmeta WHERE post_id = 123';
		$to_order       = 456;
		$from_order     = 789;

		$result = $this->mock_wcpay_subscriptions_trait->update_renewal_meta_data( $original_query, $to_order, $from_order );

		// Check that the query contains NOT IN clause.
		$this->assertStringContainsString( 'NOT IN', $result );

		// Check that specific WooPayments meta keys are excluded.
		$excluded_keys = [
			'_intent_id',
			'_intention_status',
			'_wcpay_intent_currency',
			'_payment_method_id',
			'_charge_id',
			'_wcpay_payment_method_details',
			'_stripe_customer_id',
			'_wcpay_mode',
			'_stripe_mandate_id',
		];

		foreach ( $excluded_keys as $key ) {
			$this->assertStringContainsString( $key, $result, "Meta key {$key} should be excluded from SQL query" );
		}

		// Check that the original query is preserved.
		$this->assertStringContainsString( $original_query, $result );
	}

	/**
	 * Test remove_data_renewal_order method removes WooPayments meta keys.
	 */
	public function test_remove_data_renewal_order_removes_wcpay_meta_keys() {
		$order_data = [
			// WooPayments meta keys that should be removed.
			'_intent_id'                                   => 'pi_1234567890',
			'_intention_status'                            => 'requires_action',
			'_wcpay_intent_currency'                       => 'USD',
			'_payment_method_id'                           => 'pm_1234567890',
			'_charge_id'                                   => 'ch_1234567890',
			'_wcpay_payment_method_details'                => '{"card":{"brand":"visa"}}',
			'_stripe_customer_id'                          => 'cus_1234567890',
			'_wcpay_fraud_meta_box_type'                   => 'review',
			'_wcpay_fraud_outcome_status'                  => 'review',
			'_wcpay_refund_id'                             => 're_1234567890',
			'_wcpay_transaction_fee'                       => '2.50',
			'_wcpay_mode'                                  => 'test',
			'_wcpay_multibanco_entity'                     => '12345',
			'_stripe_mandate_id'                           => 'mandate_1234567890',
			'_wcpay_multi_currency_order_exchange_rate'    => '1.25',
			'_wcpay_multi_currency_order_default_currency' => 'EUR',
			'_new_order_tracking_complete'                 => '1',
			// Non-WooPayments meta keys that should be preserved.
			'_billing_first_name'                          => 'John',
			'_billing_last_name'                           => 'Doe',
			'_order_total'                                 => '29.99',
			'_order_currency'                              => 'USD',
		];

		$result = $this->mock_wcpay_subscriptions_trait->remove_data_renewal_order( $order_data );

		// Check that WooPayments meta keys are removed.
		$wcpay_meta_keys = [
			'_intent_id',
			'_intention_status',
			'_wcpay_intent_currency',
			'_payment_method_id',
			'_charge_id',
			'_wcpay_payment_method_details',
			'_stripe_customer_id',
			'_wcpay_fraud_meta_box_type',
			'_wcpay_fraud_outcome_status',
			'_wcpay_refund_id',
			'_wcpay_transaction_fee',
			'_wcpay_mode',
			'_wcpay_multibanco_entity',
			'_stripe_mandate_id',
			'_wcpay_multi_currency_order_exchange_rate',
			'_wcpay_multi_currency_order_default_currency',
			'_new_order_tracking_complete',
		];

		foreach ( $wcpay_meta_keys as $key ) {
			$this->assertArrayNotHasKey( $key, $result, "WooPayments meta key {$key} should be removed" );
		}

		// Check that non-WooPayments meta keys are preserved.
		$preserved_keys = [
			'_billing_first_name',
			'_billing_last_name',
			'_order_total',
			'_order_currency',
		];

		foreach ( $preserved_keys as $key ) {
			$this->assertArrayHasKey( $key, $result, "Non-WooPayments meta key {$key} should be preserved" );
			$this->assertEquals( $order_data[ $key ], $result[ $key ], "Value for {$key} should be preserved" );
		}
	}

	/**
	 * Test remove_data_renewal_order method with empty array.
	 */
	public function test_remove_data_renewal_order_with_empty_array() {
		$order_data = [];
		$result     = $this->mock_wcpay_subscriptions_trait->remove_data_renewal_order( $order_data );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test exclude_wcpay_meta_from_subscription_copy method with WooPayments payment method.
	 */
	public function test_exclude_wcpay_meta_from_subscription_copy_with_wcpay_payment_method() {
		// Mock order and subscription with WooPayments payment method.
		$order        = $this->createMock( WC_Order::class );
		$subscription = $this->createMock( WC_Subscription::class );

		$order->method( 'get_payment_method' )->willReturn( 'woocommerce_payments' );
		$subscription->method( 'get_payment_method' )->willReturn( 'woocommerce_payments' );

		$payment_meta = [
			// WooPayments meta keys that should be removed.
			'_intent_id'                    => 'pi_1234567890',
			'_wcpay_payment_method_details' => '{"card":{"brand":"visa"}}',
			'_stripe_customer_id'           => 'cus_1234567890',
			'_wcpay_mode'                   => 'test',
			// Non-WooPayments meta keys that should be preserved.
			'_billing_first_name'           => 'John',
			'_billing_last_name'            => 'Doe',
		];

		$result = $this->mock_wcpay_subscriptions_trait->exclude_wcpay_meta_from_subscription_copy( $payment_meta, $order, $subscription );

		// Check that WooPayments meta keys are removed.
		$wcpay_meta_keys = [
			'_intent_id',
			'_wcpay_payment_method_details',
			'_stripe_customer_id',
			'_wcpay_mode',
		];

		foreach ( $wcpay_meta_keys as $key ) {
			$this->assertArrayNotHasKey( $key, $result, "WooPayments meta key {$key} should be removed" );
		}

		// Check that non-WooPayments meta keys are preserved.
		$preserved_keys = [
			'_billing_first_name',
			'_billing_last_name',
		];

		foreach ( $preserved_keys as $key ) {
			$this->assertArrayHasKey( $key, $result, "Non-WooPayments meta key {$key} should be preserved" );
			$this->assertEquals( $payment_meta[ $key ], $result[ $key ], "Value for {$key} should be preserved" );
		}
	}

	/**
	 * Test exclude_wcpay_meta_from_subscription_copy method with non-WooPayments payment method.
	 */
	public function test_exclude_wcpay_meta_from_subscription_copy_with_non_wcpay_payment_method() {
		// Mock order and subscription with non-WooPayments payment method.
		$order        = $this->createMock( WC_Order::class );
		$subscription = $this->createMock( WC_Subscription::class );

		$order->method( 'get_payment_method' )->willReturn( 'paypal' );
		$subscription->method( 'get_payment_method' )->willReturn( 'paypal' );

		$payment_meta = [
			'_intent_id'                    => 'pi_1234567890',
			'_wcpay_payment_method_details' => '{"card":{"brand":"visa"}}',
			'_billing_first_name'           => 'John',
		];

		$result = $this->mock_wcpay_subscriptions_trait->exclude_wcpay_meta_from_subscription_copy( $payment_meta, $order, $subscription );

		// Should return the original payment meta unchanged.
		$this->assertEquals( $payment_meta, $result );
	}

	/**
	 * Test exclude_wcpay_meta_from_subscription_copy method with mixed payment methods.
	 */
	public function test_exclude_wcpay_meta_from_subscription_copy_with_mixed_payment_methods() {
		// Mock order and subscription with different payment methods.
		$order        = $this->createMock( WC_Order::class );
		$subscription = $this->createMock( WC_Subscription::class );

		$order->method( 'get_payment_method' )->willReturn( 'woocommerce_payments' );
		$subscription->method( 'get_payment_method' )->willReturn( 'paypal' );

		$payment_meta = [
			'_intent_id'                    => 'pi_1234567890',
			'_wcpay_payment_method_details' => '{"card":{"brand":"visa"}}',
			'_billing_first_name'           => 'John',
		];

		$result = $this->mock_wcpay_subscriptions_trait->exclude_wcpay_meta_from_subscription_copy( $payment_meta, $order, $subscription );

		// Should return the original payment meta unchanged.
		$this->assertEquals( $payment_meta, $result );
	}

	/**
	 * Test exclude_wcpay_meta_from_subscription_copy method with non-array payment meta.
	 */
	public function test_exclude_wcpay_meta_from_subscription_copy_with_non_array_payment_meta() {
		$order        = $this->createMock( WC_Order::class );
		$subscription = $this->createMock( WC_Subscription::class );

		$order->method( 'get_payment_method' )->willReturn( 'woocommerce_payments' );
		$subscription->method( 'get_payment_method' )->willReturn( 'woocommerce_payments' );

		$payment_meta = 'not_an_array';

		$result = $this->mock_wcpay_subscriptions_trait->exclude_wcpay_meta_from_subscription_copy( $payment_meta, $order, $subscription );

		// Should return the original payment meta unchanged.
		$this->assertEquals( $payment_meta, $result );
	}

	/**
	 * Test exclude_wcpay_meta_from_subscription_copy method with null payment meta.
	 */
	public function test_exclude_wcpay_meta_from_subscription_copy_with_null_payment_meta() {
		$order        = $this->createMock( WC_Order::class );
		$subscription = $this->createMock( WC_Subscription::class );

		$order->method( 'get_payment_method' )->willReturn( 'woocommerce_payments' );
		$subscription->method( 'get_payment_method' )->willReturn( 'woocommerce_payments' );

		$payment_meta = null;

		$result = $this->mock_wcpay_subscriptions_trait->exclude_wcpay_meta_from_subscription_copy( $payment_meta, $order, $subscription );

		// Should return the original payment meta unchanged.
		$this->assertEquals( $payment_meta, $result );
	}

	/**
	 * Test get_excluded_meta_keys_for_subscription_copying method returns expected keys.
	 */
	public function test_get_excluded_meta_keys_for_subscription_copying_returns_expected_keys() {
		// Use reflection to access the private method.
		$reflection = new ReflectionClass( $this->mock_wcpay_subscriptions_trait );
		$method     = $reflection->getMethod( 'get_excluded_meta_keys_for_subscription_copying' );
		$method->setAccessible( true );

		$excluded_keys = $method->invoke( $this->mock_wcpay_subscriptions_trait );

		// Check that it returns an array.
		$this->assertIsArray( $excluded_keys );

		// Check that it contains expected WooPayments meta keys.
		$expected_keys = [
			'_new_order_tracking_complete',
			'_intent_id',
			'_intention_status',
			'_wcpay_intent_currency',
			'_payment_method_id',
			'_charge_id',
			'_charge_risk_level',
			'_wcpay_payment_method_details',
			'_wcpay_payment_transaction_id',
			'_stripe_customer_id',
			'_wcpay_fraud_meta_box_type',
			'_wcpay_fraud_outcome_status',
			'_wcpay_refund_id',
			'_wcpay_refund_transaction_id',
			'_wcpay_refund_status',
			'_wcpay_transaction_fee',
			'_wcpay_mode',
			'_wcpay_multibanco_entity',
			'_wcpay_multibanco_reference',
			'_wcpay_multibanco_expiry',
			'_wcpay_multibanco_url',
			'_stripe_mandate_id',
			'_wcpay_multi_currency_order_exchange_rate',
			'_wcpay_multi_currency_order_default_currency',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertContains( $key, $excluded_keys, "Expected meta key {$key} should be in excluded keys list" );
		}

		// Check that it doesn't contain non-WooPayments meta keys.
		$non_wcpay_keys = [
			'_billing_first_name',
			'_billing_last_name',
			'_order_total',
			'_order_currency',
		];

		foreach ( $non_wcpay_keys as $key ) {
			$this->assertNotContains( $key, $excluded_keys, "Non-WooPayments meta key {$key} should not be in excluded keys list" );
		}
	}
}

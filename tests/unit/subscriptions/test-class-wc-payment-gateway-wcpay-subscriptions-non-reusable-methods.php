<?php
/**
 * Class WC_Payment_Gateway_WCPay_Subscriptions_Non_Reusable_Methods_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Payment_Method;

/**
 * Unit tests for non-reusable payment method handling in WCPay subscriptions trait.
 */
class WC_Payment_Gateway_WCPay_Subscriptions_Non_Reusable_Methods_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock gateway using the subscriptions trait.
	 *
	 * @var WC_Payment_Gateway_WCPay_Subscriptions_Trait&PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_gateway;

	public function set_up() {
		parent::set_up();

		// Create a mock that uses the WC_Payment_Gateway_WCPay_Subscriptions_Trait.
		$this->mock_gateway = $this->getMockForTrait(
			WC_Payment_Gateway_WCPay_Subscriptions_Trait::class,
			[],
			'',
			true,
			true,
			true,
			[]
		);
	}

	/**
	 * Test that subscriptions created with split UPE gateways (non-reusable) are forced to manual.
	 */
	public function test_maybe_force_subscription_to_manual_with_split_upe_gateway() {
		// Arrange: Create a subscription with a split UPE gateway (iDEAL).
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'woocommerce_payments_ideal' );
		$subscription->set_requires_manual_renewal( false ); // Start as automatic.
		$subscription->save();

		// Act: Call the method.
		$this->mock_gateway->maybe_force_subscription_to_manual( $subscription );

		// Assert: Subscription should be forced to manual.
		$this->assertTrue( $subscription->is_manual(), 'Subscription should be manual for non-reusable payment method' );
		$this->assertEquals( 'woocommerce_payments_ideal', $subscription->get_meta( '_wcpay_original_payment_method_id', true ), 'Original payment method ID should be stored' );
		$this->assertEquals( 'woocommerce_payments_ideal', $subscription->get_payment_method(), 'Payment method should remain as split UPE gateway' );
	}

	/**
	 * Test that subscriptions created with base gateway (card) are NOT forced to manual.
	 */
	public function test_maybe_force_subscription_to_manual_with_base_gateway() {
		// Arrange: Create a subscription with the base gateway (card).
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'woocommerce_payments' );
		$subscription->set_requires_manual_renewal( false ); // Automatic.
		$subscription->save();

		// Act: Call the method.
		$this->mock_gateway->maybe_force_subscription_to_manual( $subscription );

		// Assert: Subscription should remain automatic (not forced to manual).
		$this->assertFalse( $subscription->is_manual(), 'Subscription should remain automatic for reusable payment method (card)' );
		$this->assertEmpty( $subscription->get_meta( '_wcpay_original_payment_method_id', true ), 'No original payment method ID should be stored for card' );
	}

	/**
	 * Test that non-WCPay subscriptions are not affected.
	 */
	public function test_maybe_force_subscription_to_manual_ignores_non_wcpay_gateway() {
		// Arrange: Create a subscription with a different payment gateway.
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'stripe' );
		$subscription->set_requires_manual_renewal( false );
		$subscription->save();

		// Act: Call the method.
		$this->mock_gateway->maybe_force_subscription_to_manual( $subscription );

		// Assert: Subscription should not be affected.
		$this->assertFalse( $subscription->is_manual(), 'Non-WCPay subscription should not be affected' );
		$this->assertEmpty( $subscription->get_meta( '_wcpay_original_payment_method_id', true ) );
	}

	/**
	 * Test that subscription is properly configured when forcing to manual (verifies side effects).
	 */
	public function test_maybe_force_subscription_to_manual_configures_subscription() {
		// Arrange: Create a subscription with a split UPE gateway.
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'woocommerce_payments_bancontact' );
		$subscription->set_requires_manual_renewal( false ); // Start as automatic.
		$subscription->save();

		// Act: Call the method.
		$this->mock_gateway->maybe_force_subscription_to_manual( $subscription );

		// Assert: Verify the subscription was properly configured.
		$this->assertTrue( $subscription->is_manual(), 'Subscription should be set to manual' );
		$this->assertEquals( 'woocommerce_payments_bancontact', $subscription->get_meta( '_wcpay_original_payment_method_id', true ), 'Original payment method ID should be stored' );
		$this->assertEquals( 'woocommerce_payments_bancontact', $subscription->get_payment_method(), 'Payment method should remain unchanged' );
	}

	/**
	 * Test hiding "Change payment" button for manual subscriptions with non-reusable payment methods.
	 */
	public function test_maybe_hide_change_payment_for_manual_subscriptions_hides_button() {
		// Arrange: Create a manual subscription with non-reusable payment method.
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( true );
		$subscription->update_meta_data( '_wcpay_original_payment_method_id', 'woocommerce_payments_ideal' );
		$subscription->save();

		$actions = [
			'change_payment_method' => [
				'url'  => 'https://example.com/change-payment',
				'name' => 'Change payment',
			],
			'view'                  => [
				'url'  => 'https://example.com/view',
				'name' => 'View',
			],
		];

		// Act: Call the method.
		$result = $this->mock_gateway->maybe_hide_change_payment_for_manual_subscriptions( $actions, $subscription );

		// Assert: "Change payment" button should be removed.
		$this->assertArrayNotHasKey( 'change_payment_method', $result, '"Change payment" action should be removed' );
		$this->assertArrayHasKey( 'view', $result, 'Other actions should remain' );
	}

	/**
	 * Test that "Change payment" button is shown for manual subscriptions without non-reusable payment method meta.
	 */
	public function test_maybe_hide_change_payment_for_manual_subscriptions_shows_button_without_meta() {
		// Arrange: Create a manual subscription WITHOUT non-reusable payment method meta.
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( true );
		$subscription->save();

		$actions = [
			'change_payment_method' => [
				'url'  => 'https://example.com/change-payment',
				'name' => 'Change payment',
			],
		];

		// Act: Call the method.
		$result = $this->mock_gateway->maybe_hide_change_payment_for_manual_subscriptions( $actions, $subscription );

		// Assert: "Change payment" button should remain.
		$this->assertArrayHasKey( 'change_payment_method', $result, '"Change payment" action should remain without non-reusable payment method meta' );
	}

	/**
	 * Test that "Change payment" button is shown for automatic subscriptions.
	 */
	public function test_maybe_hide_change_payment_for_automatic_subscriptions() {
		// Arrange: Create an automatic subscription with non-reusable payment method meta.
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( false );
		$subscription->update_meta_data( '_wcpay_original_payment_method_id', 'woocommerce_payments_ideal' );
		$subscription->save();

		$actions = [
			'change_payment_method' => [
				'url'  => 'https://example.com/change-payment',
				'name' => 'Change payment',
			],
		];

		// Act: Call the method.
		$result = $this->mock_gateway->maybe_hide_change_payment_for_manual_subscriptions( $actions, $subscription );

		// Assert: "Change payment" button should remain for automatic subscriptions.
		$this->assertArrayHasKey( 'change_payment_method', $result, '"Change payment" action should remain for automatic subscriptions' );
	}

	/**
	 * Test that Amazon Pay ECE subscriptions get switched to the correct gateway.
	 */
	public function test_maybe_switch_subscription_to_amazon_pay_gateway() {
		// Arrange: Create a parent order with Amazon Pay ECE meta.
		$parent_order = WC_Helper_Order::create_order();
		$parent_order->update_meta_data( '_wcpay_express_checkout_payment_method', Payment_Method::AMAZON_PAY );
		$parent_order->save();

		// Create a subscription with the base gateway (as it would be initially set by ECE).
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'woocommerce_payments' );
		$subscription->set_parent( $parent_order );
		$subscription->save();

		// Act: Call the gateway switch method (runs at priority 9).
		$this->mock_gateway->maybe_switch_subscription_to_amazon_pay_gateway( $subscription );

		// Assert: Subscription should be updated to Amazon Pay split gateway.
		$expected_gateway_id = WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . \WCPay\PaymentMethods\Configs\Definitions\AmazonPayDefinition::get_id();
		$this->assertEquals( $expected_gateway_id, $subscription->get_payment_method(), 'Payment method should be updated to Amazon Pay split gateway' );
	}

	public function test_amazon_pay_ece_subscription_flow() {
		// Arrange: Create a parent order with Amazon Pay ECE meta.
		$parent_order = WC_Helper_Order::create_order();
		$parent_order->update_meta_data( '_wcpay_express_checkout_payment_method', Payment_Method::AMAZON_PAY );
		$parent_order->save();

		// Create a subscription with the base gateway (as it would be initially set by ECE).
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'woocommerce_payments' );
		$subscription->set_requires_manual_renewal( false ); // Start as automatic.
		$subscription->set_parent( $parent_order );
		$subscription->save();

		// Act: Call both methods in the order they would be called by hooks.
		$this->mock_gateway->maybe_switch_subscription_to_amazon_pay_gateway( $subscription );
		$this->mock_gateway->maybe_force_subscription_to_manual( $subscription );

		// Assert: Subscription should be on Amazon Pay gateway and remain automatic.
		$this->assertEquals( 'woocommerce_payments_amazon_pay', $subscription->get_payment_method(), 'Payment method should be Amazon Pay split gateway' );
		$this->assertFalse( $subscription->is_manual(), 'Subscription should remain automatic for Amazon Pay (reusable payment method)' );
		$this->assertEmpty( $subscription->get_meta( '_wcpay_original_payment_method_id', true ), 'No original payment method ID should be stored for Amazon Pay' );
	}

	/**
	 * Test that subscriptions created via Express Checkout with Google Pay
	 * remain on the base gateway and are NOT forced to manual.
	 */
	public function test_maybe_force_subscription_to_manual_with_google_pay_ece() {
		// Arrange: Create a parent order with Google Pay ECE meta.
		$parent_order = WC_Helper_Order::create_order();
		$parent_order->update_meta_data( '_wcpay_express_checkout_payment_method', 'google_pay' );
		$parent_order->save();

		// Create a subscription with the base gateway.
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'woocommerce_payments' );
		$subscription->set_requires_manual_renewal( false ); // Automatic.
		$subscription->set_parent( $parent_order );
		$subscription->save();

		// Act: Call the method.
		$this->mock_gateway->maybe_force_subscription_to_manual( $subscription );

		// Assert: Subscription should remain on base gateway (Google Pay uses card) and NOT forced to manual.
		$this->assertEquals( 'woocommerce_payments', $subscription->get_payment_method(), 'Payment method should remain as base gateway for Google Pay' );
		$this->assertFalse( $subscription->is_manual(), 'Subscription should remain automatic for Google Pay' );
	}
}

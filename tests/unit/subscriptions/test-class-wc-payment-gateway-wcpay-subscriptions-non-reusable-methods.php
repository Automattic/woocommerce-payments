<?php
/**
 * Class WC_Payment_Gateway_WCPay_Subscriptions_Non_Reusable_Methods_Test
 *
 * @package WooCommerce\Payments\Tests
 */

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
}

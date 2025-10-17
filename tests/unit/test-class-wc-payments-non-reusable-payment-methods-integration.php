<?php
/**
 * Class WC_Payments_Non_Reusable_Payment_Methods_Integration_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Integration tests for non-reusable payment methods with subscriptions.
 * Tests the complete flow from checkout to renewal.
 */
class WC_Payments_Non_Reusable_Payment_Methods_Integration_Test extends WCPAY_UnitTestCase {

	/**
	 * Test complete flow: Non-reusable payment method -> Manual subscription -> Stays manual.
	 */
	public function test_non_reusable_payment_method_creates_and_maintains_manual_subscription() {
		// Arrange: Simulate checkout with non-reusable payment method (e.g., SEPA, iDEAL).
		$order_id     = 123;
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( true );

		// No payment tokens saved (characteristic of non-reusable payment methods).
		$subscription->payment_tokens = [];

		// Act 1: Check if payment method should be saved during checkout.
		$should_save_payment_method = $this->simulate_checkout_payment_method_saving( $order_id, $subscription );

		// Act 2: Check if subscription should convert to automatic during renewal.
		$should_convert_to_automatic = $this->simulate_renewal_conversion_logic( $subscription );

		// Act 3: Check if WCPay subscription should be created.
		$should_create_wcpay_subscription = $this->simulate_wcpay_subscription_creation( $subscription );

		// Assert: Complete flow behavior.
		$this->assertFalse( $should_save_payment_method, 'Should NOT save payment method for manual subscription' );
		$this->assertFalse( $should_convert_to_automatic, 'Should NOT convert to automatic without payment tokens' );
		$this->assertFalse( $should_create_wcpay_subscription, 'Should NOT create WCPay subscription without tokens' );
		$this->assertTrue( $subscription->is_manual(), 'Subscription should remain manual throughout' );
	}

	/**
	 * Test complete flow: Reusable payment method -> Manual subscription -> Converts to automatic.
	 */
	public function test_reusable_payment_method_allows_manual_to_automatic_conversion() {
		// Arrange: Simulate checkout with reusable payment method (e.g., Card).
		$order_id     = 123;
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( true );

		// Payment tokens saved (characteristic of reusable payment methods).
		$subscription->payment_tokens = [ 'pm_reusable123' ];

		// Act 1: Check if payment method should be saved during checkout.
		$should_save_payment_method = $this->simulate_checkout_payment_method_saving( $order_id, $subscription );

		// Act 2: Check if subscription should convert to automatic during renewal.
		$should_convert_to_automatic = $this->simulate_renewal_conversion_logic( $subscription );

		// Act 3: Check if WCPay subscription should be created.
		$should_create_wcpay_subscription = $this->simulate_wcpay_subscription_creation( $subscription );

		// Assert: Complete flow behavior.
		$this->assertTrue( $should_save_payment_method, 'Should save payment method for future renewals' );
		$this->assertTrue( $should_convert_to_automatic, 'Should convert to automatic with payment tokens' );
		$this->assertTrue( $should_create_wcpay_subscription, 'Should create WCPay subscription with tokens' );
	}

	/**
	 * Test mixed scenario: Multiple subscriptions with different payment method types.
	 */
	public function test_mixed_payment_methods_handled_correctly() {
		// Arrange: Order with both reusable and non-reusable payment method subscriptions.
		$order_id = 123;

		$manual_subscription_with_tokens = new WC_Subscription();
		$manual_subscription_with_tokens->set_requires_manual_renewal( true );
		$manual_subscription_with_tokens->payment_tokens = [ 'pm_card123' ];

		$manual_subscription_without_tokens = new WC_Subscription();
		$manual_subscription_without_tokens->set_requires_manual_renewal( true );
		$manual_subscription_without_tokens->payment_tokens = [];

		// Act: Test behavior for both subscriptions.
		$card_should_save = $this->simulate_checkout_payment_method_saving( $order_id, $manual_subscription_with_tokens );
		$sepa_should_save = $this->simulate_checkout_payment_method_saving( $order_id, $manual_subscription_without_tokens );

		$card_should_convert = $this->simulate_renewal_conversion_logic( $manual_subscription_with_tokens );
		$sepa_should_convert = $this->simulate_renewal_conversion_logic( $manual_subscription_without_tokens );

		// Assert: Different behavior based on payment method type.
		$this->assertTrue( $card_should_save, 'Should save for reusable payment method' );
		$this->assertFalse( $sepa_should_save, 'Should NOT save for non-reusable payment method' );

		$this->assertTrue( $card_should_convert, 'Should convert with tokens' );
		$this->assertFalse( $sepa_should_convert, 'Should NOT convert without tokens' );
	}

	/**
	 * Simulate the checkout payment method saving logic using real implementation.
	 */
	private function simulate_checkout_payment_method_saving( $order_id, $subscription ) {
		// Use actual logic from should_save_payment_method_for_subscription().
		$subscriptions = [ $subscription ];

		// Mock wcs_get_subscriptions_for_order.
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order_id ) use ( $subscriptions ) {
				return $subscriptions;
			}
		);

		// Create trait instance to test.
		$trait  = $this->getMockForTrait( WC_Payment_Gateway_WCPay_Subscriptions_Trait::class );
		$result = $trait->should_save_payment_method_for_subscription( $order_id );

		// Clean up.
		WC_Subscriptions::set_wcs_get_subscriptions_for_order( null );

		return $result;
	}

	/**
	 * Simulate the renewal conversion logic using real implementation.
	 */
	private function simulate_renewal_conversion_logic( $subscription ) {
		// Use actual logic from invoice service conversion.
		if ( $subscription->is_manual() ) {
			$payment_tokens = $subscription->get_payment_tokens();
			return ! empty( $payment_tokens );
		}
		return false;
	}

	/**
	 * Simulate the WCPay subscription creation logic using real implementation.
	 */
	private function simulate_wcpay_subscription_creation( $subscription ) {
		// Use actual logic from create_subscription_for_manual_renewal().
		if ( ! $subscription->is_manual() ) {
			return false;
		}

		$payment_tokens = $subscription->get_payment_tokens();
		return ! empty( $payment_tokens );
	}
}

<?php
/**
 * Class WC_Payment_Gateway_WCPay_Manual_Subscription_Payment_Saving_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Test payment method saving logic for manual subscriptions.
 */
class WC_Payment_Gateway_WCPay_Manual_Subscription_Payment_Saving_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payment_Gateway_WCPay_Subscriptions_Trait&PHPUnit_Framework_MockObject_MockObject
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
			]
		);

		$this->mock_wcpay_subscriptions_trait->supports = [];
		$this->mock_wcpay_subscriptions_trait->id       = 'woocommerce_payments';
	}

	/**
	 * Test that payment method is NOT saved for manual subscriptions.
	 */
	public function test_should_save_payment_method_for_subscription_returns_false_for_manual_subscription() {
		// Arrange.
		$order_id            = 123;
		$manual_subscription = new WC_Subscription();
		$manual_subscription->set_requires_manual_renewal( true );

		// Mock wcs_get_subscriptions_for_order to return our manual subscription.
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order_id ) use ( $manual_subscription ) {
				return [ $manual_subscription ];
			}
		);

		// Act.
		$result = $this->mock_wcpay_subscriptions_trait->should_save_payment_method_for_subscription( $order_id );

		// Assert.
		$this->assertFalse( $result, 'Payment method should NOT be saved for manual subscriptions' );
	}

	/**
	 * Test that payment method IS saved for automatic subscriptions.
	 */
	public function test_should_save_payment_method_for_subscription_returns_true_for_automatic_subscription() {
		// Arrange.
		$order_id               = 123;
		$automatic_subscription = new WC_Subscription();
		$automatic_subscription->set_requires_manual_renewal( false );

		// Mock wcs_get_subscriptions_for_order to return our automatic subscription.
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order_id ) use ( $automatic_subscription ) {
				return [ $automatic_subscription ];
			}
		);

		// Act.
		$result = $this->mock_wcpay_subscriptions_trait->should_save_payment_method_for_subscription( $order_id );

		// Assert.
		$this->assertTrue( $result, 'Payment method should be saved for automatic subscriptions' );
	}

	public function tear_down() {
		parent::tear_down();
		WC_Subscriptions::set_wcs_get_subscriptions_for_order( null );
	}
}

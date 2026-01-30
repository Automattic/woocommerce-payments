<?php
/**
 * Class WC_Payment_Gateway_WCPay_Subscriptions_Trait_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\PaymentMethods\Configs\Definitions\AmazonPayDefinition;

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
	 * Test that Amazon Pay subscription hooks are registered.
	 */
	public function test_amazon_pay_subscription_hooks_are_registered() {
		$amazon_pay_gateway_id = WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . AmazonPayDefinition::get_id();

		// Verify that the scheduled payment hook is registered for Amazon Pay gateway.
		$this->assertNotFalse(
			has_action( 'woocommerce_scheduled_subscription_payment_' . $amazon_pay_gateway_id ),
			'Amazon Pay scheduled subscription payment hook should be registered'
		);

		// Verify that the failing payment method hook is registered for Amazon Pay gateway.
		$this->assertNotFalse(
			has_action( 'woocommerce_subscription_failing_payment_method_updated_' . $amazon_pay_gateway_id ),
			'Amazon Pay failing payment method hook should be registered'
		);
	}
}

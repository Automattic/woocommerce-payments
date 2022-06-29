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

	public function test_maybe_init_subscriptions_with_wcs_disabled() {
		$this->mock_wcpay_subscriptions_trait
			->method( 'is_subscriptions_enabled' )
			->willReturn( true );

		$this->mock_wcpay_subscriptions_trait
			->method( 'is_subscriptions_plugin_active' )
			->willReturn( false );

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
	}
}

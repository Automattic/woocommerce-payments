<?php
/**
 * Class WC_Payment_Gateway_WCPay_Subscriptions_Trait_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\PaymentMethods\Configs\Definitions\AmazonPayDefinition;
use WCPay\Payment_Information;

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

	public function tear_down() {
		unset( $_GET['change_payment_method'] );
		WC_Subscriptions::set_wcs_order_contains_subscription( null );
		WC_Subscriptions::set_wcs_get_subscriptions_for_order( null );
		WC_Subscriptions::set_wcs_is_subscription( null );
		WC_Subscriptions::wcs_order_contains_renewal( null );
		WC_Subscriptions::set_wcs_is_manual_renewal_required( null );

		parent::tear_down();
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

	public function test_maybe_update_subscription_payment_method_updates_single_subscription() {
		$order         = WC_Helper_Order::create_order();
		$payment_token = WC_Helper_Token::create_token( 'pm_mock' );

		$this->mock_wcpay_subscriptions_trait
			->method( 'get_payment_token' )
			->with( $order )
			->willReturn( $payment_token );

		WC_Subscriptions_Change_Payment_Gateway::$update_payment_method_calls = [];
		WC_Subscriptions_Change_Payment_Gateway::$update_all_calls            = [];
		WC_Subscriptions_Change_Payment_Gateway::$will_update_all_return      = false;

		wc_clear_notices();
		$this->mock_wcpay_subscriptions_trait->maybe_update_subscription_payment_method( $order );

		$this->assertCount( 1, WC_Subscriptions_Change_Payment_Gateway::$update_payment_method_calls );
		$this->assertSame( $payment_token->get_gateway_id(), WC_Subscriptions_Change_Payment_Gateway::$update_payment_method_calls[0]['gateway_id'] );
		$this->assertCount( 0, WC_Subscriptions_Change_Payment_Gateway::$update_all_calls );
		$this->assertContains( 'Payment method updated.', array_column( wc_get_notices( 'success' ), 'notice' ) );
	}

	public function test_maybe_update_subscription_payment_method_updates_all_subscriptions() {
		$order         = WC_Helper_Order::create_order();
		$payment_token = WC_Helper_Token::create_token( 'pm_mock' );

		$this->mock_wcpay_subscriptions_trait
			->method( 'get_payment_token' )
			->with( $order )
			->willReturn( $payment_token );

		WC_Subscriptions_Change_Payment_Gateway::$update_payment_method_calls = [];
		WC_Subscriptions_Change_Payment_Gateway::$update_all_calls            = [];
		WC_Subscriptions_Change_Payment_Gateway::$will_update_all_return      = true;
		WC_Subscriptions_Change_Payment_Gateway::$update_all_return           = true;

		wc_clear_notices();
		$this->mock_wcpay_subscriptions_trait->maybe_update_subscription_payment_method( $order );

		$this->assertCount( 1, WC_Subscriptions_Change_Payment_Gateway::$update_all_calls );
		$this->assertSame( $payment_token->get_gateway_id(), WC_Subscriptions_Change_Payment_Gateway::$update_all_calls[0]['gateway_id'] );
		$this->assertContains( 'Payment method updated for all your current subscriptions.', array_column( wc_get_notices( 'success' ), 'notice' ) );
	}

	public function test_prepare_payment_information_saves_the_payment_method_for_an_automatic_subscription() {
		$this->arrange_recurring_payment( [ $this->create_subscription( false ) ] );

		$payment_information = $this->prepare_subscription_payment_information();

		$this->assertTrue( $payment_information->should_save_payment_method_to_store() );
	}

	public function test_prepare_payment_information_does_not_save_the_payment_method_when_the_subscription_renews_manually() {
		$this->arrange_recurring_payment( [ $this->create_subscription( true ) ] );

		$payment_information = $this->prepare_subscription_payment_information();

		$this->assertFalse( $payment_information->should_save_payment_method_to_store() );
	}

	public function test_prepare_payment_information_saves_the_payment_method_when_only_one_subscription_renews_automatically() {
		$this->arrange_recurring_payment(
			[
				$this->create_subscription( true ),
				$this->create_subscription( false ),
			]
		);

		$payment_information = $this->prepare_subscription_payment_information();

		$this->assertTrue( $payment_information->should_save_payment_method_to_store() );
	}

	public function test_prepare_payment_information_saves_the_payment_method_when_no_subscription_can_be_read() {
		$this->arrange_recurring_payment( [] );

		$payment_information = $this->prepare_subscription_payment_information();

		$this->assertTrue( $payment_information->should_save_payment_method_to_store() );
	}

	/**
	 * Storing a card is the point of the change payment method flow, so it saves even on a store
	 * where automatic payments are turned off and the subscription itself renews manually.
	 */
	public function test_prepare_payment_information_saves_the_payment_method_when_changing_it_for_a_subscription() {
		$this->arrange_recurring_payment( [ $this->create_subscription( true ) ] );
		$this->arrange_store_requiring_manual_renewal();
		WC_Subscriptions::set_wcs_is_subscription(
			function () {
				return true;
			}
		);
		$_GET['change_payment_method'] = 123;

		$payment_information = $this->prepare_subscription_payment_information();

		$this->assertTrue( $payment_information->should_save_payment_method_to_store() );
		$this->assertTrue( $payment_information->is_changing_payment_method_for_subscription() );
	}

	/**
	 * A renewal order paid through the checkout must still vault the card, otherwise the automatic
	 * subscription keeps the payment method that failed. See WOOPMNT-2882.
	 */
	public function test_prepare_payment_information_saves_the_payment_method_for_a_renewal_of_an_automatic_subscription() {
		$this->mock_wcpay_subscriptions_trait
			->method( 'is_subscriptions_enabled' )
			->willReturn( true );
		$this->arrange_store_requiring_manual_renewal();

		// A renewal order is not a subscription order, it only relates to one.
		WC_Subscriptions::set_wcs_order_contains_subscription(
			function () {
				return false;
			}
		);
		WC_Subscriptions::wcs_order_contains_renewal(
			function () {
				return true;
			}
		);
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function () {
				return [ $this->create_subscription( false ) ];
			}
		);

		$payment_information = $this->prepare_subscription_payment_information();

		$this->assertTrue( $payment_information->should_save_payment_method_to_store() );
	}

	/**
	 * Builds a subscription with the given renewal preference.
	 *
	 * @param bool $requires_manual_renewal Whether the subscription renews manually.
	 * @return WC_Subscription
	 */
	private function create_subscription( bool $requires_manual_renewal ) {
		$subscription = new WC_Subscription();
		$subscription->set_requires_manual_renewal( $requires_manual_renewal );

		return $subscription;
	}

	/**
	 * Turns off automatic payments store-wide, the setting that must not decide this on its own.
	 */
	private function arrange_store_requiring_manual_renewal() {
		WC_Subscriptions::set_wcs_is_manual_renewal_required(
			function () {
				return true;
			}
		);
	}

	/**
	 * Stubs the subscription functions so the payment is treated as recurring.
	 *
	 * @param WC_Subscription[] $subscriptions The subscriptions the paid order relates to.
	 */
	private function arrange_recurring_payment( array $subscriptions ) {
		$this->mock_wcpay_subscriptions_trait
			->method( 'is_subscriptions_enabled' )
			->willReturn( true );

		WC_Subscriptions::set_wcs_order_contains_subscription(
			function () {
				return true;
			}
		);
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function () use ( $subscriptions ) {
				return $subscriptions;
			}
		);
	}

	/**
	 * Calls the protected method under test with a fresh payment information object.
	 *
	 * @return Payment_Information
	 */
	private function prepare_subscription_payment_information() {
		$method = new ReflectionMethod(
			get_class( $this->mock_wcpay_subscriptions_trait ),
			'maybe_prepare_subscription_payment_information'
		);
		$method->setAccessible( true );

		return $method->invoke(
			$this->mock_wcpay_subscriptions_trait,
			new Payment_Information( 'pm_mock' ),
			123
		);
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

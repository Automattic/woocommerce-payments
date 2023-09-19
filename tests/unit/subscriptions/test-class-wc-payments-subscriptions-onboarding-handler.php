<?php
/**
 * Class WC_Payments_Subscriptions_Onboarding_Handler_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Subscriptions_Onboarding_Handler unit tests.
 */
class WC_Payments_Subscriptions_Onboarding_Handler_Test extends WCPAY_UnitTestCase {
	public function test_payments_subscriptions_onboarding_handler_product_save() {
		$account_mock = $this->createMock( WC_Payments_Account::class );
		$account_mock->method( 'is_stripe_connected' )->willReturn( true );

		$mock_subscription_product = new WC_Subscriptions_Product();

		$option = update_option(
			WC_Payments_Subscriptions_Onboarding_Handler::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS,
			[ $mock_subscription_product ]
		);

		$onboarding_handler = new WC_Payments_Subscriptions_Onboarding_Handler( $account_mock );
		$onboarding_handler->account_data_refreshed();

		$option = get_option( WC_Payments_Subscriptions_Onboarding_Handler::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS, [] );
		$this->assertEquals( [], $option );
	}

	public function test_payments_subscriptions_onboarding_handler_product_save_stripe_is_not_connected() {
		$account_mock = $this->createMock( WC_Payments_Account::class );
		$account_mock->method( 'is_stripe_connected' )->willReturn( false );

		$mock_subscription_product = new WC_Subscriptions_Product();

		$option = update_option(
			WC_Payments_Subscriptions_Onboarding_Handler::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS,
			[ $mock_subscription_product ]
		);

		$onboarding_handler = new WC_Payments_Subscriptions_Onboarding_Handler( $account_mock );
		$onboarding_handler->account_data_refreshed();

		$option = get_option( WC_Payments_Subscriptions_Onboarding_Handler::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS, [] );
		$this->assertEquals( [ $mock_subscription_product ], $option );

		delete_option( WC_Payments_Subscriptions_Onboarding_Handler::WCPAY_SUBSCRIPTION_AUTO_PUBLISH_PRODUCTS );
	}
}

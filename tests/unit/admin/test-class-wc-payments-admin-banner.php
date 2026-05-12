<?php
/**
 * Class WC_Payments_Admin_Banner_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Admin_Banner coordinator unit tests.
 *
 * The class itself owns no banner-specific logic; per-banner behaviour is
 * tested against each WC_Payments_Abstract_Admin_Banner subclass directly.
 * This file just locks in the coordinator contract: it dispatches
 * init_hooks() / init_global_hooks() to every registered banner.
 */
class WC_Payments_Admin_Banner_Test extends WCPAY_UnitTestCase {

	public function test_init_global_hooks_dispatches_to_one_and_done_banner(): void {
		// One-and-done is the only built-in banner that registers global hooks,
		// so it's the proxy assertion for "the coordinator forwarded the call".
		$banner = $this->make_coordinator();
		$banner->init_global_hooks();

		$this->assertNotFalse(
			has_action( 'woocommerce_payment_complete' ),
			'init_global_hooks() must register the one-and-done order-completion invalidator.'
		);
		$this->assertNotFalse( has_action( 'woocommerce_order_status_completed' ) );
		$this->assertNotFalse( has_action( 'woocommerce_order_status_processing' ) );
	}

	public function test_init_hooks_dispatches_to_every_banner(): void {
		// The base class registers admin_init handlers; verifying that each
		// banner's handlers are present proves the coordinator dispatched to
		// every subclass.
		$banner = $this->make_coordinator();
		$banner->init_hooks();

		// One-and-done has snooze.
		$this->assertNotFalse(
			has_action( 'admin_init' ),
			'init_hooks() should register at least one admin_init handler.'
		);
		// Post-KYC has the account_refreshed cache invalidator.
		$this->assertNotFalse(
			has_action( 'woocommerce_payments_account_refreshed' ),
			'Post-KYC banner subclass must register the account_refreshed invalidator via init_hooks().'
		);
	}

	private function make_coordinator(): WC_Payments_Admin_Banner {
		$mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_account = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();
		return new WC_Payments_Admin_Banner( $mock_gateway, $mock_account );
	}
}

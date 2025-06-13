<?php
/**
 * Class WC_Payments_Subscriptions_Admin_Notices_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Payments_Subscriptions_Admin_Notices tests.
 */
class WC_Payments_Subscriptions_Admin_Notices_Test extends WCPAY_UnitTestCase {
	/**
	 * Admin notices instance.
	 *
	 * @var WC_Payments_Subscriptions_Admin_Notices
	 */
	private $admin_notices;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		require_once WCPAY_ABSPATH . 'includes/subscriptions/class-wc-payments-subscriptions-admin-notices.php';
		$this->admin_notices = new WC_Payments_Subscriptions_Admin_Notices();
	}

	/**
	 * Test display_stripe_billing_deprecation_notice method.
	 */
	public function test_display_stripe_billing_deprecation_notice() {
		// Test version < 9.7.0.
		/** @var WC_Payments_Subscriptions_Admin_Notices|\PHPUnit\Framework\MockObject\MockObject $admin_notices */
		$admin_notices = $this->getMockBuilder( WC_Payments_Subscriptions_Admin_Notices::class )
			->onlyMethods( [ 'is_bundled_subscriptions_enabled', 'get_screen_id', 'get_wcpay_version' ] )
			->getMock();

		$admin_notices->expects( $this->once() )
			->method( 'is_bundled_subscriptions_enabled' )
			->willReturn( true );

		$admin_notices->expects( $this->once() )
			->method( 'get_screen_id' )
			->willReturn( 'edit-shop_subscription' );

		$admin_notices->expects( $this->once() )
			->method( 'get_wcpay_version' )
			->willReturn( '9.6.0' );

		ob_start();
		$admin_notices->display_stripe_billing_deprecation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<p><strong>Important:</strong> From version 9.7 of WooPayments (scheduled for 23 July, 2025)', $output );

		// Test version < 9.8.0.
		/** @var WC_Payments_Subscriptions_Admin_Notices|\PHPUnit\Framework\MockObject\MockObject $admin_notices */
		$admin_notices = $this->getMockBuilder( WC_Payments_Subscriptions_Admin_Notices::class )
			->onlyMethods( [ 'is_bundled_subscriptions_enabled', 'get_screen_id', 'get_wcpay_version' ] )
			->getMock();

		$admin_notices->expects( $this->once() )
			->method( 'is_bundled_subscriptions_enabled' )
			->willReturn( true );

		$admin_notices->expects( $this->once() )
			->method( 'get_screen_id' )
			->willReturn( 'edit-shop_subscription' );

		$admin_notices->expects( $this->once() )
			->method( 'get_wcpay_version' )
			->willReturn( '9.7.0' );

		ob_start();
		$admin_notices->display_stripe_billing_deprecation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'WooPayments no longer allows customers to create new subscriptions', $output );

		// Test version < 9.9.0.
		/** @var WC_Payments_Subscriptions_Admin_Notices|\PHPUnit\Framework\MockObject\MockObject $admin_notices */
		$admin_notices = $this->getMockBuilder( WC_Payments_Subscriptions_Admin_Notices::class )
			->onlyMethods( [ 'is_bundled_subscriptions_enabled', 'get_screen_id', 'get_wcpay_version' ] )
			->getMock();

		$admin_notices->expects( $this->once() )
			->method( 'is_bundled_subscriptions_enabled' )
			->willReturn( true );

		$admin_notices->expects( $this->once() )
			->method( 'get_screen_id' )
			->willReturn( 'edit-shop_subscription' );

		$admin_notices->expects( $this->once() )
			->method( 'get_wcpay_version' )
			->willReturn( '9.8.0' );

		ob_start();
		$admin_notices->display_stripe_billing_deprecation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'WooPayments no longer supports billing for existing customer subscriptions', $output );

		// Test version >= 9.9.0.
		/** @var WC_Payments_Subscriptions_Admin_Notices|\PHPUnit\Framework\MockObject\MockObject $admin_notices */
		$admin_notices = $this->getMockBuilder( WC_Payments_Subscriptions_Admin_Notices::class )
			->onlyMethods( [ 'is_bundled_subscriptions_enabled', 'get_screen_id', 'get_wcpay_version' ] )
			->getMock();

		$admin_notices->expects( $this->once() )
			->method( 'is_bundled_subscriptions_enabled' )
			->willReturn( true );

		$admin_notices->expects( $this->once() )
			->method( 'get_screen_id' )
			->willReturn( 'edit-shop_subscription' );

		$admin_notices->expects( $this->once() )
			->method( 'get_wcpay_version' )
			->willReturn( '9.9.0' );

		ob_start();
		$admin_notices->display_stripe_billing_deprecation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'WooPayments no longer supports subscriptions capabilities', $output );

		// Test that the notice is not displayed when is_bundled_subscriptions_enabled returns false.
		/** @var WC_Payments_Subscriptions_Admin_Notices|\PHPUnit\Framework\MockObject\MockObject $admin_notices */
		$admin_notices = $this->getMockBuilder( WC_Payments_Subscriptions_Admin_Notices::class )
			->onlyMethods( [ 'is_bundled_subscriptions_enabled', 'get_screen_id' ] )
			->getMock();

		$admin_notices->expects( $this->once() )
			->method( 'get_screen_id' )
			->willReturn( 'edit-shop_subscription' );

		$admin_notices->expects( $this->once() )
			->method( 'is_bundled_subscriptions_enabled' )
			->willReturn( false );

		ob_start();
		$admin_notices->display_stripe_billing_deprecation_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test get_wcpay_version method.
	 */
	public function test_get_wcpay_version() {
		// Use reflection to access protected method.
		$method = new ReflectionMethod( WC_Payments_Subscriptions_Admin_Notices::class, 'get_wcpay_version' );
		$method->setAccessible( true );

		// Test that it returns the version from WC_Payments::get_file_version.
		$this->assertEquals( WC_Payments::get_file_version( WCPAY_PLUGIN_FILE ), $method->invoke( $this->admin_notices ) );
	}
}

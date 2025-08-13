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
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		require_once WCPAY_ABSPATH . 'includes/subscriptions/class-wc-payments-subscriptions-admin-notices.php';
	}

	/**
	 * Test display_stripe_billing_deprecation_notice method.
	 *
	 * @dataProvider display_stripe_billing_deprecation_notice_provider
	 * @param string $version The version of WooPayments.
	 * @param string $expected_output The expected output of the notice.
	 */
	public function test_display_stripe_billing_deprecation_notice( $version, $expected_output ): void {
		$admin_notices = $this->get_admin_notices_mock();

		$admin_notices->expects( $this->once() )
			->method( 'is_bundled_subscriptions_enabled' )
			->willReturn( true );

		$admin_notices->expects( $this->once() )
			->method( 'get_screen_id' )
			->willReturn( 'edit-shop_subscription' );

		$admin_notices->expects( $this->once() )
			->method( 'get_wcpay_version' )
			->willReturn( $version );

		ob_start();
		$admin_notices->display_stripe_billing_deprecation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( $expected_output, $output );
	}

	/**
	 * Test that the notice is not displayed when is_bundled_subscriptions_enabled returns false.
	 */
	public function test_the_notice_is_not_displayed_when_bundled_subscriptions_is_not_enabled(): void {
		$admin_notices = $this->get_admin_notices_mock();

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
	 * Get a mock of the admin notices class.
	 *
	 * @return WC_Payments_Subscriptions_Admin_Notices|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function get_admin_notices_mock() {
		return $this->getMockBuilder( WC_Payments_Subscriptions_Admin_Notices::class )
			->onlyMethods( [ 'is_bundled_subscriptions_enabled', 'get_screen_id', 'get_wcpay_version' ] )
			->getMock();
	}

	/**
	 * Get a provider for the display_stripe_billing_deprecation_notice method.
	 *
	 * @return iterable
	 */
	public function display_stripe_billing_deprecation_notice_provider(): iterable {
		yield '< 9.7.0' => [
			'version'         => '9.6.0',
			'expected_output' => '<p><strong>Important:</strong> From version 9.7 of WooPayments (scheduled for 23 July, 2025)',
		];
		yield '9.7.0' => [
			'version'         => '9.7.0',
			'expected_output' => 'WooPayments no longer allows customers to create new subscriptions',
		];
		yield '9.8.0' => [
			'version'         => '9.8.0',
			'expected_output' => 'WooPayments no longer supports billing for existing customer subscriptions',
		];
		yield '>= 9.9.0' => [
			'version'         => '9.9.0',
			'expected_output' => 'WooPayments no longer supports subscriptions capabilities',
		];
	}
}

<?php
/**
 * Class WC_Payments_Bnpl_Announcement_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Bnpl_Announcement unit tests.
 */
class WC_Payments_Bnpl_Announcement_Test extends WCPAY_UnitTestCase {
	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $account_service_mock;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $gateway_mock;

	/**
	 * @var WC_Payments_Bnpl_Announcement
	 */
	private $bnpl_announcement;

	protected function setUp(): void {
		parent::setUp();

		delete_user_meta( get_current_user_id(), '_wcpay_bnpl_april15_viewed' );
		set_transient( 'wcpay_bnpl_april15_successful_purchases_count', 5 );

		$this->gateway_mock = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_upe_enabled_payment_method_ids' ] )
			->getMock();

		$this->account_service_mock = $this->getMockBuilder( WC_Payments_Account::class )->disableOriginalConstructor()->setMethods( [ 'get_account_country' ] )->getMock();

		$this->bnpl_announcement = new WC_Payments_Bnpl_Announcement( $this->gateway_mock, $this->account_service_mock, strtotime( '2024-06-06' ) );
	}

	protected function tearDown(): void {
		parent::tearDown();

		wp_deregister_script( 'WCPAY_BNPL_ANNOUNCEMENT' );
	}

	public function test_it_enqueues_scripts_for_eligible_users() {
		global $current_section, $current_tab, $wp_actions;

		// mocking the settings page URL.
		$current_section = 'woocommerce_payments';
		$current_tab     = 'checkout';
		$this->set_is_admin( true );

		// mocking the "did action" for 'current_screen'.
		$wp_actions['current_screen'] = true; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		WC_Payments::mode()->live();
		$this->set_current_user_can( true );
		$this->account_service_mock->method( 'get_account_country' )->willReturn( 'US' );
		$this->gateway_mock->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ 'card' ] );

		$this->bnpl_announcement->maybe_enqueue_scripts();

		do_action( 'admin_enqueue_scripts' );

		// ensuring the dialog has been marked as "viewed".
		$this->assertEquals( '1', get_user_meta( get_current_user_id(), '_wcpay_bnpl_april15_viewed', true ) );
		$this->assertTrue( wp_script_is( 'WCPAY_BNPL_ANNOUNCEMENT', 'registered' ) );
	}

	public function test_it_does_not_enqueues_scripts_for_users_that_have_already_seen_the_message() {
		global $current_section, $current_tab, $wp_actions;

		// mocking the settings page URL.
		$current_section = 'woocommerce_payments';
		$current_tab     = 'checkout';
		$this->set_is_admin( true );

		// mocking the "did action" for 'current_screen'.
		$wp_actions['current_screen'] = true; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		WC_Payments::mode()->live();
		$this->set_current_user_can( true );
		$this->account_service_mock->method( 'get_account_country' )->willReturn( 'US' );
		$this->gateway_mock->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ 'card' ] );

		// marking it as "already viewed" for the current user.
		add_user_meta( get_current_user_id(), '_wcpay_bnpl_april15_viewed', '1' );

		$this->bnpl_announcement->maybe_enqueue_scripts();

		do_action( 'admin_enqueue_scripts' );

		$this->assertFalse( wp_script_is( 'WCPAY_BNPL_ANNOUNCEMENT', 'registered' ) );
	}

	private function set_current_user_can( bool $can ) {
		global $current_user_can;

		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_user_can = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'current_user_can' ] )
			->getMock();

		$current_user_can->method( 'current_user_can' )->willReturn( $can );
	}

	/**
	 * @param bool $is_admin
	 */
	private function set_is_admin( bool $is_admin ) {
		global $current_screen;

		if ( ! $is_admin ) {
			$current_screen = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited

			return;
		}

		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_screen = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( $is_admin );
		$current_screen->id     = 'wc-payments-deposits';
		$current_screen->action = null;
	}
}

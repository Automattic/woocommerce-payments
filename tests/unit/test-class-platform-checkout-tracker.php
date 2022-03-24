<?php
/**
 * Class Platform_Checkout_Tracker_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Platform_Checkout_Tracker;

/**
 * Platform_Checkout_Tracker unit tests.
 */
class Platform_Checkout_Tracker_Test extends WP_UnitTestCase {

	/**
	 * @var Platform_Checkout_Tracker
	 */
	private $tracker;

	/**
	 * The HTTP client.
	 *
	 * @var WC_Payments_Http
	 */
	private $http_client_stub;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->http_client_stub = $this->getMockBuilder( WC_Payments_Http::class )->disableOriginalConstructor()->setMethods( [ 'wpcom_json_api_request_as_user' ] )->getMock();
		$this->tracker          = new WCPay\Platform_Checkout_Tracker( $this->http_client_stub );
	}

	public function test_tracks_obeys_platform_checkout_flag() {
		$this->set_is_platform_checkout_eligible( false );
		$this->assertFalse( $this->tracker->should_enable_tracking( null, null ) );
	}

	public function test_does_not_track_admin_pages() {
		wp_set_current_user( 1 );
		$this->set_is_platform_checkout_eligible( true );
		$this->set_is_admin( true );
		$this->assertFalse( $this->tracker->should_enable_tracking( null, null ) );
	}

	public function test_does_track_non_admins() {
		global $wp_roles;
		$this->set_is_platform_checkout_eligible( true );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
		wp_set_current_user( 1 );
		$this->set_is_admin( false );

		$all_roles = $wp_roles->get_names();
		$all_roles = array_diff( $all_roles, [ 'administrator' ] );

		foreach ( $all_roles as $role ) {
			wp_get_current_user()->set_role( $role );
			$this->assertTrue( $this->tracker->should_enable_tracking( null, null ) );
		}
	}

	/**
	 * @param bool $is_admin
	 */
	private function set_is_admin( bool $is_admin ) {
		global $current_screen;

		if ( ! $is_admin ) {
			$current_screen = null;
			return;
		}

		$current_screen = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( $is_admin );
	}

	/**
	 * Cache account details.
	 *
	 * @param $account
	 */
	private function set_is_platform_checkout_eligible( $is_platform_checkout_eligible ) {
		add_option(
			WC_Payments_Account::ACCOUNT_OPTION,
			[
				'account' => [ 'platform_checkout_eligible' => $is_platform_checkout_eligible ],
			]
		);
	}
}

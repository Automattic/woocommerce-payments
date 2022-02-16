<?php
/**
 * Class Platform_Checkout_Tracker_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use SebastianBergmann\CodeCoverage\Util;
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
	public function setUp() {
		parent::setUp();

		$this->http_client_stub = $this->getMockBuilder( WC_Payments_Http::class )->disableOriginalConstructor()->setMethods( [ 'wpcom_json_api_request_as_user' ] )->getMock();
		$this->tracker          = new Platform_Checkout_Tracker( $this->http_client_stub );
	}

	public function test_should_track_obeys_platform_checkou_flag() {
		update_option( '_wcpay_feature_platform_checkout', '0' );
		$this->assertFalse( $this->tracker->should_track() );
	}

	public function test_does_not_track_admin_pages() {
		update_option( '_wcpay_feature_platform_checkout', '1' );
		wp_set_current_user( 1 );
		$this->set_is_admin( true );
		$this->assertFalse( $this->tracker->should_track() );
	}

	public function test_does_track_non_admins() {
		global $wp_roles;
		update_option( '_wcpay_feature_platform_checkout', '1' );
		wp_set_current_user( 1 );
		$this->set_is_admin( false );

		$all_roles = $wp_roles->get_names();
		$all_roles = array_diff( $all_roles, [ 'administrator' ] );

		foreach ( $all_roles as $role ) {
			wp_get_current_user()->set_role( $role );
			$this->assertTrue( $this->tracker->should_track() );
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
}

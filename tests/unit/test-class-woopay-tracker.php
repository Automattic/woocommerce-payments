<?php
/**
 * Class WooPay_Tracker_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\WooPay_Tracker;

/**
 * WooPay_Tracker unit tests.
 */
class WooPay_Tracker_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WooPay_Tracker
	 */
	private $tracker;

	/**
	 * The HTTP client.
	 *
	 * @var WC_Payments_Http
	 */
	private $http_client_stub;

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->http_client_stub = $this->getMockBuilder( WC_Payments_Http::class )->disableOriginalConstructor()->setMethods( [ 'wpcom_json_api_request_as_user' ] )->getMock();
		$this->tracker          = new WCPay\WooPay_Tracker( $this->http_client_stub );

		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );

		$this->mock_account = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();
	}

	public function tear_down() {
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );

		parent::tear_down();
	}

	public function test_tracks_obeys_woopay_flag() {
		$this->set_account_connected( true );
		WC_Payments::set_account_service( $this->mock_account );
		$this->set_is_woopay_eligible( false );
		$this->assertFalse( $this->tracker->should_enable_tracking( null, null ) );
	}

	public function test_does_not_track_admin_pages() {
		wp_set_current_user( 1 );
		$this->set_is_woopay_eligible( true );
		$this->set_account_connected( true );
		WC_Payments::set_account_service( $this->mock_account );
		$this->set_is_admin( true );
		$this->assertFalse( $this->tracker->should_enable_tracking( null, null ) );
	}

	public function test_does_track_non_admins() {
		global $wp_roles;
		$this->set_is_woopay_eligible( true );
		$this->set_account_connected( true );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
		WC_Payments::set_account_service( $this->mock_account );
		wp_set_current_user( 1 );
		$this->set_is_admin( false );

		$all_roles = $wp_roles->get_names();
		$all_roles = array_diff( $all_roles, [ 'administrator' ] );

		foreach ( $all_roles as $role ) {
			wp_get_current_user()->set_role( $role );
			$this->assertTrue( $this->tracker->should_enable_tracking( null, null ) );
		}
	}

	public function test_does_not_track_when_account_not_connected() {
		wp_set_current_user( 1 );
		$this->set_is_woopay_eligible( true );
		$this->set_account_connected( false );
		WC_Payments::set_account_service( $this->mock_account );
		$is_admin_event      = false;
		$track_on_all_stores = true;
		$this->assertFalse( $this->tracker->should_enable_tracking( $is_admin_event, $track_on_all_stores ) );
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
	}

	/**
	 * Cache account details.
	 *
	 * @param $is_woopay_eligible
	 */
	private function set_is_woopay_eligible( $is_woopay_eligible ) {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_woopay_eligible ] );
	}

	/**
	 * Set Stripe Account connections status.
	 *
	 * @param $is_stripe_connected
	 */
	private function set_account_connected( $is_stripe_connected ) {
		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( $is_stripe_connected );
	}
}

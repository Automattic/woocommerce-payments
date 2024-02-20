<?php
/**
 * Class WooPay_Tracker_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay_Tracker;
use WC_Payments_Account;
use WC_Payments_Http;

/**
 * WooPay_Tracker unit tests.
 */
class WooPay_Tracker_Test extends WCPAY_UnitTestCase {
	/**
	 * @var WooPay_Tracker
	 */
	private $tracker;

	/**
	 * @var WC_Payments_Http
	 */
	private $http_client_stub;

	/**
	 * @var WC_Payments_Account
	 */
	private $mock_account;

	/**
	 * @var WCPay\Database_Cache
	 */
	private $mock_cache;

	/**
	 * @var WCPay\Database_Cache
	 */
	private $cache;

	public function setUp(): void {
		parent::setUp();

		$this->http_client_stub = $this->createMock( WC_Payments_Http::class );
		$this->http_client_stub->method( 'is_user_connected' )->willReturn( true );
		$this->http_client_stub->method( 'get_connected_user_data' )->willReturn( [ 'ID' => 1234 ] );

		$this->tracker = new WooPay_Tracker( $this->http_client_stub );

		$this->cache      = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );
		WC_Payments::get_gateway()->enable();

		$this->mock_account = $this->createMock( WC_Payments_Account::class );
	}

	public function tearDown(): void {
		WC_Payments::set_database_cache( $this->cache );
		parent::tearDown();
	}

	public function test_tracks_obeys_woopay_flag(): void {
		$is_woopay_eligible   = false;
		$is_account_connected = true;

		$this->setup_woopay_environment( $is_woopay_eligible, $is_account_connected );
		$this->assertFalse( $this->tracker->should_enable_tracking() );
	}

	public function test_does_not_track_admin_pages(): void {
		$is_woopay_eligible   = true;
		$is_account_connected = true;
		$is_admin_page        = true;
		$this->setup_woopay_environment( $is_woopay_eligible, $is_account_connected, $is_admin_page );
		$this->assertFalse( $this->tracker->should_enable_tracking() );
	}

	public function test_does_track_non_admins(): void {
		$is_woopay_eligible   = true;
		$is_account_connected = true;
		$this->setup_woopay_environment( $is_woopay_eligible, $is_account_connected );

		global $wp_roles;
		$all_roles = array_diff( $wp_roles->get_names(), [ 'administrator' ] );

		foreach ( $all_roles as $role ) {
			wp_get_current_user()->set_role( $role );
			$this->assertTrue( $this->tracker->should_enable_tracking() );
		}
	}

	public function test_does_not_track_when_account_not_connected(): void {
		$is_woopay_eligible   = true;
		$is_account_connected = false;
		$this->setup_woopay_environment( $is_woopay_eligible, $is_account_connected );
		$this->assertFalse( $this->tracker->should_enable_tracking() );
	}

	public function test_tracks_build_event_obj_for_admin_events(): void {
		$this->set_account_connected( true );
		$event_name = 'wcadmin_test_event';
		$properties = [ 'test_property' => 'value' ];

		$event_obj = $this->invoke_method( $this->tracker, 'tracks_build_event_obj', [ wp_get_current_user(), $event_name, $properties ] );
		$this->assertEquals( 'value', $event_obj->test_property );
		$this->assertEquals( 1234, $event_obj->_ui );
		$this->assertEquals( $event_name, $event_obj->_en );
	}

	public function test_tracks_build_event_obj_for_shopper_events() {
		$this->set_account_connected( true );
		$event_name = 'wcpay_test_event';
		$properties = [ 'test_property' => 'value' ];

		$event_obj = $this->invoke_method( $this->tracker, 'tracks_build_event_obj', [ wp_get_current_user(), $event_name, $properties ] );

		$this->assertInstanceOf( Jetpack_Tracks_Event::class, $event_obj );
		$this->assertEquals( 'value', $event_obj->test_property );
		$this->assertEquals( 1234, $event_obj->_ui );
		$this->assertEquals( $event_name, $event_obj->_en );
	}

	private function setup_woopay_environment( bool $is_woopay_eligible, bool $is_stripe_connected, bool $is_admin = false ): void {
		$this->set_is_woopay_eligible( $is_woopay_eligible );
		$this->set_account_connected( $is_stripe_connected );
		$this->set_is_admin( $is_admin );
		WC_Payments::set_account_service( $this->mock_account );
	}

	/**
	 * Utility method to access protected methods for testing.
	 */
	protected function invoke_method( &$object, $method_name, array $parameters = [] ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $object, $parameters );
	}

	/**
	 * Mock is_admin() function.
	 *
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

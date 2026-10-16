<?php
/**
 * Class WooPay_Tracker_Test
 *
 * @package WooCommerce\Payments\Tests
 */

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

	/**
	 * @var string
	 */
	private $platform_checkout_setting;

	public function setUp(): void {
		parent::setUp();

		$this->http_client_stub = $this->createMock( WC_Payments_Http::class );
		$this->http_client_stub->method( 'is_user_connected' )->willReturn( true );
		$this->http_client_stub->method( 'get_connected_user_data' )->willReturn( [ 'ID' => 1234 ] );

		$this->tracker = new WooPay_Tracker( $this->http_client_stub );
		$this->tracker->init_hooks();

		$this->cache      = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );
		WC_Payments::get_gateway()->enable();
		$this->platform_checkout_setting = WC_Payments::get_gateway()->get_option( 'platform_checkout' );

		$this->mock_account = $this->createMock( WC_Payments_Account::class );
		update_option( 'woocommerce_allow_tracking', 'yes' );
	}

	public function tearDown(): void {
		WC_Payments::set_database_cache( $this->cache );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', $this->platform_checkout_setting );
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
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );

		global $wp_roles;
		$all_roles = array_diff( array_keys( $wp_roles->get_names() ), [ 'administrator' ] );

		foreach ( $all_roles as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertTrue( $this->tracker->should_enable_tracking(), "Tracking should be enabled for the $role role." );
		}
	}

	public function test_does_not_track_when_account_not_connected(): void {
		$is_woopay_eligible   = true;
		$is_account_connected = false;
		$this->setup_woopay_environment( $is_woopay_eligible, $is_account_connected );
		$this->assertFalse( $this->tracker->should_enable_tracking() );
	}

	public function test_shopper_tracking_config_is_enabled_when_woopay_is_not_eligible(): void {
		$is_woopay_eligible   = false;
		$is_account_connected = true;
		$this->setup_woopay_environment( $is_woopay_eligible, $is_account_connected );

		$config = $this->tracker->add_tracking_config_to_payment_fields( [] );
		$params = $this->tracker->add_tracking_config_to_express_checkout( [] );

		$this->assertTrue( $config['isShopperTrackingEnabled'] );
		$this->assertTrue( $params['is_shopper_tracking_enabled'] );
	}

	public function test_shopper_tracking_config_is_disabled_when_store_opts_out(): void {
		$is_woopay_eligible   = true;
		$is_account_connected = true;
		$this->setup_woopay_environment( $is_woopay_eligible, $is_account_connected );
		update_option( 'woocommerce_allow_tracking', 'no' );

		$config = $this->tracker->add_tracking_config_to_payment_fields( [] );
		$params = $this->tracker->add_tracking_config_to_express_checkout( [] );

		$this->assertFalse( $config['isShopperTrackingEnabled'] );
		$this->assertFalse( $params['is_shopper_tracking_enabled'] );
	}

	public function test_ajax_tracks_records_each_batched_event(): void {
		$recorded = [];
		$tracker  = $this->get_tracker_with_mocked_recording();
		$tracker->expects( $this->exactly( 2 ) )
			->method( 'maybe_record_event' )
			->willReturnCallback(
				function ( $event, $data ) use ( &$recorded ) {
					$recorded[] = [ $event, $data ];
					return true;
				}
			);
		$_REQUEST['tracksEvents'] = wp_slash(
			wp_json_encode(
				[
					[
						'name'       => 'product_page_view',
						'properties' => [ 'record_event_data' => [ 'track_on_all_stores' => true ] ],
					],
					[
						'name'       => 'gpay_button_load',
						'properties' => [ 'source' => 'product' ],
					],
				]
			)
		);

		$this->call_ajax_tracks( $tracker );

		$this->assertSame(
			[
				[ 'product_page_view', [ 'record_event_data' => [ 'track_on_all_stores' => true ] ] ],
				[ 'gpay_button_load', [ 'source' => 'product' ] ],
			],
			$recorded
		);
	}

	public function test_ajax_tracks_skips_invalid_batched_events(): void {
		$tracker = $this->get_tracker_with_mocked_recording();
		$tracker->expects( $this->once() )
			->method( 'maybe_record_event' )
			->with( 'cart_page_view', [] );
		$_REQUEST['tracksEvents'] = wp_slash(
			wp_json_encode(
				[
					'not an event',
					[ 'properties' => [ 'source' => 'cart' ] ],
					[ 'name' => [ 'not', 'a', 'string' ] ],
					[
						'name'       => 'cart_page_view',
						'properties' => 'not an array',
					],
				]
			)
		);

		$this->call_ajax_tracks( $tracker );
	}

	public function test_ajax_tracks_limits_the_number_of_batched_events(): void {
		$tracker = $this->get_tracker_with_mocked_recording();
		$tracker->expects( $this->exactly( 20 ) )->method( 'maybe_record_event' );
		$_REQUEST['tracksEvents'] = wp_slash( wp_json_encode( array_fill( 0, WooPay_Tracker::MAX_BATCHED_EVENTS + 5, [ 'name' => 'cart_page_view' ] ) ) );

		$this->call_ajax_tracks( $tracker );
	}

	public function test_ajax_tracks_rejects_batched_events_that_are_not_an_array(): void {
		$tracker = $this->get_tracker_with_mocked_recording();
		$tracker->expects( $this->never() )->method( 'maybe_record_event' );
		$_REQUEST['tracksEvents'] = 'not json';

		$response = $this->call_ajax_tracks( $tracker );

		$this->assertSame(
			[
				'success' => false,
				'data'    => 'No valid event name or type.',
			],
			$response
		);
	}

	public function test_ajax_tracks_records_a_single_event(): void {
		$tracker = $this->get_tracker_with_mocked_recording();
		$tracker->expects( $this->once() )
			->method( 'maybe_record_event' )
			->with( 'cart_page_view', [ 'source' => 'cart' ] );
		$_REQUEST['tracksEventName'] = 'cart_page_view';
		$_REQUEST['tracksEventProp'] = wp_slash( wp_json_encode( [ 'source' => 'cart' ] ) );

		$this->call_ajax_tracks( $tracker );
	}

	/**
	 * @return WooPay_Tracker&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function get_tracker_with_mocked_recording() {
		return $this->getMockBuilder( WooPay_Tracker::class )
			->setConstructorArgs( [ $this->http_client_stub ] )
			->onlyMethods( [ 'maybe_record_event' ] )
			->getMock();
	}

	/**
	 * @return array|null The decoded JSON response.
	 */
	private function call_ajax_tracks( WooPay_Tracker $tracker ): ?array {
		$_REQUEST['tracksNonce'] = wp_create_nonce( 'platform_tracks_nonce' );

		// Make wp_send_json_*() stop the handler like it does in a real AJAX request.
		add_filter( 'wp_doing_ajax', '__return_true', 999 );
		add_filter(
			'wp_die_ajax_handler',
			function () {
				return function () {
					throw new WPDieException();
				};
			},
			999
		);

		ob_start();
		try {
			$tracker->ajax_tracks();
		} catch ( WPDieException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Expected, the handler always ends with wp_send_json_*().
		} finally {
			$output = ob_get_clean();
			remove_all_filters( 'wp_doing_ajax' );
			remove_all_filters( 'wp_die_ajax_handler' );
			unset( $_REQUEST['tracksNonce'], $_REQUEST['tracksEvents'], $_REQUEST['tracksEventName'], $_REQUEST['tracksEventProp'] );
		}

		return json_decode( $output, true );
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
	protected function invoke_method( &$instance, $method_name, array $parameters = [] ) {
		$reflection = new \ReflectionClass( get_class( $instance ) );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $instance, $parameters );
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

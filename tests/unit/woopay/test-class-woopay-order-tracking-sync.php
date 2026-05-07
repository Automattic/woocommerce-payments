<?php
/**
 * Class WooPay_Order_Tracking_Sync_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Order_Tracking_Sync;
use WCPay\WooPay\Tracking_Providers\WooPay_Tracking_Provider;
use PHPUnit\Framework\MockObject\MockObject;

require_once __DIR__ . '/tracking-providers/fake-fulfillment.php';
require_once __DIR__ . '/tracking-providers/fake-persistence-provider.php';

/**
 * WooPay_Order_Tracking_Sync unit tests.
 */
class WooPay_Order_Tracking_Sync_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WP_User
	 */
	protected static $admin_user;

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $account_mock;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $api_client_mock;

	/**
	 * @var WooPay_Order_Tracking_Sync
	 */
	private $tracking_sync;

	/**
	 * @var WCPay\Database_Cache
	 */
	private $cache;

	/**
	 * @var WCPay\Database_Cache|MockObject
	 */
	private $mock_cache;

	public function set_up() {
		parent::set_up();

		WooPay_Order_Tracking_Sync::reset_providers();

		$this->account_mock    = $this->createMock( WC_Payments_Account::class );
		$this->api_client_mock = $this->createMock( WC_Payments_API_Client::class );
		$this->tracking_sync   = new WooPay_Order_Tracking_Sync( $this->api_client_mock, $this->account_mock );

		$this->cache      = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );

		$this->set_is_woopay_eligible( true );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
	}

	public function tear_down() {
		WC_Payments::set_database_cache( $this->cache );
		WooPay_Order_Tracking_Sync::reset_providers();
		// Disable WooPay between tests to avoid side effects on other tests.
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'no' );
		// Clear any debounce transients leaked across tests.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_woopay_tracking_webhook_%' OR option_name LIKE '_transient_timeout_woopay_tracking_webhook_%'" );
		delete_option( WooPay_Order_Tracking_Sync::WEBHOOK_ID_OPTION );
		parent::tear_down();
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user = $factory->user->create_and_get( [ 'role' => 'administrator' ] );
	}

	public function test_get_providers_returns_default_chain_in_priority_order() {
		$providers = WooPay_Order_Tracking_Sync::get_providers();

		$this->assertIsArray( $providers );
		$this->assertCount( 3, $providers );
		$this->assertInstanceOf(
			\WCPay\WooPay\Tracking_Providers\WooPay_Fulfillments_API_Provider::class,
			$providers[0],
			'Fulfillments API should be priority 1.'
		);
		$this->assertInstanceOf(
			\WCPay\WooPay\Tracking_Providers\WooPay_Shipment_Tracking_Provider::class,
			$providers[1],
			'WC Shipment Tracking / AST should be priority 2.'
		);
		$this->assertInstanceOf(
			\WCPay\WooPay\Tracking_Providers\WooPay_ShipStation_Provider::class,
			$providers[2],
			'ShipStation standalone should be priority 3.'
		);
	}

	public function test_get_providers_is_filterable() {
		$custom_provider = $this->createMock( WooPay_Tracking_Provider::class );
		$custom_provider->method( 'get_hooks' )->willReturn( [] );

		add_filter(
			'wcpay_woopay_tracking_providers',
			function () use ( $custom_provider ) {
				return [ $custom_provider ];
			}
		);

		WooPay_Order_Tracking_Sync::reset_providers();
		$providers = WooPay_Order_Tracking_Sync::get_providers();

		$this->assertCount( 1, $providers );
		$this->assertSame( $custom_provider, $providers[0] );
	}

	public function test_get_providers_filter_receives_default_list() {
		$received = null;
		add_filter(
			'wcpay_woopay_tracking_providers',
			function ( $providers ) use ( &$received ) {
				$received = $providers;
				return $providers;
			}
		);

		WooPay_Order_Tracking_Sync::reset_providers();
		WooPay_Order_Tracking_Sync::get_providers();

		$this->assertIsArray( $received );
		$this->assertCount( 3, $received );
	}

	public function test_constructor_calls_register_persistence_hooks_on_providers_that_implement_it() {
		Fake_Persistence_Provider::reset();

		$provider = new Fake_Persistence_Provider();
		add_filter(
			'wcpay_woopay_tracking_providers',
			function () use ( $provider ) {
				return [ $provider ];
			}
		);

		WooPay_Order_Tracking_Sync::reset_providers();

		// Constructing the sync class is what triggers the persistence hook
		// registration — `set_up()` already constructed one with the default
		// provider list, so we construct a fresh one against the filtered list.
		new WooPay_Order_Tracking_Sync( $this->api_client_mock, $this->account_mock );

		$this->assertSame(
			1,
			Fake_Persistence_Provider::$register_calls,
			'register_persistence_hooks should be invoked exactly once during sync construction.'
		);
	}

	public function test_get_order_shipments_returns_empty_when_no_provider_has_data() {
		$order = WC_Helper_Order::create_order();

		$shipments = WooPay_Order_Tracking_Sync::get_order_shipments( $order );

		$this->assertEmpty( $shipments );
	}

	public function test_get_order_shipments_short_circuits_on_first_non_empty_provider() {
		$order = WC_Helper_Order::create_order();

		$first_provider = $this->createMock( WooPay_Tracking_Provider::class );
		$first_provider->method( 'is_available' )->willReturn( true );
		$first_provider->expects( $this->once() )->method( 'get_shipments' )->willReturn(
			[
				[
					'tracking_number' => 'FIRST_PROVIDER',
					'carrier_name'    => 'Provider1',
					'tracking_url'    => '',
					'date_shipped'    => '',
					'status'          => 'fulfilled',
					'items'           => [],
				],
			]
		);
		$first_provider->method( 'get_hooks' )->willReturn( [] );

		// Second provider must NEVER be queried — chain short-circuits on first match.
		$second_provider = $this->createMock( WooPay_Tracking_Provider::class );
		$second_provider->expects( $this->never() )->method( 'is_available' );
		$second_provider->expects( $this->never() )->method( 'get_shipments' );
		$second_provider->method( 'get_hooks' )->willReturn( [] );

		add_filter(
			'wcpay_woopay_tracking_providers',
			function () use ( $first_provider, $second_provider ) {
				return [ $first_provider, $second_provider ];
			}
		);
		WooPay_Order_Tracking_Sync::reset_providers();

		$shipments = WooPay_Order_Tracking_Sync::get_order_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( 'FIRST_PROVIDER', $shipments[0]['tracking_number'] );
	}

	public function test_get_order_shipments_falls_through_when_first_provider_unavailable() {
		$order = WC_Helper_Order::create_order();

		$first_provider = $this->createMock( WooPay_Tracking_Provider::class );
		$first_provider->method( 'is_available' )->willReturn( false );
		$first_provider->expects( $this->never() )->method( 'get_shipments' );
		$first_provider->method( 'get_hooks' )->willReturn( [] );

		$second_provider = $this->createMock( WooPay_Tracking_Provider::class );
		$second_provider->method( 'is_available' )->willReturn( true );
		$second_provider->expects( $this->once() )->method( 'get_shipments' )->willReturn(
			[
				[
					'tracking_number' => 'FROM_SECOND',
					'carrier_name'    => 'Provider2',
					'tracking_url'    => '',
					'date_shipped'    => '',
					'status'          => 'fulfilled',
					'items'           => [],
				],
			]
		);
		$second_provider->method( 'get_hooks' )->willReturn( [] );

		add_filter(
			'wcpay_woopay_tracking_providers',
			function () use ( $first_provider, $second_provider ) {
				return [ $first_provider, $second_provider ];
			}
		);
		WooPay_Order_Tracking_Sync::reset_providers();

		$shipments = WooPay_Order_Tracking_Sync::get_order_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( 'FROM_SECOND', $shipments[0]['tracking_number'] );
	}

	public function test_get_order_shipments_falls_through_on_empty_first_provider_data() {
		$order = WC_Helper_Order::create_order();

		$first_provider = $this->createMock( WooPay_Tracking_Provider::class );
		$first_provider->method( 'is_available' )->willReturn( true );
		$first_provider->expects( $this->once() )->method( 'get_shipments' )->willReturn( [] );
		$first_provider->method( 'get_hooks' )->willReturn( [] );

		$second_provider = $this->createMock( WooPay_Tracking_Provider::class );
		$second_provider->method( 'is_available' )->willReturn( true );
		$second_provider->expects( $this->once() )->method( 'get_shipments' )->willReturn(
			[
				[
					'tracking_number' => 'FROM_SECOND',
					'carrier_name'    => 'Provider2',
					'tracking_url'    => '',
					'date_shipped'    => '',
					'status'          => 'fulfilled',
					'items'           => [],
				],
			]
		);
		$second_provider->method( 'get_hooks' )->willReturn( [] );

		add_filter(
			'wcpay_woopay_tracking_providers',
			function () use ( $first_provider, $second_provider ) {
				return [ $first_provider, $second_provider ];
			}
		);
		WooPay_Order_Tracking_Sync::reset_providers();

		$shipments = WooPay_Order_Tracking_Sync::get_order_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( 'FROM_SECOND', $shipments[0]['tracking_number'] );
	}

	public function test_send_webhook_skips_when_woopay_disabled_on_shop() {
		// Simulate a non-WooPay merchant.
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'no' );

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'is_woopay', true );
		$order->save();

		$action_fired = false;
		add_action(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		WooPay_Order_Tracking_Sync::send_webhook( $order->get_id() );

		$this->assertFalse( $action_fired, 'Account-level gate should short-circuit before any per-order work.' );
	}

	public function test_send_webhook_skips_non_woopay_orders() {
		$order = WC_Helper_Order::create_order();
		// No 'is_woopay' meta — should be skipped.

		$action_fired = false;
		add_action(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		WooPay_Order_Tracking_Sync::send_webhook( $order->get_id() );

		$this->assertFalse( $action_fired );
	}

	public function test_send_webhook_fires_for_woopay_orders() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'is_woopay', true );
		$order->save();

		$fired_order_id = null;
		add_action(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			function ( $order_id ) use ( &$fired_order_id ) {
				$fired_order_id = $order_id;
			}
		);

		WooPay_Order_Tracking_Sync::send_webhook( $order->get_id() );

		$this->assertEquals( $order->get_id(), $fired_order_id );
	}

	public function test_send_webhook_debounces_duplicate_calls() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'is_woopay', true );
		$order->save();

		$fire_count = 0;
		add_action(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			function () use ( &$fire_count ) {
				++$fire_count;
			}
		);

		// First call should fire.
		WooPay_Order_Tracking_Sync::send_webhook( $order->get_id() );
		// Second call should be debounced.
		WooPay_Order_Tracking_Sync::send_webhook( $order->get_id() );

		$this->assertEquals( 1, $fire_count );
	}

	public function test_send_webhook_re_fires_after_debounce_window() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'is_woopay', true );
		$order->save();

		$fire_count = 0;
		add_action(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			function () use ( &$fire_count ) {
				++$fire_count;
			}
		);

		WooPay_Order_Tracking_Sync::send_webhook( $order->get_id() );
		// Simulate the debounce window expiring.
		delete_transient( WooPay_Order_Tracking_Sync::DEBOUNCE_TRANSIENT_PREFIX . $order->get_id() );
		WooPay_Order_Tracking_Sync::send_webhook( $order->get_id() );

		$this->assertEquals( 2, $fire_count, 'Webhook should fire again once the debounce window expires.' );
	}

	public function test_send_webhook_handles_wc_order_argument() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'is_woopay', true );
		$order->save();

		$fired_order_id = null;
		add_action(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			function ( $order_id ) use ( &$fired_order_id ) {
				$fired_order_id = $order_id;
			}
		);

		// ShipStation passes a WC_Order object, not an ID.
		WooPay_Order_Tracking_Sync::send_webhook( $order );

		$this->assertEquals( $order->get_id(), $fired_order_id );
	}

	public function test_send_webhook_handles_fulfillment_object_argument() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'is_woopay', true );
		$order->save();

		// Fake_Fulfillment exposes get_entity_id() returning the order ID.
		$fulfillment = new Fake_Fulfillment( [ '_entity_id' => $order->get_id() ] );

		$fired_order_id = null;
		add_action(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			function ( $order_id ) use ( &$fired_order_id ) {
				$fired_order_id = $order_id;
			}
		);

		// WC Fulfillments API passes a Fulfillment object whose get_entity_id() returns the order id.
		WooPay_Order_Tracking_Sync::send_webhook( $fulfillment );

		$this->assertEquals( $order->get_id(), $fired_order_id );
	}

	public function test_send_webhook_bails_on_invalid_argument() {
		$action_fired = false;
		add_action(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		WooPay_Order_Tracking_Sync::send_webhook( 'invalid_string' );

		$this->assertFalse( $action_fired );
	}

	public function test_create_payload_returns_original_when_webhook_unknown() {
		$original = [ 'foo' => 'bar' ];
		$result   = WooPay_Order_Tracking_Sync::create_payload( $original, 'order', 1, 999999 );

		$this->assertSame( $original, $result );
	}

	public function test_create_payload_returns_original_when_topic_does_not_match() {
		wp_set_current_user( self::$admin_user->ID );

		// Construct a webhook with the WooPay delivery URL but a different topic.
		// Both Order_Status_Sync and Order_Tracking_Sync register the same payload
		// filter against the same delivery URL, so the topic must gate which
		// filter handles the payload — otherwise one clobbers the other.
		$webhook = new WC_Webhook();
		$webhook->set_name( 'Some other woopay webhook' );
		$webhook->set_user_id( get_current_user_id() );
		$webhook->set_topic( 'order.status_changed' );
		$webhook->set_secret( wp_generate_password( 50, false ) );
		$webhook->set_delivery_url( WCPay\WooPay\WooPay_Utilities::get_woopay_rest_url( 'merchant-notification' ) );
		$webhook->set_status( 'active' );
		$webhook->save();

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'is_woopay', true );
		$order->save();

		$original = [ 'status' => 'completed' ];
		$result   = WooPay_Order_Tracking_Sync::create_payload( $original, 'order', $order->get_id(), $webhook->get_id() );

		$this->assertSame( $original, $result, 'Tracking-sync payload filter must not rewrite a status_changed webhook payload.' );

		$webhook->delete();
	}

	public function test_create_payload_returns_original_for_non_woopay_delivery_url() {
		wp_set_current_user( self::$admin_user->ID );

		$webhook = new WC_Webhook();
		$webhook->set_name( 'Some other webhook' );
		$webhook->set_user_id( get_current_user_id() );
		$webhook->set_topic( 'order.created' );
		$webhook->set_secret( wp_generate_password( 50, false ) );
		$webhook->set_delivery_url( 'https://example.com/some-other-receiver' );
		$webhook->set_status( 'active' );
		$webhook->save();

		$order    = WC_Helper_Order::create_order();
		$original = [ 'foo' => 'bar' ];

		$result = WooPay_Order_Tracking_Sync::create_payload( $original, 'order', $order->get_id(), $webhook->get_id() );

		$this->assertSame( $original, $result );

		$webhook->delete();
	}

	public function test_create_payload_returns_original_for_non_woopay_order() {
		wp_set_current_user( self::$admin_user->ID );
		$this->account_mock->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );
		$this->tracking_sync->maybe_create_woopay_order_webhook();
		$webhook_id = WooPay_Order_Tracking_Sync::get_webhook()[0];

		$order = WC_Helper_Order::create_order();
		// No `is_woopay` meta — payload assembly should be skipped (defense-in-depth).

		$original = [ 'foo' => 'bar' ];
		$result   = WooPay_Order_Tracking_Sync::create_payload( $original, 'order', $order->get_id(), $webhook_id );

		$this->assertSame( $original, $result );

		WooPay_Order_Tracking_Sync::remove_webhook();
	}

	public function test_create_payload_assembles_woopay_payload_for_woopay_order() {
		wp_set_current_user( self::$admin_user->ID );
		$this->account_mock->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );
		$this->tracking_sync->maybe_create_woopay_order_webhook();
		$webhook_id = WooPay_Order_Tracking_Sync::get_webhook()[0];

		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'is_woopay', true );
		$order->save();

		// Inject a provider that returns one shipment.
		$provider = $this->createMock( WooPay_Tracking_Provider::class );
		$provider->method( 'is_available' )->willReturn( true );
		$provider->method( 'get_shipments' )->willReturn(
			[
				[
					'tracking_number' => 'TEST123',
					'carrier_name'    => 'TestCo',
					'tracking_url'    => 'https://test.example.com/TEST123',
					'date_shipped'    => '2026-04-01',
					'status'          => 'fulfilled',
					'items'           => [],
				],
			]
		);
		$provider->method( 'get_hooks' )->willReturn( [] );
		add_filter(
			'wcpay_woopay_tracking_providers',
			function () use ( $provider ) {
				return [ $provider ];
			}
		);
		WooPay_Order_Tracking_Sync::reset_providers();

		$payload = WooPay_Order_Tracking_Sync::create_payload( [], 'order', $order->get_id(), $webhook_id );

		$this->assertArrayHasKey( 'blog_id', $payload );
		$this->assertArrayHasKey( 'order_id', $payload );
		$this->assertArrayHasKey( 'shipments', $payload );
		$this->assertEquals( $order->get_id(), $payload['order_id'] );
		$this->assertCount( 1, $payload['shipments'] );
		$this->assertEquals( 'TEST123', $payload['shipments'][0]['tracking_number'] );

		WooPay_Order_Tracking_Sync::remove_webhook();
	}

	public function test_webhook_is_created() {
		wp_set_current_user( self::$admin_user->ID );
		$this->account_mock->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );

		$this->assertEmpty( WooPay_Order_Tracking_Sync::get_webhook() );

		$this->tracking_sync->maybe_create_woopay_order_webhook();

		$this->assertNotEmpty( WooPay_Order_Tracking_Sync::get_webhook() );

		// Cleanup.
		WooPay_Order_Tracking_Sync::remove_webhook();
	}

	public function test_webhook_creation_caches_id_in_option() {
		wp_set_current_user( self::$admin_user->ID );
		$this->account_mock->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );

		$this->assertSame( 0, (int) get_option( WooPay_Order_Tracking_Sync::WEBHOOK_ID_OPTION, 0 ) );
		$this->tracking_sync->maybe_create_woopay_order_webhook();
		$this->assertGreaterThan( 0, (int) get_option( WooPay_Order_Tracking_Sync::WEBHOOK_ID_OPTION, 0 ) );

		// Subsequent maybe_create call should short-circuit using the cached id.
		$call_count_before = count( WooPay_Order_Tracking_Sync::get_webhook() );
		$this->tracking_sync->maybe_create_woopay_order_webhook();
		$call_count_after = count( WooPay_Order_Tracking_Sync::get_webhook() );

		$this->assertEquals( $call_count_before, $call_count_after, 'Subsequent calls should not create a duplicate webhook.' );

		WooPay_Order_Tracking_Sync::remove_webhook();
		$this->assertSame( 0, (int) get_option( WooPay_Order_Tracking_Sync::WEBHOOK_ID_OPTION, 0 ), 'Removal should clear the cached id.' );
	}

	public function test_webhook_removal() {
		wp_set_current_user( self::$admin_user->ID );
		$this->account_mock->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );

		$this->tracking_sync->maybe_create_woopay_order_webhook();
		$this->assertNotEmpty( WooPay_Order_Tracking_Sync::get_webhook() );

		WooPay_Order_Tracking_Sync::remove_webhook();
		$this->assertEmpty( WooPay_Order_Tracking_Sync::get_webhook() );
	}

	public function test_remove_webhook_is_safe_when_no_webhook_exists() {
		// Neither cached ID nor stored webhook — must not error.
		delete_option( WooPay_Order_Tracking_Sync::WEBHOOK_ID_OPTION );

		WooPay_Order_Tracking_Sync::remove_webhook();

		$this->assertEmpty( WooPay_Order_Tracking_Sync::get_webhook() );
	}

	public function test_remove_webhook_handles_stale_cache_id() {
		// Cached ID points to a webhook that no longer exists. Must not throw.
		update_option( WooPay_Order_Tracking_Sync::WEBHOOK_ID_OPTION, 999999, false );

		WooPay_Order_Tracking_Sync::remove_webhook();

		$this->assertSame( 0, (int) get_option( WooPay_Order_Tracking_Sync::WEBHOOK_ID_OPTION, 0 ), 'Stale cache must be cleared after remove.' );
	}

	public function test_add_topics_registers_tracking_updated() {
		$topics = WooPay_Order_Tracking_Sync::add_topics( [] );

		$this->assertArrayHasKey( 'order.tracking_updated', $topics );
		$this->assertContains(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			$topics['order.tracking_updated']
		);
	}

	public function test_add_event_registers_tracking_updated() {
		$events = WooPay_Order_Tracking_Sync::add_event( [] );

		$this->assertContains( 'tracking_updated', $events );
	}

	public function test_add_resource_includes_order() {
		$resources = WooPay_Order_Tracking_Sync::add_resource( [] );

		$this->assertContains( 'order', $resources );
	}

	private function set_is_woopay_eligible( $is_woopay_eligible ) {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_woopay_eligible ] );
	}
}

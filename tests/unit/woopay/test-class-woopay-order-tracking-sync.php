<?php
/**
 * Class WooPay_Order_Tracking_Sync_Test
 *
 * @package WooCommerce\Payments\Tests
 */

declare( strict_types=1 );

use WCPay\WooPay\WooPay_Order_Tracking_Sync;
use PHPUnit\Framework\MockObject\MockObject;

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
		parent::tear_down();
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user = $factory->user->create_and_get( [ 'role' => 'administrator' ] );
	}

	public function test_get_providers_returns_array() {
		$providers = WooPay_Order_Tracking_Sync::get_providers();

		$this->assertIsArray( $providers );
		$this->assertNotEmpty( $providers );
	}

	public function test_get_providers_is_filterable() {
		$custom_provider = $this->createMock( \WCPay\WooPay\Tracking_Providers\WooPay_Tracking_Provider::class );
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

	public function test_get_order_shipments_returns_empty_when_no_provider_has_data() {
		$order = WC_Helper_Order::create_order();

		$shipments = WooPay_Order_Tracking_Sync::get_order_shipments( $order );

		$this->assertEmpty( $shipments );
	}

	public function test_get_order_shipments_returns_data_from_shipment_tracking_meta() {
		$order = WC_Helper_Order::create_order();

		$tracking_items = [
			[
				'tracking_provider'        => 'FedEx',
				'custom_tracking_provider' => '',
				'custom_tracking_link'     => 'https://fedex.com/track/123',
				'tracking_number'          => '398242362749',
				'date_shipped'             => '1711584000',
			],
		];

		$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
		$order->save();

		// The provider chain requires the tracking plugin class to exist.
		// Test get_shipments directly instead to verify normalization.
		$provider  = new \WCPay\WooPay\Tracking_Providers\WooPay_Shipment_Tracking_Provider();
		$shipments = $provider->get_shipments( $order );

		$this->assertCount( 1, $shipments );
		$this->assertEquals( '398242362749', $shipments[0]['tracking_number'] );
		$this->assertEquals( 'FedEx', $shipments[0]['carrier_name'] );
		$this->assertEquals( 'https://fedex.com/track/123', $shipments[0]['tracking_url'] );
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

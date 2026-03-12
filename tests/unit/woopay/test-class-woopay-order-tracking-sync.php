<?php
/**
 * Class WooPay_Order_Tracking_Sync_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Order_Tracking_Sync;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * WooPay_Order_Tracking_Sync unit tests.
 */
class WooPay_Order_Tracking_Sync_Test extends WP_UnitTestCase {

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

		$this->account_mock    = $this->createMock( WC_Payments_Account::class );
		$this->api_client_mock = $this->createMock( WC_Payments_API_Client::class );
		$this->tracking_sync   = new WooPay_Order_Tracking_Sync( $this->api_client_mock, $this->account_mock );

		// Mock the main class's cache service.
		$this->cache      = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );

		// Enable woopay.
		$this->set_is_woopay_eligible( true );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
	}

	public function tear_down() {
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->cache );
		parent::tear_down();
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user = $factory->user->create_and_get( [ 'role' => 'administrator' ] );
	}

	/**
	 * Tests that add_topics adds 'order.tracking_updated' to the topic hooks array.
	 */
	public function test_add_topics_registers_tracking_updated() {
		$topic_hooks = WooPay_Order_Tracking_Sync::add_topics( [] );

		$this->assertArrayHasKey( 'order.tracking_updated', $topic_hooks );
		$this->assertContains(
			WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED,
			$topic_hooks['order.tracking_updated']
		);
	}

	/**
	 * Tests that add_event adds 'tracking_updated' to valid events.
	 */
	public function test_add_event_registers_tracking_updated() {
		$events = WooPay_Order_Tracking_Sync::add_event( [] );

		$this->assertContains( 'tracking_updated', $events );
	}

	/**
	 * Tests that add_resource includes 'order'.
	 */
	public function test_add_resource_includes_order() {
		$resources = WooPay_Order_Tracking_Sync::add_resource( [] );

		$this->assertContains( 'order', $resources );
	}

	/**
	 * Tests that send_webhook fires the custom action for WooPay orders.
	 */
	public function test_send_webhook_fires_action_for_woopay_orders() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( 'is_woopay', true );
		$order->save();

		$fulfillment = $this->make_fulfillment_stub( $order->get_id() );

		$action_fired = false;
		$hook_name    = WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED;

		add_action(
			$hook_name,
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		WooPay_Order_Tracking_Sync::send_webhook( $fulfillment );

		$this->assertTrue( $action_fired );

		$order->delete( true );
	}

	/**
	 * Tests that send_webhook skips non-WooPay orders.
	 */
	public function test_send_webhook_skips_non_woopay_orders() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$fulfillment = $this->make_fulfillment_stub( $order->get_id() );

		$action_fired = false;
		$hook_name    = WooPay_Order_Tracking_Sync::WCPAY_WEBHOOK_WOOPAY_ORDER_TRACKING_UPDATED;

		add_action(
			$hook_name,
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		WooPay_Order_Tracking_Sync::send_webhook( $fulfillment );

		$this->assertFalse( $action_fired );

		$order->delete( true );
	}

	/**
	 * Tests that create_payload returns the correct structure for a tracking webhook.
	 */
	public function test_create_payload_returns_shipments_for_tracking_webhook() {
		wp_set_current_user( self::$admin_user->ID );

		$this->account_mock->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );

		// Create the status webhook first (tracking webhook depends on it for the shared secret).
		$status_webhook = new \WC_Webhook();
		$status_webhook->set_name( 'WooPayments woopay order status sync' );
		$status_webhook->set_user_id( get_current_user_id() );
		$status_webhook->set_topic( 'order.status_changed' );
		$status_webhook->set_secret( wp_generate_password( 50, false ) );
		$status_webhook->set_delivery_url( 'https://example.com/merchant-notification' );
		$status_webhook->set_status( 'active' );
		$status_webhook->save();

		// Create the tracking webhook.
		$this->tracking_sync->maybe_create_woopay_tracking_webhook();

		$webhook_ids = WooPay_Order_Tracking_Sync::get_webhook();
		$this->assertNotEmpty( $webhook_ids );
		$webhook_id = $webhook_ids[0];

		// Create an order.
		$order = WC_Helper_Order::create_order();
		$order->save();

		$pre_processing_payload = [
			'status' => 'processing',
		];

		$post_processing_payload = WooPay_Order_Tracking_Sync::create_payload(
			$pre_processing_payload,
			'order',
			$order->get_id(),
			$webhook_id
		);

		$this->assertArrayHasKey( 'order_id', $post_processing_payload );
		$this->assertArrayHasKey( 'shipments', $post_processing_payload );
		$this->assertArrayHasKey( 'blog_id', $post_processing_payload );
		$this->assertEquals( $order->get_id(), $post_processing_payload['order_id'] );

		$order->delete( true );
		WooPay_Order_Tracking_Sync::remove_webhook();
		$status_webhook->delete( true );
	}

	/**
	 * Tests that create_payload returns the original payload for non-tracking webhooks.
	 */
	public function test_create_payload_passes_through_for_non_tracking_webhook() {
		wp_set_current_user( self::$admin_user->ID );

		// Create a non-tracking webhook.
		$webhook = new \WC_Webhook();
		$webhook->set_name( 'Some other webhook' );
		$webhook->set_user_id( get_current_user_id() );
		$webhook->set_topic( 'order.status_changed' );
		$webhook->set_secret( wp_generate_password( 50, false ) );
		$webhook->set_delivery_url( 'https://example.com/webhook' );
		$webhook->set_status( 'active' );
		$webhook->save();

		$pre_processing_payload = [
			'status'   => 'processing',
			'order_id' => 1,
		];

		$post_processing_payload = WooPay_Order_Tracking_Sync::create_payload(
			$pre_processing_payload,
			'order',
			1,
			$webhook->get_id()
		);

		$this->assertEquals( $pre_processing_payload, $post_processing_payload );

		$webhook->delete( true );
	}

	/**
	 * Cache account details.
	 *
	 * @param bool $is_woopay_eligible Whether WooPay is eligible.
	 */
	private function set_is_woopay_eligible( $is_woopay_eligible ) {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_woopay_eligible ] );
	}

	/**
	 * Creates a simple fulfillment stub with get_entity_id().
	 *
	 * @param int $order_id The order ID.
	 * @return object
	 */
	private function make_fulfillment_stub( $order_id ) {
		return new class( $order_id ) {
			/**
			 * Entity ID.
			 *
			 * @var string
			 */
			private $entity_id;

			/**
			 * Constructor.
			 *
			 * @param int $id Order ID.
			 */
			public function __construct( $id ) {
				$this->entity_id = (string) $id;
			}

			/**
			 * Get the entity ID.
			 *
			 * @return string
			 */
			public function get_entity_id() {
				return $this->entity_id;
			}
		};
	}
}

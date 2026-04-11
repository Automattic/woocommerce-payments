<?php
/**
 * Class WooPay_Order_Status_Sync_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Order_Status_Sync;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * WooPay_Utilities unit tests.
 */
class WooPay_Order_Status_Sync_Test extends WP_UnitTestCase {

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
	 * @var WooPay_Order_Status_Sync|MockObject
	 */
	private $webhook_sync_mock;

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

		$this->account_mock      = $this->createMock( WC_Payments_Account::class );
		$this->api_client_mock   = $this->createMock( WC_Payments_API_Client::class );
		$this->webhook_sync_mock = new WCPay\WooPay\WooPay_Order_Status_Sync( $this->api_client_mock, $this->account_mock );

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
	 * Tests that WooPay-specific webhooks are modified as expected.
	 * @group webhook
	 */
	public function test_woopay_specific_webhook_payload_is_updated() {
		wp_set_current_user( self::$admin_user->ID );
		$pre_processing_payload = [
			'status'             => 'publish',
			'average_rating'     => '0.00',
			'catalog_visibility' => 'visible',
			'categories'         => [
				[
					'id'   => 9,
					'name' => 'Clothing',
					'slug' => 'clothing',
				],
			],
		];

		$woopay_specific_payload = [
			'blog_id'      => false,
			'order_id'     => 1,
			'order_status' => 'publish',
		];

		$this->account_mock->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );

		// Create the WebHook with WooPay specific delivery URL.
		$this->webhook_sync_mock->maybe_create_woopay_order_webhook();

		$post_processing_payload = $this->webhook_sync_mock->create_payload( $pre_processing_payload, 'product', 1, 1 );
		$this->assertNotEquals( $pre_processing_payload, $post_processing_payload );
		$this->assertEquals( $post_processing_payload, $woopay_specific_payload );

		$this->webhook_sync_mock->remove_webhook();
		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );
	}

	/**
	 * Tests that non WooPay webhooks (e.g. Product Updated, Order created) are filtered out and eventually not modified.
	 */
	public function test_non_woopay_specific_webhook_payload_remains_unaffected() {
		wp_set_current_user( self::$admin_user->ID );
		$pre_processing_payload = [
			'status'             => 'publish',
			'average_rating'     => '0.00',
			'catalog_visibility' => 'visible',
			'categories'         => [
				[
					'id'   => 9,
					'name' => 'Clothing',
					'slug' => 'clothing',
				],
			],
		];

		$this->create_non_woopay_specific_webhook();

		$post_processing_payload = $this->webhook_sync_mock->create_payload( $pre_processing_payload, 'product', 1, 2 );
		$this->assertEquals( $pre_processing_payload, $post_processing_payload );

		$this->webhook_sync_mock->remove_webhook();
		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );
	}

	/**
	 * Tests that the webhook is created successfully if the logged-in user has the capability manage_woocommerce
	 * and the account is in good working order.
	 */
	public function test_webhook_is_created() {
		wp_set_current_user( self::$admin_user->ID );
		$this->account_mock->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );

		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );

		$this->webhook_sync_mock->maybe_create_woopay_order_webhook();

		$this->assertNotEmpty( WooPay_Order_Status_Sync::get_webhook() );
	}

	/**
	 * Tests that the webhook is not created for under review WCPay accounts.
	 */
	public function test_webhook_is_not_created_for_under_review() {
		wp_set_current_user( self::$admin_user->ID );
		$this->account_mock->method( 'is_stripe_connected' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( true );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );

		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );

		$this->webhook_sync_mock->maybe_create_woopay_order_webhook();

		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );
	}

	/**
	 * Tests that the webhook is not created for rejected WCPay accounts.
	 */
	public function test_webhook_is_not_created_for_rejected() {
		wp_set_current_user( self::$admin_user->ID );
		$this->account_mock->method( 'is_stripe_connected' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( true );

		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );

		$this->webhook_sync_mock->maybe_create_woopay_order_webhook();

		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );
	}

	/**
	 * Tests that the webhook is deleted successfully.
	 */
	public function test_webhook_removal() {
		wp_set_current_user( self::$admin_user->ID );
		$this->account_mock->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->account_mock->method( 'is_account_under_review' )->willReturn( false );
		$this->account_mock->method( 'is_account_rejected' )->willReturn( false );

		$this->webhook_sync_mock->maybe_create_woopay_order_webhook();
		$this->assertNotEmpty( WooPay_Order_Status_Sync::get_webhook() );

		$this->set_is_woopay_eligible( false );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'no' );

		$this->webhook_sync_mock->remove_webhook();
		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );
	}

	/**
	 * Cache account details.
	 *
	 * @param $account
	 */
	private function set_is_woopay_eligible( $is_woopay_eligible ) {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_woopay_eligible ] );
	}

	private function create_non_woopay_specific_webhook() {
		$delivery_url_non_specific_for_woopay = 'some-woocommerce-core-webhook-delivery-url';

		$webhook = new \WC_Webhook();
		$webhook->set_name( 'WCPay woopay order status sync' );
		$webhook->set_user_id( get_current_user_id() );
		$webhook->set_topic( 'order.status_changed' );
		$webhook->set_secret( wp_generate_password( 50, false ) );
		$webhook->set_delivery_url( $delivery_url_non_specific_for_woopay );
		$webhook->set_status( 'active' );
		$webhook->save();
	}
}

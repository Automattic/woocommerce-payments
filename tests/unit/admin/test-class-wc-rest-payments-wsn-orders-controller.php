<?php
/**
 * Class WC_REST_Payments_WSN_Orders_Controller_Test
 *
 * @package WooCommerce\Payments\Admin
 */

/**
 * Unit tests for the Overview-tab orders REST controller.
 *
 * Coverage focus:
 * - Permission gating (rejects non-manage_woocommerce users)
 * - Period enum validation
 * - Empty state when no orders carry the marketplace meta
 * - Populated state: stats aggregation + order projection
 * - Date-window filtering (orders older than `period` are excluded)
 *
 * The controller relies on `wc_get_orders()` which transparently uses HPOS when
 * available — tests run against whichever orders schema the test environment has
 * (HPOS-on in WC 8.0+, legacy CPT before that).
 */
class WC_REST_Payments_WSN_Orders_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	public function set_up() {
		parent::set_up();

		// Register routes via the rest_api_init hook — WP 6.0+ throws _doing_it_wrong
		// for any direct register_rest_route() call outside that hook.
		add_action( 'rest_api_init', [ $this, 'register_routes_for_test' ] );
		do_action( 'rest_api_init' );

		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tear_down() {
		remove_action( 'rest_api_init', [ $this, 'register_routes_for_test' ] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function register_routes_for_test() {
		( new WC_REST_Payments_WSN_Orders_Controller() )->register_routes();
	}

	public function test_get_requires_manage_woocommerce_capability() {
		// Subscriber lacks manage_woocommerce — REST should reject with 401/403.
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/orders' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
	}

	public function test_get_returns_empty_state_when_no_marketplace_orders_exist() {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/orders' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( '30d', $data['period'] );
		$this->assertTrue( $data['is_empty'] );
		$this->assertSame( [], $data['stats'] );
		$this->assertSame( [], $data['orders'] );
	}

	public function test_get_rejects_invalid_period() {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/orders' );
		$request->set_param( 'period', 'forever' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_get_aggregates_marketplace_orders_into_stats() {
		wp_set_current_user( $this->admin_user_id );

		// Create three WSN-tagged orders + one non-WSN order to confirm the meta
		// filter excludes orders without the marketplace tag.
		$this->create_marketplace_order( 50.00, 'a', 'midcentury-manila' );
		$this->create_marketplace_order( 75.00, 'a', 'midcentury-manila' );
		$this->create_marketplace_order( 25.00, 'b', 'tiny-pottery' );
		$this->create_plain_order( 999.00 ); // Should NOT appear in network stats.

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/orders' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertFalse( $data['is_empty'] );
		$this->assertSame( 3, $data['stats']['network_orders'] );
		// AOV = (50 + 75 + 25) / 3 = 50.00.
		$this->assertStringContainsString( '50.00', wp_strip_all_tags( $data['stats']['network_aov_formatted'] ) );
		// Top source bucket: 'a' has 2 of 3 = 66.7%.
		$this->assertSame( 'a', $data['stats']['top_source'] );
		$this->assertSame( '66.7%', $data['stats']['top_source_share'] );

		// 3 orders projected into the orders array, each carrying the project fields.
		$this->assertCount( 3, $data['orders'] );
		foreach ( $data['orders'] as $order_payload ) {
			$this->assertArrayHasKey( 'id', $order_payload );
			$this->assertArrayHasKey( 'number', $order_payload );
			$this->assertArrayHasKey( 'status', $order_payload );
			$this->assertArrayHasKey( 'storefront_slug', $order_payload );
			$this->assertArrayHasKey( 'total_formatted', $order_payload );
		}
	}

	public function test_period_filter_excludes_older_orders() {
		wp_set_current_user( $this->admin_user_id );

		// One order inside the 7d window, one well outside it.
		$this->create_marketplace_order( 10.00, 'a', 'recent-shop', '-1 day' );
		$this->create_marketplace_order( 999.00, 'a', 'ancient-shop', '-60 days' );

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/orders' );
		$request->set_param( 'period', '7d' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 1, $data['stats']['network_orders'] );
		$this->assertCount( 1, $data['orders'] );
		$this->assertSame( 'recent-shop', $data['orders'][0]['storefront_slug'] );
	}

	/**
	 * Create a WC order tagged with the marketplace meta keys per api-contract.md §7.
	 *
	 * @param float  $total           Order total.
	 * @param string $cohort          'a' or 'b'.
	 * @param string $storefront_slug The merchant slug the shopper came from.
	 * @param string $created_offset  strtotime() expression relative to now.
	 * @return int Order ID.
	 */
	private function create_marketplace_order( float $total, string $cohort, string $storefront_slug, string $created_offset = '-1 hour' ): int {
		$order = wc_create_order();
		$order->set_total( $total );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_REST_Payments_WSN_Orders_Controller::META_IS_MARKETPLACE, true );
		$order->update_meta_data( WC_REST_Payments_WSN_Orders_Controller::META_STOREFRONT_SLUG, $storefront_slug );
		$order->update_meta_data( WC_REST_Payments_WSN_Orders_Controller::META_COHORT, $cohort );
		$order->set_date_created( new WC_DateTime( $created_offset ) );
		$order->save();
		return $order->get_id();
	}

	/**
	 * Create a non-WSN order — should never appear in the network stats.
	 *
	 * @param float $total
	 * @return int
	 */
	private function create_plain_order( float $total ): int {
		$order = wc_create_order();
		$order->set_total( $total );
		$order->set_status( 'completed' );
		$order->save();
		return $order->get_id();
	}
}

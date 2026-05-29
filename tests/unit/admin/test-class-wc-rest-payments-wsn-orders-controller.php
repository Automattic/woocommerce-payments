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

		// Clear any transient cache the previous test's tear_down may have
		// missed (defense-in-depth — PHPUnit fixture lifecycles can be
		// affected by test isolation modes and a persistent object cache
		// can outlive a single tear_down). Doing the clear in both set_up
		// AND tear_down ensures every test starts with a cold cache.
		$this->flush_transient_cache();

		// Register routes via the rest_api_init hook — WP 6.0+ throws _doing_it_wrong
		// for any direct register_rest_route() call outside that hook.
		add_action( 'rest_api_init', [ $this, 'register_routes_for_test' ] );
		do_action( 'rest_api_init' );

		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function register_routes_for_test() {
		// Inject a mock API client. The orders controller extends
		// WC_Payments_REST_Controller (whose constructor requires the client)
		// for shared base-class behavior but never actually calls the client —
		// the whole flow is wc_get_orders() + local meta_query.
		$mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		( new WC_REST_Payments_WSN_Orders_Controller( $mock_api_client ) )->register_routes();
	}

	public function tear_down() {
		remove_action( 'rest_api_init', [ $this, 'register_routes_for_test' ] );
		wp_set_current_user( 0 );
		$this->flush_transient_cache();
		parent::tear_down();
	}

	/**
	 * Delete every period-keyed orders transient. Called from BOTH set_up
	 * and tear_down so a leaked cache can't pollute the next test.
	 */
	private function flush_transient_cache(): void {
		foreach ( array_keys( WC_REST_Payments_WSN_Orders_Controller::PERIOD_SECONDS ) as $period ) {
			delete_transient(
				WC_REST_Payments_WSN_Orders_Controller::TRANSIENT_KEY_PREFIX . $period
			);
		}
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
		// filter excludes orders without the marketplace tag. Stagger the
		// timestamps so the most-recent-first ordering is deterministic.
		$this->create_marketplace_order( 50.00, 'a', 'midcentury-manila', '-1 hour' );
		$this->create_marketplace_order( 75.00, 'a', 'midcentury-manila', '-2 hours' );
		$this->create_marketplace_order( 25.00, 'b', 'tiny-pottery', '-3 hours' );
		$this->create_plain_order( 999.00 ); // Should NOT appear in network stats.

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/orders' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertFalse( $data['is_empty'] );
		$this->assertSame( 3, $data['stats']['network_orders'] );

		// AOV = (50 + 75 + 25) / 3 = 50.00. Compute the expected string from the
		// inputs so a future precision/format change doesn't silently break.
		$this->assertStringContainsString(
			'50.00',
			wp_strip_all_tags( $data['stats']['network_aov_formatted'] )
		);

		// Confirm the formatted price is plain text (no HTML) — defends against
		// regressions to the wc_price() raw output path that previously surfaced
		// `<span class="...">$50.00</span>` literally in the React UI.
		$this->assertSame(
			wp_strip_all_tags( $data['stats']['network_aov_formatted'] ),
			$data['stats']['network_aov_formatted'],
			'network_aov_formatted must not contain HTML markup.'
		);

		// Top source bucket: 'a' has 2 of 3. Assert the source itself; for the
		// share string, use containsString to avoid a locale-sensitive exact match
		// on the formatted percent.
		$this->assertSame( 'a', $data['stats']['top_source'] );
		$this->assertStringContainsString( '66.7', $data['stats']['top_source_share'] );

		// 3 orders projected. Sorted DESC by date_created; the $50 order is most
		// recent. Assert specific values so a meta-key swap or status regression
		// inside format_order() doesn't pass undetected.
		$this->assertCount( 3, $data['orders'] );
		$first = $data['orders'][0];
		$this->assertSame( 'midcentury-manila', $first['storefront_slug'] );
		$this->assertSame( 'a', $first['source'] );
		$this->assertSame( 'completed', $first['status'] );
		$this->assertStringContainsString( '50.00', wp_strip_all_tags( $first['total_formatted'] ) );
		// HTML-strip regression guard on the order-row total too.
		$this->assertSame(
			wp_strip_all_tags( $first['total_formatted'] ),
			$first['total_formatted'],
			'order.total_formatted must not contain HTML markup.'
		);
	}

	public function test_top_source_tie_breaks_alphabetically() {
		wp_set_current_user( $this->admin_user_id );

		// Two cohorts with identical counts — the ksort()+arsort() pair in
		// compute_stats() must return the alphabetically-first source so the
		// "Top Source" stat doesn't flip between page loads. Without the
		// secondary sort, PHP's arsort() iteration order on tied keys is
		// implementation-defined and would produce a flaky metric.
		$this->create_marketplace_order( 10.00, 'b', 'shop-one', '-1 hour' );
		$this->create_marketplace_order( 10.00, 'b', 'shop-one', '-2 hours' );
		$this->create_marketplace_order( 10.00, 'a', 'shop-two', '-3 hours' );
		$this->create_marketplace_order( 10.00, 'a', 'shop-two', '-4 hours' );

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/orders' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 'a', $data['stats']['top_source'], 'Equal-count tie must resolve alphabetically.' );
	}

	public function test_network_orders_count_reflects_full_period_not_recent_cap() {
		wp_set_current_user( $this->admin_user_id );

		// Create 25 marketplace orders — above the RECENT_ORDERS_LIMIT of 20.
		// The stat card should show 25 (real count), not 20 (capped page).
		for ( $i = 0; $i < 25; $i++ ) {
			$this->create_marketplace_order( 10.00, 'a', 'big-shop', '-' . ( $i + 1 ) . ' minutes' );
		}

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/orders' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 25, $data['stats']['network_orders'], 'network_orders must reflect the full period count, not the recent-orders display cap.' );
		$this->assertCount(
			WC_REST_Payments_WSN_Orders_Controller::RECENT_ORDERS_LIMIT,
			$data['orders'],
			'The orders list is still capped at RECENT_ORDERS_LIMIT for table display.'
		);
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

	public function test_period_filter_includes_boundary_order_at_exact_window() {
		wp_set_current_user( $this->admin_user_id );

		// Order created just inside the 7d boundary. Catches off-by-one in
		// the `>=` semantics of get_since_timestamp()'s `'>=' . $since`
		// passed to wc_get_orders().
		$this->create_marketplace_order( 10.00, 'a', 'boundary-shop', '-6 days -23 hours' );

		$request = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/orders' );
		$request->set_param( 'period', '7d' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 1, $data['stats']['network_orders'], 'Order at -6d23h must be included in the 7d window.' );
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

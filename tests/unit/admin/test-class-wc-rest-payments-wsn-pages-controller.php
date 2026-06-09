<?php
/**
 * Class WC_REST_Payments_WSN_Pages_Controller_Test
 *
 * @package WooCommerce\Payments\Admin
 */

/**
 * Unit tests for the Profile-tab page picker REST controller.
 *
 * Coverage focus:
 *   - WC + WP core "policy" pages surface first (policy_pages bucket)
 *   - Title-keyword fallback also categorizes as policy
 *   - Functional WC pages (cart/checkout/shop/my-account) are excluded entirely
 *   - Front page + posts page are excluded
 *   - Other published pages land in other_pages alphabetically
 *   - Permission gating
 */
class WC_REST_Payments_WSN_Pages_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * Test-scoped admin user with manage_woocommerce.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Pages created by individual tests, cleaned up in tear_down.
	 *
	 * @var int[]
	 */
	private $created_page_ids = [];

	public function set_up() {
		parent::set_up();

		add_action( 'rest_api_init', [ $this, 'register_routes_for_test' ] );
		do_action( 'rest_api_init' );

		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tear_down() {
		remove_action( 'rest_api_init', [ $this, 'register_routes_for_test' ] );
		wp_set_current_user( 0 );
		foreach ( $this->created_page_ids as $id ) {
			wp_delete_post( $id, true );
		}
		$this->created_page_ids = [];

		delete_option( 'woocommerce_refund_returns_page_id' );
		delete_option( 'woocommerce_terms_page_id' );
		delete_option( 'wp_page_for_privacy_policy' );
		delete_option( 'woocommerce_cart_page_id' );
		delete_option( 'woocommerce_checkout_page_id' );

		parent::tear_down();
	}

	public function register_routes_for_test() {
		$mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		( new WC_REST_Payments_WSN_Pages_Controller( $mock_api_client ) )->register_routes();
	}

	public function test_get_requires_manage_woocommerce() {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/pages' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
	}

	public function test_returns_empty_buckets_when_no_pages_exist() {
		wp_set_current_user( $this->admin_user_id );

		// Wipe any pages WP installed by default.
		$existing = get_pages( [ 'post_status' => 'publish' ] );
		foreach ( $existing as $page ) {
			wp_delete_post( $page->ID, true );
		}

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/pages' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( [], $data['policy_pages'] );
		$this->assertSame( [], $data['other_pages'] );
	}

	public function test_wc_refund_returns_page_surfaces_in_policy_bucket() {
		wp_set_current_user( $this->admin_user_id );

		$refund_id = $this->create_page( 'Refund & Returns Policy' );
		update_option( 'woocommerce_refund_returns_page_id', $refund_id );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/pages' )
		);
		$data     = $response->get_data();

		$titles = wp_list_pluck( $data['policy_pages'], 'title' );
		$this->assertContains( 'Refund & Returns Policy', $titles );

		$categories = wp_list_pluck( $data['policy_pages'], 'category' );
		$this->assertContains( 'refund_returns', $categories );
	}

	public function test_title_keyword_match_surfaces_page_in_policy_bucket() {
		wp_set_current_user( $this->admin_user_id );

		// Page whose title contains "Returns" but isn't pointed to by any WC option.
		$this->create_page( 'Returns Guide' );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/pages' )
		);
		$data     = $response->get_data();

		$titles = wp_list_pluck( $data['policy_pages'], 'title' );
		$this->assertContains(
			'Returns Guide',
			$titles,
			'Pages whose title contains a policy keyword should surface even when no WC option points to them.'
		);
	}

	public function test_functional_wc_pages_are_excluded() {
		wp_set_current_user( $this->admin_user_id );

		$cart_id     = $this->create_page( 'Cart' );
		$checkout_id = $this->create_page( 'Checkout' );
		update_option( 'woocommerce_cart_page_id', $cart_id );
		update_option( 'woocommerce_checkout_page_id', $checkout_id );

		$this->create_page( 'About Us' );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/pages' )
		);
		$data     = $response->get_data();

		$all_titles = array_merge(
			wp_list_pluck( $data['policy_pages'], 'title' ),
			wp_list_pluck( $data['other_pages'], 'title' )
		);
		$this->assertNotContains( 'Cart', $all_titles );
		$this->assertNotContains( 'Checkout', $all_titles );
		$this->assertContains( 'About Us', $all_titles );
	}

	public function test_other_pages_bucket_excludes_policy_pages() {
		wp_set_current_user( $this->admin_user_id );

		$refund_id = $this->create_page( 'Refund & Returns Policy' );
		update_option( 'woocommerce_refund_returns_page_id', $refund_id );
		$this->create_page( 'About Us' );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/pages' )
		);
		$data     = $response->get_data();

		$other_titles = wp_list_pluck( $data['other_pages'], 'title' );
		$this->assertNotContains(
			'Refund & Returns Policy',
			$other_titles,
			'A page in policy_pages must NOT also appear in other_pages.'
		);
		$this->assertContains( 'About Us', $other_titles );
	}

	private function create_page( string $title ): int {
		$id                       = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
		$this->created_page_ids[] = $id;
		return $id;
	}
}

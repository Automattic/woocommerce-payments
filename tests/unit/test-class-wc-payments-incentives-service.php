<?php
/**
 * Class WC_Payments_Incentives_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Database_Cache;

/**
 * WC_Payments_Incentives_Service unit tests.
 */
class WC_Payments_Incentives_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Incentives_Service
	 */
	private $incentives_service;

	/**
	 * Mocked incentive data.
	 *
	 * @var array
	 */
	private $mock_incentive_data;

	/**
	 * Mocked incentives cache data.
	 *
	 * @var array
	 */
	private $mock_incentives_cache_data;

	/**
	 * Transient key for incentives cache.
	 *
	 * @var string
	 */
	private $transient_incentives_cache_key = WC_Payments_Incentives_Service::PREFIX . 'woopayments_cache';

	/**
	 * Transient key for has orders.
	 *
	 * @var string
	 */
	private $transient_has_orders_key = WC_Payments_Incentives_Service::PREFIX . 'woopayments_store_has_orders';

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->incentives_service = new WC_Payments_Incentives_Service( $this->createMock( Database_Cache::class ) );
		$this->incentives_service->init_hooks();

		$this->mock_incentive_data        = [
			'id'          => 'incentive_id',
			'type'        => 'connect_page',
			'description' => 'incentive_description',
			'tc_url'      => 'incentive_tc_url',
		];
		$this->mock_incentives_cache_data = [
			'incentives'   => [],
			// This is the hash of the test store context:
			// 'country' => Country_Code::UNITED_STATES,
			// 'locale' => 'en_US',
			// 'has_orders' => false,
			// 'has_payments' => false,
			// 'has_wcpay' => false.
			'context_hash' => '6d37bc19d822af681f896b21065134c7',
			'timestamp'    => 1234567890,
		];

		// Ensure the Payments menu is present.
		global $menu;
		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$menu = [
			[ 'Payments', null, 'wc-admin&path=/payments/connect' ],
		];

		// Ensure no payment gateways are available.
		add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $menu;
		$menu = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited

		remove_all_filters( 'pre_http_request' );
		remove_filter( 'woocommerce_available_payment_gateways', '__return_empty_array' );

		$this->incentives_service->clear_cache();

		parent::tear_down();
	}

	public function test_filters_registered_properly() {
		// Assert.
		$this->assertNotFalse( has_action( 'admin_menu', [ $this->incentives_service, 'add_payments_menu_badge' ] ) );
		$this->assertNotFalse( has_filter( 'woocommerce_admin_allowed_promo_notes', [ $this->incentives_service, 'allowed_promo_notes' ] ) );
		$this->assertNotFalse( has_filter( 'woocommerce_admin_woopayments_onboarding_task_badge', [ $this->incentives_service, 'onboarding_task_badge' ] ) );
	}

	public function test_add_payments_menu_badge_without_incentive() {
		global $menu;

		// Arrange.
		$this->mock_cache_with();

		// Act.
		$this->incentives_service->add_payments_menu_badge();

		// Assert.
		$this->assertSame( 'Payments', $menu[0][0] );
	}

	public function test_add_payments_menu_badge_with_incentive() {
		global $menu;

		// Arrange.
		$this->mock_cache_with( [ $this->mock_incentive_data ] );

		// Act.
		$this->incentives_service->add_payments_menu_badge();

		// Assert.
		$this->assertSame( 'Payments' . WC_Payments_Admin::MENU_NOTIFICATION_BADGE, $menu[0][0] );
	}

	public function test_allowed_promo_notes_without_incentive() {
		// Arrange.
		$this->mock_cache_with();

		// Act.
		$promo_notes = $this->incentives_service->allowed_promo_notes();

		// Assert.
		$this->assertEmpty( $promo_notes );
	}

	public function test_allowed_promo_notes_with_incentive() {
		// Arrange.
		$this->mock_cache_with( [ $this->mock_incentive_data ] );

		// Act.
		$promo_notes = $this->incentives_service->allowed_promo_notes();

		// Assert.
		$this->assertContains( $this->mock_incentive_data['id'], $promo_notes );
	}

	public function test_onboarding_task_badge_without_incentive() {
		// Arrange.
		$this->mock_cache_with();

		// Act.
		$badge = $this->incentives_service->onboarding_task_badge( '' );

		// Assert.
		$this->assertEmpty( $badge );
	}

	public function test_onboarding_task_badge_with_incentive_no_task_badge() {
		// Arrange.
		$this->mock_cache_with( [ $this->mock_incentive_data ] );

		// Act.
		$badge = $this->incentives_service->onboarding_task_badge( '' );

		// Assert.
		$this->assertEmpty( $badge );
	}

	public function test_onboarding_task_badge_with_incentive_and_task_badge() {
		// Arrange.
		$incentive_data               = $this->mock_incentive_data;
		$incentive_data['task_badge'] = 'task_badge';
		$this->mock_cache_with( [ $incentive_data ] );

		// Act.
		$badge = $this->incentives_service->onboarding_task_badge( '' );

		// Assert.
		$this->assertSame( $badge, 'task_badge' );
	}

	public function test_get_connect_incentive_non_supported_country() {
		// Arrange.
		add_filter(
			'woocommerce_countries_base_country',
			function () {
				return '__';
			}
		);

		// Act and assert.
		$this->assertNull( $this->incentives_service->get_connect_incentive() );

		// Clean up.
		remove_all_filters( 'woocommerce_countries_base_country' );
	}

	public function test_get_connect_incentive_cached_error() {
		// Arrange.
		$this->mock_cache_with();

		// Act and assert.
		$this->assertNull( $this->incentives_service->get_connect_incentive() );
	}

	public function test_get_cached_connect_incentive_from_cache() {
		// Arrange.
		$this->mock_cache_with( [ $this->mock_incentive_data ] );

		// Act and assert.
		$this->assertSame(
			$this->mock_incentive_data,
			$this->incentives_service->get_connect_incentive()
		);
	}

	public function test_get_connect_incentive_doesnt_refresh_cache_on_same_content_hash() {
		// Arrange.
		$this->mock_cache_with( [ $this->mock_incentive_data ] );
		set_transient( $this->transient_has_orders_key, 'no', HOUR_IN_SECONDS );

		// Act.
		$this->incentives_service->get_connect_incentive();

		// Assert.
		$this->assertSame(
			$this->mock_incentives_cache_data['timestamp'],
			get_transient( $this->transient_incentives_cache_key )['timestamp']
		);

		// Clean up.
		delete_transient( $this->transient_has_orders_key );
	}

	public function test_get_connect_incentive_refreshes_cache_on_wrong_content_hash() {
		// Arrange.
		$this->mock_cache_with( [ $this->mock_incentive_data ] );
		set_transient( $this->transient_has_orders_key, 'yes', HOUR_IN_SECONDS );

		// Act.
		$this->incentives_service->get_connect_incentive();

		// Assert.
		$this->assertNotSame(
			$this->mock_incentives_cache_data['timestamp'],
			get_transient( $this->transient_incentives_cache_key )['timestamp']
		);

		// Clean up.
		delete_transient( $this->transient_has_orders_key );
	}

	public function test_get_connect_incentive_refreshes_cache_on_missing_content_hash() {
		// Arrange.
		$cached_data               = $this->mock_incentives_cache_data;
		$cached_data['incentives'] = [ $this->mock_incentive_data ];
		unset( $cached_data['context_hash'] );
		set_transient( $this->transient_incentives_cache_key, $cached_data, HOUR_IN_SECONDS );

		// Act.
		$this->incentives_service->get_connect_incentive();

		// Assert.
		$this->assertNotSame(
			$this->mock_incentives_cache_data['timestamp'],
			get_transient( $this->transient_incentives_cache_key )['timestamp']
		);
	}

	public function test_get_connect_incentive_error_on_fetch() {
		// Arrange.
		$this->mock_wp_remote_get( new WP_Error() );

		// Act and assert.
		$this->assertNull( $this->incentives_service->get_connect_incentive() );
	}

	public function test_get_connect_incentive_without_incentive_no_cache_for() {
		// Arrange.
		$this->mock_wp_remote_get(
			[
				'response' => [ 'code' => 204 ],
			]
		);

		// Assert.
		add_filter(
			'pre_set_transient_' . $this->transient_incentives_cache_key,
			function ( $value, $expiration ) {
				$this->assertArrayHasKey( 'incentives', $value );
				$this->assertArrayHasKey( 'context_hash', $value );
				$this->assertArrayHasKey( 'timestamp', $value );

				$this->assertEmpty( $value['incentives'] );
				$this->assertSame( $this->mock_incentives_cache_data['context_hash'], $value['context_hash'] );
				$this->assertGreaterThanOrEqual( time(), $value['timestamp'] );

				// Ensure the cache is set to expire in 1 day.
				$this->assertSame( DAY_IN_SECONDS, $expiration );

				return $value;
			},
			10,
			2
		);

		// Act.
		$this->incentives_service->get_connect_incentive();

		// Clean up.
		remove_all_filters( 'pre_set_transient_' . $this->transient_incentives_cache_key );
	}

	public function test_get_connect_incentive_with_incentive_and_cache_for() {
		// Arrange.
		$this->mock_wp_remote_get(
			[
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ $this->mock_incentive_data ] ),
				'headers'  => [
					'cache-for' => '1',
				],
			]
		);

		// Assert.
		add_filter(
			'pre_set_transient_' . $this->transient_incentives_cache_key,
			function ( $value, $expiration ) {
				$this->assertArrayHasKey( 'incentives', $value );
				$this->assertArrayHasKey( 'context_hash', $value );
				$this->assertArrayHasKey( 'timestamp', $value );

				$this->assertSame( [ $this->mock_incentive_data ], $value['incentives'] );
				$this->assertSame( $this->mock_incentives_cache_data['context_hash'], $value['context_hash'] );
				$this->assertGreaterThanOrEqual( time(), $value['timestamp'] );

				// Ensure the cache is set to expire in 1 second.
				$this->assertSame( 1, $expiration );

				return $value;
			},
			10,
			2
		);

		// Act.
		$this->incentives_service->get_connect_incentive();

		// Clean up.
		remove_all_filters( 'pre_set_transient_' . $this->transient_incentives_cache_key );
	}

	public function test_get_connect_incentive_uses_cached_context() {
		$had_woopayments_option_key = WC_Payments_Incentives_Service::PREFIX . 'woopayments_store_had_woopayments';

		// Arrange.
		set_transient( $this->transient_has_orders_key, 'yes', HOUR_IN_SECONDS );
		update_option( $had_woopayments_option_key, 'yes' );
		$call_count = 0;
		add_filter(
			'woocommerce_order_query_args',
			function ( $args ) use ( &$call_count ) {
				$call_count++;

				return $args;
			}
		);

		// Act.
		$this->incentives_service->get_connect_incentive();

		// Assert.
		$this->assertSame( 0, $call_count );

		// Clean up.
		delete_transient( $this->transient_has_orders_key );
		delete_option( $had_woopayments_option_key );
	}

	public function test_get_connect_incentive_has_orders_is_cached_longer_when_has_orders() {
		// Arrange.
		// Mock wc_get_orders to return a mocked order.
		$mock_order = $this->createMock( WC_Order::class );
		$mock_order->method( 'get_id' )->willReturn( 1 );
		$date_created = new WC_DateTime( 'now - 30 day' );
		$mock_order->method( 'get_date_created' )->willReturn( $date_created );
		$this->mock_wc_get_orders( [ $mock_order ] );

		// Assert.
		add_filter(
			'pre_set_transient_' . $this->transient_has_orders_key,
			function ( $value, $expiration ) {
				$this->assertSame( 'yes', $value );

				// Ensure the cache is set to expire in 90 days - 30 days = 60 days.
				$expected_expiration = 60 * DAY_IN_SECONDS;
				// Allowing 5-second difference to avoid flaky tests due to time()  precision.
				$this->assertLessThanOrEqual( 5, abs( $expected_expiration - $expiration ), 'Expiration time should be within 5 second of expected value' );

				return $value;
			},
			10,
			2
		);

		// Act.
		$this->incentives_service->get_connect_incentive();

		// Clean up.
		remove_all_filters( 'woocommerce_order_query' );
		remove_all_filters( 'pre_set_transient_' . $this->transient_has_orders_key );
	}

	public function test_get_connect_incentive_has_orders_caches_when_no_orders() {
		// Arrange.
		// Mock wc_get_orders to return an empty array.
		$this->mock_wc_get_orders( [] );

		// Assert.
		add_filter(
			'pre_set_transient_' . $this->transient_has_orders_key,
			function ( $value, $expiration ) {
				$this->assertSame( 'no', $value );

				// Ensure the cache is set to expire in 6 hours.
				$this->assertSame( 6 * HOUR_IN_SECONDS, $expiration );

				return $value;
			},
			10,
			2
		);

		// Act.
		$this->incentives_service->get_connect_incentive();

		// Clean up.
		remove_all_filters( 'woocommerce_order_query' );
		remove_all_filters( 'pre_set_transient_' . $this->transient_has_orders_key );
	}

	/**
	 * Mocks the cache with the given incentives list.
	 *
	 * @param array $incentives The incentives list to mock with.
	 *
	 * @return void
	 */
	private function mock_cache_with( array $incentives = [] ) {
		$cache_data               = $this->mock_incentives_cache_data;
		$cache_data['incentives'] = $incentives;

		set_transient( $this->transient_incentives_cache_key, $cache_data, HOUR_IN_SECONDS );
	}

	/**
	 * Helper method to mock the remote request to the incentives endpoint.
	 *
	 * @param array|WP_Error $response The response to return from the HTTP request.
	 *
	 * @return void
	 */
	private function mock_wp_remote_get( $response ) {
		add_filter(
			'pre_http_request',
			function ( $value, $parsed_args, $url ) use ( $response ) {
				if ( str_starts_with( $url, 'https://public-api.wordpress.com/wpcom/v2/wcpay/incentives' ) ) {
					return $response;
				}

				return $value;
			},
			10,
			3
		);
	}

	/**
	 * Helper method to mock wc_get_orders function
	 *
	 * @param array $orders The orders to return from the mock.
	 */
	private function mock_wc_get_orders( $orders ) {
		add_filter(
			'woocommerce_order_query',
			function () use ( $orders ) {
				return $orders;
			}
		);
	}
}

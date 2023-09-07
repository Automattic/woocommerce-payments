<?php
/**
 * Class WC_Payments_Incentives_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
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
	 * Mock Database_Cache
	 *
	 * @var MockObject
	 */
	private $mock_database_cache;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_database_cache = $this->createMock( Database_Cache::class );
		$this->incentives_service  = new WC_Payments_Incentives_Service( $this->mock_database_cache );

		global $menu;
		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$menu = [
			[ 'Payments', null, 'wc-admin&path=/payments/connect' ],
		];
	}

	public function tear_down() {
		parent::tear_down();

		global $menu;
		$menu = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Mocked incentive data.
	 *
	 * @var array
	 */
	private $mock_incentive_data = [
		'incentive'    => [
			'id'          => 'incentive_id',
			'type'        => 'connect_page',
			'description' => 'incentive_description',
			'tc_url'      => 'incentive_tc_url',
		],
		// This is the hash of the test store context:
		// 'country' => 'US',
		// 'locale' => 'en_US',
		// 'has_orders' => false,
		// 'has_payments' => false,
		// 'has_wcpay' => false.
		'context_hash' => '6d37bc19d822af681f896b21065134c7',
	];

	public function test_filters_registered_properly() {
		$this->assertNotFalse( has_action( 'admin_menu', [ $this->incentives_service, 'add_payments_menu_badge' ] ) );
		$this->assertNotFalse( has_filter( 'woocommerce_admin_allowed_promo_notes', [ $this->incentives_service, 'allowed_promo_notes' ] ) );
		$this->assertNotFalse( has_filter( 'woocommerce_admin_woopayments_onboarding_task_badge', [ $this->incentives_service, 'onboarding_task_badge' ] ) );
	}

	public function test_add_payments_menu_badge_without_incentive() {
		global $menu;

		$this->mock_database_cache_with();

		$this->incentives_service->add_payments_menu_badge();

		$this->assertSame( 'Payments', $menu[0][0] );
	}

	public function test_add_payments_menu_badge_with_incentive() {
		global $menu;

		$this->mock_database_cache_with( $this->mock_incentive_data );

		$this->incentives_service->add_payments_menu_badge();

		$this->assertSame( 'Payments' . WC_Payments_Admin::MENU_NOTIFICATION_BADGE, $menu[0][0] );
	}

	public function test_allowed_promo_notes_without_incentive() {
		$this->mock_database_cache_with();

		$promo_notes = $this->incentives_service->allowed_promo_notes();

		$this->assertEmpty( $promo_notes );
	}

	public function test_allowed_promo_notes_with_incentive() {
		$this->mock_database_cache_with( $this->mock_incentive_data );

		$promo_notes = $this->incentives_service->allowed_promo_notes();

		$this->assertContains( $this->mock_incentive_data['incentive']['id'], $promo_notes );
	}

	public function test_onboarding_task_badge_without_incentive() {
		$this->mock_database_cache_with();

		$badge = $this->incentives_service->onboarding_task_badge( '' );

		$this->assertEmpty( $badge );
	}

	public function test_onboarding_task_badge_with_incentive_no_task_badge() {
		$this->mock_database_cache_with( $this->mock_incentive_data );

		$badge = $this->incentives_service->onboarding_task_badge( '' );

		$this->assertEmpty( $badge );
	}

	public function test_onboarding_task_badge_with_incentive_and_task_badge() {
		$incentive_data                            = $this->mock_incentive_data;
		$incentive_data['incentive']['task_badge'] = 'task_badge';
		$this->mock_database_cache_with( $incentive_data );

		$badge = $this->incentives_service->onboarding_task_badge( '' );

		$this->assertEquals( $badge, 'task_badge' );
	}

	public function test_get_cached_connect_incentive_non_supported_country() {
		add_filter(
			'woocommerce_countries_base_country',
			function() {
				return '__';
			}
		);

		$this->assertNull( $this->incentives_service->get_cached_connect_incentive() );
	}

	public function test_get_cached_connect_incentive_cached_error() {
		$this->mock_database_cache_with();

		$this->assertNull( $this->incentives_service->get_cached_connect_incentive() );
	}

	public function test_get_cached_connect_incentive_from_cache() {
		$this->mock_database_cache_with( $this->mock_incentive_data );

		$this->assertEquals(
			$this->mock_incentive_data['incentive'],
			$this->incentives_service->get_cached_connect_incentive()
		);
	}

	public function test_get_cached_connect_incentive_doesnt_refresh_cache_on_same_content_hash() {
		$this->mock_database_cache
			->method( 'get' )
			->willReturn( $this->mock_incentive_data );

		$this->mock_database_cache
			->expects( $this->never() )
			->method( 'get_or_add' );

		$this->assertEquals(
			$this->mock_incentive_data['incentive'],
			$this->incentives_service->get_cached_connect_incentive()
		);
	}

	public function test_get_cached_connect_incentive_refreshes_cache_on_wrong_content_hash() {
		$this->mock_database_cache
			->method( 'get' )
			->willReturn( array_merge( $this->mock_incentive_data, [ 'context_hash' => 'wrong_hash' ] ) );

		$this->mock_database_cache
			->expects( $this->atLeastOnce() )
			->method( 'get_or_add' )
			->willReturn( $this->mock_incentive_data );

		$this->assertEquals(
			$this->mock_incentive_data['incentive'],
			$this->incentives_service->get_cached_connect_incentive()
		);
	}

	public function test_get_cached_connect_incentive_refreshes_cache_on_missing_content_hash() {
		$this->mock_database_cache
			->method( 'get' )
			->willReturn( array_merge( $this->mock_incentive_data, [ 'context_hash' => null ] ) );

		$this->mock_database_cache
			->expects( $this->atLeastOnce() )
			->method( 'get_or_add' )
			->willReturn( $this->mock_incentive_data );

		$this->assertEquals(
			$this->mock_incentive_data['incentive'],
			$this->incentives_service->get_cached_connect_incentive()
		);
	}

	public function test_fetch_connect_incentive_error() {
		$this->mock_wp_remote_get( new WP_Error() );

		$this->assertNull( $this->incentives_service->fetch_connect_incentive_details() );
	}

	public function test_fetch_connect_incentive_without_incentive_no_cache_for() {
		$this->mock_wp_remote_get(
			[
				'response' => [ 'code' => 204 ],
			]
		);

		$expected = [
			'incentive'    => [],
			'ttl'          => 86400,
			'context_hash' => '6d37bc19d822af681f896b21065134c7',
		];

		$this->assertSame( $expected, $this->incentives_service->fetch_connect_incentive_details() );
	}

	public function test_fetch_connect_incentive_with_incentive_and_cache_for() {
		$this->mock_wp_remote_get(
			[
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ $this->mock_incentive_data['incentive'] ] ),
				'headers'  => [
					'cache-for' => '1',
				],
			]
		);

		$expected = array_merge( $this->mock_incentive_data, [ 'ttl' => 1 ] );
		ksort( $expected );

		$result = $this->incentives_service->fetch_connect_incentive_details();
		ksort( $result );

		$this->assertSame( $expected, $result );
	}

	private function mock_database_cache_with( $incentive = null ) {
		$this->mock_database_cache
			->method( 'get_or_add' )
			->willReturn( $incentive );
	}

	private function mock_wp_remote_get( $response ) {
		add_filter(
			'pre_http_request',
			function() use ( $response ) {
				return $response;
			}
		);
	}
}

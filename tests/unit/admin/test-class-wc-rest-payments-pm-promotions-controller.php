<?php
/**
 * Class WC_REST_Payments_Promotions_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_REST_Payments_Promotions_Controller unit tests.
 */
class WC_REST_Payments_Promotions_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_PM_Promotions_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * @var WC_Payments_PM_Promotions_Service
	 */
	private $promotions_service;

	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->mock_api_client    = $this->createMock( WC_Payments_API_Client::class );
		$this->promotions_service = new WC_Payments_PM_Promotions_Service();

		$this->controller = new WC_REST_Payments_PM_Promotions_Controller( $this->mock_api_client, $this->promotions_service );
	}

	public function tear_down() {
		parent::tear_down();
		delete_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		delete_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION );
		delete_option( WC_Payments_PM_Promotions_Service::ACTIVATED_PROMOTIONS_OPTION );
		$this->promotions_service->reset_memo();
	}

	public function test_get_promotions_returns_cached_data() {
		$mock_promotions = [
			[
				'promo_id'      => 'test_promo',
				'discount_rate' => '100%',
				'duration_days' => 90,
				'variations'    => [
					[
						'id'          => 'test_promo__spotlight_1',
						'type'        => 'spotlight',
						'heading'     => 'Test Promotion',
						'description' => 'Test description',
						'cta_label'   => 'Activate',
						'cta_url'     => '#',
						'tc_url'      => 'https://example.com/terms',
					],
				],
			],
		];

		// Generate the context hash to match what the service will generate.
		$store_context = [
			'dismissals' => WC_Payments_PM_Promotions_Service::get_promotion_dismissals(),
			'locale'     => get_locale(),
		];
		$context_hash  = md5(
			wp_json_encode(
				[
					'dismissals' => $store_context['dismissals'],
					'locale'     => $store_context['locale'],
				]
			)
		);

		// Set the cache with the proper structure including context_hash.
		set_transient(
			WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY,
			[
				'promotions'   => $mock_promotions,
				'context_hash' => $context_hash,
				'timestamp'    => time(),
			],
			300
		);

		$request  = new WP_REST_Request( 'GET' );
		$response = $this->controller->get_promotions( $request );

		$this->assertSame( 200, $response->status );
		$this->assertSame( $mock_promotions, $response->get_data() );
	}

	public function test_get_promotion_dismissals() {
		$dismissals = [
			'promo1' => [
				'promo1__spotlight_1' => 1234567890,
			],
			'promo2' => [
				'promo2__spotlight_1' => 1234567900,
			],
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$result = WC_Payments_PM_Promotions_Service::get_promotion_dismissals();

		$this->assertSame( $dismissals, $result );
	}

	public function test_get_promotion_variation_dismissals() {
		$dismissals = [
			'promo1' => [
				'promo1__spotlight_1' => 1234567890,
				'promo1__spotlight_2' => 1234567900,
			],
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$result = WC_Payments_PM_Promotions_Service::get_promotion_variation_dismissals( 'promo1' );

		$this->assertSame( $dismissals['promo1'], $result );
		$this->assertSame( [], WC_Payments_PM_Promotions_Service::get_promotion_variation_dismissals( 'promo3' ) );
	}

	public function test_get_variation_dismissal_time() {
		$timestamp  = 1234567890;
		$dismissals = [
			'promo1' => [
				'promo1__spotlight_1' => $timestamp,
			],
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$result = WC_Payments_PM_Promotions_Service::get_variation_dismissal_time( 'promo1', 'promo1__spotlight_1' );

		$this->assertSame( $timestamp, $result );
		$this->assertNull( WC_Payments_PM_Promotions_Service::get_variation_dismissal_time( 'promo1', 'promo1__spotlight_2' ) );
		$this->assertNull( WC_Payments_PM_Promotions_Service::get_variation_dismissal_time( 'promo2', 'promo2__spotlight_1' ) );
	}

	public function test_get_activated_promotions() {
		$activated = [
			'promo1' => 1234567890,
			'promo2' => 1234567900,
		];
		update_option( WC_Payments_PM_Promotions_Service::ACTIVATED_PROMOTIONS_OPTION, $activated );

		$result = WC_Payments_PM_Promotions_Service::get_activated_promotions();

		$this->assertSame( $activated, $result );
	}

	public function test_is_promotion_activated() {
		$activated = [
			'promo1' => 1234567890,
			'promo2' => 1234567900,
		];
		update_option( WC_Payments_PM_Promotions_Service::ACTIVATED_PROMOTIONS_OPTION, $activated );

		$this->assertTrue( WC_Payments_PM_Promotions_Service::is_promotion_activated( 'promo1' ) );
		$this->assertTrue( WC_Payments_PM_Promotions_Service::is_promotion_activated( 'promo2' ) );
		$this->assertFalse( WC_Payments_PM_Promotions_Service::is_promotion_activated( 'promo3' ) );
	}

	public function test_get_promotion_activation_time() {
		$timestamp = 1234567890;
		$activated = [
			'promo1' => $timestamp,
		];
		update_option( WC_Payments_PM_Promotions_Service::ACTIVATED_PROMOTIONS_OPTION, $activated );

		$result = WC_Payments_PM_Promotions_Service::get_promotion_activation_time( 'promo1' );

		$this->assertSame( $timestamp, $result );
		$this->assertNull( WC_Payments_PM_Promotions_Service::get_promotion_activation_time( 'promo2' ) );
	}

	/**
	 * Test that dismissing a promotion records the timestamp and clears cache.
	 */
	public function test_dismiss_promotion_records_timestamp_and_clears_cache() {
		// First, get promotions to populate cache.
		$this->controller->get_promotions();

		// Verify cache is set.
		$cache_before = get_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		$this->assertNotFalse( $cache_before, 'Cache should be set after getting promotions' );

		// Dismiss the primary spotlight.
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'identifier', 'klarna-2026-promo' );
		$request->set_param( 'variation_id', 'klarna-2026-promo__spotlight_primary' );

		$response = $this->controller->dismiss_promotion( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertSame( 'dismissed', $data['status'] );

		// Verify dismissal was recorded.
		$dismissal_time = WC_Payments_PM_Promotions_Service::get_variation_dismissal_time(
			'klarna-2026-promo',
			'klarna-2026-promo__spotlight_primary'
		);
		$this->assertNotNull( $dismissal_time );
		$this->assertEqualsWithDelta( time(), $dismissal_time, 5 ); // Within 5 seconds.

		// Verify cache was cleared.
		$cache_after = get_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		$this->assertFalse( $cache_after, 'Cache should be cleared after dismissal' );
	}

	/**
	 * Test that dismissals are included in store context for server requests.
	 *
	 * When dismissals change, the context hash changes, which triggers a fresh
	 * request to the server (instead of using cached data).
	 */
	public function test_dismissals_invalidate_cache_for_fresh_server_request() {
		// Get promotions to populate cache.
		$this->controller->get_promotions();

		// Store the current cache.
		$cache_before = get_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		$this->assertNotFalse( $cache_before );
		$hash_before = $cache_before['context_hash'];

		// Add a dismissal (simulating what happens after dismiss_promotion clears cache).
		$dismissals = [
			'klarna-2026-promo' => [
				'klarna-2026-promo__spotlight_primary' => time(),
			],
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		// Clear cache and memo to simulate fresh request.
		delete_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		$this->promotions_service->reset_memo();

		// Get promotions again - this should create a new cache with different hash.
		$this->controller->get_promotions();

		$cache_after = get_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		$this->assertNotFalse( $cache_after );
		$hash_after = $cache_after['context_hash'];

		// The context hash should be different because dismissals changed.
		$this->assertNotSame( $hash_before, $hash_after, 'Context hash should change when dismissals change' );
	}
}

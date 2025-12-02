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
		// Mock promotions in the new flat structure.
		$mock_promotions = [
			[
				'id'             => 'test_promo__spotlight',
				'promo_id'       => 'test_promo',
				'payment_method' => 'klarna',
				'type'           => 'spotlight',
				'title'          => 'Test Promotion',
				'description'    => 'Test description',
				'cta_label'      => 'Enable Klarna',
				'tc_url'         => 'https://example.com/terms',
				'tc_label'       => 'See terms',
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
		// Note: The response may be filtered/transformed by the service.
		$this->assertIsArray( $response->get_data() );
	}

	public function test_get_promotion_dismissals() {
		// New flat structure: [id => timestamp].
		$dismissals = [
			'promo1__spotlight' => 1234567890,
			'promo2__spotlight' => 1234567900,
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$result = WC_Payments_PM_Promotions_Service::get_promotion_dismissals();

		$this->assertSame( $dismissals, $result );
	}

	public function test_is_promotion_dismissed() {
		$dismissals = [
			'promo1__spotlight' => 1234567890,
			'promo1__badge'     => 1234567900,
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$this->assertTrue( WC_Payments_PM_Promotions_Service::is_promotion_dismissed( 'promo1__spotlight' ) );
		$this->assertTrue( WC_Payments_PM_Promotions_Service::is_promotion_dismissed( 'promo1__badge' ) );
		$this->assertFalse( WC_Payments_PM_Promotions_Service::is_promotion_dismissed( 'promo2__spotlight' ) );
	}

	public function test_get_promotion_dismissal_time() {
		$timestamp  = 1234567890;
		$dismissals = [
			'promo1__spotlight' => $timestamp,
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$result = WC_Payments_PM_Promotions_Service::get_promotion_dismissal_time( 'promo1__spotlight' );

		$this->assertSame( $timestamp, $result );
		$this->assertNull( WC_Payments_PM_Promotions_Service::get_promotion_dismissal_time( 'promo1__badge' ) );
		$this->assertNull( WC_Payments_PM_Promotions_Service::get_promotion_dismissal_time( 'promo2__spotlight' ) );
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
}

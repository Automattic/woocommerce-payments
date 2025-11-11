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
	 * @var WC_REST_Payments_Promotions_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->controller = new WC_REST_Payments_Promotions_Controller( $this->mock_api_client );
	}

	public function tear_down() {
		parent::tear_down();
		delete_transient( WC_REST_Payments_Promotions_Controller::PROMOTIONS_CACHE_KEY );
		delete_option( WC_REST_Payments_Promotions_Controller::DISMISSED_PROMOTIONS_OPTION );
		delete_option( WC_REST_Payments_Promotions_Controller::ACTIVATED_PROMOTIONS_OPTION );
	}

	public function test_get_promotions_returns_cached_data() {
		$mock_promotions = [
			'available_promotions' => [
				[
					'identifier'     => 'test_promo',
					'name'           => 'Test Promotion',
					'payment_method' => 'klarna',
					'duration_days'  => 90,
					'status'         => 'offered',
				],
			],
			'active_promotions'    => [],
		];

		// Set the cache.
		set_transient( WC_REST_Payments_Promotions_Controller::PROMOTIONS_CACHE_KEY, $mock_promotions, 300 );

		$request  = new WP_REST_Request( 'GET' );
		$response = $this->controller->get_promotions( $request );

		$this->assertSame( 200, $response->status );
		$this->assertSame( $mock_promotions, $response->get_data() );
	}

	public function test_mark_promotion_dismissed() {
		$identifier = 'test_promo';

		// Verify it's not dismissed initially.
		$this->assertFalse( WC_REST_Payments_Promotions_Controller::is_promotion_dismissed( $identifier ) );

		// Simulate dismissing the promotion.
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'identifier', $identifier );

		// We need to mock the API call since we can't actually call the server.
		$this->markTestIncomplete( 'This test requires mocking the Request::create() static method.' );
	}

	public function test_get_dismissed_promotions() {
		$dismissed = [ 'promo1', 'promo2' ];
		update_option( WC_REST_Payments_Promotions_Controller::DISMISSED_PROMOTIONS_OPTION, $dismissed );

		$result = WC_REST_Payments_Promotions_Controller::get_dismissed_promotions();

		$this->assertSame( $dismissed, $result );
	}

	public function test_is_promotion_dismissed() {
		$dismissed = [ 'promo1', 'promo2' ];
		update_option( WC_REST_Payments_Promotions_Controller::DISMISSED_PROMOTIONS_OPTION, $dismissed );

		$this->assertTrue( WC_REST_Payments_Promotions_Controller::is_promotion_dismissed( 'promo1' ) );
		$this->assertTrue( WC_REST_Payments_Promotions_Controller::is_promotion_dismissed( 'promo2' ) );
		$this->assertFalse( WC_REST_Payments_Promotions_Controller::is_promotion_dismissed( 'promo3' ) );
	}

	public function test_get_activated_promotions() {
		$activated = [
			'promo1' => 1234567890,
			'promo2' => 1234567900,
		];
		update_option( WC_REST_Payments_Promotions_Controller::ACTIVATED_PROMOTIONS_OPTION, $activated );

		$result = WC_REST_Payments_Promotions_Controller::get_activated_promotions();

		$this->assertSame( $activated, $result );
	}

	public function test_is_promotion_activated() {
		$activated = [
			'promo1' => 1234567890,
			'promo2' => 1234567900,
		];
		update_option( WC_REST_Payments_Promotions_Controller::ACTIVATED_PROMOTIONS_OPTION, $activated );

		$this->assertTrue( WC_REST_Payments_Promotions_Controller::is_promotion_activated( 'promo1' ) );
		$this->assertTrue( WC_REST_Payments_Promotions_Controller::is_promotion_activated( 'promo2' ) );
		$this->assertFalse( WC_REST_Payments_Promotions_Controller::is_promotion_activated( 'promo3' ) );
	}

	public function test_get_promotion_activation_time() {
		$timestamp = 1234567890;
		$activated = [
			'promo1' => $timestamp,
		];
		update_option( WC_REST_Payments_Promotions_Controller::ACTIVATED_PROMOTIONS_OPTION, $activated );

		$result = WC_REST_Payments_Promotions_Controller::get_promotion_activation_time( 'promo1' );

		$this->assertSame( $timestamp, $result );
		$this->assertNull( WC_REST_Payments_Promotions_Controller::get_promotion_activation_time( 'promo2' ) );
	}
}

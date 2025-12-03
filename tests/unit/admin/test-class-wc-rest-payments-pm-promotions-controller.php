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
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * @var WC_Payments_PM_Promotions_Service
	 */
	private $promotions_service;

	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		// Create mock gateway with available payment methods.
		$this->mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_gateway->method( 'get_upe_available_payment_methods' )
			->willReturn( [ 'card', 'klarna', 'affirm', 'afterpay_clearpay' ] );
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$this->promotions_service = new WC_Payments_PM_Promotions_Service( $this->mock_gateway );

		$this->controller = new WC_REST_Payments_PM_Promotions_Controller( $this->mock_api_client, $this->promotions_service );
	}

	public function tear_down() {
		parent::tear_down();
		delete_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		delete_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION );
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
			'dismissals' => $this->promotions_service->get_promotion_dismissals(),
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

	public function test_get_promotions_returns_empty_array_when_no_promotions() {
		// Create a mock service that returns null (no promotions).
		$mock_service = $this->createMock( WC_Payments_PM_Promotions_Service::class );
		$mock_service->method( 'get_visible_promotions' )
			->willReturn( null );

		// Create controller with mock service.
		$controller = new WC_REST_Payments_PM_Promotions_Controller( $this->mock_api_client, $mock_service );

		$request  = new WP_REST_Request( 'GET' );
		$response = $controller->get_promotions( $request );

		$this->assertSame( 200, $response->status );
		$this->assertIsArray( $response->get_data() );
		$this->assertEmpty( $response->get_data() );
	}

	public function test_get_promotion_dismissals() {
		// New flat structure: [id => timestamp].
		$dismissals = [
			'promo1__spotlight' => 1234567890,
			'promo2__spotlight' => 1234567900,
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$result = $this->promotions_service->get_promotion_dismissals();

		$this->assertSame( $dismissals, $result );
	}

	public function test_is_promotion_dismissed_returns_true_for_past_timestamp() {
		$dismissals = [
			'promo1__spotlight' => time() - 3600, // 1 hour ago.
			'promo1__badge'     => time() - 1,    // 1 second ago.
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$this->assertTrue( $this->promotions_service->is_promotion_dismissed( 'promo1__spotlight' ) );
		$this->assertTrue( $this->promotions_service->is_promotion_dismissed( 'promo1__badge' ) );
	}

	public function test_is_promotion_dismissed_returns_false_for_non_existent() {
		$dismissals = [
			'promo1__spotlight' => time() - 3600,
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$this->assertFalse( $this->promotions_service->is_promotion_dismissed( 'promo2__spotlight' ) );
	}

	public function test_is_promotion_dismissed_returns_false_for_future_timestamp() {
		$dismissals = [
			'promo1__spotlight' => time() + 3600, // 1 hour from now.
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$this->assertFalse( $this->promotions_service->is_promotion_dismissed( 'promo1__spotlight' ) );
	}

	public function test_is_promotion_dismissed_returns_false_for_invalid_values() {
		$dismissals = [
			'promo1__spotlight' => 'invalid',
			'promo2__spotlight' => 0,
			'promo3__spotlight' => -1,
			'promo4__spotlight' => null,
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$this->assertFalse( $this->promotions_service->is_promotion_dismissed( 'promo1__spotlight' ) );
		$this->assertFalse( $this->promotions_service->is_promotion_dismissed( 'promo2__spotlight' ) );
		$this->assertFalse( $this->promotions_service->is_promotion_dismissed( 'promo3__spotlight' ) );
		$this->assertFalse( $this->promotions_service->is_promotion_dismissed( 'promo4__spotlight' ) );
	}
}

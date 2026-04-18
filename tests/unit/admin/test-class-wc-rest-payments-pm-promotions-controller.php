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

		// Create and set an admin user (required for permission checks).
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

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

	public function test_get_promotions_returns_promotions_from_service() {
		// Mock promotions in the flat structure.
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

		// Create a mock service that returns the promotions directly.
		$mock_service = $this->createMock( WC_Payments_PM_Promotions_Service::class );
		$mock_service->method( 'get_visible_promotions' )
			->willReturn( $mock_promotions );

		// Create controller with mock service.
		$controller = new WC_REST_Payments_PM_Promotions_Controller( $this->mock_api_client, $mock_service );

		$request  = new WP_REST_Request( 'GET' );
		$response = $controller->get_promotions( $request );

		$this->assertSame( 200, $response->status );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertCount( 1, $data );
		$this->assertSame( 'test_promo__spotlight', $data[0]['id'] );
		$this->assertSame( 'klarna', $data[0]['payment_method'] );
		$this->assertSame( 'spotlight', $data[0]['type'] );
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
}

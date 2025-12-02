<?php
/**
 * Class WC_REST_Payments_PM_Promotions_Controller_Integration_Test
 *
 * Integration tests for the PM Promotions REST controller endpoints.
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_REST_Payments_PM_Promotions_Controller integration tests.
 *
 * Tests the REST API endpoints for PM promotions.
 */
class WC_REST_Payments_PM_Promotions_Controller_Integration_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_PM_Promotions_Controller
	 */
	private $controller;

	/**
	 * Controller with mocked service for isolated endpoint testing.
	 *
	 * @var WC_REST_Payments_PM_Promotions_Controller
	 */
	private $controller_with_mock;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * @var WC_Payments_PM_Promotions_Service|MockObject
	 */
	private $mock_promotions_service;

	/**
	 * @var WC_Payments_PM_Promotions_Service
	 */
	private $promotions_service;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * REST route base.
	 *
	 * @var string
	 */
	private $rest_base = '/wc/v3/payments/pm-promotions';

	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->mock_api_client         = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_promotions_service = $this->createMock( WC_Payments_PM_Promotions_Service::class );

		// Create mock gateway with available payment methods for integration tests.
		$this->mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_gateway->method( 'get_upe_available_payment_methods' )
			->willReturn( [ 'card', 'klarna', 'affirm', 'afterpay_clearpay' ] );
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] ); // No PMs enabled, so promotions will show.

		// Real service with mock gateway for integration tests.
		$this->promotions_service = new WC_Payments_PM_Promotions_Service( $this->mock_gateway );

		// Controller with real service for integration tests.
		$this->controller = new WC_REST_Payments_PM_Promotions_Controller(
			$this->mock_api_client,
			$this->promotions_service
		);

		// Controller with mocked service for isolated endpoint tests.
		$this->controller_with_mock = new WC_REST_Payments_PM_Promotions_Controller(
			$this->mock_api_client,
			$this->mock_promotions_service
		);
	}

	public function tear_down() {
		parent::tear_down();
		delete_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		delete_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION );
		delete_option( WC_Payments_PM_Promotions_Service::ACTIVATED_PROMOTIONS_OPTION );
		$this->promotions_service->reset_memo();
	}

	/**
	 * Helper to create a valid promotion array.
	 *
	 * @param array $overrides Optional overrides.
	 *
	 * @return array Promotion data.
	 */
	private function create_valid_promotion( array $overrides = [] ): array {
		return array_merge(
			[
				'id'                   => 'test-promo__spotlight',
				'promo_id'             => 'test-promo',
				'payment_method'       => 'klarna',
				'payment_method_title' => 'Klarna',
				'type'                 => 'spotlight',
				'title'                => 'Test Promotion',
				'description'          => 'Test description',
				'cta_label'            => 'Enable Now',
				'tc_url'               => 'https://example.com/terms',
				'tc_label'             => 'See terms',
			],
			$overrides
		);
	}

	/*
	 * =========================================================================
	 * GET PROMOTIONS ENDPOINT TESTS
	 * =========================================================================
	 */

	public function test_get_promotions_returns_200_response() {
		$this->mock_promotions_service->method( 'get_visible_promotions' )
			->willReturn( [ $this->create_valid_promotion() ] );

		$request  = new WP_REST_Request( 'GET', $this->rest_base );
		$response = $this->controller_with_mock->get_promotions( $request );

		$this->assertSame( 200, $response->status );
	}

	public function test_get_promotions_returns_array_of_promotions() {
		$promotions = [
			$this->create_valid_promotion( [ 'id' => 'promo1__spotlight' ] ),
			$this->create_valid_promotion(
				[
					'id'   => 'promo2__badge',
					'type' => 'badge',
				]
			),
		];

		$this->mock_promotions_service->method( 'get_visible_promotions' )
			->willReturn( $promotions );

		$request  = new WP_REST_Request( 'GET', $this->rest_base );
		$response = $this->controller_with_mock->get_promotions( $request );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertCount( 2, $data );
	}

	public function test_get_promotions_returns_empty_array_when_no_promotions() {
		$this->mock_promotions_service->method( 'get_visible_promotions' )
			->willReturn( null );

		$request  = new WP_REST_Request( 'GET', $this->rest_base );
		$response = $this->controller_with_mock->get_promotions( $request );

		// Controller converts null to empty array for consistent REST response.
		$this->assertSame( [], $response->get_data() );
	}

	public function test_get_promotions_returns_promotion_with_all_fields() {
		$promotion = $this->create_valid_promotion(
			[
				'footnote' => 'Test footnote',
				'image'    => 'https://example.com/image.png',
			]
		);

		$this->mock_promotions_service->method( 'get_visible_promotions' )
			->willReturn( [ $promotion ] );

		$request  = new WP_REST_Request( 'GET', $this->rest_base );
		$response = $this->controller_with_mock->get_promotions( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertArrayHasKey( 'promo_id', $data[0] );
		$this->assertArrayHasKey( 'payment_method', $data[0] );
		$this->assertArrayHasKey( 'payment_method_title', $data[0] );
		$this->assertArrayHasKey( 'type', $data[0] );
		$this->assertArrayHasKey( 'title', $data[0] );
		$this->assertArrayHasKey( 'description', $data[0] );
		$this->assertArrayHasKey( 'cta_label', $data[0] );
		$this->assertArrayHasKey( 'tc_url', $data[0] );
		$this->assertArrayHasKey( 'tc_label', $data[0] );
		$this->assertArrayHasKey( 'footnote', $data[0] );
		$this->assertArrayHasKey( 'image', $data[0] );
	}

	/*
	 * =========================================================================
	 * ACTIVATE PROMOTION ENDPOINT TESTS
	 * =========================================================================
	 */

	public function test_activate_promotion_calls_service_with_identifier() {
		$identifier = 'test-promo';

		$this->mock_promotions_service->expects( $this->once() )
			->method( 'activate_promotion' )
			->with( $identifier )
			->willReturn(
				[
					'success'    => true,
					'identifier' => $identifier,
					'status'     => 'active',
				]
			);

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $identifier . '/activate' );
		$request->set_param( 'identifier', $identifier );

		$response = $this->controller_with_mock->activate_promotion( $request );

		$this->assertSame( 200, $response->status );
	}

	public function test_activate_promotion_returns_success_response() {
		$identifier = 'test-promo';

		$this->mock_promotions_service->method( 'activate_promotion' )
			->willReturn(
				[
					'success'    => true,
					'identifier' => $identifier,
					'status'     => 'active',
				]
			);

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $identifier . '/activate' );
		$request->set_param( 'identifier', $identifier );

		$response = $this->controller_with_mock->activate_promotion( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertSame( 'active', $data['status'] );
		$this->assertSame( $identifier, $data['identifier'] );
	}

	public function test_activate_promotion_integration_stores_activation() {
		$identifier = 'test-promo';

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $identifier . '/activate' );
		$request->set_param( 'identifier', $identifier );

		$this->controller->activate_promotion( $request );

		$this->assertTrue( WC_Payments_PM_Promotions_Service::is_promotion_activated( $identifier ) );
	}

	/*
	 * =========================================================================
	 * DISMISS PROMOTION ENDPOINT TESTS
	 * =========================================================================
	 */

	public function test_dismiss_promotion_calls_service_with_id() {
		$id = 'test-promo__spotlight';

		$this->mock_promotions_service->expects( $this->once() )
			->method( 'dismiss_promotion' )
			->with( $id )
			->willReturn( true );

		// URL params must be set explicitly when calling controller directly (not routed via REST API).
		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/dismiss' );
		$request->set_param( 'id', $id );

		$response = $this->controller_with_mock->dismiss_promotion( $request );

		$this->assertSame( 200, $response->status );
	}

	public function test_dismiss_promotion_returns_success_true_when_dismissed() {
		$id = 'test-promo__spotlight';

		$this->mock_promotions_service->method( 'dismiss_promotion' )
			->willReturn( true );

		// URL params must be set explicitly when calling controller directly (not routed via REST API).
		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/dismiss' );
		$request->set_param( 'id', $id );

		$response = $this->controller_with_mock->dismiss_promotion( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
	}

	public function test_dismiss_promotion_returns_success_false_when_already_dismissed() {
		$id = 'test-promo__spotlight';

		$this->mock_promotions_service->method( 'dismiss_promotion' )
			->willReturn( false );

		// URL params must be set explicitly when calling controller directly (not routed via REST API).
		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/dismiss' );
		$request->set_param( 'id', $id );

		$response = $this->controller_with_mock->dismiss_promotion( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
	}

	public function test_dismiss_promotion_integration_stores_dismissal() {
		$id = 'test-promo__spotlight';

		// URL params must be set explicitly when calling controller directly (not routed via REST API).
		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/dismiss' );
		$request->set_param( 'id', $id );

		$this->controller->dismiss_promotion( $request );

		$this->assertTrue( WC_Payments_PM_Promotions_Service::is_promotion_dismissed( $id ) );
	}

	/*
	 * =========================================================================
	 * ROUTE REGISTRATION TESTS
	 * =========================================================================
	 */

	public function test_register_routes_creates_get_endpoint() {
		// Expect the "incorrect usage" notice since we're calling outside rest_api_init.
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();
		$route  = '/wc/v3/payments/pm-promotions';

		$this->assertArrayHasKey( $route, $routes );
		$this->assertContains( 'GET', array_keys( $routes[ $route ][0]['methods'] ) );
	}

	public function test_register_routes_creates_activate_endpoint() {
		// Expect the "incorrect usage" notice since we're calling outside rest_api_init.
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();
		$route  = '/wc/v3/payments/pm-promotions/(?P<identifier>[a-zA-Z0-9_-]+)/activate';

		$this->assertArrayHasKey( $route, $routes );
		$this->assertContains( 'POST', array_keys( $routes[ $route ][0]['methods'] ) );
	}

	public function test_register_routes_creates_dismiss_endpoint() {
		// Expect the "incorrect usage" notice since we're calling outside rest_api_init.
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();
		$route  = '/wc/v3/payments/pm-promotions/(?P<id>[a-zA-Z0-9_-]+)/dismiss';

		$this->assertArrayHasKey( $route, $routes );
		$this->assertContains( 'POST', array_keys( $routes[ $route ][0]['methods'] ) );
	}

	/*
	 * =========================================================================
	 * PERMISSION TESTS
	 * =========================================================================
	 */

	public function test_check_permission_returns_true_for_admin() {
		// User 1 is an admin.
		wp_set_current_user( 1 );

		$result = $this->controller->check_permission();

		$this->assertTrue( $result );
	}

	public function test_check_permission_returns_false_for_non_admin() {
		// Create a subscriber user.
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->controller->check_permission();

		$this->assertFalse( $result );
	}

	public function test_check_permission_returns_false_for_guest() {
		wp_set_current_user( 0 );

		$result = $this->controller->check_permission();

		$this->assertFalse( $result );
	}

	/*
	 * =========================================================================
	 * FULL INTEGRATION TESTS
	 * =========================================================================
	 */

	public function test_full_workflow_get_dismiss_verify() {
		// Step 1: Get promotions (they exist from mock data).
		$get_response = $this->controller->get_promotions();

		$promotions = $get_response->get_data();
		$this->assertNotNull( $promotions );

		// Step 2: Dismiss a promotion using the full id.
		$first_promo_id  = $promotions[0]['id'];
		$dismiss_request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $first_promo_id . '/dismiss' );
		$dismiss_request->set_param( 'id', $first_promo_id );

		$dismiss_response = $this->controller->dismiss_promotion( $dismiss_request );

		$this->assertTrue( $dismiss_response->get_data()['success'] );

		// Step 3: Verify dismissal was recorded.
		$this->assertTrue( WC_Payments_PM_Promotions_Service::is_promotion_dismissed( $first_promo_id ) );
	}

	public function test_full_workflow_activate_verify() {
		$identifier = 'test-promo';

		// Step 1: Activate the promotion.
		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $identifier . '/activate' );
		$request->set_param( 'identifier', $identifier );

		$response = $this->controller->activate_promotion( $request );

		$this->assertTrue( $response->get_data()['success'] );

		// Step 2: Verify activation was recorded.
		$this->assertTrue( WC_Payments_PM_Promotions_Service::is_promotion_activated( $identifier ) );

		// Step 3: Verify timestamp was set.
		$activation_time = WC_Payments_PM_Promotions_Service::get_promotion_activation_time( $identifier );
		$this->assertNotNull( $activation_time );
		$this->assertGreaterThan( 0, $activation_time );
	}
}

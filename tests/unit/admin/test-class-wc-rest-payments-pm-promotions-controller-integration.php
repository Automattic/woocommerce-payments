<?php
/**
 * Class WC_REST_Payments_PM_Promotions_Controller_Integration_Test
 *
 * Integration tests for the PM Promotions REST controller endpoints.
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Activate_PM_Promotion;

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

	/**
	 * Original payment gateway map to restore after tests.
	 *
	 * @var array|null
	 */
	private $original_gateway_map;

	public function set_up() {
		parent::set_up();

		// Expect the "incorrect usage" notice since we register routes outside rest_api_init.
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		// Store original gateway map to restore in tear_down.
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );
		$this->original_gateway_map = $property->getValue();

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

		// Register routes for test controllers so rest_do_request() works.
		// We register both controllers - the mocked one for isolated tests.
		$this->controller_with_mock->register_routes();
	}

	public function tear_down() {
		parent::tear_down();

		// Restore original gateway map to prevent test pollution.
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );
		$property->setValue( null, $this->original_gateway_map );

		delete_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		delete_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION );
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

	/**
	 * Helper to set up the promotions cache with given promotions.
	 *
	 * @param array $promotions Array of promotions to cache.
	 */
	private function set_promotions_cache( array $promotions ): void {
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

		set_transient(
			WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY,
			[
				'promotions'   => $promotions,
				'context_hash' => $context_hash,
				'timestamp'    => time(),
			],
			DAY_IN_SECONDS
		);
	}

	/**
	 * Helper to set up a mock payment gateway in WC_Payments for testing.
	 *
	 * @param string          $payment_method_id The payment method ID (e.g., 'klarna').
	 * @param MockObject|null $gateway_mock      Optional gateway mock. Creates one if not provided.
	 * @param bool            $enabled           Whether the gateway should be mocked as enabled. Default true.
	 */
	private function set_payment_gateway_for_testing( string $payment_method_id, $gateway_mock = null, bool $enabled = true ): void {
		if ( null === $gateway_mock ) {
			$gateway_mock = $this->createMock( WC_Payment_Gateway_WCPay::class );
			$gateway_mock->method( 'enable' )->willReturn( true );
			$gateway_mock->method( 'get_option' )
				->willReturnCallback(
					function ( $key ) use ( $enabled ) {
						return 'enabled' === $key ? ( $enabled ? 'yes' : 'no' ) : '';
					}
				);
			$gateway_mock->method( 'get_option_key' )->willReturn( 'woocommerce_woocommerce_payments_' . $payment_method_id . '_settings' );
			$gateway_mock->method( 'get_payment_method_capability_key_map' )->willReturn( [] );
			$gateway_mock->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [] );
			$gateway_mock->method( 'update_option' )->willReturn( true );
		}

		// Use reflection to access the private static property.
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );

		$gateway_map                       = $property->getValue();
		$gateway_map[ $payment_method_id ] = $gateway_mock;
		$property->setValue( null, $gateway_map );
	}

	/**
	 * Helper to clean up the WC_Payments gateway map after testing.
	 *
	 * @param string $payment_method_id The payment method ID to remove.
	 */
	private function clear_payment_gateway_for_testing( string $payment_method_id ): void {
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );

		$gateway_map = $property->getValue();
		unset( $gateway_map[ $payment_method_id ] );
		$property->setValue( null, $gateway_map );
	}

	/*
	 * =========================================================================
	 * GET PROMOTIONS ENDPOINT TESTS
	 *
	 * Unit tests use direct controller calls to verify logic with mocked service.
	 * Integration tests use rest_do_request() to verify REST routing and permissions.
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

	public function test_get_promotions_returns_401_for_unauthenticated_user() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', $this->rest_base );
		$response = rest_do_request( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/*
	 * =========================================================================
	 * ACTIVATE PROMOTION ENDPOINT TESTS
	 * =========================================================================
	 */

	public function test_activate_promotion_calls_service_with_id() {
		$id = 'test-promo';

		$this->mock_promotions_service->expects( $this->once() )
			->method( 'activate_promotion' )
			->with( $id )
			->willReturn( true );

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/activate' );
		$request->set_param( 'id', $id );

		$response = $this->controller_with_mock->activate_promotion( $request );

		$this->assertSame( 200, $response->status );
	}

	public function test_activate_promotion_returns_success_response() {
		$id = 'test-promo';

		$this->mock_promotions_service->method( 'activate_promotion' )
			->willReturn( true );

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/activate' );
		$request->set_param( 'id', $id );

		$response = $this->controller_with_mock->activate_promotion( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
	}

	public function test_activate_promotion_returns_401_for_unauthenticated_user() {
		wp_set_current_user( 0 );
		$id = 'test-promo';

		$request  = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/activate' );
		$response = rest_do_request( $request );

		$this->assertSame( 401, $response->get_status() );
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

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/dismiss' );
		$request->set_param( 'id', $id );

		$response = $this->controller_with_mock->dismiss_promotion( $request );

		$this->assertSame( 200, $response->status );
	}

	public function test_dismiss_promotion_returns_success_true_when_dismissed() {
		$id = 'test-promo__spotlight';

		$this->mock_promotions_service->method( 'dismiss_promotion' )
			->willReturn( true );

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

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/dismiss' );
		$request->set_param( 'id', $id );

		$response = $this->controller_with_mock->dismiss_promotion( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
	}

	public function test_dismiss_promotion_returns_401_for_unauthenticated_user() {
		wp_set_current_user( 0 );
		$id = 'test-promo__spotlight';

		$request  = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/dismiss' );
		$response = rest_do_request( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_dismiss_promotion_integration_stores_dismissal() {
		$id = 'test-promo__spotlight';

		// Set up cache with a test promotion so dismiss_promotion can find it.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/dismiss' );
		$request->set_param( 'id', $id );

		$this->controller->dismiss_promotion( $request );

		$this->assertTrue( $this->promotions_service->is_promotion_dismissed( $id ) );
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
		$route  = '/wc/v3/payments/pm-promotions/(?P<id>[a-zA-Z0-9_-]+)/activate';

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
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
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
		// Set up cache with a test promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		// Step 1: Get promotions.
		$get_response = $this->controller->get_promotions();

		$promotions = $get_response->get_data();
		$this->assertNotNull( $promotions );
		$this->assertNotEmpty( $promotions );

		// Step 2: Dismiss a promotion using the full id.
		$first_promo_id  = $promotions[0]['id'];
		$dismiss_request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $first_promo_id . '/dismiss' );
		$dismiss_request->set_param( 'id', $first_promo_id );

		$dismiss_response = $this->controller->dismiss_promotion( $dismiss_request );

		$this->assertTrue( $dismiss_response->get_data()['success'] );

		// Step 3: Verify dismissal was recorded.
		$this->assertTrue( $this->promotions_service->is_promotion_dismissed( $first_promo_id ) );
	}

	public function test_full_workflow_activate_returns_success() {
		// Set up cache with a test promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		$id = 'test-promo__spotlight';

		// Mock the API request to return success.
		$this->mock_wcpay_request( Activate_PM_Promotion::class, 1, $id, [] );

		// Set up the payment gateway in WC_Payments so enable_payment_method_gateway can find it.
		$this->set_payment_gateway_for_testing( 'klarna' );

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/activate' );
		$request->set_param( 'id', $id );

		$response = $this->controller->activate_promotion( $request );

		// Clean up the gateway.
		$this->clear_payment_gateway_for_testing( 'klarna' );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_full_workflow_activate_returns_false_for_invalid_id() {
		// Set up cache with a test promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		$id = 'non-existent-promo';

		$request = new WP_REST_Request( 'POST', $this->rest_base . '/' . $id . '/activate' );
		$request->set_param( 'id', $id );

		$response = $this->controller->activate_promotion( $request );

		$this->assertFalse( $response->get_data()['success'] );
	}
}

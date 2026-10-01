<?php
/**
 * Class WC_REST_Payments_Early_Fraud_Warnings_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Database_Cache;

require_once WCPAY_ABSPATH . 'includes/admin/class-wc-rest-payments-early-fraud-warnings-controller.php';

/**
 * WC_REST_Payments_Early_Fraud_Warnings_Controller unit tests.
 */
class WC_REST_Payments_Early_Fraud_Warnings_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Early_Fraud_Warnings_Controller
	 */
	private $controller;

	/**
	 * API client mock.
	 *
	 * @var WC_Payments_API_Client|PHPUnit\Framework\MockObject\MockObject
	 */
	private $api_client;

	/**
	 * Order service mock.
	 *
	 * @var WC_Payments_Order_Service|PHPUnit\Framework\MockObject\MockObject
	 */
	private $order_service;

	/**
	 * Database cache mock.
	 *
	 * @var Database_Cache|PHPUnit\Framework\MockObject\MockObject
	 */
	private $database_cache;

	public function set_up() {
		parent::set_up();

		$this->api_client     = $this->createMock( WC_Payments_API_Client::class );
		$this->order_service  = $this->createMock( WC_Payments_Order_Service::class );
		$this->database_cache = $this->createMock( Database_Cache::class );
		$this->controller     = new WC_REST_Payments_Early_Fraud_Warnings_Controller(
			$this->api_client,
			$this->order_service,
			$this->database_cache
		);

		WC_Payments::mode()->live();
	}

	public function tear_down() {
		WC_Payments::mode()->live();

		parent::tear_down();
	}

	public function test_get_active_early_fraud_warnings_returns_the_cached_list() {
		// Arrange: The cache holds one actionable warning.
		$warnings = [
			[
				'order_id'     => 42,
				'order_number' => 'INV-42',
				'charge_id'    => 'ch_actionable',
				'created'      => 1719800000,
			],
		];
		$this->database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn( $warnings );

		// Act: Read the endpoint.
		$response = $this->controller->get_active_early_fraud_warnings( new WP_REST_Request( 'GET' ) );

		// Assert: The list is returned as-is.
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $warnings, $response->get_data() );
	}

	public function test_test_mode_reads_the_test_mode_cache_key() {
		// Arrange: The store is in test mode.
		WC_Payments::mode()->test();

		// Assert: The test-mode list is the one read.
		$this->database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->with( 'wcpay_test_early_fraud_warning_orders_cache' )
			->willReturn( [] );

		// Act.
		$this->controller->get_active_early_fraud_warnings( new WP_REST_Request( 'GET' ) );
	}

	public function test_live_mode_reads_the_live_mode_cache_key() {
		// Arrange: The store is in live mode.
		WC_Payments::mode()->live();

		// Assert: The live-mode list is the one read.
		$this->database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->with( 'wcpay_early_fraud_warning_orders_cache' )
			->willReturn( [] );

		// Act.
		$this->controller->get_active_early_fraud_warnings( new WP_REST_Request( 'GET' ) );
	}

	public function test_a_cache_miss_returns_an_empty_list_rather_than_null() {
		// Arrange: get_or_add hands back null, as it does when the generator fails validation.
		$this->database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn( null );

		// Act.
		$response = $this->controller->get_active_early_fraud_warnings( new WP_REST_Request( 'GET' ) );

		// Assert: The client always receives a list.
		$this->assertSame( [], $response->get_data() );
	}

	private function dismiss_request( int $order_id, bool $dismissed ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'order_id', $order_id );
		$request->set_param( 'dismissed', $dismissed );

		return $request;
	}

	public function test_dismiss_route_is_registered_for_post() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wc/v3/payments/early_fraud_warnings/(?P<order_id>\d+)/dismiss', $routes );
		$this->assertTrue( $routes['/wc/v3/payments/early_fraud_warnings/(?P<order_id>\d+)/dismiss'][0]['methods']['POST'] );
	}

	public function test_check_permission_requires_manage_woocommerce() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertFalse( $this->controller->check_permission() );

		$user = self::factory()->user->create_and_get();
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user->ID );
		$this->assertTrue( $this->controller->check_permission() );
	}

	public function test_dismiss_records_the_dismissal_and_clears_the_cache() {
		$order = WC_Helper_Order::create_order();
		$this->order_service->method( 'get_early_fraud_warning_for_order' )
			->willReturn(
				[
					'efw_id'         => 'issfr_1',
					'efw_actionable' => true,
				]
			);
		$this->order_service->expects( $this->once() )->method( 'dismiss_early_fraud_warning' );
		$this->order_service->expects( $this->never() )->method( 'undismiss_early_fraud_warning' );
		$this->order_service->method( 'is_early_fraud_warning_dismissed' )->willReturn( true );
		$this->database_cache->expects( $this->once() )->method( 'delete_early_fraud_warning_caches' );

		$response = $this->controller->set_early_fraud_warning_dismissed( $this->dismiss_request( $order->get_id(), true ) );

		$this->assertSame( [ 'dismissed' => true ], $response->get_data() );
	}

	public function test_undo_clears_the_dismissal_and_clears_the_cache() {
		$order = WC_Helper_Order::create_order();
		$this->order_service->method( 'get_early_fraud_warning_for_order' )
			->willReturn(
				[
					'efw_id'         => 'issfr_1',
					'efw_actionable' => true,
				]
			);
		$this->order_service->expects( $this->once() )->method( 'undismiss_early_fraud_warning' );
		$this->order_service->expects( $this->never() )->method( 'dismiss_early_fraud_warning' );
		$this->order_service->method( 'is_early_fraud_warning_dismissed' )->willReturn( false );
		$this->database_cache->expects( $this->once() )->method( 'delete_early_fraud_warning_caches' );

		$response = $this->controller->set_early_fraud_warning_dismissed( $this->dismiss_request( $order->get_id(), false ) );

		$this->assertSame( [ 'dismissed' => false ], $response->get_data() );
	}

	public function test_undo_is_allowed_once_the_warning_is_resolved() {
		$order = WC_Helper_Order::create_order();
		$this->order_service->method( 'get_early_fraud_warning_for_order' )
			->willReturn(
				[
					'efw_id'         => 'issfr_1',
					'efw_actionable' => false,
				]
			);
		$this->order_service->expects( $this->once() )->method( 'undismiss_early_fraud_warning' );

		$response = $this->controller->set_early_fraud_warning_dismissed( $this->dismiss_request( $order->get_id(), false ) );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
	}

	public function test_dismiss_rejects_a_resolved_warning() {
		$order = WC_Helper_Order::create_order();
		$this->order_service->method( 'get_early_fraud_warning_for_order' )
			->willReturn(
				[
					'efw_id'         => 'issfr_1',
					'efw_actionable' => false,
				]
			);
		$this->order_service->expects( $this->never() )->method( 'dismiss_early_fraud_warning' );
		$this->database_cache->expects( $this->never() )->method( 'delete_early_fraud_warning_caches' );

		$response = $this->controller->set_early_fraud_warning_dismissed( $this->dismiss_request( $order->get_id(), true ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_efw_not_actionable', $response->get_error_code() );
		$this->assertSame( 400, $response->get_error_data()['status'] );
	}

	public function test_dismiss_returns_404_for_an_order_without_a_warning() {
		$order = WC_Helper_Order::create_order();
		$this->order_service->method( 'get_early_fraud_warning_for_order' )->willReturn( null );

		$response = $this->controller->set_early_fraud_warning_dismissed( $this->dismiss_request( $order->get_id(), true ) );

		$this->assertSame( 'wcpay_efw_not_found', $response->get_error_code() );
		$this->assertSame( 404, $response->get_error_data()['status'] );
	}

	public function test_dismiss_returns_404_for_a_missing_order() {
		$response = $this->controller->set_early_fraud_warning_dismissed( $this->dismiss_request( 999999, true ) );

		$this->assertSame( 'wcpay_efw_not_found', $response->get_error_code() );
		$this->assertSame( 404, $response->get_error_data()['status'] );
	}

	public function test_dismiss_responds_with_the_recorded_state_not_the_requested_one() {
		// A warning without an ID: the service records nothing, so the response says not dismissed.
		$order = WC_Helper_Order::create_order();
		$this->order_service->method( 'get_early_fraud_warning_for_order' )
			->willReturn( [ 'efw_actionable' => true ] );
		$this->order_service->method( 'is_early_fraud_warning_dismissed' )->willReturn( false );

		$response = $this->controller->set_early_fraud_warning_dismissed( $this->dismiss_request( $order->get_id(), true ) );

		$this->assertSame( [ 'dismissed' => false ], $response->get_data() );
	}
}

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
				'order_id'  => 42,
				'charge_id' => 'ch_actionable',
				'created'   => 1719800000,
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
}

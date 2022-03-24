<?php
/**
 * Class WC_REST_Payments_Onboarding_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_REST_Payments_Onboarding_Controller unit tests.
 */
class WC_REST_Payments_Onboarding_Controller_Test extends WP_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Onboarding_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * @var WC_Payments_Onboarding_Service|MockObject
	 */
	private $mock_onboarding_service;

	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->mock_api_client         = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_onboarding_service = $this->createMock( WC_Payments_Onboarding_Service::class );

		$this->controller = new WC_REST_Payments_Onboarding_Controller(
			$this->mock_api_client,
			$this->mock_onboarding_service
		);
	}

	public function test_get_business_types() {
		$mock_business_types = [
			'key'   => 'TEST',
			'name'  => 'Test',
			'types' => [],
		];

		$this->mock_onboarding_service
			->expects( $this->once() )
			->method( 'get_cached_business_types' )
			->willReturn( $mock_business_types );

		$request = new WP_REST_Request( 'GET' );

		$response = $this->controller->get_business_types( $request );

		$this->assertSame( 200, $response->status );
		$this->assertSame( [ 'data' => $mock_business_types ], $response->get_data() );
	}
}

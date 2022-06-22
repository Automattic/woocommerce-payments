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
class WC_REST_Payments_Onboarding_Controller_Test extends WCPAY_UnitTestCase {
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

	public function test_get_required_verification_information_with_missing_params() {
		$request  = new WP_REST_Request( 'GET', '', [ 'foo' => 'bar' ] );
		$response = $this->controller->get_required_verification_information( $request );

		$this->assertSame( 400, $response->status );
		$this->assertSame(
			[ 'result' => WC_REST_Payments_Onboarding_Controller::RESULT_BAD_REQUEST ],
			$response->get_data()
		);
	}

	public function test_get_required_verification_information() {
		$mock_requirements = [
			'business_profile.url',
			'business_profile.mcc',
			'representative.first_name',
			'representative.last_name',
			'representative.dob.day',
			'representative.dob.month',
			'representative.dob.year',
			'representative.phone',
			'representative.email',
			'representative.address.line1',
			'representative.address.postal_code',
			'representative.address.city',
			'representative.address.state',
			'representative.ssn_last_4',
			'company.name',
			'company.tax_id',
			'tos_acceptance.ip',
			'tos_acceptance.date',
			'external_account',
		];

		$this->mock_onboarding_service
			->expects( $this->once() )
			->method( 'get_required_verification_information' )
			->willReturn( $mock_requirements );

		$request = new WP_REST_Request( 'GET' );
		$request->set_url_params(
			[
				'country'   => 'US',
				'type'      => 'company',
				'structure' => 'sole_propreitor',
			]
		);
		$response = $this->controller->get_required_verification_information( $request );

		$this->assertSame( 200, $response->status );
		$this->assertSame( [ 'data' => $mock_requirements ], $response->get_data() );
	}
}

<?php
/**
 * Class WC_REST_Payments_Onboarding_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Country_Code;

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

	public function test_create_embedded_kyc_session() {
		$kyc_session = [
			'clientSecret'   => 'accs_secret__XXX',
			'expiresAt'      => time() + 120,
			'accountId'      => 'acct_XXX',
			'isLive'         => false,
			'accountCreated' => true,
			'publishableKey' => 'pk_test_XXX',
		];

		$this->mock_onboarding_service
			->expects( $this->once() )
			->method( 'create_embedded_kyc_session' )
			->willReturn(
				$kyc_session
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'create_live_account' => true,
			]
		);

		$response = $this->controller->create_embedded_kyc_session( $request );
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			array_merge(
				$kyc_session,
				[
					'locale' => 'en_US',
				]
			),
			$response->get_data()
		);
	}

	public function test_init_test_drive_account_forwards_account_data_to_service() {
		$account_data = [ 'extra_bootstrapping' => false ];

		$this->mock_onboarding_service
			->expects( $this->once() )
			->method( 'init_test_drive_account' )
			->with( 'US', [], $account_data )
			->willReturn( true );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'country'      => 'US',
				'account_data' => $account_data,
			]
		);

		$response = $this->controller->init_test_drive_account( $request );
		$this->assertSame( 200, $response->status );
		$this->assertSame( [ 'success' => true ], $response->get_data() );
	}

	public function test_init_test_drive_account_defaults_account_data_to_empty_array() {
		$this->mock_onboarding_service
			->expects( $this->once() )
			->method( 'init_test_drive_account' )
			->with( 'US', [], [] )
			->willReturn( true );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params( [ 'country' => 'US' ] );

		$response = $this->controller->init_test_drive_account( $request );
		$this->assertSame( 200, $response->status );
		$this->assertSame( [ 'success' => true ], $response->get_data() );
	}

	public function test_init_test_drive_account_returns_error_on_service_exception() {
		$this->mock_onboarding_service
			->expects( $this->once() )
			->method( 'init_test_drive_account' )
			->willThrowException( new Exception( 'Something went wrong' ) );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params( [ 'country' => 'US' ] );

		$response = $this->controller->init_test_drive_account( $request );
		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'bad_request', $response->get_error_code() );
		$this->assertSame( 'Something went wrong', $response->get_error_message() );
	}

	public function test_finalize_embedded_kyc() {
		$response_data = [
			'success'           => true,
			'account_id'        => 'acct_1PvxJQQujq4nxoo6',
			'details_submitted' => true,
			'mode'              => 'test',
			'promotion_id'      => null,
		];
		$this->mock_onboarding_service
			->expects( $this->once() )
			->method( 'finalize_embedded_kyc' )
			->willReturn(
				$response_data
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'source' => 'embedded',
				'from'   => 'wcpay-connect',
			]
		);

		$response = $this->controller->finalize_embedded_kyc( $request );
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			array_merge(
				$response_data,
				[
					'params' => [
						'promo'                    => '',
						'from'                     => 'wcpay-connect',
						'source'                   => 'embedded',
						'wcpay-connection-success' => '1',
					],
				]
			),
			$response->get_data()
		);
	}
}

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

	public function test_get_progressive_onboarding_eligible() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_po_eligible' )
			->willReturn(
				[
					'result' => 'eligible',
					'data'   => [],
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'business'        => [
					'country' => Country_Code::UNITED_STATES,
					'type'    => 'company',
					'mcc'     => 'most_popular__software_services',
				],
				'store'           => [
					'annual_revenue'    => 'less_than_250k',
					'go_live_timeframe' => 'within_1month',
				],
				'woo_store_stats' => [],
			]
		);

		$response = $this->controller->get_progressive_onboarding_eligible( $request );
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'result' => 'eligible',
				'data'   => [],
			],
			$response->get_data()
		);
	}

	public function test_get_progressive_onboarding_not_eligible() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_po_eligible' )
			->willReturn(
				[
					'result' => 'not_eligible',
					'data'   => [],
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[

				'business'        => [
					'country' => Country_Code::UNITED_STATES,
					'type'    => 'company',
					'mcc'     => 'most_popular__software_services',
				],
				'store'           => [
					'annual_revenue'    => 'from_1m_to_20m',
					'go_live_timeframe' => 'from_1_to_3months', // Fails because of the go live timeframe.
				],
				'woo_store_stats' => [],
			]
		);

		$response = $this->controller->get_progressive_onboarding_eligible( $request );
		$this->assertSame( 200, $response->status );
		$this->assertSame(
			[
				'result' => 'not_eligible',
				'data'   => [],
			],
			$response->get_data()
		);
	}

	public function test_get_embedded_kyc_session() {
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

		$this->mock_onboarding_service
			->expects( $this->once() )
			->method( 'set_embedded_kyc_in_progress' );

		$request = new WP_REST_Request( 'GET' );
		$request->set_query_params(
			[
				'progressive'         => true,
				'create_live_account' => true,
			]
		);

		$response = $this->controller->get_embedded_kyc_session( $request );
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

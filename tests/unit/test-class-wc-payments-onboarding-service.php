<?php
/**
 * Class WC_Payments_Onboarding_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Onboarding_Service unit tests.
 */
class WC_Payments_Onboarding_Service_Test extends WP_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Onboarding_Service
	 */
	private $onboarding_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Example business types array.
	 *
	 * @var array
	 */
	private $mock_business_types = [
		[
			'key'   => 'US',
			'name'  => 'United States (US)',
			'types' => [
				[
					'key'        => 'individual',
					'name'       => 'Individual',
					'structures' => [],
				],
				[
					'key'        => 'company',
					'name'       => 'Company',
					'structures' => [
						[
							'key'  => 'sole_proprietorship',
							'name' => 'Sole proprietorship',
						],
						[
							'key'  => 'single_member_llc',
							'name' => 'Single member llc',
						],
						[
							'key'  => 'multi_member_llc',
							'name' => 'Multi member llc',
						],
						[
							'key'  => 'private_partnership',
							'name' => 'Private partnership',
						],
						[
							'key'  => 'private_corporation',
							'name' => 'Private corporation',
						],
						[
							'key'  => 'unincorporated_association',
							'name' => 'Unincorporated association',
						],
						[
							'key'  => 'public_partnership',
							'name' => 'Public partnership',
						],
						[
							'key'  => 'public_corporation',
							'name' => 'Public corporation',
						],
					],
				],
				[
					'key'        => 'non_profit',
					'name'       => 'Non profit',
					'structures' => [
						[
							'key'  => 'incorporated_non_profit',
							'name' => 'Incorporated non profit',
						],
						[
							'key'  => 'unincorporated_non_profit',
							'name' => 'Unincorporated non profit',
						],
					],
				],
				[
					'key'        => 'government_entity',
					'name'       => 'Government entity',
					'structures' => [
						[
							'key'  => 'governmental_unit',
							'name' => 'Governmental unit',
						],
						[
							'key'  => 'government_instrumentality',
							'name' => 'Government instrumentality',
						],
						[
							'key'  => 'tax_exempt_government_instrumentality',
							'name' => 'Tax exempt government instrumentality',
						],
					],
				],
			],
		],
	];

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->onboarding_service = new WC_Payments_Onboarding_Service( $this->mock_api_client );
	}

	/**
	 * Post-test teardown
	 */
	public function tear_down() {
		parent::tear_down();

		delete_option( WC_Payments_Onboarding_Service::BUSINESS_TYPES_OPTION );
	}

	public function test_get_required_verification_information() {
		$mock_requirements = [ 'requirement1', 'requirement2', 'requirement3' ];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_required_verification_information' )
			->with( 'US', 'company', 'sole_propietorship' )
			->willReturn( $mock_requirements );

		$this->assertEquals(
			$mock_requirements,
			$this->onboarding_service->get_required_verification_information( 'US', 'company', 'sole_propietorship' )
		);
	}

	public function test_get_cached_business_types_with_no_server_connection() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'is_server_connected' )
			->willReturn( false );

		$this->assertEquals( [], $this->onboarding_service->get_cached_business_types() );
	}

	public function test_get_cached_business_types_from_cache() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'is_server_connected' )
			->willReturn( true );

		$this->cache_business_types( $this->mock_business_types );

		$this->assertEquals(
			$this->mock_business_types,
			$this->onboarding_service->get_cached_business_types()
		);
	}

	public function test_get_cached_business_types_cached_error() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'is_server_connected' )
			->willReturn( true );

		$this->cache_business_types( WC_Payments_Onboarding_Service::BUSINESS_TYPES_RETRIEVAL_ERROR );

		$this->assertFalse( $this->onboarding_service->get_cached_business_types() );
	}

	public function test_get_cached_business_types_no_cache() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'is_server_connected' )
			->willReturn( true );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_business_types' )
			->willReturn( $this->mock_business_types );

		$result = $this->onboarding_service->get_cached_business_types();

		$this->assertEquals(
			$this->mock_business_types,
			$result
		);

		$this->assertEquals(
			$this->mock_business_types,
			get_option( WC_Payments_Onboarding_Service::BUSINESS_TYPES_OPTION )['business_types']
		);
	}

	public function test_get_cached_business_types_api_error() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'is_server_connected' )
			->willReturn( true );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_business_types' )
			->willThrowException( new API_Exception( 'error contacting server', 500, 500 ) );

		$result = $this->onboarding_service->get_cached_business_types();

		$this->assertEquals(
			false,
			$result
		);

		$this->assertEquals(
			WC_Payments_Onboarding_Service::BUSINESS_TYPES_RETRIEVAL_ERROR,
			get_option( WC_Payments_Onboarding_Service::BUSINESS_TYPES_OPTION )['business_types']
		);
	}

	public function test_get_cached_business_types_force_refresh() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'is_server_connected' )
			->willReturn( true );

		$this->cache_business_types( [ 'foo' => 'bar' ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_onboarding_business_types' )
			->willReturn( $this->mock_business_types );

		$result = $this->onboarding_service->get_cached_business_types( true );

		$this->assertEquals(
			$this->mock_business_types,
			$result
		);

		$this->assertEquals(
			$this->mock_business_types,
			get_option( WC_Payments_Onboarding_Service::BUSINESS_TYPES_OPTION )['business_types']
		);
	}

	/**
	 * Cache account details.
	 *
	 * @param $account
	 */
	private function cache_business_types( $business_types ) {
		add_option(
			WC_Payments_Onboarding_Service::BUSINESS_TYPES_OPTION,
			[
				'business_types' => $business_types,
				'expires'        => time() + 2 * HOUR_IN_SECONDS,
			]
		);
	}
}

<?php
/**
 * Class WC_Payments_Onboarding_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;

/**
 * WC_Payments_Onboarding_Service unit tests.
 */
class WC_Payments_Onboarding_Service_Test extends WCPAY_UnitTestCase {
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
	 * Mock Database_Cache
	 *
	 * @var MockObject
	 */
	private $mock_database_cache;

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

		$this->mock_api_client     = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_database_cache = $this->createMock( Database_Cache::class );

		$this->onboarding_service = new WC_Payments_Onboarding_Service( $this->mock_api_client, $this->mock_database_cache );
	}

	public function test_filters_registered_properly() {
		$this->assertNotFalse( has_filter( 'wcpay_dev_mode', [ $this->onboarding_service, 'maybe_enable_dev_mode' ] ) );
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

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn( $this->mock_business_types );

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

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn( null );

		$this->assertFalse( $this->onboarding_service->get_cached_business_types() );
	}

	public function test_set_test_mode() {
		$this->onboarding_service->set_test_mode( true );

		$this->assertTrue( get_option( 'wcpay_onboarding_test_mode' ) );
		$this->assertTrue( WC_Payments::mode()->is_dev() );

		$this->onboarding_service->set_test_mode( false );

		$this->assertFalse( get_option( 'wcpay_onboarding_test_mode' ) );
		$this->assertFalse( WC_Payments::mode()->is_dev() );

		delete_option( 'wcpay_onboarding_test_mode' );
	}
}

<?php
/**
 * Class WC_Payments_Onboarding_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Country_Code;
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
			'key'   => Country_Code::UNITED_STATES,
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
		$this->onboarding_service->init_hooks();
	}

	public function test_filters_registered_properly() {
		$this->assertNotFalse( has_filter( 'admin_body_class', [ $this->onboarding_service, 'add_admin_body_classes' ] ) );
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

	public function test_add_admin_body_classes_when_not_onboarding() {
		$this->assertEquals( '', $this->onboarding_service->add_admin_body_classes() );
	}

	public function test_add_admin_body_classes_when_onboarding() {
		$_GET['path'] = '/payments/onboarding';

		$this->assertEquals( ' woocommerce-admin-is-loading', $this->onboarding_service->add_admin_body_classes() );
	}

	public function test_set_test_mode() {
		$this->onboarding_service->set_test_mode( true );

		$this->assertEquals( 'yes', get_option( WC_Payments_Onboarding_Service::TEST_MODE_OPTION, 'no' ) );

		$this->onboarding_service->set_test_mode( false );

		$this->assertEquals( 'no', get_option( WC_Payments_Onboarding_Service::TEST_MODE_OPTION, 'no' ) );

		delete_option( WC_Payments_Onboarding_Service::TEST_MODE_OPTION );
	}

	/**
	 * @dataProvider data_get_from
	 */
	public function test_get_from( $expected, $referer, $get_params ) {
		$this->assertEquals( $expected, WC_Payments_Onboarding_Service::get_from( $referer, $get_params ) );
	}

	/**
	 * Data provider for test_get_from.
	 *
	 * @return array[]
	 */
	public function data_get_from(): array {
		return [
			'Unknown from'                                 => [
				'',
				'',
				[],
			],
			'Non-empty from GET param trumps everything'   => [
				'WCADMIN_PAYMENT_INCENTIVE',
				'/wp-admin/admin.php?page=wc-settings&tab=checkout',
				[
					'source'                             => 'wcpay-connect-page',
					'wcpay-connect'                      => 'WCADMIN_PAYMENT_TASK',
					'wcpay-disable-onboarding-test-mode' => 'true',
					'from'                               => 'WCADMIN_PAYMENT_INCENTIVE',
				],
			],
			'Empty from GET param is ignored'              => [
				'WCADMIN_PAYMENT_TASK',
				'',
				[
					'from'          => '',
					'wcpay-connect' => 'WCADMIN_PAYMENT_TASK',
				],
			],
			'Via test to live param'                       => [
				'WCPAY_TEST_TO_LIVE',
				'any',
				[
					'wcpay-connect'                      => '1',
					'wcpay-disable-onboarding-test-mode' => 'true',
				],
			],
			'test to live param takes precedence'          => [
				'WCPAY_TEST_TO_LIVE',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fconnect',
				[
					'wcpay-connect'                      => 'WCADMIN_PAYMENT_TASK',
					'wcpay-disable-onboarding-test-mode' => 'true',
				],
			],
			'Via reset account param'                      => [
				'WCPAY_RESET_ACCOUNT',
				'any',
				[
					'wcpay-connect'       => '1',
					'wcpay-reset-account' => 'true',
				],
			],
			'reset account param takes precedence'         => [
				'WCPAY_RESET_ACCOUNT',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fconnect',
				[
					'wcpay-connect'       => 'WCADMIN_PAYMENT_TASK',
					'wcpay-reset-account' => 'true',
				],
			],
			'Via the wcpay-connect value - takes precedence over referer' => [
				'WCADMIN_PAYMENT_TASK',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fconnect',
				[ 'wcpay-connect' => 'WCADMIN_PAYMENT_TASK' ],
			],
			'Via the wcpay-connect value - Payments task'  => [
				'WCADMIN_PAYMENT_TASK',
				'any',
				[ 'wcpay-connect' => 'WCADMIN_PAYMENT_TASK' ],
			],
			'Via the wcpay-connect value - Payments Settings' => [
				'WCADMIN_PAYMENT_SETTINGS',
				'any',
				[ 'wcpay-connect' => 'WCADMIN_PAYMENT_SETTINGS' ],
			],
			'Via the wcpay-connect value - Incentive page' => [
				'WCADMIN_PAYMENT_INCENTIVE',
				'any',
				[ 'wcpay-connect' => 'WCADMIN_PAYMENT_INCENTIVE' ],
			],
			'Via the wcpay-connect value - Connect page'   => [
				'WCPAY_CONNECT',
				'any',
				[ 'wcpay-connect' => 'WCPAY_CONNECT' ],
			],
			'Via the wcpay-connect value - Onboarding wizard' => [
				'WCPAY_ONBOARDING_WIZARD',
				'any',
				[ 'wcpay-connect' => 'WCPAY_ONBOARDING_WIZARD' ],
			],
			'Via the wcpay-connect value - Test to live'   => [
				'WCPAY_TEST_TO_LIVE',
				'any',
				[ 'wcpay-connect' => 'WCPAY_TEST_TO_LIVE' ],
			],
			'Via the wcpay-connect value - Reset account'  => [
				'WCPAY_RESET_ACCOUNT',
				'any',
				[ 'wcpay-connect' => 'WCPAY_RESET_ACCOUNT' ],
			],
			'Via the wcpay-connect value - WPCOM'          => [
				'WPCOM',
				'any',
				[ 'wcpay-connect' => 'WPCOM' ],
			],
			'Via the wcpay-connect value - Stripe'         => [
				'STRIPE',
				'any',
				[ 'wcpay-connect' => 'STRIPE' ],
			],
			'Invalid wcpay-connect value is ignored'       => [
				'',
				'any',
				[ 'wcpay-connect' => 'something' ],
			],
			'Via the referer URL - payments task'          => [
				'WCADMIN_PAYMENT_TASK',
				'/wp-admin/admin.php?page=wc-admin&task=payments',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - settings page'          => [
				'WCADMIN_PAYMENT_SETTINGS',
				'/wp-admin/admin.php?page=wc-settings&tab=checkout',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - incentive page'         => [
				'WCADMIN_PAYMENT_INCENTIVE',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fwc-pay-welcome-page',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - Connect page'           => [
				'WCPAY_CONNECT',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fconnect',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - Onboarding wizard'      => [
				'WCPAY_ONBOARDING_WIZARD',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fonboarding',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - WPCOM'                  => [
				'WPCOM',
				'http://public-api.wordpress.com/something',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - Stripe'                 => [
				'STRIPE',
				'http://something.stripe.com/something',
				[ 'wcpay-connect' => '1' ],
			],
		];
	}

	/**
	 * @dataProvider data_get_source
	 */
	public function test_get_source( $expected, $referer, $get_params ) {
		$this->assertEquals( $expected, WC_Payments_Onboarding_Service::get_source( $referer, $get_params ) );
	}

	/**
	 * Data provider for test_get_source.
	 *
	 * @return array[]
	 */
	public function data_get_source(): array {
		return [
			'Valid source GET param trumps everything'    => [
				'wcpay-connect-page',
				'/wp-admin/admin.php?page=wc-settings&tab=checkout',
				[
					'source'                             => 'wcpay-connect-page',
					'wcpay-connect'                      => 'WCADMIN_PAYMENT_TASK',
					'wcpay-disable-onboarding-test-mode' => 'true',
					'from'                               => 'WCADMIN_PAYMENT_INCENTIVE',
				],
			],
			'Invalid source GET param is ignored'         => [
				'wcadmin-payment-task',
				'',
				[
					'source'        => 'bogus',
					'wcpay-connect' => 'WCADMIN_PAYMENT_TASK',
				],
			],
			'unknown source GET param is ignored'         => [
				'wcadmin-payment-task',
				'',
				[
					'source'        => 'unknown',
					'wcpay-connect' => 'WCADMIN_PAYMENT_TASK',
				],
			],
			'Unknown source'                              => [
				'unknown',
				'',
				[],
			],
			'Via the wcpay-connect value'                 => [
				'wcadmin-payment-task',
				'any',
				[ 'wcpay-connect' => 'WCADMIN_PAYMENT_TASK' ],
			],
			'Via the referer URL - with valid source in it' => [
				'wcpay-go-live-task',
				'/wp-admin/admin.php?page=wc-admin&task=payments&source=wcpay-go-live-task',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - with invalid source in it' => [
				'wcadmin-payment-task',
				'/wp-admin/admin.php?page=wc-admin&task=payments&source=bogus',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - payments task'         => [
				'wcadmin-payment-task',
				'/wp-admin/admin.php?page=wc-admin&task=payments',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - settings page'         => [
				'wcadmin-settings-page',
				'/wp-admin/admin.php?page=wc-settings&tab=checkout',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - incentive page'        => [
				'wcadmin-incentive-page',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fwc-pay-welcome-page',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - Connect page'          => [
				'wcpay-connect-page',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fconnect',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - Overview page'         => [
				'wcpay-overview-page',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Foverview',
				[ 'wcpay-connect' => '1' ],
			],
			'Via the referer URL - Deposits/Payouts page' => [
				'wcpay-payouts-page',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fdeposits',
				[ 'wcpay-connect' => '1' ],
			],
			'Via test to live param'                      => [
				'wcpay-setup-live-payments',
				'any',
				[
					'wcpay-connect'                      => '1',
					'wcpay-disable-onboarding-test-mode' => 'true',
				],
			],
			'test to live param takes precedence'         => [
				'wcpay-setup-live-payments',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fconnect',
				[
					'wcpay-connect'                      => 'WCADMIN_PAYMENT_TASK',
					'wcpay-disable-onboarding-test-mode' => 'true',
					'from'                               => 'WCADMIN_PAYMENT_INCENTIVE',
				],
			],
			'Via reset account param'                     => [
				'wcpay-reset-account',
				'any',
				[
					'wcpay-connect'       => '1',
					'wcpay-reset-account' => 'true',
				],
			],
			'reset account param takes precedence'        => [
				'wcpay-reset-account',
				'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fconnect',
				[
					'wcpay-connect'       => 'WCADMIN_PAYMENT_TASK',
					'wcpay-reset-account' => 'true',
					'from'                => 'WCADMIN_PAYMENT_INCENTIVE',
				],
			],
			'wcpay-connect value takes precedence over from and referer' => [
				'wcadmin-payment-task',
				'/wp-admin/admin.php?page=wc-settings&tab=checkout',
				[
					'wcpay-connect' => 'WCADMIN_PAYMENT_TASK',
					'from'          => 'WCADMIN_PAYMENT_INCENTIVE',
				],
			],
			'from value takes precedence over referer'    => [
				'wcadmin-incentive-page',
				'/wp-admin/admin.php?page=wc-settings&tab=checkout',
				[
					'wcpay-connect' => 'bogus',
					'from'          => 'WCADMIN_PAYMENT_INCENTIVE',
				],
			],
		];
	}
}

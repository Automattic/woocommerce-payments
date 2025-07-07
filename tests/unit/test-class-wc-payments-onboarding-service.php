<?php
/**
 * Class WC_Payments_Onboarding_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Country_Code;
use WCPay\Database_Cache;
use WCPay\Exceptions\API_Exception;

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
	 * @var Database_Cache|MockObject
	 */
	private $mock_database_cache;

	/**
	 * Mock WC_Payments_Session_Service
	 *
	 * @var WC_Payments_Session_Service|MockObject
	 */
	private $mock_session_service;

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

		$this->mock_api_client      = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_database_cache  = $this->createMock( Database_Cache::class );
		$this->mock_session_service = $this->createMock( WC_Payments_Session_Service::class );

		$this->onboarding_service = new WC_Payments_Onboarding_Service( $this->mock_api_client, $this->mock_database_cache, $this->mock_session_service );
		$this->onboarding_service->init_hooks();
	}

	public function test_filters_registered_properly() {
		$this->assertNotFalse( has_filter( 'admin_body_class', [ $this->onboarding_service, 'add_admin_body_classes' ] ) );
	}

	public function test_create_embedded_kyc_session() {
		// Arrange.
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( true );

		$expected_account_session = [
			'client_secret'             => 'secret',
			'expires_at'                => time() + 3600,
			'account_id'                => 'acc_123',
			'is_live'                   => true,
			'account_created'           => true,
			'publishable_key'           => 'pk_test_123',
			'woopay_enabled_by_default' => true,
		];

		$this->mock_api_client
			->method( 'initialize_onboarding_embedded_kyc' )
			->willReturn( $expected_account_session );

		$this->onboarding_service->clear_embedded_kyc_in_progress();

		delete_transient( WC_Payments_Account::WOOPAY_ENABLED_BY_DEFAULT_TRANSIENT );

		// Act.
		$result = $this->onboarding_service->create_embedded_kyc_session( [], false );

		// Assert.
		$this->assertEquals( $expected_account_session['client_secret'], $result['clientSecret'] );
		$this->assertEquals( $expected_account_session['expires_at'], $result['expiresAt'] );
		$this->assertEquals( $expected_account_session['account_id'], $result['accountId'] );
		$this->assertEquals( $expected_account_session['is_live'], $result['isLive'] );
		$this->assertEquals( $expected_account_session['account_created'], $result['accountCreated'] );
		$this->assertEquals( $expected_account_session['publishable_key'], $result['publishableKey'] );

		$this->assertTrue( $this->onboarding_service->is_embedded_kyc_in_progress() );
		$this->assertTrue( get_transient( WC_Payments_Account::WOOPAY_ENABLED_BY_DEFAULT_TRANSIENT ) );
	}

	public function test_create_embedded_kyc_session_no_wpcom_connection() {
		// Arrange.
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( false );

		// Act.
		$result = $this->onboarding_service->create_embedded_kyc_session( [], false );

		// Assert.
		$this->assertEmpty( $result );
	}

	public function test_finalize_embedded_kyc() {
		// Arrange.
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( true );

		$expected_result = [
			'success'           => true,
			'account_id'        => 'acc_id',
			'details_submitted' => true,
			'mode'              => 'test',
			'promotion_id'      => 'promotion_id',
		];
		$this->mock_api_client
			->method( 'finalize_onboarding_embedded_kyc' )
			->willReturn( $expected_result );

		$this->onboarding_service->set_embedded_kyc_in_progress();

		// Act.
		$result = $this->onboarding_service->finalize_embedded_kyc( 'en_US', 'source', [] );

		// Assert.
		$this->assertEquals( $expected_result['success'], $result['success'] );
		$this->assertEquals( $expected_result['account_id'], $result['account_id'] );
		$this->assertEquals( $expected_result['details_submitted'], $result['details_submitted'] );
		$this->assertEquals( $expected_result['mode'], $result['mode'] );
		$this->assertEquals( $expected_result['promotion_id'], $result['promotion_id'] );

		$this->assertFalse( $this->onboarding_service->is_embedded_kyc_in_progress() );
	}

	public function test_finalize_embedded_kyc_no_wpcom_connection() {
		// Arrange.
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( false );

		// Act.
		$result = $this->onboarding_service->finalize_embedded_kyc( 'en_US', 'source', [] );

		// Assert.
		$this->assertEquals( [ 'success' => false ], $result );
	}

	public function test_finalize_embedded_kyc_no_success() {
		// Arrange.
		$this->mock_api_client
			->method( 'is_server_connected' )
			->willReturn( true );

		$this->mock_api_client
			->method( 'finalize_onboarding_embedded_kyc' )
			->willReturn( [ 'success' => false ] );

		// Assert.
		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Failed to finalize onboarding session.' );

		// Act.
		$this->onboarding_service->finalize_embedded_kyc( 'en_US', 'source', [] );
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

	public function test_is_embedded_kyc_in_progress() {
		$this->assertFalse( $this->onboarding_service->is_embedded_kyc_in_progress() );

		$this->onboarding_service->set_embedded_kyc_in_progress();

		$this->assertTrue( $this->onboarding_service->is_embedded_kyc_in_progress() );

		$this->onboarding_service->clear_embedded_kyc_in_progress();

		$this->assertFalse( $this->onboarding_service->is_embedded_kyc_in_progress() );
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

	/**
	 * Test successful migration from test drive account to live account.
	 */
	public function test_migrate_test_drive_account_to_live_success() {
		// Arrange.
		$context = [
			'from'   => 'test_from',
			'source' => 'test_source',
		];
		$self_assessment_data = [
			'business_type' => 'individual',
		];
		$capabilities = [
			'card_payments' => true,
			'transfers' => false,
		];

		// Mock account service methods
		$mock_account = $this->createMock( WC_Payments_Account::class );
		WC_Payments::set_account_service( $mock_account );

		$mock_account->expects( $this->once() )
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$mock_account->expects( $this->once() )
			->method( 'get_cached_account_data' )
			->willReturn([
				'capabilities' => [
					'card_payments' => 'active',
					'transfers' => 'inactive',
				],
			]);

		$mock_account->expects( $this->once() )
			->method( 'overwrite_cache_with_no_account' );

		// Mock expected account session response
		$expected_account_session = [
			'client_secret' => 'test_secret',
			'expires_at' => time() + 3600,
			'account_id' => 'acct_test123',
			'is_live' => true,
		];

		// Mock create_embedded_kyc_session
		$this->mock_api_client
			->method( 'initialize_onboarding_embedded_kyc' )
			->willReturn( $expected_account_session );

		// Act.
		$result = $this->onboarding_service->migrate_test_drive_account_to_live( $context, $self_assessment_data );

		// Assert.
		$this->assertEquals( $expected_account_session['client_secret'], $result['clientSecret'] );
		$this->assertEquals( $expected_account_session['expires_at'], $result['expiresAt'] );
		$this->assertEquals( $expected_account_session['account_id'], $result['accountId'] );
		$this->assertEquals( $expected_account_session['is_live'], $result['isLive'] );
		$this->assertFalse( $this->onboarding_service->is_onboarding_migrate_to_live_in_progress() );
	}

	/**
	 * Test migration failure when no account exists.
	 */
	public function test_migrate_test_drive_account_to_live_no_account() {
		// Arrange.
		$context = [
			'from'   => 'test_from',
			'source' => 'test_source',
		];
		$self_assessment_data = [];

		// Mock account service methods
		$mock_account = $this->createMock( WC_Payments_Account::class );
		$mock_account->expects( $this->once() )
			->method( 'is_stripe_connected' )
			->willReturn( false );

		WC_Payments::set_account_service( $mock_account );

		// Assert.
		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Failed to migrate the account: account does not exist.' );

		// Act.
		$this->onboarding_service->migrate_test_drive_account_to_live( $context, $self_assessment_data );
	}

	/**
	 * Test migration failure during the process.
	 */
	public function test_migrate_test_drive_account_to_live_failure() {
		// Arrange.
		$context = [
			'from'   => 'test_from',
			'source' => 'test_source',
		];
		$self_assessment_data = [];

		// Mock account service methods
		$mock_account = $this->createMock( WC_Payments_Account::class );
		WC_Payments::set_account_service( $mock_account );

		$mock_account->expects( $this->once() )
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$mock_account->expects( $this->once() )
			->method( 'get_cached_account_data' )
			->willReturn([
				'capabilities' => [
					'card_payments' => 'active',
				],
			]);

		$mock_account->expects( $this->once() )
			->method( 'overwrite_cache_with_no_account' )
			->willThrowException( new Exception( 'Test error' ) );

		// Assert.
		$this->expectException( API_Exception::class );
		$this->expectExceptionMessage( 'Failed to migrate the account.' );

		// Act.
		$this->onboarding_service->migrate_test_drive_account_to_live( $context, $self_assessment_data );

		// Verify migration flag is cleared even on failure
		$this->assertFalse( $this->onboarding_service->is_onboarding_migrate_to_live_in_progress() );
	}

	/**
	 * Test that capabilities are correctly mapped from account data.
	 */
	public function test_migrate_test_drive_account_to_live_capabilities_mapping() {
		// Arrange.
		$context = [
			'from'   => 'test_from',
			'source' => 'test_source',
		];
		$self_assessment_data = [];

		// Mock account service methods
		$mock_account = $this->createMock( WC_Payments_Account::class );
		WC_Payments::set_account_service( $mock_account );

		$mock_account->expects( $this->once() )
			->method( 'is_stripe_connected' )
			->willReturn( true );

		$mock_account->expects( $this->once() )
			->method( 'get_cached_account_data' )
			->willReturn([
				'capabilities' => [
					'card_payments' => 'active',
					'transfers' => 'inactive',
					'sepa_debit_payments' => 'pending',
					'sofort_payments' => 'active',
				],
			]);

		$mock_account->expects( $this->once() )
			->method( 'overwrite_cache_with_no_account' );

		// Mock expected account session response with mapped capabilities
		$expected_account_session = [
			'client_secret' => 'test_secret',
			'expires_at' => time() + 3600,
			'account_id' => 'acct_test123',
			'is_live' => true,
		];

		// Mock create_embedded_kyc_session and verify capabilities are correctly mapped
		$this->mock_api_client
			->method( 'initialize_onboarding_embedded_kyc' )
			->willReturnCallback(function($data, $progressive, $capabilities) use ($expected_account_session) {
				// Verify capabilities are correctly mapped from status to boolean
				$this->assertEquals(true, $capabilities['card_payments']);
				$this->assertEquals(false, $capabilities['transfers']);
				$this->assertEquals(false, $capabilities['sepa_debit_payments']);
				$this->assertEquals(true, $capabilities['sofort_payments']);
				return $expected_account_session;
			});

		// Act.
		$result = $this->onboarding_service->migrate_test_drive_account_to_live( $context, $self_assessment_data );

		// Assert basic response
		$this->assertEquals( $expected_account_session['client_secret'], $result['clientSecret'] );
	}
}

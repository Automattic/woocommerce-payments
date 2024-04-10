<?php
/**
 * Class WC_Payments_Fraud_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;
use WCPay\Internal\Logger as InternalLogger;

/**
 * WC_Payments_Fraud_Service unit tests.
 */
class WC_Payments_Fraud_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Fraud_Service
	 */
	private $fraud_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * Mock WC_Payments_Session_Service.
	 *
	 * @var WC_Payments_Session_Service|MockObject
	 */
	private $mock_session_service;

	/**
	 * Mock database cache.
	 *
	 * @var Database_Cache|MockObject;
	 */
	private $mock_database_cache;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_customer_service = $this->createMock( WC_Payments_Customer_Service::class );
		$this->mock_account          = $this->createMock( WC_Payments_Account::class );
		$this->mock_session_service  = $this->createMock( WC_Payments_Session_Service::class );
		$this->mock_database_cache   = $this->createMock( Database_Cache::class );

		$this->fraud_service = new WC_Payments_Fraud_Service( $this->mock_api_client, $this->mock_customer_service, $this->mock_account, $this->mock_session_service, $this->mock_database_cache );
		$this->fraud_service->init_hooks();
	}

	public function test_registers_filters_and_actions_properly() {
		$this->assertNotFalse( has_action( 'init', [ $this->fraud_service, 'link_session_if_user_just_logged_in' ] ) );
		$this->assertNotFalse( has_action( 'admin_print_footer_scripts', [ $this->fraud_service, 'add_sift_js_tracker_in_admin' ] ) );
	}

	public function test_get_fraud_services_config_returns_from_account() {
		wp_set_current_user( 1 );
		$this->mock_in_admin();

		$this->mock_account
			->method( 'get_stripe_account_id' )
			->willReturn( 'stripe_acct_123' );

		$this->mock_account
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'fraud_services' => [
						'stripe' => [],
						'sift'   => [
							'bogus_account_entry' => true, // This one is only in the account data.
						],
					],
				]
			);

		$this->mock_database_cache
			->expects( $this->never() )
			->method( 'get_or_add' );

		$this->mock_session_service
			->method( 'get_sift_session_id' )
			->willReturn( 'sift_session_123' );

		if ( function_exists( 'did_filter' ) ) {
			$wcpay_prepare_fraud_config_filter_run = did_filter( 'wcpay_prepare_fraud_config' );
		}

		$result = $this->fraud_service->get_fraud_services_config();

		$this->assertEquals(
			[
				'stripe' => [],
				'sift'   => [
					'bogus_account_entry' => true, // This one is only in the account data.
					'user_id'             => 'stripe_acct_123',
					'session_id'          => 'sift_session_123',
				],
			],
			$result
		);

		if ( function_exists( 'did_filter' ) ) {
			$this->assertEquals( $wcpay_prepare_fraud_config_filter_run + 2, did_filter( 'wcpay_prepare_fraud_config' ) );
		}
	}

	public function test_get_fraud_services_config_returns_from_fetched_config() {
		wp_set_current_user( 1 );
		$this->mock_in_admin();

		$this->mock_account
			->method( 'get_stripe_account_id' )
			->willReturn( 'stripe_acct_123' );

		$this->mock_account
			->method( 'get_cached_account_data' )
			->willReturn( [] );

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'stripe' => [],
					'sift'   => [
						'bogus_fetched_entry' => true, // This one is only in the fetched data.
					],
				]
			);

		$this->mock_session_service
			->method( 'get_sift_session_id' )
			->willReturn( 'sift_session_123' );

		if ( function_exists( 'did_filter' ) ) {
			$wcpay_prepare_fraud_config_filter_run = did_filter( 'wcpay_prepare_fraud_config' );
		}

		$result = $this->fraud_service->get_fraud_services_config();

		$this->assertEquals(
			[
				'stripe' => [],
				'sift'   => [
					'bogus_fetched_entry' => true, // This one is only in the fetched data.
					'user_id'             => 'stripe_acct_123',
					'session_id'          => 'sift_session_123',
				],
			],
			$result
		);

		if ( function_exists( 'did_filter' ) ) {
			$this->assertEquals( $wcpay_prepare_fraud_config_filter_run + 2, did_filter( 'wcpay_prepare_fraud_config' ) );
		}
	}

	public function test_get_fraud_services_config_returns_default() {
		wp_set_current_user( 1 );
		$this->mock_in_admin();

		$this->mock_account
			->method( 'get_stripe_account_id' )
			->willReturn( 'stripe_acct_123' );

		$this->mock_account
			->method( 'get_cached_account_data' )
			->willReturn( [] );

		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn( null );

		$this->mock_session_service
			->method( 'get_sift_session_id' )
			->willReturn( 'sift_session_123' );

		if ( function_exists( 'did_filter' ) ) {
			$wcpay_prepare_fraud_config_filter_run = did_filter( 'wcpay_prepare_fraud_config' );
		}

		$result = $this->fraud_service->get_fraud_services_config();

		$this->assertEquals(
			[
				'stripe' => [],
			],
			$result
		);

		if ( function_exists( 'did_filter' ) ) {
			$this->assertEquals( $wcpay_prepare_fraud_config_filter_run + 1, did_filter( 'wcpay_prepare_fraud_config' ) );
		}
	}

	public function test_link_session_if_user_just_logged_in_bails_on_no_wpcom_connection() {
		$this->mock_api_client
			->method( 'get_blog_id' )
			->willReturn( null );

		$this->mock_session_service
			->expects( $this->never() )
			->method( 'link_current_session_to_customer' );

		$this->fraud_service->link_session_if_user_just_logged_in();
	}

	public function test_link_session_if_user_just_logged_in_bails_if_user_didnt_just_logged_in() {
		$this->mock_api_client
			->method( 'get_blog_id' )
			->willReturn( 123 );

		$this->mock_session_service
			->expects( $this->once() )
			->method( 'user_just_logged_in' )
			->willReturn( false );

		$this->mock_session_service
			->expects( $this->never() )
			->method( 'link_current_session_to_customer' );

		$this->fraud_service->link_session_if_user_just_logged_in();
	}

	public function test_link_session_if_user_just_logged_in_bails_if_sift_is_not_enabled() {
		$this->mock_api_client
			->method( 'get_blog_id' )
			->willReturn( 123 );

		$this->mock_session_service
			->expects( $this->once() )
			->method( 'user_just_logged_in' )
			->willReturn( true );

		$this->mock_account
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'fraud_services' => [
						'stripe' => [],
					],
				]
			);

		$this->mock_session_service
			->expects( $this->never() )
			->method( 'link_current_session_to_customer' );

		$this->fraud_service->link_session_if_user_just_logged_in();
	}

	public function test_link_session_if_user_just_logged_in_bails_if_no_customer_id_for_user() {
		wp_set_current_user( 1 );

		$this->mock_api_client
			->method( 'get_blog_id' )
			->willReturn( 123 );

		$this->mock_session_service
			->expects( $this->once() )
			->method( 'user_just_logged_in' )
			->willReturn( true );

		$this->mock_account
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'fraud_services' => [
						'sift' => [
							'bogus' => true,
						],
					],
				]
			);

		$this->mock_customer_service
			->method( 'get_customer_id_by_user_id' )
			->willReturn( null );

		$this->mock_session_service
			->expects( $this->never() )
			->method( 'link_current_session_to_customer' );

		$this->fraud_service->link_session_if_user_just_logged_in();
	}

	public function test_link_session_if_user_just_logged_in() {
		wp_set_current_user( 1 );

		$this->mock_api_client
			->method( 'get_blog_id' )
			->willReturn( 123 );

		$this->mock_session_service
			->expects( $this->once() )
			->method( 'user_just_logged_in' )
			->willReturn( true );

		$this->mock_account
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'fraud_services' => [
						'sift' => [
							'bogus' => true,
						],
					],
				]
			);

		$this->mock_customer_service
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_id' );

		$this->mock_session_service
			->expects( $this->once() )
			->method( 'link_current_session_to_customer' );

		$this->fraud_service->link_session_if_user_just_logged_in();
	}

	public function test_link_session_if_user_just_logged_in_logs_on_exception() {
		wp_set_current_user( 1 );

		$this->mock_api_client
			->method( 'get_blog_id' )
			->willReturn( 123 );

		$this->mock_session_service
			->expects( $this->once() )
			->method( 'user_just_logged_in' )
			->willReturn( true );

		$this->mock_account
			->method( 'get_cached_account_data' )
			->willReturn(
				[
					'fraud_services' => [
						'sift' => [
							'bogus' => true,
						],
					],
				]
			);

		$this->mock_customer_service
			->method( 'get_customer_id_by_user_id' )
			->willReturn( 'cus_id' );

		$this->mock_session_service
			->expects( $this->once() )
			->method( 'link_current_session_to_customer' )
			->willThrowException( new \WCPay\Exceptions\API_Exception( 'bogus', 'code', 0 ) );

		// Mock the logger.
		$mock_logger = $this->getMockBuilder( 'WC_Logger' )
			->disableOriginalConstructor()
			->getMock();

		$logger_ref = new ReflectionProperty( 'WCPay\Internal\Logger', 'wc_logger' );
		$logger_ref->setAccessible( true );
		$logger_ref->setValue( wcpay_get_container()->get( InternalLogger::class ), $mock_logger );

		// Make sure the gateway is set because the logger will not log otherwise.
		$gateway                = WC_Payments::get_gateway();
		$mock_gateway           = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$mock_gateway->settings = [ 'empty' => false ];
		WC_Payments::set_gateway( $mock_gateway );

		// Use dev mode to ensure logging.
		WC_Payments::mode()->dev();

		$mock_logger
			->expects( $this->once() )
			->method( 'log' )
			->with( $this->anything(), $this->stringStartsWith( '[Tracking] Error when linking session with user' ) );

		$this->fraud_service->link_session_if_user_just_logged_in();

		// Put the previous gateway back.
		WC_Payments::set_gateway( $gateway );
	}

	private function mock_in_admin() {
		global $current_screen;

		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_screen = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( true );
	}
}

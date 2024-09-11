<?php
/**
 * Class WC_Payments_Account_Server_Links_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;
use WCPay\Database_Cache;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Account unit tests for Server Links related methods.
 */
class WC_Payments_Account_Server_Links_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Account
	 */
	private $wcpay_account;

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
	 * Previous user ID.
	 * @var int
	 */
	private $previous_user_id;

	/**
	 * Mock WC_Payments_Action_Scheduler_Service
	 *
	 * @var WC_Payments_Action_Scheduler_Service|MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * Mock WC_Payments_Onboarding_Service.
	 *
	 * @var WC_Payments_Onboarding_Service|MockObject
	 */
	private $mock_onboarding_service;

	/**
	 * Mock WC_Payments_Redirect_Service.
	 *
	 * @var WC_Payments_Redirect_Service|MockObject
	 */
	private $mock_redirect_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->previous_user_id = get_current_user_id();
		// Set admin as the current user.
		wp_set_current_user( 1 );

		// Set the request as if the user is requesting to access a server link.
		add_filter( 'wp_doing_ajax', '__return_false' );
		$_GET['wcpay-link-handler'] = '';

		$this->mock_api_client               = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_database_cache           = $this->createMock( Database_Cache::class );
		$this->mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );
		$this->mock_onboarding_service       = $this->createMock( WC_Payments_Onboarding_Service::class );
		$this->mock_redirect_service         = $this->createMock( WC_Payments_Redirect_Service::class );

		// Mock WC_Payments_Account without redirect_to to prevent headers already sent error.
		$this->wcpay_account = $this->getMockBuilder( WC_Payments_Account::class )
			->setMethods( [ 'init_hooks' ] )
			->setConstructorArgs( [ $this->mock_api_client, $this->mock_database_cache, $this->mock_action_scheduler_service, $this->mock_onboarding_service, $this->mock_redirect_service ] )
			->getMock();

		$this->wcpay_account->init_hooks();
	}

	public function tear_down() {
		wp_set_current_user( $this->previous_user_id );

		unset( $_GET['wcpay-link-handler'] );

		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_doing_ajax', '__return_false' );

		parent::tear_down();
	}

	public function test_maybe_redirect_to_server_link_skips_ajax_requests() {
		add_filter( 'wp_doing_ajax', '__return_true' );

		$this->mock_redirect_service->expects( $this->never() )->method( 'redirect_to_account_link' );

		$this->wcpay_account->maybe_redirect_by_get_param();
	}

	public function test_maybe_redirect_to_server_link_skips_non_admin_users() {
		wp_set_current_user( 0 );

		$this->mock_redirect_service->expects( $this->never() )->method( 'redirect_to_account_link' );

		$this->wcpay_account->maybe_redirect_by_get_param();
	}

	public function test_maybe_redirect_to_server_link_skips_regular_requests() {
		unset( $_GET['wcpay-link-handler'] );

		$this->mock_redirect_service->expects( $this->never() )->method( 'redirect_to_account_link' );

		$this->wcpay_account->maybe_redirect_by_get_param();
	}

	public function test_maybe_redirect_to_server_link_forwards_all_arguments() {
		$_GET['type']       = 'login_link';
		$_GET['id']         = 'link_id';
		$_GET['random_arg'] = 'random_arg';

		$this->mock_redirect_service
			->expects( $this->once() )
			->method( 'redirect_to_account_link' )
			->with(
				[
					'type'       => 'login_link',
					'id'         => 'link_id',
					'random_arg' => 'random_arg',
				]
			);

		$this->wcpay_account->maybe_redirect_by_get_param();
	}
}

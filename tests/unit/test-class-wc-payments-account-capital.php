<?php
/**
 * Class WC_Payments_Account_Capital_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request\Get_Account_Capital_Link;
use WCPay\Core\Server\Response;
use WCPay\Exceptions\API_Exception;
use WCPay\Database_Cache;

/**
 * WC_Payments_Account unit tests for Capital-related methods.
 */
class WC_Payments_Account_Capital_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Account
	 */
	private $wcpay_account;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock Database_Cache
	 *
	 * @var Database_Cache|PHPUnit_Framework_MockObject_MockObject
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
	 * @var WC_Payments_Action_Scheduler_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->previous_user_id = get_current_user_id();
		// Set admin as the current user.
		wp_set_current_user( 1 );

		// Set the request as if the user is requesting to view a capital offer.
		add_filter( 'wp_doing_ajax', '__return_false' );
		$_GET['wcpay-loan-offer'] = '';

		$this->mock_api_client = $this->createMock( 'WC_Payments_API_Client' );

		$this->mock_database_cache = $this->createMock( Database_Cache::class );

		$this->mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );

		// Mock WC_Payments_Account without redirect_to to prevent headers already sent error.
		$this->wcpay_account = $this->getMockBuilder( WC_Payments_Account::class )
			->setMethods( [ 'redirect_to', 'init_hooks' ] )
			->setConstructorArgs( [ $this->mock_api_client, $this->mock_database_cache, $this->mock_action_scheduler_service ] )
			->getMock();
		$this->wcpay_account->init_hooks();
	}

	public function tear_down() {
		wp_set_current_user( $this->previous_user_id );

		unset( $_GET['wcpay-loan-offer'] );

		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_doing_ajax', '__return_false' );

		parent::tear_down();
	}

	public function test_maybe_redirect_to_capital_offer_will_run() {
		$wcpay_account = $this->getMockBuilder( WC_Payments_Account::class )
			->setMethodsExcept( [ 'maybe_redirect_to_capital_offer', 'init_hooks' ] )
			->setConstructorArgs( [ $this->mock_api_client, $this->mock_database_cache, $this->mock_action_scheduler_service ] )
			->getMock();
		$wcpay_account->init_hooks();

		$this->assertNotFalse(
			has_action( 'admin_init', [ $wcpay_account, 'maybe_redirect_to_capital_offer' ] )
		);
	}

	public function test_maybe_redirect_to_capital_offer_skips_ajax_requests() {
		add_filter( 'wp_doing_ajax', '__return_true' );

		$this->mock_wcpay_request( Get_Account_Capital_Link::class, 0 );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}

	public function test_maybe_redirect_to_capital_offer_skips_non_admin_users() {
		wp_set_current_user( 0 );

		$this->mock_wcpay_request( Get_Account_Capital_Link::class, 0 );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}

	public function test_maybe_redirect_to_capital_offer_skips_regular_requests() {
		unset( $_GET['wcpay-loan-offer'] );

		$this->mock_wcpay_request( Get_Account_Capital_Link::class, 0 );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}

	public function test_maybe_redirect_to_capital_offer_redirects_to_capital_offer() {
		$request = $this->mock_wcpay_request( Get_Account_Capital_Link::class );
		$request
			->expects( $this->once() )
			->method( 'set_type' )
			->with( 'capital_financing_offer' );

		$request
			->expects( $this->once() )
			->method( 'set_return_url' )
			->with( 'http://example.org/wp-admin/admin.php?page=wc-admin&path=/payments/overview' );

		$request
			->expects( $this->once() )
			->method( 'set_refresh_url' )
			->with( 'http://example.org/wp-admin/admin.php?wcpay-loan-offer' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( new Response( [ 'url' => 'https://capital.url' ] ) );

		$this->wcpay_account->expects( $this->once() )->method( 'redirect_to' )->with( 'https://capital.url' );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}

	public function test_maybe_redirect_to_capital_offer_redirects_to_overview_on_error() {
		$request = $this->mock_wcpay_request( Get_Account_Capital_Link::class );
		$request
			->expects( $this->once() )
			->method( 'set_type' )
			->with( 'capital_financing_offer' );

		$request
			->expects( $this->once() )
			->method( 'set_return_url' )
			->with( 'http://example.org/wp-admin/admin.php?page=wc-admin&path=/payments/overview' );

		$request
			->expects( $this->once() )
			->method( 'set_refresh_url' )
			->with( 'http://example.org/wp-admin/admin.php?wcpay-loan-offer' );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'Error: This account has no offer of financing from Capital.', 'invalid_request_error', 400 )
			);

		$this->wcpay_account->expects( $this->once() )->method( 'redirect_to' )->with( 'http://example.org/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Foverview&wcpay-loan-offer-error=1' );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}
}

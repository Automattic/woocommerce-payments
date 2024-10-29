<?php
/**
 * Class WC_Payments_Redirect_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Server\Request\Get_Account_Capital_Link;
use WCPay\Core\Server\Request\Get_Account_Login_Data;
use WCPay\Core\Server\Response;
use WCPay\Exceptions\API_Exception;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Redirect_Service unit tests .
 */
class WC_Payments_Redirect_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Previous user ID.
	 * @var int
	 */
	private $previous_user_id;

	/**
	 * Mock WC_Payments_Redirect_Service.
	 *
	 * @var WC_Payments_Redirect_Service|MockObject
	 */
	private $redirect_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->previous_user_id = get_current_user_id();
		// Set admin as the current user.
		wp_set_current_user( 1 );

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->redirect_service = $this->getMockBuilder( WC_Payments_Redirect_Service::class )
			->onlyMethods( [ 'redirect_to' ] )
			->setConstructorArgs( [ $this->mock_api_client ] )
			->getMock();
	}

	public function tear_down() {
		wp_set_current_user( $this->previous_user_id );

		parent::tear_down();
	}

	public function test_maybe_redirect_to_capital_offer_redirects_to_capital_offer() {
		// Set the request as if the user is requesting to view a capital offer.
		$request = $this->mock_wcpay_request( Get_Account_Capital_Link::class );
		$request
			->expects( $this->once() )
			->method( 'set_type' )
			->with( 'capital_financing_offer' );

		$request
			->expects( $this->once() )
			->method( 'set_return_url' )
			->with( admin_url( 'admin.php?page=wc-admin&path=/payments/overview' ) );

		$request
			->expects( $this->once() )
			->method( 'set_refresh_url' )
			->with( admin_url( 'admin.php?wcpay-loan-offer' ) );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( new Response( [ 'url' => 'https://capital.url' ] ) );

		$this->redirect_service->expects( $this->once() )->method( 'redirect_to' )->with( 'https://capital.url' );

		$this->redirect_service->redirect_to_capital_view_offer_page();
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
			->with( admin_url( 'admin.php?page=wc-admin&path=/payments/overview' ) );

		$request
			->expects( $this->once() )
			->method( 'set_refresh_url' )
			->with( admin_url( 'admin.php?wcpay-loan-offer' ) );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willThrowException(
				new API_Exception( 'Error: This account has no offer of financing from Capital.', 'invalid_request_error', 400 )
			);

		$this->redirect_service->expects( $this->once() )->method( 'redirect_to' )->with( 'http://example.org/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Foverview&wcpay-loan-offer-error=1' );

		$this->redirect_service->redirect_to_capital_view_offer_page();
	}

	public function test_redirect_to_account_link_success() {
		$this->mock_api_client
			->method( 'get_link' )
			->willReturn( [ 'url' => 'https://link.url' ] );

		$this->redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( 'https://link.url' );

		$this->redirect_service->redirect_to_account_link(
			[
				'type'       => 'login_link',
				'id'         => 'link_id',
				'random_arg' => 'random_arg',
			]
		);
	}

	public function test_redirect_to_account_link_to_overview_on_error() {
		$this->mock_api_client
			->method( 'get_link' )
			->willThrowException( new API_Exception( 'Error: The requested link is invalid.', 'invalid_request_error', 400 ) );

		$this->redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( admin_url( 'admin.php?page=wc-admin&path=%2Fpayments%2Foverview&wcpay-server-link-error=1' ) );

		$this->redirect_service->redirect_to_account_link(
			[
				'type'       => 'login_link',
				'id'         => 'link_id',
				'random_arg' => 'random_arg',
			]
		);
	}

	public function test_redirect_to_login_success() {
		$request = $this->mock_wcpay_request( Get_Account_Login_Data::class );

		$request->expects( $this->once() )
			->method( 'set_redirect_url' )
			->with( admin_url( 'admin.php?page=wc-admin&path=/payments/overview' ) );

		$request->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( new Response( [ 'url' => 'https://login.url' ] ) );

		$this->redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( 'https://login.url' );

		$this->redirect_service->redirect_to_login();
	}

	public function test_redirect_to_connect_page_no_redirect() {
		// Arrange.
		// Set the request as if the user is already on the Connect page.
		$_GET = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];

		// Assert.
		$this->redirect_service
			->expects( $this->never() )
			->method( 'redirect_to' );

		// Act.
		$this->redirect_service->redirect_to_connect_page();

		// Cleanup.
		unset( $_GET );
	}

	public function test_redirect_to_connect_page_sets_transient_on_error_message() {
		// Arrange.
		// Set the request as if the user is already on the Connect page.
		$_GET = [
			'page' => 'wc-admin',
			'path' => '/some-other-path',
		];

		// Assert.
		$this->redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( admin_url( 'admin.php?page=wc-admin&path=/payments/connect' ) );

		// Act.
		$this->redirect_service->redirect_to_connect_page( 'Error message' );

		// Assert.
		$this->assertEquals( 'Error message', get_transient( WC_Payments_Account::ERROR_MESSAGE_TRANSIENT ) );

		// Cleanup.
		unset( $_GET );
	}

	public function test_redirect_to_connect_page_redirects() {
		// Arrange.
		$_GET = [
			'page' => 'wc-admin',
			'path' => '/some-other-path',
		];

		// Assert.
		$this->redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( admin_url( 'admin.php?page=wc-admin&path=/payments/connect' ) );

		// Act.
		$this->redirect_service->redirect_to_connect_page();

		// Cleanup.
		unset( $_GET );
	}

	public function test_redirect_to_connect_page_redirects_with_from() {
		// Assert.
		$this->redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( admin_url( 'admin.php?page=wc-admin&path=/payments/connect&from=FROM_SOMEWHERE' ) );

		// Act.
		$this->redirect_service->redirect_to_connect_page( null, 'FROM_SOMEWHERE' );
	}

	public function test_redirect_to_connect_page_redirects_without_from_when_empty() {
		// Assert.
		$this->redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( admin_url( 'admin.php?page=wc-admin&path=/payments/connect' ) );

		// Act.
		$this->redirect_service->redirect_to_connect_page( null, '' );
	}

	public function test_redirect_to_connect_page_redirects_with_additional_params() {
		// Assert.
		$this->redirect_service
			->expects( $this->once() )
			->method( 'redirect_to' )
			->with( admin_url( 'admin.php?page=wc-admin&path=/payments/connect&source=some-source' ) );

		// Act.
		$this->redirect_service->redirect_to_connect_page( null, null, [ 'source' => 'some-source' ] );
	}
}

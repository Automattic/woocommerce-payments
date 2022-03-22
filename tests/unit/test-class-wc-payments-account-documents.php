<?php
/**
 * Class WC_Payments_Account_Documents_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Account unit tests for Documents-related methods.
 */
class WC_Payments_Account_Documents_Test extends WP_UnitTestCase {
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
	 * Previous user ID.
	 * @var int
	 */
	private $previous_user_id;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->previous_user_id = get_current_user_id();
		// Set admin as the current user.
		wp_set_current_user( 1 );

		// Set the request as if the user is requesting to view a document.
		add_filter( 'wp_doing_ajax', '__return_false' );
		$_GET['wcpay-view-document'] = 'documentID';
		$_REQUEST['_wpnonce']        = wp_create_nonce( 'wcpay-view-document' );

		$this->mock_api_client = $this->createMock( 'WC_Payments_API_Client' );

		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );
	}

	public function tear_down() {
		wp_set_current_user( $this->previous_user_id );

		unset( $_GET['wcpay-view-document'] );
		unset( $_REQUEST['_wpnonce'] );

		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_doing_ajax', '__return_false' );

		parent::tear_down();
	}

	public function test_maybe_load_document_will_run() {
		$wcpay_account = $this->getMockBuilder( WC_Payments_Account::class )
			->setConstructorArgs( [ $this->mock_api_client ] )
			->getMock();

		$this->assertNotFalse(
			has_action( 'admin_init', [ $wcpay_account, 'maybe_load_document' ] )
		);
	}

	public function test_maybe_load_document_skips_ajax_requests() {
		add_filter( 'wp_doing_ajax', '__return_true' );

		$this->mock_api_client->expects( $this->never() )->method( 'get_document' );

		$this->wcpay_account->maybe_load_document();
	}

	public function test_maybe_load_document_skips_non_admin_users() {
		wp_set_current_user( 0 );

		$this->mock_api_client->expects( $this->never() )->method( 'get_document' );

		$this->wcpay_account->maybe_load_document();
	}

	public function test_maybe_load_document_skips_regular_requests() {
		unset( $_GET['wcpay-view-document'] );

		$this->mock_api_client->expects( $this->never() )->method( 'get_document' );

		$this->wcpay_account->maybe_load_document();
	}

	public function test_maybe_load_document_skips_invalid_nonce() {
		unset( $_REQUEST['_wpnonce'] );

		$this->mock_api_client->expects( $this->never() )->method( 'get_document' );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );

		$this->wcpay_account->maybe_load_document();
	}

	public function test_maybe_load_document_returns_the_document() {
		$expected_response = [
			'headers'  => [ 'content-type' => 'text/html' ],
			'response' => [ 'code' => 200 ],
			'body'     => '<html><body>Document documentID</body></html>',
		];

		$this->mock_api_client
			->method( 'get_document' )
			->with( 'documentID' )
			->willReturn( $expected_response );

		$this->expectOutputString( '<html><body>Document documentID</body></html>' );

		$this->wcpay_account->maybe_load_document();
	}
}

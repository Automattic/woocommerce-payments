<?php
/**
 * Class WC_Payments_Account_Capital_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Account unit tests for Capital-related methods.
 */
class WC_Payments_Account_Capital_Test extends WP_UnitTestCase {
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

		// Set the request as if the user is requesting to view a capital offer.
		add_filter( 'wp_doing_ajax', '__return_false' );
		$_GET['wcpay-loan-offer'] = '';

		$this->mock_api_client = $this->createMock( 'WC_Payments_API_Client' );

		// Mock WC_Payments_Account without redirect_to to prevent headers already sent error.
		$this->wcpay_account = $this->getMockBuilder( WC_Payments_Account::class )
			->setMethods( [ 'redirect_to' ] )
			->setConstructorArgs( [ $this->mock_api_client ] )
			->getMock();
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
			->setConstructorArgs( [ $this->mock_api_client ] )
			->getMock();

		$this->assertNotFalse(
			has_action( 'admin_init', [ $wcpay_account, 'maybe_redirect_to_capital_offer' ] )
		);
	}

	public function test_maybe_redirect_to_capital_offer_skips_ajax_requests() {
		add_filter( 'wp_doing_ajax', '__return_true' );

		$this->mock_api_client->expects( $this->never() )->method( 'get_capital_link' );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}

	public function test_maybe_redirect_to_capital_offer_skips_non_admin_users() {
		wp_set_current_user( 0 );

		$this->mock_api_client->expects( $this->never() )->method( 'get_capital_link' );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}

	public function test_maybe_redirect_to_capital_offer_skips_regular_requests() {
		unset( $_GET['wcpay-loan-offer'] );

		$this->mock_api_client->expects( $this->never() )->method( 'get_capital_link' );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}

	public function test_maybe_redirect_to_capital_offer_redirects_to_capital_offer() {
		$this->mock_api_client
			->method( 'get_capital_link' )
			->with(
				'capital_financing_offer',
				'http://example.org/wp-admin/admin.php?page=wc-admin&path=/payments/overview',
				'http://example.org/wp-admin/admin.php?wcpay-loan-offer'
			)
			->willReturn( [ 'url' => 'https://capital.url' ] );

		$this->wcpay_account->expects( $this->once() )->method( 'redirect_to' )->with( 'https://capital.url' );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}

	public function test_maybe_redirect_to_capital_offer_redirects_to_overview_on_error() {
		$this->mock_api_client
			->method( 'get_capital_link' )
			->with(
				'capital_financing_offer',
				'http://example.org/wp-admin/admin.php?page=wc-admin&path=/payments/overview',
				'http://example.org/wp-admin/admin.php?wcpay-loan-offer'
			)
			->willThrowException( new API_Exception( 'Error: This account has no offer of financing from Capital.', 'invalid_request_error', 400 ) );

		$this->wcpay_account->expects( $this->once() )->method( 'redirect_to' )->with( 'http://example.org/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Foverview&wcpay-loan-offer-error=1' );

		$this->wcpay_account->maybe_redirect_to_capital_offer();
	}
}

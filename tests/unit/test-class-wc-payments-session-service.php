<?php
/**
 * Class WC_Payments_Session_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Session_Service unit tests.
 */
class WC_Payments_Session_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Session_Service
	 */
	private $session_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Session_Handler.
	 *
	 * @var WC_Session_Handler|MockObject
	 */
	private $mock_session_handler;

	/**
	 * Pre-test setup
	 */
	public function setUp(): void {
		parent::setUp();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->session_service = new WC_Payments_Session_Service( $this->mock_api_client );

		$this->mock_session_handler = $this->createMock( WC_Session_Handler::class );
	}

	public function test_user_just_logged_in_returns_false_for_no_user_logged_in() {
		wp_set_current_user( 0 );

		$this->assertFalse( $this->session_service->user_just_logged_in() );
	}

	public function test_user_just_logged_in_returns_false_on_invalid_session() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$customer_id = 1;

		wp_set_current_user( $customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'set' ] )
			->getMock();

		add_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		$this->assertFalse( $this->session_service->user_just_logged_in() );

		remove_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		WC()->session = $tmp_session;
	}

	public function test_user_just_logged_in_returns_false_for_missing_or_invalid_session_cookie() {
		wp_set_current_user( 1 );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn( false );

		$this->assertFalse( $this->session_service->user_just_logged_in() );

		WC()->session = $tmp_session;
	}

	public function test_user_just_logged_in() {
		$new_customer_id = 1;
		$old_customer_id = 2;

		wp_set_current_user( $new_customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn(
				[
					$old_customer_id,
					time() + ( 60 * 60 * 48 ),
					time() + ( 60 * 60 * 47 ),
					'hash',
				]
			);
		$this->mock_session_handler
			->method( 'get_customer_id' )
			->willReturn( $new_customer_id );

		$this->assertTrue( $this->session_service->user_just_logged_in() );

		WC()->session = $tmp_session;
	}

	public function test_get_sift_session_id_returns_null_when_no_jetpack_connection() {
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( null );

		$this->assertNull( $this->session_service->get_sift_session_id() );
	}

	public function test_get_sift_session_id_from_cookie_when_user_just_logged_in() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$new_customer_id = 1;
		$old_customer_id = 222;

		wp_set_current_user( $new_customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn(
				[
					$old_customer_id,
					time() + ( 60 * 60 * 48 ),
					time() + ( 60 * 60 * 47 ),
					'hash',
				]
			);
		$this->mock_session_handler
			->method( 'get_customer_id' )
			->willReturn( $new_customer_id );

		$this->assertEquals( $wpcom_blog_id . '_' . $old_customer_id, $this->session_service->get_sift_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_get_sift_session_id_from_session_when_didnt_user_just_logged_in() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$customer_id = 1;

		wp_set_current_user( $customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn(
				[
					$customer_id, // The same customer ID is used for the cookie and the session.
					time() + ( 60 * 60 * 48 ),
					time() + ( 60 * 60 * 47 ),
					'hash',
				]
			);
		$this->mock_session_handler
			->method( 'get_customer_id' )
			->willReturn( $customer_id );

		$this->assertEquals( $wpcom_blog_id . '_' . $customer_id, $this->session_service->get_sift_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_get_sift_session_id_returns_null_on_invalid_session() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$customer_id = 1;

		wp_set_current_user( $customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'set' ] )
			->getMock();

		add_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		$this->assertNull( $this->session_service->get_sift_session_id() );

		remove_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		WC()->session = $tmp_session;
	}

	public function test_get_cookie_session_id_returns_null_when_no_jetpack_connection() {
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( null );

		$this->assertNull( $this->session_service->get_cookie_session_id() );
	}

	public function test_get_cookie_session_id_returns_null_when_no_session_handler() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$tmp_session  = WC()->session;
		WC()->session = null;

		$this->assertNull( $this->session_service->get_cookie_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_get_cookie_session_id_returns_null_when_invalid_session_handler() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'set' ] )
			->getMock();

		add_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		$this->assertNull( $this->session_service->get_cookie_session_id() );

		remove_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		WC()->session = $tmp_session;
	}

	public function test_get_cookie_session_id_returns_null_when_no_cookie() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn( false );

		$this->assertNull( $this->session_service->get_cookie_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_get_cookie_session_id() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$customer_id = 'customer_id';
		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn(
				[
					$customer_id,
					time() + ( 60 * 60 * 48 ),
					time() + ( 60 * 60 * 47 ),
					'hash',
				]
			);

		$this->assertEquals( $wpcom_blog_id . '_' . $customer_id, $this->session_service->get_cookie_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_link_current_session_to_customer() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$customer_id = 'customer_id';
		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn(
				[
					$customer_id,
					time() + ( 60 * 60 * 48 ),
					time() + ( 60 * 60 * 47 ),
					'hash',
				]
			);

		$expected_response = [ 'success' => true ];
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'link_session_to_customer' )
			->with( $wpcom_blog_id . '_' . $customer_id, $customer_id )
			->willReturn( $expected_response );

		$this->assertEquals( $expected_response, $this->session_service->link_current_session_to_customer( $customer_id ) );

		WC()->session = $tmp_session;
	}

	public function return_stdclass() {
		return stdClass::class;
	}
}

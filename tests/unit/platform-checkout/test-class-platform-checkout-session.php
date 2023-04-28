<?php
/**
 * Class Platform_Checkout_Session_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Database_Cache;
use WCPay\Platform_Checkout\Platform_Checkout_Store_Api_Token;
use WCPay\Platform_Checkout\Platform_Checkout_Session;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;

/**
 * Platform_Checkout_Session unit tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Platform_Checkout_Session_Test extends WP_UnitTestCase {

	/**
	 * @var PHPUnit_Framework_MockObject_MockObject|Database_Cache
	 */
	private $mock_cache;

	public function set_up() {
		parent::set_up();
		$this->mock_cache = $this->createMock( Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );
	}

	public function test_determine_current_user_for_platform_checkout_with_valid_request_returns_user_from_token() {
		$user_id = 123;
		$this->generate_valid_platform_checkout_session( $user_id );

		$this->assertEquals( $user_id, Platform_Checkout_Session::determine_current_user_for_platform_checkout( null ) );
	}

	public function test_determine_current_user_for_platform_checkout_with_invalid_request_returns_user() {
		$this->generate_valid_platform_checkout_session( 123 );
		// Set the user agent to something else.
		$_SERVER['HTTP_USER_AGENT'] = 'Not WooPay';

		$this->assertEquals( 1, Platform_Checkout_Session::determine_current_user_for_platform_checkout( 1 ) );
	}

	public function test_determine_current_user_for_platform_checkout_with_invalid_request_uri() {
		$this->generate_valid_platform_checkout_session( 123 );
		// Set the request URI to something else.
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/products';

		$this->assertNull( Platform_Checkout_Session::determine_current_user_for_platform_checkout( null ) );
	}

	public function test_determine_current_user_for_platform_checkout_with_woopay_disabled() {
		$this->generate_valid_platform_checkout_session( 123 );
		// Disable WooPay.
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'no' );

		$this->assertNull( Platform_Checkout_Session::determine_current_user_for_platform_checkout( null ) );
	}

	public function test_determine_current_user_for_platform_checkout_with_invalid_jetpack_signature() {
		$this->generate_valid_platform_checkout_session( 123 );
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_false', 11 );

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'WooPay request is not signed correctly.' );
		$this->expectExceptionCode( 401 );

		Platform_Checkout_Session::determine_current_user_for_platform_checkout( null );
	}

	private function generate_valid_platform_checkout_session( int $user_id ) {
		add_filter(
			'woocommerce_session_handler',
			function ( $session_handler ) {
				return \WCPay\Platform_Checkout\SessionHandler::class;
			},
			20
		);

		// Generate a valid cart token.
		$cart_token = JsonWebToken::create(
			[
				'user_id' => $user_id,
				'exp'     => time() + DAY_IN_SECONDS,
				'iss'     => 'wc/store/v1',
			],
			'@' . wp_salt()
		);

		// Set up the request.
		$_SERVER['HTTP_USER_AGENT'] = 'WooPay';
		$_SERVER['REQUEST_URI']     = '/wp-json/wc/store/cart';
		$_SERVER['HTTP_CART_TOKEN'] = $cart_token;
		// The `runTestsInSeparateProcesses` annotation is necessary because of this constant.
		define( 'REST_REQUEST', true );

		// Set WooPay as enabled.
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );

		// Apply filter to make sure the request signature is valid.
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		// Initialize mock session based on valid cart token.
		WC()->initialize_session();
		$session = WC()->session;
		$session->set( 'customer', [ 'id' => $user_id ] );
		$session->save_data();
	}
}

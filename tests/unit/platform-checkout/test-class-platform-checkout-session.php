<?php
/**
 * Class Platform_Checkout_Session_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use \WCPay\Platform_Checkout\Platform_Checkout_Session;

/**
 * Platform_Checkout_Session unit tests.
 */
class Platform_Checkout_Session_Test extends WP_UnitTestCase {

	public function test_determine_current_user_for_platform_checkout_passing_null() {
		$result = Platform_Checkout_Session::determine_current_user_for_platform_checkout( null );
		$this->assertEquals( null, $result );
	}

	public function test_determine_current_user_for_platform_checkout_passing_user() {
		$user = new WP_User();

		$result = Platform_Checkout_Session::determine_current_user_for_platform_checkout( $user );
		$this->assertEquals( $user, $result );
	}

	public function test_determine_current_user_for_platform_checkout_via_header() {
		$_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] = 'asdf';

		$result = Platform_Checkout_Session::determine_current_user_for_platform_checkout( null );
		$this->assertEquals( null, $result );
	}

	public function test_determine_current_user_for_platform_checkout_via_invalid_header() {
		$_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] = 1;

		$result = Platform_Checkout_Session::determine_current_user_for_platform_checkout( null );
		$this->assertEquals( 1, $result );
	}

	public function test_determine_session_cookie_for_platform_checkout() {
		$cookie_hash = 'cookie_hash';

		$result = Platform_Checkout_Session::determine_session_cookie_for_platform_checkout( $cookie_hash );
		$this->assertEquals( $cookie_hash, $result );
	}

	public function test_determine_session_cookie_for_platform_checkout_via_header() {
		$cookie_hash = 'cookie_hash';

		$_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] = 0;

		$result = Platform_Checkout_Session::determine_session_cookie_for_platform_checkout( $cookie_hash );
		$this->assertEquals( Platform_Checkout_Session::PLATFORM_CHECKOUT_SESSION_COOKIE_NAME, $result );
	}
}

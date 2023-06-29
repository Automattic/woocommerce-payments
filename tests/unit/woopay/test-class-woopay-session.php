<?php
/**
 * Class WooPay_Session_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Session;
use WCPay\Platform_Checkout\WooPay_Store_Api_Token;
use WCPay\Platform_Checkout\SessionHandler;

/**
 * WooPay_Session unit tests.
 */
class WooPay_Session_Test extends WCPAY_UnitTestCase {
	public function test_get_user_id_from_cart_token_with_guest_user() {
		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN'] = $guest_cart_token;

		$this->setup_session( 0 );

		$this->assertEquals( WooPay_Session::get_user_id_from_cart_token(), 0 );
	}

	public function test_get_user_id_from_cart_token_with_non_numeric_customer_id() {
		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN'] = $guest_cart_token;

		$this->setup_session( 'abc' );

		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_get_user_id_from_cart_token_with_logged_in_user() {
		$user = self::factory()->user->create_and_get();

		wp_set_current_user( $user->ID );

		$woopay_store_api_token   = WooPay_Store_Api_Token::init();
		$authenticated_cart_token = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN'] = $authenticated_cart_token;

		$this->setup_session( $user->ID );

		$this->assertEquals( WooPay_Session::get_user_id_from_cart_token(), $user->ID );

		wp_set_current_user( 0 );
	}

	public function test_get_user_id_from_cart_token_with_verified_user_store_api_token_without_id_in_session() {
		$verified_user = self::factory()->user->create_and_get();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();
		$verified_user_token    = $woopay_store_api_token->get_store_api_token_for_user_id( $verified_user->ID );

		$_SERVER['HTTP_CART_TOKEN']                      = $guest_cart_token;
		$_SERVER['HTTP_WOOPAY_VERIFIED_USER_CART_TOKEN'] = $verified_user_token;

		$this->setup_session( 0 );

		$this->assertEquals( WooPay_Session::get_user_id_from_cart_token(), 0 );
	}

	public function test_get_user_id_from_cart_token_with_verified_user_store_api_token() {
		$verified_user = self::factory()->user->create_and_get();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();
		$verified_user_token    = $woopay_store_api_token->get_store_api_token_for_user_id( $verified_user->ID );

		$_SERVER['HTTP_CART_TOKEN']                      = $guest_cart_token;
		$_SERVER['HTTP_WOOPAY_VERIFIED_USER_CART_TOKEN'] = $verified_user_token;

		$this->setup_session(
			0,
			[
				'woopay_verified_user_id' => $verified_user->ID,
			]
		);

		$this->assertEquals( WooPay_Session::get_user_id_from_cart_token(), $verified_user->ID );
	}

	private function setup_session( $customer_id, $extra = [] ) {
		$session_handler = new SessionHandler();

		$session_handler->init();
		$session_handler->set( 'cart', 'fake cart' );
		$session_handler->set( 'customer', [ 'id' => $customer_id ] );

		foreach ( $extra as $key => $value ) {
			$session_handler->set( $key, $value );
		}

		$session_handler->save_data();
	}
}

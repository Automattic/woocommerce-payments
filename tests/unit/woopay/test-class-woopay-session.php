<?php
/**
 * Class WooPay_Session_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\WooPay\WooPay_Session;
use WCPay\Platform_Checkout\WooPay_Store_Api_Token;
use WCPay\Platform_Checkout\SessionHandler;
use WCPay\WooPay\WooPay_Scheduler;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * WooPay_Session unit tests.
 */
class WooPay_Session_Test extends WCPAY_UnitTestCase {
	/**
	 * @var Database_Cache|MockObject
	 */
	protected $mock_cache;

	/**
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|MockObject
	 */
	private $mock_customer_service;

	/**
	 * WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $original_customer_service;

	public function set_up() {
		parent::set_up();

		// Clear any existing test products and their lookup table entries.
		global $wpdb;

		$test_product_ids = $wpdb->get_col(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'product'
			 AND pm.meta_key = '_sku'
			 AND (pm.meta_value LIKE 'DUMMY SKU%' OR pm.meta_value LIKE 'TEST_SKU_%')"
		);

		// deleting any test products created by the WC_Helper_Product helper.
		foreach ( $test_product_ids as $product_id ) {
			wp_delete_post( $product_id, true );
		}

		// clearing up any remaining orphaned lookup entries.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id NOT IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product')" );

		// clearing up any object caches.
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		wp_cache_delete( 'wc_product_meta_lookup', 'woocommerce' );

		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );

		// Enable woopay.
		$this->set_is_woopay_eligible( true );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );

		$_SERVER['HTTP_USER_AGENT'] = 'WooPay';
		$_SERVER['REQUEST_URI']     = '/wp-json/wc/store/v1/checkout';

		$this->mock_customer_service     = $this->createMock( WC_Payments_Customer_Service::class );
		$this->original_customer_service = WC_Payments::get_customer_service();
		WC_Payments::set_customer_service( $this->mock_customer_service );

		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );
	}

	public function tear_down() {
		WC_Payments::set_customer_service( $this->original_customer_service );

		wp_set_current_user( 0 );

		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );
		remove_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_true' );
		remove_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_false' );

		unset(
			$_SERVER['HTTP_NONCE'],
			$_SERVER['HTTP_CART_TOKEN'],
			$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS']
		);

		parent::tear_down();
	}

	public function test_get_user_id_from_cart_token_with_guest_user() {
		define( 'REST_REQUEST', true );

		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN'] = $guest_cart_token;

		$this->setup_session( 0 );
		$this->setup_adapted_extensions();

		$this->assertEquals( WooPay_Session::get_user_id_from_cart_token(), 0 );
	}

	public function test_get_user_id_from_cart_token_with_non_numeric_customer_id() {
		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN'] = $guest_cart_token;

		$this->setup_session( 'abc' );
		$this->setup_adapted_extensions();

		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_get_user_id_from_cart_token_with_logged_in_user() {
		$user = self::factory()->user->create_and_get();

		wp_set_current_user( $user->ID );

		$woopay_store_api_token   = WooPay_Store_Api_Token::init();
		$authenticated_cart_token = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN'] = $authenticated_cart_token;

		$this->setup_session( $user->ID );
		$this->setup_adapted_extensions();

		$this->assertEquals( WooPay_Session::get_user_id_from_cart_token(), $user->ID );
	}

	public function test_get_user_id_from_cart_token_with_verified_user_email_address_header_without_email_in_session() {
		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN']                      = $guest_cart_token;
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = 'test@example.com';

		$this->setup_session( 0 );
		$this->setup_adapted_extensions();

		$this->assertEquals( WooPay_Session::get_user_id_from_cart_token(), 0 );
	}

	public function test_get_user_id_from_cart_token_with_verified_user_store_api_token_without_adapted_extensions() {
		$verified_user = self::factory()->user->create_and_get();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN']                      = $guest_cart_token;
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;

		$this->setup_session(
			0,
			$verified_user->user_email
		);

		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_get_user_id_from_cart_token_with_verified_user_store_api_token() {
		$verified_user = self::factory()->user->create_and_get();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN']                      = $guest_cart_token;
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;

		$this->setup_session(
			0,
			$verified_user->user_email
		);
		$this->setup_adapted_extensions();

		$this->assertEquals( WooPay_Session::get_user_id_from_cart_token(), $verified_user->ID );
	}

	public function test_woopay_order_payment_status_changed_with_verified_user_store_api_token_without_adapted_extensions() {
		$verified_user = self::factory()->user->create_and_get();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN']                      = $guest_cart_token;
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;

		$order = \WC_Helper_Order::create_order( $verified_user->ID );
		$order->set_billing_email( $verified_user->user_email );
		$order->save();
		WooPay_Session::woopay_order_payment_status_changed( $order->get_id() );

		$updated_order = wc_get_order( $order->get_id() );
		$this->assertEmpty( $updated_order->get_meta( 'woopay_merchant_customer_id' ) );
		$this->assertEquals( $updated_order->get_customer_id(), $verified_user->ID );
	}

	public function test_woopay_order_payment_status_changed_with_verified_user_store_api_token_with_non_matching_order_billing_email() {
		$verified_user = self::factory()->user->create_and_get();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN']                      = $guest_cart_token;
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;

		$this->setup_adapted_extensions();

		$order = \WC_Helper_Order::create_order( $verified_user->ID );
		$order->set_billing_email( 'test@example.com' );
		$order->save();
		WooPay_Session::woopay_order_payment_status_changed( $order->get_id() );

		$updated_order = wc_get_order( $order->get_id() );
		$this->assertEmpty( $updated_order->get_meta( 'woopay_merchant_customer_id' ) );
		$this->assertEquals( $updated_order->get_customer_id(), $verified_user->ID );
	}

	public function test_woopay_order_payment_status_changed_with_verified_user_store_api_token() {
		$verified_user = self::factory()->user->create_and_get();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN']                      = $guest_cart_token;
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;

		$this->setup_adapted_extensions();

		$order = \WC_Helper_Order::create_order( $verified_user->ID );
		$order->set_billing_email( $verified_user->user_email );
		$order->save();
		WooPay_Session::woopay_order_payment_status_changed( $order->get_id() );

		$updated_order = wc_get_order( $order->get_id() );
		$this->assertEquals( $updated_order->get_meta( 'woopay_merchant_customer_id' ), $verified_user->ID );
		$this->assertEquals( $updated_order->get_customer_id(), 0 );
	}

	public function test_session_currency_set_for_multi_currency_enabled() {
		$user_id = 1;
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->with( $user_id )
			->willReturn( $user_id );

		// For multi-currency enabled.
		update_option( '_wcpay_feature_customer_multi_currency', '1' );

		// Set mismatched user and session currency codes.
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, 'ABC' );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, MultiCurrency::CURRENCY_META_KEY, 'DEF' );

		WooPay_Session::get_frontend_init_session_request();

		// Currency in session should have been modified.
		$this->assertSame(
			'DEF',
			WC()->session->get( MultiCurrency::CURRENCY_SESSION_KEY )
		);

		// Destroy session data.
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, null );
	}

	public function test_session_currency_not_set_for_multi_currency_disabled() {
		$user_id = 1;
		$this->mock_customer_service
			->expects( $this->once() )
			->method( 'get_customer_id_by_user_id' )
			->with( $user_id )
			->willReturn( $user_id );

		// For multi-currency disabled.
		update_option( '_wcpay_feature_customer_multi_currency', '0' );

		// Set mismatched user and session currency codes.
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, 'ABC' );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, MultiCurrency::CURRENCY_META_KEY, 'DEF' );

		WooPay_Session::get_frontend_init_session_request();

		// Currency in session should NOT have been modified.
		$this->assertSame(
			'ABC',
			WC()->session->get( MultiCurrency::CURRENCY_SESSION_KEY )
		);

		// Destroy session data.
		WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, null );
	}

	public function test_determine_current_user_is_request_woopay_false() {
		$_SERVER['HTTP_USER_AGENT'] = 'NotWooPay';

		$guest_user = 0;
		self::factory()->user->create_and_get();

		$this->assertEquals( WooPay_Session::determine_current_user_for_woopay( $guest_user ), 0 );
	}

	public function test_determine_current_user_is_store_api_request_false() {
		$_SERVER['REQUEST_URI'] = '/another/store/url';

		$guest_user = 0;
		self::factory()->user->create_and_get();

		$this->assertEquals( WooPay_Session::determine_current_user_for_woopay( $guest_user ), 0 );
	}

	public function test_determine_current_user_is_store_api_request_true_using_uri() {
		$guest_user  = 0;
		$woopay_user = self::factory()->user->create_and_get();

		wp_set_current_user( $woopay_user->ID );

		$woopay_store_api_token   = WooPay_Store_Api_Token::init();
		$authenticated_cart_token = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN'] = $authenticated_cart_token;

		$this->setup_session( $woopay_user->ID );

		$this->assertEquals( WooPay_Session::determine_current_user_for_woopay( $guest_user ), $woopay_user->ID );
	}

	public function test_determine_current_user_is_store_api_request_true_using_rest_route_parameter() {
		$_SERVER['REQUEST_URI'] = '/index.php';
		$_REQUEST['rest_route'] = '/wc/store/v1/checkout';

		$guest_user  = 0;
		$woopay_user = self::factory()->user->create_and_get();

		wp_set_current_user( $woopay_user->ID );

		$woopay_store_api_token   = WooPay_Store_Api_Token::init();
		$authenticated_cart_token = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN'] = $authenticated_cart_token;

		$this->setup_session( $woopay_user->ID );

		$this->assertEquals( WooPay_Session::determine_current_user_for_woopay( $guest_user ), $woopay_user->ID );

		unset( $_REQUEST['rest_route'] );
	}

	public function test_get_request_auth_level_returns_blog_token_when_signed() {
		$this->assertSame( WooPay_Session::AUTH_BLOG_TOKEN, WooPay_Session::get_request_auth_level() );
	}

	public function test_get_request_auth_level_returns_none_without_signature_when_cart_token_auth_disabled() {
		$this->unsign_request();
		$this->deny_cart_token_auth();

		$woopay_store_api_token     = WooPay_Store_Api_Token::init();
		$_SERVER['HTTP_CART_TOKEN'] = $woopay_store_api_token->get_cart_token();

		$this->assertSame( WooPay_Session::AUTH_NONE, WooPay_Session::get_request_auth_level() );
	}

	public function test_get_request_auth_level_returns_cart_token_by_default() {
		$this->unsign_request();

		$woopay_store_api_token     = WooPay_Store_Api_Token::init();
		$_SERVER['HTTP_CART_TOKEN'] = $woopay_store_api_token->get_cart_token();

		$this->assertSame( WooPay_Session::AUTH_CART_TOKEN, WooPay_Session::get_request_auth_level() );
	}

	public function test_store_advertises_cart_token_auth_support_by_default() {
		$this->assertTrue( WooPay_Session::is_cart_token_auth_allowed() );
	}

	public function test_get_request_auth_level_returns_none_when_cart_token_is_invalid() {
		$this->unsign_request();
		$this->allow_cart_token_auth();

		$_SERVER['HTTP_CART_TOKEN'] = 'not-a-valid-cart-token';

		$this->assertSame( WooPay_Session::AUTH_NONE, WooPay_Session::get_request_auth_level() );
	}

	public function test_verified_email_is_rejected_under_cart_token_auth_without_store_minted_nonce() {
		$verified_user = self::factory()->user->create_and_get();

		$this->unsign_request();
		$this->allow_cart_token_auth();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();

		$_SERVER['HTTP_CART_TOKEN']                      = $woopay_store_api_token->get_cart_token();
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;

		$this->setup_session( 0, $verified_user->user_email );
		$this->setup_adapted_extensions();

		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_verified_email_is_rejected_under_cart_token_auth_with_nonce_for_another_user() {
		$verified_user = self::factory()->user->create_and_get();
		$other_user    = self::factory()->user->create_and_get();

		$this->unsign_request();
		$this->allow_cart_token_auth();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();

		$_SERVER['HTTP_CART_TOKEN']                      = $woopay_store_api_token->get_cart_token();
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;
		$_SERVER['HTTP_NONCE']                           = $this->create_woopay_nonce( $other_user->ID );

		$this->setup_session( 0, $verified_user->user_email );
		$this->setup_adapted_extensions();

		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_verified_email_is_accepted_under_cart_token_auth_with_store_minted_nonce() {
		$verified_user = self::factory()->user->create_and_get();

		$this->unsign_request();
		$this->allow_cart_token_auth();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();

		$_SERVER['HTTP_CART_TOKEN']                      = $woopay_store_api_token->get_cart_token();
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;
		$_SERVER['HTTP_NONCE']                           = $this->create_woopay_nonce( $verified_user->ID );

		$this->setup_session( 0, $verified_user->user_email );
		$this->setup_adapted_extensions();

		$this->assertEquals( $verified_user->ID, WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_verified_email_does_not_require_nonce_when_request_is_signed() {
		$verified_user = self::factory()->user->create_and_get();

		$woopay_store_api_token = WooPay_Store_Api_Token::init();

		$_SERVER['HTTP_CART_TOKEN']                      = $woopay_store_api_token->get_cart_token();
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;

		$this->setup_session( 0, $verified_user->user_email );
		$this->setup_adapted_extensions();

		$this->assertEquals( $verified_user->ID, WooPay_Session::get_user_id_from_cart_token() );
	}

	/**
	 * Drops the blog token signature that set_up() installs.
	 */
	private function unsign_request() {
		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );
	}

	private function allow_cart_token_auth() {
		add_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_true' );
	}

	private function deny_cart_token_auth() {
		add_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_false' );
	}

	/**
	 * Mints a nonce the same way the store does, via the private producer, so these
	 * tests fail if the producer and verifier ever drift apart.
	 */
	private function create_woopay_nonce( int $uid ): string {
		$method = new ReflectionMethod( WooPay_Session::class, 'create_woopay_nonce' );
		$method->setAccessible( true );

		return $method->invoke( null, $uid );
	}

	private function setup_session( $customer_id, $customer_email = null ) {
		$session_handler = new SessionHandler();

		$session_handler->init();
		$session_handler->set( 'cart', 'fake cart' );
		$session_handler->set(
			'customer',
			[
				'id'    => $customer_id,
				'email' => $customer_email,
			]
		);

		$session_handler->save_data();
	}

	private function setup_adapted_extensions() {
		update_option( WooPay_Scheduler::ENABLED_ADAPTED_EXTENSIONS_OPTION_NAME, [ 'woocommerce-points-and-rewards' ] );
	}

	private function set_is_woopay_eligible( $is_woopay_eligible ) {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_woopay_eligible ] );
	}
}

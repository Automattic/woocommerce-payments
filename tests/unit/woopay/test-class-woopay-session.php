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

		// Clean up IP-related headers that tests may have set.
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		unset( $_SERVER['HTTP_X_REAL_IP'] );

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

		$guest_user  = 0;
		$woopay_user = self::factory()->user->create_and_get();

		$this->assertEquals( WooPay_Session::determine_current_user_for_woopay( $guest_user ), 0 );
	}

	public function test_determine_current_user_is_store_api_request_false() {
		$_SERVER['REQUEST_URI'] = '/another/store/url';

		$guest_user  = 0;
		$woopay_user = self::factory()->user->create_and_get();

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

	/**
	 * Tests for maybe_override_customer_ip().
	 */

	public function test_maybe_override_customer_ip_sets_remote_addr_from_x_forwarded_for() {
		$original_remote_addr            = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$_SERVER['REMOTE_ADDR']          = '192.0.91.172';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		WooPay_Session::maybe_override_customer_ip();

		$this->assertEquals( '203.0.113.50', $_SERVER['REMOTE_ADDR'] );

		$_SERVER['REMOTE_ADDR'] = $original_remote_addr;
	}

	public function test_maybe_override_customer_ip_uses_first_ip_from_x_forwarded_for_chain() {
		$original_remote_addr            = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$_SERVER['REMOTE_ADDR']          = '192.0.91.172';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 198.51.100.10, 192.0.89.12';

		WooPay_Session::maybe_override_customer_ip();

		$this->assertEquals( '203.0.113.50', $_SERVER['REMOTE_ADDR'] );

		$_SERVER['REMOTE_ADDR'] = $original_remote_addr;
	}

	public function test_maybe_override_customer_ip_falls_back_to_x_real_ip() {
		$original_remote_addr      = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$_SERVER['REMOTE_ADDR']    = '192.0.91.172';
		$_SERVER['HTTP_X_REAL_IP'] = '198.51.100.25';

		WooPay_Session::maybe_override_customer_ip();

		$this->assertEquals( '198.51.100.25', $_SERVER['REMOTE_ADDR'] );

		$_SERVER['REMOTE_ADDR'] = $original_remote_addr;
	}

	public function test_maybe_override_customer_ip_prefers_x_forwarded_for_over_x_real_ip() {
		$original_remote_addr            = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$_SERVER['REMOTE_ADDR']          = '192.0.91.172';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';
		$_SERVER['HTTP_X_REAL_IP']       = '198.51.100.25';

		WooPay_Session::maybe_override_customer_ip();

		$this->assertEquals( '203.0.113.50', $_SERVER['REMOTE_ADDR'] );

		$_SERVER['REMOTE_ADDR'] = $original_remote_addr;
	}

	public function test_maybe_override_customer_ip_ignores_non_woopay_requests() {
		$_SERVER['HTTP_USER_AGENT']      = 'Mozilla/5.0';
		$original_remote_addr            = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$_SERVER['REMOTE_ADDR']          = '192.0.91.172';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

		WooPay_Session::maybe_override_customer_ip();

		// REMOTE_ADDR should NOT be changed.
		$this->assertEquals( '192.0.91.172', $_SERVER['REMOTE_ADDR'] );

		$_SERVER['REMOTE_ADDR']     = $original_remote_addr;
		$_SERVER['HTTP_USER_AGENT'] = 'WooPay';
	}

	public function test_maybe_override_customer_ip_ignores_invalid_ip() {
		$original_remote_addr            = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$_SERVER['REMOTE_ADDR']          = '192.0.91.172';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip-address';

		WooPay_Session::maybe_override_customer_ip();

		// REMOTE_ADDR should NOT be changed when the header has an invalid IP.
		$this->assertEquals( '192.0.91.172', $_SERVER['REMOTE_ADDR'] );

		$_SERVER['REMOTE_ADDR'] = $original_remote_addr;
	}

	public function test_maybe_override_customer_ip_no_change_when_no_ip_headers() {
		$original_remote_addr   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$_SERVER['REMOTE_ADDR'] = '192.0.91.172';

		// No X-Forwarded-For or X-Real-IP set.
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		unset( $_SERVER['HTTP_X_REAL_IP'] );

		WooPay_Session::maybe_override_customer_ip();

		// REMOTE_ADDR should remain unchanged.
		$this->assertEquals( '192.0.91.172', $_SERVER['REMOTE_ADDR'] );

		$_SERVER['REMOTE_ADDR'] = $original_remote_addr;
	}

	public function test_maybe_override_customer_ip_handles_ipv6() {
		$original_remote_addr            = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$_SERVER['REMOTE_ADDR']          = '192.0.91.172';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db8::1';

		WooPay_Session::maybe_override_customer_ip();

		$this->assertEquals( '2001:db8::1', $_SERVER['REMOTE_ADDR'] );

		$_SERVER['REMOTE_ADDR'] = $original_remote_addr;
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

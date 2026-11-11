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
use WCPay\WooPay\WooPay_Utilities;
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

	/**
	 * Snapshot of $_SERVER, restored on tear down.
	 *
	 * @var array
	 */
	private $original_server;

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

		$this->original_server      = $_SERVER;
		$_SERVER['HTTP_USER_AGENT'] = 'WooPay';
		WCPay_Test_Request_Context::set( 'store_api' );

		// Tests dispatch REST requests internally (rest_preload_api_request) without WooPay credentials.
		// The authentication hook is exercised by calling it directly.
		remove_filter( 'rest_request_before_callbacks', [ WooPay_Session::class, 'authenticate_woopay_rest_request' ], 20 );
		remove_filter( 'rest_request_after_callbacks', [ WooPay_Session::class, 'end_woopay_rest_request' ], 20 );

		$this->mock_customer_service     = $this->createMock( WC_Payments_Customer_Service::class );
		$this->original_customer_service = WC_Payments::get_customer_service();
		WC_Payments::set_customer_service( $this->mock_customer_service );

		// Needed as the key for attested-email envelopes; see build_envelope().
		Jetpack_Options::update_option( 'blog_token', 'test.blog.token' );
	}

	public function tear_down() {
		// Leave no request marked as WooPay-authenticated for the next test.
		$accepted_requests = new ReflectionProperty( WooPay_Session::class, 'woopay_rest_requests' );
		$accepted_requests->setAccessible( true );
		$accepted_requests->setValue( null, [] );

		add_filter( 'rest_request_before_callbacks', [ WooPay_Session::class, 'authenticate_woopay_rest_request' ], 20, 3 );
		add_filter( 'rest_request_after_callbacks', [ WooPay_Session::class, 'end_woopay_rest_request' ], 20, 3 );
		$_SERVER = $this->original_server;
		WCPay_Test_Request_Context::reset();

		// Leave an ordinary front-end request behind rather than no request at all —
		// WordPress always defines this, and code under test dereferences it directly.
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = '/';
		}

		WC_Payments::set_customer_service( $this->original_customer_service );

		wp_set_current_user( 0 );

		unset( $_SERVER[ WooPay_Session::VOUCH_HEADER ] );
		unset( $_GET['email'], $_POST['email'], $_REQUEST['_ajax_nonce'] );

		remove_filter( 'pre_option_home', [ $this, 'serve_home_on_port_8052' ] );
		remove_all_filters( 'wcpay_woopay_session_allowed_origins' );

		WC()->session->__unset( WooPay_Session::WOOPAY_SESSION_KEY );

		// Here rather than after each assertion, so a failing test cannot leave every later
		// one authenticated by a signature.
		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		WC_Payments::set_database_cache( new WCPay\Database_Cache() );

		parent::tear_down();
	}

	/**
	 * Puts this store on the signed path, as the platform's account payload does.
	 *
	 * A signature only authenticates a request for a store the platform says WooPay still
	 * signs for, so every test of the signed path has to say which kind of store this is.
	 *
	 * @param bool $forced Whether the platform has this store on the signed path.
	 */
	private function set_forced_signed_requests( bool $forced = true ): void {
		$cache = $this->createMock( WCPay\Database_Cache::class );
		$cache->method( 'get' )->willReturn( [ 'platform_woopay_force_signed_requests' => $forced ] );

		WC_Payments::set_database_cache( $cache );
	}

	public function test_a_sealed_shopper_ip_is_recorded_on_the_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_customer_ip_address( '192.0.91.172' );

		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header( [ 'customer_ip' => '203.0.113.10' ] );

		WooPay_Session::set_woopay_order_customer_ip( $order );

		// The address WooPay saw the shopper arrive from, rather than the WordPress.com
		// address this request left from.
		$this->assertSame( '203.0.113.10', $order->get_customer_ip_address() );
	}

	public function test_an_ipv6_shopper_address_is_recorded_on_the_order() {
		$order = WC_Helper_Order::create_order();

		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header( [ 'customer_ip' => '2001:db8::8a2e:370:7334' ] );

		WooPay_Session::set_woopay_order_customer_ip( $order );

		$this->assertSame( '2001:db8::8a2e:370:7334', $order->get_customer_ip_address() );
	}

	public function test_an_order_keeps_its_own_address_when_the_vouch_seals_none() {
		$order = WC_Helper_Order::create_order();
		$order->set_customer_ip_address( '192.0.91.172' );

		// A vouch with no address in it, as on a WooPay release that seals none yet.
		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header();

		WooPay_Session::set_woopay_order_customer_ip( $order );

		$this->assertSame( '192.0.91.172', $order->get_customer_ip_address() );
	}

	public function test_an_order_keeps_its_own_address_when_the_sealed_one_does_not_parse() {
		$order = WC_Helper_Order::create_order();
		$order->set_customer_ip_address( '192.0.91.172' );

		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header( [ 'customer_ip' => 'not-an-ip-address' ] );

		WooPay_Session::set_woopay_order_customer_ip( $order );

		$this->assertSame( '192.0.91.172', $order->get_customer_ip_address() );
	}

	/**
	 * The vouch is what turns card-testing protection off and what puts an address on the
	 * order, so holding a copy of one cannot be enough -- it has to belong to the request
	 * presenting it. WooPay binds each envelope to the Cart-Token it travels with.
	 */
	public function test_a_vouch_sealed_for_another_cart_is_refused() {
		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header();

		// A captured envelope, presented by a caller carrying their own cart.
		$_SERVER['HTTP_CART_TOKEN'] = 'someone.elses.cart';

		$this->assertNull( WooPay_Session::get_woopay_vouch() );
		$this->assertFalse( WooPay_Session::is_request_vouched_by_woopay() );
	}

	public function test_a_vouch_presented_without_a_cart_is_refused() {
		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header();

		unset( $_SERVER['HTTP_CART_TOKEN'] );

		$this->assertNull( WooPay_Session::get_woopay_vouch() );
	}

	public function test_a_vouch_naming_no_cart_is_refused() {
		// What a WooPay release that predates the binding would send. Accepting it would
		// leave the envelope exactly as transferable as before.
		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header( [ 'cart_token' => null ] );

		$this->assertNull( WooPay_Session::get_woopay_vouch() );
	}

	/**
	 * The one the hash comparison cannot catch on its own: an envelope sealed with an empty
	 * token names `hash( 'sha256', '' )`, which is exactly what a request carrying no
	 * Cart-Token hashes to -- so it would be accepted from any caller that sends none, which
	 * is what an ordinary checkout on this store looks like.
	 */
	public function test_a_vouch_bound_to_no_cart_at_all_is_refused() {
		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header( [ 'cart_token' => hash( 'sha256', '' ) ] );

		unset( $_SERVER['HTTP_CART_TOKEN'] );

		$this->assertNull( WooPay_Session::get_woopay_vouch() );
		$this->assertFalse( WooPay_Session::is_request_vouched_by_woopay() );
	}

	public function test_a_vouch_naming_an_empty_cart_is_refused() {
		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header( [ 'cart_token' => '' ] );

		$this->assertNull( WooPay_Session::get_woopay_vouch() );
	}

	public function test_a_vouch_sealed_for_this_cart_is_accepted() {
		// The control: the binding must not close on the request it was made for, or every
		// WooPay checkout runs the fraud check.
		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header();

		$this->assertTrue( WooPay_Session::is_request_vouched_by_woopay() );
	}

	public function test_an_unsealed_address_cannot_be_stamped_on_an_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_customer_ip_address( '192.0.91.172' );

		// Everything a visitor can arrange for themselves: the User-Agent, a Cart-Token, and
		// a header naming whatever address they like. None of it may reach the order — the
		// address counts only when it comes out of the sealed envelope.
		$_SERVER['HTTP_USER_AGENT']           = 'WooPay';
		$_SERVER['HTTP_X_WOOPAY_CUSTOMER_IP'] = '203.0.113.10';
		$_SERVER['HTTP_CART_TOKEN']           = WooPay_Store_Api_Token::init()->get_cart_token();

		try {
			WooPay_Session::set_woopay_order_customer_ip( $order );

			$this->assertSame( '192.0.91.172', $order->get_customer_ip_address() );
		} finally {
			unset( $_SERVER['HTTP_X_WOOPAY_CUSTOMER_IP'] );
		}
	}

	/**
	 * Seals a vouch envelope the way WooPay does, and encodes it for the header.
	 *
	 * @param array $extra Payload fields to seal alongside the timestamp.
	 *
	 * @return string The header value.
	 */
	private function build_vouch_header( array $extra = [] ): string {
		// Bound to the cart the request carries, as WooPay seals it.
		$_SERVER['HTTP_CART_TOKEN'] = 'the.cart.token';

		$key        = WooPay_Utilities::derive_key_for( WooPay_Utilities::VOUCH_KEY_PURPOSE );
		$iv         = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$plaintext  = wp_json_encode(
			array_merge(
				[
					'timestamp'  => time(),
					'cart_token' => hash( 'sha256', $_SERVER['HTTP_CART_TOKEN'] ),
				],
				$extra
			)
		);
		$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		$envelope = array_map(
			'base64_encode',
			[
				'data' => $ciphertext,
				'iv'   => $iv,
				'hash' => hash_hmac( 'sha256', $iv . $ciphertext, $key ),
			]
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( wp_json_encode( $envelope ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_user_id_from_cart_token_with_guest_user() {
		define( 'REST_REQUEST', true );

		$woopay_store_api_token = WooPay_Store_Api_Token::init();
		$guest_cart_token       = $woopay_store_api_token->get_cart_token();

		$_SERVER['HTTP_CART_TOKEN'] = $guest_cart_token;

		$this->setup_session( 0 );
		$this->setup_adapted_extensions();

		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
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
		$_SERVER['HTTP_NONCE']      = $this->create_woopay_nonce( $user->ID );

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

		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
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
		$_SERVER['HTTP_NONCE']                           = $this->create_woopay_nonce( $verified_user->ID );

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
		$_SERVER['HTTP_NONCE']                           = $this->create_woopay_nonce( $verified_user->ID );

		$this->setup_adapted_extensions();

		$order = \WC_Helper_Order::create_order( $verified_user->ID );
		$order->set_billing_email( $verified_user->user_email );
		$order->save();
		$this->setup_session( $verified_user->ID );

		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		WooPay_Session::authenticate_woopay_rest_request( null, [], $request );
		WooPay_Session::woopay_order_payment_status_changed( $order->get_id() );
		WooPay_Session::end_woopay_rest_request( null, [], $request );

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

	public function test_authenticate_rest_request_passes_through_a_request_not_from_woopay() {
		$_SERVER['HTTP_USER_AGENT'] = 'NotWooPay';

		$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );

		$this->assertNull( WooPay_Session::authenticate_woopay_rest_request( null, [], $request ) );
		$this->assertFalse( WooPay_Session::is_inside_woopay_rest_route() );
	}

	public function test_authenticate_rest_request_passes_through_a_route_woopay_does_not_use() {
		$request = new WP_REST_Request( 'GET', '/wc/v3/orders' );

		$this->assertNull( WooPay_Session::authenticate_woopay_rest_request( null, [], $request ) );
		$this->assertFalse( WooPay_Session::is_inside_woopay_rest_route() );
	}

	public function test_authenticate_rest_request_passes_through_an_existing_error() {
		$error = new WP_Error( 'other', 'Other error' );

		$this->assertSame(
			$error,
			WooPay_Session::authenticate_woopay_rest_request( $error, [], new WP_REST_Request( 'GET', '/wc/store/v1/cart' ) )
		);
		$this->assertFalse( WooPay_Session::is_inside_woopay_rest_route() );
	}

	public function test_authenticate_rest_request_sets_the_user_of_an_accepted_request() {
		$woopay_user = self::factory()->user->create_and_get();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();
		$_SERVER['HTTP_NONCE']      = $this->create_woopay_nonce( $woopay_user->ID );

		$this->setup_session( $woopay_user->ID );
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );

		$this->assertNull( WooPay_Session::authenticate_woopay_rest_request( null, [], $request ) );
		$this->assertSame( $woopay_user->ID, get_current_user_id() );
		$this->assertTrue( WooPay_Session::is_inside_woopay_rest_route() );

		WooPay_Session::end_woopay_rest_request( null, [], $request );
		$this->assertFalse( WooPay_Session::is_inside_woopay_rest_route() );
	}

	public function test_authenticate_rest_request_accepts_the_woopay_session_route() {
		$this->authenticate_woopay_request_headers();

		$request = new WP_REST_Request( 'POST', '/payments/woopay/session' );

		$this->assertNull( WooPay_Session::authenticate_woopay_rest_request( null, [], $request ) );
		$this->assertTrue( WooPay_Session::is_inside_woopay_rest_route() );

		WooPay_Session::end_woopay_rest_request( null, [], $request );
	}

	public function test_nested_rest_requests_clear_the_woopay_state_in_order() {
		$this->authenticate_woopay_request_headers();

		$outer = new WP_REST_Request( 'POST', '/payments/woopay/session' );
		$inner = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );

		WooPay_Session::authenticate_woopay_rest_request( null, [], $outer );
		WooPay_Session::authenticate_woopay_rest_request( null, [], $inner );

		WooPay_Session::end_woopay_rest_request( null, [], $inner );
		$this->assertTrue( WooPay_Session::is_inside_woopay_rest_route(), 'The outer request is still being dispatched.' );

		// A request that was never accepted must not end the outer one.
		WooPay_Session::end_woopay_rest_request( null, [], new WP_REST_Request( 'GET', '/wc/store/v1/cart' ) );
		$this->assertTrue( WooPay_Session::is_inside_woopay_rest_route() );

		WooPay_Session::end_woopay_rest_request( null, [], $outer );
		$this->assertFalse( WooPay_Session::is_inside_woopay_rest_route() );
	}

	/**
	 * Gives the current request the headers of an authenticated WooPay request.
	 */
	private function authenticate_woopay_request_headers() {
		$woopay_user = self::factory()->user->create_and_get();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();
		$_SERVER['HTTP_NONCE']      = $this->create_woopay_nonce( $woopay_user->ID );

		$this->setup_session( $woopay_user->ID );
	}

	public function test_is_valid_request_with_cart_token_is_true_for_a_valid_cart_token() {
		$woopay_store_api_token     = WooPay_Store_Api_Token::init();
		$_SERVER['HTTP_CART_TOKEN'] = $woopay_store_api_token->get_cart_token();

		$this->assertTrue( WooPay_Session::is_valid_request_with_cart_token() );
	}

	public function test_session_payload_carries_the_store_version() {
		// WooPay decides whether to sign by comparing this against its own minimum, and
		// treats a payload without it as too old. Dropping it would leave every store on
		// signed requests with nothing failing to say so.
		$request = WooPay_Session::get_init_session_request();

		$this->assertArrayHasKey( 'wcpay_version', $request );
		$this->assertSame( WCPAY_VERSION_NUMBER, $request['wcpay_version'] );
	}

	public function test_is_valid_request_with_cart_token_is_false_when_cart_token_is_invalid() {
		$_SERVER['HTTP_CART_TOKEN'] = 'not-a-valid-cart-token';

		$this->assertFalse( WooPay_Session::is_valid_request_with_cart_token() );
	}

	public function test_verified_email_is_rejected_under_cart_token_auth_without_store_minted_nonce() {
		$verified_user = self::factory()->user->create_and_get();

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

		$woopay_store_api_token = WooPay_Store_Api_Token::init();

		$_SERVER['HTTP_CART_TOKEN']                      = $woopay_store_api_token->get_cart_token();
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;
		$_SERVER['HTTP_NONCE']                           = $this->create_woopay_nonce( $verified_user->ID );

		$this->setup_session( 0, $verified_user->user_email );
		$this->setup_adapted_extensions();

		$this->assertEquals( $verified_user->ID, WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_a_signed_request_vouches_for_the_email_it_names() {
		$this->set_forced_signed_requests();
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		// A signed request is WooPay by definition, so the email needs no envelope. This is
		// the pre-11.2.0 behaviour, kept for stores WooPay is told to keep signing for.
		$this->assertTrue( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );

		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );
	}

	public function test_a_signed_request_resolves_the_session_customer_without_a_nonce() {
		$this->set_forced_signed_requests();
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		$shopper = self::factory()->user->create_and_get();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();

		$this->setup_session( $shopper->ID );

		// No HTTP_NONCE: the signature is what pairs the request with the account, the way
		// it did before the store started asking for a store-minted nonce.
		$this->assertEquals( $shopper->ID, WooPay_Session::get_user_id_from_cart_token() );

		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );
	}

	public function test_a_signed_request_resolves_the_verified_email_without_a_nonce() {
		$this->set_forced_signed_requests();
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		$verified_user = self::factory()->user->create_and_get();

		$_SERVER['HTTP_CART_TOKEN']                      = WooPay_Store_Api_Token::init()->get_cart_token();
		$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] = $verified_user->user_email;

		$this->setup_session( 0, $verified_user->user_email );
		$this->setup_adapted_extensions();

		// No HTTP_NONCE: the signature authenticates the verified email header, as it did
		// before the store started asking for email_verified_session_nonce.
		$this->assertEquals( $verified_user->ID, WooPay_Session::get_user_id_from_cart_token() );

		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );
	}

	public function test_email_is_not_attested_without_an_envelope() {
		$woopay_request = $this->attested_request();

		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com', $woopay_request ) );
	}

	public function test_email_is_attested_by_a_fresh_envelope_for_that_email() {
		$woopay_request = $this->attested_request( $this->build_envelope( 'shopper@example.com' ) );

		$this->assertTrue( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com', $woopay_request ) );
	}

	public function test_envelope_does_not_attest_to_a_different_email() {
		// The envelope is valid, but names someone else, so it vouches only for that address.
		$woopay_request = $this->attested_request( $this->build_envelope( 'other@example.com' ) );

		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com', $woopay_request ) );
	}

	public function test_stale_envelope_does_not_attest() {
		$woopay_request = $this->attested_request( $this->build_envelope( 'shopper@example.com', time() - 3600 ) );

		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com', $woopay_request ) );
	}

	/**
	 * @dataProvider provider_malformed_envelopes
	 *
	 * @param mixed $envelope The malformed value arriving as the attestation param.
	 */
	public function test_malformed_envelope_does_not_attest( $envelope ) {
		$woopay_request = $this->attested_request( $envelope );

		// decrypt_signed_data() indexes data/iv/hash directly, so these have to be refused
		// here rather than reaching it and warning on an undefined index.
		$this->assertNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );
	}

	public function provider_malformed_envelopes() {
		return [
			'not an array'      => [ 'just-a-string' ],
			'empty array'       => [ [] ],
			'missing hash'      => [
				[
					'data' => 'x',
					'iv'   => 'y',
				],
			],
			'missing iv'        => [
				[
					'data' => 'x',
					'hash' => 'z',
				],
			],
			'missing data'      => [
				[
					'iv'   => 'y',
					'hash' => 'z',
				],
			],
			'non-string member' => [
				[
					'data' => [ 'nested' ],
					'iv'   => 'y',
					'hash' => 'z',
				],
			],
			'null member'       => [
				[
					'data' => null,
					'iv'   => 'y',
					'hash' => 'z',
				],
			],
			'empty strings'     => [
				[
					'data' => '',
					'iv'   => '',
					'hash' => '',
				],
			],
		];
	}

	public function test_guest_envelope_attests_without_naming_an_email() {
		$woopay_request = $this->attested_request( $this->build_envelope() );

		// A guest shopper has no account to name. The attestation still stands — it is
		// what admits the request — but there is no email for it to vouch for.
		$this->assertNotNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );
		$this->assertNull( WooPay_Session::get_woopay_attested_account_email( $woopay_request ) );
		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com', $woopay_request ) );
	}

	public function test_absent_envelope_does_not_attest() {
		$woopay_request = $this->attested_request();

		// Carrying no envelope at all is the ordinary case, not a malformed one.
		$this->assertNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );
	}

	public function test_envelope_is_refused_on_a_second_request() {
		$woopay_request = $this->attested_request( $this->build_envelope( 'shopper@example.com' ) );

		$this->assertNotNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );

		// A second request replaying the same envelope is the attack: it is still fresh,
		// still sealed correctly, and would otherwise mint a nonce for that shopper.
		$this->reset_resolved_attestations();

		$this->assertNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );
		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com', $woopay_request ) );
	}

	public function test_envelope_serves_every_caller_within_one_request() {
		$woopay_request = $this->attested_request( $this->build_envelope( 'shopper@example.com' ) );

		// The permission check, the email lookup and the nonce gate each resolve the
		// envelope independently. Spending it on the first of them would break checkout.
		$this->assertNotNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );
		$this->assertSame( 'shopper@example.com', WooPay_Session::get_woopay_attested_account_email( $woopay_request ) );
		$this->assertTrue( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com', $woopay_request ) );
		$this->assertNotNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );
	}

	public function test_envelope_is_refused_on_a_second_request_under_a_persistent_object_cache() {
		// Stores at this size normally run Redis or Memcached, and transients behave
		// differently there: set_transient() becomes wp_cache_set(), which stores
		// unconditionally and reports success either way. The claim has to be made with an
		// operation that refuses a key already present, or the guard is only as good as the
		// read in front of it — and two requests arriving together both pass that read.
		//
		// Honest about what this does and does not prove: the race is not reproducible in a
		// unit test, and this passes against the read-then-write version too, since a
		// sequential replay is caught by the read. It is here so the object cache path is
		// exercised at all — nothing else covers it — and so a change that leaves the claim
		// working only on the database path fails.
		$was_using_ext_object_cache = wp_using_ext_object_cache( true );

		try {
			$woopay_request = $this->attested_request( $this->build_envelope( 'shopper@example.com' ) );

			$this->assertNotNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );

			$this->reset_resolved_attestations();

			$this->assertNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );
		} finally {
			wp_using_ext_object_cache( $was_using_ext_object_cache );
		}
	}

	public function test_the_recorded_claim_does_not_carry_a_timestamp() {
		$envelope = $this->build_envelope( 'shopper@example.com' );

		$woopay_request = $this->attested_request( $envelope );

		$this->assertNotNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );

		// On the database path a duplicate claim is refused by MySQL reporting zero affected
		// rows for an INSERT that changes nothing, which only holds while every request
		// writes the same value. A timestamp here would make two requests either side of a
		// second boundary look like a change, and both would be told they spent the envelope.
		$this->assertEquals( 1, get_transient( $this->attestation_claim_key( $envelope['hash'] ) ) );
	}

	public function test_spending_one_envelope_does_not_affect_another() {
		$woopay_request = $this->attested_request( $this->build_envelope( 'shopper@example.com' ) );
		$this->assertNotNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );

		// A shopper's envelope being spent must not lock anyone else out.
		$this->reset_resolved_attestations();
		$woopay_request = $this->attested_request( $this->build_envelope( 'other@example.com' ) );

		$this->assertNotNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );
	}

	public function test_stale_envelope_is_not_spent_before_it_is_rejected() {
		// A stale envelope is refused on freshness, so it never reaches the claim — the
		// rejection reason stays 'stale' rather than turning into 'already used'.
		$envelope       = $this->build_envelope( 'shopper@example.com', time() - 3600 );
		$woopay_request = $this->attested_request( $envelope );

		$this->assertNull( WooPay_Session::get_woopay_attestation( $woopay_request ) );
		$this->assertFalse( $this->attestation_was_claimed( $envelope['hash'] ) );
	}

	public function test_attested_account_email_outranks_a_caller_supplied_email() {
		$woopay_request = $this->attested_request( $this->build_envelope( 'shopper@example.com' ) );
		$woopay_request->set_query_params( [ 'email' => 'other@example.com' ] );

		$this->assertSame( 'shopper@example.com', WooPay_Session::get_user_email( wp_get_current_user(), $woopay_request ) );
	}

	public function test_authenticated_cart_token_is_accepted_with_a_store_minted_nonce() {
		$shopper = self::factory()->user->create_and_get();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();
		$_SERVER['HTTP_NONCE']      = $this->create_woopay_nonce( $shopper->ID );

		$this->setup_session( $shopper->ID );

		$this->assertEquals( $shopper->ID, WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_authenticated_cart_token_is_rejected_without_a_nonce() {
		$shopper = self::factory()->user->create_and_get();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();

		$this->setup_session( $shopper->ID );

		// A Cart-Token on its own no longer resolves the account behind the session.
		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_authenticated_cart_token_is_rejected_with_a_nonce_for_another_user() {
		$shopper = self::factory()->user->create_and_get();
		$other   = self::factory()->user->create_and_get();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();
		$_SERVER['HTTP_NONCE']      = $this->create_woopay_nonce( $other->ID );

		$this->setup_session( $shopper->ID );

		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_unauthenticated_request_is_rejected_as_carrying_nothing() {
		$this->expect_woopay_request_to_die( 'WooPay request is not signed correctly.' );
	}

	public function test_signed_request_without_a_cart_token_is_let_through() {
		$this->set_forced_signed_requests();
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		// No Cart-Token, which is refused above when unsigned. Signed, the request is not
		// refused, and with no Cart-Token to resolve an account from it keeps the user it had.
		$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
		$this->assertNull( WooPay_Session::authenticate_woopay_rest_request( null, [], $request ) );
		$this->assertSame( 0, get_current_user_id() );
		WooPay_Session::end_woopay_rest_request( null, [], $request );

		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );
	}

	public function test_invalid_cart_token_is_rejected_as_invalid() {
		$_SERVER['HTTP_CART_TOKEN'] = 'not.a.valid.token';

		$this->expect_woopay_request_to_die( 'The Cart-Token on this WooPay request is invalid or expired.' );
	}

	/**
	 * Asserts the current request is refused by authenticate_woopay_rest_request().
	 *
	 * @param string $expected_message The message the request should be refused with.
	 */
	private function expect_woopay_request_to_die( string $expected_message ) {
		$result = WooPay_Session::authenticate_woopay_rest_request( null, [], new WP_REST_Request( 'GET', '/wc/store/v1/cart' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $expected_message, $result->get_error_message() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
		$this->assertFalse( WooPay_Session::is_inside_woopay_rest_route() );
	}

	public function test_nonce_is_withheld_from_the_rest_route_when_the_email_is_not_attested() {
		$woopay_request = $this->attested_request();

		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		// Names the shopper in a plain parameter, which anyone can do.
		$woopay_request->set_query_params( [ 'email' => $shopper->user_email ] );

		$request = WooPay_Session::get_init_session_request( null, null, null, $woopay_request );

		// The REST route hands this array back in plaintext, so an unattested caller must
		// not be able to name an email and be given a nonce minted for that account.
		$this->assertArrayNotHasKey( 'email_verified_session_nonce', $request );
	}

	public function test_nonce_is_minted_for_the_rest_route_when_the_email_is_attested() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		$woopay_request = $this->attested_request( $this->build_envelope( $shopper->user_email ) );

		$request = WooPay_Session::get_init_session_request( null, null, null, $woopay_request );

		// This is the flow the gate exists to permit: WooPay vouched for the email, so the
		// shopper gets the nonce that unlocks their balance.
		$this->assertArrayHasKey( 'email_verified_session_nonce', $request );
	}

	public function test_nonce_is_minted_without_an_attestation_when_it_is_not_disclosed() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		$_POST['email'] = $shopper->user_email;

		// No WP_REST_Request means the frontend or redirect caller, which encrypts the
		// payload or POSTs it server-side. Nothing is disclosed, so nothing is withheld —
		// gating these too would break the paths that never leaked the nonce.
		$request = WooPay_Session::get_init_session_request();

		$this->assertArrayHasKey( 'email_verified_session_nonce', $request );

		unset( $_POST['email'] );
	}

	public function test_rest_route_withholds_extension_data_for_an_email_it_was_only_told_about() {
		$woopay_request = $this->attested_request();

		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		// Names the shopper in a plain parameter, which anyone can do.
		$woopay_request->set_query_params( [ 'email' => $shopper->user_email ] );

		$request = WooPay_Session::get_init_session_request( null, null, null, $woopay_request );

		// The nonce was already withheld here, but the balance it guards was not: this route
		// returns adapted extension data in plaintext, so naming an address must not be enough
		// to read that account's Points & Rewards and Gift Card data.
		$this->assertArrayNotHasKey( 'adapted_extensions', $request );

		// The email itself still travels. It is how WooPay recognises a returning shopper and
		// decides to ask for an OTP, so withholding it signs nobody in — a narrower question
		// than whose balances to hand back, and answered separately.
		$this->assertSame( $shopper->user_email, $request['email'] );
	}

	public function test_a_guest_envelope_does_not_let_the_caller_name_someone_elses_email() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		// A guest shopper has no account to name, so their envelope carries no email — and it
		// still authorizes the route. That combination is the one worth pinning: the envelope
		// gets the caller in, and the parameter must not then decide whose data comes back.
		$woopay_request = $this->attested_request( $this->build_envelope() );
		$_POST['email'] = $shopper->user_email;

		$request = WooPay_Session::get_init_session_request( null, null, null, $woopay_request );

		$this->assertArrayNotHasKey( 'adapted_extensions', $request );
		$this->assertArrayNotHasKey( 'email_verified_session_nonce', $request );

		// Still named to WooPay, so the shopper can still be asked for an OTP.
		$this->assertSame( $shopper->user_email, $request['email'] );

		unset( $_POST['email'] );
	}

	public function test_rest_route_returns_extension_data_for_an_attested_email() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		$woopay_request = $this->attested_request( $this->build_envelope( $shopper->user_email ) );

		$request = WooPay_Session::get_init_session_request( null, null, null, $woopay_request );

		// The control for the two above: withholding is the point, but not at the cost of the
		// flow this route exists to serve.
		$this->assertSame( $shopper->user_email, $request['email'] );
		$this->assertNotEmpty( $request['adapted_extensions'] );
	}

	public function test_rest_route_returns_extension_data_for_the_email_a_signed_request_names() {
		$this->set_forced_signed_requests();
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		// What WooPay sends a store the platform keeps on the signed path: a signed GET
		// naming the shopper in the query string, with no envelope.
		$woopay_request = $this->attested_request();
		$woopay_request->set_query_params( [ 'email' => $shopper->user_email ] );

		$request = WooPay_Session::get_init_session_request( null, null, null, $woopay_request );

		// The signature is WooPay naming the address, as the envelope is on the unsigned
		// path, so the shopper's balance and the nonce that unlocks it both come back.
		$this->assertSame( $shopper->user_email, $request['email'] );
		$this->assertArrayHasKey( 'adapted_extensions', $request );
		$this->assertNotEmpty( $request['adapted_extensions'] );
		$this->assertArrayHasKey( 'email_verified_session_nonce', $request );
	}

	public function test_rest_route_withholds_extension_data_for_a_signature_off_the_signed_path() {
		$this->set_forced_signed_requests( false );
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		$woopay_request = $this->attested_request();
		$woopay_request->set_query_params( [ 'email' => $shopper->user_email ] );

		$request = WooPay_Session::get_init_session_request( null, null, null, $woopay_request );

		// A store the platform moved off the signed path refuses a signature however well
		// formed, so it names nobody, and the plain parameter is all that is left.
		$this->assertArrayNotHasKey( 'adapted_extensions', $request );
		$this->assertArrayNotHasKey( 'email_verified_session_nonce', $request );
	}

	public function test_ajax_caller_ignores_the_email_in_the_query_string() {
		// A logged-out shopper who typed their email at checkout.
		wp_set_current_user( 0 );
		WC()->customer->set_billing_email( 'shopper@example.com' );
		WC()->customer->save();

		// AJAX callers pass no request, so this must be ignored.
		$_GET['email'] = 'other@example.com';

		$request = WooPay_Session::get_init_session_request();

		$this->assertSame( 'shopper@example.com', $request['email'] );
		$this->assertSame( 'shopper@example.com', WC()->customer->get_billing_email() );
	}

	public function test_ajax_get_woopay_session_refuses_a_get_request() {
		wp_set_current_user( 0 );
		WC()->customer->set_billing_email( 'shopper@example.com' );
		WC()->customer->save();

		// A GET with a valid nonce.
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_REQUEST['_ajax_nonce']   = wp_create_nonce( 'woopay_session_nonce' );
		$_GET['email']             = 'other@example.com';

		$response = $this->call_ajax_handler( 'ajax_get_woopay_session' );

		$this->assertSame(
			[
				'success' => false,
				'data'    => 'You aren’t authorized to do that.',
			],
			$response
		);
		$this->assertSame( 'shopper@example.com', WC()->customer->get_billing_email() );
	}

	public function test_ajax_get_woopay_session_lets_a_first_party_post_past_the_guard() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = home_url();
		$_REQUEST['_ajax_nonce']   = wp_create_nonce( 'woopay_session_nonce' );
		Jetpack_Options::delete_option( 'id' );

		$response = $this->call_ajax_handler( 'ajax_get_woopay_session' );

		// Reaching the blog ID check means the guard and the nonce passed.
		$this->assertSame(
			[
				'success' => false,
				'data'    => 'Could not determine the blog ID.',
			],
			$response
		);
	}

	public function test_ajax_init_woopay_refuses_a_cross_origin_post() {
		wp_set_current_user( 0 );
		WC()->customer->set_billing_email( 'shopper@example.com' );
		WC()->customer->save();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = 'https://elsewhere.example';
		$_REQUEST['_ajax_nonce']   = wp_create_nonce( 'wcpay_init_woopay_nonce' );
		$_POST['email']            = 'other@example.com';

		$response = $this->call_ajax_handler( 'ajax_init_woopay' );

		$this->assertSame(
			[
				'success' => false,
				'data'    => 'You aren’t authorized to do that.',
			],
			$response
		);
		$this->assertSame( 'shopper@example.com', WC()->customer->get_billing_email() );
	}

	public function test_pay_for_order_session_is_refused_with_the_wrong_key() {
		$order = WC_Helper_Order::create_order( 0 );

		$this->assert_pay_for_order_session_refused( $order->get_id(), 'wc_order_not_the_real_key' );
	}

	public function test_pay_for_order_session_is_refused_without_a_key() {
		$order = WC_Helper_Order::create_order( 0 );

		$this->assert_pay_for_order_session_refused( $order->get_id(), null );
	}

	public function test_pay_for_order_session_is_refused_for_another_customers_order() {
		$owner   = self::factory()->user->create( [ 'role' => 'customer' ] );
		$shopper = self::factory()->user->create( [ 'role' => 'customer' ] );
		$order   = WC_Helper_Order::create_order( $owner );
		wp_set_current_user( $shopper );

		// The key alone does not let one customer pay another's order.
		$this->assert_pay_for_order_session_refused( $order->get_id(), $order->get_order_key() );
	}

	public function test_pay_for_order_session_is_refused_for_an_unknown_order() {
		$this->assert_pay_for_order_session_refused( 999999, 'wc_order_any_key' );
	}

	public function test_pay_for_order_session_opens_for_the_key_holder() {
		$order = WC_Helper_Order::create_order( 0 );

		$this->assert_pay_for_order_session_links_back_to( $order );
	}

	public function test_pay_for_order_session_opens_for_the_customer_who_owns_the_order() {
		$owner = self::factory()->user->create( [ 'role' => 'customer' ] );
		$order = WC_Helper_Order::create_order( $owner );
		wp_set_current_user( $owner );

		$this->assert_pay_for_order_session_links_back_to( $order );
	}

	public function test_get_init_session_request_returns_an_error_for_an_unauthorized_pay_for_order() {
		$order = WC_Helper_Order::create_order( 0 );
		$this->mock_customer_service->expects( $this->never() )->method( 'get_customer_id_by_user_id' );

		// A caller outside AJAX keeps control and gets nothing printed.
		$this->expectOutputString( '' );

		$result = WooPay_Session::get_init_session_request( $order->get_id(), 'wc_order_not_the_real_key' );

		$this->assertWPError( $result );
		$this->assertSame( 'woopay_pay_for_order_not_authorized', $result->get_error_code() );
		$this->assertSame( [ 'status' => 403 ], $result->get_error_data() );
	}

	public function test_ajax_get_woopay_session_refuses_a_pay_for_order_with_the_wrong_key() {
		$order = WC_Helper_Order::create_order( 0 );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = home_url();
		$_REQUEST['_ajax_nonce']   = wp_create_nonce( 'woopay_session_nonce' );
		$_POST['order_id']         = (string) $order->get_id();
		$_POST['key']              = 'wc_order_not_the_real_key';
		$original_blog_id          = Jetpack_Options::get_option( 'id' );
		Jetpack_Options::update_option( 'id', 1234 );

		try {
			$response = $this->call_ajax_handler( 'ajax_get_woopay_session' );
		} finally {
			false === $original_blog_id ? Jetpack_Options::delete_option( 'id' ) : Jetpack_Options::update_option( 'id', $original_blog_id );
			unset( $_POST['order_id'], $_POST['key'] );
		}

		$this->assertSame(
			[
				'success' => false,
				'data'    => 'You aren’t authorized to do that.',
			],
			$response
		);
	}

	public function test_ajax_set_woopay_phone_number_refuses_a_cross_origin_post() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = 'https://elsewhere.example';
		$_REQUEST['_ajax_nonce']   = wp_create_nonce( WooPay_Session::SAVE_USER_NONCE_ACTION );

		$response = $this->call_ajax_handler( 'ajax_set_woopay_phone_number' );

		$this->assertSame(
			[
				'success' => false,
				'data'    => 'You aren’t authorized to do that.',
			],
			$response
		);
	}

	/**
	 * @dataProvider provider_foreign_origins
	 *
	 * @param string $origin The Origin header, with `%s` standing for the store's host.
	 */
	public function test_is_disallowed_session_request_blocks_a_post_from_a_foreign_origin( string $origin ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = sprintf( $origin, wp_parse_url( home_url(), PHP_URL_HOST ) );

		$this->assertTrue( $this->is_disallowed_session_request() );
	}

	public function provider_foreign_origins() {
		return [
			'a subdomain of the store'       => [ 'https://blog.%s' ],
			'another port on the store host' => [ 'http://%s:9999' ],
			'the store host over https'      => [ 'https://%s' ],
			// Sent by sandboxed iframes and some redirects.
			'an opaque origin'               => [ 'null' ],
		];
	}

	public function test_is_disallowed_session_request_allows_a_post_without_an_origin() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		unset( $_SERVER['HTTP_ORIGIN'] );

		// Browsers always send an Origin on POST.
		$this->assertFalse( $this->is_disallowed_session_request() );
	}

	public function test_is_disallowed_session_request_compares_hosts_case_insensitively() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = 'http://' . strtoupper( wp_parse_url( home_url(), PHP_URL_HOST ) );

		$this->assertFalse( $this->is_disallowed_session_request() );
	}

	public function test_is_disallowed_session_request_allows_the_store_on_its_configured_port() {
		add_filter( 'pre_option_home', [ $this, 'serve_home_on_port_8052' ] );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = 'http://store.example:8052';

		$this->assertFalse( $this->is_disallowed_session_request() );
	}

	public function test_is_disallowed_session_request_allows_an_origin_added_by_the_filter() {
		add_filter( 'wcpay_woopay_session_allowed_origins', [ $this, 'allow_shop_example_origin' ] );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = 'https://shop.example';

		$this->assertFalse( $this->is_disallowed_session_request() );
	}

	public function test_is_disallowed_session_request_blocks_another_port_of_an_origin_added_by_the_filter() {
		add_filter( 'wcpay_woopay_session_allowed_origins', [ $this, 'allow_shop_example_origin' ] );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = 'https://shop.example:8443';

		$this->assertTrue( $this->is_disallowed_session_request() );
	}

	public function test_is_disallowed_session_request_blocks_when_the_filter_returns_non_string_origins() {
		add_filter(
			'wcpay_woopay_session_allowed_origins',
			function () {
				return [ null, [ 'https://shop.example' ] ];
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = 'https://shop.example';

		$this->assertTrue( $this->is_disallowed_session_request() );
	}

	public function test_is_disallowed_session_request_allows_a_same_origin_fetch_from_a_host_home_url_does_not_name() {
		// Like a language subdomain, which home_url() doesn't name.
		$_SERVER['REQUEST_METHOD']      = 'POST';
		$_SERVER['HTTP_ORIGIN']         = 'https://de.shop.example';
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';

		$this->assertFalse( $this->is_disallowed_session_request() );
	}

	public function test_is_disallowed_session_request_blocks_a_same_site_fetch() {
		$_SERVER['REQUEST_METHOD']      = 'POST';
		$_SERVER['HTTP_ORIGIN']         = sprintf( 'https://blog.%s', wp_parse_url( home_url(), PHP_URL_HOST ) );
		$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-site';

		$this->assertTrue( $this->is_disallowed_session_request() );
	}

	public function test_is_disallowed_session_request_logs_the_rejected_origin() {
		WC_Payments::mode()->dev();

		$messages = [];
		$capture  = function ( $message ) use ( &$messages ) {
			$messages[] = $message;

			return $message;
		};
		add_filter( 'woocommerce_logger_log_message', $capture );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = 'https://shop.example';

		$this->is_disallowed_session_request();

		$this->assertContains(
			'WooPay session request rejected: origin https://shop.example:443 is not allowed. See the wcpay_woopay_session_allowed_origins filter.',
			$messages
		);
	}

	/**
	 * Stands in for a store whose home URL carries a non-default port.
	 *
	 * @return string
	 */
	public function serve_home_on_port_8052() {
		return 'http://store.example:8052';
	}

	/**
	 * Stands in for a store that serves its checkout on a second domain.
	 *
	 * @param string[] $origins The allowed origins.
	 *
	 * @return string[]
	 */
	public function allow_shop_example_origin( $origins ) {
		$origins[] = 'https://shop.example';

		return $origins;
	}

	public function test_save_user_choice_is_saved_with_the_session_it_was_made_in() {
		wp_set_current_user( self::factory()->user->create() );

		$response = $this->post_save_user_choice( wp_create_nonce( WooPay_Session::SAVE_USER_NONCE_ACTION ) );
		$data     = WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $data['save_user_in_woopay'] );
		$this->assertSame( '+14155550123', $data['woopay_user_phone_field']['full'] );
		$this->assertSame( (string) WC()->session->get_customer_id(), $data['session_customer_id'] );
	}

	public function test_save_user_choice_does_not_start_a_session() {
		// A caller with neither a session cookie nor an account.
		$original_session = WC()->session;
		WC()->session     = new WC_Session_Handler();

		try {
			$response = $this->post_save_user_choice( wp_create_nonce( WooPay_Session::SAVE_USER_NONCE_ACTION ) );

			$this->assertFalse( $response['success'] );
			$this->assertSame( 'Could not find the shopper session.', $response['data'] );
			$this->assertFalse( WC()->session->has_session() );
			$this->assertNull( WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY ) );
		} finally {
			WC()->session = $original_session;
		}
	}

	public function test_save_user_choice_is_refused_with_the_woopay_session_nonce() {
		wp_set_current_user( self::factory()->user->create() );

		$response = $this->post_save_user_choice( wp_create_nonce( 'woopay_session_nonce' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'You aren’t authorized to do that.', $response['data'] );
		$this->assertNull( WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY ) );
	}

	public function test_save_user_nonce_of_a_guest_is_tied_to_their_session() {
		$original_session = WC()->session;
		$original_cookies = $_COOKIE;
		$original_user_id = get_current_user_id();
		$first_session    = new WC_Session_Handler();
		$second_session   = new WC_Session_Handler();
		$disable_cookies  = static function () {
			return false;
		};

		wp_set_current_user( 0 );
		unset( $_COOKIE[ 'wp_woocommerce_session_' . COOKIEHASH ] );
		add_filter( 'woocommerce_set_cookie_enabled', $disable_cookies );

		try {
			// Initialize session identity and nonce handling without registering unrelated lifecycle hooks.
			WC()->session = $first_session;
			$first_session->init_session_cookie();
			$first_session->set_customer_session_cookie( true );
			add_filter( 'nonce_user_logged_out', [ $first_session, 'maybe_update_nonce_user_logged_out' ], 10, 2 );

			$first_nonce = wp_create_nonce( WooPay_Session::SAVE_USER_NONCE_ACTION );
			$response    = $this->post_save_user_choice( $first_nonce );
			$this->assertTrue( $response['success'] );

			remove_filter( 'nonce_user_logged_out', [ $first_session, 'maybe_update_nonce_user_logged_out' ] );
			WC()->session = $second_session;
			$second_session->init_session_cookie();
			$second_session->set_customer_session_cookie( true );
			add_filter( 'nonce_user_logged_out', [ $second_session, 'maybe_update_nonce_user_logged_out' ], 10, 2 );

			$response = $this->post_save_user_choice( $first_nonce );
			$this->assertFalse( $response['success'] );
			$this->assertNull( $second_session->get( WooPay_Session::WOOPAY_SESSION_KEY ) );

			$second_nonce = wp_create_nonce( WooPay_Session::SAVE_USER_NONCE_ACTION );
			$response     = $this->post_save_user_choice( $second_nonce );
			$this->assertTrue( $response['success'] );
		} finally {
			remove_filter( 'woocommerce_set_cookie_enabled', $disable_cookies );
			remove_filter( 'nonce_user_logged_out', [ $first_session, 'maybe_update_nonce_user_logged_out' ] );
			remove_filter( 'nonce_user_logged_out', [ $second_session, 'maybe_update_nonce_user_logged_out' ] );
			WC()->session = $original_session;
			$_COOKIE      = $original_cookies;
			wp_set_current_user( $original_user_id );
		}
	}

	public function test_save_user_choice_follows_the_guest_who_made_it_to_their_account() {
		WC()->session->set( WooPay_Session::WOOPAY_SESSION_KEY, [ 'session_customer_id' => 't_guest' ] );

		WooPay_Session::keep_woopay_user_data_on_login( 't_guest', '123' );

		$this->assertSame( '123', WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY )['session_customer_id'] );
	}

	public function test_save_user_choice_of_another_session_does_not_follow_a_guest_to_their_account() {
		WC()->session->set( WooPay_Session::WOOPAY_SESSION_KEY, [ 'session_customer_id' => 't_another_guest' ] );

		WooPay_Session::keep_woopay_user_data_on_login( 't_guest', '123' );

		$this->assertSame( 't_another_guest', WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY )['session_customer_id'] );
	}

	/**
	 * Sends what the blocks checkout sends when a shopper ticks "save my information".
	 *
	 * @param string $nonce The nonce sent with the request.
	 *
	 * @return array The decoded response.
	 */
	private function post_save_user_choice( string $nonce ): array {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_REQUEST['_ajax_nonce']   = $nonce;
		$_POST                     = [
			'save_user_in_woopay'     => '1',
			'woopay_source_url'       => 'https://example.org/checkout/',
			'woopay_is_blocks'        => '1',
			'woopay_viewport'         => '1280x720',
			'woopay_user_phone_field' => [ 'full' => '+14155550123' ],
		];

		// wp_send_json_*() only goes through wp_die(), which can be intercepted, on AJAX requests.
		$die_handler = function () {
			return function () {
				throw new WPDieException();
			};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler );

		ob_start();
		try {
			WooPay_Session::ajax_set_woopay_phone_number();
		} catch ( WPDieException $e ) {
			unset( $e );
		} finally {
			$response = json_decode( ob_get_clean(), true );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			unset( $_REQUEST['_ajax_nonce'] );
			$_POST = [];
		}

		return $response;
	}

	/**
	 * Puts a guest shopper in front of a store carrying a redeemable balance for them.
	 *
	 * The nonce is only minted for a logged-out shopper who holds an account on the store
	 * and has adapted extension data, so all three have to be true before the attestation
	 * gate is the thing being tested.
	 *
	 * @return WP_User The shopper's store account.
	 */
	private function setup_shopper_with_adapted_extension_balance() {
		$this->setup_adapted_extensions();

		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new WC_Points_Rewards_Integration() );
			}
		);

		update_option( 'wc_points_rewards_redeem_points_ratio', '100:1' );

		// get_users_points() returns the user ID, so any real user has a balance.
		$shopper = self::factory()->user->create_and_get();

		wp_set_current_user( 0 );

		return $shopper;
	}

	/**
	 * Forgets attestations resolved so far, standing in for a fresh request.
	 *
	 * The per-request memo is what lets several callers share one envelope, so replay has
	 * to be asserted across requests rather than within one.
	 */
	private function reset_resolved_attestations() {
		$property = new ReflectionProperty( WooPay_Session::class, 'resolved_attestations' );
		$property->setAccessible( true );
		$property->setValue( null, [] );
	}

	/**
	 * Whether an envelope has been recorded as spent.
	 *
	 * @param string $hash The envelope's `hash` field.
	 *
	 * @return bool True if the envelope was claimed.
	 */
	private function attestation_was_claimed( string $hash ): bool {
		return false !== get_transient( $this->attestation_claim_key( $hash ) );
	}

	/**
	 * The transient key an envelope's claim is recorded under.
	 *
	 * Keyed on the decoded bytes, so respelling a spent envelope buys no second claim.
	 *
	 * @param string $hash The envelope's `hash` field.
	 *
	 * @return string The claim key.
	 */
	private function attestation_claim_key( string $hash ): string {
		return WooPay_Session::ATTESTATION_CLAIM_PREFIX . hash( 'sha256', base64_decode( $hash, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Seals an envelope the way WooPay does, so these tests fail if the two ends drift.
	 *
	 * Mirrors what decrypt_signed_data() expects, which is not the shape
	 * encrypt_and_sign_data() produces — the two directions are separate protocols.
	 *
	 * @param string|null $email     The email to attest to, or null for a guest shopper.
	 * @param int|null $timestamp Envelope timestamp. Defaults to now.
	 * @param string|null $key       Key to seal with. Defaults to the attestation key, which
	 *                               is the only one WooPay seals an attestation with.
	 *
	 * @return array The base64-encoded envelope.
	 */
	private function build_envelope( ?string $email = null, ?int $timestamp = null, ?string $key = null ): array {
		$key = $key ?? \WCPay\WooPay\WooPay_Utilities::derive_key_for( \WCPay\WooPay\WooPay_Utilities::ATTESTATION_KEY_PURPOSE );

		$payload = [ 'timestamp' => $timestamp ?? time() ];

		if ( null !== $email ) {
			$payload['user_email'] = $email;
		}

		$payload = wp_json_encode( $payload );

		$iv         = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$ciphertext = openssl_encrypt( $payload, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return [
			'data' => base64_encode( $ciphertext ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'iv'   => base64_encode( $iv ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'hash' => base64_encode( hash_hmac( 'sha256', $iv . $ciphertext, $key ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		];
	}

	/**
	 * Builds a POST to the session route carrying the envelope in a JSON body, or a bare
	 * POST when there is none.
	 *
	 * @param mixed $envelope The sealed envelope, or null for a bare request.
	 *
	 * @return WP_REST_Request
	 */
	private function attested_request( $envelope = null ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/payments/woopay/session' );

		if ( null !== $envelope ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( [ WooPay_Session::ATTESTATION_PARAM => $envelope ] ) );
		}

		return $request;
	}

	/**
	 * Calls the private guard the AJAX handlers share.
	 */
	private function is_disallowed_session_request(): bool {
		$method = new ReflectionMethod( WooPay_Session::class, 'is_disallowed_session_request' );
		$method->setAccessible( true );

		return $method->invoke( null );
	}

	/**
	 * Runs a WooPay_Session AJAX handler and returns the JSON it sent.
	 *
	 * The die handler throws, so the handler stops at its first response.
	 *
	 * @param string $handler The handler method name.
	 *
	 * @return array|null The decoded response.
	 *
	 * @throws RuntimeException When the handler fails for any reason other than responding.
	 */
	private function call_ajax_handler( string $handler ) {
		$die_handler = function () {
			return function () {
				throw new RuntimeException( 'wp_die' );
			};
		};

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		// Handlers that pass the guard must not call WooPay.
		add_filter( 'pre_http_request', [ $this, 'block_http_request' ] );

		ob_start();

		try {
			call_user_func( [ WooPay_Session::class, $handler ] );
		} catch ( RuntimeException $e ) {
			if ( 'wp_die' !== $e->getMessage() ) {
				throw $e;
			}
		} finally {
			$output = ob_get_clean();

			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'pre_http_request', [ $this, 'block_http_request' ] );
		}

		return json_decode( $output, true );
	}

	/**
	 * Posts a pay-for-order session request to wcpay_init_woopay, as the checkout does.
	 *
	 * @param int         $order_id      The order ID the caller sends.
	 * @param string|null $key           The order key the caller sends, or null to send none.
	 * @param string|null $billing_email The billing email the caller sends.
	 *
	 * @return array The decoded response, and the bodies of the session requests sent to WooPay.
	 */
	private function post_pay_for_order_session( int $order_id, ?string $key, ?string $billing_email = null ): array {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ORIGIN']    = home_url();
		$_REQUEST['_ajax_nonce']   = wp_create_nonce( 'wcpay_init_woopay_nonce' );
		$_POST                     = array_filter(
			[
				'order_id'      => (string) $order_id,
				'key'           => $key,
				'billing_email' => $billing_email,
			]
		);

		// Jetpack only sends the request for a connected site, with a blog token in its
		// key.secret form.
		$original_blog_id    = Jetpack_Options::get_option( 'id' );
		$original_blog_token = Jetpack_Options::get_option( 'blog_token' );
		Jetpack_Options::update_option( 'id', 1234 );
		Jetpack_Options::update_option( 'blog_token', 'blogkey.blogsecret' );

		$sent   = [];
		$record = function ( $preempt, $args, $url ) use ( &$sent ) {
			if ( 0 === strpos( $url, WooPay_Utilities::get_woopay_rest_url( 'init' ) ) ) {
				$sent[] = json_decode( $args['body'], true );
			}

			return $preempt;
		};
		add_filter( 'pre_http_request', $record, 1, 3 );

		// A pay-for-order session preloads the order from the Store API, whose order route
		// WooCommerce only has from 8.5. These tests are not about that preload.
		$stub_order_route = function ( $result, $server, $request ) {
			return 0 === strpos( $request->get_route(), '/wc/store/v1/order/' ) ? new WP_REST_Response( [], 200 ) : $result;
		};
		add_filter( 'rest_pre_dispatch', $stub_order_route, 10, 3 );

		try {
			$response = $this->call_ajax_handler( 'ajax_init_woopay' );
		} finally {
			remove_filter( 'pre_http_request', $record, 1 );
			remove_filter( 'rest_pre_dispatch', $stub_order_route, 10 );
			false === $original_blog_id ? Jetpack_Options::delete_option( 'id' ) : Jetpack_Options::update_option( 'id', $original_blog_id );
			Jetpack_Options::update_option( 'blog_token', $original_blog_token );
			$_POST = [];
		}

		return [ $response, $sent ];
	}

	/**
	 * Asserts a pay-for-order session is refused before any shopper data is gathered for it
	 * and before WooPay is contacted.
	 *
	 * @param int         $order_id The order ID the caller sends.
	 * @param string|null $key      The order key the caller sends.
	 */
	private function assert_pay_for_order_session_refused( int $order_id, ?string $key ): void {
		$this->mock_customer_service->expects( $this->never() )->method( 'get_customer_id_by_user_id' );
		$this->mock_customer_service->expects( $this->never() )->method( 'create_customer_for_user' );

		[ $response, $sent ] = $this->post_pay_for_order_session( $order_id, $key );

		$this->assertSame(
			[
				'success' => false,
				'data'    => 'You aren’t authorized to do that.',
			],
			$response
		);
		$this->assertSame( [], $sent );
	}

	/**
	 * Asserts the session sent to WooPay returns the shopper to the order's own payment link.
	 *
	 * @param WC_Order $order The order being paid.
	 */
	private function assert_pay_for_order_session_links_back_to( WC_Order $order ): void {
		[ , $sent ] = $this->post_pay_for_order_session( $order->get_id(), $order->get_order_key(), $order->get_billing_email() );

		$this->assertCount( 1, $sent );

		$store_data = $sent[0]['store_data'];
		parse_str( (string) wp_parse_url( $store_data['return_url'], PHP_URL_QUERY ), $query );

		$this->assertSame( $order->get_order_key(), $query['key'] ?? null );
		$this->assertSame( $store_data['return_url'], $store_data['blog_checkout_url'] );
	}

	/**
	 * Short-circuits outgoing HTTP requests.
	 *
	 * @return WP_Error
	 */
	public function block_http_request() {
		return new WP_Error( 'http_request_blocked', 'Outgoing requests are blocked in this test.' );
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

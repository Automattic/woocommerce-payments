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
		$_SERVER['REQUEST_URI']     = '/wp-json/wc/store/v1/checkout';

		$this->mock_customer_service     = $this->createMock( WC_Payments_Customer_Service::class );
		$this->original_customer_service = WC_Payments::get_customer_service();
		WC_Payments::set_customer_service( $this->mock_customer_service );

		// Needed as the key for attested-email envelopes; see build_envelope().
		Jetpack_Options::update_option( 'blog_token', 'test.blog.token' );
	}

	public function tear_down() {
		// The request context this class fakes must not outlive it: suites that run later
		// read $_SERVER['REQUEST_URI'] to decide whether they are serving the Store API.
		$_SERVER = $this->original_server;

		// Leave an ordinary front-end request behind rather than no request at all —
		// WordPress always defines this, and code under test dereferences it directly.
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = '/';
		}

		WC_Payments::set_customer_service( $this->original_customer_service );

		wp_set_current_user( 0 );

		remove_filter( 'wcpay_is_woopay_store_api_request', '__return_true' );

		unset(
			$_GET[ WooPay_Session::ATTESTATION_PARAM ],
			$_POST[ WooPay_Session::ATTESTATION_PARAM ],
			$_SERVER[ WooPay_Session::VOUCH_HEADER ]
		);

		parent::tear_down();
	}

	public function test_a_sealed_shopper_ip_is_recorded_on_the_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_customer_ip_address( '192.0.91.172' );

		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = $this->build_vouch_header( [ 'customer_ip' => '203.0.113.10' ] );

		WooPay_Session::set_woopay_order_customer_ip( $order );

		// The address WooPay saw the shopper arrive from, rather than the WordPress.com
		// address this request left from. See WOOPAY-415.
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

	public function test_an_unsealed_address_cannot_be_stamped_on_an_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_customer_ip_address( '192.0.91.172' );

		// Everything a visitor can arrange for themselves: the User-Agent, a Cart-Token, and
		// a header naming whatever address they like. Before the value moved inside the
		// envelope this was enough to stamp it on their own order and walk past the
		// merchant's IP rules. See WOOPAY-463.
		$_SERVER['HTTP_USER_AGENT']           = 'WooPay';
		$_SERVER['HTTP_X_WOOPAY_CUSTOMER_IP'] = '203.0.113.10';
		$_SERVER['HTTP_CART_TOKEN']           = WooPay_Store_Api_Token::init()->get_cart_token();

		add_filter( 'wcpay_is_woopay_store_api_request', '__return_true' );

		try {
			WooPay_Session::set_woopay_order_customer_ip( $order );

			$this->assertSame( '192.0.91.172', $order->get_customer_ip_address() );
		} finally {
			remove_filter( 'wcpay_is_woopay_store_api_request', '__return_true' );
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
		$key        = WooPay_Utilities::derive_key_for( WooPay_Utilities::VOUCH_KEY_PURPOSE );
		$iv         = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$plaintext  = wp_json_encode( array_merge( [ 'timestamp' => time() ], $extra ) );
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

	public function test_an_accepted_woopay_request_marks_itself_as_one() {
		// Pins what accepting a Cart-Token buys: the request is flagged as WooPay before any
		// customer resolves. Anything gated on this filter is therefore only as strong as the
		// credential the auth path accepted.
		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();

		$this->setup_session( 0 );

		WooPay_Session::determine_current_user_for_woopay( 0 );

		/**
		 * Filters whether the current request is a WooPay Store API request.
		 *
		 * @since 7.2.0
		 *
		 * @param bool $is_woopay_store_api_request Whether this is a WooPay Store API request.
		 */
		$this->assertTrue( apply_filters( 'wcpay_is_woopay_store_api_request', false ) );
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
		$_SERVER['HTTP_NONCE']      = $this->create_woopay_nonce( $woopay_user->ID );

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
		$_SERVER['HTTP_NONCE']      = $this->create_woopay_nonce( $woopay_user->ID );

		$this->setup_session( $woopay_user->ID );

		$this->assertEquals( WooPay_Session::determine_current_user_for_woopay( $guest_user ), $woopay_user->ID );

		unset( $_REQUEST['rest_route'] );
	}

	public function test_get_request_auth_level_returns_cart_token_for_a_valid_cart_token() {
		$woopay_store_api_token     = WooPay_Store_Api_Token::init();
		$_SERVER['HTTP_CART_TOKEN'] = $woopay_store_api_token->get_cart_token();

		$this->assertSame( 'cart_token', WooPay_Session::get_request_auth_level() );
	}

	public function test_session_payload_carries_the_store_version() {
		// WooPay decides whether to sign by comparing this against its own minimum, and
		// treats a payload without it as too old. Dropping it would leave every store on
		// signed requests with nothing failing to say so.
		$request = WooPay_Session::get_init_session_request();

		$this->assertArrayHasKey( 'wcpay_version', $request );
		$this->assertSame( WCPAY_VERSION_NUMBER, $request['wcpay_version'] );
	}

	public function test_get_request_auth_level_returns_none_when_cart_token_is_invalid() {
		$_SERVER['HTTP_CART_TOKEN'] = 'not-a-valid-cart-token';

		$this->assertSame( 'none', WooPay_Session::get_request_auth_level() );
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

	public function test_email_is_not_attested_without_an_envelope() {
		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_email_is_attested_by_a_fresh_envelope_for_that_email() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );

		$this->assertTrue( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_envelope_does_not_attest_to_a_different_email() {
		// The envelope is valid, but names someone else, so it vouches only for that address.
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'other@example.com' );

		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_stale_envelope_does_not_attest() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com', time() - 3600 );

		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	/**
	 * @dataProvider provider_malformed_envelopes
	 *
	 * @param mixed $envelope The malformed value arriving as the attestation param.
	 */
	public function test_malformed_envelope_does_not_attest( $envelope ) {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $envelope;

		// decrypt_signed_data() indexes data/iv/hash directly, so these have to be refused
		// here rather than reaching it and warning on an undefined index.
		$this->assertNull( WooPay_Session::get_woopay_attestation() );
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
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope();

		// A guest shopper has no account to name. The attestation still stands — it is
		// what admits the request — but there is no email for it to vouch for.
		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );
		$this->assertNull( WooPay_Session::get_woopay_attested_account_email() );
		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_absent_envelope_does_not_attest() {
		// Carrying no envelope at all is the ordinary case, not a malformed one.
		$this->assertNull( WooPay_Session::get_woopay_attestation() );
	}

	public function test_envelope_is_refused_on_a_second_request() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );

		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );

		// A second request replaying the same envelope is the attack: it is still fresh,
		// still sealed correctly, and would otherwise mint a nonce for that shopper.
		$this->reset_resolved_attestations();

		$this->assertNull( WooPay_Session::get_woopay_attestation() );
		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_envelope_serves_every_caller_within_one_request() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );

		// The permission check, the email lookup and the nonce gate each resolve the
		// envelope independently. Spending it on the first of them would break checkout.
		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );
		$this->assertSame( 'shopper@example.com', WooPay_Session::get_woopay_attested_account_email() );
		$this->assertTrue( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );
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
			$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );

			$this->assertNotNull( WooPay_Session::get_woopay_attestation() );

			$this->reset_resolved_attestations();

			$this->assertNull( WooPay_Session::get_woopay_attestation() );
		} finally {
			wp_using_ext_object_cache( $was_using_ext_object_cache );
		}
	}

	public function test_the_recorded_claim_does_not_carry_a_timestamp() {
		$envelope = $this->build_envelope( 'shopper@example.com' );

		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $envelope;

		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );

		// On the database path a duplicate claim is refused by MySQL reporting zero affected
		// rows for an INSERT that changes nothing, which only holds while every request
		// writes the same value. A timestamp here would make two requests either side of a
		// second boundary look like a change, and both would be told they spent the envelope.
		$this->assertEquals( 1, get_transient( WooPay_Session::ATTESTATION_CLAIM_PREFIX . md5( $envelope['hash'] ) ) );
	}

	public function test_spending_one_envelope_does_not_affect_another() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );
		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );

		// A shopper's envelope being spent must not lock anyone else out.
		$this->reset_resolved_attestations();
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'other@example.com' );

		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );
	}

	public function test_stale_envelope_is_not_spent_before_it_is_rejected() {
		// A stale envelope is refused on freshness, so it never reaches the claim — the
		// rejection reason stays 'stale' rather than turning into 'already used'.
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com', time() - 3600 );

		$this->assertNull( WooPay_Session::get_woopay_attestation() );
		$this->assertFalse( $this->attestation_was_claimed( $_POST[ WooPay_Session::ATTESTATION_PARAM ]['hash'] ) );
	}

	public function test_attested_account_email_outranks_a_caller_supplied_email() {
		$_GET['email']                              = 'other@example.com';
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );

		$this->assertSame( 'shopper@example.com', WooPay_Session::get_user_email( wp_get_current_user() ) );

		unset( $_GET['email'] );
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

	public function test_invalid_cart_token_is_rejected_as_invalid() {
		$_SERVER['HTTP_CART_TOKEN'] = 'not.a.valid.token';

		$this->expect_woopay_request_to_die( 'The Cart-Token on this WooPay request is invalid or expired.' );
	}

	/**
	 * Asserts the current request is refused by determine_current_user_for_woopay().
	 *
	 * @param string $expected_message The message the request should be refused with.
	 */
	private function expect_woopay_request_to_die( string $expected_message ) {
		try {
			WooPay_Session::determine_current_user_for_woopay( null );
		} catch ( WPDieException $e ) {
			$this->assertSame( $expected_message, $e->getMessage() );
			$this->assertSame( 401, $e->getCode() );

			return;
		}

		$this->fail( 'Expected the request to be refused, but it was allowed through.' );
	}

	public function test_nonce_is_withheld_from_the_rest_route_when_the_email_is_not_attested() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		// Names the shopper in a plain parameter, which anyone can do.
		$_GET['email'] = $shopper->user_email;

		$request = WooPay_Session::get_init_session_request( null, null, null, new WP_REST_Request() );

		// The REST route hands this array back in plaintext, so an unattested caller must
		// not be able to name an email and be given a nonce minted for that account.
		$this->assertArrayNotHasKey( 'email_verified_session_nonce', $request );

		unset( $_GET['email'] );
	}

	public function test_nonce_is_minted_for_the_rest_route_when_the_email_is_attested() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( $shopper->user_email );

		$request = WooPay_Session::get_init_session_request( null, null, null, new WP_REST_Request() );

		// This is the flow the gate exists to permit: WooPay vouched for the email, so the
		// shopper gets the nonce that unlocks their balance.
		$this->assertArrayHasKey( 'email_verified_session_nonce', $request );
	}

	public function test_nonce_is_minted_without_an_attestation_when_it_is_not_disclosed() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		$_GET['email'] = $shopper->user_email;

		// No WP_REST_Request means the frontend or redirect caller, which encrypts the
		// payload or POSTs it server-side. Nothing is disclosed, so nothing is withheld —
		// gating these too would break the paths that never leaked the nonce.
		$request = WooPay_Session::get_init_session_request();

		$this->assertArrayHasKey( 'email_verified_session_nonce', $request );

		unset( $_GET['email'] );
	}

	public function test_rest_route_withholds_extension_data_for_an_email_it_was_only_told_about() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		// Names the shopper in a plain parameter, which anyone can do.
		$_GET['email'] = $shopper->user_email;

		$request = WooPay_Session::get_init_session_request( null, null, null, new WP_REST_Request() );

		// The nonce was already withheld here, but the balance it guards was not: this route
		// returns adapted extension data in plaintext, so naming an address must not be enough
		// to read that account's Points & Rewards and Gift Card data.
		$this->assertArrayNotHasKey( 'adapted_extensions', $request );

		// The email itself still travels. It is how WooPay recognises a returning shopper and
		// decides to ask for an OTP, so withholding it signs nobody in — a narrower question
		// than whose balances to hand back, and answered separately.
		$this->assertSame( $shopper->user_email, $request['email'] );

		unset( $_GET['email'] );
	}

	public function test_a_guest_envelope_does_not_let_the_caller_name_someone_elses_email() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		// A guest shopper has no account to name, so their envelope carries no email — and it
		// still authorizes the route. That combination is the one worth pinning: the envelope
		// gets the caller in, and the parameter must not then decide whose data comes back.
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope();
		$_POST['email']                             = $shopper->user_email;

		$request = WooPay_Session::get_init_session_request( null, null, null, new WP_REST_Request() );

		$this->assertArrayNotHasKey( 'adapted_extensions', $request );
		$this->assertArrayNotHasKey( 'email_verified_session_nonce', $request );

		// Still named to WooPay, so the shopper can still be asked for an OTP.
		$this->assertSame( $shopper->user_email, $request['email'] );

		unset( $_POST['email'] );
	}

	public function test_rest_route_returns_extension_data_for_an_attested_email() {
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( $shopper->user_email );

		$request = WooPay_Session::get_init_session_request( null, null, null, new WP_REST_Request() );

		// The control for the two above: withholding is the point, but not at the cost of the
		// flow this route exists to serve.
		$this->assertSame( $shopper->user_email, $request['email'] );
		$this->assertNotEmpty( $request['adapted_extensions'] );
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
		return false !== get_transient( WooPay_Session::ATTESTATION_CLAIM_PREFIX . md5( $hash ) );
	}

	/**
	 * Seals an envelope the way WooPay does, so these tests fail if the two ends drift.
	 *
	 * Mirrors what decrypt_signed_data() expects, which is not the shape
	 * encrypt_and_sign_data() produces — the two directions are separate protocols.
	 *
	 * @param string|null $email     The email to attest to, or null for a guest shopper.
	 * @param int|null $timestamp Envelope timestamp. Defaults to now.
	 *
	 * @return array The base64-encoded envelope.
	 */
	private function build_envelope( ?string $email = null, ?int $timestamp = null ): array {
		$key = \WCPay\WooPay\WooPay_Utilities::get_store_blog_token();

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

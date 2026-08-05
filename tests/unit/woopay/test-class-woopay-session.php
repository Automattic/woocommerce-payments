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

		// Needed as the key for attested-email envelopes; see build_envelope().
		Jetpack_Options::update_option( 'blog_token', 'test.blog.token' );
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
			$_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'],
			$_GET['encrypted_data'],
			$_POST['encrypted_data']
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
		$this->assertSame( 'blog_token', WooPay_Session::get_request_auth_level() );
	}

	public function test_get_request_auth_level_returns_none_without_signature_when_cart_token_auth_disabled() {
		$this->unsign_request();
		$this->deny_cart_token_auth();

		$woopay_store_api_token     = WooPay_Store_Api_Token::init();
		$_SERVER['HTTP_CART_TOKEN'] = $woopay_store_api_token->get_cart_token();

		$this->assertSame( 'none', WooPay_Session::get_request_auth_level() );
	}

	public function test_get_request_auth_level_returns_cart_token_by_default() {
		$this->unsign_request();

		$woopay_store_api_token     = WooPay_Store_Api_Token::init();
		$_SERVER['HTTP_CART_TOKEN'] = $woopay_store_api_token->get_cart_token();

		$this->assertSame( 'cart_token', WooPay_Session::get_request_auth_level() );
	}

	public function test_get_request_auth_level_returns_none_for_cart_token_when_opted_out() {
		$this->unsign_request();
		$this->deny_cart_token_auth();

		$woopay_store_api_token     = WooPay_Store_Api_Token::init();
		$_SERVER['HTTP_CART_TOKEN'] = $woopay_store_api_token->get_cart_token();

		$this->assertSame( 'none', WooPay_Session::get_request_auth_level() );
	}

	public function test_store_advertises_cart_token_auth_support_by_default() {
		// The store advertising this is what lets WooPay stop signing, so the
		// default is load-bearing rather than incidental.
		$this->assertTrue( WooPay_Session::is_cart_token_auth_allowed() );
	}

	public function test_store_does_not_advertise_cart_token_auth_support_when_opted_out() {
		$this->deny_cart_token_auth();

		$this->assertFalse( WooPay_Session::is_cart_token_auth_allowed() );
	}

	public function test_store_advertises_cart_token_auth_support_when_opted_in() {
		$this->allow_cart_token_auth();

		$this->assertTrue( WooPay_Session::is_cart_token_auth_allowed() );
	}

	public function test_get_request_auth_level_returns_none_when_cart_token_is_invalid() {
		$this->unsign_request();
		$this->allow_cart_token_auth();

		$_SERVER['HTTP_CART_TOKEN'] = 'not-a-valid-cart-token';

		$this->assertSame( 'none', WooPay_Session::get_request_auth_level() );
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

	public function test_email_is_attested_when_the_request_is_signed() {
		// A signed request is WooPay by definition, so the email it names needs no envelope.
		$this->assertTrue( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_email_is_not_attested_without_a_signature_or_envelope() {
		$this->unsign_request();

		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_email_is_attested_by_a_fresh_envelope_for_that_email() {
		$this->unsign_request();

		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		$this->assertTrue( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_envelope_does_not_attest_to_a_different_email() {
		$this->unsign_request();

		// The envelope is valid, but names someone else, so it vouches only for that address.
		$_GET['encrypted_data'] = $this->build_envelope( 'other@example.com' );

		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_stale_envelope_does_not_attest() {
		$this->unsign_request();

		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com', time() - 3600 );

		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_envelope_is_refused_on_a_second_request() {
		$this->unsign_request();

		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );

		// A second request replaying the same envelope is the attack: it is still fresh,
		// still sealed correctly, and would otherwise mint a nonce for that shopper.
		$this->reset_resolved_attestations();

		$this->assertNull( WooPay_Session::get_woopay_attestation() );
		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_envelope_serves_every_caller_within_one_request() {
		$this->unsign_request();

		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		// The permission check, the email lookup and the nonce gate each resolve the
		// envelope independently. Spending it on the first of them would break checkout.
		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );
		$this->assertSame( 'shopper@example.com', WooPay_Session::get_woopay_attested_email() );
		$this->assertTrue( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );
	}

	public function test_spending_one_envelope_does_not_affect_another() {
		$this->unsign_request();

		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );
		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );

		// A shopper's envelope being spent must not lock anyone else out.
		$this->reset_resolved_attestations();
		$_GET['encrypted_data'] = $this->build_envelope( 'other@example.com' );

		$this->assertNotNull( WooPay_Session::get_woopay_attestation() );
	}

	public function test_stale_envelope_is_not_spent_before_it_is_rejected() {
		$this->unsign_request();

		// A stale envelope is refused on freshness, so it never reaches the claim — the
		// rejection reason stays 'stale' rather than turning into 'already used'.
		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com', time() - 3600 );

		$this->assertNull( WooPay_Session::get_woopay_attestation() );
		$this->assertFalse( $this->attestation_was_claimed( $_GET['encrypted_data']['hash'] ) );
	}

	public function test_envelope_does_not_attest_when_opted_out() {
		$this->unsign_request();
		$this->deny_cart_token_auth();

		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		// Opting out has to reach every consumer of the envelope, not just the route's
		// permission check — this is what gates minting email_verified_session_nonce.
		$this->assertFalse( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
		$this->assertNull( WooPay_Session::get_woopay_attestation() );
	}

	public function test_signature_still_attests_when_opted_out() {
		$this->deny_cart_token_auth();

		// The signature is what the opt-out falls back to, so it must still vouch.
		$this->assertTrue( WooPay_Session::is_email_attested_by_woopay( 'shopper@example.com' ) );
	}

	public function test_attested_email_does_not_outrank_a_caller_supplied_email_when_opted_out() {
		$this->unsign_request();
		$this->deny_cart_token_auth();

		$_GET['email']          = 'other@example.com';
		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		// With the envelope refused, this falls back to the plain parameter it would have
		// used before the attestation existed.
		$this->assertSame( 'other@example.com', WooPay_Session::get_user_email( wp_get_current_user() ) );

		unset( $_GET['email'] );
	}

	public function test_attested_email_outranks_a_caller_supplied_email() {
		$this->unsign_request();

		$_GET['email']          = 'other@example.com';
		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		$this->assertSame( 'shopper@example.com', WooPay_Session::get_user_email( wp_get_current_user() ) );

		unset( $_GET['email'] );
	}

	public function test_authenticated_cart_token_is_accepted_with_a_store_minted_nonce() {
		$shopper = self::factory()->user->create_and_get();

		$this->unsign_request();
		$this->allow_cart_token_auth();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();
		$_SERVER['HTTP_NONCE']      = $this->create_woopay_nonce( $shopper->ID );

		$this->setup_session( $shopper->ID );

		$this->assertEquals( $shopper->ID, WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_authenticated_cart_token_is_rejected_without_a_nonce() {
		$shopper = self::factory()->user->create_and_get();

		$this->unsign_request();
		$this->allow_cart_token_auth();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();

		$this->setup_session( $shopper->ID );

		// A Cart-Token on its own no longer resolves the account behind the session.
		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_authenticated_cart_token_is_rejected_with_a_nonce_for_another_user() {
		$shopper = self::factory()->user->create_and_get();
		$other   = self::factory()->user->create_and_get();

		$this->unsign_request();
		$this->allow_cart_token_auth();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();
		$_SERVER['HTTP_NONCE']      = $this->create_woopay_nonce( $other->ID );

		$this->setup_session( $shopper->ID );

		$this->assertNull( WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_authenticated_cart_token_does_not_require_a_nonce_when_signed() {
		$shopper = self::factory()->user->create_and_get();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();

		$this->setup_session( $shopper->ID );

		// A signed request proves WooPay composed it, so the nonce adds nothing. WooPay
		// versions that still sign must keep working unchanged.
		$this->assertEquals( $shopper->ID, WooPay_Session::get_user_id_from_cart_token() );
	}

	public function test_unauthenticated_request_is_rejected_as_carrying_nothing() {
		$this->unsign_request();

		$this->expect_woopay_request_to_die( 'WooPay request is not signed correctly.' );
	}

	public function test_cart_token_request_is_rejected_as_needing_a_signature_when_opted_out() {
		$this->unsign_request();
		$this->deny_cart_token_auth();

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();

		// The store opted back out while WooPay was still going unsigned for it. Saying so
		// separates a transient rollout mismatch from a store that is actually misconfigured.
		$this->expect_woopay_request_to_die( 'This store requires WooPay requests to be signed with its blog token.' );
	}

	public function test_invalid_cart_token_is_rejected_as_invalid() {
		$this->unsign_request();
		$this->allow_cart_token_auth();

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
		$this->unsign_request();
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
		$this->unsign_request();
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		$_GET['encrypted_data'] = $this->build_envelope( $shopper->user_email );

		$request = WooPay_Session::get_init_session_request( null, null, null, new WP_REST_Request() );

		// This is the flow the gate exists to permit: WooPay vouched for the email, so the
		// shopper gets the nonce that unlocks their balance.
		$this->assertArrayHasKey( 'email_verified_session_nonce', $request );
	}

	public function test_nonce_is_minted_without_an_attestation_when_it_is_not_disclosed() {
		$this->unsign_request();
		$shopper = $this->setup_shopper_with_adapted_extension_balance();

		$_GET['email'] = $shopper->user_email;

		// No WP_REST_Request means the frontend or redirect caller, which encrypts the
		// payload or POSTs it server-side. Nothing is disclosed, so nothing is withheld —
		// gating these too would break the paths that never leaked the nonce.
		$request = WooPay_Session::get_init_session_request();

		$this->assertArrayHasKey( 'email_verified_session_nonce', $request );

		unset( $_GET['email'] );
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
	 * @param string   $email     The email to attest to.
	 * @param int|null $timestamp Envelope timestamp. Defaults to now.
	 *
	 * @return array The base64-encoded envelope.
	 */
	private function build_envelope( string $email, ?int $timestamp = null ): array {
		$key = \WCPay\WooPay\WooPay_Utilities::get_store_blog_token();

		$payload = wp_json_encode(
			[
				'user_email' => $email,
				'timestamp'  => $timestamp ?? time(),
			]
		);

		$iv         = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$ciphertext = openssl_encrypt( $payload, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return [
			'data' => base64_encode( $ciphertext ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'iv'   => base64_encode( $iv ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'hash' => base64_encode( hash_hmac( 'sha256', $iv . $ciphertext, $key ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		];
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

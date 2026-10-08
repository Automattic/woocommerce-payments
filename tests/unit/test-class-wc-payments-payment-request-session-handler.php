<?php
/**
 * Class WC_Payments_Payment_Request_Session_Handler_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;

require_once WCPAY_ABSPATH . '/includes/class-wc-payments-payment-request-session-handler.php';

/**
 * WC_Payments_Payment_Request_Session_Handler unit tests.
 */
class WC_Payments_Payment_Request_Session_Handler_Test extends WCPAY_UnitTestCase {

	/**
	 * Previous user ID.
	 * @var int
	 */
	private $previous_user_id;

	/**
	 * Cookies set during the execution of one test.
	 * @var int
	 */
	private $cookies_jar = [];

	/**
	 * The separator used by the WC_Session_Handler, which changes between WC versions.
	 * @var string
	 */
	private $cookies_separator = '||';

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->previous_user_id = get_current_user_id();
		add_filter( 'woocommerce_set_cookie_enabled', [ $this, '__cookie_handler' ], 10, 3 );
		wp_logout();
		// emptying the cookies that would otherwise have been set by `wp_logout`.
		$this->cookies_jar = [];
		if ( version_compare( WC_VERSION, '10.0.0', '>=' ) ) {
			$this->cookies_separator = '|';
		}
	}

	public function tear_down() {
		wp_set_current_user( $this->previous_user_id );
		unset( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION'] );
		unset( $_COOKIE[ 'wp_woocommerce_session_' . COOKIEHASH ] );
		remove_filter( 'woocommerce_set_cookie_enabled', [ $this, '__cookie_handler' ] );
		$this->cookies_jar = [];

		parent::tear_down();
	}

	public function __cookie_handler( $_, $name, $value ) {
		$this->cookies_jar[ $name ] = $value;

		return false;
	}

	/**
	 * This function is meant to mock the value expected by `WC_Session_Handler->get_session_cookie()`.
	 *
	 * @param string $customer_id A fake customer id;
	 *
	 * @return void
	 */
	private function __create_fake_session_cookie( $customer_id ) {
		$session_expiration = time() + HOUR_IN_SECONDS;

		$to_hash     = $customer_id . $this->cookies_separator . $session_expiration;
		$cookie_hash = hash_hmac( 'md5', $to_hash, wp_hash( $to_hash ) );

		$_COOKIE[ 'wp_woocommerce_session_' . COOKIEHASH ] = implode(
			$this->cookies_separator,
			[
				$customer_id,
				$session_expiration,
				time() + HOUR_IN_SECONDS,
				$cookie_hash,
			]
		);
	}

	/**
	 * When a request is sent without cookies and without a token header, the session should be saved in the DB, and the cookies should be sent.
	 */
	public function test_anonymous_session_saved_and_cookie_sent_without_token() {
		global $wpdb;

		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION'] = '';

		$session_handler = new WC_Payments_Payment_Request_Session_Handler();
		$session_handler->init();

		$session_handler->set( 'test_key', 'test_value' );
		$session_handler->set( 'cart_key', 'cart_data' );

		$this->assertEquals( 'test_value', $session_handler->get( 'test_key' ) );
		$this->assertEquals( 'cart_data', $session_handler->get( 'cart_key' ) );

		$session_id = $session_handler->session_id;
		$this->assertStringStartsWith( 't_', $session_id );
		$this->assertArrayNotHasKey( 'wp_woocommerce_session_' . COOKIEHASH, $this->cookies_jar );

		$session_handler->save_data();
		do_action( 'woocommerce_set_cart_cookies', true ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment

		$session_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s",
				$session_id
			)
		);

		$this->assertNotNull( $session_data );
		$unserialized_data = maybe_unserialize( $session_data->session_value );
		$this->assertEquals( 'test_value', $unserialized_data['test_key'] );
		$this->assertEquals( 'cart_data', $unserialized_data['cart_key'] );
		$this->assertArrayHasKey( 'wp_woocommerce_session_' . COOKIEHASH, $this->cookies_jar );

		list( $cookie_customer_id ) = explode( $this->cookies_separator, $this->cookies_jar[ 'wp_woocommerce_session_' . COOKIEHASH ] );
		$this->assertNotEquals( $session_id, $cookie_customer_id );
		$this->assertEquals( $session_handler->get_customer_id(), $cookie_customer_id );
	}

	/**
	 * When a request is sent without cookies and without a token header, the session should be saved in the DB, and the cookies should be sent.
	 */
	public function test_session_saved_and_cookie_sent_without_token() {
		global $wpdb;

		wp_set_current_user( 1 );

		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION'] = '';

		$session_handler = new WC_Payments_Payment_Request_Session_Handler();
		$session_handler->init();

		$session_handler->set( 'test_key', 'test_value' );
		$session_handler->set( 'cart_key', 'cart_data' );

		$this->assertEquals( 'test_value', $session_handler->get( 'test_key' ) );
		$this->assertEquals( 'cart_data', $session_handler->get( 'cart_key' ) );

		$session_id = $session_handler->session_id;
		$this->assertStringStartsWith( 't_', $session_id );
		$this->assertArrayNotHasKey( 'wp_woocommerce_session_' . COOKIEHASH, $this->cookies_jar );

		$session_handler->save_data();
		do_action( 'woocommerce_set_cart_cookies', true ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment

		$session_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s",
				$session_id
			)
		);

		$this->assertNotNull( $session_data );
		$unserialized_data = maybe_unserialize( $session_data->session_value );
		$this->assertEquals( 'test_value', $unserialized_data['test_key'] );
		$this->assertEquals( 'cart_data', $unserialized_data['cart_key'] );
		$this->assertArrayHasKey( 'wp_woocommerce_session_' . COOKIEHASH, $this->cookies_jar );

		list( $cookie_customer_id ) = explode( $this->cookies_separator, $this->cookies_jar[ 'wp_woocommerce_session_' . COOKIEHASH ] );
		$this->assertNotEquals( $session_id, $cookie_customer_id );
		$this->assertEquals( $session_handler->get_customer_id(), $cookie_customer_id );
	}

	/**
	 * When a request is received with cookies and with a token header, the session returns the session value from the DB.
	 */
	public function test_session_retrieved_with_token_header() {
		global $wpdb;

		wp_set_current_user( 1 );

		// creating some mocked values for the session identified by the header.
		$mock_header_session_id  = 't_' . substr( md5( 'test_session_' . time() ), 0, 30 );
		$mock_header_session_exp = time() + HOUR_IN_SECONDS;
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_sessions',
			[
				'session_key'    => $mock_header_session_id,
				// using "maybe_serialize" instead of just "serialize" because otherwise phpcs isn't happy.
				'session_value'  => maybe_serialize(
					[
						'token_customer_id' => strval( get_current_user_id() ),
						'test_key'          => 'header_session_value',
					]
				),
				'session_expiry' => $mock_header_session_exp,
			]
		);
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION'] = JsonWebToken::create(
			[
				'session_id' => $mock_header_session_id,
				'exp'        => $mock_header_session_exp,
				'iss'        => 'woopayments/product-page',
			],
			'@' . wp_salt()
		);

		// creating some mocked values for the session identified by the cookie.
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_sessions',
			[
				'session_key'    => get_current_user_id(),
				// using "maybe_serialize" instead of just "serialize" because otherwise phpcs isn't happy.
				'session_value'  => maybe_serialize(
					[
						'test_key' => 'another_value',
					]
				),
				'session_expiry' => time() + HOUR_IN_SECONDS,
			]
		);
		$this->__create_fake_session_cookie( get_current_user_id() );

		$session_handler = new WC_Payments_Payment_Request_Session_Handler();
		$session_handler->init();

		$this->assertEquals( $mock_header_session_id, $session_handler->session_id );
		$this->assertEquals( 'header_session_value', $session_handler->get( 'test_key' ) );
		$this->assertEquals( '1', $session_handler->get( 'token_customer_id' ) );
		$this->assertEquals( 1, $session_handler->get_customer_id() );
	}

	/**
	 * Store API checkout calls `init_session_cookie()` again after creating the buyer's account.
	 * The parent then migrates WooCommerce's guest session, which must not replace the token session.
	 */
	public function test_token_session_survives_account_creation_at_checkout() {
		$token_session_id = $this->__set_up_guest_express_session();

		$session_handler = new WC_Payments_Payment_Request_Session_Handler();
		$session_handler->init();
		// Changed earlier in the checkout request and not saved yet.
		$session_handler->set( 'customer', [ 'email' => 'new-buyer@example.com' ] );

		$new_customer_id = self::factory()->user->create();
		wp_set_current_user( $new_customer_id );
		$session_handler->init_session_cookie();

		// Nothing has been saved since the migration, so the stored token session must be unchanged.
		$this->assertSame( [ 'flat_rate:1' ], $session_handler->get_session( $token_session_id )['chosen_shipping_methods'] ?? null );
		$this->assertSame( $token_session_id, $session_handler->session_id );
		$this->assertSame( [ 'flat_rate:1' ], $session_handler->get( 'chosen_shipping_methods' ) );
		$this->assertNull( $session_handler->get( 'test_key' ) );
		$this->assertSame( [ 'email' => 'new-buyer@example.com' ], $session_handler->get( 'customer' ) );
		$this->assertSame( (string) $new_customer_id, $session_handler->get( 'token_customer_id' ) );
		$this->assertSame( (string) $new_customer_id, $session_handler->get_customer_id() );
	}

	/**
	 * A retry after a failed payment sends the same token with WooCommerce's cookie for the new user.
	 * The token session saved after account creation must pass the token/customer check.
	 */
	public function test_follow_up_request_after_account_creation_passes_token_check() {
		$token_session_id = $this->__set_up_guest_express_session();

		$session_handler = new WC_Payments_Payment_Request_Session_Handler();
		$session_handler->init();
		$new_customer_id = self::factory()->user->create();
		wp_set_current_user( $new_customer_id );
		$session_handler->init_session_cookie();
		$session_handler->save_data();

		// After migrating the guest session, WooCommerce points its session cookie at the new user.
		$this->__create_fake_session_cookie( (string) $new_customer_id );
		$follow_up_session_handler = new WC_Payments_Payment_Request_Session_Handler();
		$follow_up_session_handler->init();

		$this->assertSame( $token_session_id, $follow_up_session_handler->session_id );
		$this->assertSame( (string) $new_customer_id, $follow_up_session_handler->get( 'token_customer_id' ) );
		$this->assertSame( [ 'flat_rate:1' ], $follow_up_session_handler->get( 'chosen_shipping_methods' ) );
	}

	/**
	 * Sets up a guest's product-page express checkout session: the token session holding the shipping
	 * choice and its header, plus WooCommerce's normal guest session and cookie.
	 *
	 * @return string The token session ID.
	 */
	private function __set_up_guest_express_session() {
		global $wpdb;

		$guest_customer_id = 't_' . substr( md5( uniqid( 'guest_session_', true ) ), 0, 30 );
		$token_session_id  = 't_' . substr( md5( uniqid( 'token_session_', true ) ), 0, 30 );
		$session_expiry    = time() + HOUR_IN_SECONDS;
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_sessions',
			[
				'session_key'    => $token_session_id,
				'session_value'  => maybe_serialize(
					[
						'token_customer_id'       => $guest_customer_id,
						'chosen_shipping_methods' => [ 'flat_rate:1' ],
					]
				),
				'session_expiry' => $session_expiry,
			]
		);
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION'] = JsonWebToken::create(
			[
				'session_id' => $token_session_id,
				'exp'        => $session_expiry,
				'iss'        => 'woopayments/product-page',
			],
			'@' . wp_salt()
		);
		// WooCommerce's normal guest session, which never held the express checkout cart.
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_sessions',
			[
				'session_key'    => $guest_customer_id,
				'session_value'  => maybe_serialize( [ 'test_key' => 'guest_session_value' ] ),
				'session_expiry' => $session_expiry,
			]
		);
		$this->__create_fake_session_cookie( $guest_customer_id );

		return $token_session_id;
	}
}

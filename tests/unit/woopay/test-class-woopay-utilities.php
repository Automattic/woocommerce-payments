<?php
/**
 * Class WooPay_Utilities_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Utilities;
use WCPay\WooPay\WooPay_Session;

/**
 * WooPay_Utilities unit tests.
 */
class WooPay_Utilities_Test extends WCPAY_UnitTestCase {
	public function set_up() {
		parent::set_up();
		$this->gateway_mock = $this->createMock( WC_Payment_Gateway_WCPay::class );

		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );
	}

	public function tear_down() {
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );

		parent::tear_down();
	}

	/**
	 * Data provider for test_should_enable_woopay.
	 *
	 * @return array
	 */
	public function should_enable_woopay_data_provider() {
		return [
			[ true, 'yes', true ],
			[ true, 'no', false ],
			[ false, 'yes', false ],
			[ false, 'no', false ],
		];
	}

	/**
	 * WooPay is available if feature flags are enabled.
	 *
	 * @dataProvider should_enable_woopay_data_provider
	 * @return void
	 */
	public function test_should_enable_woopay( $woopay_eligible, $gateway_woopay_enabled, $expected ) {
		$this->set_is_woopay_eligible( $woopay_eligible );

		$this->gateway_mock->expects( $this->once() )
			->method( 'get_option' )
			->with( 'platform_checkout', 'no' )
			->willReturn( $gateway_woopay_enabled );

		$woopay_utilities = new WooPay_Utilities();
		$actual           = $woopay_utilities->should_enable_woopay( $this->gateway_mock );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_is_country_available.
	 *
	 * @see test-data/ip_geolocation.json
	 *
	 * @return array
	 */
	public function is_country_available_data_provider() {
		return [
			[ '206.71.50.230', true ], // US.
			[ '187.34.8.193', false ], // BR.
		];
	}

	/**
	 * WooPay is available if feature flags are enabled.
	 *
	 * @dataProvider is_country_available_data_provider
	 * @return void
	 */
	public function test_is_country_available( $ip_address, $expected ) {
		$_SERVER['REMOTE_ADDR'] = $ip_address;

		WC_Payments::mode()->live();

		$woopay_utilities = new WooPay_Utilities();
		$actual           = $woopay_utilities->is_country_available();
		$this->assertSame( $expected, $actual );
	}

	public function test_is_country_available_in_test_mode_return_true() {
		WC_Payments::mode()->test();

		$woopay_utilities = new WooPay_Utilities();
		$actual           = $woopay_utilities->is_country_available();
		$this->assertSame( true, $actual );
	}

	/**
	 * WooPay button is available in cart and checkout while logged out.
	 *
	 * @return void
	 */
	public function test_should_enable_woopay_on_guest_checkout_logged_out() {
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		wp_set_current_user( 0 );

		$woopay_utilities = new WooPay_Utilities();

		$this->assertTrue( $woopay_utilities->should_enable_woopay_on_guest_checkout() );
		$this->clean_up_should_enable_woopay_tests();
	}

	/**
	 * WooPay button is available in cart and checkout while logged in.
	 *
	 * @return void
	 */
	public function test_should_enable_woopay_on_guest_checkout_logged_in_on_cart_or_checkout() {
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		wp_set_current_user( 1 );

		$woopay_utilities = new WooPay_Utilities();

		$this->assertTrue( $woopay_utilities->should_enable_woopay_on_guest_checkout() );
		$this->clean_up_should_enable_woopay_tests();
	}

	/**
	 * WooPay button is NOT available in cart and checkout while logged out and has subscription.
	 *
	 * @return void
	 */
	public function test_should_enable_woopay_on_guest_checkout_logged_out_has_subscription() {
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		wp_set_current_user( 0 );
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$woopay_utilities = new WooPay_Utilities();

		$this->assertFalse( $woopay_utilities->should_enable_woopay_on_guest_checkout() );
		$this->clean_up_should_enable_woopay_tests();
	}

	/**
	 * WooPay button is available in cart and checkout while logged in and has subscription.
	 *
	 * @return void
	 */
	public function test_should_enable_woopay_on_guest_checkout_logged_in_has_subscription() {
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		wp_set_current_user( 1 );
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$woopay_utilities = new WooPay_Utilities();

		$this->assertTrue( $woopay_utilities->should_enable_woopay_on_guest_checkout() );
		$this->clean_up_should_enable_woopay_tests();
	}

	/**
	 * WooPay button is NOT available in cart and checkout while logged out and guest checkout is disabled.
	 *
	 * @return void
	 */
	public function test_should_enable_woopay_on_guest_checkout_logged_out_guest_checkout_disabled() {
		add_filter( 'woocommerce_is_checkout', '__return_true' );
		wp_set_current_user( 0 );
		update_option( 'woocommerce_enable_guest_checkout', 'no' );

		$woopay_utilities = new WooPay_Utilities();

		$this->assertFalse( $woopay_utilities->should_enable_woopay_on_guest_checkout() );
		$this->clean_up_should_enable_woopay_tests();
	}

	/**
	 * WooPay user is saved to platform on classic checkout.
	 *
	 * @return void
	 */
	public function test_should_save_platform_customer_in_classic_checkout() {
		$woopay_utilities = new WooPay_Utilities();

		$_POST['save_user_in_woopay'] = 'true';
		$this->assertTrue( $woopay_utilities->should_save_platform_customer() );
		unset( $_POST['save_user_in_woopay'] );
	}

	/**
	 * WooPay should be enabled for guest checkout when user is logged in.
	 *
	 * @return void
	 */
	public function test_should_enable_woopay_on_guest_checkout_logged_in() {
		wp_set_current_user( 1 );

		$woopay_utilities = new WooPay_Utilities();

		$this->assertTrue( $woopay_utilities->should_enable_woopay_on_guest_checkout() );
	}

	/**
	 * WooPay should be enabled for guest checkout when user is not logged in and guest checkout is enabled.
	 *
	 * @return void
	 */
	public function test_should_enable_woopay_on_guest_checkout_logged_out_guest_enabled() {
		wp_set_current_user( 0 );
		add_filter(
			'pre_option_woocommerce_enable_guest_checkout',
			function () {
				return 'yes';
			}
		);

		$woopay_utilities = new WooPay_Utilities();

		$this->assertTrue( $woopay_utilities->should_enable_woopay_on_guest_checkout() );

		remove_all_filters( 'pre_option_woocommerce_enable_guest_checkout' );
	}

	/**
	 * WooPay should be disabled for guest checkout when user is not logged in and guest checkout is disabled.
	 *
	 * @return void
	 */
	public function test_should_enable_woopay_on_guest_checkout_logged_out_guest_disabled() {
		wp_set_current_user( 0 );
		add_filter(
			'pre_option_woocommerce_enable_guest_checkout',
			function () {
				return 'no';
			}
		);

		$woopay_utilities = new WooPay_Utilities();

		$this->assertFalse( $woopay_utilities->should_enable_woopay_on_guest_checkout() );

		remove_all_filters( 'pre_option_woocommerce_enable_guest_checkout' );
	}

	/**
	 * WooPay should be disabled for guest checkout when user is not logged in and cart contains subscription.
	 *
	 * @return void
	 */
	public function test_should_enable_woopay_on_guest_checkout_logged_out_has_subscription_with_enable_guest_checkout_enabled() {
		wp_set_current_user( 0 );
		add_filter(
			'pre_option_woocommerce_enable_guest_checkout',
			function () {
				return 'yes';
			}
		);

		WC_Subscriptions_Cart::set_cart_contains_subscription( true );

		$woopay_utilities = new WooPay_Utilities();

		$this->assertFalse( $woopay_utilities->should_enable_woopay_on_guest_checkout() );

		remove_all_filters( 'pre_option_woocommerce_enable_guest_checkout' );
	}

	private function clean_up_should_enable_woopay_tests() {
		remove_filter( 'woocommerce_is_checkout', '__return_true' );
		wp_set_current_user( 0 );
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );
	}

	/**
	 * Cache account details.
	 *
	 * @param $account
	 */
	private function set_is_woopay_eligible( $is_woopay_eligible ) {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_woopay_eligible ] );
	}

	public function test_encrypt_and_sign_data_returns_nothing_without_a_blog_token() {
		Jetpack_Options::update_option( 'blog_token', '' );

		$this->assertSame(
			[],
			WooPay_Utilities::encrypt_and_sign_data( [ 'wcpay_version' => '1.0.0' ] )
		);
	}

	public function test_encrypt_and_sign_data_produces_a_payload_the_receiver_can_open() {
		$token = 'test.blog.token';

		Jetpack_Options::update_option( 'blog_token', $token );

		$session = [
			'wcpay_version' => '1.0.0',
			'blog_id'       => 123,
		];

		$encrypted = WooPay_Utilities::encrypt_and_sign_data( $session );

		$this->assertArrayHasKey( 'data', $encrypted );

		$parts = array_map( 'base64_decode', $encrypted['data'] );

		// The key WooPay derives to open this direction.
		$session_key = hash_hkdf( 'sha256', $token, 32, WooPay_Utilities::SESSION_KEY_PURPOSE );

		// Checked the way the receiver checks it, so this fails if either side of the
		// contract moves. The HMAC has to cover the IV as well as the ciphertext.
		$this->assertSame(
			hash_hmac( 'sha256', $parts['iv'] . $parts['session'], $session_key ),
			$parts['hash']
		);

		// And specifically not the older form this direction has moved off.
		$this->assertNotSame(
			hash_hmac( 'sha256', $parts['session'], $session_key ),
			$parts['hash']
		);

		// Nor sealed with the blog token itself, which is what keeps this payload
		// distinct from an inbound attestation rather than relying on field names.
		$this->assertNotSame(
			hash_hmac( 'sha256', $parts['iv'] . $parts['session'], $token ),
			$parts['hash']
		);

		$this->assertSame(
			$session,
			json_decode(
				openssl_decrypt(
					$parts['session'],
					'aes-256-cbc',
					$session_key,
					OPENSSL_RAW_DATA,
					$parts['iv']
				),
				true
			)
		);
	}

	/**
	 * Each direction stands on its own key, so a payload this store sealed for WooPay is
	 * not a payload WooPay sealed for this store, whatever its fields are called.
	 */
	public function test_a_payload_this_store_sealed_is_not_a_valid_attestation() {
		Jetpack_Options::update_option( 'blog_token', 'test.blog.token' );

		$encrypted = WooPay_Utilities::encrypt_and_sign_data( [ 'blog_id' => 123 ] );

		$replayed = [
			'data' => $encrypted['data']['session'],
			'iv'   => $encrypted['data']['iv'],
			'hash' => $encrypted['data']['hash'],
		];

		$this->assertNull( WooPay_Utilities::decrypt_signed_data( $replayed, [ WooPay_Utilities::ATTESTATION_KEY_PURPOSE ] ) );
	}

	public function test_an_envelope_woopay_sealed_for_this_store_still_opens() {
		$token = 'test.blog.token';

		Jetpack_Options::update_option( 'blog_token', $token );

		$payload = [
			'timestamp'  => time(),
			'user_email' => 'shopper@example.com',
		];

		$this->assertSame(
			$payload,
			WooPay_Utilities::decrypt_signed_data(
				$this->seal_for_store( $payload, hash_hkdf( 'sha256', $token, 32, WooPay_Utilities::ATTESTATION_KEY_PURPOSE ) ),
				[ WooPay_Utilities::ATTESTATION_KEY_PURPOSE ]
			)
		);
	}

	/**
	 * WooPay releases that predate the derived key seal with the blog token itself, and
	 * so does the connect exchange, for every merchant. Both have to keep opening until
	 * the follow-up removes the fallback.
	 */
	public function test_an_envelope_sealed_with_the_undifferentiated_blog_token_still_opens() {
		$token = 'test.blog.token';

		Jetpack_Options::update_option( 'blog_token', $token );

		$payload = [ 'user_email' => 'shopper@example.com' ];

		$this->assertSame( $payload, WooPay_Utilities::decrypt_signed_data( $this->seal_for_store( $payload, $token ), [ WooPay_Utilities::CONNECT_KEY_PURPOSE, null ] ) );
	}

	/**
	 * The half that has to ship first. WooPay still seals the connect exchange with the
	 * undifferentiated blog token, and cannot stop until every store accepts a derived key --
	 * the connect iframe serves stores on every release and nothing on that request says
	 * which one it is talking to. Accepting it now is what makes that switch a one-line
	 * change with no window where a store cannot open what it is sent.
	 */
	public function test_a_connect_envelope_sealed_with_the_derived_key_opens() {
		$token = 'test.blog.token';

		Jetpack_Options::update_option( 'blog_token', $token );

		$payload = [ 'user_email' => 'shopper@example.com' ];

		$this->assertSame(
			$payload,
			WooPay_Utilities::decrypt_signed_data(
				$this->seal_for_store( $payload, hash_hkdf( 'sha256', $token, 32, WooPay_Utilities::CONNECT_KEY_PURPOSE ) ),
				[ WooPay_Utilities::CONNECT_KEY_PURPOSE, null ]
			)
		);
	}

	/**
	 * Naming the accepted keys per call site also narrows what each one takes. An attestation
	 * is sealed for the session route and says who a shopper is; the connect exchange has no
	 * business opening one, and before the keys were listed explicitly it would have.
	 */
	public function test_an_attestation_is_not_accepted_as_connect_data() {
		$token = 'test.blog.token';

		Jetpack_Options::update_option( 'blog_token', $token );

		$attestation = $this->seal_for_store(
			[
				'timestamp'  => time(),
				'user_email' => 'shopper@example.com',
			],
			hash_hkdf( 'sha256', $token, 32, WooPay_Utilities::ATTESTATION_KEY_PURPOSE )
		);

		$this->assertNull( WooPay_Utilities::decrypt_signed_data( $attestation, [ WooPay_Utilities::CONNECT_KEY_PURPOSE, null ] ) );
	}

	public function test_an_envelope_sealed_with_the_wrong_key_does_not_open() {
		Jetpack_Options::update_option( 'blog_token', 'test.blog.token' );

		$this->assertNull(
			WooPay_Utilities::decrypt_signed_data( $this->seal_for_store( [ 'timestamp' => time() ], 'not.the.blog.token' ), [ WooPay_Utilities::ATTESTATION_KEY_PURPOSE ] )
		);
	}

	/**
	 * Seals a payload the way WooPay's encrypt_data() does.
	 *
	 * @param array  $payload The payload to seal.
	 * @param string $key     The key to seal it with.
	 *
	 * @return array The envelope, in the shape decrypt_signed_data() reads.
	 */
	private function seal_for_store( array $payload, string $key ): array {
		$iv         = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$ciphertext = openssl_encrypt( wp_json_encode( $payload ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return array_map(
			'base64_encode',
			[
				'data' => $ciphertext,
				'iv'   => $iv,
				'hash' => hash_hmac( 'sha256', $iv . $ciphertext, $key ),
			]
		);
	}
}

<?php
/**
 * Class WC_REST_WooPay_Session_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Platform_Checkout\WooPay_Store_Api_Token;
use WCPay\WooPay\WooPay_Session;
use WCPay\WooPay\WooPay_Utilities;

/**
 * WC_REST_WooPay_Session_Controller unit tests.
 *
 * This route carries the store's own session material and creates a Stripe customer as a
 * side effect, so it must stay closed to callers who cannot prove they are WooPay — even
 * while proxied Store API traffic moves to Cart-Token authorization. See WOOPAY-463.
 */
class WC_REST_WooPay_Session_Controller_Test extends WCPAY_UnitTestCase {

	const BLOG_TOKEN = 'test.blog.token';

	/**
	 * The system under test.
	 *
	 * @var WC_REST_WooPay_Session_Controller
	 */
	private $controller;

	public function set_up() {
		parent::set_up();

		$this->controller = new WC_REST_WooPay_Session_Controller();

		Jetpack_Options::update_option( 'blog_token', self::BLOG_TOKEN );

		$_SERVER['HTTP_USER_AGENT'] = 'WooPay';
	}

	public function tear_down() {
		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );
		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_false' );
		remove_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_true' );
		remove_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_false' );

		unset(
			$_SERVER['HTTP_USER_AGENT'],
			$_SERVER['HTTP_CART_TOKEN'],
			$_GET['encrypted_data'],
			$_POST['encrypted_data']
		);

		parent::tear_down();
	}

	public function test_permission_is_granted_for_a_signed_woopay_request() {
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		$this->assertTrue( $this->controller->check_permission() );
	}

	public function test_permission_is_granted_for_an_attested_email_without_a_signature() {
		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		$this->assertTrue( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_an_unauthenticated_request() {
		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_a_cart_token_only_request() {
		add_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_true' );

		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();

		// A Cart-Token authorizes proxied Store API traffic, but never this route: any
		// visitor can obtain one for their own cart, so it establishes too little here.
		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_an_envelope_sealed_with_the_wrong_key() {
		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com', null, 'not.the.blog.token' );

		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_a_stale_envelope() {
		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com', time() - 3600 );

		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_when_both_levers_are_off() {
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_false' );
		add_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_false' );

		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		// Before 10.10.0 the signature filter alone closed this route. It takes both now,
		// so this pins the documented replacement for that kill switch.
		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_suppressing_the_signature_alone_still_admits_an_attestation() {
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_false' );

		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		// The narrowing is deliberate: the filter answers whether the request was signed,
		// and a false there is indistinguishable from an ordinary unsigned request — which
		// is the case the attestation exists to serve. Changing this should be a decision,
		// not an accident.
		$this->assertTrue( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_a_replayed_envelope() {
		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		$this->assertTrue( $this->controller->check_permission() );

		// The envelope rides in the query string, so it reaches access logs and browser
		// history. Once delivered it has to be worthless to whoever reads it there.
		$this->reset_resolved_attestations();

		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_an_attested_email_when_opted_out() {
		add_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_false' );

		$_GET['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		// The opt-out covers both channels. A store that answers no has WooPay signing
		// again, so a perfectly good envelope buys nothing here — otherwise the filter
		// would close proxied traffic while leaving this route open.
		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_granted_for_a_signed_request_when_opted_out() {
		add_filter( 'wcpay_woopay_allow_cart_token_auth', '__return_false' );
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		// Opting out means falling back to the signature, so it has to keep working —
		// otherwise the escape hatch closes the route outright.
		$this->assertTrue( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_when_the_user_agent_is_not_woopay() {
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';

		$this->assertFalse( $this->controller->check_permission() );
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
	 * Seals an envelope the way WooPay does, so these tests fail if the two ends drift.
	 *
	 * Note the shape `decrypt_signed_data()` expects is not the one `encrypt_and_sign_data()`
	 * produces — the two directions are separate protocols. This mirrors the decrypt side.
	 *
	 * @param string      $email     The email to attest to.
	 * @param int|null    $timestamp Envelope timestamp. Defaults to now.
	 * @param string|null $key       Key to seal with. Defaults to the store blog token.
	 *
	 * @return array The base64-encoded envelope.
	 */
	private function build_envelope( string $email, ?int $timestamp = null, ?string $key = null ): array {
		$key     = $key ?? self::BLOG_TOKEN;
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
}

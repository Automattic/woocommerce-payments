<?php
/**
 * Class WC_REST_WooPay_Session_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Platform_Checkout\WooPay_Store_Api_Token;
use WCPay\WooPay\WooPay_Session;

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
		unset(
			$_SERVER['HTTP_USER_AGENT'],
			$_SERVER['HTTP_CART_TOKEN'],
			$_GET[ WooPay_Session::ATTESTATION_PARAM ],
			$_POST[ WooPay_Session::ATTESTATION_PARAM ],
			$_POST['encrypted_data']
		);

		parent::tear_down();
	}

	public function test_permission_is_granted_for_an_attested_email() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );

		$this->assertTrue( $this->controller->check_permission() );
	}

	public function test_permission_is_granted_for_a_guest_envelope_naming_no_email() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope();

		// A guest shopper has no account to name, so the envelope carries no email. It
		// still proves WooPay composed the request, which is what this route asks for.
		$this->assertTrue( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_an_unauthenticated_request() {
		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_a_cart_token_only_request() {
		$_SERVER['HTTP_CART_TOKEN'] = WooPay_Store_Api_Token::init()->get_cart_token();

		// A Cart-Token authorizes proxied Store API traffic, but never this route: any
		// visitor can obtain one for their own cart, so it establishes too little here.
		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_an_envelope_sent_in_the_query_string() {
		$_GET[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );

		// The envelope travels in a POST body so it stays out of access logs, browser
		// history and Referer headers — a property the sender provides and the receiver
		// would hand straight back by reading the query string too. It is this store's own
		// access log that would record valid envelopes. WooPay only ever POSTs, so nothing
		// legitimate is turned away here.
		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_an_envelope_under_the_old_parameter() {
		$_POST['encrypted_data'] = $this->build_envelope( 'shopper@example.com' );

		// `encrypted_data` is an older exchange that get_user_email() reads, with a
		// different payload and different rules. Giving the attestation its own name is
		// what stops one being mistaken for the other.
		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_an_envelope_sealed_with_the_wrong_key() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com', null, 'not.the.blog.token' );

		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_a_stale_envelope() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com', time() - 3600 );

		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_for_a_replayed_envelope() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );

		$this->assertTrue( $this->controller->check_permission() );

		// The body keeps the envelope out of logs and history, but not out of anything
		// that saw the request itself. Once delivered it has to be worthless to replay.
		$this->reset_resolved_attestations();

		$this->assertFalse( $this->controller->check_permission() );
	}

	public function test_permission_is_denied_when_the_user_agent_is_not_woopay() {
		$_POST[ WooPay_Session::ATTESTATION_PARAM ] = $this->build_envelope( 'shopper@example.com' );

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
	 * @param string|null $email     The email to attest to, or null for a guest shopper.
	 * @param int|null    $timestamp Envelope timestamp. Defaults to now.
	 * @param string|null $key       Key to seal with. Defaults to the store blog token.
	 *
	 * @return array The base64-encoded envelope.
	 */
	private function build_envelope( ?string $email = null, ?int $timestamp = null, ?string $key = null ): array {
		$key     = $key ?? self::BLOG_TOKEN;
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
}

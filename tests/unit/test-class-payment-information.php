<?php
/**
 * Class Payment_Information_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Payment_Information;
use WCPay\Constants\Payment_Type;
use WCPay\Constants\Payment_Initiated_By;
use WCPay\Constants\Payment_Capture_Type;
use WCPay\WooPay\WooPay_Session;
use WCPay\WooPay\WooPay_Utilities;

/**
 * Payment_Information unit tests.
 */
class Payment_Information_Test extends WCPAY_UnitTestCase {
	const PAYMENT_METHOD_REQUEST_KEY     = 'wcpay-payment-method';
	const CONFIRMATION_TOKEN_REQUEST_KEY = 'wcpay-confirmation-token';
	const PAYMENT_METHOD                 = 'pm_mock';
	const CONFIRMATION_TOKEN             = 'ctoken_mock';
	const CARD_TOKEN_REQUEST_KEY         = 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token';
	const TOKEN                          = 'pm_mock_token';
	const CART_TOKEN                     = 'the.cart.token';
	const BLOG_TOKEN                     = 'test.blog.token';

	/**
	 * WC token to be used in tests.
	 * @var WC_Payment_Token_CC
	 */
	private $card_token;

	/**
	 * Snapshot of $_SERVER, restored on tear down.
	 * @var array
	 */
	private $original_server;

	/**
	 * Blog token before the test, restored on tear down.
	 * @var mixed
	 */
	private $original_blog_token;

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'customer' ] ) );
		$this->card_token = WC_Helper_Token::create_token( self::TOKEN );

		$this->original_server     = $_SERVER;
		$this->original_blog_token = Jetpack_Options::get_option( 'blog_token' );
	}

	public function tear_down() {
		$_SERVER = $this->original_server;
		Jetpack_Options::update_option( 'blog_token', $this->original_blog_token );
		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );
		WC_Payments::set_database_cache( new WCPay\Database_Cache() );

		parent::tear_down();
	}

	public function test_requires_payment_method_or_token() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.' );

		new Payment_Information( '' );
	}

	public function test_is_merchant_initiated_defaults_to_false() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), null );
		$this->assertFalse( $payment_information->is_merchant_initiated() );
	}

	public function test_is_merchant_initiated_returns_true_when_payment_initiated_by_merchant() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), null, Payment_Initiated_By::MERCHANT() );
		$this->assertTrue( $payment_information->is_merchant_initiated() );
	}

	public function test_is_merchant_initiated_returns_false_when_payment_initiated_by_customer() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), null, Payment_Initiated_By::CUSTOMER() );
		$this->assertFalse( $payment_information->is_merchant_initiated() );
	}

	public function test_is_using_manual_capture_defaults_to_false() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), null );
		$this->assertFalse( $payment_information->is_using_manual_capture() );
	}

	public function test_is_using_manual_capture_returns_true_when_set_to_manual_capture() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), null, null, Payment_Capture_Type::MANUAL() );
		$this->assertTrue( $payment_information->is_using_manual_capture() );
	}

	public function test_is_using_manual_capture_returns_false_when_set_to_automatic_capture() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), null, null, Payment_Capture_Type::AUTOMATIC() );
		$this->assertFalse( $payment_information->is_using_manual_capture() );
	}

	public function test_get_payment_method_returns_payment_method() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), null );
		$this->assertEquals( self::PAYMENT_METHOD, $payment_information->get_payment_method() );
	}

	public function test_get_payment_method_returns_token_if_present() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), $this->card_token );
		$this->assertEquals( self::TOKEN, $payment_information->get_payment_method() );
	}

	public function test_is_using_confirmation_token_returns_true_for_confirmation_token() {
		$payment_information = new Payment_Information( self::CONFIRMATION_TOKEN );
		$this->assertTrue( $payment_information->is_using_confirmation_token() );
	}

	public function test_is_using_confirmation_token_returns_false_for_payment_method() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD );
		$this->assertFalse( $payment_information->is_using_confirmation_token() );
	}

	public function test_get_payment_token_returns_token() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), $this->card_token );
		$this->assertEquals( $this->card_token, $payment_information->get_payment_token() );
	}

	public function test_set_token_updates_token() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD );
		$this->assertFalse( $payment_information->is_using_saved_payment_method() );

		$payment_information->set_token( $this->card_token );
		$this->assertEquals( $this->card_token, $payment_information->get_payment_token() );
		$this->assertTrue( $payment_information->is_using_saved_payment_method() );
	}

	public function test_get_customer_id() {
		$expected_customer_id = 'old_customer_id';
		$payment_information  = new Payment_Information(
			self::PAYMENT_METHOD,
			null,
			Payment_Type::SINGLE(),
			$this->card_token,
			null,
			null,
			null,
			'',
			null,
			$expected_customer_id
		);
		$this->assertEquals( $expected_customer_id, $payment_information->get_customer_id() );
	}

	public function test_get_payment_method_from_request() {
		$payment_method = Payment_Information::get_payment_method_from_request(
			[ self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD ]
		);
		$this->assertEquals( self::PAYMENT_METHOD, $payment_method );
	}

	public function test_get_payment_method_from_request_with_confirmation_token() {
		$payment_method = Payment_Information::get_payment_method_from_request(
			[ self::CONFIRMATION_TOKEN_REQUEST_KEY => self::CONFIRMATION_TOKEN ]
		);
		$this->assertEquals( self::CONFIRMATION_TOKEN, $payment_method );
	}

	public function test_get_payment_method_from_request_prefers_confirmation_token_over_payment_method() {
		$payment_method = Payment_Information::get_payment_method_from_request(
			[
				self::CONFIRMATION_TOKEN_REQUEST_KEY => self::CONFIRMATION_TOKEN,
				self::PAYMENT_METHOD_REQUEST_KEY     => self::PAYMENT_METHOD,
			]
		);
		$this->assertEquals( self::CONFIRMATION_TOKEN, $payment_method );
	}

	public function test_get_token_from_request_returns_null_when_not_set() {
		$token = Payment_Information::get_token_from_request( [] );
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_when_new() {
		$token = Payment_Information::get_token_from_request(
			[
				'payment_method'             => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::CARD_TOKEN_REQUEST_KEY => 'new',
			]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_when_invalid() {
		$token = Payment_Information::get_token_from_request(
			[
				'payment_method'             => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::CARD_TOKEN_REQUEST_KEY => $this->card_token->get_id() + 1,
			]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_when_wrong_gateway() {
		$this->card_token->set_gateway_id( 'wrong_gateway' );
		$this->card_token->save();
		$token = Payment_Information::get_token_from_request(
			[
				'payment_method'             => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::CARD_TOKEN_REQUEST_KEY => $this->card_token->get_id(),
			]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_when_wrong_customer() {
		$this->card_token->set_user_id( get_current_user_id() + 1 );
		$this->card_token->save();
		$token = Payment_Information::get_token_from_request(
			[
				'payment_method'             => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::CARD_TOKEN_REQUEST_KEY => $this->card_token->get_id(),
			]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_for_ownerless_token_when_logged_out() {
		wp_set_current_user( 0 );
		$ownerless_token = WC_Helper_Token::create_token( 'pm_ownerless', 0 );

		$token = Payment_Information::get_token_from_request(
			[
				'payment_method'             => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::CARD_TOKEN_REQUEST_KEY => $ownerless_token->get_id(),
			]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_for_ownerless_token_when_logged_in() {
		$ownerless_token = WC_Helper_Token::create_token( 'pm_ownerless', 0 );

		$token = Payment_Information::get_token_from_request(
			[
				'payment_method'             => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::CARD_TOKEN_REQUEST_KEY => $ownerless_token->get_id(),
			]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_for_owned_token_when_logged_out() {
		wp_set_current_user( 0 );

		$token = Payment_Information::get_token_from_request(
			[
				'payment_method'             => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::CARD_TOKEN_REQUEST_KEY => $this->card_token->get_id(),
			]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_when_missing_payment_method() {
		$token = Payment_Information::get_token_from_request(
			[ self::CARD_TOKEN_REQUEST_KEY => $this->card_token->get_id() + 1 ]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_token() {
		$token = Payment_Information::get_token_from_request(
			[
				'payment_method'             => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::CARD_TOKEN_REQUEST_KEY => $this->card_token->get_id(),
			]
		);
		$this->assertEquals( $this->card_token, $token );
	}

	public function test_from_payment_request_with_token() {
		$payment_information = Payment_Information::from_payment_request(
			[
				'payment_method'                 => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD,
				self::CARD_TOKEN_REQUEST_KEY     => $this->card_token->get_id(),
			],
			null,
			Payment_Type::SINGLE(),
			Payment_Initiated_By::MERCHANT()
		);
		$this->assertEquals( self::TOKEN, $payment_information->get_payment_method() );
		$this->assertTrue( $payment_information->is_using_saved_payment_method() );
		$this->assertEquals( $this->card_token, $payment_information->get_payment_token() );
		$this->assertTrue( $payment_information->is_merchant_initiated() );
		$this->assertNull( $payment_information->get_error() );
	}

	public function test_from_payment_request_rejects_ownerless_token_when_logged_out() {
		wp_set_current_user( 0 );
		$ownerless_token = WC_Helper_Token::create_token( 'pm_ownerless', 0 );

		$this->expectException( \WCPay\Exceptions\Invalid_Payment_Method_Exception::class );

		Payment_Information::from_payment_request(
			[
				'payment_method'             => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::CARD_TOKEN_REQUEST_KEY => $ownerless_token->get_id(),
			]
		);
	}

	public function test_from_payment_request_ignores_ownerless_token_and_uses_new_payment_method_when_logged_out() {
		wp_set_current_user( 0 );
		$ownerless_token = WC_Helper_Token::create_token( 'pm_ownerless', 0 );

		$payment_information = Payment_Information::from_payment_request(
			[
				'payment_method'                 => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD,
				self::CARD_TOKEN_REQUEST_KEY     => $ownerless_token->get_id(),
			]
		);
		$this->assertFalse( $payment_information->is_using_saved_payment_method() );
		$this->assertNull( $payment_information->get_payment_token() );
		$this->assertEquals( 'pm_mock', $payment_information->get_payment_method() );
	}

	public function test_from_payment_request_without_token() {
		$payment_information = Payment_Information::from_payment_request(
			[ self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD ]
		);
		$this->assertEquals( self::PAYMENT_METHOD, $payment_information->get_payment_method() );
		$this->assertFalse( $payment_information->is_using_saved_payment_method() );
		$this->assertFalse( $payment_information->is_merchant_initiated() );
		$this->assertNull( $payment_information->get_error() );
	}

	public function test_from_payment_request_with_error() {
		$payment_information = Payment_Information::from_payment_request(
			[
				'payment_method'                     => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::PAYMENT_METHOD_REQUEST_KEY     => Payment_Information::PAYMENT_METHOD_ERROR,
				'wcpay-payment-method-error-message' => 'Invalid Card',
				'wcpay-payment-method-error-code'    => 'invalid_card',
			]
		);
		$this->assertEquals( Payment_Information::PAYMENT_METHOD_ERROR, $payment_information->get_payment_method() );
		$this->assertFalse( $payment_information->is_using_saved_payment_method() );
		$this->assertFalse( $payment_information->is_merchant_initiated() );

		$error = $payment_information->get_error();
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertEquals( 'Invalid Card', $error->get_error_message() );
		$this->assertEquals( 'invalid_card', $error->get_error_code() );
	}

	public function test_from_payment_request_with_error_no_details() {
		$payment_information = Payment_Information::from_payment_request(
			[
				'payment_method'                 => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::PAYMENT_METHOD_REQUEST_KEY => Payment_Information::PAYMENT_METHOD_ERROR,
			]
		);
		$this->assertEquals( Payment_Information::PAYMENT_METHOD_ERROR, $payment_information->get_payment_method() );
		$this->assertFalse( $payment_information->is_using_saved_payment_method() );
		$this->assertFalse( $payment_information->is_merchant_initiated() );

		$error = $payment_information->get_error();
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertEquals( "We're not able to process this payment. Please try again later.", $error->get_error_message() );
		$this->assertEquals( 'unknown-error', $error->get_error_code() );
	}

	public function test_from_payment_request_with_error_empty_message() {
		$payment_information = Payment_Information::from_payment_request(
			[
				'payment_method'                     => WC_Payment_Gateway_WCPay::GATEWAY_ID,
				self::PAYMENT_METHOD_REQUEST_KEY     => Payment_Information::PAYMENT_METHOD_ERROR,
				'wcpay-payment-method-error-message' => '',
				'wcpay-payment-method-error-code'    => 'unknown-error',
			]
		);

		$error = $payment_information->get_error();
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertEquals( "We're not able to process this payment. Please try again later.", $error->get_error_message() );
	}

	public function test_get_cvc_confirmation_from_request_returns_null_if_payment_method_is_empty() {
		$cvc_confirmation = Payment_Information::get_cvc_confirmation_from_request( [ 'payment_method' => null ] );
		$this->assertEquals( null, $cvc_confirmation );
	}

	public function test_get_fingerprint_from_request() {
		$fingerprint = '123abc';
		$result      = Payment_Information::get_fingerprint_from_request( [ 'wcpay-fingerprint' => $fingerprint ] );
		$this->assertEquals( $fingerprint, $result );
	}

	public function test_get_fingerprint_from_request_returns_empty_string_if_invalid() {
		$result = Payment_Information::get_fingerprint_from_request( [ 'wcpay-fingerprint' => [] ] );
		$this->assertEquals( '', $result );
	}

	public function test_get_fingerprint_from_request_returns_empty_string_if_empty() {
		$result = Payment_Information::get_fingerprint_from_request( [] );
		$this->assertEquals( '', $result );
	}

	public function test_from_payment_request_does_not_tag_order_as_woopay_without_proof() {
		$order = WC_Helper_Order::create_order();

		Payment_Information::from_payment_request(
			[
				self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD,
				'is_woopay'                      => '1',
			],
			$order
		);

		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( 'is_woopay' ) );
	}

	public function test_from_payment_request_tags_order_as_woopay_when_vouched_by_woopay() {
		$order = WC_Helper_Order::create_order();
		$this->set_woopay_vouch_header();

		Payment_Information::from_payment_request(
			[
				self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD,
				'is_woopay'                      => '1',
			],
			$order
		);

		$this->assertTrue( (bool) wc_get_order( $order->get_id() )->get_meta( 'is_woopay' ) );
	}

	public function test_from_payment_request_tags_order_as_woopay_when_signed_with_blog_token() {
		$order = WC_Helper_Order::create_order();
		$cache = $this->createMock( WCPay\Database_Cache::class );
		$cache->method( 'get' )->willReturn( [ 'platform_woopay_force_signed_requests' => true ] );
		WC_Payments::set_database_cache( $cache );
		add_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_true' );

		Payment_Information::from_payment_request(
			[
				self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD,
				'is_woopay'                      => '1',
			],
			$order
		);

		$this->assertTrue( (bool) wc_get_order( $order->get_id() )->get_meta( 'is_woopay' ) );
	}

	public function test_from_payment_request_with_woopay_flag_and_no_order() {
		$payment_information = Payment_Information::from_payment_request(
			[
				self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD,
				'is_woopay'                      => '1',
			]
		);

		$this->assertSame( 'pm_mock', $payment_information->get_payment_method() );
	}

	/**
	 * Puts a WooPay vouch on the current request, sealed for the cart it carries.
	 */
	private function set_woopay_vouch_header(): void {
		Jetpack_Options::update_option( 'blog_token', self::BLOG_TOKEN );
		$_SERVER['HTTP_CART_TOKEN'] = self::CART_TOKEN;

		$key        = WooPay_Utilities::derive_key_for( WooPay_Utilities::VOUCH_KEY_PURPOSE );
		$iv         = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$plaintext  = wp_json_encode(
			[
				'timestamp'  => time(),
				'cart_token' => hash( 'sha256', self::CART_TOKEN ),
			]
		);
		$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		$envelope   = array_map(
			'base64_encode',
			[
				'data' => $ciphertext,
				'iv'   => $iv,
				'hash' => hash_hmac( 'sha256', $iv . $ciphertext, $key ),
			]
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$_SERVER[ WooPay_Session::VOUCH_HEADER ] = base64_encode( wp_json_encode( $envelope ) );
	}
}

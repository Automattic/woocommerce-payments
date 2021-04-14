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
use WCPay\Payment_Method\Sepa;

/**
 * Payment_Information unit tests.
 */
class Payment_Information_Test extends WP_UnitTestCase {
	const PAYMENT_METHOD_REQUEST_KEY = 'wcpay-payment-method';
	const PAYMENT_METHOD             = 'pm_mock';
	const TOKEN_REQUEST_KEY          = 'wc-' . \WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token';
	const SEPA_TOKEN_REQUEST_KEY     = 'wc-' . Sepa::GATEWAY_ID . '-payment-token';
	const TOKEN                      = 'pm_mock_token';

	/**
	 * WC token to be used in tests.
	 * @var WC_Payment_Token_CC
	 */
	private $token;

	/**
	 * WC SEPA token to be used in tests.
	 * @var WC_Payment_Token_Sepa
	 */
	private $sepa_token;

	public function setUp() {
		parent::setUp();

		$this->token      = WC_Helper_Token::create_token( self::TOKEN );
		$this->sepa_token = WC_Helper_Token::create_sepa_token( self::TOKEN );
	}

	public function test_requires_payment_method_or_token() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid payment method. Please input a new card number.' );

		$payment_information = new Payment_Information( '' );
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
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), $this->token );
		$this->assertEquals( self::TOKEN, $payment_information->get_payment_method() );
	}

	public function test_get_payment_token_returns_token() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), $this->token );
		$this->assertEquals( $this->token, $payment_information->get_payment_token() );
	}

	public function is_using_saved_payment_method_returns_true_if_token() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD, null, Payment_Type::SINGLE(), $this->token );
		$this->assertTrue( $payment_information->is_using_saved_payment_method() );
	}

	public function test_set_token_updates_token() {
		$payment_information = new Payment_Information( self::PAYMENT_METHOD );
		$this->assertFalse( $payment_information->is_using_saved_payment_method() );

		$payment_information->set_token( $this->token );
		$this->assertEquals( $this->token, $payment_information->get_payment_token() );
		$this->assertTrue( $payment_information->is_using_saved_payment_method() );
	}

	public function test_get_payment_method_from_request() {
		$payment_method = Payment_Information::get_payment_method_from_request(
			[ self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD ]
		);
		$this->assertEquals( self::PAYMENT_METHOD, $payment_method );
	}

	public function test_get_token_from_request_returns_null_when_not_set() {
		$token = Payment_Information::get_token_from_request( [] );
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_when_new() {
		$token = Payment_Information::get_token_from_request(
			[ self::TOKEN_REQUEST_KEY => 'new' ]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_when_invalid() {
		$token = Payment_Information::get_token_from_request(
			[ self::TOKEN_REQUEST_KEY => $this->token->get_id() + 1 ]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_when_wrong_gateway() {
		$this->token->set_gateway_id( 'wrong_gateway' );
		$this->token->save();
		$token = Payment_Information::get_token_from_request(
			[ self::TOKEN_REQUEST_KEY => $this->token->get_id() ]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_null_when_wrong_customer() {
		$this->token->set_user_id( get_current_user_id() + 1 );
		$this->token->save();
		$token = Payment_Information::get_token_from_request(
			[ self::TOKEN_REQUEST_KEY => $this->token->get_id() ]
		);
		$this->assertNull( $token );
	}

	public function test_get_token_from_request_returns_token() {
		$token = Payment_Information::get_token_from_request(
			[ self::TOKEN_REQUEST_KEY => $this->token->get_id() ]
		);
		$this->assertEquals( $this->token, $token );
	}

	public function test_get_token_from_request_sepa_gateway() {
		$token = Payment_Information::get_token_from_request(
			[
				'payment_method'             => Sepa::GATEWAY_ID,
				self::SEPA_TOKEN_REQUEST_KEY => $this->sepa_token->get_id(),
			]
		);
		$this->assertEquals( $this->sepa_token, $token );
	}

	public function test_from_payment_request_with_token() {
		$payment_information = Payment_Information::from_payment_request(
			[
				self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD,
				self::TOKEN_REQUEST_KEY          => $this->token->get_id(),
			],
			null,
			Payment_Type::SINGLE(),
			Payment_Initiated_By::MERCHANT()
		);
		$this->assertEquals( self::TOKEN, $payment_information->get_payment_method() );
		$this->assertTrue( $payment_information->is_using_saved_payment_method() );
		$this->assertEquals( $this->token, $payment_information->get_payment_token() );
		$this->assertTrue( $payment_information->is_merchant_initiated() );
	}

	public function test_from_payment_request_without_token() {
		$payment_information = Payment_Information::from_payment_request(
			[ self::PAYMENT_METHOD_REQUEST_KEY => self::PAYMENT_METHOD ]
		);
		$this->assertEquals( self::PAYMENT_METHOD, $payment_information->get_payment_method() );
		$this->assertFalse( $payment_information->is_using_saved_payment_method() );
		$this->assertFalse( $payment_information->is_merchant_initiated() );
	}
}

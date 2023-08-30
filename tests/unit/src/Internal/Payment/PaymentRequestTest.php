<?php
/**
 * Class PaymentRequestTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use WC_Payment_Token;
use WC_Payment_Tokens;
use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;
use WCPay\Internal\Payment\PaymentMethod\SavedPaymentMethod;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\PaymentRequestException;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPAY_UnitTestCase;

/**
 * Tests for class PaymentRequestUtilTest
 */
class PaymentRequestTest extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var PaymentRequest
	 */
	private $sut;

	public function setUp(): void {
		parent::setUp();
		$this->mock_legacy_proxy = $this->createMock( LegacyProxy::class );
	}

	/**
	 * @dataProvider provider_text_string_param
	 */
	public function test_get_fraud_prevention_token( ?string $value, ?string $expected ) {
		$request   = is_null( $value ) ? [] : [ 'wcpay-fraud-prevention-token' => $value ];
		$this->sut = new PaymentRequest( $this->mock_legacy_proxy, $request );
		$this->assertSame( $expected, $this->sut->get_fraud_prevention_token() );
	}

	/**
	 * @dataProvider provider_text_string_param
	 */
	public function test_get_woopay_intent_id( ?string $value, ?string $expected ) {
		$request   = is_null( $value ) ? [] : [ 'platform-checkout-intent' => $value ];
		$this->sut = new PaymentRequest( $this->mock_legacy_proxy, $request );
		$this->assertSame( $expected, $this->sut->get_woopay_intent_id() );
	}

	/**
	 * @dataProvider provider_text_string_param
	 */
	public function test_get_intent_id( ?string $value, ?string $expected ) {
		$request   = is_null( $value ) ? [] : [ 'intent_id' => $value ];
		$this->sut = new PaymentRequest( $this->mock_legacy_proxy, $request );
		$this->assertSame( $expected, $this->sut->get_intent_id() );
	}

	/**
	 * @dataProvider provider_text_string_param
	 */
	public function test_get_payment_method_id( ?string $value, ?string $expected ) {
		$request   = is_null( $value ) ? [] : [ 'payment_method_id' => $value ];
		$this->sut = new PaymentRequest( $this->mock_legacy_proxy, $request );
		$this->assertSame( $expected, $this->sut->get_payment_method_id() );
	}

	public function provider_text_string_param(): array {
		return [
			'Param is not set'                          => [
				'value'    => null,
				'expected' => null,
			],
			'empty string'                              => [
				'value'    => '',
				'expected' => '',
			],
			'normal string'                             => [
				'value'    => 'String-with-dash_and_underscore',
				'expected' => 'String-with-dash_and_underscore',
			],
			'string will be changed after sanitization' => [
				'value'    => " \n<tag>String-with_special_chars__@.#$%^&*()",
				'expected' => 'String-with_special_chars__@.#$%^&*()',
			],
		];
	}

	public function provider_text_string_for_bool_representation(): array {
		return [
			'Param is not set' => [
				'value'    => null,
				'expected' => false,
			],
			'empty string'     => [
				'value'    => '',
				'expected' => true,
			],
			'any string'       => [
				'value'    => 'any string',
				'expected' => true,
			],
		];
	}

	/**
	 * @dataProvider provider_text_string_for_bool_representation
	 */
	public function test_is_woopay_preflight_check( ?string $value, bool $expected ) {
		$request   = is_null( $value ) ? [] : [ 'is-woopay-preflight-check' => $value ];
		$this->sut = new PaymentRequest( $this->mock_legacy_proxy, $request );
		$this->assertSame( $expected, $this->sut->is_woopay_preflight_check() );
	}

	/**
	 * @dataProvider provider_test_order_id
	 */
	public function test_get_order_id( ?string $value, ?int $expected ) {
		$request   = is_null( $value ) ? [] : [ 'order_id' => $value ];
		$this->sut = new PaymentRequest( $this->mock_legacy_proxy, $request );
		$this->assertSame( $expected, $this->sut->get_order_id() );
	}

	public function provider_test_order_id(): array {
		return [
			'Param is not set'     => [
				'value'    => null,
				'expected' => null,
			],
			'normal id'            => [
				'value'    => '123',
				'expected' => 123,
			],
			'id will be sanitized' => [
				'value'    => '123abc',
				'expected' => 123,
			],
		];
	}

	/**
	 * @dataProvider provider_get_payment_method_throw_exception_due_to_miss_payment_method_param
	 */
	public function test_get_payment_method_throw_exception_due_to_miss_payment_method_param( array $request ) {
		$this->expectException( PaymentRequestException::class );
		$this->expectExceptionMessage( 'WooPayments is not used during checkout.' );
		$this->sut = new PaymentRequest( $this->mock_legacy_proxy, $request );
		$this->sut->get_payment_method();
	}

	public function provider_get_payment_method_throw_exception_due_to_miss_payment_method_param(): array {
		return [
			'empty payment_method param' => [ [ 'payment_method' => '' ] ],
			'not WooPayments method'     => [
				[ 'payment_method' => 'NOT_woocommerce_payments' ],
			],
		];
	}

	public function test_get_payment_return_new_payment_method() {
		$request   = [
			'payment_method'       => 'woocommerce_payments',
			'wcpay-payment-method' => 'pm_mock',
		];
		$this->sut = new PaymentRequest(
			$this->mock_legacy_proxy,
			$request
		);

		$this->assertInstanceOf( NewPaymentMethod::class, $this->sut->get_payment_method() );
	}

	public function test_get_payment_throw_exception_due_to_invalid_token_id() {
		$request   = [
			'payment_method'                        => 'woocommerce_payments',
			'wc-woocommerce_payments-payment-token' => 123456,
		];
		$this->sut = new PaymentRequest(
			$this->mock_legacy_proxy,
			$request
		);

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_static' )
			->with( WC_Payment_Tokens::class, 'get', 123456 )
			->willReturn( null );
		$this->expectException( PaymentRequestException::class );
		$this->expectExceptionMessage( 'Invalid saved payment method (token) ID' );

		$this->sut->get_payment_method();
	}

	public function test_get_payment_return_saved_payment_method() {
		$request   = [
			'payment_method'                        => 'woocommerce_payments',
			'wc-woocommerce_payments-payment-token' => 123456,
		];
		$this->sut = new PaymentRequest(
			$this->mock_legacy_proxy,
			$request
		);

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_static' )
			->with( WC_Payment_Tokens::class, 'get', 123456 )
			->willReturn( $this->createMock( WC_Payment_Token::class ) );

		$this->assertInstanceOf( SavedPaymentMethod::class, $this->sut->get_payment_method() );
	}

	public function test_get_payment_method_throw_exception_due_to_no_payment_method_attached() {
		$request   = [ 'payment_method' => 'woocommerce_payments' ];
		$this->sut = new PaymentRequest(
			$this->mock_legacy_proxy,
			$request
		);

		$this->expectException( PaymentRequestException::class );
		$this->expectExceptionMessage( 'No valid payment method attached to the request.' );

		$this->sut->get_payment_method();
	}
}

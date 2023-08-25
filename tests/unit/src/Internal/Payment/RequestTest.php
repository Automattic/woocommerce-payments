<?php
/**
 * Class RequestTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Util;

use WCPay\Internal\Payment\Request as PaymentRequest;
use WCPAY_UnitTestCase;

/**
 * Tests for class PaymentRequestUtilTest
 */
class RequestTest extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var PaymentRequest
	 */
	private $sut;

	/**
	 * @dataProvider provider_text_string_param
	 */
	public function test_get_fraud_prevention_token( ?string $value, ?string $expected ) {
		$request   = is_null( $value ) ? [] : [ 'wcpay-fraud-prevention-token' => $value ];
		$this->sut = new PaymentRequest( $request );
		$this->assertSame( $expected, $this->sut->get_fraud_prevention_token() );
	}

	/**
	 * @dataProvider provider_text_string_param
	 */
	public function test_get_woopay_intent_id( ?string $value, ?string $expected ) {
		$request   = is_null( $value ) ? [] : [ 'platform-checkout-intent' => $value ];
		$this->sut = new PaymentRequest( $request );
		$this->assertSame( $expected, $this->sut->get_woopay_intent_id() );
	}

	/**
	 * @dataProvider provider_text_string_param
	 */
	public function test_get_intent_id( ?string $value, ?string $expected ) {
		$request   = is_null( $value ) ? [] : [ 'intent_id' => $value ];
		$this->sut = new PaymentRequest( $request );
		$this->assertSame( $expected, $this->sut->get_intent_id() );
	}

	/**
	 * @dataProvider provider_text_string_param
	 */
	public function test_get_payment_method_id( ?string $value, ?string $expected ) {
		$request   = is_null( $value ) ? [] : [ 'payment_method_id' => $value ];
		$this->sut = new PaymentRequest( $request );
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
				'expected' => false,
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
		$this->sut = new PaymentRequest( $request );
		$this->assertSame( $expected, $this->sut->is_woopay_preflight_check() );
	}

	/**
	 * @dataProvider provider_test_order_id
	 */
	public function test_get_order_id( ?string $value, ?int $expected ) {
		$request   = is_null( $value ) ? [] : [ 'order_id' => $value ];
		$this->sut = new PaymentRequest( $request );
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
}

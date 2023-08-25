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
	 * @dataProvider provider_get_fraud_prevention_token
	 */
	public function test_get_fraud_prevention_token( ?string $value, ?string $expected ) {
		$request   = is_null( $value ) ? [] : [ 'wcpay-fraud-prevention-token' => $value ];
		$this->sut = new PaymentRequest( $request );
		$this->assertSame( $expected, $this->sut->get_fraud_prevention_token() );
	}

	public function provider_get_fraud_prevention_token(): array {
		return [
			'Param is not set'                          => [
				'value'    => null,
				'expected' => null,
			],
			'empty string'                              => [
				'value'    => '',
				'expected' => '',
			],
			'string with dash and underscore'           => [
				'value'    => 'string-with-dash_and_underscore',
				'expected' => 'string-with-dash_and_underscore',
			],
			'string will be changed after sanitization' => [
				'value'    => 'string-with-dash_and_underscore<>!@#$%^&*()./\\',
				'expected' => 'string-with-dash_and_underscore',
			],
		];
	}

	/**
	 * @dataProvider provider_is_woopay_preflight_check
	 */
	public function test_is_woopay_preflight_check( ?string $value, bool $expected ) {
		$request   = is_null( $value ) ? [] : [ 'is-woopay-preflight-check' => $value ];
		$this->sut = new PaymentRequest( $request );
		$this->assertSame( $expected, $this->sut->is_woopay_preflight_check() );
	}

	public function provider_is_woopay_preflight_check(): array {
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
}

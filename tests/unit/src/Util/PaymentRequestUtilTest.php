<?php
/**
 * Class PaymentRequestUtilTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Util;

use WCPay\Util\PaymentRequestUtil;
use WCPAY_UnitTestCase;

/**
 * Tests for class PaymentRequestUtilTest
 */
class PaymentRequestUtilTest extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var PaymentRequestUtil
	 */
	private $sut;

	protected function setUp(): void {
		parent::setUp();
		$this->sut = new PaymentRequestUtil();
	}

	/**
	 * @dataProvider provider_get_fraud_prevention_token
	 */
	public function test_get_fraud_prevention_token( ?string $value, ?string $expected ) {
		if ( is_null( $value ) ) {
			unset( $_POST['wcpay-fraud-prevention-token'] );
		} else {
			$_POST['wcpay-fraud-prevention-token'] = $value;
		}
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
		if ( is_null( $value ) ) {
			unset( $_POST['is-woopay-preflight-check'] );
		} else {
			$_POST['is-woopay-preflight-check'] = $value;
		}
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
				'expected' => true,
			],
			'any string'       => [
				'value'    => 'any string',
				'expected' => true,
			],
		];
	}
}

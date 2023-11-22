<?php
/**
 * Class FailedTransactionRateLimiterTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Internal\Payment\FailedTransactionRateLimiter;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\SessionService;
use WCPAY_UnitTestCase;

/**
 *  FailedTransactionRateLimiter unit tests.
 */
class FailedTransactionRateLimiterTest extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var FailedTransactionRateLimiter
	 */
	private $sut;

	/**
	 * @var MockObject|SessionService
	 */
	private $mock_session_service;

	/**
	 * @var MockObject|LegacyProxy
	 */
	private $mock_legacy_proxy;

	/**
	 * Pre-test setup
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_session_service = $this->createMock( SessionService::class );
		$this->mock_legacy_proxy    = $this->createMock( LegacyProxy::class );

		$this->sut = new FailedTransactionRateLimiter(
			$this->mock_session_service,
			$this->mock_legacy_proxy
		);
	}

	public function provider_should_bump_rate_limiter(): array {
		return [
			'card_declined'    => [ 'card_declined', true ],
			'incorrect_number' => [ 'incorrect_number', true ],
			'incorrect_cvc'    => [ 'incorrect_cvc', true ],
			'any_other_code'   => [ 'any_other_code', false ],
		];
	}

	/**
	 * @dataProvider provider_should_bump_rate_limiter
	 */
	public function test_should_bump_rate_limiter( string $error_code, bool $expected ) {
		$this->assertSame( $expected, $this->sut->should_bump_rate_limiter( $error_code ) );
	}
}

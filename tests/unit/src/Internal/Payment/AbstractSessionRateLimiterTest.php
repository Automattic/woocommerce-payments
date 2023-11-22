<?php
/**
 * Class AbstractSessionRateLimiterTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Payment;

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Internal\Payment\AbstractSessionRateLimiter;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\SessionService;
use WCPAY_UnitTestCase;

/**
 *  AbstractSessionRateLimiter unit tests.
 */
class AbstractSessionRateLimiterTest extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var AbstractSessionRateLimiter
	 */
	private $sut;

	/**
	 * @var string
	 */
	private $mock_key;

	/**
	 * @var MockObject|SessionService
	 */
	private $mock_session_service;

	/**
	 * @var MockObject|LegacyProxy
	 */
	private $mock_legacy_proxy;

	/**
	 * @var int
	 */
	private $mock_threshold;

	/**
	 * @var int
	 */
	private $mock_delay;

	/**
	 * Pre-test setup
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_key             = 'session_key';
		$this->mock_threshold       = 2;
		$this->mock_delay           = 600;
		$this->mock_session_service = $this->createMock( SessionService::class );
		$this->mock_legacy_proxy    = $this->createMock( LegacyProxy::class );

		$this->sut = new TestConcreteSessionRateLimiter(
			$this->mock_key,
			$this->mock_threshold,
			$this->mock_delay,
			$this->mock_session_service,
			$this->mock_legacy_proxy
		);
	}

	public function test_bump() {
		$timestamp_1 = time();
		$timestamp_2 = $timestamp_1 + 10;

		$this->mock_session_service->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( $this->mock_key )
			->willReturnOnConsecutiveCalls( null, [ $timestamp_1 ] );

		$this->mock_legacy_proxy->expects( $this->exactly( 2 ) )
			->method( 'call_function' )
			->withConsecutive( [ 'time' ], [ 'time' ] )
			->willReturnOnConsecutiveCalls( $timestamp_1, $timestamp_2 );

		$this->mock_session_service->expects( $this->exactly( 2 ) )
			->method( 'set' )
			->withConsecutive(
				[ $this->mock_key, [ $timestamp_1 ] ],
				[ $this->mock_key, [ $timestamp_1, $timestamp_2 ] ]
			);

		// Calling this two times so that can test the session value is either null or an array.
		$this->sut->bump();
		$this->sut->bump();
	}

	public function test_is_limited_always_returns_false_when_rate_limiter_is_disabled() {
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'wcpay_session_rate_limiter_disabled_' . $this->mock_key )
			->willReturn( 'yes' );

		$this->assertFalse( $this->sut->is_limited() );
	}

	public function provider_is_limited(): array {
		$time = time();
		return [
			'un-initialized registry'              => [
				null,
				false,
				false,
			],
			'empty registry'                       => [
				null,
				false,
				false,
			],
			'yet reach threshold'                  => [
				[ $time ],
				false,
				false,
			],
			'reach threshold but not within delay' => [
				[ $time - 800, $time - 700 ],
				true,
				false,
			],
			'reach threshold, got limited'         => [
				[ $time - 200, $time - 100 ],
				false,
				true,
			],
		];
	}

	/**
	 * @dataProvider provider_is_limited
	 */
	public function test_is_limited( ?array $session_registry, bool $is_clear_session, bool $expected ) {
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'wcpay_session_rate_limiter_disabled_' . $this->mock_key )
			->willReturn( 'no' );

		$this->mock_session_service->expects( $this->once() )
			->method( 'get' )
			->with( $this->mock_key )
			->willReturn( $session_registry );

		$this->mock_session_service->expects( $this->exactly( $is_clear_session ? 1 : 0 ) )
			->method( 'set' )
			->with( $this->mock_key, [] );

		$this->assertSame( $expected, $this->sut->is_limited() );
	}
}

/**
 * A simple class to extend the SUT abstract for testing purpose.
 */
// phpcs:disable
class TestConcreteSessionRateLimiter extends AbstractSessionRateLimiter {
	public function __construct(
		string $key,
		int $threshold,
		int $delay,
		SessionService $session_service,
		LegacyProxy $legacy_proxy
	) {
		parent::__construct( $key, $threshold, $delay, $session_service, $legacy_proxy );
	}
}
// phpcs:enable

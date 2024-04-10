<?php
/**
 * Class MinimumAmountServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\MinimumAmountService;
use WCPAY_UnitTestCase;

/**
 * Order service unit tests.
 */
class MinimumAmountServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var MinimumAmountService
	 */
	private $sut;

	/**
	 * @var LegacyProxy|MockObject
	 */
	private $mock_legacy_proxy;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_legacy_proxy = $this->createMock( LegacyProxy::class );
		$this->sut               = new MinimumAmountService( $this->mock_legacy_proxy );
	}

	public function test_store_amount_from_exception() {
		$exception = new Amount_Too_Small_Exception( 'Amount too small', 100, 'EUR', 400 );

		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'set_transient', 'wcpay_minimum_amount_eur', 100, DAY_IN_SECONDS );

		$this->sut->store_amount_from_exception( $exception );
	}

	public function test_verify_amount_returns_void() {
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_transient', 'wcpay_minimum_amount_eur' )
			->willReturn( 100 );

		$this->sut->verify_amount( 'EUR', 150 );
	}

	public function test_verify_amount_throw_exception() {
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_transient', 'wcpay_minimum_amount_eur' )
			->willReturn( 100 );

		$this->expectException( Amount_Too_Small_Exception::class );
		$this->expectExceptionMessage( 'Order amount too small' );

		$this->sut->verify_amount( 'EUR', 50 );
	}

	public function test_set_cached_amount() {
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'set_transient', 'wcpay_minimum_amount_usd', 100, DAY_IN_SECONDS );

		\PHPUnit_Utils::call_method( $this->sut, 'set_cached_amount', [ 'USD', 100 ] );
	}

	public function provider_get_cached_amount() {
		return [
			'Transient not set'       => [ false, 0 ],
			'Transient invalid value' => [ null, 0 ],
			'Transient valid value '  => [ 123, 123 ],
		];
	}

	/**
	 * @dataProvider provider_get_cached_amount
	 */
	public function test_get_cached_amount( $transient_value, $expected ) {
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_transient', 'wcpay_minimum_amount_eur' )
			->willReturn( $transient_value );

		\PHPUnit_Utils::call_method( $this->sut, 'get_cached_amount', [ 'EUR' ] );
	}
}

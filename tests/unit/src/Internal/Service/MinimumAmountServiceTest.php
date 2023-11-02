<?php
/**
 * Class MinimumAmountServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Payments_Utils;
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

	public function test_set_cache() {
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'set_transient', 'wcpay_minimum_amount_usd', 100, DAY_IN_SECONDS );

		$this->sut->set_cache( 'USD', 100 );
	}

	public function provider_get_cache() {
		return [
			'Transient not set'       => [ false, 0 ],
			'Transient invalid value' => [ null, 0 ],
			'Transient valid value '  => [ 123, 123 ],
		];
	}

	/**
	 * @dataProvider provider_get_cache
	 */
	public function test_get_cache( $transient_value, $expected ) {
		$this->mock_legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_transient', 'wcpay_minimum_amount_eur' )
			->willReturn( $transient_value );

		$this->assertSame( $expected, $this->sut->get_cache( 'EUR' ) );
	}

	public function test_get_error_message_for_shoppers() {

		$this->mock_legacy_proxy->expects( $this->exactly( 2 ) )
			->method( 'call_static' )
			->withConsecutive(
				[ WC_Payments_Utils::class, 'interpret_stripe_amount', 50, 'EUR' ],
				[ WC_Payments_Utils::class, 'format_currency', 0.5, 'EUR' ],
			)
			->willReturnOnConsecutiveCalls(
				0.5,
				'0.5 €'
			);

		$this->assertSame(
			'The selected payment method requires a total amount of at least 0.5 €.',
			$this->sut->get_error_message_for_shoppers( 'EUR', 50 )
		);
	}
}

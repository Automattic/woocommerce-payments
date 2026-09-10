<?php
/**
 * Historical valuation arithmetic regression tests.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\HistoricalSalesValuation;

/** Keep monetary rounding at the reporting minor-unit boundary. */
class HistoricalSalesValuationTest extends \PHPUnit\Framework\TestCase {
	/** Decimal ratios include zero, half ties and cancellation of large factors. */
	public function test_exact_decimal_conversion(): void {
		$calculator = new HistoricalSalesValuation();
		foreach ( [
			[ '36.00', '0.85', 2, 4235 ],
			[ '18.00', '0.85', 2, 2118 ],
			[ '0', '0.85', 2, 0 ],
			[ '1.005', '1', 2, 101 ],
			[ '1.0049', '1', 2, 100 ],
			[ '1', '2', 0, 1 ],
			[ '1', '3', 3, 333 ],
			[ '100000000000000000', '100000000000000000', 2, 100 ],
			[ '0.000000000000000001', '0.000000000000000001', 2, 100 ],
		] as $case ) {
			$this->assertSame( $case[3], $calculator->convert( $case[0], $case[1], $case[2] ) );
		}
	}

	/** Unsupported inputs must not become approximate or infinite money. */
	public function test_invalid_and_overflowing_inputs(): void {
		$calculator = new HistoricalSalesValuation();
		foreach ( [ [ '1', '0', 2 ], [ '-1', '1', 2 ], [ '1e3', '1', 2 ], [ '1', 'NaN', 2 ], [ '1', '1', 4 ], [ '1', '1', -1 ], [ '999999999999999999', '0.000000000000000001', 2 ], [ '1.0000000000000000001', '1', 2 ] ] as $case ) {
			$this->assertNull( $calculator->convert( ...$case ) );
		}
	}
}

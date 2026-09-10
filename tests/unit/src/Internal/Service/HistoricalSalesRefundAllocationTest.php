<?php
/**
 * Exact reversal of historical sales valuations.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\HistoricalSalesRefundAllocation;

/** Cumulative allocation must never create an extra converted refund cent. */
class HistoricalSalesRefundAllocationTest extends \PHPUnit\Framework\TestCase {
	/** Two GBP18 reversals exactly reverse an established EUR42.35 sale. */
	public function test_split_refunds_preserve_original_valuation(): void {
		$allocator = new HistoricalSalesRefundAllocation();
		$this->assertSame( 2118, $allocator->allocate( 3600, 4235, 0, 1800 ) );
		$this->assertSame( 2117, $allocator->allocate( 3600, 4235, 1800, 1800 ) );
		$this->assertSame( 4235, $allocator->allocate( 3600, 4235, 0, 3600 ) );
	}

	/** Every partition of a small sale reverses exactly the original valuation. */
	public function test_partition_invariance(): void {
		$allocator = new HistoricalSalesRefundAllocation();
		foreach ( [ 1, 2, 3, 7, 100, 4235 ] as $valued ) {
			for ( $split = 0; $split <= 37; ++$split ) {
				$first  = $allocator->allocate( 37, $valued, 0, $split );
				$second = $allocator->allocate( 37, $valued, $split, 37 - $split );
				$this->assertSame( $valued, $first + $second );
			}
		}
	}

	/** Multiple partial reversals preserve value at every step, including half ties. */
	public function test_multiple_partial_refunds(): void {
		$allocator = new HistoricalSalesRefundAllocation();
		foreach ( [ 2, 3, 10, 37 ] as $original ) {
			foreach ( [ 1, 3, 42 ] as $valued ) {
				$total = 0;
				for ( $previous = 0; $previous < $original; ++$previous ) {
					$allocated = $allocator->allocate( $original, $valued, $previous, 1 );
					$this->assertNotNull( $allocated );
					$this->assertGreaterThanOrEqual( 0, $allocated );
					$total += $allocated;
					$this->assertLessThanOrEqual( $valued, $total );
				}
				$this->assertSame( $valued, $total );
			}
		}
		$this->assertSame( PHP_INT_MAX, $allocator->allocate( 1, PHP_INT_MAX, 0, 1 ) );
	}

	/** Invalid or overflowing evidence is unavailable, not an approximate float. */
	public function test_invalid_inputs(): void {
		$allocator = new HistoricalSalesRefundAllocation();
		foreach ( [ [ 0, 10, 0, 0 ], [ 10, -1, 0, 1 ], [ 10, 10, -1, 1 ], [ 10, 10, 0, -1 ], [ 10, 10, 11, 0 ], [ 10, 10, 9, 2 ], [ PHP_INT_MAX, PHP_INT_MAX, 0, 2 ] ] as $args ) {
			$this->assertNull( $allocator->allocate( ...$args ) );
		}
		$this->assertSame( 0, $allocator->allocate( 10, 0, 0, 10 ) );
	}
}

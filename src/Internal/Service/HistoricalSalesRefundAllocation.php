<?php
/**
 * Allocate reversals of an established historical sales valuation.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** Does not qualify a valuation or calculate processor refund proceeds. */
class HistoricalSalesRefundAllocation {
	/**
	 * Allocate the difference between rounded cumulative reversals, in minor units.
	 *
	 * Callers must supply a qualified original valuation and a stable refund order.
	 * Over-refunds and values outside exact integer arithmetic remain unavailable.
	 *
	 * @param int $original Original sale in original-currency minor units.
	 * @param int $valued Original sale in reporting-currency minor units.
	 * @param int $previous Original-currency refunds allocated before this refund.
	 * @param int $refund This refund in original-currency minor units.
	 * @return int|null Positive reporting-currency reversal, or unavailable.
	 */
	public function allocate( int $original, int $valued, int $previous, int $refund ): ?int {
		if ( $original <= 0 || $valued < 0 || $previous < 0 || $refund < 0 || $previous > $original || $refund > $original - $previous ) {
			return null;
		}
		$cumulative = $previous + $refund;
		if ( $valued > 0 && $cumulative > intdiv( PHP_INT_MAX, $valued ) ) {
			return null;
		}
		return $this->rounded_ratio( $valued * $cumulative, $original ) - $this->rounded_ratio( $valued * $previous, $original );
	}

	/**
	 * Round a nonnegative integer ratio half up without floating-point conversion.
	 *
	 * @param int $numerator Exact product.
	 * @param int $denominator Positive original amount.
	 * @return int Rounded reporting amount.
	 */
	private function rounded_ratio( int $numerator, int $denominator ): int {
		$whole     = intdiv( $numerator, $denominator );
		$remainder = $numerator % $denominator;
		$half      = intdiv( $denominator, 2 ) + $denominator % 2;
		return $whole + ( $remainder >= $half ? 1 : 0 );
	}
}

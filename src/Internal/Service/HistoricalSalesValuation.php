<?php
/**
 * Exact decimal arithmetic for qualified historical checkout valuations.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** Converts a qualified checkout value, never processor receipts. */
class HistoricalSalesValuation {
	/**
	 * Divide an original decimal amount by an order-per-reporting rate, half up.
	 *
	 * @param string $amount Original major-unit amount.
	 * @param string $rate Saved order-per-reporting rate.
	 * @param int    $precision Reporting currency decimal places.
	 * @return int|null Reporting minor units, or unsupported/overflowing input.
	 */
	public function convert( string $amount, string $rate, int $precision ): ?int {
		$a = $this->decimal( $amount );
		$r = $this->decimal( $rate );
		if ( null === $a || null === $r || 0 === $r[0] || $precision < 0 || $precision > 3 ) {
			return null;
		}
		$numerators   = [ $a[0], $r[1], 10 ** $precision ];
		$denominators = [ $a[1], $r[0] ];
		// Cancel before multiplication to avoid needless intermediate overflow.
		foreach ( $numerators as &$numerator ) {
			foreach ( $denominators as &$denominator ) {
				$gcd         = $this->gcd( $numerator, $denominator );
				$numerator   = intdiv( $numerator, $gcd );
				$denominator = intdiv( $denominator, $gcd );
			}
			unset( $denominator );
		}
		unset( $numerator );
		$n = $this->product( $numerators );
		$d = $this->product( $denominators );
		if ( null === $n || null === $d ) {
			return null;
		}
		$whole = intdiv( $n, $d );
		$round = $n % $d >= intdiv( $d, 2 ) + $d % 2;
		if ( $round && PHP_INT_MAX === $whole ) {
			return null;
		}
		return $whole + ( $round ? 1 : 0 );
	}

	/**
	 * Parse a bounded nonnegative decimal without passing through a float.
	 *
	 * @param string $value Decimal input.
	 * @return array|null Exact numerator and denominator.
	 */
	private function decimal( string $value ): ?array {
		if ( ! preg_match( '/^(0|[1-9][0-9]*)(?:\.([0-9]+))?$/D', $value, $matches ) ) {
			return null;
		}
		$fraction = rtrim( $matches[2] ?? '', '0' );
		$digits   = ltrim( $matches[1] . $fraction, '0' );
		if ( strlen( $fraction ) > 18 || strlen( $digits ) > 18 ) {
			return null;
		}
		return [ (int) $digits, 10 ** strlen( $fraction ) ];
	}

	/**
	 * Compute a greatest common divisor.
	 *
	 * @param int $a Nonnegative numerator.
	 * @param int $b Positive denominator.
	 * @return int Positive divisor.
	 */
	private function gcd( int $a, int $b ): int {
		while ( 0 !== $b ) {
			$remainder = $a % $b;
			$a         = $b;
			$b         = $remainder;
		}
		return $a;
	}

	/**
	 * Multiply nonnegative integers without overflow.
	 *
	 * @param array $values Integer factors.
	 * @return int|null Exact product or unavailable.
	 */
	private function product( array $values ): ?int {
		$result = 1;
		foreach ( $values as $value ) {
			if ( 0 !== $value && $result > intdiv( PHP_INT_MAX, $value ) ) {
				return null;
			}
			$result *= $value;
		}
		return $result;
	}
}

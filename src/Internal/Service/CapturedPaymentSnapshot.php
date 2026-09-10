<?php
/**
 * Historical reporting evidence for one captured payment.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/**
 * Validates a fetched charge without deriving settlement from a rounded rate.
 *
 * This is an individual event snapshot, not an order or account balance.
 */
class CapturedPaymentSnapshot {

	/**
	 * Build a currency-qualified snapshot from a historical charge response.
	 *
	 * Amounts are integer minor units in their respective currencies. The caller
	 * must establish that this charge belongs to the order and separately check
	 * that all payment events have been retrieved before aggregating an order.
	 *
	 * @param array  $charge Fetched charge response.
	 * @param string $charge_id Expected charge identifier.
	 * @param string $order_currency Original payment currency.
	 * @param int    $order_amount Original payment amount in minor units.
	 * @param string $reporting_currency Requested reporting currency.
	 * @return array Snapshot or explicit incomplete state.
	 */
	public function build( array $charge, string $charge_id, string $order_currency, int $order_amount, string $reporting_currency ): array {
		return $this->qualify( $charge, $charge_id, $order_currency, $order_amount, $reporting_currency, true );
	}

	/**
	 * Qualify only the original capture, independently of later adjustments.
	 *
	 * This result never establishes refund/dispute coverage or an order balance.
	 * Callers must record and qualify those events separately before aggregation.
	 *
	 * @param array  $charge Fetched charge response.
	 * @param string $charge_id Expected charge identifier.
	 * @param string $order_currency Original payment currency.
	 * @param int    $order_amount Original payment amount in minor units.
	 * @param string $reporting_currency Requested reporting currency.
	 * @return array Capture evidence or explicit incomplete state.
	 */
	public function build_event( array $charge, string $charge_id, string $order_currency, int $order_amount, string $reporting_currency ): array {
		return $this->qualify( $charge, $charge_id, $order_currency, $order_amount, $reporting_currency, false );
	}

	/**
	 * Validate source evidence in the legacy order of precedence.
	 *
	 * @param array  $charge Fetched charge response.
	 * @param string $charge_id Expected charge identifier.
	 * @param string $order_currency Original payment currency.
	 * @param int    $order_amount Original payment amount in minor units.
	 * @param string $reporting_currency Requested reporting currency.
	 * @param bool   $require_unadjusted Whether adjustments must be absent.
	 * @return array Qualified evidence or incomplete state.
	 */
	private function qualify( array $charge, string $charge_id, string $order_currency, int $order_amount, string $reporting_currency, bool $require_unadjusted ): array {
		$incomplete = static function ( string $reason ): array {
			return [
				'state'  => 'incomplete',
				'reason' => $reason,
			];
		};
		if ( '' === $charge_id || ( $charge['id'] ?? null ) !== $charge_id ) {
			return $incomplete( 'charge_mismatch' );
		}
		if ( true !== ( $charge['paid'] ?? null ) || true !== ( $charge['captured'] ?? null ) || 'succeeded' !== ( $charge['status'] ?? null ) ) {
			return $incomplete( 'capture_unconfirmed' );
		}
		if ( $order_amount <= 0 || ( $charge['amount'] ?? null ) !== $order_amount || ( $charge['amount_captured'] ?? null ) !== $order_amount ) {
			return $incomplete( 'capture_amount_mismatch' );
		}
		if ( ! preg_match( '/^[a-zA-Z]{3}$/', $order_currency ) || strtolower( $order_currency ) !== ( $charge['currency'] ?? null ) ) {
			return $incomplete( 'original_currency_mismatch' );
		}
		if ( $require_unadjusted && ( 0 !== ( $charge['amount_refunded'] ?? null ) || false !== ( $charge['disputed'] ?? null ) ) ) {
			return $incomplete( 'adjustments_unresolved' );
		}
		$balance = $charge['balance_transaction'] ?? null;
		if ( ! is_array( $balance ) || ! is_string( $balance['id'] ?? null ) || '' === $balance['id'] ) {
			return $incomplete( 'balance_transaction_missing' );
		}
		if ( ! preg_match( '/^[a-zA-Z]{3}$/', $reporting_currency ) || strtolower( $reporting_currency ) !== ( $balance['currency'] ?? null ) ) {
			return $incomplete( 'reporting_currency_mismatch' );
		}
		if ( ! is_int( $balance['amount'] ?? null ) || $balance['amount'] <= 0 || ! is_int( $balance['created'] ?? null ) || $balance['created'] <= 0 ) {
			return $incomplete( 'balance_amount_or_time_missing' );
		}
		if ( isset( $balance['source'] ) && $balance['source'] !== $charge_id ) {
			return $incomplete( 'balance_source_mismatch' );
		}
		if ( strtolower( $order_currency ) === strtolower( $reporting_currency ) && $balance['amount'] !== $order_amount ) {
			return $incomplete( 'same_currency_amount_mismatch' );
		}
		$has_net = is_int( $balance['fee'] ?? null ) && is_int( $balance['net'] ?? null );
		if ( $has_net && $balance['amount'] - $balance['fee'] !== $balance['net'] ) {
			return $incomplete( 'balance_components_inconsistent' );
		}
		return [
			'state'                  => 'ready',
			'basis'                  => 'captured_payment_gross',
			// A dispute moves money without producing a refund, so the observed
			// state is recorded here. Absent evidence stays unknown, never 'none'.
			'dispute_state'          => is_bool( $charge['disputed'] ?? null ) ? ( $charge['disputed'] ? 'disputed' : 'none' ) : 'unknown',
			'charge_id'              => $charge_id,
			'balance_transaction_id' => $balance['id'],
			'original_amount'        => $order_amount,
			'original_currency'      => strtoupper( $order_currency ),
			'amount'                 => $balance['amount'],
			'currency'               => strtoupper( $reporting_currency ),
			'source_created'         => $balance['created'],
			'net_state'              => $has_net ? 'ready' : 'unavailable',
			'fee_amount'             => $has_net ? $balance['fee'] : null,
			'net_amount'             => $has_net ? $balance['net'] : null,
			'funds_status'           => in_array( $balance['status'] ?? null, [ 'pending', 'available' ], true ) ? $balance['status'] : 'unknown',
		];
	}
}

<?php
/**
 * Qualifies individual historical refund balance events.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/**
 * Currency-qualified events, not complete order history or bank reconciliation.
 */
class RefundBalanceSnapshot {
	/**
	 * Preserve recorded amounts without deriving a refund from a charge's rate.
	 *
	 * @param array  $refund Provider refund with expanded balance evidence.
	 * @param string $refund_id Expected refund identity.
	 * @param string $charge_id Expected charge identity.
	 * @param string $original_currency Expected payment currency.
	 * @param string $reporting_currency Required balance-event currency.
	 * @return array Qualified events or explicit incomplete state.
	 */
	public function build( array $refund, string $refund_id, string $charge_id, string $original_currency, string $reporting_currency ): array {
		$incomplete = static function ( string $reason ): array {
			return [
				'state'  => 'incomplete',
				'reason' => $reason,
			];
		};
		if ( '' === $refund_id || '' === $charge_id || ( $refund['id'] ?? null ) !== $refund_id || ( $refund['charge'] ?? null ) !== $charge_id || 'refund' !== ( $refund['object'] ?? null ) ) {
			return $incomplete( 'refund_identity_mismatch' );
		}
		if ( ! preg_match( '/^[a-zA-Z]{3}$/', $original_currency ) || ! preg_match( '/^[a-zA-Z]{3}$/', $reporting_currency ) || strtolower( $original_currency ) !== ( $refund['currency'] ?? null ) || ! is_int( $refund['amount'] ?? null ) || $refund['amount'] <= 0 ) {
			return $incomplete( 'refund_original_amount_invalid' );
		}
		$status = $refund['status'] ?? null;
		if ( ! in_array( $status, [ 'pending', 'requires_action', 'succeeded', 'failed', 'canceled' ], true ) ) {
			return $incomplete( 'refund_status_unknown' );
		}
		$fields = [ 'balance_transaction' ];
		if ( in_array( $status, [ 'failed', 'canceled' ], true ) || null !== ( $refund['failure_balance_transaction'] ?? null ) ) {
			if ( ! in_array( $status, [ 'failed', 'canceled' ], true ) ) {
				return $incomplete( 'refund_status_inconsistent' );
			}
			$fields[] = 'failure_balance_transaction';
		}
		$events = [];
		foreach ( $fields as $field ) {
			$balance = $refund[ $field ] ?? null;
			if ( ! is_array( $balance ) || 'balance_transaction' !== ( $balance['object'] ?? null ) || ! is_string( $balance['id'] ?? null ) || '' === $balance['id'] || ( $balance['source'] ?? null ) !== $refund_id ) {
				return $incomplete( 'refund_balance_evidence_missing' );
			}
			if ( strtolower( $reporting_currency ) !== ( $balance['currency'] ?? null ) ) {
				return $incomplete( 'refund_reporting_currency_mismatch' );
			}
			foreach ( [ 'amount', 'fee', 'net', 'created' ] as $integer_field ) {
				if ( ! is_int( $balance[ $integer_field ] ?? null ) ) {
					return $incomplete( 'refund_balance_components_invalid' );
				}
			}
			$is_debit = 'balance_transaction' === $field;
			if ( $balance['created'] <= 0 || $balance['amount'] - $balance['fee'] !== $balance['net'] || ( $is_debit ? $balance['amount'] >= 0 : $balance['amount'] <= 0 ) ) {
				return $incomplete( 'refund_balance_components_invalid' );
			}
			if ( $is_debit && strtolower( $original_currency ) === strtolower( $reporting_currency ) && -$refund['amount'] !== $balance['amount'] ) {
				return $incomplete( 'refund_same_currency_amount_mismatch' );
			}
			if ( isset( $events[ $balance['id'] ] ) ) {
				return $incomplete( 'refund_balance_identity_repeated' );
			}
			$events[ $balance['id'] ] = [
				'id'           => $balance['id'],
				'kind'         => $is_debit ? 'refund' : 'refund_failure_reversal',
				'amount'       => $balance['amount'],
				'fee'          => $balance['fee'],
				'net'          => $balance['net'],
				'currency'     => strtoupper( $reporting_currency ),
				'created'      => $balance['created'],
				'funds_status' => in_array( $balance['status'] ?? null, [ 'pending', 'available' ], true ) ? $balance['status'] : 'unknown',
			];
		}
		return [
			'state'             => 'events_qualified',
			'refund_id'         => $refund_id,
			'charge_id'         => $charge_id,
			'refund_status'     => $status,
			'original_amount'   => $refund['amount'],
			'original_currency' => strtoupper( $original_currency ),
			'events'            => array_values( $events ),
		];
	}
}

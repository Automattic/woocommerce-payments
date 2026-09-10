<?php
/**
 * Persists historical captured-payment evidence through WooCommerce CRUD.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Order;
use WC_Payments_Utils;

/**
 * Stores one qualified capture. It does not establish complete payment history.
 */
class CapturedPaymentSnapshotStore {

	const CURRENT_META  = '_wcpay_captured_payment_snapshot';
	const REVISION_META = '_wcpay_captured_payment_snapshot_revision';

	/**
	 * Source validator.
	 *
	 * @var CapturedPaymentSnapshot
	 */
	private $validator;

	/**
	 * Construct the store.
	 *
	 * @param CapturedPaymentSnapshot $validator Source validator.
	 */
	public function __construct( CapturedPaymentSnapshot $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Record fetched evidence; no network request or native monetary update.
	 *
	 * @param WC_Order $order Order owning the charge.
	 * @param array    $charge Historical charge response.
	 * @param string   $currency Requested reporting currency.
	 * @param array    $expected_context Expected retrieval provenance.
	 * @return array Current evidence state.
	 */
	public function record( WC_Order $order, array $charge, string $currency, array $expected_context ): array {
		if ( ! $this->valid_reporting_context( $expected_context ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_context_invalid',
			];
		}
		ksort( $expected_context );
		foreach ( $expected_context as $key => $value ) {
			if ( ! is_array( $charge['wcpay_reporting_context'] ?? null ) || ( $charge['wcpay_reporting_context'][ $key ] ?? null ) !== $value ) {
				return [
					'state'  => 'incomplete',
					'reason' => 'snapshot_context_mismatch',
				];
			}
		}
		global $wpdb;
		if ( $order->get_id() <= 0 ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'unsupported_order',
			];
		}
		$lock = 'wcpay_capture_snapshot_lock_' . $order->get_id();
		// The options table provides a site-scoped unique key on the primary DB.
		// Do not steal an old lock: a paused writer may still resume. Background
		// recovery must handle an abandoned lock explicitly before it is enabled.
		$owner = wp_generate_uuid4();
		// add_option() is an upsert and cannot provide mutual exclusion.
		$acquired = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $lock, $owner ) );
		if ( 1 !== $acquired ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_busy',
			];
		}
		try {
			return $this->record_locked( $order, $charge, $currency, $expected_context );
		} finally {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock, $owner ) );
			wp_cache_delete( $lock, 'options' );
		}
	}

	/**
	 * Read local evidence, detecting changed order inputs without fetching rates.
	 *
	 * @param WC_Order $order Order to inspect.
	 * @param string   $currency Requested reporting currency.
	 * @param array    $expected_context Expected retrieval provenance.
	 * @return array Current evidence state.
	 */
	public function read( WC_Order $order, string $currency, array $expected_context ): array {
		if ( ! $this->valid_reporting_context( $expected_context ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_context_invalid',
			];
		}
		ksort( $expected_context );
		$fresh = wc_get_order( $order->get_id() );
		if ( ! $fresh instanceof WC_Order || 'woocommerce_payments' !== $fresh->get_payment_method() ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'unsupported_order',
			];
		}
		$fresh->read_meta_data( true );
		if ( ! in_array( $fresh->get_meta( '_wcpay_mode' ), [ 'test', 'prod' ], true ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'payment_mode_missing',
			];
		}
		$record = $fresh->get_meta( self::CURRENT_META );
		if ( ! is_array( $record ) || 1 !== ( $record['version'] ?? null ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_missing',
			];
		}
		if ( ! is_array( $record['result'] ?? null ) || ! isset( $record['context'], $record['requested_currency'], $record['revision'] ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_invalid',
			];
		}
		$payload = [
			'version'            => 1,
			'context'            => $record['context'],
			'requested_currency' => $record['requested_currency'],
			'result'             => $record['result'],
		];
		if ( isset( $record['conflicting_result'] ) ) {
			$payload['conflicting_result'] = $record['conflicting_result'];
		}
		if ( hash( 'sha256', wp_json_encode( $payload ) ) !== $record['revision'] ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_invalid',
			];
		}
		if ( ! $this->is_latest_revision( $fresh, $record ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_invalid',
			];
		}
		if ( ( $record['context'] ?? null ) !== $this->context( $fresh, $expected_context ) || ( $record['requested_currency'] ?? null ) !== strtoupper( $currency ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_stale',
			];
		}
		return $record['result'];
	}

	/**
	 * Write under the per-order mutex.
	 *
	 * @param WC_Order $order Order owning the charge.
	 * @param array    $charge Historical charge response.
	 * @param string   $currency Reporting currency.
	 * @param array    $expected_context Expected retrieval provenance.
	 * @return array Evidence state.
	 */
	private function record_locked( WC_Order $order, array $charge, string $currency, array $expected_context ): array {
		$fresh = wc_get_order( $order->get_id() );
		if ( ! $fresh instanceof WC_Order || 'woocommerce_payments' !== $fresh->get_payment_method() ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'unsupported_order',
			];
		}
		// A waiting request may have cached metadata before another writer finished.
		$fresh->read_meta_data( true );
		if ( ! in_array( $fresh->get_meta( '_wcpay_mode' ), [ 'test', 'prod' ], true ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'payment_mode_missing',
			];
		}
		if ( ( 'test' === $fresh->get_meta( '_wcpay_mode' ) ) !== $expected_context['test_mode'] ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'payment_mode_mismatch',
			];
		}
		// Never relabel existing evidence or repair another account's journal.
		foreach ( [ $fresh->get_meta( self::CURRENT_META ), $this->latest_revision( $fresh ) ] as $existing ) {
			if ( '' !== $existing && null !== $existing && ( ! is_array( $existing ) || ( $existing['context']['reporting_context'] ?? null ) !== $expected_context ) ) {
				return [
					'state'  => 'incomplete',
					'reason' => 'snapshot_context_mismatch',
				];
			}
		}
		$repair = $this->repair_partial_write( $fresh );
		if ( null !== $repair ) {
			return $repair;
		}
		$context = $this->context( $fresh, $expected_context );
		// Recheck mode under the write lock: it may change while retrieval is in flight.
		if ( ( $charge['livemode'] ?? null ) !== ( 'prod' === $context['mode'] ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'payment_mode_mismatch',
			];
		}

		$result = $this->validator->build(
			$charge,
			$context['charge_id'],
			$context['currency'],
			$context['amount'],
			$currency
		);
		if ( ! empty( $context['refund_ids'] ) ) {
			$result = [
				'state'  => 'incomplete',
				'reason' => 'refund_events_required',
			];
		}
		$record   = [
			'version'            => 1,
			'context'            => $context,
			'requested_currency' => strtoupper( $currency ),
			'result'             => $result,
		];
		$revision = hash( 'sha256', wp_json_encode( $record ) );
		$current  = $fresh->get_meta( self::CURRENT_META );
		// Missing expansion or an unrelated request cannot contradict qualified evidence.
		// Affirmative amount/adjustment conflicts still follow the conflict path below.
		if ( is_array( $current ) && 'ready' === ( $current['result']['state'] ?? null ) && 'incomplete' === $result['state'] && in_array(
			$result['reason'],
			[ 'charge_mismatch', 'reporting_currency_mismatch', 'balance_transaction_missing', 'balance_amount_or_time_missing' ],
			true
		) && ! $this->contradicts_recorded_balance( $current['result'], $charge ) ) {
			return $result;
		}
		// Without a provider revision or serialized fetch generation, conflicting
		// observations cannot safely supersede a previously qualified source.
		if ( is_array( $current ) && 'inconsistent' === ( $current['result']['state'] ?? null ) ) {
			return $current['result'];
		}
		if ( is_array( $current ) && 'ready' === ( $current['result']['state'] ?? null ) && $current['result'] !== $result && ! $this->is_enrichment( $current['result'], $result ) ) {
			$record['conflicting_result'] = $result;
			$result                       = [
				'state'  => 'inconsistent',
				'reason' => 'source_changed',
			];
			$record['result']             = $result;
			$revision                     = hash( 'sha256', wp_json_encode( $record ) );
		}
		if ( is_array( $current ) && ( $current['revision'] ?? null ) === $revision && $this->is_latest_revision( $fresh, $current ) ) {
			return $result;
		}
		$record['revision']    = $revision;
		$record['recorded_at'] = time();
		$fresh->add_meta_data( self::REVISION_META, $record );
		$fresh->update_meta_data( self::CURRENT_META, $record );
		$fresh->save_meta_data();
		// CRUD does not propagate metadata insert/update failures to its caller.
		$fresh->read_meta_data( true );
		if ( $fresh->get_meta( self::CURRENT_META ) !== $record || ! $this->is_latest_revision( $fresh, $record ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_write_failed',
			];
		}
		return $result;
	}

	/**
	 * Check that the current pointer has a matching persisted revision.
	 *
	 * @param WC_Order $order Order with reloaded metadata.
	 * @param array    $record Current snapshot record.
	 * @return bool Whether the journal contains the exact record.
	 */
	private function is_latest_revision( WC_Order $order, array $record ): bool {
		return $this->latest_revision( $order ) === $record;
	}

	/**
	 * Read the most recently inserted revision, irrespective of cache array order.
	 *
	 * @param WC_Order $order Order with reloaded metadata.
	 * @return mixed Stored record, or null when absent.
	 */
	private function latest_revision( WC_Order $order ) {
		$latest = null;
		$id     = 0;
		foreach ( $order->get_meta( self::REVISION_META, false ) as $meta ) {
			if ( $meta->id > $id ) {
				$id     = $meta->id;
				$latest = $meta->value;
			}
		}
		return $latest;
	}

	/**
	 * Repair an interrupted two-write save before accepting another observation.
	 *
	 * Differing persisted facts are retained as a conflict, never silently rolled back.
	 * This does not recover an abandoned process mutex.
	 *
	 * @param WC_Order $order Order with reloaded metadata, under the mutex.
	 * @return array|null Failure state, or null when persistence is consistent.
	 */
	private function repair_partial_write( WC_Order $order ): ?array {
		$current = $order->get_meta( self::CURRENT_META );
		$latest  = $this->latest_revision( $order );
		if ( '' === $current && null === $latest ) {
			return null;
		}
		foreach ( [ $current, $latest ] as $record ) {
			if ( '' === $record || null === $record ) {
				continue;
			}
			if ( ! is_array( $record ) || ! isset( $record['revision'], $record['recorded_at'] ) ) {
				return [
					'state'  => 'incomplete',
					'reason' => 'snapshot_invalid',
				];
			}
			$payload = $record;
			unset( $payload['revision'], $payload['recorded_at'] );
			$encoded = wp_json_encode( $payload );
			if ( false === $encoded || hash( 'sha256', $encoded ) !== $record['revision'] ) {
				return [
					'state'  => 'incomplete',
					'reason' => 'snapshot_invalid',
				];
			}
		}
		if ( $current === $latest ) {
			return null;
		}
		$record = is_array( $current ) ? $current : $latest;
		if ( is_array( $current ) && is_array( $latest ) && $current['revision'] !== $latest['revision'] ) {
			$covered = false;
			// Reuse an already persisted repair; repeated failures must not nest it again.
			foreach ( [ [ $current, $latest ], [ $latest, $current ] ] as $pair ) {
				if ( 'persistence_conflict' === ( $pair[0]['result']['reason'] ?? null ) && in_array( $pair[1], $pair[0]['conflicting_result'] ?? [], true ) ) {
					$record  = $pair[0];
					$covered = true;
					break;
				}
			}
			if ( ! $covered ) {
				$record['result']             = [
					'state'  => 'inconsistent',
					'reason' => 'persistence_conflict',
				];
				$record['conflicting_result'] = [
					'current' => $current,
					'journal' => $latest,
				];
				unset( $record['revision'], $record['recorded_at'] );
				$record['revision']    = hash( 'sha256', wp_json_encode( $record ) );
				$record['recorded_at'] = time();
			}
		}
		if ( $latest !== $record ) {
			$order->add_meta_data( self::REVISION_META, $record );
		}
		$order->update_meta_data( self::CURRENT_META, $record );
		$order->save_meta_data();
		$order->read_meta_data( true );
		if ( $order->get_meta( self::CURRENT_META ) !== $record || ! $this->is_latest_revision( $order, $record ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'snapshot_write_failed',
			];
		}
		return null;
	}

	/**
	 * Validate the versioned server provenance without consulting current account state.
	 *
	 * @param array $context Expected retrieval provenance.
	 * @return bool Whether required fields have their exact types.
	 */
	private function valid_reporting_context( array $context ): bool {
		return 4 === count( $context ) && 1 === ( $context['version'] ?? null ) && is_string( $context['account_id'] ?? null ) && 1 === preg_match( '/^acct_[a-zA-Z0-9]+$/D', $context['account_id'] ) && is_int( $context['site_id'] ?? null ) && $context['site_id'] > 0 && is_bool( $context['test_mode'] ?? null );
	}

	/**
	 * Compare supplied facts even when another required response field is missing.
	 *
	 * @param array $previous Qualified snapshot.
	 * @param array $charge Current charge response.
	 * @return bool Whether comparable evidence contradicts the saved transaction.
	 */
	private function contradicts_recorded_balance( array $previous, array $charge ): bool {
		$balance = $charge['balance_transaction'] ?? null;
		if ( ( $charge['id'] ?? null ) !== $previous['charge_id'] || ! is_array( $balance ) || ( $balance['id'] ?? null ) !== $previous['balance_transaction_id'] ) {
			return false;
		}
		foreach ( [
			'amount'  => 'amount',
			'fee'     => 'fee_amount',
			'net'     => 'net_amount',
			'created' => 'source_created',
		] as $source => $saved ) {
			if ( is_int( $balance[ $source ] ?? null ) && isset( $previous[ $saved ] ) && $balance[ $source ] !== $previous[ $saved ] ) {
				return true;
			}
		}
		if ( is_string( $balance['currency'] ?? null ) && strtoupper( $balance['currency'] ) !== $previous['currency'] ) {
			return true;
		}
		return isset( $balance['source'] ) && $balance['source'] !== $previous['charge_id'];
	}

	/**
	 * Capture inputs affecting snapshot validity.
	 *
	 * @param WC_Order $order Current order.
	 * @param array    $expected_context Expected retrieval provenance.
	 * @return array Inputs affecting validity. Sales status does not erase captures.
	 */
	private function context( WC_Order $order, array $expected_context ): array {
		$refund_ids = array_map(
			static function ( $refund ) {
				return $refund->get_id();
			},
			$order->get_refunds()
		);
		sort( $refund_ids );
		return [
			'reporting_context' => $expected_context,
			'order_id'          => $order->get_id(),
			'charge_id'         => (string) $order->get_meta( '_charge_id' ),
			'mode'              => (string) $order->get_meta( '_wcpay_mode' ),
			'currency'          => $order->get_currency(),
			'amount'            => WC_Payments_Utils::prepare_amount( $order->get_total(), $order->get_currency() ),
			'refund_ids'        => $refund_ids,
		];
	}

	/**
	 * Allow additional evidence without replacing any already-qualified amount.
	 *
	 * @param array $previous Previous qualified event.
	 * @param array $next Newly validated event.
	 * @return bool Whether the new evidence only fills missing fields.
	 */
	private function is_enrichment( array $previous, array $next ): bool {
		if ( 'ready' !== ( $next['state'] ?? null ) ) {
			return false;
		}
		if ( 'unavailable' === ( $previous['net_state'] ?? 'unavailable' ) ) {
			unset( $previous['net_state'], $previous['net_amount'], $previous['fee_amount'] );
		}
		if ( 'unknown' === ( $previous['funds_status'] ?? 'unknown' ) || ( 'pending' === $previous['funds_status'] && 'available' === $next['funds_status'] ) ) {
			unset( $previous['funds_status'] );
		}
		foreach ( $previous as $key => $value ) {
			if ( ! array_key_exists( $key, $next ) || $next[ $key ] !== $value ) {
				return false;
			}
		}
		return true;
	}
}

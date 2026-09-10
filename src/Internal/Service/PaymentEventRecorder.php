<?php
/**
 * Connects qualified provider evidence to the immutable event repository.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Order;
use WC_Payments_Utils;

/** Records observations; it does not establish complete order payment coverage. */
class PaymentEventRecorder {
	/**
	 * Event storage.
	 *
	 * @var PaymentEventRepository
	 */
	private $repository;

	/**
	 * Historical charge locator.
	 *
	 * @var PaymentReceiptIndex|null
	 */
	private $index;

	/**
	 * Construct the recorder.
	 *
	 * @param PaymentEventRepository   $repository Event storage.
	 * @param PaymentReceiptIndex|null $index Historical charge locator.
	 */
	public function __construct( PaymentEventRepository $repository, ?PaymentReceiptIndex $index = null ) {
		$this->repository = $repository;
		$this->index      = $index;
	}

	/**
	 * Retain the observed account context for a matching saved payment attempt.
	 *
	 * This is an account-scoped receipt, not a canonical order-account mapping.
	 * A later attempt has a different intent partition and cannot replace this one.
	 *
	 * @param WC_Order                           $order Order with saved payment identity.
	 * @param \WC_Payments_API_Payment_Intention $intent Server payment response.
	 * @return array Persistence result, not complete payment coverage.
	 */
	public function record_intent( WC_Order $order, \WC_Payments_API_Payment_Intention $intent ): array {
		$context = $intent->get_reporting_context();
		$fresh   = is_array( $context ) ? $this->qualified_order( $order, $context, $context ) : null;
		$charge  = $intent->get_charge();
		if ( ! $fresh instanceof WC_Order || ! preg_match( '/^pi_[a-zA-Z0-9]+$/D', $intent->get_id() ) || $fresh->get_meta( '_intent_id' ) !== $intent->get_id() || ! $charge || ( $charge->get_payment_intent() && $charge->get_payment_intent() !== $intent->get_id() ) || $fresh->get_meta( '_charge_id' ) !== $charge->get_id() || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $charge->get_id() ) || strtoupper( $fresh->get_currency() ) !== $intent->get_currency() || WC_Payments_Utils::prepare_amount( $fresh->get_total(), $fresh->get_currency() ) !== $intent->get_amount() ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'intent_order_mismatch',
			];
		}
		$result = $this->record(
			$fresh,
			$context,
			$intent->get_id(),
			[
				'kind'              => 'intent_context',
				'charge_id'         => $charge->get_id(),
				'original_amount'   => $intent->get_amount(),
				'original_currency' => $intent->get_currency(),
			]
		);
		if ( 'recorded' === $result['state'] && null !== $this->index ) {
			$scope = [
				'account_id' => $context['account_id'],
				'site_id'    => $context['site_id'],
				'test_mode'  => $context['test_mode'],
				'order_id'   => $fresh->get_id(),
				'event_id'   => $intent->get_id(),
			];
			$bound = $this->index->bind( $scope, $result['revision'] );
			if ( 'bound' !== $bound['state'] ) {
				// Preserve the receipt for retry, but do not schedule an unlocatable report.
				return [
					'state'    => 'incomplete',
					'reason'   => 'receipt_index_incomplete',
					'revision' => $result['revision'],
					'index'    => $bound,
				];
			}
			$attempts = $this->record_order_attempts( $scope );
			if ( 'recorded' !== $attempts['state'] ) {
				// The receipt stands; only the completeness attestation is withheld.
				return [
					'state'    => 'incomplete',
					'reason'   => 'order_attempts_incomplete',
					'revision' => $result['revision'],
					'attempts' => $attempts,
				];
			}
		}
		return $result;
	}

	/**
	 * Backfill an identified original attempt without changing current order data.
	 *
	 * Caller owns fetch authorization and supplies trusted expected context.
	 * This records identity only; capture/refund recovery remains separate.
	 *
	 * @param int    $order_id Canonical order being recovered.
	 * @param string $charge_id Requested discovered charge.
	 * @param array  $charge Actual charge response with original metadata.
	 * @param array  $context Expected server account/site/mode context.
	 * @return array Persistence result; never complete monetary coverage.
	 */
	public function record_discovered_intent( int $order_id, string $charge_id, array $charge, array $context ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || null === $this->index ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'discovered_order_unavailable',
			];
		}
		$identity = ( new PaymentAttemptDiscovery() )->qualify_charge( $order, $charge_id, $charge, $context );
		if ( 'qualified_identity' !== $identity['state'] ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'discovered_identity_unqualified',
			];
		}
		$result = $this->record_event( $identity['scope'], $identity['receipt'] );
		if ( 'recorded' !== $result['state'] ) {
			return $result;
		}
		$bound = $this->index->bind( $identity['scope'], $result['revision'] );
		if ( 'bound' !== $bound['state'] ) {
			return [
				'state'    => 'incomplete',
				'reason'   => 'receipt_index_incomplete',
				'revision' => $result['revision'],
			];
		}
		return $result;
	}

	/**
	 * Record a historical capture using immutable receipt facts, without editing an order.
	 *
	 * @param array  $scope Recorded intent scope.
	 * @param string $revision Expected current receipt revision.
	 * @param array  $charge Provider charge with retrieval provenance.
	 * @param string $currency Reporting currency.
	 * @return array Persistence result or unqualified evidence.
	 */
	public function record_historical_capture( array $scope, string $revision, array $charge, string $currency ): array {
		$receipt = $this->repository->read_revision( $scope, $revision );
		if ( 'found' !== $receipt['state'] || $receipt['head_revision'] !== $revision ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'historical_receipt_unavailable',
			];
		}
		$facts   = $receipt['event'];
		$context = $charge['wcpay_reporting_context'] ?? null;
		if ( 'intent_context' !== ( $facts['kind'] ?? null ) || ! is_string( $facts['charge_id'] ?? null ) || ! is_int( $facts['original_amount'] ?? null ) || ! is_string( $facts['original_currency'] ?? null ) || ! is_array( $context ) || 1 !== ( $context['version'] ?? null ) || ( $charge['livemode'] ?? null ) !== ! $scope['test_mode'] || ( ! empty( $charge['payment_intent'] ) && $charge['payment_intent'] !== $scope['event_id'] ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'historical_capture_unqualified',
			];
		}
		foreach ( [ 'account_id', 'site_id', 'test_mode' ] as $key ) {
			if ( ( $context[ $key ] ?? null ) !== $scope[ $key ] ) {
				return [
					'state'  => 'incomplete',
					'reason' => 'payment_context_mismatch',
				];
			}
		}
		$snapshot = ( new CapturedPaymentSnapshot() )->build_event( $charge, $facts['charge_id'], $facts['original_currency'], $facts['original_amount'], $currency );
		if ( 'ready' !== $snapshot['state'] ) {
			return $snapshot;
		}
		$scope['event_id'] = $snapshot['balance_transaction_id'];
		return $this->record_event(
			$scope,
			[
				'kind'     => 'capture',
				'evidence' => $snapshot,
			]
		);
	}

	/**
	 * Record one verified full capture without changing the order or Analytics.
	 *
	 * @param WC_Order $order Order owning the charge.
	 * @param array    $charge Provider charge with server provenance.
	 * @param string   $currency Reporting currency.
	 * @param array    $context Expected retrieval provenance.
	 * @return array Observation persistence result.
	 */
	public function record_capture( WC_Order $order, array $charge, string $currency, array $context ): array {
		$fresh = $this->qualified_order( $order, $context, $charge['wcpay_reporting_context'] ?? null );
		if ( ! $fresh instanceof WC_Order || ( $charge['livemode'] ?? null ) !== ! $context['test_mode'] ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'payment_context_mismatch',
			];
		}
		$snapshot = ( new CapturedPaymentSnapshot() )->build_event( $charge, (string) $fresh->get_meta( '_charge_id' ), $fresh->get_currency(), WC_Payments_Utils::prepare_amount( $fresh->get_total(), $fresh->get_currency() ), $currency );
		if ( 'ready' !== $snapshot['state'] ) {
			return $snapshot;
		}
		return $this->record(
			$fresh,
			$context,
			$snapshot['balance_transaction_id'],
			[
				'kind'     => 'capture',
				'evidence' => $snapshot,
			]
		);
	}

	/**
	 * Record a fully listed refund collection using each refund's own balance events.
	 *
	 * @param WC_Order $order Order owning the charge.
	 * @param array    $collection Verified collector result.
	 * @param string   $currency Reporting currency.
	 * @param array    $context Expected retrieval provenance.
	 * @return array Persistence result; never a claim of complete payment history.
	 */
	public function record_refunds( WC_Order $order, array $collection, string $currency, array $context ): array {
		$fresh = $this->qualified_order( $order, $context, $collection['reporting_context'] ?? null );
		if ( ! $fresh instanceof WC_Order || ! preg_match( '/^[a-zA-Z]{3}$/D', $currency ) || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', (string) $fresh->get_meta( '_charge_id' ) ) || 'listed' !== ( $collection['state'] ?? null ) || ! is_array( $collection['refunds'] ?? null ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'refund_collection_unqualified',
			];
		}
		$scope = [
			'account_id' => $context['account_id'],
			'site_id'    => $context['site_id'],
			'test_mode'  => $context['test_mode'],
			'order_id'   => $fresh->get_id(),
		];
		return $this->record_refund_collection( $scope, (string) $fresh->get_meta( '_charge_id' ), $fresh->get_currency(), $collection, $currency );
	}

	/**
	 * Record historical refund balance events against a current immutable receipt.
	 *
	 * @param array  $scope Historical intent scope.
	 * @param string $revision Expected receipt revision.
	 * @param array  $collection Qualified provider collection.
	 * @param string $currency Reporting currency.
	 * @return array Persistence result or unqualified evidence.
	 */
	public function record_historical_refunds( array $scope, string $revision, array $collection, string $currency ): array {
		$receipt = $this->repository->read_revision( $scope, $revision );
		if ( 'found' !== $receipt['state'] || $receipt['head_revision'] !== $revision ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'historical_receipt_unavailable',
			];
		}
		$facts   = $receipt['event'];
		$context = $collection['reporting_context'] ?? null;
		if ( 'intent_context' !== ( $facts['kind'] ?? null ) || ! is_string( $facts['charge_id'] ?? null ) || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $facts['charge_id'] ) || ! is_string( $facts['original_currency'] ?? null ) || ! is_array( $context ) || 1 !== ( $context['version'] ?? null ) || ! preg_match( '/^[a-zA-Z]{3}$/D', $currency ) || 'listed' !== ( $collection['state'] ?? null ) || ! is_array( $collection['refunds'] ?? null ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'historical_refunds_unqualified',
			];
		}
		foreach ( [ 'account_id', 'site_id', 'test_mode' ] as $key ) {
			if ( ( $context[ $key ] ?? null ) !== $scope[ $key ] ) {
				return [
					'state'  => 'incomplete',
					'reason' => 'payment_context_mismatch',
				];
			}
		}
		return $this->record_refund_collection( $scope, $facts['charge_id'], $facts['original_currency'], $collection, $currency );
	}

	/**
	 * Extend this site's own list of the attempts it originated for one order.
	 *
	 * This is the only local statement of terminal attempt membership. It covers
	 * attempts this site created, in this account and mode, and deliberately says
	 * nothing about charges raised elsewhere against the same account. Membership
	 * only ever grows, so a later attempt cannot silently drop an earlier one.
	 *
	 * @param array $scope Recorded intent scope, including the originating intent.
	 * @return array Recorded membership revision, or an explicit incomplete reason.
	 */
	private function record_order_attempts( array $scope ): array {
		$intent_id        = $scope['event_id'];
		$membership_scope = array_merge( $scope, [ 'event_id' => 'attempts_' . hash( 'sha256', (string) $scope['order_id'] ) ] );
		$known            = $this->repository->read( $membership_scope );
		if ( ! in_array( $known['state'], [ 'found', 'missing' ], true ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'order_attempts_unavailable',
			];
		}
		$intent_ids = [];
		if ( 'found' === $known['state'] ) {
			if ( 'order_attempts' !== ( $known['event']['kind'] ?? null ) || ! is_array( $known['event']['intent_ids'] ?? null ) || ( $known['event']['order_id'] ?? null ) !== $scope['order_id'] ) {
				return [
					'state'  => 'incomplete',
					'reason' => 'order_attempts_invalid',
				];
			}
			$intent_ids = $known['event']['intent_ids'];
			if ( in_array( $intent_id, $intent_ids, true ) ) {
				return [
					'state'    => 'recorded',
					'revision' => $known['revision'],
				];
			}
		}
		$intent_ids[] = $intent_id;
		sort( $intent_ids, SORT_STRING );
		$head = $this->repository->publish(
			$membership_scope,
			$known['revision'] ?? '',
			[
				'kind'       => 'order_attempts',
				'order_id'   => $scope['order_id'],
				'intent_ids' => $intent_ids,
				'origin'     => 'site_originated',
			]
		);
		if ( 'published' !== $head['state'] ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'order_attempts_conflict',
			];
		}
		return [
			'state'    => 'recorded',
			'revision' => $head['revision'],
		];
	}

	/**
	 * Qualify all refund events before publishing the collection membership.
	 *
	 * @param array  $scope Qualified historical partition.
	 * @param string $charge_id Historical charge.
	 * @param string $original_currency Historical payment currency.
	 * @param array  $collection Qualified collection.
	 * @param string $currency Reporting currency.
	 * @return array Persistence result.
	 */
	private function record_refund_collection( array $scope, string $charge_id, string $original_currency, array $collection, string $currency ): array {
		if ( ( $collection['charge_id'] ?? null ) !== $charge_id ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'refund_collection_charge_mismatch',
			];
		}
		$retrieval = $collection['retrieval'] ?? null;
		if ( null !== $retrieval && ( ! is_array( $retrieval ) || ! is_int( $retrieval['started_at'] ?? null ) || ! is_int( $retrieval['completed_at'] ?? null ) || $retrieval['started_at'] <= 0 || $retrieval['completed_at'] < $retrieval['started_at'] ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'refund_observation_time_invalid',
			];
		}
		$observations = [];
		// Qualify the whole supplied collection before the first persistence attempt.
		foreach ( $collection['refunds'] as $refund ) {
			if ( ! is_array( $refund ) || ! is_string( $refund['id'] ?? null ) ) {
				return [
					'state'  => 'incomplete',
					'reason' => 'refund_identity_mismatch',
				];
			}
			$snapshot = ( new RefundBalanceSnapshot() )->build( $refund, $refund['id'], $charge_id, $original_currency, $currency );
			if ( 'events_qualified' !== $snapshot['state'] ) {
				return $snapshot;
			}
			foreach ( $snapshot['events'] as $event ) {
				if ( isset( $observations[ $event['id'] ] ) ) {
					return [
						'state'  => 'incomplete',
						'reason' => 'refund_balance_identity_repeated',
					];
				}
				$observations[ $event['id'] ] = [
					'kind'              => $event['kind'],
					'charge_id'         => $snapshot['charge_id'],
					'refund_id'         => $snapshot['refund_id'],
					'refund_status'     => $snapshot['refund_status'],
					'original_amount'   => $snapshot['original_amount'],
					'original_currency' => $snapshot['original_currency'],
					'evidence'          => $event,
				];
			}
		}
		$refund_ids = array_column( $collection['refunds'], 'id' );
		sort( $refund_ids, SORT_STRING );
		$membership_id    = 'membership_' . hash( 'sha256', $charge_id );
		$membership_scope = array_merge( $scope, [ 'event_id' => $membership_id ] );
		$known            = $this->repository->read( $membership_scope );
		if ( ! in_array( $known['state'], [ 'found', 'missing' ], true ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'refund_membership_unavailable',
			];
		}
		if ( 'found' === $known['state'] && ( 'refund_membership' !== ( $known['event']['kind'] ?? null ) || ! is_array( $known['event']['refund_ids'] ?? null ) || array_diff( $known['event']['refund_ids'], $refund_ids ) ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'refund_membership_shrank',
			];
		}
		$membership_event = [
			'kind'       => 'refund_membership',
			'refund_ids' => $refund_ids,
		];
		$membership_head  = 'found' === $known['state'] && $known['event'] === $membership_event
			? [
				'state'    => 'published',
				'revision' => $known['revision'],
			]
			: $this->repository->publish( $membership_scope, $known['revision'] ?? '', $membership_event );
		if ( 'published' !== $membership_head['state'] ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'refund_membership_conflict',
			];
		}
		$recorded = [];
		foreach ( $observations as $id => $observation ) {
			$result = $this->record_event( array_merge( $scope, [ 'event_id' => $id ] ), $observation );
			if ( 'recorded' !== $result['state'] ) {
				return [
					'state'    => 'incomplete',
					'reason'   => 'refund_persistence_incomplete',
					'recorded' => $recorded,
					'failure'  => $result,
				];
			}
			$recorded[ $id ] = $result['revision'];
		}
		ksort( $recorded );
		$membership = [
			'kind'               => 'refund_collection',
			'charge_id'          => $charge_id,
			'reporting_currency' => strtoupper( $currency ),
			'refund_ids'         => $refund_ids,
			'event_revisions'    => $recorded,
			'coverage'           => 'observed_collection',
			'retrieval'          => $retrieval,
		];
		// Content-address this membership, preserving prior collections without declaring freshness.
		$collection_id = 'collection_' . hash( 'sha256', wp_json_encode( $membership ) );
		$stored        = $this->record_event( array_merge( $scope, [ 'event_id' => $collection_id ] ), $membership );
		if ( 'recorded' !== $stored['state'] ) {
			return [
				'state'   => 'incomplete',
				'reason'  => 'refund_collection_persistence_incomplete',
				'failure' => $stored,
			];
		}
		return [
			'state'               => 'recorded',
			'revisions'           => $recorded,
			'collection_id'       => $collection_id,
			'collection_revision' => $stored['revision'],
			'membership_id'       => $membership_id,
			'membership_revision' => $membership_head['revision'],
		];
	}

	/**
	 * Reload and validate order identity and typed retrieval context.
	 *
	 * @param WC_Order $order Order to reload.
	 * @param array    $context Expected context.
	 * @param mixed    $actual Server or collector context.
	 * @return WC_Order|null Qualified order, or null.
	 */
	private function qualified_order( WC_Order $order, array $context, $actual ): ?WC_Order {
		if ( 4 !== count( $context ) || 1 !== ( $context['version'] ?? null ) || ! is_string( $context['account_id'] ?? null ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $context['account_id'] ) || ! is_int( $context['site_id'] ?? null ) || $context['site_id'] <= 0 || ! is_bool( $context['test_mode'] ?? null ) || ! is_array( $actual ) ) {
			return null;
		}
		foreach ( $context as $key => $value ) {
			if ( ( $actual[ $key ] ?? null ) !== $value ) {
				return null;
			}
		}
		$fresh = wc_get_order( $order->get_id() );
		if ( ! $fresh instanceof WC_Order || 'woocommerce_payments' !== $fresh->get_payment_method() ) {
			return null;
		}
		$fresh->read_meta_data( true );
		if ( ( $context['test_mode'] ? 'test' : 'prod' ) !== $fresh->get_meta( '_wcpay_mode' ) ) {
			return null;
		}
		return $fresh;
	}

	/**
	 * Preserve changed observations as conflicts instead of silently revising amounts.
	 *
	 * @param WC_Order $order Qualified order.
	 * @param array    $context Retrieval context.
	 * @param string   $id Balance event ID.
	 * @param array    $observation Qualified evidence.
	 * @return array Recorded revision or an explicit failure/conflict.
	 */
	private function record( WC_Order $order, array $context, string $id, array $observation ): array {
		$scope = [
			'account_id' => $context['account_id'],
			'site_id'    => $context['site_id'],
			'test_mode'  => $context['test_mode'],
			'order_id'   => $order->get_id(),
			'event_id'   => $id,
		];
		return $this->record_event( $scope, $observation );
	}

	/**
	 * Publish an observation within an already qualified historical partition.
	 *
	 * @param array $scope Evidence partition.
	 * @param array $observation Qualified evidence.
	 * @return array Recorded revision or explicit conflict/failure.
	 */
	private function record_event( array $scope, array $observation ): array {
		$current = $this->repository->read( $scope );
		if ( 'found' === $current['state'] && $current['event'] === $observation ) {
			return [
				'state'    => 'recorded',
				'revision' => $current['revision'],
			];
		}
		if ( ! in_array( $current['state'], [ 'missing', 'found' ], true ) ) {
			return $current;
		}
		if ( 'found' === $current['state'] && '' !== $current['revision'] ) {
			if ( 'inconsistent' === ( $current['event']['state'] ?? null ) && $current['event']['observed'] === $observation ) {
				return [
					'state'    => 'inconsistent',
					'revision' => $current['revision'],
				];
			}
			if ( $this->same_monetary_evidence( $current['event'], $observation ) && 'ready' === ( $current['event']['evidence']['net_state'] ?? null ) && 'unavailable' === ( $observation['evidence']['net_state'] ?? null ) ) {
				// Accumulate compatible evidence; the parent revision retains the fee/net source.
				foreach ( [ 'net_state', 'fee_amount', 'net_amount' ] as $key ) {
					$observation['evidence'][ $key ] = $current['event']['evidence'][ $key ];
				}
				if ( $current['event'] === $observation ) {
					return [
						'state'    => 'recorded',
						'revision' => $current['revision'],
					];
				}
			}
			if ( ! $this->same_monetary_evidence( $current['event'], $observation ) ) {
				$observation = [
					'state'    => 'inconsistent',
					'reason'   => 'source_changed',
					'previous' => $current['event'],
					'observed' => $observation,
				];
			}
		}
		$result = $this->repository->publish( $scope, $current['revision'] ?? '', $observation );
		if ( 'published' !== $result['state'] ) {
			return $result;
		}
		return [
			'state'    => 'inconsistent' === ( $observation['state'] ?? null ) ? 'inconsistent' : 'recorded',
			'revision' => $result['revision'],
		];
	}
	/**
	 * Status observations can change without contradicting recorded money.
	 *
	 * @param array $previous Previous observation.
	 * @param array $next New observation.
	 * @return bool Whether all monetary and source identity facts agree.
	 */
	private function same_monetary_evidence( array $previous, array $next ): bool {
		// Without provider revision ordering, a regressing status is contradictory evidence.
		if ( 'available' === ( $previous['evidence']['funds_status'] ?? null ) && 'available' !== ( $next['evidence']['funds_status'] ?? null ) ) {
			return false;
		}
		if ( in_array( $previous['refund_status'] ?? null, [ 'succeeded', 'failed', 'canceled' ], true ) && ( $next['refund_status'] ?? null ) !== $previous['refund_status'] ) {
			return false;
		}
		unset( $previous['refund_status'], $next['refund_status'], $previous['evidence']['funds_status'], $next['evidence']['funds_status'] );
		if ( 'capture' === ( $previous['kind'] ?? null ) && 'capture' === ( $next['kind'] ?? null ) && ( 'unavailable' === $previous['evidence']['net_state'] || 'unavailable' === $next['evidence']['net_state'] ) ) {
			unset( $previous['evidence']['net_state'], $next['evidence']['net_state'], $previous['evidence']['fee_amount'], $next['evidence']['fee_amount'], $previous['evidence']['net_amount'], $next['evidence']['net_amount'] );
		}
		return $previous === $next;
	}
}

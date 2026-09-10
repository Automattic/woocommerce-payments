<?php
/**
 * Conditional publication of payment report dependencies.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** Stores observations only; complete monetary coverage requires provider qualification. */
class PaymentReportRepository {
	/**
	 * Immutable evidence storage.
	 *
	 * @var PaymentEventRepository
	 */
	private $events;

	/**
	 * Construct without creating schema or retrieving payments.
	 *
	 * @param PaymentEventRepository $events Evidence storage.
	 */
	public function __construct( PaymentEventRepository $events ) {
		$this->events = $events;
	}

	/**
	 * Persist one webhook invalidation target so delivery retries cannot invalidate a rebuilt report.
	 *
	 * @param array  $scope Historical intent scope.
	 * @param string $event_id Provider webhook event ID.
	 * @return array Published operation, conflict for retry, or storage failure.
	 */
	public function invalidate_event( array $scope, string $event_id ): array {
		if ( ! is_string( $scope['event_id'] ?? null ) || ! preg_match( '/^pi_[a-zA-Z0-9]+$/D', $scope['event_id'] ) || ! preg_match( '/^evt_[a-zA-Z0-9]+$/D', $event_id ) ) {
			return [ 'state' => 'invalid' ];
		}
		$operation             = $scope;
		$operation['event_id'] = 'webhook_' . hash( 'sha256', $scope['event_id'] . ':' . $event_id );
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$stored = $this->events->read( $operation );
			if ( 'missing' === $stored['state'] ) {
				$receipt = $this->events->read( $scope );
				if ( 'found' !== $receipt['state'] ) {
					return $receipt;
				}
				if ( 'intent_context' !== ( $receipt['event']['kind'] ?? null ) ) {
					return [ 'state' => 'invalid' ];
				}
				$created = $this->events->publish(
					$operation,
					'',
					[
						'kind'             => 'webhook_invalidation',
						'receipt_revision' => $receipt['revision'],
					]
				);
				if ( ! in_array( $created['state'], [ 'published', 'conflict' ], true ) ) {
					return $created;
				}
				$stored = $this->events->read( $operation );
			}
			if ( 'found' !== $stored['state'] ) {
				return $stored;
			}
			$target = $stored['event']['receipt_revision'] ?? null;
			if ( 'webhook_invalidation' !== ( $stored['event']['kind'] ?? null ) || ! is_string( $target ) ) {
				return [ 'state' => 'invalid' ];
			}
			$receipt = $this->events->read_revision( $scope, $target );
			if ( 'found' !== $receipt['state'] ) {
				return $receipt;
			}
			if ( 'intent_context' !== ( $receipt['event']['kind'] ?? null ) ) {
				return [ 'state' => 'invalid' ];
			}
			// Published ancestors prove this operation committed, even after a report was rebuilt.
			$result = $this->events->publish( $scope, $target, $receipt['event'] );
			if ( 'conflict' !== $result['state'] ) {
				return $result;
			}
			// The target lost a race without committing. Persist the next target before retrying.
			$current = $this->events->read( $scope );
			if ( 'found' !== $current['state'] ) {
				return $current;
			}
			if ( 'intent_context' !== ( $current['event']['kind'] ?? null ) ) {
				return [ 'state' => 'invalid' ];
			}
			$advanced = $this->events->publish(
				$operation,
				$stored['revision'],
				[
					'kind'             => 'webhook_invalidation',
					'receipt_revision' => $current['revision'],
				]
			);
			if ( ! in_array( $advanced['state'], [ 'published', 'conflict' ], true ) ) {
				return $advanced;
			}
		}
		return [ 'state' => 'conflict' ];
	}

	/**
	 * Advance the shared receipt revision to invalidate every currency for an attempt.
	 *
	 * Receipt facts remain unchanged. Every report must reference this receipt, so
	 * readers reject earlier dependencies without enumerating report currencies.
	 *
	 * @param array  $scope Historical intent scope, never inferred from the current account.
	 * @param string $expected Receipt revision observed by the caller.
	 * @return array Publication result; conflicts require a fresh receipt and retry.
	 */
	public function invalidate_all( array $scope, string $expected ): array {
		if ( ! is_string( $scope['event_id'] ?? null ) || ! preg_match( '/^pi_[a-zA-Z0-9]+$/D', $scope['event_id'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $expected ) ) {
			return [ 'state' => 'invalid' ];
		}
		$receipt = $this->events->read( $scope );
		if ( 'found' !== $receipt['state'] ) {
			return $receipt;
		}
		if ( 'intent_context' !== ( $receipt['event']['kind'] ?? null ) ) {
			return [ 'state' => 'invalid' ];
		}
		if ( $receipt['revision'] !== $expected ) {
			return [
				'state'    => 'conflict',
				'revision' => $receipt['revision'],
			];
		}
		$result = $this->events->publish( $scope, $expected, $receipt['event'] );
		if ( 'published' !== $result['state'] ) {
			return $result;
		}
		$current = $this->events->read( $scope );
		if ( 'found' !== $current['state'] ) {
			return $current;
		}
		return $current['revision'] === $result['revision'] ? $result : [
			'state'    => 'conflict',
			'revision' => $current['revision'],
		];
	}

	/**
	 * Invalidate against the caller's observed report head before recovery.
	 *
	 * A conflicting invalidation must be retried by its caller with a fresh head.
	 *
	 * @param array  $scope Intent scope.
	 * @param string $currency Report currency.
	 * @param string $expected Revision to invalidate, or empty for first observation.
	 * @return array Conditional publication result.
	 */
	public function invalidate( array $scope, string $currency, string $expected ): array {
		$report = $this->report_scope( $scope, $currency );
		if ( null === $report ) {
			return [ 'state' => 'invalid' ];
		}
		$result = $this->events->publish( $report, $expected, [ 'kind' => 'report_invalidated' ] );
		if ( 'published' !== $result['state'] ) {
			return $result;
		}
		// Event retries may resolve an ancestor; only a current invalidation satisfies this operation.
		$current = $this->events->read( $report );
		if ( 'found' !== $current['state'] ) {
			return $current;
		}
		return $current['revision'] === $result['revision'] ? $result : [
			'state'    => 'conflict',
			'revision' => $current['revision'],
		];
	}

	/**
	 * Publish exact dependencies against the revision observed before retrieval.
	 *
	 * This is not a monetary qualification API. No totals or complete state are emitted.
	 *
	 * @param array      $scope Intent scope.
	 * @param string     $currency Report currency.
	 * @param string     $expected Head observed before retrieval.
	 * @param array      $dependencies Event IDs mapped to immutable revision hashes.
	 * @param array|null $retrieval Local fetch interval, not a provider snapshot timestamp.
	 * @return array Conditional publication result or unqualified dependencies.
	 */
	public function publish( array $scope, string $currency, string $expected, array $dependencies, ?array $retrieval = null ): array {
		$report = $this->report_scope( $scope, $currency );
		if ( null === $report || ! $this->valid_dependencies( $scope, $dependencies ) || ! $this->valid_retrieval( $retrieval ) ) {
			return [ 'state' => 'invalid' ];
		}
		$state = $this->dependency_state( $scope, $dependencies );
		if ( 'observed' !== $state ) {
			return [ 'state' => $state ];
		}
		ksort( $dependencies );
		$result = $this->events->publish(
			$report,
			$expected,
			[
				'kind'               => 'report_observation',
				'intent_id'          => $scope['event_id'],
				'reporting_currency' => strtoupper( $currency ),
				'dependencies'       => $dependencies,
				'retrieval'          => null === $retrieval ? null : [
					'started_at'   => $retrieval['started_at'],
					'completed_at' => $retrieval['completed_at'],
				],
			]
		);
		if ( 'published' !== $result['state'] ) {
			return $result;
		}
		// A retried ancestor must not acknowledge a report invalidated by a later operation.
		$current = $this->events->read( $report );
		if ( 'found' !== $current['state'] ) {
			return $current;
		}
		return $current['revision'] === $result['revision'] ? $result : [
			'state'    => 'conflict',
			'revision' => $current['revision'],
		];
	}

	/**
	 * Resolve the latest observation, withholding it after invalidation or source changes.
	 *
	 * @param array  $scope Intent scope.
	 * @param string $currency Report currency.
	 * @return array Observation, stale state, or storage failure. Never complete coverage.
	 */
	public function read( array $scope, string $currency ): array {
		$report = $this->report_scope( $scope, $currency );
		if ( null === $report ) {
			return [ 'state' => 'invalid' ];
		}
		$result = $this->events->read( $report );
		if ( 'found' !== $result['state'] ) {
			return $result;
		}
		if ( 'report_invalidated' === ( $result['event']['kind'] ?? null ) ) {
			return [
				'state'    => 'stale',
				'revision' => $result['revision'],
			];
		}
		$event = $result['event'];
		if ( ! $this->valid_retrieval( $event['retrieval'] ?? null ) ) {
			return [ 'state' => 'invalid' ];
		}
		if ( 'report_observation' !== ( $event['kind'] ?? null ) || ( $event['intent_id'] ?? null ) !== $scope['event_id'] || ( $event['reporting_currency'] ?? null ) !== strtoupper( $currency ) || ! is_array( $event['dependencies'] ?? null ) || ! $this->valid_dependencies( $scope, $event['dependencies'] ) ) {
			return [ 'state' => 'invalid' ];
		}
		$state = $this->dependency_state( $scope, $event['dependencies'] );
		$after = $this->events->read( $report );
		if ( 'found' !== $after['state'] ) {
			return $after;
		}
		if ( $after['revision'] !== $result['revision'] ) {
			$state = 'stale';
		}
		return 'observed' === $state ? [
			'state'        => $state,
			'revision'     => $result['revision'],
			'dependencies' => $event['dependencies'],
			'retrieval'    => $event['retrieval'] ?? null,
		] : [
			'state'    => $state,
			'revision' => $after['revision'],
		];
	}

	/**
	 * Resolve exact published evidence for a presentation adapter.
	 *
	 * This performs local reads only and never enumerates unrelated event heads.
	 * Monetary qualification and complete membership remain provider concerns.
	 * The final read checks invalidation while dependencies were being resolved;
	 * it does not promise an atomic provider snapshot or future freshness.
	 *
	 * @param array  $scope Historical intent scope.
	 * @param string $currency Requested reporting currency.
	 * @return array Observation with exact events, or failure without event rows.
	 */
	public function read_observations( array $scope, string $currency ): array {
		$report = $this->read( $scope, $currency );
		if ( 'observed' !== $report['state'] ) {
			return $report;
		}
		$events = [];
		foreach ( $report['dependencies'] as $id => $revision ) {
			$dependency             = $scope;
			$dependency['event_id'] = $id;
			$source                 = $this->events->read_revision( $dependency, $revision );
			if ( 'found' !== $source['state'] ) {
				return [ 'state' => 'missing' === $source['state'] ? 'stale' : $source['state'] ];
			}
			if ( $source['head_revision'] !== $revision ) {
				return [ 'state' => 'stale' ];
			}
			$events[ $id ] = [
				'revision' => $revision,
				'event'    => $source['event'],
			];
		}
		$after = $this->read( $scope, $currency );
		if ( 'observed' !== $after['state'] ) {
			return $after;
		}
		if ( $after['revision'] !== $report['revision'] ) {
			return [
				'state'    => 'stale',
				'revision' => $after['revision'],
			];
		}
		$report['events'] = $events;
		return $report;
	}

	/**
	 * Validate an optional local wall-clock interval without inventing missing timing.
	 *
	 * @param mixed $retrieval Stored or supplied interval.
	 * @return bool Whether the interval is absent or structurally valid.
	 */
	private function valid_retrieval( $retrieval ): bool {
		return null === $retrieval || ( is_array( $retrieval ) && 2 === count( $retrieval ) && is_int( $retrieval['started_at'] ?? null ) && is_int( $retrieval['completed_at'] ?? null ) && $retrieval['started_at'] > 0 && $retrieval['completed_at'] >= $retrieval['started_at'] );
	}

	/**
	 * Require a receipt dependency and a bounded set of well-formed references.
	 *
	 * @param array $scope Intent scope.
	 * @param array $dependencies References.
	 * @return bool Whether structurally valid, not monetarily qualified.
	 */
	private function valid_dependencies( array $scope, array $dependencies ): bool {
		if ( empty( $dependencies ) || count( $dependencies ) > 128 || ! isset( $dependencies[ $scope['event_id'] ] ) ) {
			return false;
		}
		foreach ( $dependencies as $id => $revision ) {
			if ( ! is_string( $id ) || ! preg_match( '/^[a-z]+_[a-zA-Z0-9]+$/D', $id ) || ! is_string( $revision ) || ! preg_match( '/^[a-f0-9]{64}$/D', $revision ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check exact references without substituting newer evidence.
	 *
	 * @param array $scope Intent scope.
	 * @param array $dependencies References.
	 * @return string Observed, stale or storage error.
	 */
	private function dependency_state( array $scope, array $dependencies ): string {
		foreach ( $dependencies as $id => $revision ) {
			$dependency             = $scope;
			$dependency['event_id'] = $id;
			$read                   = $this->events->read_revision( $dependency, $revision );
			if ( 'found' !== $read['state'] ) {
				return 'missing' === $read['state'] ? 'stale' : $read['state'];
			}
			if ( $read['head_revision'] !== $revision ) {
				return 'stale';
			}
		}
		return 'observed';
	}

	/**
	 * Preserve attempt and currency identity in a separate event partition.
	 *
	 * @param array  $scope Intent scope, validated further by event storage.
	 * @param string $currency Report currency.
	 * @return array|null Report scope or invalid input.
	 */
	private function report_scope( array $scope, string $currency ): ?array {
		if ( ! is_string( $scope['event_id'] ?? null ) || ! preg_match( '/^pi_[a-zA-Z0-9]+$/D', $scope['event_id'] ) || ! preg_match( '/^[a-zA-Z]{3}$/D', $currency ) ) {
			return null;
		}
		$scope['event_id'] = 'report_' . hash( 'sha256', $scope['event_id'] . ':' . strtoupper( $currency ) );
		return $scope;
	}
}

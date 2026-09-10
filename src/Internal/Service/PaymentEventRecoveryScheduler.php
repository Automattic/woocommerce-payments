<?php
/**
 * Bounded background recovery for persisted payment receipts.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** Keeps provider retrieval out of checkout and report rendering. */
class PaymentEventRecoveryScheduler {
	const HOOK  = 'wcpay_recover_payment_events';
	const GROUP = 'wcpay-reporting';

	/**
	 * Receipt storage.
	 *
	 * @var PaymentEventRepository
	 */
	private $repository;
	/**
	 * Evidence recovery.
	 *
	 * @var PaymentEventRecovery
	 */
	private $recovery;

	/**
	 * Construct without scheduling work.
	 *
	 * @param PaymentEventRepository $repository Receipt storage.
	 * @param PaymentEventRecovery   $recovery Evidence retrieval.
	 */
	public function __construct( PaymentEventRepository $repository, PaymentEventRecovery $recovery ) {
		$this->repository = $repository;
		$this->recovery   = $recovery;
	}

	/**
	 * Enqueue only an existing intent receipt, with canonical arguments for deduplication.
	 *
	 * @param array  $scope Account/site/mode/order/intent partition.
	 * @param string $currency Requested reporting currency.
	 * @return int Action ID, or zero when unqualified/already queued.
	 * @throws \RuntimeException When no recovery action could be scheduled.
	 */
	public function enqueue( array $scope, string $currency ): int {
		if ( ! preg_match( '/^[a-zA-Z]{3}$/D', $currency ) ) {
			return 0;
		}
		$receipt = $this->repository->read( $scope );
		if ( 'unavailable' === $receipt['state'] ) {
			throw new \RuntimeException( 'Payment receipt storage is unavailable.' );
		}
		if ( 'found' !== $receipt['state'] || 'intent_context' !== ( $receipt['event']['kind'] ?? null ) ) {
			return 0;
		}
		return $this->enqueue_scope( $scope, $currency );
	}

	/**
	 * Queue an authorized discovery candidate without requiring an existing receipt.
	 *
	 * @param array  $scope Expected account/site/mode/order and candidate charge ID.
	 * @param string $currency Reporting currency.
	 * @return int Action ID or zero for invalid/already queued work.
	 */
	public function enqueue_candidate( array $scope, string $currency ): int {
		if ( ! $this->is_candidate( $scope ) || ! preg_match( '/^[A-Z]{3}$/D', $currency ) ) {
			return 0;
		}
		return $this->enqueue_scope( $scope, $currency );
	}

	/**
	 * Run one attempt. Exhaustion throws so Action Scheduler records a failed action.
	 *
	 * @param array  $scope Persisted receipt or authorized candidate partition.
	 * @param string $currency Reporting currency at enqueue time.
	 * @param int    $attempt Zero-based attempt number.
	 * @throws \RuntimeException When recovery cannot complete or retry cannot be scheduled.
	 */
	public function run( array $scope, string $currency, int $attempt = 0 ): void {
		if ( $attempt < 0 || $attempt > 3 ) {
			throw new \RuntimeException( 'Invalid payment recovery attempt.' );
		}
		try {
			if ( $this->is_candidate( $scope ) ) {
				$context = [
					'version'    => 1,
					'account_id' => $scope['account_id'],
					'site_id'    => $scope['site_id'],
					'test_mode'  => $scope['test_mode'],
				];
				$result  = $this->recovery->recover_discovered( $scope['order_id'], $scope['event_id'], $context, $currency );
			} else {
				$result = $this->recovery->recover( $scope, $currency );
			}
		} catch ( \Throwable $exception ) {
			$result = [ 'state' => 'incomplete' ];
		}
		if ( 'observed' === ( $result['state'] ?? null ) ) {
			return;
		}
		if ( 3 === $attempt || in_array( $result['state'] ?? null, [ 'invalid', 'inconsistent' ], true ) ) {
			throw new \RuntimeException( 'Payment evidence recovery remains incomplete.' );
		}
		ksort( $scope );
		$args   = [ $scope, strtoupper( $currency ), $attempt + 1 ];
		$delays = [ 60, 300, 1800 ];
		$id     = as_schedule_single_action( time() + $delays[ $attempt ], self::HOOK, $args, $this->group( $scope, strtoupper( $currency ), $attempt + 1 ), true );
		if ( ! $id && ! as_has_scheduled_action( self::HOOK, $args, $this->group( $scope, strtoupper( $currency ), $attempt + 1 ) ) ) {
			throw new \RuntimeException( 'Payment evidence retry could not be scheduled.' );
		}
	}

	/**
	 * Validate candidate partitions independently of receipt existence.
	 *
	 * @param array $scope Candidate partition.
	 * @return bool Whether all identity fields are well formed.
	 */
	private function is_candidate( array $scope ): bool {
		return 5 === count( $scope ) && is_string( $scope['account_id'] ?? null ) && preg_match( '/^acct_[a-zA-Z0-9]+$/D', $scope['account_id'] ) && is_int( $scope['site_id'] ?? null ) && $scope['site_id'] > 0 && is_int( $scope['order_id'] ?? null ) && $scope['order_id'] > 0 && is_bool( $scope['test_mode'] ?? null ) && is_string( $scope['event_id'] ?? null ) && preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $scope['event_id'] );
	}

	/**
	 * Share deduplication and scheduling failure handling for both entry points.
	 *
	 * @throws \RuntimeException When recovery cannot be scheduled.
	 *
	 * @param array  $scope Receipt or candidate partition.
	 * @param string $currency Reporting currency.
	 * @return int Action ID or zero when already queued.
	 */
	private function enqueue_scope( array $scope, string $currency ): int {
		ksort( $scope );
		$args  = [ $scope, strtoupper( $currency ), 0 ];
		$group = $this->group( $scope, strtoupper( $currency ), 0 );
		$id    = as_enqueue_async_action( self::HOOK, $args, $group, true );
		if ( ! $id && ! as_has_scheduled_action( self::HOOK, $args, $group ) ) {
			throw new \RuntimeException( 'Payment evidence recovery could not be scheduled.' );
		}
		return $id;
	}
	/**
	 * AS unique inserts may ignore arguments, so group each receipt and attempt.
	 *
	 * @param array  $scope Canonical receipt scope.
	 * @param string $currency Uppercase reporting currency.
	 * @param int    $attempt Attempt number.
	 * @return string Stable Action Scheduler group.
	 */
	private function group( array $scope, string $currency, int $attempt ): string {
		return self::GROUP . '-' . hash( 'sha256', wp_json_encode( [ $scope, $currency, $attempt ] ) );
	}
}

<?php
/**
 * Tests bounded recovery jobs against the real Action Scheduler store.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\PaymentEventRecovery;
use WCPay\Internal\Service\PaymentEventRecoveryScheduler;
use WCPay\Internal\Service\PaymentEventRepository;

/** Verifies queue identity, retries and visible exhaustion. */
class PaymentEventRecoverySchedulerTest extends \WCPAY_UnitTestCase {
	/** A running old generation cannot suppress recovery for a new invalidation. */
	public function test_new_receipt_generation_queues_while_old_job_runs() {
		$revision   = str_repeat( 'a', 64 );
		$repository = $this->createMock( PaymentEventRepository::class );
		$repository->method( 'read' )->willReturnCallback(
			static function () use ( &$revision ) {
				return [
					'state'    => 'found',
					'revision' => $revision,
					'event'    => [ 'kind' => 'intent_context' ],
				];
			}
		);
		$scheduler = new PaymentEventRecoveryScheduler( $repository, $this->createMock( PaymentEventRecovery::class ) );
		$scope     = [
			'account_id' => 'acct_generation',
			'site_id'    => 123,
			'test_mode'  => true,
			'order_id'   => 987655,
			'event_id'   => 'pi_generation',
		];
		$ids       = [];
		try {
			$ids[] = $scheduler->enqueue( $scope, 'EUR' );
			$this->assertGreaterThan( 0, $ids[0] );
			\ActionScheduler::store()->log_execution( $ids[0] );
			$this->assertSame( 'in-progress', \ActionScheduler::store()->get_status( $ids[0] ) );
			$revision = str_repeat( 'b', 64 );
			$ids[]    = $scheduler->enqueue( $scope, 'EUR' );
			$this->assertGreaterThan( 0, $ids[1] );
			$this->assertNotSame( $ids[0], $ids[1] );
			$this->assertSame( 0, $scheduler->enqueue( $scope, 'EUR' ) );
		} finally {
			foreach ( array_filter( $ids ) as $id ) {
				\ActionScheduler::store()->delete_action( $id );
			}
		}
	}

	/** Workers must resolve lazily with fresh repository dependencies. */
	public function test_container_resolves_transient_workers() {
		$container = wcpay_get_container();
		$first     = $container->get( PaymentEventRecoveryScheduler::class );
		$this->assertInstanceOf( PaymentEventRecoveryScheduler::class, $first );
		$this->assertNotSame( $first, $container->get( PaymentEventRecoveryScheduler::class ) );
		$this->assertInstanceOf( PaymentEventRecovery::class, $container->get( PaymentEventRecovery::class ) );
	}

	/** The real hook forwards queued currency and exposes worker failure. */
	public function test_registered_worker_preserves_job_arguments() {
		$production = [ \WC_Payments::get_order_service(), 'recover_payment_events' ];
		$this->assertSame( 10, has_action( PaymentEventRecoveryScheduler::HOOK, $production ) );
		$scope     = [
			'account_id' => 'acct_callback',
			'site_id'    => 123,
			'test_mode'  => true,
			'order_id'   => 987654,
			'event_id'   => 'pi_callback',
		];
		$scheduler = $this->createMock( PaymentEventRecoveryScheduler::class );
		$scheduler->expects( $this->once() )->method( 'run' )->with( $scope, 'EUR', 2 )->willThrowException( new \RuntimeException( 'Synthetic worker failure' ) );
		$service  = new \WC_Payments_Order_Service( $this->createMock( \WC_Payments_API_Client::class ), null, $scheduler );
		$currency = get_option( 'woocommerce_currency' );
		remove_action( PaymentEventRecoveryScheduler::HOOK, $production, 10 );
		try {
			$service->init_hooks();
			$this->assertSame( 10, has_action( PaymentEventRecoveryScheduler::HOOK, [ $service, 'recover_payment_events' ] ) );
			update_option( 'woocommerce_currency', 'USD' );
			$this->expectException( \RuntimeException::class );
			$this->expectExceptionMessage( 'Synthetic worker failure' );
			/**
			 * Exercise the registered recovery callback with persisted job arguments.
			 *
			 * @since 11.1.0
			 */
			do_action( PaymentEventRecoveryScheduler::HOOK, $scope, 'EUR', 2 );
		} finally {
			update_option( 'woocommerce_currency', $currency );
			global $wp_filter;
			foreach ( $wp_filter as $hook_name => $hook ) {
				foreach ( $hook->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( is_array( $callback['function'] ) && $service === $callback['function'][0] ) {
							remove_filter( $hook_name, $callback['function'], $priority );
						}
					}
				}
			}
			add_action( PaymentEventRecoveryScheduler::HOOK, $production, 10, 3 );
		}
	}

	/** A failed storage recheck must reach the caller's failure handling. */
	public function test_unavailable_receipt_storage_throws() {
		$repository = $this->createMock( PaymentEventRepository::class );
		$repository->method( 'read' )->willReturn( [ 'state' => 'unavailable' ] );
		$scheduler = new PaymentEventRecoveryScheduler( $repository, $this->createMock( PaymentEventRecovery::class ) );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Payment receipt storage is unavailable.' );
		$scheduler->enqueue( [ 'event_id' => 'pi_unavailable' ], 'EUR' );
	}

	/** Queue rejection must be distinguishable from an existing action. */
	public function test_initial_queue_failure_is_visible() {
		$repository = $this->createMock( PaymentEventRepository::class );
		$repository->method( 'read' )->willReturn(
			[
				'state' => 'found',
				'event' => [ 'kind' => 'intent_context' ],
			]
		);
		$scheduler = new PaymentEventRecoveryScheduler( $repository, $this->createMock( PaymentEventRecovery::class ) );
		$reject    = static function ( $pre, $hook ) {
			return PaymentEventRecoveryScheduler::HOOK === $hook ? 0 : $pre;
		};
		add_filter( 'pre_as_enqueue_async_action', $reject, 10, 2 );
		try {
			$this->expectException( \RuntimeException::class );
			$this->expectExceptionMessage( 'Payment evidence recovery could not be scheduled.' );
			$scheduler->enqueue( [ 'event_id' => 'pi_queuefailure' ], 'EUR' );
		} finally {
			remove_filter( 'pre_as_enqueue_async_action', $reject, 10 );
		}
	}

	/** A stored receipt can queue once and retry without silently succeeding. */
	public function test_receipt_queue_and_bounded_retry() {
		$scope = [
			'account_id' => 'acct_schedulerfixture',
			'site_id'    => 123,
			'test_mode'  => true,
			'order_id'   => 987654,
			'event_id'   => 'pi_fixture',
		];
		ksort( $scope );
		$repository = $this->createMock( PaymentEventRepository::class );
		$reads      = 0;
		$repository->method( 'read' )->willReturnCallback(
			static function () use ( &$reads ) {
				return 1 === ++$reads ? [ 'state' => 'missing' ] : [
					'state' => 'found',
					'event' => [ 'kind' => 'intent_context' ],
				];
			}
		);
		$recovery = $this->createMock( PaymentEventRecovery::class );
		$recovery->expects( $this->exactly( 4 ) )->method( 'recover' )->with( $scope, 'EUR' )->willThrowException( new \RuntimeException( 'Synthetic retrieval failure' ) );
		$scheduler = new PaymentEventRecoveryScheduler( $repository, $recovery );
		$ids       = [];
		foreach ( [ 0, 1 ] as $attempt ) {
			$this->assertFalse( as_has_scheduled_action( PaymentEventRecoveryScheduler::HOOK, [ $scope, 'EUR', $attempt ] ) );
		}
		try {
			$this->assertSame( 0, $scheduler->enqueue( $scope, 'EUR' ) );
			$id = $scheduler->enqueue( array_reverse( $scope, true ), 'eur' );
			$this->assertGreaterThan( 0, $id );
			$ids[] = $id;
			$this->assertSame( 0, $scheduler->enqueue( $scope, 'EUR' ) );
			$other_id = $scheduler->enqueue( array_merge( $scope, [ 'event_id' => 'pi_other' ] ), 'EUR' );
			$this->assertGreaterThan( 0, $other_id );
			$ids[] = $other_id;
			$this->assertNotSame( \ActionScheduler::store()->fetch_action( $id )->get_group(), \ActionScheduler::store()->fetch_action( $other_id )->get_group() );
			$this->assertSame( [ $scope, 'EUR', 0 ], \ActionScheduler::store()->fetch_action( $id )->get_args() );
			$start = time();
			global $wp_filter;
			$previous_hook = isset( $wp_filter[ PaymentEventRecoveryScheduler::HOOK ] ) ? clone $wp_filter[ PaymentEventRecoveryScheduler::HOOK ] : null;
			remove_all_actions( PaymentEventRecoveryScheduler::HOOK );
			add_action( PaymentEventRecoveryScheduler::HOOK, [ $scheduler, 'run' ], 10, 3 );
			\ActionScheduler_QueueRunner::instance()->process_action( $id, '43781-test' );
			$retry_ids = as_get_scheduled_actions(
				[
					'hook'   => PaymentEventRecoveryScheduler::HOOK,
					'args'   => [ $scope, 'EUR', 1 ],
					'status' => 'pending',
				],
				'ids'
			);
			$ids       = array_merge( $ids, $retry_ids );
			$this->assertCount( 1, $retry_ids );
			$this->assertGreaterThanOrEqual( $start + 60, \ActionScheduler::store()->fetch_action( $retry_ids[0] )->get_schedule()->get_date()->getTimestamp() );
			$current_id = $retry_ids[0];
			foreach ( [
				1 => 300,
				2 => 1800,
			] as $attempt => $delay ) {
				$start = time();
				\ActionScheduler_QueueRunner::instance()->process_action( $current_id, '43781-test' );
				$this->assertSame( 'complete', \ActionScheduler::store()->get_status( $current_id ) );
				$next_ids = as_get_scheduled_actions(
					[
						'hook'   => PaymentEventRecoveryScheduler::HOOK,
						'args'   => [ $scope, 'EUR', $attempt + 1 ],
						'status' => 'pending',
					],
					'ids'
				);
				$ids      = array_merge( $ids, $next_ids );
				$this->assertCount( 1, $next_ids );
				$current_id = $next_ids[0];
				$this->assertGreaterThanOrEqual( $start + $delay, \ActionScheduler::store()->fetch_action( $current_id )->get_schedule()->get_date()->getTimestamp() );
			}
			\ActionScheduler_QueueRunner::instance()->process_action( $current_id, '43781-test' );
			$this->assertSame( 'failed', \ActionScheduler::store()->get_status( $current_id ) );
			$this->assertFalse( as_has_scheduled_action( PaymentEventRecoveryScheduler::HOOK, [ $scope, 'EUR', 4 ] ) );

		} finally {
			remove_action( PaymentEventRecoveryScheduler::HOOK, [ $scheduler, 'run' ], 10 );
			if ( isset( $previous_hook ) ) {
				// Restore the exact hook state isolated by this runner test.
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$wp_filter[ PaymentEventRecoveryScheduler::HOOK ] = $previous_hook;
			}
			foreach ( $ids as $id ) {
				\ActionScheduler::store()->delete_action( $id );
			}
		}
	}
	/** A rejected retry insertion must not be reported as successful recovery. */
	public function test_retry_schedule_failure_is_visible() {
		$scope = [
			'account_id' => 'acct_schedulerfixture',
			'site_id'    => 123,
			'test_mode'  => true,
			'order_id'   => 987654,
			'event_id'   => 'pi_schedulefailure',
		];
		ksort( $scope );
		$recovery = $this->createMock( PaymentEventRecovery::class );
		$recovery->expects( $this->once() )->method( 'recover' )->willReturn( [ 'state' => 'incomplete' ] );
		$scheduler = new PaymentEventRecoveryScheduler( $this->createMock( PaymentEventRepository::class ), $recovery );
		$reject    = static function ( $pre, $timestamp, $hook ) {
			return PaymentEventRecoveryScheduler::HOOK === $hook ? 0 : $pre;
		};
		add_filter( 'pre_as_schedule_single_action', $reject, 10, 3 );
		try {
			$this->expectException( \RuntimeException::class );
			$this->expectExceptionMessage( 'Payment evidence retry could not be scheduled.' );
			$scheduler->run( $scope, 'EUR' );
		} finally {
			remove_filter( 'pre_as_schedule_single_action', $reject, 10 );
			$this->assertFalse( as_has_scheduled_action( PaymentEventRecoveryScheduler::HOOK, [ $scope, 'EUR', 1 ] ) );
		}
	}
	/** Candidate jobs have a distinct identity and use discovery recovery. */
	public function test_candidate_job(): void {
		$scope    = [
			'account_id' => 'acct_candidate',
			'site_id'    => 123,
			'test_mode'  => false,
			'order_id'   => 987659,
			'event_id'   => 'ch_candidate',
		];
		$context  = [
			'version'    => 1,
			'account_id' => 'acct_candidate',
			'site_id'    => 123,
			'test_mode'  => false,
		];
		$recovery = $this->createMock( PaymentEventRecovery::class );
		$recovery->expects( $this->exactly( 2 ) )->method( 'recover_discovered' )->with( 987659, 'ch_candidate', $context, 'EUR' )->willReturnOnConsecutiveCalls( [ 'state' => 'incomplete' ], [ 'state' => 'observed' ] );
		$recovery->expects( $this->never() )->method( 'recover' );
		$scheduler = new PaymentEventRecoveryScheduler( $this->createMock( PaymentEventRepository::class ), $recovery );
		$service   = new \WC_Payments_Order_Service( $this->createMock( \WC_Payments_API_Client::class ), null, $scheduler );
		global $wp_filter;
		$previous_hook = clone $wp_filter[ PaymentEventRecoveryScheduler::HOOK ];
		remove_all_actions( PaymentEventRecoveryScheduler::HOOK );
		add_action( PaymentEventRecoveryScheduler::HOOK, [ $service, 'recover_payment_events' ], 10, 3 );
		$id = 0;
		ksort( $scope );
		try {
			$id = $scheduler->enqueue_candidate( $scope, 'EUR' );
			$this->assertGreaterThan( 0, $id );
			$this->assertSame( 0, $scheduler->enqueue_candidate( $scope, 'EUR' ) );
			\ActionScheduler_QueueRunner::instance()->process_action( $id, '43781-candidate-test' );
			$this->assertSame( 'complete', \ActionScheduler::store()->get_status( $id ) );
			$this->assertTrue( as_has_scheduled_action( PaymentEventRecoveryScheduler::HOOK, [ $scope, 'EUR', 1 ] ) );
			$retry_id = as_next_scheduled_action( PaymentEventRecoveryScheduler::HOOK, [ $scope, 'EUR', 1 ] );
			$this->assertIsInt( $retry_id );
			$retry_ids = as_get_scheduled_actions(
				[
					'hook'   => PaymentEventRecoveryScheduler::HOOK,
					'args'   => [ $scope, 'EUR', 1 ],
					'status' => 'pending',
				],
				'ids'
			);
			$this->assertCount( 1, $retry_ids );
			\ActionScheduler_QueueRunner::instance()->process_action( $retry_ids[0], '43781-candidate-test' );
			$this->assertSame( 'complete', \ActionScheduler::store()->get_status( $retry_ids[0] ) );
			$this->assertFalse( as_has_scheduled_action( PaymentEventRecoveryScheduler::HOOK, [ $scope, 'EUR', 2 ] ) );
		} finally {
			// Restore the hook isolated for this queue runner test.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_filter[ PaymentEventRecoveryScheduler::HOOK ] = $previous_hook;
			foreach ( as_get_scheduled_actions(
				[
					'hook' => PaymentEventRecoveryScheduler::HOOK,
					'args' => [ $scope, 'EUR', 1 ],
				],
				'ids'
			) as $retry_id ) {
				\ActionScheduler::store()->delete_action( $retry_id );
			}
			if ( $id ) {
				\ActionScheduler::store()->delete_action( $id ); }
		}
	}
}

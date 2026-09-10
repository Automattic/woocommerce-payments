<?php
/**
 * Discovery queue integration.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\PaymentAttemptDiscovery;
use WCPay\Internal\Service\PaymentAttemptDiscoveryScheduler;
use WCPay\Internal\Service\PaymentEventRepository;
use WCPay\Internal\Service\PaymentEventRecoveryScheduler;

/** Executes only task-owned actions through the queue runner. */
class PaymentAttemptDiscoverySchedulerTest extends \WCPAY_UnitTestCase {
	/** Authorized start queues a generation bound to the canonical order. */
	public function test_authorized_start(): void {
		$user  = get_current_user_id();
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );
		$order = new \WC_Order();
		$order->save();
		$discovery = $this->createMock( PaymentAttemptDiscovery::class );
		$discovery->expects( $this->once() )->method( 'resolve_context' )->with( false )->willReturn(
			[
				'version'    => 1,
				'account_id' => 'acct_start',
				'site_id'    => 123,
				'test_mode'  => false,
			]
		);
		$repository = $this->createMock( PaymentEventRepository::class );
		$repository->method( 'is_schema_compatible' )->willReturn( true );
		$scheduler = new PaymentAttemptDiscoveryScheduler( $discovery, $repository, $this->createMock( PaymentEventRecoveryScheduler::class ) );
		$result    = [];
		try {
			$result = $scheduler->start( $order->get_id(), 'EUR', false );
			$this->assertSame( 'queued', $result['state'] );
			$this->assertGreaterThan( 0, $result['action_id'] );
			$this->assertSame( $order->get_id(), $result['scope']['order_id'] );
			$this->assertSame( 'unknown', $result['coverage'] );
		} finally {
			if ( ! empty( $result['action_id'] ) ) {
				\ActionScheduler::store()->delete_action( $result['action_id'] ); }
			$order->delete( true );
			wp_set_current_user( $user );
			wp_delete_user( $admin );
		}
	}

	/** Unauthorized requests cannot resolve a connection or enqueue work. */
	public function test_start_requires_order_access(): void {
		$discovery = $this->createMock( PaymentAttemptDiscovery::class );
		$discovery->expects( $this->never() )->method( 'resolve_context' );
		$repository = $this->createMock( PaymentEventRepository::class );
		$repository->expects( $this->never() )->method( 'is_schema_compatible' );
		$scheduler = new PaymentAttemptDiscoveryScheduler( $discovery, $repository, $this->createMock( PaymentEventRecoveryScheduler::class ) );
		$user      = get_current_user_id();
		wp_set_current_user( 0 );
		try {
			$this->assertSame( 'forbidden', $scheduler->start( 456, 'EUR', false )['state'] );
		} finally {
			wp_set_current_user( $user );
		}
	}

	/** Container registration and the production callback retain job boundaries. */
	public function test_registered_worker(): void {
		$this->assertInstanceOf( PaymentAttemptDiscoveryScheduler::class, wcpay_get_container()->get( PaymentAttemptDiscoveryScheduler::class ) );
		$callback = [ \WC_Payments::get_order_service(), 'discover_payment_attempts' ];
		$this->assertSame( 10, has_action( PaymentAttemptDiscoveryScheduler::HOOK, $callback ) );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Invalid payment discovery job.' );
		/**
		 * Run the registered worker with an invalid saved job boundary.
		 *
		 * @since 11.1.0
		 */
		do_action(
			PaymentAttemptDiscoveryScheduler::HOOK,
			[
				'account_id' => 'acct_fixture',
				'site_id'    => 123,
				'order_id'   => 456,
				'test_mode'  => false,
				'event_id'   => 'discovery_registered',
			],
			'EUR',
			20,
			0
		);
	}

	/** A thrown worker failure schedules the first bounded retry. */
	public function test_worker_exception_retry(): void {
		$scope = [
			'account_id' => 'acct_retry',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'discovery_retry',
		];
		ksort( $scope );
		$discovery = $this->createMock( PaymentAttemptDiscovery::class );
		$discovery->method( 'resolve_context' )->willReturn( [] );
		$discovery->method( 'run_step' )->willThrowException( new \RuntimeException( 'Temporary worker failure' ) );
		$scheduler = new PaymentAttemptDiscoveryScheduler( $discovery, $this->createMock( PaymentEventRepository::class ), $this->createMock( PaymentEventRecoveryScheduler::class ) );
		$args      = [ $scope, 'EUR', 0, 1 ];
		try {
			$start = time();
			$scheduler->run( $scope, 'EUR' );
			$ids = as_get_scheduled_actions(
				[
					'hook' => PaymentAttemptDiscoveryScheduler::HOOK,
					'args' => $args,
				],
				'ids'
			);
			$this->assertCount( 1, $ids );
			$this->assertGreaterThanOrEqual( $start + 60, \ActionScheduler::store()->fetch_action( $ids[0] )->get_schedule()->get_date()->getTimestamp() );
		} finally {
			foreach ( as_get_scheduled_actions(
				[
					'hook' => PaymentAttemptDiscoveryScheduler::HOOK,
					'args' => $args,
				],
				'ids'
			) as $id ) {
				\ActionScheduler::store()->delete_action( $id );
			}
		}
	}

	/** Retry exhaustion and bounded truncation are failed jobs, never completion. */
	public function test_terminal_failures(): void {
		$scope = [
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'discovery_failures',
		];
		foreach ( [
			[ [ 'state' => 'incomplete' ], 0, 3 ],
			[
				[
					'state'     => 'queued',
					'has_more'  => true,
					'next_page' => null,
				],
				19,
				0,
			],
		] as $case ) {
			$discovery = $this->createMock( PaymentAttemptDiscovery::class );
			$discovery->method( 'resolve_context' )->willReturn( [] );
			$discovery->method( 'run_step' )->willReturn( $case[0] );
			$scheduler = new PaymentAttemptDiscoveryScheduler( $discovery, $this->createMock( PaymentEventRepository::class ), $this->createMock( PaymentEventRecoveryScheduler::class ) );
			remove_action( PaymentAttemptDiscoveryScheduler::HOOK, [ \WC_Payments::get_order_service(), 'discover_payment_attempts' ], 10 );
			add_action( PaymentAttemptDiscoveryScheduler::HOOK, [ $scheduler, 'run' ], 10, 4 );
			$id = as_enqueue_async_action( PaymentAttemptDiscoveryScheduler::HOOK, [ $scope, 'EUR', $case[1], $case[2] ], '43781-failure-test' );
			try {
				\ActionScheduler_QueueRunner::instance()->process_action( $id, '43781-test' );
				$this->assertSame( 'failed', \ActionScheduler::store()->get_status( $id ) );
			} finally {
				remove_action( PaymentAttemptDiscoveryScheduler::HOOK, [ $scheduler, 'run' ], 10 );
				add_action( PaymentAttemptDiscoveryScheduler::HOOK, [ \WC_Payments::get_order_service(), 'discover_payment_attempts' ], 10, 4 );
				\ActionScheduler::store()->delete_action( $id );
			}
		}
	}

	/** Continuation runs once and terminal listing stops scheduling. */
	public function test_queue_progression(): void {
		$scope = [
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'discovery_queue',
		];
		ksort( $scope );
		$context   = [
			'version'    => 1,
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'test_mode'  => false,
		];
		$discovery = $this->createMock( PaymentAttemptDiscovery::class );
		$discovery->expects( $this->exactly( 2 ) )->method( 'resolve_context' )->with( false )->willReturn( $context );
		$repository = $this->createMock( PaymentEventRepository::class );
		$candidates = $this->createMock( PaymentEventRecoveryScheduler::class );
		$discovery->expects( $this->exactly( 2 ) )->method( 'run_step' )->with( $scope, 'EUR', $context, $repository, $candidates )->willReturnOnConsecutiveCalls(
			[
				'state'     => 'queued',
				'has_more'  => true,
				'next_page' => 2,
			],
			[
				'state'     => 'queued',
				'has_more'  => false,
				'next_page' => null,
			]
		);
		$scheduler = new PaymentAttemptDiscoveryScheduler( $discovery, $repository, $candidates );
		$ids       = [];
		remove_action( PaymentAttemptDiscoveryScheduler::HOOK, [ \WC_Payments::get_order_service(), 'discover_payment_attempts' ], 10 );
		add_action( PaymentAttemptDiscoveryScheduler::HOOK, [ $scheduler, 'run' ], 10, 4 );
		try {
			$ids[] = $scheduler->enqueue( $scope, 'EUR' );
			$this->assertGreaterThan( 0, $ids[0] );
			$this->assertSame( 0, $scheduler->enqueue( $scope, 'EUR' ) );
			$this->assertSame( 0, $scheduler->enqueue( array_reverse( $scope, true ), 'EUR' ) );
			\ActionScheduler_QueueRunner::instance()->process_action( $ids[0], '43781-discovery-test' );
			$this->assertSame( 'complete', \ActionScheduler::store()->get_status( $ids[0] ) );
			$next = as_get_scheduled_actions(
				[
					'hook'   => PaymentAttemptDiscoveryScheduler::HOOK,
					'args'   => [ $scope, 'EUR', 1, 0 ],
					'status' => 'pending',
				],
				'ids'
			);
			$ids  = array_merge( $ids, $next );
			$this->assertCount( 1, $next );
			\ActionScheduler_QueueRunner::instance()->process_action( $next[0], '43781-discovery-test' );
			$this->assertSame( 'complete', \ActionScheduler::store()->get_status( $next[0] ) );
			$this->assertFalse( as_has_scheduled_action( PaymentAttemptDiscoveryScheduler::HOOK, [ $scope, 'EUR', 2, 0 ] ) );
		} finally {
			remove_action( PaymentAttemptDiscoveryScheduler::HOOK, [ $scheduler, 'run' ], 10 );
			add_action( PaymentAttemptDiscoveryScheduler::HOOK, [ \WC_Payments::get_order_service(), 'discover_payment_attempts' ], 10, 4 );
			foreach ( $ids as $id ) {
				\ActionScheduler::store()->delete_action( $id ); }
		}
	}
}

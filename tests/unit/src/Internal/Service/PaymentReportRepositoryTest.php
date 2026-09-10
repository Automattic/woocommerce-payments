<?php
/**
 * Conditional payment report publication against real storage.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\PaymentEventRepository;
use WCPay\Internal\Service\PaymentReportRepository;

/** Report membership qualification belongs to the provider, not this store. */
class PaymentReportRepositoryTest extends \WCPAY_UnitTestCase {
	/** A newer refund membership makes an earlier worker's dependencies stale. */
	public function test_membership_growth_rejects_old_report_dependencies() {
		global $wpdb;
		$db          = clone $wpdb;
		$db->prefix .= 'task_membership_report_test_';
		$events      = new PaymentEventRepository( $db );
		$this->assertTrue( $events->create_schema() );
		try {
			$scope            = [
				'account_id' => 'acct_membership',
				'site_id'    => 123,
				'order_id'   => 456,
				'test_mode'  => false,
				'event_id'   => 'pi_membership',
			];
			$receipt          = $events->publish( $scope, '', [ 'kind' => 'intent_context' ] );
			$member_scope     = array_merge( $scope, [ 'event_id' => 'membership_fixture' ] );
			$empty            = [
				'kind'       => 'refund_membership',
				'refund_ids' => [],
			];
			$original         = $events->publish( $member_scope, '', $empty );
			$reports          = new PaymentReportRepository( $events );
			$old_dependencies = [
				'pi_membership'      => $receipt['revision'],
				'membership_fixture' => $original['revision'],
			];
			$report           = $reports->publish( $scope, 'EUR', '', $old_dependencies );
			$this->assertSame( 'published', $report['state'] );
			$this->assertNull( $reports->read( $scope, 'EUR' )['retrieval'] );
			$observations = $reports->read_observations( $scope, 'EUR' );
			$this->assertSame( 'observed', $observations['state'] );
			$this->assertSame( $report['revision'], $observations['revision'] );
			$this->assertSame( [ 'kind' => 'intent_context' ], $observations['events']['pi_membership']['event'] );
			$this->assertSame( $receipt['revision'], $observations['events']['pi_membership']['revision'] );
			$this->assertSame( $empty, $observations['events']['membership_fixture']['event'] );
			$this->assertArrayNotHasKey( 'total', $observations );
			foreach ( [
				[],
				[
					'started_at'   => 20,
					'completed_at' => 10,
				],
				[
					'started_at'   => 0,
					'completed_at' => 10,
				],
				[
					'started_at'   => 1.5,
					'completed_at' => 10,
				],
			] as $bad_interval ) {
				$this->assertSame( 'invalid', $reports->publish( $scope, 'EUR', $report['revision'], $old_dependencies, $bad_interval )['state'] );
			}
			$this->assertSame( $report['revision'], $reports->read( $scope, 'EUR' )['revision'] );
			$first = $events->publish(
				$member_scope,
				$original['revision'],
				[
					'kind'       => 'refund_membership',
					'refund_ids' => [ 're_first' ],
				]
			);
			$this->assertSame( 'published', $first['state'] );
			$this->assertSame( 'stale', $reports->read( $scope, 'EUR' )['state'] );
			$this->assertSame( 'stale', $reports->read_observations( $scope, 'EUR' )['state'] );
			$this->assertArrayNotHasKey( 'events', $reports->read_observations( $scope, 'EUR' ) );
			$this->assertSame( 'stale', $reports->publish( $scope, 'EUR', $report['revision'], $old_dependencies )['state'] );
			// A retried event-store ancestor is auditable, but cannot become a current report.
			$this->assertSame( $original, $events->publish( $member_scope, '', $empty ) );
			$this->assertSame( 'stale', $reports->publish( $scope, 'EUR', $report['revision'], $old_dependencies )['state'] );
			$competing = $events->publish(
				$member_scope,
				$original['revision'],
				[
					'kind'       => 'refund_membership',
					'refund_ids' => [ 're_second' ],
				]
			);
			$this->assertSame( 'conflict', $competing['state'] );
			$this->assertSame( [ 're_first' ], $events->read( $member_scope )['event']['refund_ids'] );
			$both = $events->publish(
				$member_scope,
				$first['revision'],
				[
					'kind'       => 'refund_membership',
					'refund_ids' => [ 're_first', 're_second' ],
				]
			);
			$this->assertSame( 'published', $both['state'] );
			$new_dependencies = array_merge( $old_dependencies, [ 'membership_fixture' => $both['revision'] ] );
			$this->assertSame( 'published', $reports->publish( $scope, 'EUR', $report['revision'], $new_dependencies )['state'] );
			$this->assertSame( 'observed', $reports->read( $scope, 'EUR' )['state'] );
		} finally {
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_heads" );
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_revisions" );
		}
	}

	/** An invalidation during recovery rejects the old worker and retains audit history. */
	public function test_invalidation_blocks_stale_publication() {
		global $wpdb;
		$db          = clone $wpdb;
		$db->prefix .= 'task_report_repository_test_';
		$events      = new PaymentEventRepository( $db );
		$this->assertTrue( $events->create_schema() );
		try {
			$reports = new PaymentReportRepository( $events );
			$scope   = [
				'account_id' => 'acct_original',
				'site_id'    => 123,
				'order_id'   => 456,
				'test_mode'  => false,
				'event_id'   => 'pi_original',
			];
			$receipt = $events->publish( $scope, '', [ 'kind' => 'intent_context' ] );
			$deps    = [ 'pi_original' => $receipt['revision'] ];
			$first   = $reports->publish( $scope, 'EUR', '', $deps );
			$this->assertSame( 'published', $first['state'] );
			$this->assertSame( 'observed', $reports->read( $scope, 'EUR' )['state'] );
			$this->assertSame( 'missing', $reports->read( $scope, 'USD' )['state'] );
			$invalidated = $reports->invalidate( $scope, 'EUR', $first['revision'] );
			$this->assertSame( 'published', $invalidated['state'] );
			$this->assertSame( 'stale', $reports->read( $scope, 'EUR' )['state'] );
			$this->assertSame( 'conflict', $reports->publish( $scope, 'EUR', '', $deps )['state'] );
			$this->assertSame( 'conflict', $reports->publish( $scope, 'EUR', $first['revision'], $deps )['state'] );
			$rebuilt = $reports->publish( $scope, 'EUR', $invalidated['revision'], $deps );
			$this->assertSame( 'published', $rebuilt['state'] );
			$this->assertSame( 'observed', $reports->read( $scope, 'EUR' )['state'] );
			$this->assertSame( 'conflict', $reports->invalidate( $scope, 'EUR', $first['revision'] )['state'] );
			$this->assertSame( 'published', $reports->publish( $scope, 'USD', '', $deps )['state'] );
			$this->assertSame( 'published', $reports->invalidate_all( $scope, $receipt['revision'] )['state'] );
			$this->assertSame( 'stale', $reports->read( $scope, 'USD' )['state'] );
			$this->assertSame( 'conflict', $reports->invalidate_all( $scope, $receipt['revision'] )['state'] );
			$this->assertSame( [ 'kind' => 'intent_context' ], $events->read( $scope )['event'] );
			$this->assertSame( 'stale', $reports->read( $scope, 'EUR' )['state'] );
			$this->assertSame( 'stale', $reports->publish( $scope, 'EUR', $rebuilt['revision'], $deps )['state'] );
			$this->assertSame( 'invalid', $reports->publish( $scope, 'EUR', $rebuilt['revision'], [] )['state'] );
			$webhook = $reports->invalidate_event( $scope, 'evt_refund' );
			$this->assertSame( 'published', $webhook['state'] );
			$current = $events->read( $scope );
			$latest  = $reports->publish( $scope, 'EUR', $rebuilt['revision'], [ 'pi_original' => $current['revision'] ] );
			$this->assertSame( 'published', $latest['state'] );
			$this->assertSame( $webhook, $reports->invalidate_event( $scope, 'evt_refund' ) );
			$this->assertSame( 'observed', $reports->read( $scope, 'EUR' )['state'] );
			$this->assertSame( $current, $events->read( $scope ) );
			$this->assertSame( 'published', $reports->invalidate_event( $scope, 'evt_dispute' )['state'] );
			$this->assertSame( 'stale', $reports->read( $scope, 'EUR' )['state'] );
			$this->assertSame( 'invalid', $reports->invalidate_event( $scope, 'not_an_event' )['state'] );
			foreach ( [ false, true ] as $committed ) {
				$fault = $this->createMock( PaymentEventRepository::class );
				$fault->method( 'read' )->willReturnCallback( [ $events, 'read' ] );
				$fault->method( 'read_revision' )->willReturnCallback( [ $events, 'read_revision' ] );
				$fault->method( 'publish' )->willReturnCallback(
					static function ( $partition, $expected, $event ) use ( $events, $scope, $committed ) {
						if ( $partition['event_id'] === $scope['event_id'] ) {
							if ( $committed ) {
								$events->publish( $partition, $expected, $event );
							}
							throw new \RuntimeException( 'Interrupted invalidation' );
						}
						return $events->publish( $partition, $expected, $event );
					}
				);
				$event_id = $committed ? 'evt_aftercommit' : 'evt_beforecommit';
				try {
					( new PaymentReportRepository( $fault ) )->invalidate_event( $scope, $event_id );
					$this->fail( 'Expected interrupted invalidation.' );
				} catch ( \RuntimeException $exception ) {
					$this->assertSame( 'Interrupted invalidation', $exception->getMessage() );
				}
				$before_retry = $events->read( $scope );
				$this->assertSame( 'published', $reports->invalidate_event( $scope, $event_id )['state'] );
				$after_retry = $events->read( $scope );
				if ( $committed ) {
					$this->assertSame( $before_retry, $after_retry );
				} else {
					$this->assertNotSame( $before_retry['revision'], $after_retry['revision'] );
				}
				$this->assertSame( 'published', $reports->invalidate_event( $scope, $event_id )['state'] );
				$this->assertSame( $after_retry, $events->read( $scope ) );
			}
			// Pause one delivery after its durable target, then let another event finish and rebuild.
			$paused = $this->createMock( PaymentEventRepository::class );
			$paused->method( 'read' )->willReturnCallback( [ $events, 'read' ] );
			$paused->method( 'read_revision' )->willReturnCallback( [ $events, 'read_revision' ] );
			$paused->method( 'publish' )->willReturnCallback(
				static function ( $partition, $expected, $event ) use ( $events, $scope ) {
					return $partition['event_id'] === $scope['event_id'] ? [ 'state' => 'unavailable' ] : $events->publish( $partition, $expected, $event );
				}
			);
			$this->assertSame( 'unavailable', ( new PaymentReportRepository( $paused ) )->invalidate_event( $scope, 'evt_paused' )['state'] );
			$this->assertSame( 'published', $reports->invalidate_event( $scope, 'evt_concurrent' )['state'] );
			$concurrent  = $events->read( $scope );
			$report_head = $reports->read( $scope, 'EUR' );
			$this->assertSame( 'published', $reports->publish( $scope, 'EUR', $report_head['revision'], [ 'pi_original' => $concurrent['revision'] ] )['state'] );
			$this->assertSame( 'published', $reports->invalidate_event( $scope, 'evt_paused' )['state'] );
			$this->assertSame( $concurrent, $events->read( $scope ) );
			$this->assertSame( 'observed', $reports->read( $scope, 'EUR' )['state'] );
			// An event first observed after that rebuild still invalidates it.
			$this->assertSame( 'published', $reports->invalidate_event( $scope, 'evt_afterrebuild' )['state'] );
			$this->assertSame( 'stale', $reports->read( $scope, 'EUR' )['state'] );
			foreach ( [ false, true ] as $target_committed ) {
				$raced        = false;
				$target_fault = $this->createMock( PaymentEventRepository::class );
				$target_fault->method( 'read' )->willReturnCallback( [ $events, 'read' ] );
				$target_fault->method( 'read_revision' )->willReturnCallback( [ $events, 'read_revision' ] );
				$target_fault->method( 'publish' )->willReturnCallback(
					static function ( $partition, $expected, $event ) use ( $events, $scope, $target_committed, &$raced ) {
						if ( $partition['event_id'] === $scope['event_id'] && ! $raced ) {
							$raced = true;
							// A distinct qualified receipt wins before the invalidation's CAS.
							$events->publish( $partition, $expected, array_merge( $event, [ 'observation' => $target_committed ? 2 : 1 ] ) );
						}
						if ( $partition['event_id'] !== $scope['event_id'] && '' !== $expected ) {
							if ( $target_committed ) {
								$events->publish( $partition, $expected, $event );
							}
							throw new \RuntimeException( 'Interrupted target advancement' );
						}
						return $events->publish( $partition, $expected, $event );
					}
				);
				$event_id = $target_committed ? 'evt_targetcommitted' : 'evt_targetunsaved';
				try {
					( new PaymentReportRepository( $target_fault ) )->invalidate_event( $scope, $event_id );
					$this->fail( 'Expected interrupted target advancement.' );
				} catch ( \RuntimeException $exception ) {
					$this->assertSame( 'Interrupted target advancement', $exception->getMessage() );
				}
				$winning = $events->read( $scope );
				$this->assertSame( 'published', $reports->invalidate_event( $scope, $event_id )['state'] );
				$finished = $events->read( $scope );
				$this->assertSame( $winning['event'], $finished['event'] );
				$this->assertNotSame( $winning['revision'], $finished['revision'] );
				$this->assertSame( 'published', $reports->invalidate_event( $scope, $event_id )['state'] );
				$this->assertSame( $finished, $events->read( $scope ) );
			}
		} finally {
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_heads" );
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_revisions" );
		}
	}
}

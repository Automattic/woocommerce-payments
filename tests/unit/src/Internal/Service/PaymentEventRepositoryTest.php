<?php
/**
 * Real database publication tests for immutable payment evidence.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\PaymentEventRepository;

/** Tests storage semantics independently of monetary qualification. */
class PaymentEventRepositoryTest extends \WCPAY_UnitTestCase {
	/** A discovery generation survives worker reconstruction and stale progress writes. */
	public function test_discovery_page_restart(): void {
		$db    = $this->isolated_database();
		$store = new PaymentEventRepository( $db );
		$this->assertTrue( $store->create_schema() );
		$scope = [
			'account_id' => 'acct_discovery',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'discovery_generation1',
		];
		$page  = [
			'kind'         => 'discovery_progress',
			'currency'     => 'EUR',
			'page'         => 1,
			'pending_page' => [
				'state'      => 'candidates',
				'charge_ids' => [ 'ch_first', 'ch_second' ],
				'has_more'   => true,
				'next_page'  => 2,
			],
		];
		try {
			$this->assertSame( 'invalid', ( new \WCPay\Internal\Service\PaymentAttemptDiscovery() )->capture_page( $scope, 'EUR', 2, '', $store )['state'] );
			$capture = $this->getMockBuilder( \WCPay\Internal\Service\PaymentAttemptDiscovery::class )->onlyMethods( [ 'read_page' ] )->getMock();
			$capture->expects( $this->once() )->method( 'read_page' )->with( 456, false, 1 )->willReturn( $page['pending_page'] );
			$saved = $capture->capture_page( $scope, 'EUR', 1, '', $store );
			$this->assertSame( 'captured', $saved['state'] );
			$restarted = new PaymentEventRepository( $db );
			$this->assertSame( $page, $restarted->read( $scope )['event'] );
			$remaining                               = $page;
			$remaining['pending_page']['charge_ids'] = [ 'ch_second' ];
			$advanced                                = $restarted->publish( $scope, $saved['revision'], $remaining );
			$stale                                   = $store->publish(
				$scope,
				$saved['revision'],
				array_merge(
					$page,
					[
						'page'         => 3,
						'pending_page' => null,
					]
				)
			);
			$this->assertSame( 'conflict', $stale['state'] );
			$this->assertSame( $remaining, ( new PaymentEventRepository( $db ) )->read( $scope )['event'] );
			$this->assertSame( $advanced['revision'], $restarted->read( $scope )['revision'] );
			$discovery = $this->getMockBuilder( \WCPay\Internal\Service\PaymentAttemptDiscovery::class )->onlyMethods( [ 'read_page' ] )->getMock();
			$discovery->expects( $this->never() )->method( 'read_page' );
			$scheduler = $this->createMock( \WCPay\Internal\Service\PaymentEventRecoveryScheduler::class );
			$scheduler->expects( $this->once() )->method( 'enqueue_candidate' )->with( array_merge( $scope, [ 'event_id' => 'ch_second' ] ), 'EUR' )->willReturn( 1 );
			$outcome = $discovery->dispatch_stored_page( $scope, 'EUR', $restarted, $scheduler );
			$this->assertSame( 'queued', $outcome['state'] );
			$progress = $store->read( $scope )['event'];
			$this->assertSame( 'queued', $progress['phase'] );
			$this->assertNull( $progress['pending_page'] );
			$this->assertSame( 2, $progress['next_page'] );
			$this->assertSame( 'unknown', $progress['coverage'] );
			$this->assertSame( $outcome, $discovery->dispatch_stored_page( $scope, 'EUR', new PaymentEventRepository( $db ), $scheduler ) );
			$next_capture = $this->getMockBuilder( \WCPay\Internal\Service\PaymentAttemptDiscovery::class )->onlyMethods( [ 'read_page' ] )->getMock();
			$next_capture->expects( $this->once() )->method( 'read_page' )->with( 456, false, 2 )->willReturn(
				[
					'state'      => 'candidates',
					'charge_ids' => [],
					'has_more'   => false,
					'next_page'  => null,
				]
			);
			$head = $store->read( $scope );
			$this->assertSame( 'incomplete', $next_capture->capture_page( $scope, 'USD', 2, $head['revision'], $store )['state'] );
			$this->assertSame( 'incomplete', $next_capture->capture_page( $scope, 'EUR', 3, $head['revision'], $store )['state'] );
			$this->assertSame( 'incomplete', $next_capture->capture_page( $scope, 'EUR', 2, $saved['revision'], $store )['state'] );
			$this->assertSame( 'captured', $next_capture->capture_page( $scope, 'EUR', 2, $head['revision'], $store )['state'] );
			$terminal = $discovery->dispatch_stored_page( $scope, 'EUR', new PaymentEventRepository( $db ), $scheduler );
			$this->assertSame(
				[
					'state'     => 'queued',
					'coverage'  => 'unknown',
					'has_more'  => false,
					'next_page' => null,
				],
				$terminal
			);
			$this->assertSame( $terminal, $discovery->dispatch_stored_page( $scope, 'EUR', $store, $scheduler ) );
			$worker_scope = array_merge( $scope, [ 'event_id' => 'discovery_worker' ] );
			$worker       = $this->getMockBuilder( \WCPay\Internal\Service\PaymentAttemptDiscovery::class )->onlyMethods( [ 'read_page' ] )->getMock();
			$worker->expects( $this->exactly( 2 ) )->method( 'read_page' )->willReturnCallback(
				function ( $order_id, $mode, $page_number ) {
					$this->assertSame( 456, $order_id );
					$this->assertFalse( $mode );
					$this->assertContains( $page_number, [ 1, 2 ] );
					return [
						'state'      => 'candidates',
						'charge_ids' => [],
						'has_more'   => 1 === $page_number,
						'next_page'  => 1 === $page_number ? 2 : null,
					];
				}
			);
			$context = [
				'version'    => 1,
				'account_id' => $scope['account_id'],
				'site_id'    => $scope['site_id'],
				'test_mode'  => false,
			];
			$this->assertSame(
				[
					'state'     => 'queued',
					'coverage'  => 'unknown',
					'has_more'  => true,
					'next_page' => 2,
				],
				$worker->run_step( $worker_scope, 'EUR', $context, $store, $scheduler )
			);
			$this->assertSame( $terminal, $worker->run_step( $worker_scope, 'EUR', $context, new PaymentEventRepository( $db ), $scheduler ) );
			$this->assertSame( $terminal, $worker->run_step( $worker_scope, 'EUR', $context, new PaymentEventRepository( $db ), $scheduler ) );

			$this->assertSame( 'incomplete', $next_capture->capture_page( $scope, 'EUR', 3, $store->read( $scope )['revision'], $store )['state'] );

			foreach ( [ [ 'account_id' => 'acct_other' ], [ 'test_mode' => true ], [ 'order_id' => 457 ], [ 'site_id' => 124 ], [ 'event_id' => 'discovery_generation2' ] ] as $change ) {
				$this->assertSame( 'missing', $store->read( array_merge( $scope, $change ) )['state'] );
			}
		} finally {
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_heads" );
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_revisions" );
		}
	}

	/** Historical reports resolve their published dependency, never a replacement head. */
	public function test_read_exact_published_revision() {
		$wpdb       = $this->isolated_database();
		$repository = new PaymentEventRepository( $wpdb );
		$this->assertTrue( $repository->create_schema() );
		$scope = [
			'account_id' => 'acct_original',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'txn_fixture',
		];
		try {
			$first  = $repository->publish( $scope, '', [ 'amount' => 4190 ] );
			$second = $repository->publish(
				$scope,
				$first['revision'],
				[
					'amount' => 4190,
					'fee'    => 193,
				]
			);
			$orphan = $repository->publish( $scope, $first['revision'], [ 'amount' => 9999 ] );
			$this->assertSame( 'conflict', $orphan['state'] );
			$this->assertSame(
				[
					'state'         => 'found',
					'revision'      => $first['revision'],
					'head_revision' => $second['revision'],
					'event'         => [ 'amount' => 4190 ],
				],
				$repository->read_revision( $scope, $first['revision'] )
			);
			$this->assertSame(
				[
					'amount' => 4190,
					'fee'    => 193,
				],
				$repository->read_revision( $scope, $second['revision'] )['event']
			);
			$this->assertSame( 'missing', $repository->read_revision( $scope, $orphan['revision'] )['state'] );
			$this->assertSame( 'missing', $repository->read_revision( array_merge( $scope, [ 'test_mode' => true ] ), $first['revision'] )['state'] );
			$this->assertSame( 'invalid', $repository->read_revision( $scope, '' )['state'] );
			$this->assertSame( 'invalid', $repository->read_revision( $scope, 'invalid' )['state'] );
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wcpay_event_revisions SET payload=%s WHERE revision=%s", '{}', $first['revision'] ) );
			$this->assertSame( 'invalid', $repository->read_revision( $scope, $first['revision'] )['state'] );
		} finally {
			$wpdb->query( "DROP TABLE {$wpdb->prefix}wcpay_event_heads" );
			$wpdb->query( "DROP TABLE {$wpdb->prefix}wcpay_event_revisions" );
		}
	}

	/** Verify retry ancestry, lost races and account partitions against real SQL. */
	public function test_publication_and_partition_isolation() {
		$wpdb = $this->isolated_database();
		foreach ( [ 'wcpay_event_heads', 'wcpay_event_revisions' ] as $suffix ) {
			$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $suffix ) ) ), 'Refuse pre-existing repository tables.' );
		}
		$repository = new PaymentEventRepository( $wpdb );
		$this->assertTrue( $repository->create_schema() );
		$scope = [
			'account_id' => 'acct_original',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'txn_fixture',
		];
		try {
			$this->assertSame( [ 'state' => 'missing' ], $repository->read( $scope ) );
			$first = $repository->publish(
				$scope,
				'',
				[
					'amount'   => 4190,
					'currency' => 'EUR',
				]
			);
			$this->assertSame( 'published', $first['state'] );
			$this->assertSame(
				$first,
				$repository->publish(
					array_reverse( $scope, true ),
					'',
					[
						'amount'   => 4190,
						'currency' => 'EUR',
					]
				)
			);
			$second = $repository->publish(
				$scope,
				$first['revision'],
				[
					'amount'   => 4190,
					'currency' => 'EUR',
					'fee'      => 193,
					'net'      => 3997,
				]
			);
			$this->assertSame( 'published', $second['state'] );
			// Retrying an ancestor must not roll back a newer committed observation.
			$this->assertSame(
				$first,
				$repository->publish(
					$scope,
					'',
					[
						'amount'   => 4190,
						'currency' => 'EUR',
					]
				)
			);
			$this->assertSame( 'conflict', $repository->publish( $scope, $first['revision'], [ 'amount' => 4290 ] )['state'] );
			$this->assertSame( $second['revision'], $repository->read( $scope )['revision'] );
			$this->assertSame( 3997, $repository->read( $scope )['event']['net'] );
			foreach ( [ [ 'account_id' => 'acct_other' ], [ 'site_id' => 124 ], [ 'test_mode' => true ], [ 'order_id' => 457 ], [ 'event_id' => 'txn_other' ] ] as $change ) {
				$other = array_merge( $scope, $change );
				$this->assertSame( 'missing', $repository->read( $other )['state'] );
				$this->assertSame( 'published', $repository->publish( $other, '', [ 'amount' => 999 ] )['state'] );
				$this->assertSame( $second['revision'], $repository->read( $scope )['revision'] );
			}
			$this->assertSame( 'invalid', $repository->publish( [], '', [] )['state'] );
			$this->assertSame( 'invalid', $repository->publish( $scope, 'invalid', [] )['state'] );
			// A real failed UPDATE must not be reported as a competing observation.
			$fail  = static function ( $sql ) {
				return 0 === strpos( $sql, 'UPDATE ' ) && false !== strpos( $sql, 'wcpay_event_heads' ) ? 'SELECT * FROM task_43781_missing_table' : $sql;
			};
			$prior = $wpdb->suppress_errors( true );
			add_filter( 'query', $fail );
			try {
				$this->assertSame(
					'unavailable',
					$repository->publish(
						$scope,
						$second['revision'],
						[
							'amount' => 4190,
							'status' => 'available',
						]
					)['state']
				);
			} finally {
				remove_filter( 'query', $fail );
				$wpdb->suppress_errors( $prior );
			}
			$this->assertSame( $second['revision'], $repository->read( $scope )['revision'] );
			// A full readable chain cannot be advanced beyond the validation budget.
			$deep_scope = array_merge( $scope, [ 'event_id' => 'txn_deep' ] );
			ksort( $deep_scope );
			$partition = hash( 'sha256', wp_json_encode( $deep_scope ) );
			$parent    = '';
			for ( $index = 0; $index < 1000; ++$index ) {
				$payload = wp_json_encode(
					[
						'version' => 1,
						'scope'   => $deep_scope,
						'parent'  => $parent,
						'event'   => [ 'sequence' => $index ],
					]
				);
				$parent  = hash( 'sha256', $payload );
				$this->assertSame(
					1,
					$wpdb->insert(
						$wpdb->prefix . 'wcpay_event_revisions',
						[
							'partition_key' => $partition,
							'revision'      => $parent,
							'payload'       => $payload,
						]
					)
				);
			}
			$this->assertSame(
				1,
				$wpdb->insert(
					$wpdb->prefix . 'wcpay_event_heads',
					[
						'partition_key' => $partition,
						'revision'      => $parent,
					]
				)
			);
			$this->assertSame( 'found', $repository->read( $deep_scope )['state'] );
			$this->assertSame( 'history_limit', $repository->publish( $deep_scope, $parent, [ 'sequence' => 1000 ] )['state'] );
			$this->assertSame( 'found', $repository->read( $deep_scope )['state'] );
			$this->assertSame( $parent, $repository->read( $deep_scope )['revision'] );
			// Tampering with a reachable row must make the report unavailable.
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wcpay_event_revisions SET payload=%s WHERE revision=%s", '{}', $first['revision'] ) );
			$this->assertSame( 'invalid', $repository->read( $scope )['state'] );
		} finally {
			$wpdb->query( "DROP TABLE {$wpdb->prefix}wcpay_event_heads" );
			$wpdb->query( "DROP TABLE {$wpdb->prefix}wcpay_event_revisions" );
		}
	}
	/**
	 * Existing incompatible tables must not be treated as an installed schema.
	 *
	 * @dataProvider incompatible_schema_provider
	 * @param string $suffix Table suffix.
	 * @param string $alter Deliberate incompatible alteration.
	 */
	public function test_incompatible_schema( string $suffix, string $alter ) {
		$wpdb = $this->isolated_database();
		foreach ( [ 'wcpay_event_heads', 'wcpay_event_revisions' ] as $table ) {
			$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $table ) ) ), 'Refuse pre-existing repository tables.' );
		}
		$repository = new PaymentEventRepository( $wpdb );
		$this->assertTrue( $repository->create_schema() );
		$scope = [
			'account_id' => 'acct_original',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'txn_fixture',
		];
		try {
			$first = $repository->publish( $scope, '', [ 'amount' => 4190 ] );
			$this->assertSame( 'published', $first['state'] );
			$this->assertTrue( $repository->create_schema() );
			$this->assertSame( $first['revision'], $repository->read( $scope )['revision'] );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL fragments come only from the fixed test provider.
			$this->assertNotFalse( $wpdb->query( "ALTER TABLE {$wpdb->prefix}{$suffix} {$alter}" ) );
			$this->assertFalse( $repository->create_schema() );
			$this->assertSame( 'unavailable', $repository->read( $scope )['state'] );
			$this->assertSame( 'unavailable', $repository->publish( $scope, $first['revision'], [ 'amount' => 4290 ] )['state'] );
			$this->assertSame( '1', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wcpay_event_revisions" ) );
			$this->assertSame( $first['revision'], $wpdb->get_var( "SELECT revision FROM {$wpdb->prefix}wcpay_event_heads" ) );
		} finally {
			$this->assertNotFalse( $wpdb->query( "DROP TABLE {$wpdb->prefix}wcpay_event_heads" ) );
			$this->assertNotFalse( $wpdb->query( "DROP TABLE {$wpdb->prefix}wcpay_event_revisions" ) );
		}
	}

	/** @return array Incompatible real schemas. */
	public function incompatible_schema_provider(): array {
		return [
			'additional unique key'          => [ 'wcpay_event_revisions', 'ADD UNIQUE KEY single_partition (partition_key)' ],
			'truncated primary key'          => [ 'wcpay_event_heads', 'DROP PRIMARY KEY, ADD PRIMARY KEY (partition_key(32))' ],
			'head without unique key'        => [ 'wcpay_event_heads', 'DROP PRIMARY KEY' ],
			'revision without unique key'    => [ 'wcpay_event_revisions', 'DROP PRIMARY KEY' ],
			'nontransactional engine'        => [ 'wcpay_event_heads', 'ENGINE=MyISAM' ],
			'case insensitive head identity' => [ 'wcpay_event_heads', 'MODIFY partition_key char(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL' ],
		];
	}
	/**
	 * Keep schema mutation fixtures separate from plugin startup tables.
	 *
	 * @return \wpdb Database wrapper with a task-owned table prefix.
	 */
	private function isolated_database(): \wpdb {
		global $wpdb;
		$database          = clone $wpdb;
		$database->prefix .= 'task_event_repository_test_';
		return $database;
	}
}

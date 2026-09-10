<?php
/**
 * Candidate discovery never certifies complete historical membership.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Core\Server\Request\List_Transactions;
use WCPay\Core\Server\Response;
use WCPay\Internal\Service\PaymentAttemptDiscovery;

/** Exercises the real request construction boundary with mocked transport. */
class PaymentAttemptDiscoveryTest extends \WCPAY_UnitTestCase {
	/** Disconnected, changed and malformed identity never yields usable context. */
	public function test_context_unavailable(): void {
		$request = $this->mock_wcpay_request( \WCPay\Core\Server\Request\Get_Account::class, 0, null, null, null, null, true );
		$request->expects( $this->once() )->method( 'get_connection_site_id' )->willReturn( null );
		$request->expects( $this->never() )->method( 'format_response' );
		$this->assertSame( [], ( new PaymentAttemptDiscovery() )->resolve_context( false ) );
		foreach ( [ [ 124, 'acct_valid' ], [ null, 'acct_valid' ], [ 123, 'invalid' ], [ 123, null ] ] as $case ) {
			$request = $this->mock_wcpay_request( \WCPay\Core\Server\Request\Get_Account::class );
			$request->method( 'get_connection_site_id' )->willReturnOnConsecutiveCalls( 123, $case[0] );
			$request->method( 'format_response' )->willReturn( new Response( [ 'account_id' => $case[1] ] ) );
			$this->assertSame( [], ( new PaymentAttemptDiscovery() )->resolve_context( false ) );
		}
	}

	/** Resolve current account without replacing explicit historical payment mode. */
	public function test_resolve_context(): void {
		$request = $this->mock_wcpay_request( \WCPay\Core\Server\Request\Get_Account::class );
		$request->expects( $this->exactly( 2 ) )->method( 'get_connection_site_id' )->willReturn( 123 );
		$request->method( 'format_response' )->willReturn( new Response( [ 'account_id' => 'acct_current' ] ) );
		$this->assertSame(
			[
				'version'    => 1,
				'account_id' => 'acct_current',
				'site_id'    => 123,
				'test_mode'  => true,
			],
			( new PaymentAttemptDiscovery() )->resolve_context( true )
		);
	}

	/** Changed connection context stops work before storage or network access. */
	public function test_worker_connection_change(): void {
		$scope      = [
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'discovery_generation1',
		];
		$context    = [
			'version'    => 1,
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'test_mode'  => false,
		];
		$repository = $this->createMock( \WCPay\Internal\Service\PaymentEventRepository::class );
		$repository->expects( $this->never() )->method( 'read' );
		$scheduler = $this->createMock( \WCPay\Internal\Service\PaymentEventRecoveryScheduler::class );
		$scheduler->expects( $this->never() )->method( 'enqueue_candidate' );
		foreach ( [ [ 'account_id' => 'acct_other' ], [ 'site_id' => 124 ], [ 'test_mode' => true ], [ 'version' => 2 ] ] as $change ) {
			$this->assertSame( 'context_changed', ( new PaymentAttemptDiscovery() )->run_step( $scope, 'EUR', array_merge( $context, $change ), $repository, $scheduler )['reason'] );
		}
	}

	/** Unavailable storage or failed publication never admits dispatch. */
	public function test_capture_storage_failures(): void {
		$scope = [
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'discovery_generation1',
		];
		foreach ( [ 'unavailable', 'invalid' ] as $state ) {
			$repository = $this->createMock( \WCPay\Internal\Service\PaymentEventRepository::class );
			$repository->method( 'read' )->willReturn( [ 'state' => $state ] );
			$repository->expects( $this->never() )->method( 'publish' );
			$discovery = $this->getMockBuilder( PaymentAttemptDiscovery::class )->onlyMethods( [ 'read_page' ] )->getMock();
			$discovery->expects( $this->never() )->method( 'read_page' );
			$this->assertSame( 'incomplete', $discovery->capture_page( $scope, 'EUR', 1, '', $repository )['state'] );
		}
		foreach ( [ 'unavailable', 'conflict', 'history_limit' ] as $state ) {
			$repository = $this->createMock( \WCPay\Internal\Service\PaymentEventRepository::class );
			$repository->expects( $this->once() )->method( 'read' )->willReturn( [ 'state' => 'missing' ] );
			$repository->expects( $this->once() )->method( 'publish' )->willReturn( [ 'state' => $state ] );
			$discovery = $this->getMockBuilder( PaymentAttemptDiscovery::class )->onlyMethods( [ 'read_page' ] )->getMock();
			$discovery->expects( $this->once() )->method( 'read_page' )->with( 456, false, 1 )->willReturn(
				[
					'state'      => 'candidates',
					'charge_ids' => [ 'ch_pending' ],
					'has_more'   => false,
					'next_page'  => null,
				]
			);
			$this->assertSame( 'incomplete', $discovery->capture_page( $scope, 'EUR', 1, '', $repository )['state'] );
		}
	}

	/** An already published ancestor cannot authorize stale continuation. */
	public function test_concurrent_progress(): void {
		$scope      = [
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'order_id'   => 456,
			'test_mode'  => false,
			'event_id'   => 'discovery_generation1',
		];
		$page       = [
			'kind'         => 'discovery_progress',
			'currency'     => 'EUR',
			'page'         => 1,
			'pending_page' => [
				'state'      => 'candidates',
				'charge_ids' => [],
				'has_more'   => true,
				'next_page'  => 2,
			],
		];
		$repository = $this->createMock( \WCPay\Internal\Service\PaymentEventRepository::class );
		$repository->method( 'read' )->with( $scope )->willReturnOnConsecutiveCalls(
			[
				'state'    => 'found',
				'revision' => str_repeat( 'a', 64 ),
				'event'    => $page,
			],
			[
				'state'    => 'found',
				'revision' => str_repeat( 'c', 64 ),
				'event'    => [],
			]
		);
		$repository->expects( $this->once() )->method( 'publish' )->willReturn(
			[
				'state'    => 'published',
				'revision' => str_repeat( 'b', 64 ),
			]
		);
		$scheduler = $this->createMock( \WCPay\Internal\Service\PaymentEventRecoveryScheduler::class );
		$scheduler->expects( $this->never() )->method( 'enqueue_candidate' );
		$result = ( new PaymentAttemptDiscovery() )->dispatch_stored_page( $scope, 'EUR', $repository, $scheduler );
		$this->assertSame( 'incomplete', $result['state'] );
		$this->assertSame( 'concurrent_progress', $result['reason'] );
		$this->assertArrayNotHasKey( 'next_page', $result );
	}

	/** A partial queue failure leaves the page available for retry. */
	public function test_dispatch_page_failure(): void {
		$discovery = $this->getMockBuilder( PaymentAttemptDiscovery::class )->onlyMethods( [ 'read_page' ] )->getMock();
		$discovery->expects( $this->once() )->method( 'read_page' )->with( 123, false, 1 )->willReturn(
			[
				'state'      => 'candidates',
				'coverage'   => 'unknown',
				'charge_ids' => [ 'ch_first', 'ch_second' ],
				'has_more'   => false,
				'next_page'  => null,
			]
		);
		$scheduler = $this->createMock( \WCPay\Internal\Service\PaymentEventRecoveryScheduler::class );
		$calls     = 0;
		$scheduler->method( 'enqueue_candidate' )->willReturnCallback(
			function ( $scope, $currency ) use ( &$calls ) {
				++$calls;
				$this->assertSame( 'acct_fixture', $scope['account_id'] );
				$this->assertSame( 123, $scope['order_id'] );
				$this->assertSame( 'EUR', $currency );
				if ( 2 === $calls ) {
					throw new \RuntimeException( 'Queue unavailable' ); }
				return 3 === $calls ? 0 : $calls;
			}
		);
		$context = [
			'version'    => 1,
			'account_id' => 'acct_fixture',
			'site_id'    => 456,
			'test_mode'  => false,
		];
		$result  = $discovery->dispatch_page( 123, $context, 'EUR', 1, $scheduler );
		$this->assertSame( 'incomplete', $result['state'] );
		$this->assertSame( 1, $result['retry_page'] );
		$this->assertSame( [ 'ch_second' ], $result['pending_page']['charge_ids'] );
		$result = $discovery->dispatch_page( 123, $context, 'EUR', 1, $scheduler, $result['pending_page'] );
		$this->assertSame( 'queued', $result['state'] );
		$this->assertSame( 'unknown', $result['coverage'] );
		$this->assertNull( $result['next_page'] );
		$this->assertSame( 3, $calls );
	}

	/** Invalid saved pages cannot schedule a valid prefix before rejection. */
	public function test_saved_page_validation(): void {
		$discovery = $this->getMockBuilder( PaymentAttemptDiscovery::class )->onlyMethods( [ 'read_page' ] )->getMock();
		$discovery->expects( $this->never() )->method( 'read_page' );
		$scheduler = $this->createMock( \WCPay\Internal\Service\PaymentEventRecoveryScheduler::class );
		$scheduler->expects( $this->never() )->method( 'enqueue_candidate' );
		$context = [
			'version'    => 1,
			'account_id' => 'acct_fixture',
			'site_id'    => 456,
			'test_mode'  => false,
		];
		$base    = [
			'state'      => 'candidates',
			'charge_ids' => [ 'ch_valid' ],
			'has_more'   => false,
			'next_page'  => null,
		];
		foreach ( [
			[ 'charge_ids' => [ 'ch_valid', 'invalid' ] ],
			[ 'charge_ids' => [ 1 => 'ch_valid' ] ],
			[ 'charge_ids' => array_fill( 0, 101, 'ch_valid' ) ],
			[ 'charge_ids' => null ],
			[ 'has_more' => 1 ],
			[
				'has_more'  => true,
				'next_page' => 3,
			],
			[ 'next_page' => 2 ],
		] as $change ) {
			$this->assertSame( [ 'state' => 'invalid' ], $discovery->dispatch_page( 123, $context, 'EUR', 1, $scheduler, array_merge( $base, $change ) ) );
		}
	}

	/** Multiple charge candidates survive without trusting decorated intent IDs. */
	public function test_candidates(): void {
		$request = $this->mock_wcpay_request( List_Transactions::class );
		$request->expects( $this->once() )->method( 'set_filters' )->with(
			[
				'match'       => 'all',
				'order_id_is' => '123',
				'test_mode'   => 1,
			]
		);
		$request->expects( $this->never() )->method( 'set_search' );
		$request->expects( $this->once() )->method( 'set_page' )->with( 1 );
		$request->expects( $this->once() )->method( 'set_page_size' )->with( 100 );
		$request->method( 'format_response' )->willReturn(
			new Response(
				[
					'data' => [
						[
							'charge_id'         => 'ch_old',
							'payment_intent_id' => 'pi_current',
						],
						[ 'charge_id' => 'ch_new' ],
						[ 'charge_id' => 'ch_old' ],
					],
				]
			)
		);
		$result = ( new PaymentAttemptDiscovery() )->read_page( 123, true );
		$this->assertSame( [ 'ch_old', 'ch_new' ], $result['charge_ids'] );
		$this->assertSame( 'unknown', $result['coverage'] );
		$this->assertFalse( $result['has_more'] );
	}
	/** Empty and malformed pages never become complete zero totals. */
	public function test_page_boundaries(): void {
		$read   = function ( array $data, int $page = 1 ): array {
			$request = $this->mock_wcpay_request( List_Transactions::class );
			$request->method( 'format_response' )->willReturn( new Response( $data ) );
			return ( new PaymentAttemptDiscovery() )->read_page( 123, false, $page );
		};
		$result = $read( [ 'data' => [] ] );
		$this->assertSame( 'candidates', $result['state'] );
		$this->assertSame( 'unknown', $result['coverage'] );
		$this->assertSame( [], $result['charge_ids'] );
		$this->assertSame( 'unavailable', $read( [ 'data' => [ [ 'charge_id' => '<invalid>' ] ] ] )['state'] );
		$data   = [ 'data' => array_fill( 0, 100, [ 'charge_id' => 'ch_candidate' ] ) ];
		$result = $read( $data, 20 );
		$this->assertTrue( $result['has_more'] );
		$this->assertNull( $result['next_page'] );
		$this->assertSame( 'invalid', ( new PaymentAttemptDiscovery() )->read_page( 123, false, 21 )['state'] );
		$data['data'][] = [ 'charge_id' => 'ch_extra' ];
		$this->assertSame( 'unavailable', $read( $data )['state'] );
	}
	/** Candidate metadata must bind the original payment to this order and site. */
	public function test_charge_identity(): void {
		$order = new \WC_Order();
		$order->set_order_key( 'wc_order_synthetic_identity' );
		$order->save();
		try {
			$context = [
				'version'    => 1,
				'account_id' => 'acct_fixture',
				'site_id'    => 123,
				'test_mode'  => false,
			];
			$charge  = [
				'id'                      => 'ch_old',
				'payment_intent'          => 'pi_old',
				'amount'                  => 3600,
				'currency'                => 'gbp',
				'livemode'                => true,
				'wcpay_reporting_context' => $context,
				'metadata'                => [
					'order_id'  => (string) $order->get_id(),
					'order_key' => $order->get_order_key(),
					'site_url'  => get_site_url(),
				],
			];
			$reader  = new PaymentAttemptDiscovery();
			$result  = $reader->qualify_charge( $order, 'ch_old', $charge, $context );
			$this->assertSame( 'qualified_identity', $result['state'] );
			$this->assertSame( 'pi_old', $result['scope']['event_id'] );
			$this->assertSame( 3600, $result['receipt']['original_amount'] );
			foreach ( [
				[ 'metadata', 'site_url', 'https://another.example.invalid' ],
				[ 'metadata', 'order_id', '999999999' ],
				[ 'wcpay_reporting_context', 'account_id', 'acct_other' ],
				[ 'wcpay_reporting_context', 'site_id', 124 ],
				[ 'wcpay_reporting_context', 'test_mode', true ],
			] as $mutation ) {
				$changed                                 = $charge;
				$changed[ $mutation[0] ][ $mutation[1] ] = $mutation[2];
				$this->assertSame( 'unqualified', $reader->qualify_charge( $order, 'ch_old', $changed, $context )['state'] );
			}
			foreach ( [
				'livemode'       => false,
				'amount'         => '3600',
				'currency'       => 'GBP',
				'payment_intent' => '',
				'id'             => 'ch_other',
			] as $key => $value ) {
				$changed         = $charge;
				$changed[ $key ] = $value;
				$this->assertSame( 'unqualified', $reader->qualify_charge( $order, 'ch_old', $changed, $context )['state'] );
			}
			$charge['metadata']['order_key'] = 'wc_order_another';
			$this->assertSame( 'unqualified', $reader->qualify_charge( $order, 'ch_old', $charge, $context )['state'] );
		} finally {
			$order->delete( true );
		}
	}
}

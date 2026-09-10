<?php
/**
 * Qualification-to-persistence tests using real order CRUD and event SQL.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\PaymentEventRecorder;
use WCPay\Internal\Service\PaymentEventRepository;
use WCPay\Internal\Service\PaymentReceiptIndex;

/** Keeps captured money and refund debit/reversal evidence separate. */
class PaymentEventRecorderTest extends \WCPAY_UnitTestCase {
	/**
	 * Store exact provider amounts and preserve contradictions without rewriting sales.
	 *
	 * @dataProvider storage_provider
	 * @param bool $hpos Whether HPOS is enabled.
	 * @param bool $test_mode Whether the payment was in test mode.
	 */
	public function test_record_qualified_evidence( bool $hpos, bool $test_mode ) {
		global $wpdb;
		$db          = clone $wpdb;
		$db->prefix .= 'task_event_recorder_test_';
		foreach ( [ 'wcpay_event_heads', 'wcpay_event_revisions', 'options' ] as $suffix ) {
			$this->assertNull( $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $db->prefix . $suffix ) ) ) );
		}
		$repository = new PaymentEventRepository( $db );
		$this->assertTrue( $repository->create_schema() );
		$db->options = $db->prefix . 'options';
		$this->assertNotFalse( $db->query( "CREATE TABLE {$db->options} (option_name varchar(191) NOT NULL PRIMARY KEY, option_value longtext NOT NULL, autoload varchar(20) NOT NULL)" ) );
		$index            = new PaymentReceiptIndex( $db, $repository );
		$recorder         = new PaymentEventRecorder( $repository, $index );
		$previous_storage = get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		update_option( 'woocommerce_custom_orders_table_enabled', $hpos ? 'yes' : 'no' );
		$this->assertSame( $hpos, \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		$order = new \WC_Order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_currency( 'GBP' );
		$order->set_total( 36 );
		$order->update_meta_data( '_charge_id', 'ch_fixture' );
		$order->update_meta_data( '_wcpay_mode', $test_mode ? 'test' : 'prod' );
		$order->save();
		$context = [
			'version'    => 1,
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'test_mode'  => $test_mode,
		];
		$scope   = [
			'account_id' => 'acct_fixture',
			'site_id'    => 123,
			'test_mode'  => $test_mode,
			'order_id'   => $order->get_id(),
			'event_id'   => 'txn_capture',
		];
		$charge  = [
			'id'                      => 'ch_fixture',
			'paid'                    => true,
			'captured'                => true,
			'status'                  => 'succeeded',
			'amount'                  => 3600,
			'amount_captured'         => 3600,
			'currency'                => 'gbp',
			'amount_refunded'         => 0,
			'disputed'                => false,
			'livemode'                => ! $test_mode,
			'wcpay_reporting_context' => $context,
			'balance_transaction'     => [
				'id'       => 'txn_capture',
				'amount'   => 4190,
				'currency' => 'eur',
				'created'  => 1700000000,
				'fee'      => 193,
				'net'      => 3997,
				'status'   => 'pending',
			],
		];
		$refund  = [
			'id'                          => 're_fixture',
			'object'                      => 'refund',
			'charge'                      => 'ch_fixture',
			'currency'                    => 'gbp',
			'amount'                      => 500,
			'status'                      => 'failed',
			'balance_transaction'         => [
				'id'       => 'txn_refund',
				'object'   => 'balance_transaction',
				'source'   => 're_fixture',
				'currency' => 'eur',
				'amount'   => -582,
				'fee'      => 0,
				'net'      => -582,
				'created'  => 1700000001,
			],
			'failure_balance_transaction' => [
				'id'       => 'txn_reversal',
				'object'   => 'balance_transaction',
				'source'   => 're_fixture',
				'currency' => 'eur',
				'amount'   => 580,
				'fee'      => 10,
				'net'      => 570,
				'created'  => 1700000002,
			],
		];
		try {
			$order->update_meta_data( '_intent_id', 'pi_fixture' );
			$order->save_meta_data();
			$intent = \WC_Helper_Intention::create_intention(
				[
					'id'       => 'pi_fixture',
					'amount'   => 3600,
					'currency' => 'gbp',
					'charge'   => [
						'id'       => 'ch_fixture',
						'amount'   => 3600,
						'currency' => 'gbp',
					],
				]
			);
			$this->assertSame( 'incomplete', $recorder->record_intent( $order, $intent )['state'] );
			$intent->set_reporting_context( $context );
			$scheduler = $this->createMock( \WCPay\Internal\Service\PaymentEventRecoveryScheduler::class );
			$scheduler->expects( $this->exactly( 2 ) )->method( 'enqueue' )->with( array_merge( $scope, [ 'event_id' => 'pi_fixture' ] ), get_woocommerce_currency() )->willThrowException( new \RuntimeException( 'Synthetic queue failure' ) );
			$order_service = new \WC_Payments_Order_Service( $this->createMock( \WC_Payments_API_Client::class ), $recorder, $scheduler );
			$order_service->attach_intent_info_to_order( $order, $intent );
			$this->assertSame( 'found', $repository->read( array_merge( $scope, [ 'event_id' => 'pi_fixture' ] ) )['state'] );
			$binding = $recorder->record_intent( $order, $intent );
			$this->assertSame( 'recorded', $binding['state'] );
			$indexed = $index->resolve( 'acct_fixture', 123, $test_mode, 'ch_fixture' );
			$this->assertSame( 'found', $indexed['state'] );
			$this->assertSame( $binding['revision'], $indexed['receipt_revision'] );
			$this->assertEquals( array_merge( $scope, [ 'event_id' => 'pi_fixture' ] ), $indexed['scope'] );
			$this->assertSame( $binding, $recorder->record_intent( $order, $intent ) );
			$order_service->attach_intent_info_to_order( $order, $intent );
			$this->assertSame( $binding['revision'], $repository->read( array_merge( $scope, [ 'event_id' => 'pi_fixture' ] ) )['revision'] );
			$failed_index = $this->createMock( PaymentReceiptIndex::class );
			$failed_index->method( 'bind' )->willReturn( [ 'state' => 'unavailable' ] );
			$unindexed_recorder = new PaymentEventRecorder( $repository, $failed_index );
			$unindexed          = $unindexed_recorder->record_intent( $order, $intent );
			$this->assertSame( 'incomplete', $unindexed['state'] );
			$this->assertSame( 'receipt_index_incomplete', $unindexed['reason'] );
			$this->assertSame( $binding['revision'], $unindexed['revision'] );
			$no_queue = $this->createMock( \WCPay\Internal\Service\PaymentEventRecoveryScheduler::class );
			$no_queue->expects( $this->never() )->method( 'enqueue' );
			( new \WC_Payments_Order_Service( $this->createMock( \WC_Payments_API_Client::class ), $unindexed_recorder, $no_queue ) )->attach_intent_info_to_order( $order, $intent );
			$this->assertSame( 'pi_fixture', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
			$this->assertSame( $binding, $recorder->record_intent( $order, $intent ) );
			$failed_recorder = $this->createMock( PaymentEventRecorder::class );
			$failed_recorder->expects( $this->once() )->method( 'record_intent' )->willThrowException( new \RuntimeException( 'Synthetic storage failure' ) );
			( new \WC_Payments_Order_Service( $this->createMock( \WC_Payments_API_Client::class ), $failed_recorder ) )->attach_intent_info_to_order( $order, $intent );
			$this->assertSame( 'pi_fixture', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
			$intent_scope = array_merge( $scope, [ 'event_id' => 'pi_fixture' ] );
			$this->assertSame( 'ch_fixture', $repository->read( $intent_scope )['event']['charge_id'] );
			$wrong_intent = \WC_Helper_Intention::create_intention(
				[
					'id'       => 'pi_fixture',
					'amount'   => 3600,
					'currency' => 'gbp',
					'charge'   => [
						'id'             => 'ch_fixture',
						'payment_intent' => 'pi_other',
					],
				]
			);
			$wrong_intent->set_reporting_context( $context );
			$this->assertSame( 'incomplete', $recorder->record_intent( $order, $wrong_intent )['state'] );
			$this->assertSame( $binding['revision'], $repository->read( $intent_scope )['revision'] );
			$collector = $this->createMock( \WCPay\Internal\Service\RefundHistoryCollection::class );
			$collector->expects( $this->once() )->method( 'collect' )->with( 'ch_fixture', $test_mode, $context )->willReturn(
				[
					'state'             => 'listed',
					'charge_id'         => 'ch_fixture',
					'reporting_context' => $context,
					'refunds'           => [],
				]
			);
			$recovery = new \WCPay\Internal\Service\PaymentEventRecovery( $repository, $recorder, $collector );
			$this->assertSame( 'incomplete', $recovery->recover( array_merge( $intent_scope, [ 'event_id' => 'pi_missing' ] ), 'EUR' )['state'] );
			$request = $this->mock_wcpay_request( \WCPay\Core\Server\Request\Get_Charge::class, 1, 'ch_fixture' );
			$request->expects( $this->once() )->method( 'set_test_mode' )->with( $test_mode );
			$request->expects( $this->once() )->method( 'set_include_reporting_context' );
			$recovered_charge                              = $charge;
			$recovered_charge['balance_transaction']['id'] = 'txn_recovered';
			$wrong_charge                                  = $recovered_charge;
			$wrong_charge['payment_intent']                = 'pi_other';
			$request->method( 'format_response' )->willReturn( $wrong_charge );
			$this->assertSame( 'charge_intent_mismatch', $recovery->recover( $intent_scope, 'EUR' )['reason'] );
			$this->assertSame( 'missing', $repository->read( array_merge( $scope, [ 'event_id' => 'txn_recovered' ] ) )['state'] );
			$request = $this->mock_wcpay_request( \WCPay\Core\Server\Request\Get_Charge::class, 1, 'ch_fixture' );
			$request->expects( $this->once() )->method( 'set_test_mode' )->with( $test_mode );
			$request->expects( $this->once() )->method( 'set_include_reporting_context' );
			$request->method( 'format_response' )->willReturn( $recovered_charge );
			$recovery_started_at = time();
			$this->assertSame( 'observed', $recovery->recover( $intent_scope, 'EUR' )['state'] );
			$report = ( new \WCPay\Internal\Service\PaymentReportRepository( $repository ) )->read( $intent_scope, 'EUR' );
			$this->assertSame( 'observed', $report['state'] );
			$this->assertCount( 4, $report['dependencies'] );
			$activity_reader = new \WCPay\Internal\Service\PaymentActivityReader( $index, new \WCPay\Internal\Service\PaymentReportRepository( $repository ) );
			$activity        = $activity_reader->read_for_order( $order->get_id(), 'EUR', $test_mode );
			$this->assertSame( 'known', $activity['state'] );
			$this->assertSame( 'unknown', $activity['coverage'] );
			$this->assertNull( $activity['total'] );
			$this->assertCount( 1, $activity['attempts'] );
			$this->assertSame( 4190, $activity['attempts'][0]['rows'][0]['amount'] );
			$this->assertSame( 2, $activity['attempts'][0]['rows'][0]['precision'] );
			$this->assertSame( 'capture', $activity['attempts'][0]['rows'][0]['kind'] );
			$this->assertSame( 'EUR', $activity['attempts'][0]['rows'][0]['currency'] );
			$this->assertSame( [], $activity_reader->read_for_order( $order->get_id(), 'EUR', ! $test_mode )['attempts'] );
			$this->assertSame( 'missing', $activity_reader->read_for_order( $order->get_id(), 'USD', $test_mode )['attempts'][0]['state'] );
			$this->assert_metabox_activity( $activity_reader, $order, [ 'EUR 41.90', 'Fee: EUR 1.93', 'Net amount: EUR 39.97' ] );
			$this->assertIsArray( $report['retrieval'] ?? null );
			$this->assertGreaterThanOrEqual( $recovery_started_at, $report['retrieval']['started_at'] );
			$this->assertGreaterThanOrEqual( $report['retrieval']['started_at'], $report['retrieval']['completed_at'] );
			$this->assertLessThanOrEqual( time(), $report['retrieval']['completed_at'] );
			$this->assertSame( $binding['revision'], $report['dependencies']['pi_fixture'] );
			$this->assertSame( $repository->read( array_merge( $scope, [ 'event_id' => 'txn_recovered' ] ) )['revision'], $report['dependencies']['txn_recovered'] );
			$reports          = new \WCPay\Internal\Service\PaymentReportRepository( $repository );
			$racing_collector = $this->createMock( \WCPay\Internal\Service\RefundHistoryCollection::class );
			$racing_collector->expects( $this->once() )->method( 'collect' )->willReturnCallback(
				function () use ( $reports, $intent_scope, $context ) {
					$current = $reports->read( $intent_scope, 'EUR' );
					$this->assertSame( 'stale', $current['state'] );
					$this->assertSame( 'published', $reports->invalidate( $intent_scope, 'EUR', $current['revision'] )['state'] );
					return [
						'state'             => 'listed',
						'charge_id'         => 'ch_fixture',
						'reporting_context' => $context,
						'refunds'           => [],
					];
				}
			);
			$request = $this->mock_wcpay_request( \WCPay\Core\Server\Request\Get_Charge::class, 1, 'ch_fixture' );
			$request->method( 'format_response' )->willReturn( $recovered_charge );
			$racing_recovery = new \WCPay\Internal\Service\PaymentEventRecovery( $repository, $recorder, $racing_collector, $reports );
			$raced           = $racing_recovery->recover( $intent_scope, 'EUR' );
			$this->assertSame( 'incomplete', $raced['state'] );
			$this->assertSame( 'conflict', $raced['report']['state'] );
			$this->assertSame( 'stale', $reports->read( $intent_scope, 'EUR' )['state'] );
			$order->update_meta_data( '_intent_id', 'pi_later' );
			$order->save_meta_data();
			$this->assertSame( 'pi_later', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
			$this->assertSame( $indexed, $index->resolve( 'acct_fixture', 123, $test_mode, 'ch_fixture' ) );
			$this->assertSame( 'incomplete', $recorder->record_intent( $order, $intent )['state'] );
			$order->set_currency( 'USD' );
			$order->set_total( 99 );
			$order->update_meta_data( '_charge_id', 'ch_later' );
			$order->update_meta_data( '_wcpay_mode', $test_mode ? 'prod' : 'test' );
			$order->save();
			$old_collector = $this->createMock( \WCPay\Internal\Service\RefundHistoryCollection::class );
			$old_collector->expects( $this->once() )->method( 'collect' )->with( 'ch_fixture', $test_mode, $context )->willReturn(
				[
					'state'             => 'listed',
					'charge_id'         => 'ch_fixture',
					'reporting_context' => $context,
					'refunds'           => [],
				]
			);
			$old_request = $this->mock_wcpay_request( \WCPay\Core\Server\Request\Get_Charge::class, 1, 'ch_fixture' );
			$old_request->expects( $this->once() )->method( 'set_test_mode' )->with( $test_mode );
			$old_request->method( 'format_response' )->willReturn( $recovered_charge );
			$old_recovery = new \WCPay\Internal\Service\PaymentEventRecovery( $repository, $recorder, $old_collector, $reports );
			$this->assertSame( 'observed', $old_recovery->recover( $intent_scope, 'EUR' )['state'] );
			$current_order = wc_get_order( $order->get_id() );
			$this->assertSame( 'USD', $current_order->get_currency() );
			$this->assertEquals( 99, $current_order->get_total() );
			$this->assertSame( 'ch_later', $current_order->get_meta( '_charge_id' ) );
			$this->assertSame( $test_mode ? 'prod' : 'test', $current_order->get_meta( '_wcpay_mode' ) );
			$order->set_currency( 'GBP' );
			$order->set_total( 36 );
			$order->update_meta_data( '_charge_id', 'ch_fixture' );
			$order->update_meta_data( '_wcpay_mode', $test_mode ? 'test' : 'prod' );
			$order->save();
			$this->assertSame( 'pi_later', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );

			$this->assertSame( $binding['revision'], $repository->read( $intent_scope )['revision'] );
			$report_head  = $reports->read( $intent_scope, 'EUR' );
			$receipt_deps = [ 'pi_fixture' => $binding['revision'] ];
			$this->assertSame( 'published', $reports->publish( $intent_scope, 'EUR', $report_head['revision'], $receipt_deps )['state'] );
			$this->assertSame( 'published', $reports->publish( $intent_scope, 'USD', '', $receipt_deps )['state'] );
			$webhook_scheduler = new \WCPay\Internal\Service\PaymentEventRecoveryScheduler( $repository, $recovery );
			$webhook           = new \WCPay\Internal\Service\PaymentEventWebhook( $index, $reports, $webhook_scheduler );
			$event             = [
				'id'       => 'evt_historical',
				'type'     => 'charge.refunded',
				'account'  => 'acct_fixture',
				'livemode' => ! $test_mode,
				'data'     => [ 'object' => [ 'id' => 'ch_fixture' ] ],
			];
			$job_scope         = $intent_scope;
			ksort( $job_scope );
			$job_args = [ $job_scope, 'EUR', 0 ];
			try {
				$webhook->process( $event, 123, 'EUR' );
				$this->assertSame( 'stale', $reports->read( $intent_scope, 'EUR' )['state'] );
				$this->assertSame( 'stale', $reports->read( $intent_scope, 'USD' )['state'] );
				$after_webhook = $repository->read( $intent_scope );
				$this->assertNotSame( $binding['revision'], $after_webhook['revision'] );
				$webhook->process( $event, 123, 'EUR' );
				$this->assertSame( $after_webhook, $repository->read( $intent_scope ) );
				$jobs = as_get_scheduled_actions(
					[
						'hook'   => $webhook_scheduler::HOOK,
						'args'   => $job_args,
						'status' => 'pending',
					],
					'ids'
				);
				$this->assertCount( 1, $jobs );
				$this->assertSame( $job_args, \ActionScheduler::store()->fetch_action( $jobs[0] )->get_args() );
				$this->assertSame( 'pi_later', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
			} finally {
				foreach ( as_get_scheduled_actions(
					[
						'hook' => $webhook_scheduler::HOOK,
						'args' => $job_args,
					],
					'ids'
				) as $job_id ) {
					\ActionScheduler::store()->delete_action( $job_id );
				}
			}
			$historical_charge                              = $charge;
			$historical_charge['balance_transaction']['id'] = 'txn_historical';
			$historical_receipt                             = $repository->read( $intent_scope );
			$historical                                     = $recorder->record_historical_capture( $intent_scope, $historical_receipt['revision'], $historical_charge, 'EUR' );
			$this->assertSame( 'recorded', $historical['state'] );
			$this->assertSame( 4190, $repository->read( array_merge( $intent_scope, [ 'event_id' => 'txn_historical' ] ) )['event']['evidence']['amount'] );
			$this->assertSame( 'pi_later', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
			$historical_refund                                      = $refund;
			$historical_refund['id']                                = 're_fixture';
			$historical_refund['balance_transaction']['id']         = 'txn_refund';
			$historical_refund['balance_transaction']['source']     = 're_fixture';
			$historical_refund['failure_balance_transaction']['id'] = 'txn_reversal';
			$historical_refund['failure_balance_transaction']['source'] = 're_fixture';
			$historical_collection                                      = [
				'state'             => 'listed',
				'charge_id'         => 'ch_fixture',
				'reporting_context' => $context,
				'refunds'           => [ $historical_refund ],
			];
			$wrong_empty = array_merge(
				$historical_collection,
				[
					'charge_id' => 'ch_other',
					'refunds'   => [],
				]
			);
			$this->assertSame( 'incomplete', $recorder->record_historical_refunds( $intent_scope, $historical_receipt['revision'], $wrong_empty, 'EUR' )['state'] );
			$this->assertSame( 'incomplete', $recorder->record_refunds( $order, $wrong_empty, 'EUR', $context )['state'] );
			$historical_collection['retrieval'] = [
				'started_at'   => 1700000500,
				'completed_at' => 1700000510,
			];
			$historical_refunds                 = $recorder->record_historical_refunds( $intent_scope, $historical_receipt['revision'], $historical_collection, 'EUR' );
			$this->assertSame( 'recorded', $historical_refunds['state'] );
			$observed_collection = $repository->read( array_merge( $intent_scope, [ 'event_id' => $historical_refunds['collection_id'] ] ) );
			$this->assertSame( $historical_collection['retrieval'], $observed_collection['event']['retrieval'] ?? null );
			$bad_time = array_merge(
				$historical_collection,
				[
					'retrieval' => [
						'started_at'   => 1700000500,
						'completed_at' => 1700000499,
					],
				]
			);
			$this->assertSame( 'incomplete', $recorder->record_historical_refunds( $intent_scope, $historical_receipt['revision'], $bad_time, 'EUR' )['state'] );
			$shrunk = array_merge( $historical_collection, [ 'refunds' => [] ] );
			$this->assertSame( 'incomplete', $recorder->record_historical_refunds( $intent_scope, $historical_receipt['revision'], $shrunk, 'EUR' )['state'] );
			$this->assertSame( -582, $repository->read( array_merge( $intent_scope, [ 'event_id' => 'txn_refund' ] ) )['event']['evidence']['amount'] );
			$this->assertSame( 570, $repository->read( array_merge( $intent_scope, [ 'event_id' => 'txn_reversal' ] ) )['event']['evidence']['net'] );
			$this->assertSame( 'pi_later', wc_get_order( $order->get_id() )->get_meta( '_intent_id' ) );
			$historical_collection['reporting_context']['test_mode'] = ! $test_mode;
			$this->assertSame( 'incomplete', $recorder->record_historical_refunds( $intent_scope, $historical_receipt['revision'], $historical_collection, 'EUR' )['state'] );
			$historical_charge['wcpay_reporting_context']['account_id'] = 'acct_other';
			$this->assertSame( 'incomplete', $recorder->record_historical_capture( $intent_scope, $historical_receipt['revision'], $historical_charge, 'EUR' )['state'] );
			$wrong = $charge;
			$wrong['wcpay_reporting_context']['account_id'] = 'acct_wrong';
			$this->assertSame( 'incomplete', $recorder->record_capture( $order, $wrong, 'EUR', $context )['state'] );
			$this->assertSame( 'missing', $repository->read( $scope )['state'] );
			$without_net = $charge;
			unset( $without_net['balance_transaction']['fee'], $without_net['balance_transaction']['net'] );
			$this->assertSame( 'recorded', $recorder->record_capture( $order, $without_net, 'EUR', $context )['state'] );
			$this->assertSame( 'unavailable', $repository->read( $scope )['event']['evidence']['net_state'] );
			$first = $recorder->record_capture( $order, $charge, 'EUR', $context );
			$this->assertSame( 'recorded', $first['state'] );
			$this->assertSame( $first, $recorder->record_capture( $order, $charge, 'EUR', $context ) );
			$this->assertSame( $first, $recorder->record_capture( $order, $without_net, 'EUR', $context ) );
			$stored = $repository->read( $scope )['event']['evidence'];
			$this->assertSame( 4190, $stored['amount'] );
			$this->assertSame( 3997, $stored['net_amount'] );
			$without_net['balance_transaction']['status'] = 'available';
			$this->assertSame( 'recorded', $recorder->record_capture( $order, $without_net, 'EUR', $context )['state'] );
			$this->assertSame( 'available', $repository->read( $scope )['event']['evidence']['funds_status'] );
			$this->assertSame( 3997, $repository->read( $scope )['event']['evidence']['net_amount'] );
			$charge['balance_transaction']['status'] = 'available';
			$this->assertSame( 'recorded', $recorder->record_capture( $order, $charge, 'EUR', $context )['state'] );
			$collection                = [
				'state'             => 'listed',
				'charge_id'         => 'ch_fixture',
				'reporting_context' => $context,
				'refunds'           => [ $refund ],
			];
			$charge['amount_refunded'] = 500;
			$this->assertSame( 'recorded', $recorder->record_capture( $order, $charge, 'EUR', $context )['state'] );
			$recorded = $recorder->record_refunds( $order, $collection, 'EUR', $context );
			$this->assertSame( 'recorded', $recorded['state'] );
			$this->assertCount( 2, $recorded['revisions'] );
			$manifest = $repository->read( array_merge( $scope, [ 'event_id' => $recorded['collection_id'] ] ) );
			$this->assertSame( $recorded['collection_revision'], $manifest['revision'] );
			$this->assertSame( [ 're_fixture' ], $manifest['event']['refund_ids'] );
			$this->assertSame( $recorded['revisions'], $manifest['event']['event_revisions'] );
			$empty_collection = array_merge( $collection, [ 'refunds' => [] ] );
			$this->assertSame( 'incomplete', $recorder->record_refunds( $order, $empty_collection, '', $context )['state'] );
			$order->delete_meta_data( '_charge_id' );
			$order->save_meta_data();
			$this->assertSame( 'incomplete', $recorder->record_refunds( $order, $empty_collection, 'EUR', $context )['state'] );
			$order->update_meta_data( '_charge_id', 'ch_fixture' );
			$order->save_meta_data();
			$empty = $recorder->record_refunds( $order, $empty_collection, 'EUR', $context );
			$this->assertSame( 'incomplete', $empty['state'] );
			$this->assertSame( 'refund_membership_shrank', $empty['reason'] );
			$this->assertSame( $manifest, $repository->read( array_merge( $scope, [ 'event_id' => $recorded['collection_id'] ] ) ) );
			$this->assertSame( 'found', $repository->read( array_merge( $scope, [ 'event_id' => $recorded['collection_id'] ] ) )['state'] );

			$this->assertSame( $recorded, $recorder->record_refunds( $order, $collection, 'EUR', $context ) );
			$debit    = $repository->read( array_merge( $scope, [ 'event_id' => 'txn_refund' ] ) )['event'];
			$reversal = $repository->read( array_merge( $scope, [ 'event_id' => 'txn_reversal' ] ) )['event'];
			$this->assertSame( -582, $debit['evidence']['amount'] );
			$this->assertSame( 'failed', $debit['refund_status'] );
			$this->assertSame( 580, $reversal['evidence']['amount'] );
			$this->assertSame( 570, $reversal['evidence']['net'] );
			$activity_dependencies                               = $recorded['revisions'];
			$activity_dependencies[ $intent_scope['event_id'] ]  = $repository->read( $intent_scope )['revision'];
			$activity_dependencies[ $scope['event_id'] ]         = $repository->read( $scope )['revision'];
			$activity_dependencies[ $recorded['collection_id'] ] = $recorded['collection_revision'];
			$activity_dependencies[ $recorded['membership_id'] ] = $recorded['membership_revision'];
			$this->assertSame( 'published', $reports->publish( $intent_scope, 'EUR', $reports->read( $intent_scope, 'EUR' )['revision'], $activity_dependencies )['state'] );
			$activity_rows = $activity_reader->read_for_order( $order->get_id(), 'EUR', $test_mode )['attempts'][0]['rows'];
			$this->assertCount( 3, $activity_rows );
			$this->assertSame(
				[
					'capture'                 => 193,
					'refund'                  => 0,
					'refund_failure_reversal' => 10,
				],
				array_column( $activity_rows, 'fee_amount', 'kind' )
			);
			$this->assertSame(
				[
					'capture'                 => 3997,
					'refund'                  => -582,
					'refund_failure_reversal' => 570,
				],
				array_column( $activity_rows, 'net_amount', 'kind' )
			);
			$this->assertSame(
				[
					'capture'                 => 4190,
					'refund'                  => -582,
					'refund_failure_reversal' => 580,
				],
				array_column( $activity_rows, 'amount', 'kind' )
			);
			$late_refund = $refund;
			$this->assert_metabox_activity( $activity_reader, $order, [ 'EUR 41.90', 'EUR -5.82', 'EUR 5.80', 'Fee: EUR 0.00', 'Net amount: EUR -5.82', 'Fee: EUR 0.10', 'Net amount: EUR 5.70' ] );
			$late_refund['status'] = 'pending';
			unset( $late_refund['failure_balance_transaction'] );
			$late_collection = array_merge( $collection, [ 'refunds' => [ $late_refund ] ] );
			$this->assertSame( 'incomplete', $recorder->record_refunds( $order, $late_collection, 'EUR', $context )['state'] );
			$this->assertSame( 'inconsistent', $repository->read( array_merge( $scope, [ 'event_id' => 'txn_refund' ] ) )['event']['state'] );
			$stale_activity = $activity_reader->read_for_order( $order->get_id(), 'EUR', $test_mode );
			$this->assertSame( 'stale', $stale_activity['attempts'][0]['state'] );
			$this->assertSame( [], $stale_activity['attempts'][0]['rows'] );
			$this->assert_metabox_activity( $activity_reader, $order, [] );
			$charge['balance_transaction']['amount'] = 4290;
			$charge['balance_transaction']['net']    = 4097;
			$this->assertSame( 'inconsistent', $recorder->record_capture( $order, $charge, 'EUR', $context )['state'] );
			$conflict = $repository->read( $scope )['event'];
			$this->assertSame( 4190, $conflict['previous']['evidence']['amount'] );
			$this->assertSame( 4290, $conflict['observed']['evidence']['amount'] );
			$charge['balance_transaction']['amount'] = 4390;
			$charge['balance_transaction']['net']    = 4197;
			$third                                   = $recorder->record_capture( $order, $charge, 'EUR', $context );
			$this->assertSame( 'inconsistent', $third['state'] );
			$this->assertSame( $third, $recorder->record_capture( $order, $charge, 'EUR', $context ) );
			$this->assertSame( $conflict, $repository->read( $scope )['event']['previous'] );
			$this->assertSame( 4390, $repository->read( $scope )['event']['observed']['evidence']['amount'] );
			$fresh = wc_get_order( $order->get_id() );
			$this->assertSame( 'GBP', $fresh->get_currency() );
			$this->assertEquals( 36, $fresh->get_total() );
			$historic                                = [
				'id'                      => 'ch_discovered',
				'payment_intent'          => 'pi_discovered',
				'amount'                  => 1200,
				'currency'                => 'gbp',
				'livemode'                => ! $test_mode,
				'wcpay_reporting_context' => $context,
				'metadata'                => [
					'order_id'  => (string) $fresh->get_id(),
					'order_key' => $fresh->get_order_key(),
					'site_url'  => esc_url( get_site_url() ),
				],
			];
			$wrong_identity                          = $historic;
			$wrong_identity['metadata']['order_key'] = 'wc_order_wrong';
			$this->assertSame( 'incomplete', $recorder->record_discovered_intent( $fresh->get_id(), 'ch_discovered', $wrong_identity, $context )['state'] );
			$this->assertSame( 'missing', $repository->read( array_merge( $intent_scope, [ 'event_id' => 'pi_discovered' ] ) )['state'] );
			$backfilled = $recorder->record_discovered_intent( $fresh->get_id(), 'ch_discovered', $historic, $context );
			$this->assertSame( 'recorded', $backfilled['state'] );
			$this->assertSame( $backfilled, $recorder->record_discovered_intent( $fresh->get_id(), 'ch_discovered', $historic, $context ) );
			$discovered_scope = array_merge( $intent_scope, [ 'event_id' => 'pi_discovered' ] );
			$this->assertSame( 1200, $repository->read( $discovered_scope )['event']['original_amount'] );
			$this->assertSame( 'found', $index->resolve( 'acct_fixture', 123, $test_mode, 'ch_discovered' )['state'] );
			$this->assertSame( $fresh->get_meta( '_charge_id' ), wc_get_order( $fresh->get_id() )->get_meta( '_charge_id' ) );
			$historic['id']             = 'ch_fetched';
			$historic['payment_intent'] = 'pi_fetched';
			$fetched_scope              = array_merge( $intent_scope, [ 'event_id' => 'pi_fetched' ] );
			$this->assertSame( 'missing', $repository->read( $fetched_scope )['state'] );
			$historic['paid']                = true;
			$historic['captured']            = true;
			$historic['status']              = 'succeeded';
			$historic['amount_captured']     = 1200;
			$historic['balance_transaction'] = [
				'id'       => 'txn_discovered',
				'amount'   => 1397,
				'fee'      => 60,
				'net'      => 1337,
				'currency' => 'eur',
				'created'  => 1700000000,
				'status'   => 'available',
			];
			$discovered_collector            = $this->createMock( \WCPay\Internal\Service\RefundHistoryCollection::class );
			$discovered_collector->expects( $this->once() )->method( 'collect' )->with( 'ch_fetched', $test_mode, $context )->willReturn(
				[
					'state'             => 'listed',
					'charge_id'         => 'ch_fetched',
					'reporting_context' => $context,
					'retrieval'         => [
						'started_at'   => time(),
						'completed_at' => time(),
					],
					'refunds'           => [],
				]
			);
			for ( $fetch = 0; $fetch < 2; ++$fetch ) {
				$request = $this->mock_wcpay_request( \WCPay\Core\Server\Request\Get_Charge::class, 1, 'ch_fetched' );
				$request->expects( $this->once() )->method( 'set_test_mode' )->with( $test_mode );
				$request->expects( $this->once() )->method( 'set_include_reporting_context' );
				$request->method( 'format_response' )->willReturn( $historic );
			}
			$discovered_recovery = new \WCPay\Internal\Service\PaymentEventRecovery( $repository, $recorder, $discovered_collector );
			$this->assertSame( 'observed', $discovered_recovery->recover_discovered( $fresh->get_id(), 'ch_fetched', $context, 'EUR' )['state'] );
			$discovered_activity = array_column( $activity_reader->read_for_order( $fresh->get_id(), 'EUR', $test_mode )['attempts'], null, 'intent_id' );
			$this->assertSame( 1397, $discovered_activity['pi_fetched']['rows'][0]['amount'] );
			$this->assertSame( 1337, $discovered_activity['pi_fetched']['rows'][0]['net_amount'] );
			$this->assert_metabox_activity( $activity_reader, $order, [ 'EUR 13.97', 'Fee: EUR 0.60', 'Net amount: EUR 13.37' ] );

			foreach ( [ $historic, new \WP_Error( 'synthetic_failure' ) ] as $fetch_result ) {
				$request = $this->mock_wcpay_request( \WCPay\Core\Server\Request\Get_Charge::class, 1, 'ch_fetched' );
				$request->method( 'format_response' )->willReturn( $fetch_result );
			}
			$this->assertSame( 'incomplete', $discovered_recovery->recover_discovered( $fresh->get_id(), 'ch_fetched', $context, 'EUR' )['state'] );
			$this->assertSame( 'stale', $reports->read( $fetched_scope, 'EUR' )['state'] );

		} finally {
			$fixture_id = $order->get_id();
			$order->delete( true );
			if ( $hpos ) {
				wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class )->process_batch( [ $fixture_id ] );
			}
			$db->query( "DROP TABLE {$db->options}" );
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_heads" );
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_revisions" );
			update_option( 'woocommerce_custom_orders_table_enabled', $previous_storage );
		}
	}

	/**
	 * Exercise real persisted evidence through the core metabox and provider hook.
	 *
	 * Opt in with WCPAY_TEST_CUSTOMER_HISTORY_INTEGRATION=1 when running against
	 * the companion core branch. Default WooPayments CI still exercises the
	 * recorder against its supported core version without assuming the new UI.
	 *
	 * @param \WCPay\Internal\Service\PaymentActivityReader $reader Fixture-backed production reader.
	 * @param \WC_Order $order Canonical fixture order.
	 * @param array $amounts Expected qualified monetary strings; empty means stale.
	 */
	private function assert_metabox_activity( \WCPay\Internal\Service\PaymentActivityReader $reader, \WC_Order $order, array $amounts ): void {
		if ( '1' !== getenv( 'WCPAY_TEST_CUSTOMER_HISTORY_INTEGRATION' ) ) {
			return;
		}
		$previous_user     = get_current_user_id();
		$previous_currency = get_option( 'woocommerce_currency' );
		$user              = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$buffer_level      = ob_get_level();
		try {
			wp_set_current_user( $user );
			update_option( 'woocommerce_currency', 'EUR' );
			$reader->init_hooks();
			$history = new \Automattic\WooCommerce\Internal\Admin\Orders\MetaBoxes\CustomerHistory();
			ob_start();
			$history->output( $order );
			$html = ob_get_clean();
			$this->assertStringContainsString( 'Payment activity for this order', $html );
			$this->assertStringContainsString( 'Complete payment total unavailable', $html );
			foreach ( $amounts as $amount ) {
				$this->assertStringContainsString( $amount, $html );
			}
			if ( ! $amounts ) {
				$this->assertStringNotContainsString( 'EUR 41.90', $html );
				$this->assertStringNotContainsString( 'EUR -5.82', $html );
				$this->assertStringNotContainsString( 'EUR 5.80', $html );
				$this->assertStringContainsString( 'Payment activity is unavailable', $html );
			}
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			remove_filter( 'woocommerce_customer_history_payment_activity', [ $reader, 'provide_customer_history' ], 10 );
			wp_set_current_user( $previous_user );
			update_option( 'woocommerce_currency', $previous_currency );
			wp_delete_user( $user );
		}
	}

	/** @return array Storage and mode combinations. */
	public function storage_provider(): array {
		return [ [ false, false ], [ true, false ], [ false, true ], [ true, true ] ];
	}
}

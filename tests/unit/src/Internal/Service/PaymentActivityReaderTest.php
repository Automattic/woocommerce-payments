<?php
/**
 * Reject malformed stored payment observations at the monetary read boundary.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\PaymentActivityReader;
use WCPay\Internal\Service\PaymentReceiptIndex;
use WCPay\Internal\Service\PaymentReportRepository;

/** Generic event storage does not establish monetary validity. */
class PaymentActivityReaderTest extends \WCPAY_UnitTestCase {
	/** The prototype admin provider authorizes before reading and registers once. */
	public function test_customer_history_provider() {
		$order = new \WC_Order();
		$order->save();
		$previous_user = get_current_user_id();
		$user          = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$index         = $this->createMock( PaymentReceiptIndex::class );
		$index->expects( $this->once() )->method( 'find_for_order' )->with( $order->get_id(), 10, '' )->willReturn(
			[
				'state'       => 'unknown',
				'receipts'    => [],
				'has_more'    => false,
				'next_cursor' => '',
			]
		);
		$reader  = new PaymentActivityReader( $index, $this->createMock( PaymentReportRepository::class ) );
		$request = [
			'order_id'  => $order->get_id(),
			'currency'  => 'EUR',
			'test_mode' => false,
			'after'     => '',
		];
		$other   = [ 'other_provider' => [ 'state' => 'unknown' ] ];
		// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment -- Exercises the prototype provider contract; does not introduce public hooks.
		try {
			$reader->init_hooks();
			$reader->init_hooks();
			wp_set_current_user( 0 );
			$this->assertSame( $other, apply_filters( 'woocommerce_customer_history_payment_activity', $other, $request ) );
			wp_set_current_user( $user );
			$this->assertSame( $other, apply_filters( 'woocommerce_customer_history_payment_activity', $other, array_merge( $request, [ 'provider' => 'another_gateway' ] ) ) );
			$this->assertSame( $other, apply_filters( 'woocommerce_customer_history_payment_activity', $other, array_merge( $request, [ 'test_mode' => 'false' ] ) ) );
			$result = apply_filters( 'woocommerce_customer_history_payment_activity', $other, $request );
			$this->assertSame( $other['other_provider'], $result['other_provider'] );
			$this->assertSame( 'unknown', $result['woocommerce_payments']['state'] );
			$this->assertFalse( $result['woocommerce_payments']['test_mode'] );
			$this->assertNull( $result['woocommerce_payments']['total'] );
		} finally {
			remove_filter( 'woocommerce_customer_history_payment_activity', [ $reader, 'provide_customer_history' ], 10 );
			wp_set_current_user( $previous_user );
			$order->delete( true );
			wp_delete_user( $user );
		}
		// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingHookComment
	}

	/** Missing original facts and same-currency contradictions cannot become display money. */
	public function test_rejects_malformed_observations() {
		$scope = [
			'event_id'  => 'pi_fixture',
			'test_mode' => false,
		];
		$index = $this->createMock( PaymentReceiptIndex::class );
		$index->method( 'find_for_order' )->willReturn(
			[
				'state'       => 'known',
				'receipts'    => [ [ 'scope' => $scope ] ],
				'has_more'    => false,
				'next_cursor' => '',
			]
		);
		$reports = $this->createMock( PaymentReportRepository::class );
		$receipt = [
			'kind'      => 'intent_context',
			'charge_id' => 'ch_fixture',
		];
		$capture = [
			'kind'     => 'capture',
			'evidence' => [
				'state'                  => 'ready',
				'basis'                  => 'captured_payment_gross',
				'charge_id'              => 'ch_fixture',
				'balance_transaction_id' => 'txn_fixture',
				'amount'                 => 4190,
				'currency'               => 'EUR',
				'source_created'         => 1700000000,
			],
		];
		$report  = [
			'state'     => 'observed',
			'revision'  => 'test',
			'retrieval' => null,
			'events'    => [
				'pi_fixture'  => [
					'event'    => $receipt,
					'revision' => 'receipt',
				],
				'txn_fixture' => [
					'event'    => $capture,
					'revision' => 'capture',
				],
			],
		];
		$reports->method( 'read_observations' )->willReturnCallback(
			static function () use ( &$report ) {
				return $report;
			}
		);
		$reader = new PaymentActivityReader( $index, $reports );
		$this->assertSame( 'unqualified', $reader->read_for_order( 1, 'EUR', false )['attempts'][0]['state'] );
		foreach ( [
			'original_amount'   => 3600,
			'original_currency' => 'EUR',
		] as $key => $value ) {
			$report['events']['pi_fixture']['event'][ $key ]              = $value;
			$report['events']['txn_fixture']['event']['evidence'][ $key ] = $value;
		}
		$this->assertSame( 'unqualified', $reader->read_for_order( 1, 'EUR', false )['attempts'][0]['state'] );
		$report['events']['txn_fixture']['event']['evidence']['amount'] = 3600;
		$this->assertSame( 'observed', $reader->read_for_order( 1, 'EUR', false )['attempts'][0]['state'] );
		$row = $reader->read_for_order( 1, 'EUR', false )['attempts'][0]['rows'][0];
		$this->assertSame( 'unavailable', $row['net_state'] );
		$this->assertNull( $row['net_amount'] );
		$evidence               = &$report['events']['txn_fixture']['event']['evidence'];
		$evidence['net_state']  = 'ready';
		$evidence['fee_amount'] = 193;
		$evidence['net_amount'] = 3407;
		$row                    = $reader->read_for_order( 1, 'EUR', false )['attempts'][0]['rows'][0];
		$this->assertSame( 193, $row['fee_amount'] );
		$this->assertSame( 3407, $row['net_amount'] );
		$evidence['net_amount'] = 3408;
		$row                    = $reader->read_for_order( 1, 'EUR', false )['attempts'][0]['rows'][0];
		$this->assertSame( 3600, $row['amount'] );
		$this->assertSame( 'unavailable', $row['net_state'] );
		$this->assertNull( $row['fee_amount'] );
	}
}
